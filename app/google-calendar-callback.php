<?php
session_start();

require_once "../DB_connection.php";
require_once "helpers/google_calendar.php";
require_once "helpers/google_auth.php";
require_once "model/user.php";
require_once "model/GoogleWorkspace.php";

function google_calendar_callback_redirect($meetingDate, $message = '', $isError = true)
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

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: ../login.php?error=" . urlencode('First login'));
    exit();
}

$pending = $_SESSION['pending_google_calendar_meeting'] ?? null;
$currentUserId = (int)$_SESSION['id'];
$meetingDate = trim((string)($pending['meeting_date'] ?? ''));

$pendingAction = trim((string)($pending['action'] ?? ''));
if (
    !is_array($pending)
    || (int)($pending['user_id'] ?? 0) !== $currentUserId
    || !in_array($pendingAction, ['create', 'update', 'delete'], true)
) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Meeting request expired. Please try again.');
}

$createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
if ($createdAt <= 0 || (time() - $createdAt) > 1800) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Meeting request expired. Please try again.');
}

if (!google_calendar_is_enabled()) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Google Calendar integration is not configured yet.');
}

$expectedState = trim((string)($pending['state'] ?? ''));
$returnedState = trim((string)($_GET['state'] ?? ''));
if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Google authorization state did not match. Please try again.');
}

if (!empty($_GET['error'])) {
    unset($_SESSION['pending_google_calendar_meeting']);
    $error = trim((string)$_GET['error']);
    $description = trim((string)($_GET['error_description'] ?? ''));
    $message = $description !== '' ? $description : $error;
    google_calendar_callback_redirect($meetingDate, $message !== '' ? $message : 'Google authorization was cancelled.');
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Google did not return an authorization code.');
}

$exchange = google_calendar_exchange_code_for_tokens($code);
if (!$exchange['ok']) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, (string)($exchange['error'] ?? 'Unable to complete Google authorization.'));
}

$tokens = (array)($exchange['tokens'] ?? []);
$accessToken = trim((string)($tokens['access_token'] ?? ''));
if ($accessToken === '') {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Google did not return an access token.');
}

$userinfo = google_workspace_fetch_userinfo($accessToken);
if (!$userinfo['ok']) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, (string)($userinfo['error'] ?? 'Unable to verify the Google account.'));
}

$profile = (array)($userinfo['profile'] ?? []);
$googleSub = trim((string)($profile['sub'] ?? ''));
$googleEmail = strtolower(trim((string)($profile['email'] ?? '')));
$googlePicture = trim((string)($profile['picture'] ?? ''));
$emailVerified = $profile['email_verified'] ?? false;
$hostedDomain = trim((string)($profile['hd'] ?? ''));

if ($googleSub === '' || $googleEmail === '') {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Google account information is incomplete.');
}

$currentUser = get_user_by_id($pdo, $currentUserId);
if (!$currentUser) {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Your TaskFlow account could not be found.');
}

$currentGoogleSub = trim((string)($currentUser['google_sub'] ?? ''));
if ($currentGoogleSub !== '') {
    if (!hash_equals($currentGoogleSub, $googleSub)) {
        unset($_SESSION['pending_google_calendar_meeting']);
        google_calendar_callback_redirect($meetingDate, 'Please authorize the same Google account linked to your TaskFlow login.');
    }
} else {
    $currentEmail = strtolower(trim((string)($currentUser['username'] ?? '')));
    if ($currentEmail === '' || !hash_equals($currentEmail, $googleEmail) || !google_auth_email_is_authoritative($googleEmail, $emailVerified, $hostedDomain)) {
        unset($_SESSION['pending_google_calendar_meeting']);
        google_calendar_callback_redirect($meetingDate, 'The authorized Google account does not match your TaskFlow account.');
    }

    user_link_google_account_unscoped($pdo, $currentUserId, $googleSub, $emailVerified, $googlePicture);
}

$existingTokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
$refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
if ($refreshToken === '') {
    $refreshToken = trim((string)($existingTokenRecord['refresh_token'] ?? ''));
}

if ($refreshToken === '') {
    unset($_SESSION['pending_google_calendar_meeting']);
    google_calendar_callback_redirect($meetingDate, 'Google did not grant offline access. Please try again and allow access.');
}

$mergedScopes = google_workspace_scope_merge(
    (string)($existingTokenRecord['scope'] ?? ''),
    trim((string)($tokens['scope'] ?? ''))
);

google_workspace_save_refresh_token(
    $pdo,
    $currentUserId,
    $googleSub,
    $googleEmail,
    $refreshToken,
    $mergedScopes
);

header("Location: google-calendar-meeting.php?resume=1");
exit();
