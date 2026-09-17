<?php

function route_index(array $params = []): void {
    if (is_public_short_host() && !is_authenticated()) {
        route_redirect_to_url(['slug' => ROOT_LINK_SLUG]);
        return;
    }

    require_login();
    $me = current_user();

    $rows = db()->query('
        SELECT links.id, links.slug, links.url, creators.username AS creator, links.user_id,
               links.created_at, links.updated_at, links.expires_at, links.notes,
               editors.username AS updated_by, links.clicks, links.last_clicked_at
        FROM links
        JOIN users AS creators ON creators.id = links.user_id
        LEFT JOIN users AS editors ON editors.id = links.updated_by
        ORDER BY links.id DESC
    ')->fetchAll();

    $all_links = [];
    foreach ($rows as $r) {
        $r['notes']      = $r['notes'] ?? '';
        $r['clicks']     = (int)($r['clicks'] ?? 0);
        $r['expired']    = is_expired($r['expires_at']);
        $r['is_root']    = is_root_slug((string)$r['slug']);
        $r['can_delete'] = !$r['is_root'] && ((int)$me['is_admin'] === 1 || (int)$r['user_id'] === (int)$me['id']);
        $all_links[] = $r;
    }

    render('index', ['all_links' => $all_links]);
}

function route_login(array $params = []): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = ($_POST['remember_me'] ?? '') === '1';

        if ($username === '' || $password === '') {
            flash('Username and password are required.');
            redirect(url_for('login'));
        }

        $stmt = db()->prepare('SELECT id, username, password, is_admin FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row && $row['password'] && password_verify($password, $row['password'])) {
            login_user_now($row, $remember);
            redirect(url_for('index'));
        }

        flash('Invalid username or password.');
        redirect(url_for('login'));
    }

    if (($_GET['reason'] ?? '') === 'timeout') {
        flash('Your session expired. Please log in again.');
    }
    render('login');
}

function route_logout(array $params = []): void {
    require_login();
    logout_user_now();
    redirect(url_for('login'));
}

function route_session_ping(array $params = []): void {
    require_login();
    $_SESSION['last_activity'] = time();
    http_response_code(204);
    exit;
}

function route_check_username(array $params = []): void {
    $username = trim($_GET['u'] ?? '');
    if ($username === '') {
        json_response(['exists' => false]);
    }
    $stmt = db()->prepare('SELECT 1 FROM users WHERE username = ?');
    $stmt->execute([$username]);
    json_response(['exists' => (bool)$stmt->fetch()]);
}

function route_forgot_password(array $params = []): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        if ($username !== '') {
            db()->prepare('UPDATE users SET pending_reset = 1 WHERE username = ?')
                ->execute([$username]);
        }
        flash('If that account exists, an administrator has been notified. Contact them to receive a new password.');
        redirect(url_for('login'));
    }
    render('forgot_password');
}

function route_admin_users(array $params = []): void {
    require_admin();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_username = trim($_POST['username'] ?? '');
        $new_password = $_POST['password'] ?? '';
        $make_admin   = ($_POST['make_admin'] ?? '') === '1';

        if ($new_username === '') {
            flash('Username is required.');
            redirect(url_for('admin_users'));
        }
        if (strlen($new_password) < 6) {
            flash('Password must be at least 6 characters.');
            redirect(url_for('admin_users'));
        }

        try {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            db()->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)')
                ->execute([$new_username, $hash, $make_admin ? 1 : 0]);
        } catch (PDOException $e) {
            flash('That username already exists.');
            redirect(url_for('admin_users'));
        }

        $_SESSION['new_user_credentials'] = [
            'username'  => $new_username,
            'password'  => $new_password,
            'login_url' => url_for('login', [], true),
            'is_admin'  => $make_admin,
        ];
        $role = $make_admin ? 'admin' : 'user';
        flash("Added \"{$new_username}\" as {$role}.");
        redirect(url_for('admin_users'));
    }

    $users = db()->query('
        SELECT id, username, is_admin, pending_reset FROM users
        ORDER BY pending_reset DESC, is_admin DESC, id ASC
    ')->fetchAll();

    $new_creds = $_SESSION['new_user_credentials'] ?? null;
    unset($_SESSION['new_user_credentials']);

    render('admin_users', ['users' => $users, 'new_creds' => $new_creds]);
}

function route_admin_delete_user(array $params): void {
    require_admin();
    $me = current_user();
    $user_id = (int)$params['user_id'];

    if ($user_id === (int)$me['id']) {
        flash('You cannot delete your own account.');
        redirect(url_for('admin_users'));
    }

    $stmt = db()->prepare('SELECT username, is_admin FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('User not found.');
        redirect(url_for('admin_users'));
    }

    if ((int)$row['is_admin'] === 1) {
        $count = (int)db()->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        if ($count <= 1) {
            flash('Cannot delete the last remaining admin.');
            redirect(url_for('admin_users'));
        }
    }

    db()->prepare('DELETE FROM links WHERE user_id = ?')->execute([$user_id]);
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);

    flash($row['username'] . ' removed.');
    redirect(url_for('admin_users'));
}

