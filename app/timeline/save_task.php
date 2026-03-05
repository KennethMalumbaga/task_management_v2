<?php

require_once __DIR__ . '/_common.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    timeline_api_send(false, 'Invalid request method.');
}

$auth = timeline_api_require_auth();
timeline_api_require_csrf();

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$title = trim((string)($_POST['title'] ?? ''));
$assigneeUserId = isset($_POST['assignee_user_id']) ? (int)$_POST['assignee_user_id'] : 0;

if ($title === '') {
    timeline_api_send(false, 'Task title is required.');
}

timeline_ensure_schema($pdo);

$taskRow = null;
if ($taskId > 0) {
    $taskRow = timeline_get_timeline_task_by_id($pdo, $taskId);
    if (!$taskRow) {
        timeline_api_send(false, 'Timeline task not found.');
    }
    $projectId = (int)($taskRow['project_id'] ?? 0);
}

if ($projectId <= 0) {
    timeline_api_send(false, 'Project is required.');
}

timeline_api_require_leader_permissions($pdo, $projectId, $auth);

try {
    $saved = timeline_save_task_lane(
        $pdo,
        $projectId,
        $taskId,
        $title,
        $assigneeUserId > 0 ? $assigneeUserId : null,
        (int)$auth['id']
    );
    if (!$saved) {
        timeline_api_send(false, 'Unable to save timeline task.');
    }

    timeline_api_send(true, 'Timeline task saved.', [
        'task' => $saved,
        'project_id' => $projectId,
    ]);
} catch (Throwable $e) {
    timeline_api_send(false, 'Unable to save timeline task.');
}

