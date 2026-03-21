<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$options = getopt('', [
    'host:',
    'port:',
    'user:',
    'database:',
    'password::',
]);

foreach (['host', 'port', 'user', 'database'] as $key) {
    if (!isset($options[$key]) || trim((string)$options[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }
}

$password = isset($options['password']) ? (string)$options['password'] : '';
if ($password === '') {
    fwrite(STDOUT, 'Railway DB password: ');
    $password = rtrim((string)fgets(STDIN), "\r\n");
}

try {
    $mysqli = mysqli_init();
    $mysqli->real_connect(
        (string)$options['host'],
        (string)$options['user'],
        $password,
        (string)$options['database'],
        (int)$options['port']
    );

    $database = $mysqli->real_escape_string((string)$options['database']);
    $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$database}'";
    $result = $mysqli->query($sql);

    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['TABLE_NAME'];
    }
    $result->free();

    if ($tables === []) {
        fwrite(STDOUT, "No tables found in database {$options['database']}.\n");
        $mysqli->close();
        exit(0);
    }

    $quotedTables = array_map(
        static fn(string $table): string => '`' . str_replace('`', '``', $table) . '`',
        $tables
    );

    $mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
    $mysqli->query('DROP TABLE IF EXISTS ' . implode(', ', $quotedTables));
    $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

    fwrite(STDOUT, 'Dropped ' . count($tables) . " table(s).\n");
    $mysqli->close();
} catch (Throwable $e) {
    fwrite(STDERR, "Schema reset failed: " . $e->getMessage() . "\n");
    exit(1);
}
