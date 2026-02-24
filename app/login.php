<?php
session_start();

require_once "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/input.php";

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
if (tenant_column_exists($pdo, 'users', 'organization_id') && !$orgId && !$isSuperAdmin) {
    $em = "Account is not linked to a workspace.";
    header("Location: ../login.php?error=$em");
    exit();
}
if ($orgId && tenant_table_exists($pdo, 'organizations')) {
    $orgStmt = $pdo->prepare("SELECT name, status FROM organizations WHERE id = ? LIMIT 1");
    $orgStmt->execute([$orgId]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
    if (!$org && !$isSuperAdmin) {
        $em = "Account is not linked to a valid workspace.";
        header("Location: ../login.php?error=$em");
        exit();
    }
    if (!$org && $isSuperAdmin) {
        $org = null;
    }
    $orgStatus = strtolower((string)(is_array($org) ? ($org['status'] ?? 'active') : 'active'));
    if (in_array($orgStatus, ['suspended', 'canceled'], true) && !$isSuperAdmin) {
        $em = "Workspace access is currently disabled. Please contact support.";
        header("Location: ../login.php?error=$em");
        exit();
    }
    $orgName = is_array($org) ? ($org['name'] ?? null) : null;
}

session_regenerate_id(true);
$_SESSION['role'] = $role;
$_SESSION['id'] = $id;
$_SESSION['username'] = $usernameDb;
$_SESSION['full_name'] = $user['full_name'];

if ($orgId) {
    $_SESSION['organization_id'] = (int)$orgId;
    $_SESSION['organization_role'] = tenant_resolve_user_membership_role(
        $pdo,
        $id,
        (int)$orgId,
        $role === 'admin' ? 'admin' : 'member'
    );
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

$_SESSION['toast_success'] = "Logged in successfully!";
if ($isSuperAdmin) {
    header("Location: ../maintenance_dashboard.php");
    exit();
}

header("Location: ../index.php");
exit();