function route_admin_toggle_admin(array $params): void {
    require_admin();
    $me = current_user();
    $user_id = (int)$params['user_id'];

    if ($user_id === (int)$me['id']) {
        flash('You cannot change your own admin status.');
        redirect(url_for('admin_users'));
    }

    $stmt = db()->prepare('SELECT username, is_admin FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('User not found.');
        redirect(url_for('admin_users'));
    }

    $currently_admin = (int)$row['is_admin'] === 1;
    if ($currently_admin) {
        $count = (int)db()->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        if ($count <= 1) {
            flash('Cannot demote the last remaining admin.');
            redirect(url_for('admin_users'));
        }
    }

    $new_value = $currently_admin ? 0 : 1;
    db()->prepare('UPDATE users SET is_admin = ? WHERE id = ?')->execute([$new_value, $user_id]);

    $action = $currently_admin ? 'demoted to user' : 'promoted to admin';
    flash($row['username'] . ' ' . $action . '.');
    redirect(url_for('admin_users'));
}

function route_admin_reset_password(array $params): void {
    require_admin();
    $user_id = (int)$params['user_id'];
    $new_password = $_POST['password'] ?? '';

    if (strlen($new_password) < 6) {
        flash('Password must be at least 6 characters.');
        redirect(url_for('admin_users'));
    }

    $stmt = db()->prepare('SELECT username, is_admin FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('User not found.');
        redirect(url_for('admin_users'));
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password = ?, pending_reset = 0 WHERE id = ?')
        ->execute([$hash, $user_id]);

    $_SESSION['new_user_credentials'] = [
        'username'  => $row['username'],
        'password'  => $new_password,
        'login_url' => url_for('login', [], true),
        'is_admin'  => (int)$row['is_admin'] === 1,
    ];
    flash('Password reset for "' . $row['username'] . '".');
    redirect(url_for('admin_users'));
}

function route_profile(array $params = []): void {
    require_login();
    render('profile');
}

function route_profile_change_username(array $params = []): void {
    require_login();
    $me = current_user();
    $new_username = trim($_POST['new_username'] ?? '');

    if ($new_username === '') {
        flash('Username cannot be empty.');
        redirect(url_for('profile'));
    }
    if ($new_username === $me['username']) {
        flash('That is already your username.');
        redirect(url_for('profile'));
    }

    try {
        db()->prepare('UPDATE users SET username = ? WHERE id = ?')
            ->execute([$new_username, (int)$me['id']]);
    } catch (PDOException $e) {
        flash('That username is already taken.');
        redirect(url_for('profile'));
    }

    flash('Username updated.');
    redirect(url_for('profile'));
}

function route_profile_change_password(array $params = []): void {
    require_login();
    $me = current_user();
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '') {
        flash('New password cannot be empty.');
        redirect(url_for('profile'));
    }
    if (strlen($new_password) < 6) {
        flash('New password must be at least 6 characters.');
        redirect(url_for('profile'));
    }
    if ($new_password !== $confirm_password) {
        flash('New password and confirmation do not match.');
        redirect(url_for('profile'));
    }

    $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([(int)$me['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current_password, $row['password'])) {
        flash('Current password is incorrect.');
        redirect(url_for('profile'));
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([$hash, (int)$me['id']]);

    flash('Password updated.');
    redirect(url_for('profile'));
}

function route_shorten_url(array $params = []): void {
    require_login();
    $me = current_user();

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $original_url = trim($data['url'] ?? '');
    $custom_slug  = trim($data['slug'] ?? '');
    $raw_expires  = $data['expires_at'] ?? null;
    $notes        = trim($data['notes'] ?? '');
    $notes        = $notes === '' ? null : $notes;

    if ($original_url === '' || $custom_slug === '') {
        json_response(['error' => 'Both URL and custom slug are required.'], 400);
    }

    if (!preg_match('#^https?://#i', $original_url)) {
        $original_url = 'https://' . $original_url;
    }
    if (!is_valid_url($original_url)) {
        json_response(['error' => 'Enter a valid URL.'], 400);
    }
    if (!is_valid_slug($custom_slug)) {
        json_response(['error' => 'Slug must be 1-64 letters, numbers, dashes, or underscores and cannot be a reserved route.'], 400);
    }

    $expires_at = parse_expires_at($raw_expires);
    if ($raw_expires && !$expires_at) {
        json_response(['error' => 'Invalid expiration date.'], 400);
    }
    if ($expires_at && strtotime($expires_at) <= time()) {
        json_response(['error' => 'Expiration date must be in the future.'], 400);
    }

    try {
        db()->prepare('INSERT INTO links (user_id, slug, url, created_at, expires_at, notes) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([(int)$me['id'], $custom_slug, $original_url, now_str(), $expires_at, $notes]);
    } catch (PDOException $e) {
        json_response(['error' => 'This custom tou.ro/slug is already taken.'], 400);
    }

    json_response([
        'success'    => true,
        'short_link' => short_url($custom_slug),
    ]);
}

function route_edit_link(array $params): void {
    require_login();
    $link_id = (int)$params['link_id'];

    $stmt = db()->prepare('SELECT id, user_id, slug, url, created_at, updated_at, expires_at, notes FROM links WHERE id = ?');
    $stmt->execute([$link_id]);
    $link = $stmt->fetch();
    if (!$link) abort(404);
    if (is_root_slug((string)$link['slug'])) {
        flash('The tou.ro homepage link cannot be edited.');
        redirect(url_for('index'));
    }
    $link['notes'] = $link['notes'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_url      = trim($_POST['url'] ?? '');
        $raw_expires  = $_POST['expires_at'] ?? '';
        $clear_expiry = ($_POST['clear_expiry'] ?? '') === '1';
        $new_notes    = trim($_POST['notes'] ?? '');
        $new_notes    = $new_notes === '' ? null : $new_notes;

        if ($new_url === '') {
            flash('URL is required.');
            redirect(url_for('edit_link', ['link_id' => $link_id]));
        }
        if (!preg_match('#^https?://#i', $new_url)) {
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
            if (strtotime($new_expires) <= time()) {
                flash('Expiration date must be in the future.');
                redirect(url_for('edit_link', ['link_id' => $link_id]));
            }
        } else {
            $new_expires = $link['expires_at'];
        }

        $me = current_user();
        db()->prepare('UPDATE links SET url = ?, expires_at = ?, notes = ?, updated_at = ?, updated_by = ? WHERE id = ?')
            ->execute([$new_url, $new_expires, $new_notes, now_str(), (int)$me['id'], $link_id]);

        flash('Link updated.');
        redirect(url_for('index'));
    }

    render('edit_link', ['link' => $link]);
}

function route_update_link_notes(array $params): void {
    require_login();
    $link_id = (int)$params['link_id'];

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $new_notes = trim($data['notes'] ?? '');
    $new_notes = $new_notes === '' ? null : $new_notes;

    $stmt = db()->prepare('SELECT id FROM links WHERE id = ?');
    $stmt->execute([$link_id]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Link not found.'], 404);
    }

    $me = current_user();
    $ts = now_str();
    db()->prepare('UPDATE links SET notes = ?, updated_at = ?, updated_by = ? WHERE id = ?')
        ->execute([$new_notes, $ts, (int)$me['id'], $link_id]);

    json_response([
        'success'    => true,
        'notes'      => $new_notes ?? '',
        'updated_at' => $ts,
        'updated_by' => $me['username'],
    ]);
}

function route_link_click_counts(array $params = []): void {
    require_login();
    $rows = db()->query('SELECT id, clicks, last_clicked_at FROM links')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['id']] = [
            'clicks'          => (int)($r['clicks'] ?? 0),
            'last_clicked_at' => $r['last_clicked_at'] ?? null,
        ];
    }
    json_response($out);
}

function route_delete_link(array $params): void {
    require_login();
    $me = current_user();
    $link_id = (int)$params['link_id'];

    $stmt = db()->prepare('SELECT user_id, slug FROM links WHERE id = ?');
    $stmt->execute([$link_id]);
    $row = $stmt->fetch();

    if (!$row) {
        flash('Link not found.');
        redirect(url_for('index'));
    }
    if (is_root_slug((string)$row['slug'])) {
        flash('The tou.ro homepage link cannot be deleted. Update its destination instead.');
        redirect(url_for('index'));
    }
    if ((int)$me['is_admin'] !== 1 && (int)$row['user_id'] !== (int)$me['id']) {
        abort(403);
    }

    db()->prepare('DELETE FROM links WHERE id = ?')->execute([$link_id]);
    flash('Link deleted.');
    redirect(url_for('index'));
}

function route_redirect_to_url(array $params): void {
    $slug = $params['slug'];
    $stmt = db()->prepare('SELECT url, expires_at FROM links WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        render('not_found', ['slug' => $slug]);
        return;
    }
    if (is_expired($row['expires_at'])) {
        http_response_code(410);
        render('expired', ['slug' => $slug, 'expires_at' => $row['expires_at']]);
        return;
    }

    db()->prepare('UPDATE links SET clicks = clicks + 1, last_clicked_at = ? WHERE slug = ?')
        ->execute([now_str(), $slug]);
    redirect($row['url']);
}
