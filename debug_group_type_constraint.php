<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$sql = "SELECT conname, pg_get_constraintdef(c.oid) AS def
        FROM pg_constraint c
        JOIN pg_class t ON c.conrelid = t.oid
        WHERE t.relname = 'groups' AND c.contype = 'c'
        ORDER BY conname";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "No CHECK constraints found on groups table.\n";
} else {
    foreach ($rows as $r) {
        echo $r['conname'] . " => " . $r['def'] . PHP_EOL;
    }
}
