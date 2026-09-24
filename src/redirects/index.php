<?php

/**
 * Short-link engine: look up /{slug} in the database and redirect, or show not-found / expired.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    abort(404);
}

$path = request_path();
$short_url = ltrim($path, '/');

if ($short_url === 'redirects' || str_starts_with($short_url, 'redirects/')) {
    abort(404);
}

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

$conn->prepare('UPDATE links SET clicks = clicks + 1, last_clicked_at = ? WHERE short_url = ?')
    ->execute([now_str(), $short_url]);
redirect($url);
