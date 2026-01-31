<?php

try {
    $pdo = new PDO(
        "pgsql:host=localhost;port=5432;dbname=task_management_db",
        "postgres",
        "admin"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
