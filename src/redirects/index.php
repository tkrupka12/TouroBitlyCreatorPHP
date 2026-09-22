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

if ($short_url === '' || $short_url === 'redirects' || str_starts_with($short_url, 'redirects/')) {
    abort(404);
}

follow_short_link(get_db(), $short_url);
