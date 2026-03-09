<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../model/Subtask.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    timeline_api_send(false, 'Invalid request method.');
}

$auth = timeline_api_require_auth();
timeline_api_require_csrf();

$timelineTaskId = isset($_POST['timeline_task_id']) ? (int)$_POST['timeline_task_id'] : 0;
$phaseId = isset($_POST['phase_id']) ? (int)$_POST['phase_id'] : 0;
$name = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$icon = (string)($_POST['icon'] ?? 'fa-circle');
$color = (string)($_POST['color'] ?? '#6C3CE1');
$startDay = isset($_POST['start_day']) ? (int)$_POST['start_day'] : 1;
$durationDays = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 1;

if ($timelineTaskId <= 0) {
    timeline_api_send(false, 'Timeline task is required.');
}
if ($name === '') {
    timeline_api_send(false, 'Phase name is required.');
}

timeline_ensure_schema($pdo);

$timelineTask = timeline_get_timeline_task_by_id($pdo, $timelineTaskId);
if (!$timelineTask) {
    timeline_api_send(false, 'Timeline task not found.');
}

$projectId = (int)($timelineTask['project_id'] ?? 0);
timeline_api_require_leader_permissions($pdo, $projectId, $auth);

try {
    $saved = timeline_save_phase(
        $pdo,
        $timelineTaskId,
        $phaseId,
        $name,
        $description,
        $icon,
        $color,
        $startDay,
        $durationDays,
        (int)$auth['id']
    );
    if (!$saved) {
        timeline_api_send(false, 'Unable to save phase.');
    }

    $phaseSubtaskSynced = false;
    try {
        $syncedSubtask = subtask_sync_from_timeline_phase(
            $pdo,
            $projectId,
            $timelineTask,
            $saved,
            (int)$auth['id']
        );
        $phaseSubtaskSynced = (bool)$syncedSubtask;
    } catch (Throwable $syncErr) {
        $phaseSubtaskSynced = false;
    }

    timeline_api_send(true, 'Phase saved.', [
        'phase' => $saved,
        'project_id' => $projectId,
        'timeline_task_id' => $timelineTaskId,
        'phase_subtask_synced' => $phaseSubtaskSynced,
    ]);
} catch (Throwable $e) {
    timeline_api_send(false, 'Unable to save phase.');
}
