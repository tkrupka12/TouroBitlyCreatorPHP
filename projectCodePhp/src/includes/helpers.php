<?php

function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now_str(): string {
    return date(TIMESTAMP_FMT);
}

function parse_expires_at($value): ?string {
    if (!$value) return null;
    $value = trim((string)$value);
    if ($value === '') return null;
    foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt instanceof DateTime && $dt->format($fmt) === $value) {
            return $dt->format(TIMESTAMP_FMT);
        }
    }
    return null;
}

function is_expired(?string $expires_at): bool {
    if (!$expires_at) return false;
    $dt = DateTime::createFromFormat(TIMESTAMP_FMT, $expires_at);
    if (!$dt) return false;
    return $dt <= new DateTime('now');
}

function is_valid_url(string $url): bool {
    if (preg_match('/\s/', $url)) return false;
    $parts = parse_url($url);
    if (!$parts) return false;
    $scheme = strtolower($parts['scheme'] ?? '');
    $host   = $parts['host'] ?? '';
    return in_array($scheme, ['http', 'https'], true) && $host !== '';
}

function is_valid_slug(string $slug): bool {
    return preg_match(SLUG_PATTERN, $slug) === 1 && !in_array($slug, RESERVED_SLUGS, true);
}

function is_root_slug(string $slug): bool {
    return $slug === ROOT_LINK_SLUG;
}

function short_label(string $slug): string {
    return is_root_slug($slug) ? 'tou.ro' : 'tou.ro/' . $slug;
}

function short_url(string $slug): string {
    if (is_root_slug($slug)) {
        return SHORT_LINK_ORIGIN;
    }
    return SHORT_LINK_ORIGIN . '/' . rawurlencode($slug);
}

function is_public_short_host(): bool {
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    $public = strtolower(parse_url(SHORT_LINK_ORIGIN, PHP_URL_HOST) ?: 'tou.ro');
    return $host === $public || $host === 'www.' . $public;
}

function url_for(string $name, array $args = [], bool $external = false): string {
    $path = URL_MAP[$name] ?? '/';
    foreach ($args as $key => $val) {
        $path = str_replace('{' . $key . '}', rawurlencode((string)$val), $path);
    }
    if ($external) {
        return SHORT_LINK_ORIGIN . $path;
    }
    return $path;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $msg): void {
    if (!isset($_SESSION['_flashes'])) $_SESSION['_flashes'] = [];
    $_SESSION['_flashes'][] = $msg;
}

function get_flashed_messages(): array {
    $msgs = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $msgs;
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function abort(int $status): void {
    http_response_code($status);
    echo $status === 404 ? 'Not found' : ($status === 403 ? 'Forbidden' : 'Error');
    exit;
}

function render(string $view, array $vars = []): void {
    $vars['session_lifetime_seconds'] = SESSION_LIFETIME_MINUTES * 60;
    extract($vars, EXTR_SKIP);
    ob_start();
    include BASE_DIR . '/templates/' . $view . '.php';
    $content = ob_get_clean();
    include BASE_DIR . '/templates/base.php';
}
