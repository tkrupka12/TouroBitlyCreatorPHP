<?php
// Change ADMIN_USERNAME / ADMIN_PASSWORD before real use.
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123';

const SESSION_LIFETIME_MINUTES = 30;
const REMEMBER_DURATION_DAYS   = 30;

const TIMESTAMP_FMT      = 'Y-m-d H:i:s';
const SLUG_PATTERN       = '/^[A-Za-z0-9_-]{1,64}$/';
const RESERVED_SLUGS     = ['login', 'logout', 'register', 'shorten', 'static', 'admin', 'links'];

const SHORT_LINK_ORIGIN  = 'https://tou.ro';
const ROOT_LINK_SLUG     = '';
const ROOT_LINK_URL      = 'https://www.touro.edu';

define('BASE_DIR', dirname(__DIR__));
define('DB_PATH',  BASE_DIR . '/touro_users.db');

const URL_MAP = [
    'index'                    => '/',
    'login'                    => '/login',
    'logout'                   => '/logout',
    'session_ping'             => '/session/ping',
    'check_username'           => '/check-username',
    'forgot_password'          => '/forgot-password',
    'admin_users'              => '/admin/users',
    'admin_delete_user'        => '/admin/users/{user_id}/delete',
    'admin_toggle_admin'       => '/admin/users/{user_id}/toggle-admin',
    'admin_reset_password'     => '/admin/users/{user_id}/reset-password',
    'profile'                  => '/profile',
    'profile_change_username'  => '/profile/username',
    'profile_change_password'  => '/profile/password',
    'shorten_url'              => '/shorten',
    'edit_link'                => '/links/{link_id}/edit',
    'update_link_notes'        => '/links/{link_id}/notes',
    'link_click_counts'        => '/links/clicks',
    'delete_link'              => '/links/{link_id}/delete',
    'redirect_to_url'          => '/{slug}',
];
