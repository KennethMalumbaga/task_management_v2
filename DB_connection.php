<?php

try {
    // Load local env file (optional) without overriding real env vars.
    $envFiles = [__DIR__ . '/.env.local', __DIR__ . '/.env'];
    foreach ($envFiles as $envFile) {
        if (is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
                $name = trim($name);
                $value = trim($value);
                if ($name === '') {
                    continue;
                }
                if ($value !== '' && $value[0] === '"' && str_ends_with($value, '"')) {
                    $value = substr($value, 1, -1);
                }
                if (getenv($name) === false) {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                }
            }
        }
    }

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
        $host = getenv('PGHOST') ?: 'localhost';
        $port = getenv('PGPORT') ?: 5432;
        $dbName = getenv('PGDATABASE') ?: 'task_management_db';
        $user = getenv('PGUSER') ?: 'postgres';
        $pass = getenv('PGPASSWORD') ?: 'admin';
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
