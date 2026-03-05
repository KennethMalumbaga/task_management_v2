<?php

require_once __DIR__ . '/_common.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    timeline_api_send(false, 'Invalid request method.');
}

$auth = timeline_api_require_auth();
timeline_api_require_csrf();

$phaseId = isset($_POST['phase_id']) ? (int)$_POST['phase_id'] : 0;
if ($phaseId <= 0) {
    timeline_api_send(false, 'Phase is required.');
}

timeline_ensure_schema($pdo);

$phase = timeline_get_phase_by_id($pdo, $phaseId);
if (!$phase) {
    timeline_api_send(false, 'Phase not found.');
}

$timelineTaskId = (int)($phase['timeline_task_id'] ?? 0);
$timelineTask = timeline_get_timeline_task_by_id($pdo, $timelineTaskId);
if (!$timelineTask) {
    timeline_api_send(false, 'Timeline task not found.');
}

$projectId = (int)($timelineTask['project_id'] ?? 0);
timeline_api_require_leader_permissions($pdo, $projectId, $auth);

try {
    $deleted = timeline_delete_phase($pdo, $phaseId);
    if (!$deleted) {
        timeline_api_send(false, 'Unable to delete phase.');
    }
    timeline_api_send(true, 'Phase removed.');
} catch (Throwable $e) {
    timeline_api_send(false, 'Unable to delete phase.');
}
