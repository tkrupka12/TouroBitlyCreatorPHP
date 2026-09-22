<?php

/**
 * Login page: show the form and sign the user in (optional "keep me logged in").
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = ($_POST['remember_me'] ?? '') === '1';

    if ($username_email === '' || $password === '') {
        flash('Email and password are required.');
        redirect(url_for('login'));
    }

    $stmt = get_db()->prepare(
        'SELECT id, username_email, users_name, password, role, group_id FROM users WHERE username_email = ?'
    );
    $stmt->execute([$username_email]);
    $user_row = $stmt->fetch(PDO::FETCH_NUM);

    if ($user_row && $user_row[3] && check_password_hash($user_row[3], $password)) {
        login_user([
            'id' => $user_row[0],
            'username_email' => $user_row[1],
            'users_name' => $user_row[2],
            'role' => $user_row[4],
            'group_id' => $user_row[5],
        ], $remember);
        redirect(url_for('index'));
    }

    flash('Invalid email or password.');
    redirect(url_for('login'));
}

if (($_GET['reason'] ?? '') === 'timeout') {
    flash('Your session expired. Please log in again.');
}
render('login');
