<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once "invite_helpers.php";
require_once __DIR__ . "/helpers/google_auth.php";
require_once __DIR__ . "/model/user.php";

function google_invite_redirect($token, $message = '', $isError = true)
{
    $params = [];
    $token = trim((string)$token);
    if ($token !== '') {
        $params[] = 'token=' . urlencode($token);
    }

    if (trim((string)$message) !== '') {
        $params[] = ($isError ? 'error=' : 'success=') . urlencode((string)$message);
    }

    $target = "../join-workspace.php";
    if (!empty($params)) {
        $target .= '?' . implode('&', $params);
    }

    header("Location: " . $target);
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    google_invite_redirect('', "Invalid Google invite request.");
}

$token = trim((string)($_POST['token'] ?? ''));
if ($token === '') {
    google_invite_redirect('', "Invitation token is missing.");
}

if (!csrf_verify('google_invite_init_form', $_POST['csrf_token'] ?? null, true)) {
    google_invite_redirect($token, "Invalid or expired request. Please refresh and try again.");
}

if (!tenant_table_exists($pdo, 'workspace_invites')) {
    google_invite_redirect($token, "Invitation system is not available yet.");
}

if (!google_auth_is_enabled()) {
    google_invite_redirect($token, "Google invite acceptance is not configured yet.");
}

$credential = trim((string)($_POST['credential'] ?? ''));
if ($credential === '') {
    google_invite_redirect($token, "Google did not return an invite credential.");
}

$verification = google_auth_verify_id_token($credential, google_login_client_id());
if (!$verification['ok']) {
    google_invite_redirect($token, (string)($verification['error'] ?? 'Google invite could not be verified.'));
}

$claims = (array)($verification['claims'] ?? []);
$googleSub = trim((string)($claims['sub'] ?? ''));
$email = strtolower(trim((string)($claims['email'] ?? '')));
$fullName = trim((string)($claims['name'] ?? ''));
$pictureUrl = trim((string)($claims['picture'] ?? ''));
$emailVerified = $claims['email_verified'] ?? false;
$hostedDomain = trim((string)($claims['hd'] ?? ''));

if ($googleSub === '' || $email === '') {
    google_invite_redirect($token, "Google account information is incomplete.");
}

if (!google_auth_email_is_authoritative($email, $emailVerified, $hostedDomain)) {
    google_invite_redirect($token, "This Google account cannot be used to accept workspace invites automatically.");
}

$existingGoogleUser = user_get_by_google_sub_unscoped($pdo, $googleSub);
if ($existingGoogleUser !== 0) {
    unset($_SESSION['pending_google_invite_accept']);
    google_invite_redirect($token, "This Google account already has a TaskFlow account. Please sign in instead.");
}

$existingEmailUser = user_get_by_email_unscoped($pdo, $email);
if ($existingEmailUser !== 0) {
    unset($_SESSION['pending_google_invite_accept']);
    google_invite_redirect($token, "This email already has a TaskFlow account. Please sign in instead.");
}

$stmt = $pdo->prepare(
    "SELECT wi.id, wi.organization_id, wi.email, wi.full_name, wi.role, wi.status, wi.expires_at,
            o.status AS organization_status
     FROM workspace_invites wi
     JOIN organizations o ON o.id = wi.organization_id
     WHERE wi.token = ?
     LIMIT 1"
);
$stmt->execute([$token]);
$invite = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$invite) {
    google_invite_redirect($token, "Invalid invitation link.");
}

$inviteStatus = strtolower((string)($invite['status'] ?? ''));
if ($inviteStatus !== 'pending') {
    google_invite_redirect($token, "This invitation is no longer active.");
}

$expiresAt = strtotime((string)($invite['expires_at'] ?? ''));
if ($expiresAt !== false && $expiresAt <= time()) {
    google_invite_redirect($token, "This invitation has expired. Ask your admin to send a new one.");
}

$workspaceAccess = tenant_workspace_access_state($pdo, (int)$invite['organization_id'], false);
if (empty($workspaceAccess['can_access_workspace'])) {
    google_invite_redirect($token, (string)($workspaceAccess['message'] ?? "This workspace is currently unavailable."));
}

$capacity = tenant_check_workspace_capacity($pdo, (int)$invite['organization_id']);
if (!$capacity['ok']) {
    google_invite_redirect($token, (string)$capacity['reason']);
}

$isOpenLink = invite_is_open_link_email((string)$invite['email']);
$inviteEmail = strtolower(trim((string)$invite['email']));
if (!$isOpenLink && !hash_equals($inviteEmail, $email)) {
    google_invite_redirect($token, "Please continue with the invited Google email: " . $inviteEmail);
}

$resolvedFullName = $fullName !== '' ? $fullName : trim((string)($invite['full_name'] ?? ''));
if ($resolvedFullName === '') {
    $resolvedFullName = invite_guess_name_from_email($email);
}

$_SESSION['pending_google_invite_accept'] = [
    'token' => $token,
    'google_sub' => $googleSub,
    'email' => $email,
    'full_name' => $resolvedFullName,
    'picture' => $pictureUrl,
    'email_verified' => !empty($emailVerified) ? 1 : 0,
    'created_at' => time(),
];

google_invite_redirect($token, "Google account connected. Finish joining the workspace below.", false);
