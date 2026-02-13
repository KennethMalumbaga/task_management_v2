<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'subtasks'");
$result = $q->fetchAll(PDO::FETCH_ASSOC);
print_r($result);
?>
