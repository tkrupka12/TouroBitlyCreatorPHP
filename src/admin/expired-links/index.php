<?php

/**
 * Expired links: list short links whose expiration date has passed (admins only).
 */

require __DIR__ . '/../../lib.php';

init_db();
start_app_session();

$user = require_group_admin();
[$scope_where, $scope_params] = link_scope_clause($user);
$stmt = get_db()->prepare("
    SELECT links.id, links.short_url, links.url,
           COALESCE(NULLIF(creators.users_name, ''), creators.username_email), links.user_id,
           links.created_at, links.updated_at, links.expires_at, links.notes,
           COALESCE(NULLIF(editors.users_name, ''), editors.username_email), links.clicks,
           links.group_id, link_groups.name
    FROM links
    JOIN users AS creators ON creators.id = links.user_id
    LEFT JOIN users AS editors ON editors.id = links.updated_by
    LEFT JOIN \"groups\" AS link_groups ON link_groups.id = links.group_id
    {$scope_where}
    ORDER BY links.expires_at DESC
");
$stmt->execute($scope_params);
$rows = $stmt->fetchAll(PDO::FETCH_NUM);

$expired_links = [];
foreach ($rows as $r) {
    if (!is_expired($r[7])) {
        continue;
    }
    $expired_links[] = [
        'id' => $r[0],
        'short_url' => $r[1],
        'url' => $r[2],
        'creator' => $r[3],
        'user_id' => $r[4],
        'created_at' => $r[5],
        'updated_at' => $r[6],
        'expires_at' => $r[7],
        'notes' => $r[8] ?: '',
        'updated_by' => $r[9],
        'clicks' => $r[10] ?: 0,
        'group_id' => $r[11] === null ? null : (int) $r[11],
        'group_name' => $r[12],
    ];
}
render('admin_expired', ['expired_links' => $expired_links]);