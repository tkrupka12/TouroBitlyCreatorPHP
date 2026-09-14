<?php

/**
 * Manage users: list accounts, create a user, reset a password, change role, or remove a user.
 */

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

$user = require_group_admin();
$conn = get_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === '') {
        if (isset($_POST['link_action'])) {
            $action = 'delete';
        } elseif (isset($_POST['user_id']) && isset($_POST['password'])) {
            $action = 'reset-password';
        } elseif (isset($_POST['user_id']) && isset($_POST['role'])) {
            $action = 'set-role';
        } else {
            $action = 'create';
        }
    }

    if ($action === 'reset-password') {
        $user_id = (int) ($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
        $new_password = $_POST['password'] ?? '';
        if (strlen($new_password) < 6) {
            flash('Password must be at least 6 characters.');
            redirect(url_for('admin_users'));
        }

        $target = load_user_row($conn, $user_id);
        if (!$target) {
            flash('User not found.');
            redirect(url_for('admin_users'));
        }
        if (!can_manage_user($user, $target)) {
            abort(403);
        }

        $hashed = generate_password_hash($new_password);
        $conn->prepare('UPDATE users SET password = ?, pending_reset = 0 WHERE id = ?')->execute([$hashed, $user_id]);

        $_SESSION['new_user_credentials'] = [
            'users_name' => $target['users_name'],
            'username_email' => $target['username_email'],
            'password' => $new_password,
            'login_url' => url_for('login', [], true),
            'role' => $target['role'],
            'group_name' => group_name_for($conn, $target['group_id']),
        ];
        flash("Password reset for \"{$target['display_name']}\".");
        redirect(url_for('admin_users'));
    }

    if ($action === 'set-role') {
        $user_id = (int) ($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
        $new_role = $_POST['role'] ?? '';

        if ($user_id === $user['id']) {
            flash('You cannot change your own role.');
            redirect(url_for('admin_users'));
        }
        if (!in_array($new_role, ASSIGNABLE_ROLES, true)) {
            flash('Choose a valid role.');
            redirect(url_for('admin_users'));
        }

        $target = load_user_row($conn, $user_id);
        if (!$target) {
            flash('User not found.');
            redirect(url_for('admin_users'));
        }
        if (!can_manage_user($user, $target)) {
            abort(403);
        }
        if ($new_role === ROLE_SUPER_ADMIN && !is_super($user)) {
            flash('Only a super admin can promote someone to super admin.');
            redirect(url_for('admin_users'));
        }
        if ($target['role'] === ROLE_SUPER_ADMIN && $new_role !== ROLE_SUPER_ADMIN && super_admin_count($conn) <= 1) {
            flash('Cannot demote the last remaining super admin.');
            redirect(url_for('admin_users'));
        }

        if ($new_role === ROLE_SUPER_ADMIN) {
            $new_group_id = null;
        } elseif (is_super($user)) {
            $requested_group = $_POST['group_id'] ?? '';
            if ($requested_group === '' && $target['group_id'] !== null) {
                $new_group_id = $target['group_id'];
            } else {
                $new_group_id = is_numeric($requested_group) ? (int) $requested_group : 0;
                if ($new_group_id <= 0 || !group_exists($conn, $new_group_id)) {
                    flash('Choose a group for that user.');
                    redirect(url_for('admin_users'));
                }
            }
        } else {
            $new_group_id = $target['group_id'];
        }

        $conn->prepare('UPDATE users SET role = ?, group_id = ?, is_admin = ? WHERE id = ?')
            ->execute([$new_role, $new_group_id, $new_role === ROLE_SUPER_ADMIN ? 1 : 0, $user_id]);

        $group_note = $new_group_id === null ? '' : ' in ' . group_name_for($conn, $new_group_id);
        flash("{$target['display_name']} is now a " . role_label($new_role) . $group_note . '.');
        redirect(url_for('admin_users'));
    }

    if ($action === 'delete') {
        $user_id = (int) ($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
        if ($user_id === $user['id']) {
            flash('You cannot delete your own account.');
            redirect(url_for('admin_users'));
        }

        $target = load_user_row($conn, $user_id);
        if (!$target) {
            flash('User not found.');
            redirect(url_for('admin_users'));
        }
        if (!can_manage_user($user, $target)) {
            abort(403);
        }

        if ($target['role'] === ROLE_SUPER_ADMIN && super_admin_count($conn) <= 1) {
            flash('Cannot delete the last remaining super admin.');
            redirect(url_for('admin_users'));
        }

        $link_action = $_POST['link_action'] ?? '';

        if ($link_action === 'delete') {
            $conn->prepare('DELETE FROM links WHERE user_id = ?')->execute([$user_id]);
            $conn->prepare('UPDATE links SET updated_by = NULL WHERE updated_by = ?')->execute([$user_id]);
            $conn->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
            flash("{$target['display_name']} removed. Their links were deleted.");
            redirect(url_for('admin_users'));
        }

        if ($link_action !== 'transfer') {
            flash('Choose whether to transfer or delete their links.');
            redirect(url_for('admin_users'));
        }

        $transfer_to = (int) ($_POST['transfer_to'] ?? 0);
        $recipient = load_user_row($conn, $transfer_to);
        if (!$recipient || $transfer_to === $user_id) {
            flash('Pick someone to receive their links.');
            redirect(url_for('admin_users'));
        }
        if (!can_manage_user($user, $recipient) && (int) $recipient['id'] !== (int) $user['id']) {
            flash('You can only transfer links to someone you can manage, or to yourself.');
            redirect(url_for('admin_users'));
        }

        if ($recipient['group_id'] !== null) {
            $conn->prepare('UPDATE links SET user_id = ?, group_id = ? WHERE user_id = ?')
                ->execute([$recipient['id'], $recipient['group_id'], $user_id]);
        } else {
            $conn->prepare('UPDATE links SET user_id = ? WHERE user_id = ?')
                ->execute([$recipient['id'], $user_id]);
        }
        $conn->prepare('UPDATE links SET updated_by = ? WHERE updated_by = ?')
            ->execute([$recipient['id'], $user_id]);
        $conn->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
        flash("{$target['display_name']} removed. Their links now belong to {$recipient['display_name']}.");
        redirect(url_for('admin_users'));
    }

    $new_users_name = trim($_POST['users_name'] ?? '');
    $new_username_email = trim($_POST['username_email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $requested_role = $_POST['role'] ?? ROLE_USER;

    if ($new_users_name === '') {
        flash('Name is required.');
        redirect(url_for('admin_users'));
    }
    if ($new_username_email === '') {
        flash('Email is required.');
        redirect(url_for('admin_users'));
    }
    if (!filter_var($new_username_email, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid email address.');
        redirect(url_for('admin_users'));
    }
    if (strlen($new_password) < 6) {
        flash('Password must be at least 6 characters.');
        redirect(url_for('admin_users'));
    }
    if (!in_array($requested_role, ASSIGNABLE_ROLES, true)) {
        flash('Choose a valid role.');
        redirect(url_for('admin_users'));
    }
    if ($requested_role === ROLE_SUPER_ADMIN && !is_super($user)) {
        flash('Only a super admin can create another super admin.');
        redirect(url_for('admin_users'));
    }

    if ($requested_role === ROLE_SUPER_ADMIN) {
        $new_group_id = null;
    } elseif (is_super($user)) {
        $requested_group = $_POST['group_id'] ?? '';
        $new_group_id = is_numeric($requested_group) ? (int) $requested_group : 0;
        if ($new_group_id <= 0 || !group_exists($conn, $new_group_id)) {
            flash('Choose a group for the new user.');
            redirect(url_for('admin_users'));
        }
    } else {
        $new_group_id = $user['group_id'];
        if ($new_group_id === null) {
            flash('Your account is not in a group, so you cannot add users.');
            redirect(url_for('admin_users'));
        }
    }

    $hashed = generate_password_hash($new_password);
    try {
        $conn->prepare(
            'INSERT INTO users (username_email, users_name, password, is_admin, role, group_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $new_username_email,
            $new_users_name,
            $hashed,
            $requested_role === ROLE_SUPER_ADMIN ? 1 : 0,
            $requested_role,
            $new_group_id,
        ]);
    } catch (PDOException $ex) {
        if (is_unique_violation($ex)) {
            flash('An account with that email already exists.');
            redirect(url_for('admin_users'));
        }
        throw $ex;
    }

    $_SESSION['new_user_credentials'] = [
        'users_name' => $new_users_name,
        'username_email' => $new_username_email,
        'password' => $new_password,
        'login_url' => url_for('login', [], true),
        'role' => $requested_role,
        'group_name' => group_name_for($conn, $new_group_id),
    ];
    flash("Added \"{$new_users_name}\" as " . role_label($requested_role) . '.');
    redirect(url_for('admin_users'));
}

$sql = '
    SELECT users.id, users.users_name, users.username_email, users.role, users.pending_reset,
           users.group_id, "groups".name AS group_name
    FROM users
    LEFT JOIN "groups" ON "groups".id = users.group_id
';
$params = [];
if (!is_super($user)) {
    $sql .= ' WHERE users.group_id = ? ';
    $params[] = (int) $user['group_id'];
}
$sql .= ' ORDER BY users.pending_reset DESC, users.role ASC, users.id ASC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$creds = $_SESSION['new_user_credentials'] ?? null;
unset($_SESSION['new_user_credentials']);
render('admin_users', [
    'users' => $users,
    'new_creds' => $creds,
    'groups' => is_super($user) ? all_groups($conn) : [],
]);
