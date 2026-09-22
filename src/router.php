<?php

/**
 * PHP built-in server router matching the production nginx layout.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if (
    preg_match('~(?:^|/)\.env(?:\.|/|$)~', $uri)
    || $uri === '/lib.php'
    || $uri === '/router.php'
    || str_starts_with($uri, '/db/')
    || str_starts_with($uri, '/templates/')
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

if ($uri === '/admin' || $uri === '/admin/') {
    header('Location: /', true, 302);
    exit;
}

if ($uri === '/') {
    require __DIR__ . '/index.php';
    exit;
}

// Serve real app files (login, profile, admin/users, etc.). Unknown paths are short links.
if (!str_starts_with($uri, '/redirects')) {
    $file = __DIR__ . $uri;
    if (is_dir($file) && is_file($file . '/index.php')) {
        require $file . '/index.php';
        exit;
    }
    if (is_file($file)) {
        if (str_ends_with($file, '.php')) {
            require $file;
            exit;
        }
        return false;
    }
}

require __DIR__ . '/redirects/index.php';
