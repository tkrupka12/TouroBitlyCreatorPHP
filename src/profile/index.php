<?php

/**
 * Profile page: signed-in user can change name, email, or password.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

$user = require_login();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    if (isset($_POST['new_password']) || isset($_POST['current_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
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

        $conn = get_db();
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if (!$row || !check_password_hash($row[0], $current_password)) {
            flash('Current password is incorrect.');
            redirect(url_for('profile'));
        }

        $conn->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([generate_password_hash($new_password), $user['id']]);
        flash('Password updated.');
        redirect(url_for('profile'));
    }

    if (isset($_POST['username_email'])) {
        $new_username_email = trim($_POST['username_email'] ?? '');

        if ($new_username_email === '') {
            flash('Email cannot be empty.');
            redirect(url_for('profile'));
        }
        if (!filter_var($new_username_email, FILTER_VALIDATE_EMAIL)) {
            flash('Enter a valid email address.');
            redirect(url_for('profile'));
        }
        if ($new_username_email === $user['username_email']) {
            flash('That is already your email.');
            redirect(url_for('profile'));
        }

        try {
            get_db()->prepare('UPDATE users SET username_email = ? WHERE id = ?')
                ->execute([$new_username_email, $user['id']]);
        } catch (PDOException $ex) {
            if (is_unique_violation($ex)) {
                flash('That email is already taken.');
                redirect(url_for('profile'));
            }
            throw $ex;
        }

        $_SESSION['username_email'] = $new_username_email;
        flash('Email updated.');
        redirect(url_for('profile'));
    }

    if (isset($_POST['users_name'])) {
        $new_users_name = trim($_POST['users_name'] ?? '');

        if ($new_users_name === '') {
            flash('Your name cannot be empty.');
            redirect(url_for('profile'));
        }

        get_db()->prepare('UPDATE users SET users_name = ? WHERE id = ?')
            ->execute([$new_users_name, $user['id']]);
        $_SESSION['users_name'] = $new_users_name;
        flash('Name updated.');
        redirect(url_for('profile'));
    }
}

render('profile');
