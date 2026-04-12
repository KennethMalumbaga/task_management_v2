<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once "helpers/google_calendar.php";
require_once "helpers/google_auth.php";
require_once "model/GoogleWorkspace.php";
require_once "model/Group.php";
require_once "model/Task.php";
require_once "model/Bulletin.php";
require_once "model/CalendarMeeting.php";
require_once "model/CalendarMeetingReminder.php";

function google_calendar_meeting_redirect($meetingDate, $message = '', $isError = true)
{
    $meetingDate = trim((string)$meetingDate);
    $params = [];

    if ($meetingDate !== '') {
        $timestamp = strtotime($meetingDate);
        if ($timestamp !== false) {
            $params[] = 'date=' . urlencode(date('Y-m-d', $timestamp));
            $params[] = 'month=' . urlencode(date('m', $timestamp));
            $params[] = 'year=' . urlencode(date('Y', $timestamp));
        }
    }

    if (trim((string)$message) !== '') {
        $params[] = ($isError ? 'error=' : 'success=') . urlencode((string)$message);
    }

    $target = "../calendar.php";
    if (!empty($params)) {
        $target .= '?' . implode('&', $params);
    }

    header("Location: " . $target);
    exit();
}

function google_calendar_meeting_normalize_action($action)
{
    $action = strtolower(trim((string)$action));
    return in_array($action, ['create', 'update', 'delete'], true) ? $action : '';
}

function google_calendar_meeting_pending_valid($pending, $currentUserId)
{
    if (!is_array($pending)) {
        return false;
    }

    $createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
    $userId = isset($pending['user_id']) ? (int)$pending['user_id'] : 0;
    $action = google_calendar_meeting_normalize_action($pending['action'] ?? '');

    return $createdAt > 0
        && (time() - $createdAt) <= 1800
        && $userId > 0
        && $userId === (int)$currentUserId
        && $action !== '';
}

function google_calendar_meeting_store_pending(array $payload, $userId)
{
    $action = google_calendar_meeting_normalize_action($payload['action'] ?? 'create');
    if ($action === '') {
        $action = 'create';
    }

    try {
        $state = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $state = hash('sha256', uniqid('google_calendar_meeting_', true) . microtime(true));
    }

    $_SESSION['pending_google_calendar_meeting'] = [
        'action' => $action,
        'meeting_id' => !empty($payload['meeting_id']) ? (int)$payload['meeting_id'] : null,
        'title' => trim((string)($payload['title'] ?? '')),
        'description' => trim((string)($payload['description'] ?? '')),
        'meeting_date' => trim((string)($payload['meeting_date'] ?? '')),
        'start_time' => trim((string)($payload['start_time'] ?? '')),
        'end_time' => trim((string)($payload['end_time'] ?? '')),
        'timezone' => trim((string)($payload['timezone'] ?? '')) ?: google_calendar_timezone(),
        'audience_type' => trim((string)($payload['audience_type'] ?? 'everyone')),
        'group_id' => !empty($payload['group_id']) ? (int)$payload['group_id'] : null,
        'task_id' => !empty($payload['task_id']) ? (int)$payload['task_id'] : null,
        'google_event_id' => trim((string)($payload['google_event_id'] ?? '')),
        'user_id' => (int)$userId,
        'state' => $state,
        'created_at' => time(),
    ];

    return $state;
}

function google_calendar_meeting_start_oauth(array $payload, $userId, $forceConsent = false)
{
    if (!google_calendar_is_enabled()) {
        google_calendar_meeting_redirect((string)($payload['meeting_date'] ?? ''), 'Google Calendar integration is not configured yet.');
    }

    $state = google_calendar_meeting_store_pending($payload, $userId);
    header("Location: " . google_calendar_build_auth_url($state, $forceConsent));
    exit();
}

