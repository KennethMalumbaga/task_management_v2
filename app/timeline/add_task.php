<?php

require_once __DIR__ . '/_common.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    timeline_api_send(false, 'Invalid request method.');
}

$auth = timeline_api_require_auth();
timeline_api_require_csrf();

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$title = trim((string)($_POST['title'] ?? ''));
$assigneeUserId = isset($_POST['assignee_user_id']) ? (int)$_POST['assignee_user_id'] : 0;

if ($projectId <= 0) {
    timeline_api_send(false, 'Project is required.');
}
if ($title === '') {
    timeline_api_send(false, 'Task title is required.');
}

timeline_api_require_leader_permissions($pdo, $projectId, $auth);

try {
    timeline_ensure_schema($pdo);
    $created = timeline_create_task_lane(
        $pdo,
        $projectId,
        $title,
        $assigneeUserId > 0 ? $assigneeUserId : null,
        (int)$auth['id']
    );
    if (!$created) {
        timeline_api_send(false, 'Unable to add timeline task.');
    }

    timeline_api_send(true, 'Timeline task added.', ['task' => $created]);
} catch (Throwable $e) {
    timeline_api_send(false, 'Unable to add timeline task.');
}
