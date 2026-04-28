<?php

if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

$loadEnvFile = static function (string $envFile): void {
    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

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
};

$env = static function (array $names, ?string $default = null): ?string {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return (string) $_ENV[$name];
        }
    }

    return $default;
};

$parseDatabaseUrl = static function (string $databaseUrl): array {
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        throw new RuntimeException('Invalid DATABASE_URL format.');
    }

    return [
        'host' => (string) ($parts['host'] ?? 'localhost'),
        'port' => (string) ($parts['port'] ?? 3306),
        'name' => ltrim((string) ($parts['path'] ?? ''), '/'),
        'user' => isset($parts['user']) ? rawurldecode((string) $parts['user']) : '',
        'pass' => isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '',
    ];
};

try {
    foreach ([__DIR__ . '/.env.local', __DIR__ . '/.env'] as $envFile) {
        $loadEnvFile($envFile);
    }

    $databaseUrl = $env(['DATABASE_URL', 'MYSQL_URL']);

    if ($databaseUrl) {
        $config = $parseDatabaseUrl($databaseUrl);
    } else {
        $config = [
            'host' => $env(['DB_HOST', 'MYSQLHOST', 'MYSQL_HOST'], 'localhost'),
            'port' => $env(['DB_PORT', 'MYSQLPORT', 'MYSQL_PORT'], '3306'),
            'name' => $env(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE']),
            'user' => $env(['DB_USER', 'MYSQLUSER', 'MYSQL_USER']),
            'pass' => $env(['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD'], ''),
        ];
    }

    if ($config['user'] === '' && $config['name'] !== '' && preg_match('/^u\d+_/i', $config['name'])) {
        // Hostinger usernames are often the same as the database name.
        $config['user'] = $config['name'];
    }

    $missing = [];
    if ($config['name'] === '') {
        $missing[] = 'DB_NAME';
    }
    if ($config['user'] === '') {
        $missing[] = 'DB_USER';
    }

    if ($missing !== []) {
        throw new RuntimeException('Missing database setting(s): ' . implode(', ', $missing) . '. Check .env.local or your hosting settings.');
    }

    $timeout = (int) $env(['DB_TIMEOUT', 'MYSQL_TIMEOUT'], '5');
    if ($timeout < 1) {
        $timeout = 5;
    }

    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4;connect_timeout={$timeout}";

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_TIMEOUT => $timeout,
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    die('Database connection failed: ' . $e->getMessage());
}