function google_calendar_meeting_validate_payload(array $payload)
{
    $meetingDate = trim((string)($payload['meeting_date'] ?? ''));
    $title = trim((string)($payload['title'] ?? ''));
    $startTime = substr(trim((string)($payload['start_time'] ?? '')), 0, 5);
    $endTime = substr(trim((string)($payload['end_time'] ?? '')), 0, 5);
    $description = trim((string)($payload['description'] ?? ''));
    $timezone = trim((string)($payload['timezone'] ?? '')) ?: google_calendar_timezone();

    if ($title === '') {
        return [false, 'Please add a meeting title.', []];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meetingDate)) {
        return [false, 'Please choose a valid meeting date.', []];
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        return [false, 'Please choose a start and end time for the meeting.', []];
    }

    try {
        $tz = new DateTimeZone($timezone);
        $startAt = new DateTimeImmutable($meetingDate . ' ' . $startTime . ':00', $tz);
        $endAt = new DateTimeImmutable($meetingDate . ' ' . $endTime . ':00', $tz);
    } catch (Throwable $e) {
        return [false, 'Meeting date or time is invalid.', []];
    }

    if ($endAt <= $startAt) {
        return [false, 'Meeting end time must be after the start time.', []];
    }

    return [
        true,
        '',
        [
            'title' => $title,
            'description' => $description,
            'meeting_date' => $meetingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
            'audience_type' => calendar_meetings_normalize_audience_type($payload['audience_type'] ?? 'everyone'),
            'group_id' => !empty($payload['group_id']) ? (int)$payload['group_id'] : null,
            'task_id' => !empty($payload['task_id']) ? (int)$payload['task_id'] : null,
        ],
    ];
}

function google_calendar_meeting_validate_existing_meeting($pdo, $meetingId, $currentUserId, $sessionRole)
{
    $meeting = calendar_meetings_get_by_id($pdo, $meetingId);
    if (!$meeting) {
        return [false, 'Meeting not found.', null];
    }

    if (!calendar_meetings_user_can_manage($meeting, $currentUserId, $sessionRole)) {
        return [false, 'You do not have permission to manage that meeting.', null];
    }

    return [true, '', $meeting];
}

function google_calendar_meeting_sync_admin_bulletin($pdo, array $meetingData, $createdBy)
{
    $meetingId = (int)($meetingData['source_id'] ?? 0);
    if ($meetingId <= 0) {
        return;
    }

    delete_bulletin_posts_by_source($pdo, 'calendar_meeting', $meetingId);

    $groupName = '';
    $taskName = '';

    if (!empty($meetingData['group_id'])) {
        $groupRow = get_group_by_id($pdo, (int)$meetingData['group_id']);
        if ($groupRow) {
            $groupName = trim((string)($groupRow['name'] ?? ''));
        }
    }

    if (!empty($meetingData['task_id'])) {
        $taskRow = get_task_by_id($pdo, (int)$meetingData['task_id']);
        if ($taskRow) {
            $taskName = trim((string)($taskRow['title'] ?? ''));
        }
    }

    try {
        create_meeting_bulletin_reminder($pdo, [
            'title' => (string)($meetingData['title'] ?? ''),
            'meeting_date' => (string)($meetingData['meeting_date'] ?? ''),
            'start_time' => (string)($meetingData['start_time'] ?? ''),
            'end_time' => (string)($meetingData['end_time'] ?? ''),
            'audience_type' => (string)($meetingData['audience_type'] ?? 'everyone'),
            'group_id' => !empty($meetingData['group_id']) ? (int)$meetingData['group_id'] : null,
            'task_id' => !empty($meetingData['task_id']) ? (int)$meetingData['task_id'] : null,
            'group_name' => $groupName,
            'task_name' => $taskName,
            'source_id' => $meetingId,
        ], (int)$createdBy);
    } catch (Throwable $e) {
        // Keep the meeting action successful even if bulletin sync fails.
    }
}

