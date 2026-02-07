<?php

try {
    $dbUrl = getenv('DATABASE_URL') ?: getenv('DATABASE_URL_PRIVATE') ?: getenv('DATABASE_PUBLIC_URL');

    if ($dbUrl) {
        $parts = parse_url($dbUrl);
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        $host = $parts['host'] ?? 'postgres.railway.internal';
        $port = $parts['port'] ?? 5432;
        $dbName = ltrim($parts['path'] ?? '', '/');
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        $sslmode = $query['sslmode'] ?? (getenv('PGSSLMODE') ?: (getenv('RAILWAY_ENVIRONMENT') ? 'require' : null));
    } else {
        $host = getenv('PGHOST') ?: 'postgres.railway.internal';
        $port = getenv('PGPORT') ?: 5432;
        $dbName = getenv('PGDATABASE') ?: 'task_management_db';
        $user = getenv('PGUSER') ?: 'postgres';
        $pass = getenv('PGPASSWORD') ?: 'hOXniYYYZRxvdhIhsBojEVQpiQCJuztM';
        $sslmode = getenv('PGSSLMODE') ?: null;
    }

    if (($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') && getenv('RAILWAY_ENVIRONMENT')) {
        die("Database connection failed: missing Railway Postgres environment variables (DATABASE_URL/PGHOST/etc).");
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
