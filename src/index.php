<?php

require __DIR__ . '/lib.php';

init_db();
start_app_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = request_path();

if ($path === '/' && $method === 'GET') {
    $user = require_login();
    [$scope_where, $scope_params] = link_scope_clause($user);
    $stmt = get_db()->prepare("
        SELECT links.id, links.short_url, links.url,
               COALESCE(NULLIF(creators.users_name, ''), creators.username_email), links.user_id,
               links.created_at, links.updated_at, links.expires_at, links.notes,
               COALESCE(NULLIF(editors.users_name, ''), editors.username_email), links.clicks,
               links.group_id, link_groups.name
        FROM links
        JOIN users AS creators ON creators.id = links.user_id
        LEFT JOIN users AS editors ON editors.id = links.updated_by
        LEFT JOIN \"groups\" AS link_groups ON link_groups.id = links.group_id
        {$scope_where}
        ORDER BY links.id DESC
    ");
    $stmt->execute($scope_params);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    $all_links = [];
    foreach ($rows as $r) {
        if (is_expired($r[7])) {
            continue;
        }
        $link_group_id = $r[11] === null ? null : (int) $r[11];
        $all_links[] = [
            'id' => $r[0],
            'short_url' => $r[1],
            'url' => $r[2],
            'creator' => $r[3],
            'user_id' => $r[4],
            'created_at' => $r[5],
            'updated_at' => $r[6],
            'expires_at' => $r[7],
            'notes' => $r[8] ?: '',
            'updated_by' => $r[9],
            'clicks' => $r[10] ?: 0,
            'group_id' => $link_group_id,
            'group_name' => $r[12],
            'expired' => is_expired($r[7]),
            'can_delete' => can_delete_link($user, $link_group_id, (int) $r[4]),
        ];
    }
    render('index', [
        'all_links' => $all_links,
        'groups' => is_super($user) ? all_groups(get_db()) : [],
    ]);
}

if ($path === '/login') {
    if ($method === 'POST') {
        $username_email = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = ($_POST['remember_me'] ?? '') === '1';

        if ($username_email === '' || $password === '') {
            flash('Email and password are required.');
            redirect(url_for('login'));
        }

        $stmt = get_db()->prepare(
            'SELECT id, username_email, users_name, password, role, group_id FROM users WHERE username_email = ?'
        );
        $stmt->execute([$username_email]);
        $user_row = $stmt->fetch(PDO::FETCH_NUM);

        if ($user_row && $user_row[3] && check_password_hash($user_row[3], $password)) {
            login_user([
                'id' => $user_row[0],
                'username_email' => $user_row[1],
                'users_name' => $user_row[2],
                'role' => $user_row[4],
                'group_id' => $user_row[5],
            ], $remember);
            redirect(url_for('index'));
        }

        flash('Invalid email or password.');
        redirect(url_for('login'));
    }

    if (($_GET['reason'] ?? '') === 'timeout') {
        flash('Your session expired. Please log in again.');
    }
    render('login');
}

if ($path === '/admin/users') {
    $user = require_group_admin();
    $conn = get_db();

    if ($method === 'POST') {
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

        // Super admins are global and hold no group; everyone else needs one.
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
    // Keyed by column name so adding a column can never shift what the template reads.
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $creds = $_SESSION['new_user_credentials'] ?? null;
    unset($_SESSION['new_user_credentials']);
    render('admin_users', [
        'users' => $users,
        'new_creds' => $creds,
        'groups' => is_super($user) ? all_groups($conn) : [],
    ]);
}

if (preg_match('#^/admin/users/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    $current = require_group_admin();
    $user_id = (int) $m[1];
    if ($user_id === $current['id']) {
        flash('You cannot delete your own account.');
        redirect(url_for('admin_users'));
    }

    $conn = get_db();
    $target = load_user_row($conn, $user_id);
    if (!$target) {
        flash('User not found.');
        redirect(url_for('admin_users'));
    }
    if (!can_manage_user($current, $target)) {
        abort(403);
    }

    if ($target['role'] === ROLE_SUPER_ADMIN && super_admin_count($conn) <= 1) {
        flash('Cannot delete the last remaining super admin.');
        redirect(url_for('admin_users'));
    }

    $conn->prepare('DELETE FROM links WHERE user_id = ?')->execute([$user_id]);
    $conn->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
    flash("{$target['display_name']} removed.");
    redirect(url_for('admin_users'));
}

if (preg_match('#^/admin/users/(\d+)/role$#', $path, $m) && $method === 'POST') {
    $current = require_group_admin();
    $user_id = (int) $m[1];
    $new_role = $_POST['role'] ?? '';

    if ($user_id === $current['id']) {
        flash('You cannot change your own role.');
        redirect(url_for('admin_users'));
    }
    if (!in_array($new_role, ASSIGNABLE_ROLES, true)) {
        flash('Choose a valid role.');
        redirect(url_for('admin_users'));
    }

    $conn = get_db();
    $target = load_user_row($conn, $user_id);
    if (!$target) {
        flash('User not found.');
        redirect(url_for('admin_users'));
    }
    if (!can_manage_user($current, $target)) {
        abort(403);
    }
    if ($new_role === ROLE_SUPER_ADMIN && !is_super($current)) {
        flash('Only a super admin can promote someone to super admin.');
        redirect(url_for('admin_users'));
    }
    if ($target['role'] === ROLE_SUPER_ADMIN && $new_role !== ROLE_SUPER_ADMIN && super_admin_count($conn) <= 1) {
        flash('Cannot demote the last remaining super admin.');
        redirect(url_for('admin_users'));
    }

    if ($new_role === ROLE_SUPER_ADMIN) {
        $new_group_id = null;
    } elseif (is_super($current)) {
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
        // Group admins can change roles inside their group but never move people out of it.
        $new_group_id = $target['group_id'];
    }

    $conn->prepare('UPDATE users SET role = ?, group_id = ?, is_admin = ? WHERE id = ?')
        ->execute([$new_role, $new_group_id, $new_role === ROLE_SUPER_ADMIN ? 1 : 0, $user_id]);

    $group_note = $new_group_id === null ? '' : ' in ' . group_name_for($conn, $new_group_id);
    flash("{$target['display_name']} is now a " . role_label($new_role) . $group_note . '.');
    redirect(url_for('admin_users'));
}

if ($path === '/admin/groups') {
    require_super_admin();
    $conn = get_db();

    if ($method === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('Group name is required.');
            redirect(url_for('admin_groups'));
        }
        if (mb_strlen($name) > GROUP_NAME_MAX_LENGTH) {
            flash('Group name must be ' . GROUP_NAME_MAX_LENGTH . ' characters or fewer.');
            redirect(url_for('admin_groups'));
        }
        try {
            $conn->prepare('INSERT INTO "groups" (name, created_at) VALUES (?, ?)')->execute([$name, now_str()]);
        } catch (PDOException $ex) {
            if (is_unique_violation($ex)) {
                flash('A group with that name already exists.');
                redirect(url_for('admin_groups'));
            }
            throw $ex;
        }
        flash("Group \"{$name}\" created.");
        redirect(url_for('admin_groups'));
    }

    $groups = $conn->query('
        SELECT g.id, g.name, g.created_at,
               (SELECT COUNT(*) FROM users WHERE users.group_id = g.id) AS member_count,
               (SELECT COUNT(*) FROM links WHERE links.group_id = g.id) AS link_count
        FROM "groups" AS g
        ORDER BY g.name COLLATE NOCASE ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
    render('admin_groups', ['groups' => $groups]);
}

if (preg_match('#^/admin/groups/(\d+)/rename$#', $path, $m) && $method === 'POST') {
    require_super_admin();
    $group_id = (int) $m[1];
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        flash('Group name is required.');
        redirect(url_for('admin_groups'));
    }
    if (mb_strlen($name) > GROUP_NAME_MAX_LENGTH) {
        flash('Group name must be ' . GROUP_NAME_MAX_LENGTH . ' characters or fewer.');
        redirect(url_for('admin_groups'));
    }

    $conn = get_db();
    if (!group_exists($conn, $group_id)) {
        flash('Group not found.');
        redirect(url_for('admin_groups'));
    }

    try {
        $conn->prepare('UPDATE "groups" SET name = ? WHERE id = ?')->execute([$name, $group_id]);
    } catch (PDOException $ex) {
        if (is_unique_violation($ex)) {
            flash('A group with that name already exists.');
            redirect(url_for('admin_groups'));
        }
        throw $ex;
    }

    flash("Group renamed to \"{$name}\".");
    redirect(url_for('admin_groups'));
}

if (preg_match('#^/admin/groups/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_super_admin();
    $group_id = (int) $m[1];
    $conn = get_db();

    $name = group_name_for($conn, $group_id);
    if ($name === null) {
        flash('Group not found.');
        redirect(url_for('admin_groups'));
    }

    // Refuse rather than cascade: deleting a group must never silently drop links or users.
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE group_id = ?');
    $stmt->execute([$group_id]);
    $member_count = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare('SELECT COUNT(*) FROM links WHERE group_id = ?');
    $stmt->execute([$group_id]);
    $link_count = (int) $stmt->fetchColumn();

    if ($member_count > 0 || $link_count > 0) {
        flash("Cannot delete \"{$name}\": it still has {$member_count} member(s) and {$link_count} link(s). Move or remove them first.");
        redirect(url_for('admin_groups'));
    }

    $conn->prepare('DELETE FROM "groups" WHERE id = ?')->execute([$group_id]);
    flash("Group \"{$name}\" deleted.");
    redirect(url_for('admin_groups'));
}

if ($path === '/admin/expired' && $method === 'GET') {
    $user = require_group_admin();
    [$scope_where, $scope_params] = link_scope_clause($user);
    $stmt = get_db()->prepare("
        SELECT links.id, links.short_url, links.url,
               COALESCE(NULLIF(creators.users_name, ''), creators.username_email), links.user_id,
               links.created_at, links.updated_at, links.expires_at, links.notes,
               COALESCE(NULLIF(editors.users_name, ''), editors.username_email), links.clicks,
               links.group_id, link_groups.name
        FROM links
        JOIN users AS creators ON creators.id = links.user_id
        LEFT JOIN users AS editors ON editors.id = links.updated_by
        LEFT JOIN \"groups\" AS link_groups ON link_groups.id = links.group_id
        {$scope_where}
        ORDER BY links.expires_at DESC
    ");
    $stmt->execute($scope_params);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    $expired_links = [];
    foreach ($rows as $r) {
        if (!is_expired($r[7])) {
            continue;
        }
        $expired_links[] = [
            'id' => $r[0],
            'short_url' => $r[1],
            'url' => $r[2],
            'creator' => $r[3],
            'user_id' => $r[4],
            'created_at' => $r[5],
            'updated_at' => $r[6],
            'expires_at' => $r[7],
            'notes' => $r[8] ?: '',
            'updated_by' => $r[9],
            'clicks' => $r[10] ?: 0,
            'group_id' => $r[11] === null ? null : (int) $r[11],
            'group_name' => $r[12],
        ];
    }
    render('admin_expired', ['expired_links' => $expired_links]);
}

if ($path === '/logout' && $method === 'GET') {
    require_login();
    unset($_SESSION['remember_me']);
    logout_user();
    redirect(url_for('login'));
}

if ($path === '/session/ping' && $method === 'GET') {
    require_login();
    $_SESSION['last_activity'] = time();
    http_response_code(204);
    exit;
}

if ($path === '/check-username' && $method === 'GET') {
    $username_email = trim($_GET['u'] ?? '');
    if ($username_email === '') {
        json_response(['exists' => false]);
    }
    $stmt = get_db()->prepare('SELECT 1 FROM users WHERE username_email = ?');
    $stmt->execute([$username_email]);
    json_response(['exists' => (bool) $stmt->fetchColumn()]);
}

if ($path === '/forgot-password') {
    if ($method === 'POST') {
        $username_email = trim($_POST['username_email'] ?? '');
        if ($username_email !== '') {
            get_db()->prepare('UPDATE users SET pending_reset = 1 WHERE username_email = ?')
                ->execute([$username_email]);
        }
        flash('If that account exists, an administrator has been notified. Contact them to receive a new password.');
        redirect(url_for('login'));
    }
    render('forgot_password');
}

if (preg_match('#^/admin/users/(\d+)/reset-password$#', $path, $m) && $method === 'POST') {
    $current = require_group_admin();
    $user_id = (int) $m[1];
    $new_password = $_POST['password'] ?? '';
    if (strlen($new_password) < 6) {
        flash('Password must be at least 6 characters.');
        redirect(url_for('admin_users'));
    }

    $conn = get_db();
    $target = load_user_row($conn, $user_id);
    if (!$target) {
        flash('User not found.');
        redirect(url_for('admin_users'));
    }
    if (!can_manage_user($current, $target)) {
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

if ($path === '/profile' && $method === 'GET') {
    require_login();
    render('profile');
}

if ($path === '/profile/name' && $method === 'POST') {
    $user = require_login();
    $new_users_name = trim($_POST['users_name'] ?? '');

    if ($new_users_name === '') {
        flash('Your name cannot be empty.');
        redirect(url_for('profile'));
    }

    get_db()->prepare('UPDATE users SET users_name = ? WHERE id = ?')->execute([$new_users_name, $user['id']]);
    $_SESSION['users_name'] = $new_users_name;
    flash('Name updated.');
    redirect(url_for('profile'));
}

if ($path === '/profile/email' && $method === 'POST') {
    $user = require_login();
    $new_username_email = trim($_POST['username_email'] ?? '');

    if ($new_username_email === '') {
        flash('Email cannot be empty.');
        redirect(url_for('profile'));
    }
    if (!filter_var($new_username_email, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid email address.');
        redirect(url_for('profile'));
    }
    if ($new_username_email === $user['username_email']) {
        flash('That is already your email.');
        redirect(url_for('profile'));
    }

    try {
        get_db()->prepare('UPDATE users SET username_email = ? WHERE id = ?')
            ->execute([$new_username_email, $user['id']]);
    } catch (PDOException $ex) {
        if (is_unique_violation($ex)) {
            flash('That email is already taken.');
            redirect(url_for('profile'));
        }
        throw $ex;
    }

    $_SESSION['username_email'] = $new_username_email;
    flash('Email updated.');
    redirect(url_for('profile'));
}

if ($path === '/profile/password' && $method === 'POST') {
    $user = require_login();
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '') {
        flash('New password cannot be empty.');
        redirect(url_for('profile'));
    }
    if (strlen($new_password) < 6) {
        flash('New password must be at least 6 characters.');
        redirect(url_for('profile'));
    }
    if ($new_password !== $confirm_password) {
        flash('New password and confirmation do not match.');
        redirect(url_for('profile'));
    }

    $conn = get_db();
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if (!$row || !check_password_hash($row[0], $current_password)) {
        flash('Current password is incorrect.');
        redirect(url_for('profile'));
    }

    $conn->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([generate_password_hash($new_password), $user['id']]);
    flash('Password updated.');
    redirect(url_for('profile'));
}

if ($path === '/shorten' && $method === 'POST') {
    $user = require_login();
    $data = request_json();
    $original_url = trim($data['url'] ?? '');
    $custom_short_url = trim($data['short_url'] ?? '');
    $mode = ($data['mode'] ?? 'custom') === 'random' ? 'random' : 'custom';
    $raw_expires = $data['expires_at'] ?? null;
    $notes = trim($data['notes'] ?? '');
    $notes = $notes === '' ? null : $notes;

    if ($original_url === '') {
        json_response(['error' => 'A destination URL is required.'], 400);
    }
    if ($mode === 'custom' && $custom_short_url === '') {
        json_response(['error' => 'Enter a custom short URL or choose to generate a random one.'], 400);
    }

    if (!str_starts_with($original_url, 'http://') && !str_starts_with($original_url, 'https://')) {
        $original_url = 'https://' . $original_url;
    }

    if (!is_valid_url($original_url)) {
        json_response(['error' => 'Enter a valid URL.'], 400);
    }

    if ($mode === 'random') {
        $custom_short_url = generate_unique_short_url(get_db());
        if ($custom_short_url === null) {
            json_response(['error' => 'Could not generate an unused short URL. Please try again.'], 500);
        }
    } else {
        if ($custom_short_url !== strtolower($custom_short_url)) {
            json_response(['error' => 'Custom short URL must be lowercase.'], 400);
        }
        if (!is_valid_short_url($custom_short_url)) {
            json_response([
                'error' => 'Custom short URL must be 1-64 lowercase letters, numbers, dashes, or underscores and cannot be a reserved route.',
            ], 400);
        }
    }

    // A super admin has no group of their own, so they pick the owning group explicitly.
    if (is_super($user)) {
        $requested_group = $data['group_id'] ?? null;
        $target_group_id = is_numeric($requested_group) ? (int) $requested_group : 0;
        if ($target_group_id <= 0 || !group_exists(get_db(), $target_group_id)) {
            json_response(['error' => 'Choose a group for this link.'], 400);
        }
    } else {
        $target_group_id = $user['group_id'];
        if ($target_group_id === null) {
            json_response(['error' => 'Your account is not in a group yet. Ask an admin to add you to one.'], 400);
        }
    }

    $expires_at = parse_expires_at($raw_expires);
    if ($raw_expires && !$expires_at) {
        json_response(['error' => 'Invalid expiration date.'], 400);
    }
    if ($expires_at) {
        $dt = DateTime::createFromFormat(TIMESTAMP_FMT, $expires_at);
        if ($dt && $dt->getTimestamp() <= time()) {
            json_response(['error' => 'Expiration date must be in the future.'], 400);
        }
    }

    try {
        get_db()->prepare(
            'INSERT INTO links (user_id, group_id, short_url, url, created_at, expires_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$user['id'], $target_group_id, $custom_short_url, $original_url, now_str(), $expires_at, $notes]);
    } catch (PDOException $ex) {
        if (is_unique_violation($ex)) {
            json_response(['error' => 'This custom short URL is already taken.'], 400);
        }
        throw $ex;
    }

    $short_link = url_for('redirect_to_url', ['short_url' => $custom_short_url], true);
    json_response(['success' => true, 'short_link' => $short_link, 'short_url' => $custom_short_url]);
}

if (preg_match('#^/links/(\d+)/edit$#', $path, $m)) {
    $user = require_login();
    $link_id = (int) $m[1];
    $stmt = get_db()->prepare(
        'SELECT id, user_id, short_url, url, created_at, updated_at, expires_at, notes, group_id FROM links WHERE id = ?'
    );
    $stmt->execute([$link_id]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if (!$row) {
        abort(404);
    }
    if (!link_visible_to($user, $row[8] === null ? null : (int) $row[8])) {
        abort(403);
    }

    $link = [
        'id' => $row[0],
        'user_id' => $row[1],
        'short_url' => $row[2],
        'url' => $row[3],
        'created_at' => $row[4],
        'updated_at' => $row[5],
        'expires_at' => $row[6],
        'notes' => $row[7] ?: '',
        'group_id' => $row[8] === null ? null : (int) $row[8],
    ];

    if ($method === 'POST') {
        $new_url = trim($_POST['url'] ?? '');
        $raw_expires = $_POST['expires_at'] ?? null;
        $clear_expiry = ($_POST['clear_expiry'] ?? '') === '1';
        $new_notes = trim($_POST['notes'] ?? '');
        $new_notes = $new_notes === '' ? null : $new_notes;

        if ($new_url === '') {
            flash('URL is required.');
            redirect(url_for('edit_link', ['link_id' => $link_id]));
        }

        if (!str_starts_with($new_url, 'http://') && !str_starts_with($new_url, 'https://')) {
            $new_url = 'https://' . $new_url;
        }
        if (!is_valid_url($new_url)) {
            flash('Enter a valid URL.');
            redirect(url_for('edit_link', ['link_id' => $link_id]));
        }

        if ($clear_expiry) {
            $new_expires = null;
        } elseif ($raw_expires) {
            $new_expires = parse_expires_at($raw_expires);
            if (!$new_expires) {
                flash('Invalid expiration date.');
                redirect(url_for('edit_link', ['link_id' => $link_id]));
            }
            $dt = DateTime::createFromFormat(TIMESTAMP_FMT, $new_expires);
            if ($dt && $dt->getTimestamp() <= time()) {
                flash('Expiration date must be in the future.');
                redirect(url_for('edit_link', ['link_id' => $link_id]));
            }
        } else {
            $new_expires = $link['expires_at'];
        }

        get_db()->prepare(
            'UPDATE links SET url = ?, expires_at = ?, notes = ?, updated_at = ?, updated_by = ? WHERE id = ?'
        )->execute([$new_url, $new_expires, $new_notes, now_str(), $user['id'], $link_id]);

        flash('Link updated.');
        redirect(url_for('index'));
    }

    render('edit_link', ['link' => $link]);
}

if (preg_match('#^/links/(\d+)/notes$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $link_id = (int) $m[1];
    $data = request_json();
    $new_notes = trim($data['notes'] ?? '');
    $new_notes = $new_notes === '' ? null : $new_notes;

    $conn = get_db();
    $stmt = $conn->prepare('SELECT group_id FROM links WHERE id = ?');
    $stmt->execute([$link_id]);
    $link_row = $stmt->fetch(PDO::FETCH_NUM);
    if (!$link_row) {
        json_response(['error' => 'Link not found.'], 404);
    }
    if (!link_visible_to($user, $link_row[0] === null ? null : (int) $link_row[0])) {
        json_response(['error' => 'You do not have access to that link.'], 403);
    }

    $timestamp = now_str();
    $conn->prepare('UPDATE links SET notes = ?, updated_at = ?, updated_by = ? WHERE id = ?')
        ->execute([$new_notes, $timestamp, $user['id'], $link_id]);

    json_response([
        'success' => true,
        'notes' => $new_notes ?: '',
        'updated_at' => $timestamp,
        'updated_by' => $user['display_name'],
    ]);
}

if ($path === '/links/clicks' && $method === 'GET') {
    $user = require_login();
    [$scope_where, $scope_params] = link_scope_clause($user);
    $stmt = get_db()->prepare("SELECT links.id, links.clicks FROM links {$scope_where}");
    $stmt->execute($scope_params);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    $out = [];
    foreach ($rows as $r) {
        $out[(string) $r[0]] = $r[1] ?: 0;
    }
    json_response($out);
}

if (preg_match('#^/links/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $link_id = (int) $m[1];
    $conn = get_db();
    $stmt = $conn->prepare('SELECT user_id, group_id FROM links WHERE id = ?');
    $stmt->execute([$link_id]);
    $row = $stmt->fetch(PDO::FETCH_NUM);

    if (!$row) {
        flash('Link not found.');
        redirect(url_for('index'));
    }
    $link_group_id = $row[1] === null ? null : (int) $row[1];
    if (!can_delete_link($user, $link_group_id, (int) $row[0])) {
        abort(403);
    }

    $conn->prepare('DELETE FROM links WHERE id = ?')->execute([$link_id]);
    flash('Link deleted.');
    $manages_a_group = is_super($user) || $user['role'] === ROLE_GROUP_ADMIN;
    $came_from_expired = ($_POST['redirect_to'] ?? '') === 'expired' && $manages_a_group;
    redirect($came_from_expired ? url_for('admin_expired') : url_for('index'));
}

if ($path !== '/' && $method === 'GET') {
    $short_url = ltrim($path, '/');
    $conn = get_db();
    $stmt = $conn->prepare('SELECT url, expires_at FROM links WHERE short_url = ?');
    $stmt->execute([$short_url]);
    $result = $stmt->fetch(PDO::FETCH_NUM);

    if (!$result) {
        render('not_found', ['short_url' => $short_url], 404);
    }

    [$url, $expires_at] = $result;
    if (is_expired($expires_at)) {
        render('expired', ['short_url' => $short_url, 'expires_at' => $expires_at], 410);
    }

    $conn->prepare('UPDATE links SET clicks = clicks + 1 WHERE short_url = ?')->execute([$short_url]);
    redirect($url);
}

abort(404);