function google_calendar_meeting_create_local($pdo, $currentUserId, array $payload, array $event)
{
    $meetingId = calendar_meetings_insert($pdo, [
        'title' => (string)($payload['title'] ?? ''),
        'description' => (string)($payload['description'] ?? ''),
        'meeting_date' => (string)($payload['meeting_date'] ?? ''),
        'start_time' => (string)($payload['start_time'] ?? ''),
        'end_time' => (string)($payload['end_time'] ?? ''),
        'timezone' => (string)($payload['timezone'] ?? google_calendar_timezone()),
        'audience_type' => (string)($payload['audience_type'] ?? 'everyone'),
        'group_id' => !empty($payload['group_id']) ? (int)$payload['group_id'] : null,
        'task_id' => !empty($payload['task_id']) ? (int)$payload['task_id'] : null,
        'google_event_id' => (string)($event['id'] ?? ''),
        'google_calendar_url' => (string)($event['htmlLink'] ?? ''),
        'google_meet_url' => (string)($event['hangoutLink'] ?? ''),
        'google_conference_id' => google_calendar_extract_conference_id($event),
        'created_by' => $currentUserId,
    ]);

    if ($meetingId <= 0) {
        return 0;
    }

    $meeting = calendar_meetings_get_by_id($pdo, $meetingId);
    if ($meeting) {
        calendar_meeting_reminder_reset_for_meeting($pdo, $meeting, new DateTimeImmutable('now'));
    }
    if ((string)($_SESSION['role'] ?? '') === 'admin') {
        google_calendar_meeting_sync_admin_bulletin($pdo, array_merge($payload, ['source_id' => $meetingId]), $currentUserId);
    }

    return $meetingId;
}

function google_calendar_meeting_update_local($pdo, array $existingMeeting, array $payload, array $event = [])
{
    $meetingId = (int)($existingMeeting['id'] ?? 0);
    if ($meetingId <= 0) {
        return false;
    }

    $updated = calendar_meetings_update($pdo, $meetingId, [
        'title' => (string)($payload['title'] ?? ''),
        'description' => (string)($payload['description'] ?? ''),
        'meeting_date' => (string)($payload['meeting_date'] ?? ''),
        'start_time' => (string)($payload['start_time'] ?? ''),
        'end_time' => (string)($payload['end_time'] ?? ''),
        'timezone' => (string)($payload['timezone'] ?? google_calendar_timezone()),
        'audience_type' => (string)($payload['audience_type'] ?? 'everyone'),
        'group_id' => !empty($payload['group_id']) ? (int)$payload['group_id'] : null,
        'task_id' => !empty($payload['task_id']) ? (int)$payload['task_id'] : null,
        'google_calendar_url' => !empty($event['htmlLink']) ? (string)$event['htmlLink'] : (string)($existingMeeting['google_calendar_url'] ?? ''),
        'google_meet_url' => !empty($event['hangoutLink']) ? (string)$event['hangoutLink'] : (string)($existingMeeting['google_meet_url'] ?? ''),
        'google_conference_id' => !empty($event) ? google_calendar_extract_conference_id($event) : (string)($existingMeeting['google_conference_id'] ?? ''),
    ]);

    if (!$updated) {
        return false;
    }

    $meeting = calendar_meetings_get_by_id($pdo, $meetingId);
    if ($meeting) {
        calendar_meeting_reminder_reset_for_meeting($pdo, $meeting, new DateTimeImmutable('now'));
    }
    if ((string)($_SESSION['role'] ?? '') === 'admin') {
        google_calendar_meeting_sync_admin_bulletin($pdo, array_merge($payload, ['source_id' => $meetingId]), (int)($existingMeeting['created_by'] ?? 0));
    }

    return true;
}

