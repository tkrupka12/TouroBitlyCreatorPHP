<?php

/**
 * Edit a short link: change destination URL, expiration, or notes. Expects ?id=.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

$user = require_login();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$link_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($link_id <= 0) {
    abort(404);
}

$stmt = get_db()->prepare(
    'SELECT id, user_id, short_url, url, created_at, updated_at, expires_at, notes, group_id FROM links WHERE id = ?'
);
$stmt->execute([$link_id]);
$row = $stmt->fetch(PDO::FETCH_NUM);
if (!$row) {
    abort(404);
}
if (!link_visible_to($user, $row[8] === null ? null : (int) $row[8])) {
    abort(403);
}
if (is_root_short_url((string) $row[2])) {
    flash('The homepage redirect is built into the software and cannot be edited.');
    redirect(url_for('index'));
}

$link = [
    'id' => $row[0],
    'user_id' => $row[1],
    'short_url' => $row[2],
    'url' => $row[3],
    'created_at' => $row[4],
    'updated_at' => $row[5],
    'expires_at' => $row[6],
    'notes' => $row[7] ?: '',
    'group_id' => $row[8] === null ? null : (int) $row[8],
];

if ($method === 'POST') {
    $new_url = trim($_POST['url'] ?? '');
    $raw_expires = $_POST['expires_at'] ?? null;
    $clear_expiry = ($_POST['clear_expiry'] ?? '') === '1';
    $new_notes = trim($_POST['notes'] ?? '');
    $new_notes = $new_notes === '' ? null : $new_notes;

    if ($new_url === '') {
        flash('URL is required.');
        redirect(url_for('edit_link', ['link_id' => $link_id]));
    }

    if (!str_starts_with($new_url, 'http://') && !str_starts_with($new_url, 'https://')) {
        $new_url = 'https://' . $new_url;
    }
    if (!is_valid_url($new_url)) {
        flash('Enter a valid URL.');
        redirect(url_for('edit_link', ['link_id' => $link_id]));
    }

    if ($clear_expiry) {
        $new_expires = null;
    } elseif ($raw_expires) {
        $new_expires = parse_expires_at($raw_expires);
        if (!$new_expires) {
            flash('Invalid expiration date.');
            redirect(url_for('edit_link', ['link_id' => $link_id]));
        }
        $dt = DateTime::createFromFormat(TIMESTAMP_FMT, $new_expires);
        if ($dt && $dt->getTimestamp() <= time()) {
            flash('Expiration date must be in the future.');
            redirect(url_for('edit_link', ['link_id' => $link_id]));
        }
    } else {
        $new_expires = $link['expires_at'];
    }

    get_db()->prepare(
        'UPDATE links SET url = ?, expires_at = ?, notes = ?, updated_at = ?, updated_by = ? WHERE id = ?'
    )->execute([$new_url, $new_expires, $new_notes, now_str(), $user['id'], $link_id]);

    flash('Link updated.');
    redirect(url_for('index'));
}

render('edit_link', ['link' => $link]);