<?php
include "../DB_connection.php";

function addColumn($pdo, $table, $column, $type) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        if ($check->rowCount() == 0) {
            $sql = "ALTER TABLE $table ADD COLUMN $column $type";
            $pdo->exec($sql);
            echo "Added column $column to $table.<br>";
        } else {
            echo "Column $column already exists in $table.<br>";
        }
    } catch (PDOException $e) {
        echo "Error adding $column: " . $e->getMessage() . "<br>";
    }
}

echo "Updating schema...<br>";
addColumn($pdo, 'users', 'bio', 'TEXT DEFAULT NULL');
addColumn($pdo, 'users', 'phone', 'VARCHAR(20) DEFAULT NULL');
addColumn($pdo, 'users', 'skills', 'TEXT DEFAULT NULL');
addColumn($pdo, 'users', 'address', 'TEXT DEFAULT NULL');
addColumn($pdo, 'users', 'profile_pic', "VARCHAR(255) DEFAULT 'user.png'");
echo "Schema update completed.";
?>