function google_calendar_meeting_delete_local($pdo, array $meeting)
{
    $meetingId = (int)($meeting['id'] ?? 0);
    if ($meetingId <= 0) {
        return false;
    }

    // Ensure optional tables exist before opening a transaction.
    // MySQL can auto-commit when CREATE TABLE runs inside a transaction,
    // which would make a later commit() throw even though the delete succeeded.
    bulletin_ensure_table($pdo);
    calendar_meeting_email_reminders_ensure_schema($pdo);

    $startedTransaction = false;
    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        delete_bulletin_posts_by_source($pdo, 'calendar_meeting', $meetingId);
        calendar_meeting_reminder_delete_for_meeting($pdo, $meetingId);
        $deleted = calendar_meetings_delete($pdo, $meetingId);

        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return $deleted;
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function google_calendar_meeting_process_with_access_token($pdo, $currentUserId, array $payload, $accessToken)
{
    $action = google_calendar_meeting_normalize_action($payload['action'] ?? 'create');
    $meetingDate = (string)($payload['meeting_date'] ?? '');

    if ($action === 'create') {
        $eventResult = google_calendar_create_meeting_event($accessToken, $payload);
        if (!$eventResult['ok']) {
            google_calendar_meeting_redirect($meetingDate, (string)($eventResult['error'] ?? 'Unable to create the Google Meet event.'));
        }

        $meetingId = google_calendar_meeting_create_local($pdo, $currentUserId, $payload, (array)($eventResult['event'] ?? []));
        unset($_SESSION['pending_google_calendar_meeting']);

        if ($meetingId <= 0) {
            google_calendar_meeting_redirect($meetingDate, 'Google Meet was created, but TaskFlow could not save the meeting locally.');
        }

        google_calendar_meeting_redirect($meetingDate, 'Meeting created and linked to Google Meet.', false);
    }

    $existingMeeting = calendar_meetings_get_by_id($pdo, (int)($payload['meeting_id'] ?? 0));
    if (!$existingMeeting || !calendar_meetings_user_can_manage($existingMeeting, $currentUserId, (string)($_SESSION['role'] ?? 'employee'))) {
        unset($_SESSION['pending_google_calendar_meeting']);
        google_calendar_meeting_redirect($meetingDate, 'That meeting was not found or you no longer have permission to manage it.');
    }

    if ($action === 'update') {
        $event = [];
        $googleEventId = trim((string)($existingMeeting['google_event_id'] ?? ''));
        if ($googleEventId !== '') {
            $eventResult = google_calendar_update_meeting_event($accessToken, $googleEventId, $payload);
            if (!$eventResult['ok']) {
                google_calendar_meeting_redirect($meetingDate, (string)($eventResult['error'] ?? 'Unable to update the Google Meet event.'));
            }
            $event = (array)($eventResult['event'] ?? []);
        }

        $updated = google_calendar_meeting_update_local($pdo, $existingMeeting, $payload, $event);
        unset($_SESSION['pending_google_calendar_meeting']);

        if (!$updated) {
            google_calendar_meeting_redirect($meetingDate, 'TaskFlow could not save the updated meeting details.');
        }

        google_calendar_meeting_redirect($meetingDate, 'Meeting updated successfully.', false);
    }

    $deleteResult = google_calendar_delete_event($accessToken, (string)($existingMeeting['google_event_id'] ?? ''));
    if (!$deleteResult['ok']) {
        google_calendar_meeting_redirect($meetingDate, (string)($deleteResult['error'] ?? 'Unable to delete the Google Meet event.'));
    }

    $deleted = google_calendar_meeting_delete_local($pdo, $existingMeeting);
    unset($_SESSION['pending_google_calendar_meeting']);

    if (!$deleted) {
        google_calendar_meeting_redirect($meetingDate, 'Google Meet was removed, but TaskFlow could not delete the saved meeting.');
    }

    google_calendar_meeting_redirect($meetingDate, 'Meeting deleted successfully.', false);
}

function google_calendar_meeting_process_with_refresh_token($pdo, $currentUserId, array $payload, $refreshToken)
{
    $refresh = google_workspace_refresh_access_token($refreshToken);
    if (!$refresh['ok']) {
        $error = strtolower((string)($refresh['error'] ?? ''));
        if (strpos($error, 'invalid_grant') !== false) {
            google_workspace_delete_token_record($pdo, $currentUserId);
            google_calendar_meeting_start_oauth($payload, $currentUserId, true);
        }

        google_calendar_meeting_redirect((string)($payload['meeting_date'] ?? ''), (string)($refresh['error'] ?? 'Unable to access Google Calendar right now.'));
    }

    $tokens = (array)($refresh['tokens'] ?? []);
    $accessToken = trim((string)($tokens['access_token'] ?? ''));
    if ($accessToken === '') {
        google_calendar_meeting_redirect((string)($payload['meeting_date'] ?? ''), 'Google did not return an access token.');
    }

    google_calendar_meeting_process_with_access_token($pdo, $currentUserId, $payload, $accessToken);
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: ../login.php?error=" . urlencode('First login'));
    exit();
}

$currentUserId = (int)$_SESSION['id'];

if (isset($_GET['resume']) && $_GET['resume'] === '1') {
    $pending = $_SESSION['pending_google_calendar_meeting'] ?? null;
    if (!google_calendar_meeting_pending_valid($pending, $currentUserId)) {
        unset($_SESSION['pending_google_calendar_meeting']);
        google_calendar_meeting_redirect('', 'Meeting request expired. Please try again.');
    }

    $tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
    $refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        google_calendar_meeting_start_oauth($pending, $currentUserId, true);
    }

    google_calendar_meeting_process_with_refresh_token($pdo, $currentUserId, $pending, $refreshToken);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    google_calendar_meeting_redirect('', 'Invalid meeting request.');
}

