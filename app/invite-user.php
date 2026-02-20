<?php
session_start();

if (!isset($_SESSION['role']) || !isset($_SESSION['id']) || $_SESSION['role'] !== "admin") {
    header("Location: ../login.php?error=First login");
    exit();
}

include "../DB_connection.php";
include "model/user.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
include "send_email.php";
require_once __DIR__ . "/helpers/input.php";

if (!isset($_POST['email']) || !isset($_POST['full_name'])) {
    header("Location: ../invite-user.php?error=Missing invite data.");
    exit();
}

if (!csrf_verify('invite_user_form', $_POST['csrf_token'] ?? null, true)) {
    header("Location: ../invite-user.php?error=" . urlencode("Invalid or expired request. Please refresh and try again."));
    exit();
}

$is_super_admin = is_super_admin($_SESSION['id'], $pdo);
if ($is_super_admin) {
    header("Location: ../invite-user.php?error=Access denied for super admin.");
    exit();
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    header("Location: ../invite-user.php?error=Workspace context is missing.");
    exit();
}

$capacity = tenant_check_workspace_capacity($pdo, (int)$orgId);
if (!$capacity['ok']) {
    header("Location: ../invite-user.php?error=" . urlencode((string)$capacity['reason']));
    exit();
}

if (!tenant_table_exists($pdo, 'workspace_invites')) {
    header("Location: ../invite-user.php?error=workspace_invites table is missing. Run migration first.");
    exit();
}

$email = strtolower(validate_input($_POST['email']));
$full_name = validate_input($_POST['full_name']);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../invite-user.php?error=Valid employee email is required.");
    exit();
}

if (empty($full_name)) {
    header("Location: ../invite-user.php?error=Employee full name is required.");
    exit();
}

// Enforce one-login-identity rule used by current auth.
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    header("Location: ../invite-user.php?error=This email already has an account. Use password reset instead.");
    exit();
}

// Prevent duplicate pending invites for the same email in the same workspace.
$stmt = $pdo->prepare(
    "SELECT id
     FROM workspace_invites
     WHERE organization_id = ?
       AND email = ?
       AND status = 'pending'
       AND expires_at > NOW()
     LIMIT 1"
);
$stmt->execute([$orgId, $email]);
if ($stmt->fetchColumn()) {
    header("Location: ../invite-user.php?error=There is already an active invite for this email.");
    exit();
}

$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
$role = 'employee';

try {
    $stmt = $pdo->prepare(
        "INSERT INTO workspace_invites
         (organization_id, invited_by, email, full_name, role, token, status, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)"
    );
    $stmt->execute([$orgId, (int)$_SESSION['id'], $email, $full_name, $role, $token, $expires_at]);
} catch (Throwable $e) {
    header("Location: ../invite-user.php?error=Failed to create invite.");
    exit();
}

$orgName = $_SESSION['organization_name'] ?? 'Workspace';
if (tenant_table_exists($pdo, 'organizations')) {
    $orgStmt = $pdo->prepare("SELECT name FROM organizations WHERE id = ? LIMIT 1");
    $orgStmt->execute([$orgId]);
    $orgName = $orgStmt->fetchColumn() ?: $orgName;
}

$inviterName = $_SESSION['full_name'] ?? 'Admin';
$mailSent = send_workspace_invite_email($email, $full_name, $orgName, $token, $inviterName, $role);

if ($mailSent) {
    header("Location: ../invite-user.php?success=" . urlencode("Invite sent to {$email}."));
    exit();
}

$link = APP_URL . "/join-workspace.php?token={$token}";
$warn = "Invite created but email was not sent. Share the invite link manually.";
header("Location: ../invite-user.php?warn=" . urlencode($warn) . "&manual_link=" . urlencode($link));
exit();
