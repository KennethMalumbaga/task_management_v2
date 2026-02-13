<?php
/**
 * Complete Database Reset Script
 * 
 * This will:
 * 1. Truncate all tables (delete all data)
 * 2. Reset AUTO_INCREMENT counters
 * 3. Create admin user (username: admin, password: admin123)
 * 
 * WARNING: This will DELETE ALL DATA in your database!
 * 
 * Usage: Visit http://localhost/task_management_v2/reset_database.php in your browser
 */

include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

try {
    echo "<h2>Resetting Database...</h2>";
    echo "<ul>";
    
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tables = ['screenshots', 'attendance', 'notifications', 'tasks', 'users'];

    if ($driver === 'pgsql') {
        $tableList = implode(', ', $tables);
        $pdo->exec("TRUNCATE TABLE {$tableList} RESTART IDENTITY CASCADE");
        foreach ($tables as $table) {
            echo "<li>✓ Cleared table: $table</li>";
        }
    } else {
        // Legacy MySQL fallback
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
            echo "<li>✓ Cleared table: $table</li>";
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    // Generate password hash and insert admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, 'admin')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['Administrator', 'admin', $adminPassword]);
    
    echo "<li>✓ Created admin user</li>";
    echo "</ul>";
    
    echo "<h2 style='color: green;'>✓ Database reset completed successfully!</h2>";
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Admin Login Credentials:</h3>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "</div>";
    echo "<p><a href='login.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page →</a></p>";
    
} catch(PDOException $e) {
    echo "<h2 style='color: red;'>Error occurred:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'pgsql') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    } catch (Throwable $ignored) {
        // no-op
    }
}



