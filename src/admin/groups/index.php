<?php

/**
 * Manage groups: list groups, create one, rename one, or delete an empty group.
 */

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

require_super_admin();
$conn = get_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === '') {
        if (isset($_POST['group_id']) && isset($_POST['name'])) {
            $action = 'rename';
        } elseif (isset($_POST['group_id'])) {
            $action = 'delete';
        } else {
            $action = 'create';
        }
    }

    if ($action === 'rename') {
        $group_id = (int) ($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            flash('Group name is required.');
            redirect(url_for('admin_groups'));
        }
        if (mb_strlen($name) > GROUP_NAME_MAX_LENGTH) {
            flash('Group name must be ' . GROUP_NAME_MAX_LENGTH . ' characters or fewer.');
            redirect(url_for('admin_groups'));
        }

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
    }

    if ($action === 'delete') {
        $group_id = (int) ($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
        $name = group_name_for($conn, $group_id);
        if ($name === null) {
            flash('Group not found.');
            redirect(url_for('admin_groups'));
        }

        $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE group_id = ?');
        $stmt->execute([$group_id]);
        $member_count = (int) $stmt->fetchColumn();

        $stmt = $conn->prepare('SELECT COUNT(*) FROM links WHERE group_id = ?');
        $stmt->execute([$group_id]);
        $link_count = (int) $stmt->fetchColumn();

        if ($member_count > 0 || $link_count > 0) {
            flash("Cannot delete \"{$name}\": it still has {$member_count} member(s) and {$link_count} link(s). Move or remove them first.");
            redirect(url_for('admin_groups'));
        }

        $conn->prepare('DELETE FROM "groups" WHERE id = ?')->execute([$group_id]);
        flash("Group \"{$name}\" deleted.");
        redirect(url_for('admin_groups'));
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('Group name is required.');
        redirect(url_for('admin_groups'));
    }
    if (mb_strlen($name) > GROUP_NAME_MAX_LENGTH) {
        flash('Group name must be ' . GROUP_NAME_MAX_LENGTH . ' characters or fewer.');
        redirect(url_for('admin_groups'));
    }
    try {
        $conn->prepare('INSERT INTO "groups" (name, created_at) VALUES (?, ?)')->execute([$name, now_str()]);
    } catch (PDOException $ex) {
        if (is_unique_violation($ex)) {
            flash('A group with that name already exists.');
            redirect(url_for('admin_groups'));
        }
        throw $ex;
    }
    flash("Group \"{$name}\" created.");
    redirect(url_for('admin_groups'));
}

$groups = $conn->query('
    SELECT g.id, g.name, g.created_at,
           (SELECT COUNT(*) FROM users WHERE users.group_id = g.id) AS member_count,
           (SELECT COUNT(*) FROM links WHERE links.group_id = g.id) AS link_count
    FROM "groups" AS g
    ORDER BY g.name COLLATE NOCASE ASC
')->fetchAll(PDO::FETCH_ASSOC);
render('admin_groups', ['groups' => $groups]);
