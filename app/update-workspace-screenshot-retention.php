<?php
session_start();

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=First login");
    exit;
}

require_once __DIR__ . "/../DB_connection.php";
require_once __DIR__ . "/model/user.php";
require_once __DIR__ . "/../inc/tenant.php";
require_once __DIR__ . "/../inc/csrf.php";
require_once __DIR__ . "/../inc/workspace_screenshot_retention.php";

function workspace_screenshot_retention_redirect_target()
{
    $allowed = ['workspace-settings.php'];
    $requested = basename((string)($_POST['redirect_to'] ?? 'workspace-settings.php'));
    return in_array($requested, $allowed, true) ? $requested : 'workspace-settings.php';
}

function workspace_screenshot_retention_redirect($type, $message)
{
    $type = strtolower(trim((string)$type)) === 'success' ? 'success' : 'error';
    $target = workspace_screenshot_retention_redirect_target();
    header("Location: ../" . $target . "?" . $type . "=" . urlencode((string)$message));
    exit;
}

if (!csrf_verify('workspace_screenshot_retention_form', $_POST['csrf_token'] ?? null, true)) {
    workspace_screenshot_retention_redirect('error', "Invalid or expired form token.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    workspace_screenshot_retention_redirect('error', "Workspace context is missing.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageWorkspace = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));
if (!$canManageWorkspace) {
    workspace_screenshot_retention_redirect('error', "You do not have permission to update screen capture retention.");
}

if (!workspace_screenshot_retention_schema_ready($pdo)) {
    workspace_screenshot_retention_redirect('error', "Screen capture retention settings are unavailable. Run sql_add_workspace_screenshot_retention.sql.");
}

$retentionDays = workspace_screenshot_retention_normalize_days($_POST['screenshot_retention_days'] ?? null);
if ($retentionDays === null) {
    workspace_screenshot_retention_redirect(
        'error',
        "Screen capture retention must be between " . workspace_screenshot_retention_min_days() . " and " . workspace_screenshot_retention_max_days() . " days."
    );
}

try {
    $stmt = $pdo->prepare(
        "UPDATE organizations
         SET screenshot_retention_days = ?
         WHERE id = ?"
    );
    $stmt->execute([(int)$retentionDays, (int)$orgId]);
} catch (Throwable $e) {
    workspace_screenshot_retention_redirect('error', "Unable to update screen capture retention right now.");
}

workspace_screenshot_retention_redirect('success', "Screen capture retention updated.");
