<?php
include "maintenance_guard.php";

enforce_maintenance_script_access();

/**
 * Get password hash for admin123
 * Visit this file in your browser: http://localhost/task_management_v2/get_password_hash.php
 * Copy the hash and replace it in reset_database.sql
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Password Hash for 'admin123'</h2>";
echo "<p><strong>Hash:</strong></p>";
echo "<textarea style='width: 100%; height: 50px; font-family: monospace;'>$hash</textarea>";
echo "<p><strong>Complete INSERT statement:</strong></p>";
echo "<textarea style='width: 100%; height: 80px; font-family: monospace;'>INSERT INTO `users` (`full_name`, `username`, `password`, `role`, `created_at`) VALUES\n('Administrator', 'admin', '$hash', 'admin', NOW());</textarea>";
echo "<p>Copy the INSERT statement above and replace the INSERT line in reset_database.sql</p>";