$action = google_calendar_meeting_normalize_action($_POST['action'] ?? 'create');
if ($action === '') {
    google_calendar_meeting_redirect((string)($_POST['meeting_date'] ?? ''), 'Invalid meeting action.');
}

$csrfFormKey = $action === 'delete' ? 'calendar_meeting_delete_form' : 'calendar_meeting_form';
if (!csrf_verify($csrfFormKey, $_POST['csrf_token'] ?? null, true)) {
    google_calendar_meeting_redirect((string)($_POST['meeting_date'] ?? ''), 'Invalid or expired request. Please refresh and try again.');
}

if ($action === 'delete') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    [$ok, $message, $existingMeeting] = google_calendar_meeting_validate_existing_meeting($pdo, $meetingId, $currentUserId, (string)$_SESSION['role']);
    if (!$ok) {
        google_calendar_meeting_redirect((string)($_POST['meeting_date'] ?? ''), $message);
    }

    $meetingDate = (string)($existingMeeting['meeting_date'] ?? (string)($_POST['meeting_date'] ?? ''));
    $googleEventId = trim((string)($existingMeeting['google_event_id'] ?? ''));

    if ($googleEventId === '' || !google_calendar_is_enabled()) {
        $deleted = google_calendar_meeting_delete_local($pdo, $existingMeeting);
        if (!$deleted) {
            google_calendar_meeting_redirect($meetingDate, 'TaskFlow could not delete the saved meeting.');
        }

        $successMessage = $googleEventId === ''
            ? 'Meeting deleted successfully.'
            : 'Meeting deleted from TaskFlow. Google Calendar sync is not available right now.';
        google_calendar_meeting_redirect($meetingDate, $successMessage, false);
    }

    $payload = [
        'action' => 'delete',
        'meeting_id' => $meetingId,
        'meeting_date' => $meetingDate,
        'google_event_id' => $googleEventId,
    ];

    $tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
    $refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
    $grantedScopes = trim((string)($tokenRecord['scope'] ?? ''));
    $hasCalendarScope = google_workspace_scope_contains($grantedScopes, google_calendar_required_scope());

    if ($refreshToken === '' || !$hasCalendarScope) {
        google_calendar_meeting_start_oauth($payload, $currentUserId, true);
    }

    google_calendar_meeting_process_with_refresh_token($pdo, $currentUserId, $payload, $refreshToken);
}

