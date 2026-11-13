<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'user'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    q("UPDATE users SET notif_seen_id = (SELECT COALESCE(MAX(id),0) FROM activity_log) WHERE id = ?", [uid()]);
    json_out(['ok' => true]);
}

function shape(array $r): array {
    return ['id' => (int)$r['id'], 'type' => $r['type'], 'message' => $r['message'], 'link' => $r['link'],
            'actor_name' => $r['actor_name'], 'ago' => time_ago($r['created_at'])];
}

$p = [];
$scope = activity_scope($p);
if (get('mode') === 'list') {
    $rows = q_all("SELECT a.* FROM activity_log a WHERE 1=1 $scope ORDER BY a.id DESC LIMIT 8", $p);
    $last = (int)q_val("SELECT COALESCE(MAX(a.id),0) FROM activity_log a WHERE 1=1 $scope", $p);
    json_out(['items' => array_map('shape', $rows), 'last' => $last, 'unread' => unread_count()]);
}
$after = (int)get('after', 0);
$p[] = $after;
$rows = q_all("SELECT a.* FROM activity_log a WHERE 1=1 $scope AND a.id > ? ORDER BY a.id ASC LIMIT 50", $p);
$last = $rows ? (int)end($rows)['id'] : $after;
json_out(['items' => array_map('shape', $rows), 'last' => $last, 'unread' => unread_count()]);
