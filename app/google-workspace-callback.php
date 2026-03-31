<?php
session_start();

require_once "../DB_connection.php";
require_once "helpers/google_workspace.php";
require_once "helpers/google_auth.php";
require_once "model/user.php";
require_once "model/GoogleWorkspace.php";

function google_workspace_callback_redirect($taskId, $message = '')
{
    $params = [];
    $taskId = (int)$taskId;
    if ($taskId > 0) {
        $params[] = 'open_task=' . urlencode((string)$taskId);
    }
    if (trim((string)$message) !== '') {
        $params[] = 'error=' . urlencode((string)$message);
    }

    $target = "../my_task.php";
    if (!empty($params)) {
        $target .= '?' . implode('&', $params);
    }
    header("Location: " . $target);
    exit();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: ../login.php?error=" . urlencode("First login"));
    exit();
}

$pending = $_SESSION['pending_google_workspace'] ?? null;
$currentUserId = (int)$_SESSION['id'];
$taskId = isset($pending['task_id']) ? (int)$pending['task_id'] : 0;

if (!is_array($pending) || (int)($pending['user_id'] ?? 0) !== $currentUserId || trim((string)($pending['action'] ?? '')) !== 'create_subtask_google_doc') {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google Docs request expired. Please try again.");
}

$createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
if ($createdAt <= 0 || (time() - $createdAt) > 1800) {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google Docs request expired. Please try again.");
}

if (!google_workspace_is_enabled()) {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google Docs integration is not configured yet.");
}

$expectedState = trim((string)($pending['state'] ?? ''));
$returnedState = trim((string)($_GET['state'] ?? ''));
if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google authorization state did not match. Please try again.");
}

if (!empty($_GET['error'])) {
    unset($_SESSION['pending_google_workspace']);
    $error = trim((string)$_GET['error']);
    $description = trim((string)($_GET['error_description'] ?? ''));
    $message = $description !== '' ? $description : $error;
    google_workspace_callback_redirect($taskId, $message !== '' ? $message : "Google authorization was cancelled.");
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google did not return an authorization code.");
}

$exchange = google_workspace_exchange_code_for_tokens($code);
if (!$exchange['ok']) {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, (string)($exchange['error'] ?? 'Unable to complete Google authorization.'));
}

$tokens = (array)($exchange['tokens'] ?? []);
$accessToken = trim((string)($tokens['access_token'] ?? ''));
if ($accessToken === '') {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google did not return an access token.");
}

$userinfo = google_workspace_fetch_userinfo($accessToken);
if (!$userinfo['ok']) {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, (string)($userinfo['error'] ?? 'Unable to verify the Google account.'));
}

$profile = (array)($userinfo['profile'] ?? []);
$googleSub = trim((string)($profile['sub'] ?? ''));
$googleEmail = strtolower(trim((string)($profile['email'] ?? '')));
$googlePicture = trim((string)($profile['picture'] ?? ''));
$emailVerified = $profile['email_verified'] ?? false;
$hostedDomain = trim((string)($profile['hd'] ?? ''));

if ($googleSub === '' || $googleEmail === '') {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google account information is incomplete.");
}

$currentUser = get_user_by_id($pdo, $currentUserId);
if (!$currentUser) {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Your TaskFlow account could not be found.");
}

$currentGoogleSub = trim((string)($currentUser['google_sub'] ?? ''));
if ($currentGoogleSub !== '') {
    if (!hash_equals($currentGoogleSub, $googleSub)) {
        unset($_SESSION['pending_google_workspace']);
        google_workspace_callback_redirect($taskId, "Please authorize the same Google account linked to your TaskFlow login.");
    }
} else {
    $currentEmail = strtolower(trim((string)($currentUser['username'] ?? '')));
    if ($currentEmail === '' || !hash_equals($currentEmail, $googleEmail) || !google_auth_email_is_authoritative($googleEmail, $emailVerified, $hostedDomain)) {
        unset($_SESSION['pending_google_workspace']);
        google_workspace_callback_redirect($taskId, "The authorized Google account does not match your TaskFlow account.");
    }

    user_link_google_account_unscoped($pdo, $currentUserId, $googleSub, $emailVerified, $googlePicture);
}

$refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
if ($refreshToken === '') {
    $existing = google_workspace_get_token_record($pdo, $currentUserId);
    $refreshToken = trim((string)($existing['refresh_token'] ?? ''));
}

if ($refreshToken === '') {
    unset($_SESSION['pending_google_workspace']);
    google_workspace_callback_redirect($taskId, "Google did not grant offline access. Please try again and allow access.");
}

google_workspace_save_refresh_token(
    $pdo,
    $currentUserId,
    $googleSub,
    $googleEmail,
    $refreshToken,
    trim((string)($tokens['scope'] ?? ''))
);

header("Location: google-subtask-doc.php?resume=1");
exit();
