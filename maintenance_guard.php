<?php
/*
 * Shared access guard for maintenance/debug scripts.
 *
 * Rules:
 * - Always allow CLI execution.
 * - Allow if ALLOW_MAINTENANCE_SCRIPTS=true/1 is set.
 * - Allow localhost requests when APP_ENV is not production.
 */

if (!function_exists('is_maintenance_script_allowed')) {
    function is_maintenance_script_allowed()
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $explicit = getenv('ALLOW_MAINTENANCE_SCRIPTS');
        if ($explicit !== false) {
            $normalized = strtolower(trim((string)$explicit));
            if ($normalized === '1' || $normalized === 'true' || $normalized === 'yes') {
                return true;
            }
        }

        $appEnv = strtolower((string)(getenv('APP_ENV') ?: 'development'));
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocalhost = in_array($remoteAddr, ['127.0.0.1', '::1'], true);

        return $isLocalhost && $appEnv !== 'production';
    }
}

if (!function_exists('enforce_maintenance_script_access')) {
    function enforce_maintenance_script_access()
    {
        if (is_maintenance_script_allowed()) {
            return;
        }

        if (PHP_SAPI !== 'cli') {
            http_response_code(403);
        }

        exit('Forbidden: maintenance script access is disabled.');
    }
}

