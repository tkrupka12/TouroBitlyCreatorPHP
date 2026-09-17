<?php

function boot_session(): void {
    $lifetime = SESSION_LIFETIME_MINUTES * 60;
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('touro_session');
    session_start();

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime && empty($_SESSION['remember_me'])) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function login_user_now(array $user, bool $remember): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['remember_me']   = $remember;
    $_SESSION['last_activity'] = time();

    if ($remember) {
        $token   = bin2hex(random_bytes(32));
        $expires = (new DateTime('+' . REMEMBER_DURATION_DAYS . ' days'))->format(TIMESTAMP_FMT);
        db()->prepare('INSERT INTO remember_tokens (token, user_id, expires_at) VALUES (?, ?, ?)')
            ->execute([$token, (int)$user['id'], $expires]);
        setcookie('touro_remember', $token, [
            'expires'  => time() + REMEMBER_DURATION_DAYS * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function logout_user_now(): void {
    if (!empty($_COOKIE['touro_remember'])) {
        db()->prepare('DELETE FROM remember_tokens WHERE token = ?')
            ->execute([$_COOKIE['touro_remember']]);
        setcookie('touro_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_unset();
    session_destroy();
}

function load_current_user(): ?array {
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, username, is_admin FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    if (!empty($_COOKIE['touro_remember'])) {
        $stmt = db()->prepare('
            SELECT u.id, u.username, u.is_admin, rt.expires_at
            FROM remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.token = ?
        ');
        $stmt->execute([$_COOKIE['touro_remember']]);
        $row = $stmt->fetch();
        if ($row && strtotime($row['expires_at']) > time()) {
            $_SESSION['user_id']     = (int)$row['id'];
            $_SESSION['remember_me'] = true;
            return ['id' => $row['id'], 'username' => $row['username'], 'is_admin' => $row['is_admin']];
        }
    }

    return null;
}

function current_user(): ?array {
    static $cached = false;
    static $user = null;
    if (!$cached) {
        $user = load_current_user();
        $cached = true;
    }
    return $user;
}

function is_authenticated(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    return $u !== null && (int)$u['is_admin'] === 1;
}

function require_login(): void {
    if (!is_authenticated()) {
        redirect(url_for('login'));
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        abort(403);
    }
}
