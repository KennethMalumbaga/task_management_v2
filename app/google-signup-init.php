<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/google_auth.php";
require_once __DIR__ . "/model/user.php";

function google_signup_redirect($message = '', $planCode = 'starter', $signupMode = '', $isError = true)
{
    $params = [];
    if (trim((string)$message) !== '') {
        $params[] = ($isError ? 'error=' : 'success=') . urlencode((string)$message);
    }

    $planCode = strtolower(trim((string)$planCode));
    if ($planCode !== '') {
        $params[] = 'plan=' . urlencode($planCode);
    }

    $signupMode = strtolower(trim((string)$signupMode));
    if ($signupMode !== '') {
        $params[] = 'signup_mode=' . urlencode($signupMode);
    }

    $target = "../signup.php";
    if (!empty($params)) {
        $target .= '?' . implode('&', $params);
    }

    header("Location: " . $target);
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    google_signup_redirect("Invalid Google signup request.");
}

$planCode = (string)($_POST['plan_code'] ?? 'starter');
$signupMode = (string)($_POST['signup_mode'] ?? '');

if (!csrf_verify('google_signup_init_form', $_POST['csrf_token'] ?? null, true)) {
    google_signup_redirect("Invalid or expired request. Please refresh and try again.", $planCode, $signupMode);
}

if (!google_auth_is_enabled()) {
    google_signup_redirect("Google signup is not configured yet.", $planCode, $signupMode);
}

$credential = trim((string)($_POST['credential'] ?? ''));
if ($credential === '') {
    google_signup_redirect("Google did not return a signup credential.", $planCode, $signupMode);
}

$verification = google_auth_verify_id_token($credential, google_login_client_id());
if (!$verification['ok']) {
    google_signup_redirect((string)($verification['error'] ?? 'Google signup could not be verified.'), $planCode, $signupMode);
}

$claims = (array)($verification['claims'] ?? []);
$googleSub = trim((string)($claims['sub'] ?? ''));
$email = strtolower(trim((string)($claims['email'] ?? '')));
$fullName = trim((string)($claims['name'] ?? ''));
$pictureUrl = trim((string)($claims['picture'] ?? ''));
$emailVerified = $claims['email_verified'] ?? false;
$hostedDomain = trim((string)($claims['hd'] ?? ''));

if ($googleSub === '' || $email === '' || $fullName === '') {
    google_signup_redirect("Google account information is incomplete.", $planCode, $signupMode);
}

if (!google_auth_email_is_authoritative($email, $emailVerified, $hostedDomain)) {
    google_signup_redirect("This Google account cannot be used for automatic workspace signup.", $planCode, $signupMode);
}

$existingGoogleUser = user_get_by_google_sub_unscoped($pdo, $googleSub);
if ($existingGoogleUser !== 0) {
    unset($_SESSION['pending_google_signup']);
    header("Location: ../login.php?success=" . urlencode("This Google account already has a TaskFlow account. Please sign in instead."));
    exit();
}

$existingEmailUser = user_get_by_email_unscoped($pdo, $email);
if ($existingEmailUser !== 0) {
    unset($_SESSION['pending_google_signup']);
    header("Location: ../login.php?success=" . urlencode("This email already has a TaskFlow account. Please sign in with Google from the login page."));
    exit();
}

$_SESSION['pending_google_signup'] = [
    'google_sub' => $googleSub,
    'email' => $email,
    'full_name' => $fullName,
    'picture' => $pictureUrl,
    'email_verified' => !empty($emailVerified) ? 1 : 0,
    'created_at' => time(),
];

google_signup_redirect("Google account connected. Finish your workspace details below.", $planCode, $signupMode, false);
