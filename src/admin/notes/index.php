<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

$user = require_login();
$data = request_json();
$link_id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
$new_notes = trim($data['notes'] ?? '');
$new_notes = $new_notes === '' ? null : $new_notes;

if ($link_id <= 0) {
    json_response(['error' => 'Link not found.'], 404);
}

$conn = get_db();
$stmt = $conn->prepare('SELECT group_id, short_url FROM links WHERE id = ?');
$stmt->execute([$link_id]);
$link_row = $stmt->fetch(PDO::FETCH_NUM);
if (!$link_row) {
    json_response(['error' => 'Link not found.'], 404);
}
if ($link_row[1] === ROOT_SHORT_URL) {
    json_response(['error' => 'This link cannot be edited.'], 403);
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