<?php

require_once __DIR__ . '/_common.php';

$auth = timeline_api_require_auth();
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
if ($projectId !== null && $projectId <= 0) {
    $projectId = null;
}

try {
    timeline_ensure_schema($pdo);
    $data = timeline_get_projects_payload($pdo, $auth['role'], (int)$auth['id'], $projectId);
    $data['csrf_token'] = csrf_token('timeline_action');
    timeline_api_send(true, 'Timeline data loaded.', $data);
} catch (Throwable $e) {
    timeline_api_send(false, 'Unable to load timeline right now.');
}
