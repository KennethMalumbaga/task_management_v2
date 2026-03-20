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

if (!csrf_verify('workspace_theme_form', $_POST['csrf_token'] ?? null, true)) {
    header("Location: ../workspace-billing.php?error=" . urlencode("Invalid or expired form token."));
    exit;
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    header("Location: ../workspace-billing.php?error=" . urlencode("Workspace context is missing."));
    exit;
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageTheme = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));
if (!$canManageTheme) {
    header("Location: ../workspace-billing.php?error=" . urlencode("You do not have permission to update the workspace theme."));
    exit;
}

if (!workspace_theme_schema_ready($pdo)) {
    header("Location: ../workspace-billing.php?error=" . urlencode("Workspace theme columns are missing. Run sql_add_workspace_theme.sql."));
    exit;
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
    header("Location: ../workspace-billing.php?success=" . urlencode("Workspace theme reset to default."));
    exit;
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
    header("Location: ../workspace-billing.php?error=" . urlencode("Primary color must be a valid hex value."));
    exit;
}
if ($rawSecondary !== '' && $secondary === null) {
    header("Location: ../workspace-billing.php?error=" . urlencode("Secondary color must be a valid hex value."));
    exit;
}
if ($rawAccent !== '' && $accent === null) {
    header("Location: ../workspace-billing.php?error=" . urlencode("Accent color must be a valid hex value."));
    exit;
}

if ($mode === 'dark' && !$modeReady) {
    header("Location: ../workspace-billing.php?error=" . urlencode("Dark mode needs the theme_mode column. Run sql_add_workspace_theme_mode.sql first."));
    exit;
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

header("Location: ../workspace-billing.php?success=" . urlencode("Workspace theme updated."));
exit;
