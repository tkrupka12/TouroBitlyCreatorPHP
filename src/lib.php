<?php

const DB_PATH = __DIR__ . '/touro_users.db';
const SHORT_URL_PATTERN = '/^[a-z0-9_-]{1,64}$/';
const RESERVED_SHORT_URLS = ['login', 'logout', 'register', 'shorten', 'static', 'admin', 'links'];

const RANDOM_SHORT_URL_LENGTH = 6;
const RANDOM_SHORT_URL_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

// Display-only branding for short links. Actual routing uses the request host.
const SHORT_LINK_DOMAIN = 'tou.ro';

const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123';

const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_GROUP_ADMIN = 'group_admin';
const ROLE_USER = 'user';
const ASSIGNABLE_ROLES = [ROLE_SUPER_ADMIN, ROLE_GROUP_ADMIN, ROLE_USER];

// Existing installs are folded into this group when roles are first introduced.
const DEFAULT_GROUP_NAME = 'Touro';
const GROUP_NAME_MAX_LENGTH = 64;

const TIMESTAMP_FMT = 'Y-m-d H:i:s';

const SESSION_LIFETIME_MINUTES = 30;
const REMEMBER_DURATION_DAYS = 30;

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function _table_columns(PDO $conn, string $table): array
{
    $cols = [];
    foreach ($conn->query("PRAGMA table_info({$table})") as $row) {
        $cols[$row['name']] = true;
    }
    return $cols;
}

