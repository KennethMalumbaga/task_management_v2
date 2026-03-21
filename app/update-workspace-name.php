<?php
session_start();

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=First login");
    exit;
}

require_once __DIR__ . "/../DB_connection.php";
require_once __DIR__ . "/model/user.php";
require_once __DIR__ . "/helpers/input.php";
require_once __DIR__ . "/../inc/tenant.php";
require_once __DIR__ . "/../inc/csrf.php";

function workspace_name_redirect_target()
{
    $allowed = ['workspace-settings.php'];
    $requested = basename((string)($_POST['redirect_to'] ?? 'workspace-settings.php'));
    return in_array($requested, $allowed, true) ? $requested : 'workspace-settings.php';
}

function workspace_name_redirect($type, $message)
{
    $type = strtolower(trim((string)$type)) === 'success' ? 'success' : 'error';
    $target = workspace_name_redirect_target();
    header("Location: ../" . $target . "?" . $type . "=" . urlencode((string)$message));
    exit;
}

if (!csrf_verify('workspace_name_form', $_POST['csrf_token'] ?? null, true)) {
    workspace_name_redirect('error', "Invalid or expired form token.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    workspace_name_redirect('error', "Workspace context is missing.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageWorkspace = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));
if (!$canManageWorkspace) {
    workspace_name_redirect('error', "You do not have permission to update the workspace name.");
}

if (!tenant_table_exists($pdo, 'organizations')) {
    workspace_name_redirect('error', "Workspace settings are unavailable right now.");
}

$workspaceName = validate_input($_POST['workspace_name'] ?? '');
$workspaceName = preg_replace('/\s+/', ' ', (string)$workspaceName);
$workspaceName = trim((string)$workspaceName);

if ($workspaceName === '') {
    workspace_name_redirect('error', "Workspace name is required.");
}

$nameLength = function_exists('mb_strlen') ? mb_strlen($workspaceName) : strlen($workspaceName);
if ($nameLength > 80) {
    workspace_name_redirect('error', "Workspace name must be 80 characters or fewer.");
}

try {
    $stmt = $pdo->prepare(
        "UPDATE organizations
         SET name = ?
         WHERE id = ?"
    );
    $stmt->execute([$workspaceName, (int)$orgId]);
    $_SESSION['organization_name'] = $workspaceName;
} catch (Throwable $e) {
    workspace_name_redirect('error', "Unable to update the workspace name right now.");
}

workspace_name_redirect('success', "Workspace name updated.");
