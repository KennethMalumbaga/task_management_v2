<?php
try {
    include __DIR__ . "/../DB_connection.php";
    
    // Check if column exists again just in case, or ALTER TABLE IF NOT EXISTS (PG 9.6+ supports IF EXISTS for ADD COLUMN)
    // SQL: ALTER TABLE tasks ADD COLUMN IF NOT EXISTS rating INTEGER DEFAULT 0;
    
    $sql = "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS rating INTEGER DEFAULT 0";
    $pdo->exec($sql);
    echo "Migration successful: Added rating column to tasks table.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
