<?php
include "DB_connection.php";
try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'chats'");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
