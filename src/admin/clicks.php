<?php

/**
 * Live click counts (JSON). Polled by the home page table.
 */

require __DIR__ . '/../lib.php';

init_db();
start_app_session();

$user = require_login();
[$scope_where, $scope_params] = link_scope_clause($user);
$stmt = get_db()->prepare("SELECT links.id, links.clicks, links.last_clicked_at FROM links {$scope_where}");
$stmt->execute($scope_params);
$rows = $stmt->fetchAll(PDO::FETCH_NUM);
$out = [];
foreach ($rows as $r) {
    $out[(string) $r[0]] = ['clicks' => $r[1] ?: 0, 'last_clicked_at' => $r[2]];
}
json_response($out);
