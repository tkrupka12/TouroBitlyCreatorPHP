<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

require_super_admin();
$group_id = (int) ($_POST['group_id'] ?? 0);
$conn = get_db();

$name = group_name_for($conn, $group_id);
if ($name === null) {
    flash('Group not found.');
    redirect(url_for('admin_groups'));
}

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