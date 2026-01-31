<?php
include "DB_connection.php";

try {
    // Check if column exists
    $check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='chats' AND column_name='opened'");
    if($check->rowCount() == 0) {
        echo "Column 'opened' not found. Adding it...\n";
        $pdo->exec("ALTER TABLE chats ADD COLUMN opened SMALLINT DEFAULT 0");
        echo "Column added.\n";
    } else {
        echo "Column 'opened' exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
