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

function google_calendar_meeting_pending_valid($pending, $currentUserId)
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
        && $action === 'create_calendar_meeting';
}

function google_calendar_meeting_store_pending(array $payload, $userId)
{
    try {
        $state = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $state = hash('sha256', uniqid('google_calendar_meeting_', true) . microtime(true));
    }

    $_SESSION['pending_google_calendar_meeting'] = [
        'action' => 'create_calendar_meeting',
        'title' => trim((string)($payload['title'] ?? '')),
        'description' => trim((string)($payload['description'] ?? '')),
        'meeting_date' => trim((string)($payload['meeting_date'] ?? '')),
        'start_time' => trim((string)($payload['start_time'] ?? '')),
        'end_time' => trim((string)($payload['end_time'] ?? '')),
        'timezone' => trim((string)($payload['timezone'] ?? '')) ?: google_calendar_timezone(),
        'audience_type' => trim((string)($payload['audience_type'] ?? 'everyone')),
        'group_id' => !empty($payload['group_id']) ? (int)$payload['group_id'] : null,
        'task_id' => !empty($payload['task_id']) ? (int)$payload['task_id'] : null,
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

function google_calendar_meeting_create_from_refresh_token($pdo, $currentUserId, array $payload, $refreshToken)
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

    $eventResult = google_calendar_create_meeting_event($accessToken, $payload);
    if (!$eventResult['ok']) {
        google_calendar_meeting_redirect((string)($payload['meeting_date'] ?? ''), (string)($eventResult['error'] ?? 'Unable to create the Google Meet event.'));
    }

    $event = (array)($eventResult['event'] ?? []);
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

    unset($_SESSION['pending_google_calendar_meeting']);

    if ($meetingId <= 0) {
        google_calendar_meeting_redirect((string)($payload['meeting_date'] ?? ''), 'Google Meet was created, but TaskFlow could not save the meeting locally.');
    }

    if ((string)($_SESSION['role'] ?? '') === 'admin') {
        $groupName = '';
        $taskName = '';

        if (!empty($payload['group_id'])) {
            $groupRow = get_group_by_id($pdo, (int)$payload['group_id']);
            if ($groupRow) {
                $groupName = trim((string)($groupRow['name'] ?? ''));
            }
        }

        if (!empty($payload['task_id'])) {
            $taskRow = get_task_by_id($pdo, (int)$payload['task_id']);
            if ($taskRow) {
                $taskName = trim((string)($taskRow['title'] ?? ''));
            }
        }

        try {
            create_meeting_bulletin_reminder($pdo, [
                'title' => (string)($payload['title'] ?? ''),
                'meeting_date' => (string)($payload['meeting_date'] ?? ''),
                'start_time' => (string)($payload['start_time'] ?? ''),
                'end_time' => (string)($payload['end_time'] ?? ''),
                'audience_type' => (string)($payload['audience_type'] ?? 'everyone'),
                'group_id' => !empty($payload['group_id']) ? (int)$payload['group_id'] : null,
                'task_id' => !empty($payload['task_id']) ? (int)$payload['task_id'] : null,
                'group_name' => $groupName,
                'task_name' => $taskName,
                'source_id' => $meetingId,
            ], $currentUserId);
        } catch (Throwable $e) {
            // Keep meeting creation successful even if the bulletin reminder fails.
        }
    }

    google_calendar_meeting_redirect((string)($payload['meeting_date'] ?? ''), 'Meeting created and linked to Google Meet.', false);
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

    google_calendar_meeting_create_from_refresh_token($pdo, $currentUserId, $pending, $refreshToken);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    google_calendar_meeting_redirect('', 'Invalid meeting request.');
}

if (!csrf_verify('calendar_meeting_form', $_POST['csrf_token'] ?? null, true)) {
    google_calendar_meeting_redirect((string)($_POST['meeting_date'] ?? ''), 'Invalid or expired request. Please refresh and try again.');
}

[$isValid, $validationMessage, $normalizedPayload] = google_calendar_meeting_validate_payload($_POST);
if (!$isValid) {
    google_calendar_meeting_redirect((string)($_POST['meeting_date'] ?? ''), $validationMessage);
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
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Only admins or task leaders can create meetings.');
    }

    $normalizedPayload['audience_type'] = 'task';
    $normalizedPayload['group_id'] = null;
    $taskId = (int)($normalizedPayload['task_id'] ?? 0);
    if ($taskId <= 0 || !isset($allowedTaskIds[$taskId])) {
        google_calendar_meeting_redirect((string)($normalizedPayload['meeting_date'] ?? ''), 'Please choose one of your tasks for this meeting.');
    }
    $normalizedPayload['task_id'] = $taskId;
}

$tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
$refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
$grantedScopes = trim((string)($tokenRecord['scope'] ?? ''));
$hasCalendarScope = google_workspace_scope_contains($grantedScopes, google_calendar_required_scope());

if ($refreshToken === '' || !$hasCalendarScope) {
    google_calendar_meeting_start_oauth($normalizedPayload, $currentUserId, true);
}

google_calendar_meeting_create_from_refresh_token($pdo, $currentUserId, $normalizedPayload, $refreshToken);
