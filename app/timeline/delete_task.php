<?php

require_once __DIR__ . '/_common.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    timeline_api_send(false, 'Invalid request method.');
}

$auth = timeline_api_require_auth();
timeline_api_require_csrf();

$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
if ($taskId <= 0) {
    timeline_api_send(false, 'Timeline task is required.');
}

timeline_ensure_schema($pdo);

$taskRow = timeline_get_timeline_task_by_id($pdo, $taskId);
if (!$taskRow) {
    timeline_api_send(false, 'Timeline task not found.');
}

$projectId = (int)($taskRow['project_id'] ?? 0);
timeline_api_require_leader_permissions($pdo, $projectId, $auth);

try {
    $deleted = timeline_delete_task_lane($pdo, $taskId);
    if (!$deleted) {
        timeline_api_send(false, 'Unable to delete timeline task.');
    }

    timeline_api_send(true, 'Timeline task removed.');
} catch (Throwable $e) {
    timeline_api_send(false, 'Unable to delete timeline task.');
}

