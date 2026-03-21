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
require_once __DIR__ . "/../inc/workspace_theme.php";

function workspace_theme_redirect_target()
{
    $allowed = ['workspace-billing.php', 'workspace-settings.php'];
    $requested = basename((string)($_POST['redirect_to'] ?? 'workspace-billing.php'));
    return in_array($requested, $allowed, true) ? $requested : 'workspace-billing.php';
}

function workspace_theme_redirect($type, $message)
{
    $type = strtolower(trim((string)$type)) === 'success' ? 'success' : 'error';
    $target = workspace_theme_redirect_target();
    header("Location: ../" . $target . "?" . $type . "=" . urlencode((string)$message));
    exit;
}

if (!csrf_verify('workspace_theme_form', $_POST['csrf_token'] ?? null, true)) {
    workspace_theme_redirect('error', "Invalid or expired form token.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    workspace_theme_redirect('error', "Workspace context is missing.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageTheme = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));
if (!$canManageTheme) {
    workspace_theme_redirect('error', "You do not have permission to update the workspace theme.");
}

if (!workspace_theme_schema_ready($pdo)) {
    workspace_theme_redirect('error', "Workspace theme columns are missing. Run sql_add_workspace_theme.sql.");
}

$modeReady = workspace_theme_mode_schema_ready($pdo);
$action = strtolower(trim((string)($_POST['theme_action'] ?? 'save')));
if ($action === 'reset') {
    if ($modeReady) {
        $stmt = $pdo->prepare(
            "UPDATE organizations
             SET theme_primary = NULL, theme_secondary = NULL, theme_accent = NULL, theme_mode = ?
             WHERE id = ?"
        );
        $stmt->execute([workspace_theme_default_mode(), (int)$orgId]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE organizations
             SET theme_primary = NULL, theme_secondary = NULL, theme_accent = NULL
             WHERE id = ?"
        );
        $stmt->execute([(int)$orgId]);
    }
    workspace_theme_redirect('success', "Workspace theme reset to default.");
}

$rawPrimary = trim((string)($_POST['theme_primary'] ?? ''));
$rawSecondary = trim((string)($_POST['theme_secondary'] ?? ''));
$rawAccent = trim((string)($_POST['theme_accent'] ?? ''));
$rawMode = trim((string)($_POST['theme_mode'] ?? workspace_theme_default_mode()));

$primary = workspace_theme_normalize_hex($rawPrimary);
$secondary = workspace_theme_normalize_hex($rawSecondary);
$accent = workspace_theme_normalize_hex($rawAccent);
$mode = workspace_theme_resolve_mode($rawMode);

if ($rawPrimary !== '' && $primary === null) {
    workspace_theme_redirect('error', "Primary color must be a valid hex value.");
}
if ($rawSecondary !== '' && $secondary === null) {
    workspace_theme_redirect('error', "Secondary color must be a valid hex value.");
}
if ($rawAccent !== '' && $accent === null) {
    workspace_theme_redirect('error', "Accent color must be a valid hex value.");
}

if ($mode === 'dark' && !$modeReady) {
    workspace_theme_redirect('error', "Dark mode needs the theme_mode column. Run sql_add_workspace_theme_mode.sql first.");
}

if ($modeReady) {
    $stmt = $pdo->prepare(
        "UPDATE organizations
         SET theme_primary = ?, theme_secondary = ?, theme_accent = ?, theme_mode = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $primary ?: null,
        $secondary ?: null,
        $accent ?: null,
        $mode,
        (int)$orgId
    ]);
} else {
    $stmt = $pdo->prepare(
        "UPDATE organizations
         SET theme_primary = ?, theme_secondary = ?, theme_accent = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $primary ?: null,
        $secondary ?: null,
        $accent ?: null,
        (int)$orgId
    ]);
}

workspace_theme_redirect('success', "Workspace theme updated.");
