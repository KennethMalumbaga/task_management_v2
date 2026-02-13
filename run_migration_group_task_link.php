<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$sql = file_get_contents(__DIR__ . "/sql_add_group_task_id_link.sql");
if ($sql === false) {
    die("Failed to read migration SQL file.\n");
}

$pdo->exec($sql);
echo "Migration applied: groups.task_id link added/verified.\n";
