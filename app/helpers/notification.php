<?php

if (!function_exists('tm_get_notification_task_id')) {
    function tm_get_notification_task_id($pdo, $notification)
    {
        if (isset($notification['task_id']) && $notification['task_id'] !== null) {
            return $notification['task_id'];
        }

        $message = $notification['message'] ?? '';
        if (preg_match("/'([^']+)'/", $message, $matches)) {
            $task_title = $matches[1];
            if (function_exists('get_task_by_title')) {
                $task = get_task_by_title($pdo, $task_title);
                if ($task != 0) {
                    return $task['id'];
                }
            }
        }

        return null;
    }
}

if (!function_exists('tm_notification_is_unread')) {
    function tm_notification_is_unread($notification)
    {
        if (!is_array($notification) || !array_key_exists('is_read', $notification)) {
            return true;
        }

        $raw = $notification['is_read'];
        if ($raw === null) {
            return true;
        }

        if (is_bool($raw)) {
            return $raw === false;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int)$raw === 0;
        }

        $normalized = strtolower(trim((string)$raw));
        return in_array($normalized, ['0', 'f', 'false', 'no', 'n', ''], true);
    }
}

if (!function_exists('tm_notification_reference_now')) {
    function tm_notification_reference_now($pdo = null)
    {
        static $cache = [];

        if ($pdo === null) {
            return time();
        }

        $key = spl_object_hash($pdo);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->query("SELECT NOW() AS now_ts");
            $rawNow = $stmt ? (string)$stmt->fetchColumn() : '';
            $parsed = strtotime($rawNow);
            if ($parsed !== false) {
                $cache[$key] = $parsed;
                return $cache[$key];
            }
        } catch (Throwable $e) {
            // Fallback to PHP runtime clock.
        }

        $cache[$key] = time();
        return $cache[$key];
    }
}

if (!function_exists('tm_notification_time_ago')) {
    function tm_notification_time_ago($notification, $nowTs = null)
    {
        $nowTs = is_numeric($nowTs) ? (int)$nowTs : time();

        $raw = '';
        if (is_array($notification)) {
            $raw = trim((string)($notification['notified_at'] ?? ''));
            if ($raw === '') {
                $raw = trim((string)($notification['date'] ?? ''));
            }
        }

        if ($raw === '') {
            return '';
        }

        // Backfilled legacy rows may have notified_at at 00:00:00 derived from DATE.
        // Treat those as day-based to avoid misleading "x hours ago".
        $legacyDate = '';
        if (is_array($notification)) {
            $legacyDate = trim((string)($notification['date'] ?? ''));
        }
        if (
            $legacyDate !== '' &&
            preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]00:00:00(?:\.0+)?(?:Z|[+\-]\d{2}:\d{2})?)?$/', $raw) &&
            strpos($raw, $legacyDate) === 0
        ) {
            $raw = $legacyDate;
        }

        // Legacy schema stores only DATE (no time). Keep that day-based.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $dayStartTs = strtotime($raw . ' 00:00:00');
            if ($dayStartTs === false) {
                return $raw;
            }
            $days = (int)floor(($nowTs - $dayStartTs) / 86400);
            if ($days <= 0) {
                return 'today';
            }
            if ($days === 1) {
                return '1 day ago';
            }
            if ($days < 7) {
                return $days . ' days ago';
            }
            if ($days < 30) {
                $weeks = (int)floor($days / 7);
                return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
            }
            if ($days < 365) {
                $months = (int)floor($days / 30);
                return $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
            }
            $years = (int)floor($days / 365);
            return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }

        $diff = $nowTs - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $minutes = (int)floor($diff / 60);
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 2592000) {
            $weeks = (int)floor($diff / 604800);
            return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 31536000) {
            $months = (int)floor($diff / 2592000);
            return $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
        }

        $years = (int)floor($diff / 31536000);
        return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
    }
}
