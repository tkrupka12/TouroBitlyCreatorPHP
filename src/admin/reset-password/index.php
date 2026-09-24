<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

$current = require_group_admin();
$user_id = (int) ($_POST['user_id'] ?? 0);
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
