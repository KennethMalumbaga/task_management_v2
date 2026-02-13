<?php
include "maintenance_guard.php";
include "DB_connection.php";
include "app/model/Group.php";

enforce_maintenance_script_access();

$deleted = delete_orphan_task_chat_groups($pdo);
echo "Orphan task chat cleanup done. Deleted rows: " . (int)$deleted . "\n";
