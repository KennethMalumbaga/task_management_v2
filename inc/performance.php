<?php

if (!function_exists('performance_monitor_threshold_ms')) {
    function performance_monitor_threshold_ms()
    {
        $raw = getenv('PERFORMANCE_LOG_THRESHOLD_MS');
        if ($raw === false || trim((string)$raw) === '') {
            return 1500;
        }

        $threshold = (int)$raw;
        return $threshold > 0 ? $threshold : 1500;
    }
}

if (!function_exists('performance_monitor_log_path')) {
    function performance_monitor_log_path()
    {
        $customPath = getenv('PERFORMANCE_LOG_PATH');
        if ($customPath !== false && trim((string)$customPath) !== '') {
            return (string)$customPath;
        }

        return dirname(__DIR__) . '/tmp/performance.log';
    }
}

if (!function_exists('performance_monitor_normalize_label')) {
    function performance_monitor_normalize_label($label)
    {
        $label = trim((string)$label);
        if ($label === '') {
            return 'request';
        }

        $label = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $label);
        return trim((string)$label, '_') ?: 'request';
    }
}

if (!function_exists('performance_monitor_request')) {
    function performance_monitor_request($label, $thresholdMs = null)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        static $registeredLabels = [];

        $label = performance_monitor_normalize_label($label);
        if (isset($registeredLabels[$label])) {
            return;
        }
        $registeredLabels[$label] = true;

        $thresholdMs = $thresholdMs !== null ? (int)$thresholdMs : performance_monitor_threshold_ms();
        if ($thresholdMs <= 0) {
            return;
        }

        $startedAt = microtime(true);

        register_shutdown_function(function () use ($label, $thresholdMs, $startedAt) {
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            if ($durationMs < $thresholdMs) {
                return;
            }

            $logPath = performance_monitor_log_path();
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }

            $userId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
            $orgId = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : 0;
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
            $status = http_response_code();
            if (!$status) {
                $status = 200;
            }

            $line = json_encode([
                'ts' => date('c'),
                'label' => $label,
                'duration_ms' => $durationMs,
                'threshold_ms' => $thresholdMs,
                'method' => $method,
                'script' => $script,
                'status' => (int)$status,
                'user_id' => $userId,
                'organization_id' => $orgId,
            ], JSON_UNESCAPED_SLASHES);

            if ($line !== false) {
                @error_log($line . PHP_EOL, 3, $logPath);
            }
        });
    }
}
