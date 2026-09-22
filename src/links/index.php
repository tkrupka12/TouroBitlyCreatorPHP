<?php

/**
 * Signed-in home: list active short links and show the create-link form.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

$user = require_login();
[$scope_where, $scope_params] = link_scope_clause($user);
$stmt = get_db()->prepare("
    SELECT links.id, links.short_url, links.url,
           COALESCE(NULLIF(creators.users_name, ''), creators.username_email), links.user_id,
           links.created_at, links.updated_at, links.expires_at, links.notes,
           COALESCE(NULLIF(editors.users_name, ''), editors.username_email), links.clicks,
           links.group_id, link_groups.name, links.last_clicked_at
    FROM links
    JOIN users AS creators ON creators.id = links.user_id
    LEFT JOIN users AS editors ON editors.id = links.updated_by
    LEFT JOIN \"groups\" AS link_groups ON link_groups.id = links.group_id
    {$scope_where}
    ORDER BY links.id DESC
");
$stmt->execute($scope_params);
$rows = $stmt->fetchAll(PDO::FETCH_NUM);

$all_links = [];
foreach ($rows as $r) {
    if (is_expired($r[7])) {
        continue;
    }
    $is_root = is_root_short_url((string) $r[1]);
    $link_group_id = $r[11] === null ? null : (int) $r[11];
    $all_links[] = [
        'id' => $r[0],
        'short_url' => $r[1],
        'url' => $is_root ? ROOT_LINK_URL : $r[2],
        'creator' => $r[3],
        'user_id' => $r[4],
        'created_at' => $r[5],
        'updated_at' => $r[6],
        'expires_at' => $is_root ? null : $r[7],
        'notes' => $r[8] ?: '',
        'updated_by' => $r[9],
        'clicks' => $r[10] ?: 0,
        'group_id' => $link_group_id,
        'group_name' => $r[12],
        'last_clicked_at' => $r[13] ?? null,
        'expired' => is_expired($r[7]),
        'is_root' => $is_root,
        'can_delete' => !$is_root && can_delete_link($user, $link_group_id, (int) $r[4], (string) $r[1]),
        'can_edit' => !$is_root && can_delete_link($user, $link_group_id, (int) $r[4], (string) $r[1]),
    ];
}
render('index', [
    'all_links' => $all_links,
    'groups' => is_super($user) ? all_groups(get_db()) : [],
]);
