<?php
/**
 * Migration: Add score column to subtasks table
 * Allows leaders to rate subtask performance (1-5 scale)
 */

include "DB_connection.php";
include "maintenance_guard.php";

enforce_maintenance_script_access();

try {
    // Check if score column already exists
    $check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'subtasks' AND column_name = 'score'");
    
    if ($check->rowCount() == 0) {
        // Add score column (1-5 rating, nullable since not all subtasks are scored)
        $pdo->exec("ALTER TABLE subtasks ADD COLUMN score SMALLINT DEFAULT NULL CHECK (score >= 1 AND score <= 5)");
        echo "SUCCESS: Added 'score' column to subtasks table.";
    } else {
        echo "INFO: Column 'score' already exists in subtasks table.";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
