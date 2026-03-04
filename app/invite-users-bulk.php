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
require_once "send_email.php";
require_once "invite_bulk_parser.php";
require_once __DIR__ . "/helpers/invite_validation.php";

if (!csrf_verify('bulk_invite_form', $_POST['csrf_token'] ?? null, true)) {
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

if (!isset($_FILES['employees_file']) || !is_array($_FILES['employees_file'])) {
    header("Location: ../invite-user.php?error=Employees file is required.");
    exit();
}

$upload = $_FILES['employees_file'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header("Location: ../invite-user.php?error=Failed to upload employees file.");
    exit();
}

$tmpPath = (string)($upload['tmp_name'] ?? '');
$originalName = (string)($upload['name'] ?? '');
$size = (int)($upload['size'] ?? 0);

if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    header("Location: ../invite-user.php?error=Invalid uploaded file.");
    exit();
}

if (!function_exists('invite_bulk_validate_upload_type')) {
    header("Location: ../invite-user.php?error=Bulk invite validator is missing.");
    exit();
}

$typeValidation = invite_bulk_validate_upload_type($originalName, $tmpPath);
if (empty($typeValidation['ok'])) {
    header("Location: ../invite-user.php?error=" . urlencode((string)($typeValidation['error'] ?? 'Unsupported file type.')));
    exit();
}

if ($size <= 0 || $size > (5 * 1024 * 1024)) {
    header("Location: ../invite-user.php?error=File size must be between 1 byte and 5MB.");
    exit();
}

try {
    $rows = bulk_invite_parse_upload($tmpPath, $originalName);
} catch (Throwable $e) {
    header("Location: ../invite-user.php?error=" . urlencode($e->getMessage()));
    exit();
}

$orgName = $_SESSION['organization_name'] ?? 'Workspace';
if (tenant_table_exists($pdo, 'organizations')) {
    $orgStmt = $pdo->prepare("SELECT name FROM organizations WHERE id = ? LIMIT 1");
    $orgStmt->execute([$orgId]);
    $orgName = $orgStmt->fetchColumn() ?: $orgName;
}
$inviterName = $_SESSION['full_name'] ?? 'Admin';

$checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$checkPendingStmt = $pdo->prepare(
    "SELECT id
     FROM workspace_invites
     WHERE organization_id = ?
       AND email = ?
       AND status = 'pending'
       AND expires_at > NOW()
     LIMIT 1"
);
$insertInviteStmt = $pdo->prepare(
    "INSERT INTO workspace_invites
     (organization_id, invited_by, email, full_name, role, token, status, expires_at)
     VALUES (?, ?, ?, ?, 'employee', ?, 'pending', ?)"
);

$created = 0;
$sent = 0;
$skippedExistingUser = 0;
$skippedPendingInvite = 0;
$invalidRows = 0;
$mailFailed = 0;

foreach ($rows as $row) {
    $email = invite_normalize_email((string)($row['email'] ?? ''));
    $fullName = invite_normalize_full_name((string)($row['full_name'] ?? ''));

    if (!invite_is_valid_email($email) || !invite_is_valid_full_name($fullName)) {
        $invalidRows++;
        continue;
    }

    $checkUserStmt->execute([$email]);
    if ($checkUserStmt->fetchColumn()) {
        $skippedExistingUser++;
        continue;
    }

    $checkPendingStmt->execute([$orgId, $email]);
    if ($checkPendingStmt->fetchColumn()) {
        $skippedPendingInvite++;
        continue;
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $token = hash('sha256', $email . microtime(true) . mt_rand());
    }
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    try {
        $insertInviteStmt->execute([$orgId, (int)$_SESSION['id'], $email, $fullName, $token, $expiresAt]);
        $created++;
    } catch (Throwable $e) {
        $invalidRows++;
        continue;
    }

    $mailSent = send_workspace_invite_email($email, $fullName, $orgName, $token, $inviterName, 'employee');
    if ($mailSent) {
        $sent++;
    } else {
        $mailFailed++;
    }
}

$parts = [];
$parts[] = "Bulk invite done.";
$parts[] = "Created: {$created}.";
$parts[] = "Emails sent: {$sent}.";
if ($skippedExistingUser > 0) {
    $parts[] = "Skipped existing users: {$skippedExistingUser}.";
}
if ($skippedPendingInvite > 0) {
    $parts[] = "Skipped active invites: {$skippedPendingInvite}.";
}
if ($invalidRows > 0) {
    $parts[] = "Skipped invalid rows: {$invalidRows}.";
}

$query = "success=" . urlencode(implode(' ', $parts));
if ($mailFailed > 0) {
    $query .= "&warn=" . urlencode("{$mailFailed} invites were created but email delivery failed. You can copy links from Recent Invites.");
}

header("Location: ../invite-user.php?{$query}");
exit();
