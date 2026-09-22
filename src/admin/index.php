<?php

/**
 * Old bookmarks and nginx rules sent people to /admin/.
 * The signed-in app now lives at /links/; /admin/ is only admin tools.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();
redirect(url_for('index'));
