<?php

/**
 * Log out: clear the session and send the user back to login.
 */

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

require_login();
unset($_SESSION['remember_me']);
logout_user();
redirect(url_for('login'));
