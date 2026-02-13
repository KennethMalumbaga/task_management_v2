<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$sql = "SELECT COALESCE(type, 'NULL') AS grp_type, COUNT(*) AS cnt
        FROM groups
        GROUP BY COALESCE(type, 'NULL')
        ORDER BY cnt DESC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['grp_type'] . " => " . $r['cnt'] . PHP_EOL;
}

echo PHP_EOL . "Sample Fireguard rows:" . PHP_EOL;
$s2 = $pdo->prepare("SELECT id, name, COALESCE(type, 'NULL') AS grp_type, task_id, created_at
                     FROM groups
                     WHERE LOWER(name) = LOWER(?)
                     ORDER BY id DESC");
$s2->execute(['Fireguard']);
$rows2 = $s2->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    echo "id={$r['id']} | name={$r['name']} | type={$r['grp_type']} | task_id=" . ($r['task_id'] === null ? 'NULL' : $r['task_id']) . " | created_at={$r['created_at']}" . PHP_EOL;
}
