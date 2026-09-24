<?php

/**
 * Create a short link (JSON). Called from the home page form.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

$user = require_login();
$data = request_json();
$original_url = trim($data['url'] ?? '');
$custom_short_url = trim($data['short_url'] ?? '');
$mode = ($data['mode'] ?? 'custom') === 'random' ? 'random' : 'custom';
$raw_expires = $data['expires_at'] ?? null;
$notes = trim($data['notes'] ?? '');
$notes = $notes === '' ? null : $notes;

if ($original_url === '') {
    json_response(['error' => 'A destination URL is required.'], 400);
}
if ($mode === 'custom' && $custom_short_url === '') {
    json_response(['error' => 'Enter a custom short URL or choose to generate a random one.'], 400);
}

if (!str_starts_with($original_url, 'http://') && !str_starts_with($original_url, 'https://')) {
    $original_url = 'https://' . $original_url;
}

if (!is_valid_url($original_url)) {
    json_response(['error' => 'Enter a valid URL.'], 400);
}

if ($mode === 'random') {
    $custom_short_url = generate_unique_short_url(get_db());
    if ($custom_short_url === null) {
        json_response(['error' => 'Could not generate an unused short URL. Please try again.'], 500);
    }
} else {
    if ($custom_short_url !== strtolower($custom_short_url)) {
        json_response(['error' => 'Custom short URL must be lowercase.'], 400);
    }
    if (!is_valid_short_url($custom_short_url)) {
        json_response([
            'error' => 'Custom short URL must be 1-64 lowercase letters, numbers, dashes, or underscores and cannot be a reserved route.',
        ], 400);
    }
}

if (is_super($user)) {
    $requested_group = $data['group_id'] ?? null;
    $target_group_id = is_numeric($requested_group) ? (int) $requested_group : 0;
    if ($target_group_id <= 0 || !group_exists(get_db(), $target_group_id)) {
        json_response(['error' => 'Choose a group for this link.'], 400);
    }
} else {
    $target_group_id = $user['group_id'];
    if ($target_group_id === null) {
        json_response(['error' => 'Your account is not in a group yet. Ask an admin to add you to one.'], 400);
    }
}

$expires_at = parse_expires_at($raw_expires);
if ($raw_expires && !$expires_at) {
    json_response(['error' => 'Invalid expiration date.'], 400);
}
if ($expires_at) {
    $dt = DateTime::createFromFormat(TIMESTAMP_FMT, $expires_at);
    if ($dt && $dt->getTimestamp() <= time()) {
        json_response(['error' => 'Expiration date must be in the future.'], 400);
    }
}

try {
    get_db()->prepare(
        'INSERT INTO links (user_id, group_id, short_url, url, created_at, expires_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$user['id'], $target_group_id, $custom_short_url, $original_url, now_str(), $expires_at, $notes]);
} catch (PDOException $ex) {
    if (is_unique_violation($ex)) {
        json_response(['error' => 'This custom short URL is already taken.'], 400);
    }
    throw $ex;
}

$short_link = short_link_url($custom_short_url);
json_response(['success' => true, 'short_link' => $short_link, 'short_url' => $custom_short_url]);
