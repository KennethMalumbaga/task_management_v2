<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

try {
    $sql = "ALTER TABLE chats ADD COLUMN attachment VARCHAR(255) DEFAULT NULL";
    $pdo->exec($sql);
    echo "Column 'attachment' added successfully to 'chats' table.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column 'attachment' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
