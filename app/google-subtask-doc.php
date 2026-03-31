<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once "helpers/subtask_google_docs.php";
require_once "helpers/google_auth.php";
require_once "model/GoogleWorkspace.php";

function google_subtask_doc_redirect($taskId, $message = '', $isError = true)
{
    $params = [];
    $taskId = (int)$taskId;
    if ($taskId > 0) {
        $params[] = 'open_task=' . urlencode((string)$taskId);
    }
    if (trim((string)$message) !== '') {
        $params[] = ($isError ? 'error=' : 'success=') . urlencode((string)$message);
    }

    $target = "../my_task.php";
    if (!empty($params)) {
        $target .= '?' . implode('&', $params);
    }
    header("Location: " . $target);
    exit();
}

function google_subtask_doc_pending_valid($pending, $currentUserId)
{
    if (!is_array($pending)) {
        return false;
    }

    $createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
    $userId = isset($pending['user_id']) ? (int)$pending['user_id'] : 0;
    $action = trim((string)($pending['action'] ?? ''));

    return $createdAt > 0
        && (time() - $createdAt) <= 1800
        && $userId > 0
        && $userId === (int)$currentUserId
        && $action === 'create_subtask_google_doc';
}

function google_subtask_doc_store_pending($subtaskId, $taskId, $userId)
{
    $state = '';
    try {
        $state = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $state = hash('sha256', uniqid('google_subtask_doc_', true) . microtime(true));
    }

    $_SESSION['pending_google_workspace'] = [
        'action' => 'create_subtask_google_doc',
        'subtask_id' => (int)$subtaskId,
        'task_id' => (int)$taskId,
        'user_id' => (int)$userId,
        'state' => $state,
        'created_at' => time(),
    ];

    return $state;
}

function google_subtask_doc_start_oauth($subtaskId, $taskId, $userId, $forceConsent = false)
{
    if (!google_workspace_is_enabled()) {
        google_subtask_doc_redirect($taskId, "Google Workspace integration is not configured yet.");
    }

    $state = google_subtask_doc_store_pending($subtaskId, $taskId, $userId);
    header("Location: " . google_workspace_build_auth_url($state, $forceConsent));
    exit();
}

function google_subtask_doc_create_from_refresh_token($pdo, $currentUserId, array $context, $refreshToken)
{
    $refresh = google_workspace_refresh_access_token($refreshToken);
    if (!$refresh['ok']) {
        $error = strtolower((string)($refresh['error'] ?? ''));
        if (strpos($error, 'invalid_grant') !== false) {
            google_workspace_delete_token_record($pdo, $currentUserId);
            google_subtask_doc_start_oauth((int)$context['id'], (int)$context['task_id'], $currentUserId, true);
        }

        google_subtask_doc_redirect((int)$context['task_id'], (string)($refresh['error'] ?? 'Unable to access Google Docs right now.'));
    }

    $tokens = (array)($refresh['tokens'] ?? []);
    $accessToken = trim((string)($tokens['access_token'] ?? ''));
    if ($accessToken === '') {
        google_subtask_doc_redirect((int)$context['task_id'], "Google did not return an access token.");
    }

    $result = subtask_google_doc_create_and_store($pdo, $context, $accessToken);
    if (!$result['ok']) {
        google_subtask_doc_redirect((int)$context['task_id'], (string)($result['error'] ?? 'Unable to create the Google Workspace file.'));
    }

    unset($_SESSION['pending_google_workspace']);
    header("Location: " . (string)$result['url']);
    exit();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: ../login.php?error=" . urlencode("First login"));
    exit();
}

$currentUserId = (int)$_SESSION['id'];
$currentRole = (string)$_SESSION['role'];

if (isset($_GET['resume']) && $_GET['resume'] === '1') {
    $pending = $_SESSION['pending_google_workspace'] ?? null;
    if (!google_subtask_doc_pending_valid($pending, $currentUserId)) {
        unset($_SESSION['pending_google_workspace']);
        google_subtask_doc_redirect(0, "Google Docs request expired. Please try again.");
    }

    $context = subtask_google_doc_fetch_context($pdo, (int)($pending['subtask_id'] ?? 0));
    if (!$context) {
        unset($_SESSION['pending_google_workspace']);
        google_subtask_doc_redirect((int)($pending['task_id'] ?? 0), "Subtask not found.");
    }
    if (!subtask_is_google_workspace_phase($context)) {
        unset($_SESSION['pending_google_workspace']);
        google_subtask_doc_redirect((int)$context['task_id'], "This phase is not configured as a Google Workspace step.");
    }
    if ((int)($context['member_id'] ?? 0) !== $currentUserId) {
        unset($_SESSION['pending_google_workspace']);
        google_subtask_doc_redirect((int)$context['task_id'], "Only the assigned member can create the Google Doc for this phase.");
    }

    $tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
    $refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        google_subtask_doc_start_oauth((int)$context['id'], (int)$context['task_id'], $currentUserId, true);
    }

    google_subtask_doc_create_from_refresh_token($pdo, $currentUserId, $context, $refreshToken);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    google_subtask_doc_redirect(0, "Invalid Google Docs request.");
}

if (!csrf_verify('google_subtask_doc_form', $_POST['csrf_token'] ?? null, true)) {
    google_subtask_doc_redirect(0, "Invalid or expired request. Please refresh and try again.");
}

$subtaskId = isset($_POST['subtask_id']) ? (int)$_POST['subtask_id'] : 0;
$context = subtask_google_doc_fetch_context($pdo, $subtaskId);
if (!$context) {
    google_subtask_doc_redirect(0, "Subtask not found.");
}

if (!subtask_is_google_workspace_phase($context)) {
    google_subtask_doc_redirect((int)$context['task_id'], "This phase is not configured as a Google Workspace step.");
}

if ((int)($context['member_id'] ?? 0) !== $currentUserId) {
    google_subtask_doc_redirect((int)$context['task_id'], "Only the assigned member can create the Google Doc for this phase.");
}

$existingUrl = trim((string)($context['google_doc_url'] ?? ''));
if ($existingUrl !== '') {
    header("Location: " . $existingUrl);
    exit();
}

$tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
$refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
if ($refreshToken === '') {
    google_subtask_doc_start_oauth((int)$context['id'], (int)$context['task_id'], $currentUserId, true);
}

google_subtask_doc_create_from_refresh_token($pdo, $currentUserId, $context, $refreshToken);
