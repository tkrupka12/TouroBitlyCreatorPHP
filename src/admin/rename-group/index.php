<?php

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    abort(404);
}

require_super_admin();
$group_id = (int) ($_POST['group_id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if ($name === '') {
    flash('Group name is required.');
    redirect(url_for('admin_groups'));
}
if (mb_strlen($name) > GROUP_NAME_MAX_LENGTH) {
    flash('Group name must be ' . GROUP_NAME_MAX_LENGTH . ' characters or fewer.');
    redirect(url_for('admin_groups'));
}

$conn = get_db();
if (!group_exists($conn, $group_id)) {
    flash('Group not found.');
    redirect(url_for('admin_groups'));
}

try {
    $conn->prepare('UPDATE "groups" SET name = ? WHERE id = ?')->execute([$name, $group_id]);
} catch (PDOException $ex) {
    if (is_unique_violation($ex)) {
        flash('A group with that name already exists.');
        redirect(url_for('admin_groups'));
    }
    throw $ex;
}

flash("Group renamed to \"{$name}\".");
redirect(url_for('admin_groups'));