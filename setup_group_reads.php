<?php
include "DB_connection.php";

try {
    $sql = "CREATE TABLE IF NOT EXISTS group_message_reads (
        id SERIAL PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        last_message_id INT NOT NULL,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Table group_message_reads created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