function _ensure_column(PDO $conn, string $table, string $column, string $definition): void
{
    $cols = _table_columns($conn, $table);
    if (!isset($cols[$column])) {
        $conn->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function init_db(): void
{
    $conn = get_db();
    $conn->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username_email TEXT UNIQUE NOT NULL,
            users_name TEXT,
            password TEXT,
            is_admin INTEGER NOT NULL DEFAULT 0
        )
    ');
    // "groups" is quoted throughout: GROUPS is a keyword in SQLite window functions.
    $conn->exec('
        CREATE TABLE IF NOT EXISTS "groups" (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at TEXT
        )
    ');
    $conn->exec('
        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            short_url TEXT UNIQUE NOT NULL,
            url TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT,
            expires_at TEXT,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
    ');
    $link_cols = _table_columns($conn, 'links');
    if (isset($link_cols['slug']) && !isset($link_cols['short_url'])) {
        $conn->exec('ALTER TABLE links RENAME COLUMN slug TO short_url');
    }
    _ensure_column($conn, 'links', 'created_at', 'TEXT');
    _ensure_column($conn, 'links', 'updated_at', 'TEXT');
    _ensure_column($conn, 'links', 'expires_at', 'TEXT');
    _ensure_column($conn, 'links', 'notes', 'TEXT');
    _ensure_column($conn, 'links', 'updated_by', 'INTEGER');
    _ensure_column($conn, 'links', 'clicks', 'INTEGER NOT NULL DEFAULT 0');
    _ensure_column($conn, 'users', 'pending_reset', 'INTEGER NOT NULL DEFAULT 0');

    // Logins are email addresses now, so the column says so. Existing rows keep their value.
    $user_cols = _table_columns($conn, 'users');
    if (isset($user_cols['username']) && !isset($user_cols['username_email'])) {
        $conn->exec('ALTER TABLE users RENAME COLUMN username TO username_email');
    }
    _ensure_column($conn, 'users', 'users_name', 'TEXT');

    // The absence of users.role marks a pre-groups database, so the backfill runs once.
    $user_cols = _table_columns($conn, 'users');
    $is_pre_groups_db = !isset($user_cols['role']);
    _ensure_column($conn, 'users', 'role', "TEXT NOT NULL DEFAULT '" . ROLE_USER . "'");
    _ensure_column($conn, 'users', 'group_id', 'INTEGER');
    _ensure_column($conn, 'links', 'group_id', 'INTEGER');

    if ($is_pre_groups_db) {
        _backfill_roles_and_groups($conn, isset($user_cols['is_admin']));
    }

    $stmt = $conn->prepare('SELECT id FROM users WHERE username_email = ?');
    $stmt->execute([ADMIN_USERNAME]);
    if (!$stmt->fetch()) {
        $conn->prepare('INSERT INTO users (username_email, users_name, password, is_admin, role) VALUES (?, ?, ?, 1, ?)')
            ->execute([ADMIN_USERNAME, 'Administrator', generate_password_hash(ADMIN_PASSWORD), ROLE_SUPER_ADMIN]);
        // Give a fresh install one group so the first super admin can add users right away.
        ensure_group($conn, DEFAULT_GROUP_NAME);
    }
}

function _backfill_roles_and_groups(PDO $conn, bool $has_is_admin): void
{
    if ($has_is_admin) {
        $conn->prepare('UPDATE users SET role = ? WHERE is_admin = 1')->execute([ROLE_SUPER_ADMIN]);
    }

    $user_count = (int) $conn->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $link_count = (int) $conn->query('SELECT COUNT(*) FROM links')->fetchColumn();
    if ($user_count === 0 && $link_count === 0) {
        return;
    }

    $group_id = ensure_group($conn, DEFAULT_GROUP_NAME);
    $conn->prepare('UPDATE users SET group_id = ? WHERE role <> ? AND group_id IS NULL')
        ->execute([$group_id, ROLE_SUPER_ADMIN]);
    $conn->prepare('UPDATE links SET group_id = ? WHERE group_id IS NULL')->execute([$group_id]);
}

function ensure_group(PDO $conn, string $name): int
{
    $stmt = $conn->prepare('SELECT id FROM "groups" WHERE name = ?');
    $stmt->execute([$name]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $conn->prepare('INSERT INTO "groups" (name, created_at) VALUES (?, ?)')->execute([$name, now_str()]);
    return (int) $conn->lastInsertId();
}

function all_groups(PDO $conn): array
{
    return $conn->query('SELECT id, name FROM "groups" ORDER BY name COLLATE NOCASE ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
}

function group_exists(PDO $conn, int $group_id): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM "groups" WHERE id = ?');
    $stmt->execute([$group_id]);
    return (bool) $stmt->fetchColumn();
}

function group_name_for(PDO $conn, ?int $group_id): ?string
{
    if ($group_id === null) {
        return null;
    }
    $stmt = $conn->prepare('SELECT name FROM "groups" WHERE id = ?');
    $stmt->execute([$group_id]);
    $name = $stmt->fetchColumn();
    return $name === false ? null : (string) $name;
}

function now_str(): string
{
    return date(TIMESTAMP_FMT);
}

function parse_expires_at($value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt instanceof DateTime) {
            return $dt->format(TIMESTAMP_FMT);
        }
    }
    return null;
}

function is_expired($expires_at): bool
{
    if (!$expires_at) {
        return false;
    }
    $dt = DateTime::createFromFormat(TIMESTAMP_FMT, $expires_at);
    if (!$dt) {
        return false;
    }
    return $dt->getTimestamp() <= time();
}

function is_valid_url(string $url): bool
{
    if (preg_match('/\s/', $url)) {
        return false;
    }
    $parsed = parse_url($url);
    if (!is_array($parsed)) {
        return false;
    }
    $scheme = strtolower($parsed['scheme'] ?? '');
    return in_array($scheme, ['http', 'https'], true) && !empty($parsed['host']);
}

function is_valid_short_url(string $short_url): bool
{
    return (bool) preg_match(SHORT_URL_PATTERN, $short_url) && !in_array($short_url, RESERVED_SHORT_URLS, true);
}

function random_short_url(): string
{
    $alphabet = RANDOM_SHORT_URL_ALPHABET;
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < RANDOM_SHORT_URL_LENGTH; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function generate_unique_short_url(PDO $conn, int $attempts = 25): ?string
{
    $stmt = $conn->prepare('SELECT 1 FROM links WHERE short_url = ?');
    for ($i = 0; $i < $attempts; $i++) {
        $candidate = random_short_url();
        if (in_array($candidate, RESERVED_SHORT_URLS, true)) {
            continue;
        }
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
    }
    return null;
}

function generate_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function check_password_hash(?string $stored, string $password): bool
{
    if ($stored === null || $stored === '') {
        return false;
    }
    if (str_starts_with($stored, 'pbkdf2:')) {
        $parts = explode('$', $stored, 3);
        if (count($parts) !== 3) {
            return false;
        }
        $methodParts = explode(':', $parts[0]);
        $algo = $methodParts[1] ?? 'sha256';
        $iterations = (int) ($methodParts[2] ?? 600000);
        $salt = $parts[1];
        $hash = $parts[2];
        // length 0 = full digest. Passing strlen/2 here truncates hex output in PHP.
        $calc = hash_pbkdf2($algo, $password, $salt, $iterations, 0, false);
        return hash_equals(strtolower($hash), strtolower($calc));
    }
    return password_verify($password, $stored);
}

function flash(string $message): void
{
    $_SESSION['_flashes'][] = $message;
}

function consume_flashes(): array
{
    $messages = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $messages;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $role = $_SESSION['role'] ?? ROLE_USER;
    $group_id = $_SESSION['group_id'] ?? null;
    $username_email = $_SESSION['username_email'] ?? '';
    $users_name = $_SESSION['users_name'] ?? '';
    return [
        'id' => (int) $_SESSION['user_id'],
        'username_email' => $username_email,
        'users_name' => $users_name,
        'display_name' => $users_name !== '' ? $users_name : $username_email,
        'role' => $role,
        'group_id' => $group_id === null ? null : (int) $group_id,
        'is_super' => $role === ROLE_SUPER_ADMIN,
        'is_group_admin' => $role === ROLE_GROUP_ADMIN,
        'is_authenticated' => true,
    ];
}

function is_super(array $user): bool
{
    return ($user['role'] ?? '') === ROLE_SUPER_ADMIN;
}

function manages_group(array $user, ?int $group_id): bool
{
    if (is_super($user)) {
        return true;
    }
    if (($user['role'] ?? '') !== ROLE_GROUP_ADMIN) {
        return false;
    }
    return $group_id !== null && $user['group_id'] !== null && (int) $user['group_id'] === $group_id;
}

function link_visible_to(array $user, ?int $link_group_id): bool
{
    if (is_super($user)) {
        return true;
    }
    if ($link_group_id === null || $user['group_id'] === null) {
        return false;
    }
    return (int) $user['group_id'] === $link_group_id;
}

function can_delete_link(array $user, ?int $link_group_id, int $creator_id): bool
{
    if (!link_visible_to($user, $link_group_id)) {
        return false;
    }
    return manages_group($user, $link_group_id) || $creator_id === (int) $user['id'];
}

/**
 * SQL fragment restricting a links query to what the user may see, plus its bindings.
 * A user with no group sees nothing rather than everything.
 */
function link_scope_clause(array $user): array
{
    if (is_super($user)) {
        return ['', []];
    }
    if ($user['group_id'] === null) {
        return ['WHERE 1 = 0', []];
    }
    return ['WHERE links.group_id = ?', [(int) $user['group_id']]];
}

function load_user_row(PDO $conn, int $user_id): ?array
{
    $stmt = $conn->prepare('SELECT id, username_email, users_name, role, group_id FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['role'] = $row['role'] ?: ROLE_USER;
    $row['group_id'] = $row['group_id'] === null ? null : (int) $row['group_id'];
    $row['users_name'] = $row['users_name'] ?? '';
    $row['display_name'] = $row['users_name'] !== '' ? $row['users_name'] : $row['username_email'];
    return $row;
}

function can_manage_user(array $actor, array $target): bool
{
    if (is_super($actor)) {
        return true;
    }
    if (($actor['role'] ?? '') !== ROLE_GROUP_ADMIN) {
        return false;
    }
    // A group admin never reaches a super admin, even one parked in their group.
    if ($target['role'] === ROLE_SUPER_ADMIN) {
        return false;
    }
    return $target['group_id'] !== null
        && $actor['group_id'] !== null
        && (int) $actor['group_id'] === (int) $target['group_id'];
}

function super_admin_count(PDO $conn): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
    $stmt->execute([ROLE_SUPER_ADMIN]);
    return (int) $stmt->fetchColumn();
}

function role_label(string $role): string
{
    return match ($role) {
        ROLE_SUPER_ADMIN => 'super admin',
        ROLE_GROUP_ADMIN => 'group admin',
        default => 'user',
    };
}

function login_user(array $row, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['username_email'] = $row['username_email'];
    $_SESSION['users_name'] = $row['users_name'] ?? '';
    $_SESSION['role'] = $row['role'] ?? ROLE_USER;
    $_SESSION['group_id'] = isset($row['group_id']) && $row['group_id'] !== null ? (int) $row['group_id'] : null;
    $_SESSION['remember_me'] = $remember;
    $_SESSION['last_activity'] = time();

    $params = session_get_cookie_params();
    $lifetime = $remember ? REMEMBER_DURATION_DAYS * 86400 : 0;
    setcookie(session_name(), session_id(), [
        'expires' => $lifetime ? time() + $lifetime : 0,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

function logout_user(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['username_email'],
        $_SESSION['users_name'],
        $_SESSION['role'],
        $_SESSION['group_id'],
        $_SESSION['remember_me'],
        $_SESSION['last_activity']
    );
}

function absolute_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $path;
}

function url_for(string $name, array $params = [], bool $external = false): string
{
    switch ($name) {
        case 'index':
            $path = '/';
            break;
        case 'login':
            $path = '/login';
            if (!empty($params['reason'])) {
                $path .= '?reason=' . rawurlencode($params['reason']);
            }
            break;
        case 'logout':
            $path = '/logout';
            break;
        case 'admin_users':
            $path = '/admin/users';
            break;
        case 'admin_expired':
            $path = '/admin/expired';
            break;
        case 'admin_groups':
            $path = '/admin/groups';
            break;
        case 'admin_rename_group':
            $path = '/admin/groups/' . (int) ($params['group_id'] ?? 0) . '/rename';
            break;
        case 'admin_delete_group':
            $path = '/admin/groups/' . (int) ($params['group_id'] ?? 0) . '/delete';
            break;
        case 'profile':
            $path = '/profile';
            break;
        case 'forgot_password':
            $path = '/forgot-password';
            break;
        case 'profile_change_email':
            $path = '/profile/email';
            break;
        case 'profile_change_name':
            $path = '/profile/name';
            break;
        case 'profile_change_password':
            $path = '/profile/password';
            break;
        case 'redirect_to_url':
            $path = '/' . rawurlencode($params['short_url'] ?? '');
            break;
        case 'edit_link':
            $path = '/links/' . (int) ($params['link_id'] ?? 0) . '/edit';
            break;
        case 'delete_link':
            $path = '/links/' . (int) ($params['link_id'] ?? 0) . '/delete';
            break;
        case 'admin_reset_password':
            $path = '/admin/users/' . (int) ($params['user_id'] ?? 0) . '/reset-password';
            break;
        case 'admin_set_role':
            $path = '/admin/users/' . (int) ($params['user_id'] ?? 0) . '/role';
            break;
        case 'admin_delete_user':
            $path = '/admin/users/' . (int) ($params['user_id'] ?? 0) . '/delete';
            break;
        default:
            $path = '/';
    }
    return $external ? absolute_url($path) : $path;
}

function redirect(string $url, int $code = 302): void
{
    header('Location: ' . $url, true, $code);
    exit;
}

function abort(int $code): void
{
    http_response_code($code);
    if ($code === 403) {
        echo 'Forbidden';
    } elseif ($code === 404) {
        echo 'Not Found';
    } else {
        echo 'Error';
    }
    exit;
}

function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = $path === null || $path === '' ? '/' : $path;
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return $path;
}

function start_app_session(): void
{
    $secret = getenv('SECRET_KEY') ?: 'dev-secret-change-me';
    session_name('touro_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['_secret_ok'])) {
        $_SESSION['_secret_ok'] = $secret;
    }

    // Sessions opened before roles or the email rename carry stale keys. Refresh from the
    // database rather than silently demoting the user or losing their display name.
    if (!empty($_SESSION['user_id']) && (!isset($_SESSION['role']) || !isset($_SESSION['username_email']))) {
        $stmt = get_db()->prepare('SELECT username_email, users_name, role, group_id FROM users WHERE id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['username_email'] = $row['username_email'];
            $_SESSION['users_name'] = $row['users_name'] ?? '';
            $_SESSION['role'] = $row['role'] ?: ROLE_USER;
            $_SESSION['group_id'] = $row['group_id'] === null ? null : (int) $row['group_id'];
            unset($_SESSION['username']);
        } else {
            logout_user();
        }
    }

    if (!empty($_SESSION['user_id']) && empty($_SESSION['remember_me'])) {
        $last = $_SESSION['last_activity'] ?? time();
        if (time() - $last > SESSION_LIFETIME_MINUTES * 60) {
            logout_user();
            $path = request_path();
            if ($path !== '/login') {
                redirect(url_for('login') . '?reason=timeout');
            }
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect(url_for('login'));
    }
    return $user;
}

function require_super_admin(): array
{
    $user = require_login();
    if (!is_super($user)) {
        abort(403);
    }
    return $user;
}

function require_group_admin(): array
{
    $user = require_login();
    if (!is_super($user) && $user['role'] !== ROLE_GROUP_ADMIN) {
        abort(403);
    }
    return $user;
}

function stacked_date_parts(?string $value): array
{
    if (!$value) {
        return [null, null];
    }
    $parts = explode(' ', $value, 2);
    $date = $parts[0] ?? '';
    $time = isset($parts[1]) ? substr($parts[1], 0, 5) : '';
    return [$date, $time];
}

function render(string $view, array $vars = [], int $status = 200): void
{
    http_response_code($status);
    extract($vars, EXTR_SKIP);
    $current_user = current_user();
    $current_group_name = $current_user ? group_name_for(get_db(), $current_user['group_id']) : null;
    $flashes = consume_flashes();
    $session_lifetime_seconds = SESSION_LIFETIME_MINUTES * 60;
    $remember_me = !empty($_SESSION['remember_me']);
    ob_start();
    include __DIR__ . '/templates/' . $view . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/templates/base.php';
    exit;
}

function is_unique_violation(PDOException $e): bool
{
    return $e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE');
}
