<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$title = "Fireguard";
$stmt = $pdo->prepare("SELECT id, title, status, created_at FROM tasks WHERE LOWER(title) = LOWER(?) ORDER BY id DESC");
$stmt->execute([$title]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Tasks with title '{$title}': " . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    echo "id={$r['id']} | status={$r['status']} | created_at={$r['created_at']}" . PHP_EOL;
}
