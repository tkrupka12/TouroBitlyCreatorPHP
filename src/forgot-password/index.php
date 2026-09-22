<?php

/**
 * Forgot password: mark the account for an admin reset if that email exists.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    if ($username_email !== '') {
        get_db()->prepare('UPDATE users SET pending_reset = 1 WHERE username_email = ?')
            ->execute([$username_email]);
    }
    flash('If that account exists, an administrator has been notified. Contact them to receive a new password.');
    redirect(url_for('login'));
}

render('forgot_password');
