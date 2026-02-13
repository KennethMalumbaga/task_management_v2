<?php
include "maintenance_guard.php";
include "DB_connection.php";
include "app/model/Group.php";

enforce_maintenance_script_access();

$sql = "SELECT name
        FROM groups
        WHERE type = 'group'
        GROUP BY LOWER(name), name
        HAVING COUNT(*) > 1";
$names = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];

$total = 0;
foreach ($names as $name) {
    $total += (int)delete_legacy_duplicate_group_chats_by_title($pdo, $name);
}

echo "Legacy duplicate group-chat cleanup done. Deleted rows: " . $total . PHP_EOL;
