<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$sql = "SELECT g.id, g.name, g.type, g.task_id, g.created_at,
               EXISTS(SELECT 1 FROM tasks t WHERE t.id = g.task_id) AS task_exists_by_id,
               (SELECT COUNT(*) FROM tasks t2 WHERE t2.title = g.name) AS task_count_by_title
        FROM groups g
        WHERE g.type = 'task_chat'
        ORDER BY g.name ASC, g.created_at DESC, g.id DESC";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo implode(" | ", [
        "id=".$r['id'],
        "name=".$r['name'],
        "task_id=".($r['task_id'] === null ? "NULL" : $r['task_id']),
        "created_at=".$r['created_at'],
        "exists_by_id=".$r['task_exists_by_id'],
        "task_count_by_title=".$r['task_count_by_title']
    ]) . PHP_EOL;
}
