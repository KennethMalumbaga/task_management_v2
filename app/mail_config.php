<?php
/*
 * Mail configuration
 *
 * Loads values from environment variables first. This keeps secrets out of
 * source control while preserving local defaults for non-sensitive fields.
 */

if (!function_exists('tm_load_env_file')) {
    function tm_load_env_file($path)
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

tm_load_env_file(dirname(__DIR__) . '/.env.local');
tm_load_env_file(dirname(__DIR__) . '/.env');

if (!function_exists('tm_detect_app_url')) {
    function tm_detect_app_url()
    {
        $scheme = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
            if ($forwarded === 'https' || $forwarded === 'http') {
                $scheme = $forwarded;
            }
        } elseif (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            $scheme = 'https';
        }

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        $scriptDir = str_replace('\\', '/', (string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        if ($scriptDir === '.' || $scriptDir === '/') {
            $scriptDir = '';
        }
        if ($scriptDir !== '' && str_ends_with($scriptDir, '/app')) {
            $scriptDir = substr($scriptDir, 0, -4);
        }

        if ($scriptDir === '') {
            $projectDirName = basename(dirname(__DIR__));
            if ($projectDirName !== '') {
                $scriptDir = '/' . $projectDirName;
            }
        }

        return rtrim($scheme . '://' . $host . $scriptDir, '/');
    }
}

$appUrl = trim((string)(getenv('APP_URL') ?: ''));
if ($appUrl === '') {
    $appUrl = tm_detect_app_url();
}

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'taskflowcore@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Task Management System');
define('APP_URL', rtrim($appUrl !== '' ? $appUrl : 'http://localhost/task_management', '/'));
?>