[$isValid, $validationMessage, $normalizedPayload] = google_calendar_meeting_validate_payload($_POST);
if (!$isValid) {
    google_calendar_meeting_redirect((string)($_POST['meeting_date'] ?? ''), $validationMessage);
}

$normalizedPayload['action'] = $action;

$existingMeeting = null;
if ($action === 'update') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    [$ok, $message, $existingMeeting] = google_calendar_meeting_validate_existing_meeting($pdo, $meetingId, $currentUserId, (string)$_SESSION['role']);
    if (!$ok) {
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), $message);
    }

    $normalizedPayload['meeting_id'] = $meetingId;
    $normalizedPayload['google_event_id'] = (string)($existingMeeting['google_event_id'] ?? '');
}

if ((string)$_SESSION['role'] === 'admin') {
    if (($normalizedPayload['audience_type'] ?? 'everyone') === 'group') {
        $groupId = (int)($normalizedPayload['group_id'] ?? 0);
        $group = $groupId > 0 ? get_group_by_id($pdo, $groupId) : 0;
        if (!$group || trim((string)($group['type'] ?? 'group')) !== 'group') {
            google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Please choose a valid group for this meeting.');
        }
        $normalizedPayload['task_id'] = null;
    } elseif (($normalizedPayload['audience_type'] ?? 'everyone') === 'task') {
        $taskId = (int)($normalizedPayload['task_id'] ?? 0);
        $task = $taskId > 0 ? get_task_by_id($pdo, $taskId) : 0;
        if (!$task) {
            google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Please choose a valid task for this meeting.');
        }
        $normalizedPayload['group_id'] = null;
    } else {
        $normalizedPayload['group_id'] = null;
        $normalizedPayload['task_id'] = null;
    }
} else {
    $leaderTasks = get_tasks_led_by_user($pdo, $currentUserId);
    $allowedTaskIds = [];
    foreach ($leaderTasks as $taskRow) {
        $allowedTaskIds[(int)($taskRow['id'] ?? 0)] = true;
    }

    if (empty($allowedTaskIds)) {
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Only admins or task leaders can manage meetings.');
    }

    $normalizedPayload['audience_type'] = 'task';
    $normalizedPayload['group_id'] = null;
    $taskId = (int)($normalizedPayload['task_id'] ?? 0);
    if ($taskId <= 0 || !isset($allowedTaskIds[$taskId])) {
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Please choose one of your tasks for this meeting.');
    }
    $normalizedPayload['task_id'] = $taskId;
}

if ($action === 'update' && !empty($normalizedPayload['google_event_id']) && !google_calendar_is_enabled()) {
    google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Google Calendar integration is not configured, so linked meetings cannot be edited right now.');
}

$needsGoogleSync = $action === 'create' || !empty($normalizedPayload['google_event_id']);
if ($needsGoogleSync) {
    $tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
    $refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
    $grantedScopes = trim((string)($tokenRecord['scope'] ?? ''));
    $hasCalendarScope = google_workspace_scope_contains($grantedScopes, google_calendar_required_scope());

    if ($refreshToken === '' || !$hasCalendarScope) {
        google_calendar_meeting_start_oauth($normalizedPayload, $currentUserId, true);
    }

    google_calendar_meeting_process_with_refresh_token($pdo, $currentUserId, $normalizedPayload, $refreshToken);
}

if ($action === 'update') {
    $meeting = $existingMeeting ?: calendar_meetings_get_by_id($pdo, (int)($normalizedPayload['meeting_id'] ?? 0));
    if (!$meeting) {
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Meeting not found.');
    }

    $updated = google_calendar_meeting_update_local($pdo, $meeting, $normalizedPayload);
    if (!$updated) {
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'TaskFlow could not save the updated meeting details.');
    }

    google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Meeting updated successfully.', false);
}

google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Invalid meeting request.');
