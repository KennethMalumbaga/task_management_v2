<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/tenant.php";
require_once __DIR__ . "/helpers/google_auth.php";
require_once __DIR__ . "/model/user.php";

function google_login_redirect($message)
{
    header("Location: ../login.php?error=" . urlencode((string)$message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    google_login_redirect("Invalid Google login request.");
}

if (!google_auth_is_enabled()) {
    google_login_redirect("Google login is not configured yet.");
}

if (!google_auth_verify_gsi_csrf($_POST, $_COOKIE)) {
    google_login_redirect("Google login request could not be verified.");
}

$credential = trim((string)($_POST['credential'] ?? ''));
if ($credential === '') {
    google_login_redirect("Google login did not return a credential.");
}

$verification = google_auth_verify_id_token($credential, google_auth_client_id());
if (!$verification['ok']) {
    google_login_redirect((string)($verification['error'] ?? 'Google login could not be verified.'));
}

$claims = (array)($verification['claims'] ?? []);
$googleSub = trim((string)($claims['sub'] ?? ''));
$email = strtolower(trim((string)($claims['email'] ?? '')));
$emailVerified = $claims['email_verified'] ?? false;
$hostedDomain = trim((string)($claims['hd'] ?? ''));
$pictureUrl = trim((string)($claims['picture'] ?? ''));

if ($googleSub === '' || $email === '') {
    google_login_redirect("Google account information is incomplete.");
}

$user = user_get_by_google_sub_unscoped($pdo, $googleSub);
if ($user === 0) {
    if (!google_auth_email_is_authoritative($email, $emailVerified, $hostedDomain)) {
        google_login_redirect("This Google account cannot be linked automatically. Please log in with your password first.");
    }

    $user = user_get_by_email_unscoped($pdo, $email);
    if ($user === 0) {
        google_login_redirect("No TaskFlow account matches this Google email. Use the invited email address or sign up first.");
    }

    $existingGoogleSub = trim((string)($user['google_sub'] ?? ''));
    if ($existingGoogleSub !== '' && $existingGoogleSub !== $googleSub) {
        google_login_redirect("This account is already linked to a different Google login.");
    }

    if (!user_link_google_account_unscoped($pdo, (int)$user['id'], $googleSub, $emailVerified, $pictureUrl)) {
        google_login_redirect("We could not link this Google account right now. Please try again.");
    }

    $user = user_get_by_google_sub_unscoped($pdo, $googleSub);
    if ($user === 0) {
        google_login_redirect("Google account linking did not complete. Please try again.");
    }
}

$role = (string)($user['role'] ?? '');
$id = (int)($user['id'] ?? 0);
$usernameDb = (string)($user['username'] ?? '');
$isSuperAdmin = ($role === 'admin' && $usernameDb === 'admin');

if ($id <= 0 || ($role !== 'admin' && $role !== 'employee')) {
    google_login_redirect("This account is not allowed to log in with Google.");
}

$orgId = tenant_resolve_user_org($pdo, $id, $user['organization_id'] ?? null);
$orgName = null;
$orgMembershipRole = null;
if (tenant_column_exists($pdo, 'users', 'organization_id') && !$orgId && !$isSuperAdmin) {
    google_login_redirect("Account is not linked to a workspace.");
}

if ($orgId && tenant_table_exists($pdo, 'organizations')) {
    $orgStmt = $pdo->prepare("SELECT name, status FROM organizations WHERE id = ? LIMIT 1");
    $orgStmt->execute([$orgId]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
    if (!$org && !$isSuperAdmin) {
        google_login_redirect("Account is not linked to a valid workspace.");
    }

    $orgStatus = strtolower((string)(is_array($org) ? ($org['status'] ?? 'active') : 'active'));
    if ($orgStatus !== 'active' && !$isSuperAdmin) {
        google_login_redirect("Workspace is currently turned off. Please contact your workspace admin.");
    }

    $orgName = is_array($org) ? ($org['name'] ?? null) : null;
    $orgMembershipRole = tenant_resolve_user_membership_role(
        $pdo,
        $id,
        (int)$orgId,
        $role === 'admin' ? 'admin' : 'member'
    );
}

unset($_SESSION['pending_login_verification']);
unset(
    $_SESSION['role'],
    $_SESSION['id'],
    $_SESSION['username'],
    $_SESSION['full_name'],
    $_SESSION['organization_id'],
    $_SESSION['organization_role'],
    $_SESSION['organization_name'],
    $_SESSION['must_change_password'],
    $_SESSION['toast_success']
);

session_regenerate_id(true);
$_SESSION['role'] = $role;
$_SESSION['id'] = $id;
$_SESSION['username'] = $usernameDb;
$_SESSION['full_name'] = (string)($user['full_name'] ?? '');

if ($orgId) {
    $_SESSION['organization_id'] = (int)$orgId;
    $_SESSION['organization_role'] = $orgMembershipRole ? (string)$orgMembershipRole : ($role === 'admin' ? 'admin' : 'member');
    if ($orgName) {
        $_SESSION['organization_name'] = $orgName;
    }
}

if (!empty($user['must_change_password'])) {
    $_SESSION['must_change_password'] = true;
    $warning = "Action Needed: Please change your password.";
    header("Location: ../edit_profile.php?warning=" . urlencode($warning));
    exit();
}

if ($role === 'admin' && !$isSuperAdmin && $orgId) {
    $billingGate = tenant_workspace_requires_payment($pdo, (int)$orgId);
    if (!empty($billingGate['required'])) {
        header("Location: ../workspace-billing.php?error=" . urlencode((string)$billingGate['reason']));
        exit();
    }
}

$_SESSION['toast_success'] = "Logged in with Google successfully!";
if ($isSuperAdmin) {
    header("Location: ../maintenance_dashboard.php");
    exit();
}

header("Location: ../index.php");
exit();
