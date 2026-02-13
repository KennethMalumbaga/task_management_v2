<?php
/**
 * Migration: Add per-assignee rating fields to task_assignees
 * Supports separate leader/member ratings on task review.
 */

include "DB_connection.php";
include "maintenance_guard.php";

enforce_maintenance_script_access();

try {
    $checks = [
        'performance_rating' => "ALTER TABLE task_assignees ADD COLUMN performance_rating SMALLINT DEFAULT NULL CHECK (performance_rating >= 1 AND performance_rating <= 5)",
        'rating_comment' => "ALTER TABLE task_assignees ADD COLUMN rating_comment TEXT DEFAULT NULL",
        'rated_by' => "ALTER TABLE task_assignees ADD COLUMN rated_by INTEGER DEFAULT NULL",
        'rated_at' => "ALTER TABLE task_assignees ADD COLUMN rated_at TIMESTAMP DEFAULT NULL"
    ];

    $added = [];
    $already = [];

    foreach ($checks as $column => $sql) {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = 'task_assignees' AND column_name = ?");
        $stmt->execute([$column]);
        if ($stmt->fetchColumn()) {
            $already[] = $column;
            continue;
        }
        $pdo->exec($sql);
        $added[] = $column;
    }

    if (!empty($added)) {
        echo "SUCCESS: Added columns -> " . implode(", ", $added) . PHP_EOL;
    } else {
        echo "INFO: All per-assignee rating columns already exist." . PHP_EOL;
    }

    if (!empty($already)) {
        echo "INFO: Existing columns -> " . implode(", ", $already) . PHP_EOL;
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
