<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/input.php";
require_once __DIR__ . "/helpers/login_verification.php";

if (!isset($_POST['user_name']) || !isset($_POST['password'])) {
    $em = "Unknown error occurred";
    header("Location: ../login.php?error=$em");
    exit();
}

if (!csrf_verify('login_form', $_POST['csrf_token'] ?? null, true)) {
    $em = "Invalid or expired request. Please refresh and try again.";
    header("Location: ../login.php?error=" . urlencode($em));
    exit();
}

$user_name = validate_input($_POST['user_name']);
$password = (string)($_POST['password'] ?? '');

if (empty($user_name)) {
    $em = "User name is required";
    header("Location: ../login.php?error=$em");
    exit();
}
if (empty($password)) {
    $em = "Password is required";
    header("Location: ../login.php?error=$em");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$user_name]);

if ($stmt->rowCount() !== 1) {
    $em = "Incorrect username or password ";
    header("Location: ../login.php?error=$em");
    exit();
}

$user = $stmt->fetch(PDO::FETCH_ASSOC);
$usernameDb = $user['username'] ?? '';
$passwordDb = $user['password'] ?? '';
$role = $user['role'] ?? '';
$id = (int)($user['id'] ?? 0);
$isSuperAdmin = ($role === 'admin' && $usernameDb === 'admin');

if ($user_name !== $usernameDb || !password_verify($password, $passwordDb)) {
    $em = "Incorrect username or password ";
    header("Location: ../login.php?error=$em");
    exit();
}

if ($role !== 'admin' && $role !== 'employee') {
    $em = "Unknown error occurred ";
    header("Location: ../login.php?error=$em");
    exit();
}

$orgId = tenant_resolve_user_org($pdo, $id, $user['organization_id'] ?? null);
$orgName = null;
$orgMembershipRole = null;
$billingGate = [
    'required' => false,
    'reason' => null,
];
if (tenant_column_exists($pdo, 'users', 'organization_id') && !$orgId && !$isSuperAdmin) {
    $em = "Account is not linked to a workspace.";
    header("Location: ../login.php?error=$em");
    exit();
}
if ($orgId && tenant_table_exists($pdo, 'organizations')) {
    $workspaceAccess = tenant_workspace_access_state($pdo, (int)$orgId, $role === 'admin' && !$isSuperAdmin);
    $org = is_array($workspaceAccess['organization'] ?? null) ? $workspaceAccess['organization'] : null;
    if (!$org && !$isSuperAdmin) {
        $em = "Account is not linked to a valid workspace.";
        header("Location: ../login.php?error=$em");
        exit();
    }
    if (!$org && $isSuperAdmin) {
        $org = null;
    }
    if (
        !$isSuperAdmin
        && empty($workspaceAccess['can_access_workspace'])
        && empty($workspaceAccess['should_route_to_billing'])
    ) {
        $em = (string)($workspaceAccess['message'] ?? tenant_workspace_inactive_message());
        header("Location: ../login.php?error=$em");
        exit();
    }
    $orgName = is_array($org) ? ($org['name'] ?? null) : null;
    if (is_array($workspaceAccess['billing_gate'] ?? null)) {
        $billingGate = $workspaceAccess['billing_gate'];
    }
    $orgMembershipRole = tenant_resolve_user_membership_role(
        $pdo,
        $id,
        (int)$orgId,
        $role === 'admin' ? 'admin' : 'member'
    );
}

if (login_verification_is_required($pdo, $id)) {
    $codeResult = login_verification_issue_code($pdo, $id, 10);
    if (!$codeResult['ok']) {
        $em = (string)($codeResult['error'] ?? 'Unable to send verification code right now.');
        header("Location: ../login.php?error=" . urlencode($em));
        exit();
    }

    include_once "send_email.php";
    $mailSent = send_login_verification_code_email($usernameDb, (string)($user['full_name'] ?? 'User'), (string)$codeResult['code']);
    if (!$mailSent) {
        $em = "Unable to send verification code right now. Please try again.";
        header("Location: ../login.php?error=" . urlencode($em));
        exit();
    }

    // Prevent stale authenticated state while waiting for verification.
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

    $_SESSION['pending_login_verification'] = [
        'user_id' => $id,
        'role' => $role,
        'username' => $usernameDb,
        'full_name' => (string)($user['full_name'] ?? ''),
        'organization_id' => $orgId ? (int)$orgId : 0,
        'organization_name' => $orgName ? (string)$orgName : '',
        'organization_role' => $orgMembershipRole ? (string)$orgMembershipRole : '',
        'is_super_admin' => $isSuperAdmin ? 1 : 0,
        'must_change_password' => !empty($user['must_change_password']) ? 1 : 0,
        'email_masked' => login_verification_mask_email($usernameDb),
        'last_code_sent_at' => time(),
    ];

    $sm = "Verification code sent to your email.";
    header("Location: ../login.php?verify_pending=1&verify_success=" . urlencode($sm));
    exit();
}

unset($_SESSION['pending_login_verification']);

session_regenerate_id(true);
$_SESSION['role'] = $role;
$_SESSION['id'] = $id;
$_SESSION['username'] = $usernameDb;
$_SESSION['full_name'] = $user['full_name'];

if ($orgId) {
    $_SESSION['organization_id'] = (int)$orgId;
    $_SESSION['organization_role'] = $orgMembershipRole ? (string)$orgMembershipRole : ($role === 'admin' ? 'admin' : 'member');
    if ($orgName) {
        $_SESSION['organization_name'] = $orgName;
    }
}

if (isset($user['must_change_password']) && $user['must_change_password']) {
    $_SESSION['must_change_password'] = true;
    $warning = "Action Needed: Please change your password.";
    header("Location: ../edit_profile.php?warning=" . urlencode($warning));
    exit();
}

if ($role === 'admin' && !$isSuperAdmin && $orgId) {
    if (!empty($billingGate['required'])) {
        header("Location: ../workspace-billing.php?error=" . urlencode((string)$billingGate['reason']));
        exit();
    }
}

$_SESSION['toast_success'] = "Logged in successfully!";
if ($isSuperAdmin) {
    header("Location: ../maintenance_dashboard.php");
    exit();
}

header("Location: ../index.php");
exit();
