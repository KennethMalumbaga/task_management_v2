<?php
/*
 * Mail configuration
 *
 * Loads values from environment variables first. This keeps secrets out of
 * source control while preserving local defaults for non-sensitive fields.
 */

if (!function_exists('tm_load_env_file')) {
    function tm_load_env_file($path, $overwrite = false)
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

            if ($overwrite || getenv($name) === false) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

if (!function_exists('tm_env_request_host_is_local')) {
    function tm_env_request_host_is_local(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        return $host === ''
            || $host === 'localhost'
            || str_starts_with($host, '127.')
            || str_starts_with($host, '[::1');
    }
}

$tmEnvRoot = dirname(__DIR__);
tm_load_env_file($tmEnvRoot . '/.env');
$tmConfiguredAppEnv = strtolower(trim((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ''))));
if ($tmConfiguredAppEnv !== 'production' || tm_env_request_host_is_local()) {
    tm_load_env_file($tmEnvRoot . '/.env.local', true);
}

if (!function_exists('tm_app_url_read_configured')) {
    function tm_app_url_read_configured(): string
    {
        $value = getenv('APP_URL');
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        if (array_key_exists('APP_URL', $_ENV) && trim((string) $_ENV['APP_URL']) !== '') {
            return trim((string) $_ENV['APP_URL']);
        }

        return '';
    }
}

if (!function_exists('tm_request_host_is_local')) {
    function tm_request_host_is_local(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === '') {
            return true;
        }

        if ($host === 'localhost' || str_starts_with($host, '127.') || str_starts_with($host, '[::1')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('tm_configured_app_url_is_local_placeholder')) {
    function tm_configured_app_url_is_local_placeholder(string $url): bool
    {
        $lower = strtolower($url);
        return str_contains($lower, 'localhost') || str_contains($lower, '127.0.0.1');
    }
}

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

        $scriptDir = str_replace('\\', '/', (string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        if ($scriptDir === '.' || $scriptDir === '/') {
            $scriptDir = '';
        }
        if ($scriptDir !== '' && str_ends_with($scriptDir, '/app')) {
            $scriptDir = substr($scriptDir, 0, -4);
        }

        // Shared hosts often map the domain root directly to this folder.
        // Keep detected public URLs based on the request path, not the disk folder name.

        return rtrim($scheme . '://' . $host . $scriptDir, '/');
    }
}

$appUrl = tm_app_url_read_configured();
if ($appUrl === '') {
    $appUrl = tm_detect_app_url();
} elseif (!tm_request_host_is_local() && tm_configured_app_url_is_local_placeholder($appUrl)) {
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
