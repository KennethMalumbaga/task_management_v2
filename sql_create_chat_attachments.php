<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

try {
    $sql = "CREATE TABLE IF NOT EXISTS chat_attachments (
        attachment_id SERIAL PRIMARY KEY,
        chat_id INT NOT NULL,
        attachment_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Table 'chat_attachments' created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
