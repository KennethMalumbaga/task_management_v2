<?php

try {
    $dbUrl = getenv('DATABASE_URL');

    if ($dbUrl) {
        $parts = parse_url($dbUrl);
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $dbName = ltrim($parts['path'] ?? '', '/');
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        $sslmode = $query['sslmode'] ?? (getenv('PGSSLMODE') ?: (getenv('RAILWAY_ENVIRONMENT') ? 'require' : null));
    } else {
        $host = getenv('PGHOST') ?: 'localhost';
        $port = getenv('PGPORT') ?: 5432;
        $dbName = getenv('PGDATABASE') ?: 'task_management_db';
        $user = getenv('PGUSER') ?: 'postgres';
        $pass = getenv('PGPASSWORD') ?: 'admin';
        $sslmode = getenv('PGSSLMODE') ?: null;
    }

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
    if ($sslmode) {
        $dsn .= ";sslmode={$sslmode}";
    }

    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
