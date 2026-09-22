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

// nginx often sends / here. That is the hardcoded homepage, not a missing short link.
if ($short_url === '') {
    record_link_click(get_db(), ROOT_LINK_SLUG);
    redirect(ROOT_LINK_URL);
}

follow_short_link(get_db(), $short_url);
