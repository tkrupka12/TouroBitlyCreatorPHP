<?php

/**
 * https://tou.ro and https://tou.ro/ always go to the Touro website.
 */

require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    abort(404);
}

init_db();
record_link_click(get_db(), ROOT_LINK_SLUG);
redirect(ROOT_LINK_URL);
