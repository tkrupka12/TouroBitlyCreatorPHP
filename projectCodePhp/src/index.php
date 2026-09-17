<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/routes.php';

init_db();
boot_session();

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

$routes = [
    ['GET',       '#^/$#',                                       'route_index'],
    ['GET|POST',  '#^/login$#',                                  'route_login'],
    ['GET',       '#^/logout$#',                                 'route_logout'],
    ['GET',       '#^/session/ping$#',                           'route_session_ping'],
    ['GET',       '#^/check-username$#',                         'route_check_username'],
    ['GET|POST',  '#^/forgot-password$#',                        'route_forgot_password'],
    ['GET|POST',  '#^/admin/users$#',                            'route_admin_users'],
    ['POST',      '#^/admin/users/(?P<user_id>\d+)/delete$#',           'route_admin_delete_user'],
    ['POST',      '#^/admin/users/(?P<user_id>\d+)/toggle-admin$#',     'route_admin_toggle_admin'],
    ['POST',      '#^/admin/users/(?P<user_id>\d+)/reset-password$#',   'route_admin_reset_password'],
    ['GET',       '#^/profile$#',                                'route_profile'],
    ['POST',      '#^/profile/username$#',                       'route_profile_change_username'],
    ['POST',      '#^/profile/password$#',                       'route_profile_change_password'],
    ['POST',      '#^/shorten$#',                                'route_shorten_url'],
    ['GET|POST',  '#^/links/(?P<link_id>\d+)/edit$#',            'route_edit_link'],
    ['POST',      '#^/links/(?P<link_id>\d+)/notes$#',           'route_update_link_notes'],
    ['GET',       '#^/links/clicks$#',                           'route_link_click_counts'],
    ['POST',      '#^/links/(?P<link_id>\d+)/delete$#',          'route_delete_link'],
    ['GET',       '#^/(?P<slug>[^/]+)$#',                        'route_redirect_to_url'],
];

foreach ($routes as [$methods, $pattern, $handler]) {
    if (!in_array($method, explode('|', $methods), true)) continue;
    if (preg_match($pattern, $path, $m)) {
        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        $handler($params);
        exit;
    }
}

http_response_code(404);
render('not_found', ['slug' => ltrim($path, '/')]);
