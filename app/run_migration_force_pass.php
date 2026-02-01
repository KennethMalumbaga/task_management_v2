<?php
try {
    include __DIR__ . "/../DB_connection.php";
    
    // Add must_change_password column
    $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN DEFAULT FALSE";
    $pdo->exec($sql);
    
    echo "Migration successful: Added must_change_password column.\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
