<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/login_verification.php";

function resend_redirect($message, $isError = false)
{
    $key = $isError ? 'verify_error' : 'verify_success';
    header("Location: ../login.php?verify_pending=1&{$key}=" . urlencode((string)$message));
    exit();
}

if (!isset($_SESSION['pending_login_verification']) || !is_array($_SESSION['pending_login_verification'])) {
    header("Location: ../login.php?error=" . urlencode("Verification session expired. Please login again."));
    exit();
}

if (!csrf_verify('resend_login_code_form', $_POST['csrf_token'] ?? null, true)) {
    resend_redirect("Invalid or expired request. Please refresh and try again.", true);
}

$pending = $_SESSION['pending_login_verification'];
$userId = (int)($pending['user_id'] ?? 0);
$username = trim((string)($pending['username'] ?? ''));
$fullName = trim((string)($pending['full_name'] ?? 'User'));

if ($userId <= 0 || $username === '') {
    unset($_SESSION['pending_login_verification']);
    header("Location: ../login.php?error=" . urlencode("Verification session expired. Please login again."));
    exit();
}

$lastSent = (int)($pending['last_code_sent_at'] ?? 0);
$elapsed = time() - $lastSent;
if ($lastSent > 0 && $elapsed < 30) {
    $wait = 30 - $elapsed;
    resend_redirect("Please wait {$wait} seconds before requesting another code.", true);
}

if (!login_verification_is_required($pdo, $userId)) {
    unset($_SESSION['pending_login_verification']);
    header("Location: ../login.php?success=" . urlencode("Verification already completed. Please login."));
    exit();
}

$codeResult = login_verification_issue_code($pdo, $userId, 10);
if (!$codeResult['ok']) {
    resend_redirect((string)($codeResult['error'] ?? 'Unable to send verification code right now.'), true);
}

include_once "send_email.php";
$mailSent = send_login_verification_code_email($username, $fullName, (string)$codeResult['code']);
if (!$mailSent) {
    resend_redirect("Unable to send verification code right now. Please try again.", true);
}

$_SESSION['pending_login_verification']['last_code_sent_at'] = time();
resend_redirect("A new verification code was sent to your email.");
