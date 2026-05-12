<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/login_verification.php";

function verify_redirect($message, $isError = true)
{
    $key = $isError ? 'verify_error' : 'verify_success';
    header("Location: ../login.php?verify_pending=1&{$key}=" . urlencode((string)$message));
    exit();
}

if (login_verification_is_temporarily_disabled()) {
    unset($_SESSION['pending_login_verification']);
    header("Location: ../login.php?success=" . urlencode("Login verification is temporarily disabled on Railway. Please login again."));
    exit();
}

if (!isset($_SESSION['pending_login_verification']) || !is_array($_SESSION['pending_login_verification'])) {
    header("Location: ../login.php?error=" . urlencode("Verification session expired. Please login again."));
    exit();
}

if (!csrf_verify('verify_login_code_form', $_POST['csrf_token'] ?? null, true)) {
    verify_redirect("Invalid or expired request. Please refresh and try again.");
}

$rawCode = strtoupper(trim((string)($_POST['verification_code'] ?? '')));
$verificationCode = preg_replace('/[^A-Z0-9]/', '', $rawCode);
if (strlen((string)$verificationCode) !== 4) {
    verify_redirect("Enter the 4-digit verification code.");
}

$pending = $_SESSION['pending_login_verification'];
$userId = (int)($pending['user_id'] ?? 0);
if ($userId <= 0) {
    unset($_SESSION['pending_login_verification']);
    header("Location: ../login.php?error=" . urlencode("Verification session expired. Please login again."));
    exit();
}

$result = login_verification_verify_code($pdo, $userId, $verificationCode, 5);
if (!$result['ok']) {
    verify_redirect((string)($result['error'] ?? 'Verification failed. Please try again.'));
}

$role = (string)($pending['role'] ?? '');
if ($role !== 'admin' && $role !== 'employee') {
    unset($_SESSION['pending_login_verification']);
    header("Location: ../login.php?error=" . urlencode("Invalid login state. Please login again."));
    exit();
}

$isSuperAdmin = !empty($pending['is_super_admin']);
$orgId = (int)($pending['organization_id'] ?? 0);
$billingGate = [
    'required' => false,
    'reason' => null,
];
if ($orgId > 0 && !$isSuperAdmin && tenant_table_exists($pdo, 'organizations')) {
    $workspaceAccess = tenant_workspace_access_state($pdo, $orgId, $role === 'admin');
    $org = is_array($workspaceAccess['organization'] ?? null) ? $workspaceAccess['organization'] : null;

    if (!$org) {
        unset($_SESSION['pending_login_verification']);
        header("Location: ../login.php?error=" . urlencode("Account is not linked to a valid workspace."));
        exit();
    }

    if (
        empty($workspaceAccess['can_access_workspace'])
        && empty($workspaceAccess['should_route_to_billing'])
    ) {
        unset($_SESSION['pending_login_verification']);
        header("Location: ../login.php?error=" . urlencode((string)($workspaceAccess['message'] ?? tenant_workspace_inactive_message())));
        exit();
    }

    if (is_array($workspaceAccess['billing_gate'] ?? null)) {
        $billingGate = $workspaceAccess['billing_gate'];
    }

    $orgName = trim((string)($org['name'] ?? ''));
    if ($orgName !== '') {
        $pending['organization_name'] = $orgName;
    }
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
$_SESSION['id'] = $userId;
$_SESSION['username'] = (string)($pending['username'] ?? '');
$_SESSION['full_name'] = (string)($pending['full_name'] ?? '');

if ($orgId > 0) {
    $_SESSION['organization_id'] = $orgId;

    $orgRole = (string)($pending['organization_role'] ?? '');
    if ($orgRole === '') {
        $orgRole = $role === 'admin' ? 'admin' : 'member';
    }
    $_SESSION['organization_role'] = $orgRole;

    $orgName = trim((string)($pending['organization_name'] ?? ''));
    if ($orgName !== '') {
        $_SESSION['organization_name'] = $orgName;
    }
}

$_SESSION['tm_show_login_loader'] = true;

if (!empty($pending['must_change_password'])) {
    $_SESSION['must_change_password'] = true;
    $warning = "Action Needed: Please change your password.";
    header("Location: ../edit_profile.php?warning=" . urlencode($warning));
    exit();
}

if ($role === 'admin' && !$isSuperAdmin && $orgId > 0 && !empty($billingGate['required'])) {
    header("Location: ../workspace-billing.php?error=" . urlencode((string)$billingGate['reason']));
    exit();
}

$_SESSION['toast_success'] = "Logged in successfully!";
if (!empty($pending['is_super_admin'])) {
    header("Location: ../maintenance_dashboard.php");
    exit();
}

header("Location: ../index.php");
exit();
