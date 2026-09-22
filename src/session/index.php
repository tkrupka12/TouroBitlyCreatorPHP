<?php

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

require_login();
$_SESSION['last_activity'] = time();
http_response_code(204);
exit;