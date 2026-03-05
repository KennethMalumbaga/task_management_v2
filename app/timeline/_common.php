<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../../inc/csrf.php';
require_once __DIR__ . '/../model/Timeline.php';

if (!function_exists('timeline_api_send')) {
    function timeline_api_send($ok, $message = '', $extra = [])
    {
        header('Content-Type: application/json; charset=utf-8');
        $payload = array_merge(
            [
                'ok' => (bool)$ok,
                'message' => (string)$message,
            ],
            is_array($extra) ? $extra : []
        );
        echo json_encode($payload);
        exit();
    }
}

if (!function_exists('timeline_api_require_auth')) {
    function timeline_api_require_auth()
    {
        if (!isset($_SESSION['id'], $_SESSION['role'])) {
            timeline_api_send(false, 'First login');
        }

        $role = (string)$_SESSION['role'];
        if ($role !== 'admin' && $role !== 'employee') {
            timeline_api_send(false, 'Unauthorized role');
        }

        return [
            'id' => (int)$_SESSION['id'],
            'role' => $role,
        ];
    }
}

if (!function_exists('timeline_api_require_csrf')) {
    function timeline_api_require_csrf()
    {
        $token = $_POST['csrf_token'] ?? null;
        if (!csrf_verify('timeline_action', $token, false)) {
            timeline_api_send(false, 'Invalid or expired request. Please refresh and try again.');
        }
    }
}

if (!function_exists('timeline_api_require_leader_permissions')) {
    function timeline_api_require_leader_permissions($pdo, $projectId, $auth)
    {
        $projectId = (int)$projectId;
        if ($projectId <= 0) {
            timeline_api_send(false, 'Invalid project context.');
        }

        if (!timeline_can_modify_project($pdo, $projectId, $auth['role'], (int)$auth['id'])) {
            timeline_api_send(false, 'Only the assigned project leader can edit this timeline.');
        }
    }
}
