<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

$current = require_group_admin();
$user_id = (int) ($_POST['user_id'] ?? 0);
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
    $new_group_id = $target['group_id'];
}

$conn->prepare('UPDATE users SET role = ?, group_id = ?, is_admin = ? WHERE id = ?')
    ->execute([$new_role, $new_group_id, $new_role === ROLE_SUPER_ADMIN ? 1 : 0, $user_id]);

$group_note = $new_group_id === null ? '' : ' in ' . group_name_for($conn, $new_group_id);
flash("{$target['display_name']} is now a " . role_label($new_role) . $group_note . '.');
redirect(url_for('admin_users'));
