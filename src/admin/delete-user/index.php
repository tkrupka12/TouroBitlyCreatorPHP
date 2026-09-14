<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

$current = require_group_admin();
$user_id = (int) ($_POST['user_id'] ?? 0);
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
if (!can_manage_user($current, $recipient) && (int) $recipient['id'] !== (int) $current['id']) {
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