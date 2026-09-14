<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

$username_email = trim($_GET['u'] ?? '');
if ($username_email === '') {
    json_response(['exists' => false]);
}
$stmt = get_db()->prepare('SELECT 1 FROM users WHERE username_email = ?');
$stmt->execute([$username_email]);
json_response(['exists' => (bool) $stmt->fetchColumn()]);