<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

$options = getopt('', [
    'input:',
    'output:',
]);

foreach (['input', 'output'] as $key) {
    if (!isset($options[$key]) || trim((string)$options[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}\n");
        exit(1);
    }
}

$input = (string)$options['input'];
$output = (string)$options['output'];

if (!is_file($input) || !is_readable($input)) {
    fwrite(STDERR, "Input file not found or not readable: {$input}\n");
    exit(1);
}

$sql = file_get_contents($input);
if ($sql === false) {
    fwrite(STDERR, "Failed to read input file: {$input}\n");
    exit(1);
}

$patterns = [
    'DEFAULT curdate()' => 'DEFAULT (curdate())',
];

$replacementCount = 0;
foreach ($patterns as $search => $replace) {
    $sql = str_replace($search, $replace, $sql, $count);
    $replacementCount += $count;
}

if (file_put_contents($output, $sql) === false) {
    fwrite(STDERR, "Failed to write output file: {$output}\n");
    exit(1);
}

fwrite(STDOUT, "Wrote fixed dump to {$output}\n");
fwrite(STDOUT, "Applied {$replacementCount} compatibility replacement(s).\n");
