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
    'file:',
    'password::',
]);

$required = ['host', 'port', 'user', 'database', 'file'];
foreach ($required as $key) {
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

$file = (string)$options['file'];
if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "SQL file not found or not readable: {$file}\n");
    exit(1);
}

$sql = file_get_contents($file);
if ($sql === false) {
    fwrite(STDERR, "Failed to read SQL file: {$file}\n");
    exit(1);
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
    $mysqli->set_charset('utf8mb4');

    fwrite(STDOUT, "Connected. Importing SQL from {$file}...\n");

    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    fwrite(STDOUT, "Import completed successfully.\n");
    $mysqli->close();
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}
