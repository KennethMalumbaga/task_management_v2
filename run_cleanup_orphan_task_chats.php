<?php
include "DB_connection.php";
include "app/model/Group.php";

$deleted = delete_orphan_task_chat_groups($pdo);
echo "Orphan task chat cleanup done. Deleted rows: " . (int)$deleted . "\n";
