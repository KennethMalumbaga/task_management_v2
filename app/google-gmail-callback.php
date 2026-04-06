<?php
session_start();

require_once "../DB_connection.php";
require_once "helpers/google_gmail.php";
require_once "helpers/google_auth.php";
require_once "model/user.php";
require_once "model/GoogleWorkspace.php";

function google_gmail_callback_redirect($message = '', $isError = false)
{
    $params = ['open_formal_email=1'];
    if (trim((string)$message) !== '') {
        $params[] = ($isError ? 'gmail_error=' : 'gmail_status=') . urlencode((string)$message);
    }

    header("Location: ../messages.php?" . implode('&', $params));
    exit();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: ../login.php?error=" . urlencode("First login"));
    exit();
}

if ((string)$_SESSION['role'] !== 'admin') {
    google_gmail_callback_redirect('Only admins can connect Gmail for formal email.', true);
}

$pending = $_SESSION['pending_google_gmail'] ?? null;
$currentUserId = (int)$_SESSION['id'];

if (!is_array($pending) || (int)($pending['user_id'] ?? 0) !== $currentUserId || trim((string)($pending['action'] ?? '')) !== 'formal_email') {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Gmail authorization request expired. Please try again.', true);
}

$createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
if ($createdAt <= 0 || (time() - $createdAt) > 1800) {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Gmail authorization request expired. Please try again.', true);
}

if (!google_gmail_is_enabled()) {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Google Gmail integration is not configured yet.', true);
}

$expectedState = trim((string)($pending['state'] ?? ''));
$returnedState = trim((string)($_GET['state'] ?? ''));
if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Google authorization state did not match. Please try again.', true);
}

if (!empty($_GET['error'])) {
    unset($_SESSION['pending_google_gmail']);
    $error = trim((string)$_GET['error']);
    $description = trim((string)($_GET['error_description'] ?? ''));
    $message = $description !== '' ? $description : $error;
    google_gmail_callback_redirect($message !== '' ? $message : 'Google authorization was cancelled.', true);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Google did not return an authorization code.', true);
}

$exchange = google_gmail_exchange_code_for_tokens($code);
if (!$exchange['ok']) {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect((string)($exchange['error'] ?? 'Unable to complete Gmail authorization.'), true);
}

$tokens = (array)($exchange['tokens'] ?? []);
$accessToken = trim((string)($tokens['access_token'] ?? ''));
if ($accessToken === '') {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Google did not return an access token.', true);
}

$userinfo = google_workspace_fetch_userinfo($accessToken);
if (!$userinfo['ok']) {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect((string)($userinfo['error'] ?? 'Unable to verify the Google account.'), true);
}

$profile = (array)($userinfo['profile'] ?? []);
$googleSub = trim((string)($profile['sub'] ?? ''));
$googleEmail = strtolower(trim((string)($profile['email'] ?? '')));
$googlePicture = trim((string)($profile['picture'] ?? ''));
$emailVerified = $profile['email_verified'] ?? false;
$hostedDomain = trim((string)($profile['hd'] ?? ''));

if ($googleSub === '' || $googleEmail === '') {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Google account information is incomplete.', true);
}

$currentUser = get_user_by_id($pdo, $currentUserId);
if (!$currentUser) {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Your TaskFlow account could not be found.', true);
}

$currentGoogleSub = trim((string)($currentUser['google_sub'] ?? ''));
if ($currentGoogleSub !== '') {
    if (!hash_equals($currentGoogleSub, $googleSub)) {
        unset($_SESSION['pending_google_gmail']);
        google_gmail_callback_redirect('Please authorize the same Google account linked to your TaskFlow login.', true);
    }
} else {
    $currentEmail = strtolower(trim((string)($currentUser['username'] ?? '')));
    if ($currentEmail === '' || !hash_equals($currentEmail, $googleEmail) || !google_auth_email_is_authoritative($googleEmail, $emailVerified, $hostedDomain)) {
        unset($_SESSION['pending_google_gmail']);
        google_gmail_callback_redirect('The authorized Google account does not match your TaskFlow account.', true);
    }

    user_link_google_account_unscoped($pdo, $currentUserId, $googleSub, $emailVerified, $googlePicture);
}

$existing = google_workspace_get_token_record($pdo, $currentUserId);
$refreshToken = trim((string)($tokens['refresh_token'] ?? ''));
if ($refreshToken === '') {
    $refreshToken = trim((string)($existing['refresh_token'] ?? ''));
}

if ($refreshToken === '') {
    unset($_SESSION['pending_google_gmail']);
    google_gmail_callback_redirect('Google did not grant offline access. Please try again and allow access.', true);
}

$existingScopes = trim((string)($existing['scope'] ?? ''));
$mergedScopes = google_workspace_scope_merge($existingScopes, trim((string)($tokens['scope'] ?? '')));

google_workspace_save_refresh_token(
    $pdo,
    $currentUserId,
    $googleSub,
    $googleEmail,
    $refreshToken,
    $mergedScopes
);

unset($_SESSION['pending_google_gmail']);
google_gmail_callback_redirect('Gmail connected. You can now send formal emails from your admin account.');
