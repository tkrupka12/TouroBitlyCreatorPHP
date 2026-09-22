<?php

require __DIR__ . '/../lib.php';

init_db();
start_app_session();
redirect(url_for('index'));
