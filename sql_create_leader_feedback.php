<?php
/**
 * Migration: Create leader_feedback table
 * Members can rate their task leader after task acceptance.
 */

include "DB_connection.php";
include "maintenance_guard.php";

enforce_maintenance_script_access();

try {
    $sql = "CREATE TABLE IF NOT EXISTS leader_feedback (
                id BIGSERIAL PRIMARY KEY,
                task_id INTEGER NOT NULL,
                leader_id INTEGER NOT NULL,
                member_id INTEGER NOT NULL,
                rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                comment TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
                CONSTRAINT leader_feedback_unique UNIQUE (task_id, leader_id, member_id)
            )";
    $pdo->exec($sql);
    echo "SUCCESS: leader_feedback table is ready.";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
