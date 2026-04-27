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
require_once __DIR__ . "/../inc/workspace_screenshot_interval.php";

function workspace_screenshot_interval_redirect_target()
{
    $allowed = ['workspace-settings.php'];
    $requested = basename((string)($_POST['redirect_to'] ?? 'workspace-settings.php'));
    return in_array($requested, $allowed, true) ? $requested : 'workspace-settings.php';
}

function workspace_screenshot_interval_redirect($type, $message)
{
    $type = strtolower(trim((string)$type)) === 'success' ? 'success' : 'error';
    $target = workspace_screenshot_interval_redirect_target();
    header("Location: ../" . $target . "?" . $type . "=" . urlencode((string)$message));
    exit;
}

if (!csrf_verify('workspace_screenshot_interval_form', $_POST['csrf_token'] ?? null, true)) {
    workspace_screenshot_interval_redirect('error', "Invalid or expired form token.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    workspace_screenshot_interval_redirect('error', "Workspace context is missing.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageWorkspace = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));
if (!$canManageWorkspace) {
    workspace_screenshot_interval_redirect('error', "You do not have permission to update screen capture timing.");
}

if (!workspace_screenshot_interval_schema_ready($pdo)) {
    workspace_screenshot_interval_redirect('error', "Screen capture timing settings are unavailable. Run sql_add_workspace_screenshot_interval.sql.");
}

$minMinutes = workspace_screenshot_interval_normalize_minutes($_POST['screenshot_interval_min_minutes'] ?? null);
$maxMinutes = workspace_screenshot_interval_normalize_minutes($_POST['screenshot_interval_max_minutes'] ?? null);
if ($minMinutes === null || $maxMinutes === null) {
    workspace_screenshot_interval_redirect(
        'error',
        "Screen capture timing must stay between "
        . workspace_screenshot_interval_min_allowed_minutes()
        . " and "
        . workspace_screenshot_interval_max_allowed_minutes()
        . " minutes."
    );
}

if ($minMinutes > $maxMinutes) {
    workspace_screenshot_interval_redirect('error', "Minimum capture interval cannot be greater than the maximum.");
}

try {
    $stmt = $pdo->prepare(
        "UPDATE organizations
         SET screenshot_interval_min_minutes = ?, screenshot_interval_max_minutes = ?
         WHERE id = ?"
    );
    $stmt->execute([(int)$minMinutes, (int)$maxMinutes, (int)$orgId]);
} catch (Throwable $e) {
    workspace_screenshot_interval_redirect('error', "Unable to update screen capture timing right now.");
}

workspace_screenshot_interval_redirect('success', "Screen capture timing updated.");
