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
