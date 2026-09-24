<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

$user = require_login();
$link_id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$conn = get_db();
$stmt = $conn->prepare('SELECT user_id, group_id, short_url FROM links WHERE id = ?');
$stmt->execute([$link_id]);
$row = $stmt->fetch(PDO::FETCH_NUM);

if (!$row) {
    flash('Link not found.');
    redirect(url_for('index'));
}
$link_group_id = $row[1] === null ? null : (int) $row[1];
if ($row[2] === ROOT_SHORT_URL || !can_delete_link($user, $link_group_id, (int) $row[0])) {
    abort(403);
}

$conn->prepare('DELETE FROM links WHERE id = ?')->execute([$link_id]);
flash('Link deleted.');
$manages_a_group = is_super($user) || $user['role'] === ROLE_GROUP_ADMIN;
$came_from_expired = ($_POST['redirect_to'] ?? '') === 'expired' && $manages_a_group;
redirect($came_from_expired ? url_for('admin_expired') : url_for('index'));
