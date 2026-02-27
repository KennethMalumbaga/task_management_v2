<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

$tenantEnabled = maintenance_is_tenant_enabled($pdo);
$globalOverrideAllowed = maintenance_is_global_override_allowed();

$tenantScripts = [
    [
        'path' => 'reset_database.php',
        'label' => 'Reset Workspace Data',
        'description' => 'Deletes tenant activity records only.',
        'destructive' => true,
    ],
    [
        'path' => 'run_cleanup_orphan_task_chats.php',
        'label' => 'Cleanup Orphan Task Chats',
        'description' => 'Removes orphan task chat groups for this tenant.',
        'destructive' => true,
    ],
    [
        'path' => 'run_cleanup_legacy_duplicate_group_chats.php',
        'label' => 'Cleanup Duplicate Group Chats',
        'description' => 'Removes duplicate legacy group chat rows for this tenant.',
        'destructive' => true,
    ],
    [
        'path' => 'debug_task_chats.php',
        'label' => 'Debug Task Chats',
        'description' => 'Inspects task_chat groups for this tenant.',
        'destructive' => false,
    ],
    [
        'path' => 'debug_groups_type_counts.php',
        'label' => 'Debug Group Type Counts',
        'description' => 'Shows group type counts for this tenant.',
        'destructive' => false,
    ],
    [
        'path' => 'debug_task_title_count.php',
        'label' => 'Debug Task Title Count',
        'description' => 'Shows matching tasks by title for this tenant.',
        'destructive' => false,
    ],
];

$globalScripts = [
    [
        'path' => 'run_migration_workspace_invites.php',
        'label' => 'Run Invite Migration',
        'description' => 'Creates workspace_invites table and indexes.',
    ],
    [
        'path' => 'debug_schema.php',
        'label' => 'Debug Schema',
        'description' => 'Inspects subtasks table columns.',
    ],
    [
        'path' => 'debug_group_type_constraint.php',
        'label' => 'Debug Group Type Constraint',
        'description' => 'Inspects DB CHECK constraints on groups.',
    ],
];

$orgRows = [];
$queryError = null;

try {
    if ($tenantEnabled && tenant_table_exists($pdo, 'organizations')) {
        $hasMembers = tenant_table_exists($pdo, 'organization_members');
        $hasSubscriptions = tenant_table_exists($pdo, 'subscriptions');

        $sql = "SELECT o.id, o.name, o.slug, o.status, o.plan_code";
        if ($hasSubscriptions) {
            $sql .= ", s.status AS subscription_status, s.seat_limit";
        } else {
            $sql .= ", NULL AS subscription_status, NULL AS seat_limit";
        }
        if ($hasMembers) {
            $sql .= ", COUNT(DISTINCT om.user_id) AS member_count";
        } else {
            $sql .= ", 0 AS member_count";
        }

        $sql .= " FROM organizations o";
        if ($hasSubscriptions) {
            $sql .= " LEFT JOIN subscriptions s ON s.organization_id = o.id";
        }
        if ($hasMembers) {
            $sql .= " LEFT JOIN organization_members om ON om.organization_id = o.id";
        }

        $sql .= " GROUP BY o.id, o.name, o.slug, o.status, o.plan_code";
        if ($hasSubscriptions) {
            $sql .= ", s.status, s.seat_limit";
        }
        $sql .= " ORDER BY o.id ASC";

        $stmt = $pdo->query($sql);
        $orgRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $queryError = $e->getMessage();
}

function maintenance_build_link(string $path, ?int $orgId = null, bool $global = false): string
{
    if ($global) {
        return $path . '?global=1';
    }
    if ($orgId !== null && $orgId > 0) {
        return $path . '?org_id=' . (int)$orgId;
    }
    return $path;
}

$totalWorkspaces = count($orgRows);
$activeWorkspaces = 0;
$totalMembers = 0;
foreach ($orgRows as $row) {
    if (strtolower((string)($row['status'] ?? '')) === 'active') {
        $activeWorkspaces++;
    }
    $totalMembers += (int)($row['member_count'] ?? 0);
}

$showRestrictedModal = isset($_GET['restricted']) && $_GET['restricted'] === '1';
$restrictedPageRaw = isset($_GET['page']) ? (string)$_GET['page'] : '';
$restrictedPage = $restrictedPageRaw !== '' ? basename($restrictedPageRaw) : 'workspace page';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --bg: #f4f6fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e5e7ef;
            --brand: #6c3ce1;
            --brand-2: #8b5cf6;
            --brand-grad: linear-gradient(135deg, #6c3ce1 0%, #8b5cf6 100%);
            --success-bg: #dcfce7;
            --success-text: #166534;
            --warn-bg: #fee2e2;
            --warn-text: #991b1b;
            --soft: #eef2ff;
            --soft-border: #c7d2fe;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'Inter', Arial, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .maintenance-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; align-items: center; justify-content: center; z-index: 1200; }
        .maintenance-modal-box { background: #fff; width: min(92vw, 380px); border-radius: 14px; padding: 24px; text-align: center; box-shadow: 0 12px 30px rgba(0,0,0,.18); }
        .maintenance-modal-actions { display: flex; gap: 10px; justify-content: center; margin-top: 16px; }
        .maintenance-modal-btn { border: none; border-radius: 9px; padding: 10px 18px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .maintenance-modal-btn.cancel { background: #f3f4f6; color: #374151; }
        .maintenance-modal-btn.confirm { background: #ef4444; color: #fff; }
        .md-navbar { display: flex; align-items: center; justify-content: space-between; padding: 0 28px; height: 60px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 6px rgba(15,23,42,.06); }
        .md-navbar-brand { display: flex; align-items: center; gap: 12px; }
        .md-navbar-logo { width: 38px; height: 38px; border-radius: 10px; background: var(--brand-grad); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; }
        .md-navbar-title { font-size: 17px; font-weight: 700; color: var(--text); line-height: 1.2; }
        .md-navbar-sub { font-size: 12px; color: var(--muted); font-weight: 400; }
        .md-exit-btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 18px; border-radius: 10px; border: none; background: #ef4444; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .2s, transform .2s; text-decoration: none; }
        .md-exit-btn:hover { background: #dc2626; transform: translateY(-1px); }
        .md-container { max-width: 1280px; margin: 0 auto; padding: 28px 24px; }
        .md-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 32px; }
        .md-stat-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 20px 22px; display: flex; align-items: flex-start; justify-content: space-between; box-shadow: 0 2px 12px rgba(15,23,42,.04); transition: box-shadow .2s, transform .2s; }
        .md-stat-card:hover { box-shadow: 0 6px 20px rgba(108,60,225,.1); transform: translateY(-2px); }
        .md-stat-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--brand-grad); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; flex-shrink: 0; }
        .md-stat-icon.green { background: linear-gradient(135deg,#22c55e,#16a34a); }
        .md-stat-icon.blue  { background: linear-gradient(135deg,#0ea5e9,#3b82f6); }
        .md-stat-label { font-size: 13px; color: var(--muted); margin-bottom: 4px; }
        .md-stat-value { font-size: 34px; font-weight: 800; color: var(--text); line-height: 1.1; }
        .md-stat-trend { color: #a78bfa; font-size: 18px; }
        .md-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .md-section-title { font-size: 22px; font-weight: 700; color: var(--text); margin: 0 0 2px; }
        .md-section-sub { font-size: 13px; color: var(--brand); font-weight: 500; }
        .md-search { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 0 14px; height: 38px; color: var(--muted); font-size: 13px; min-width: 220px; }
        .md-search input { border: none; outline: none; font-size: 13px; color: var(--text); background: transparent; width: 100%; }
        .md-search input::placeholder { color: var(--muted); }
        .md-workspaces-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
        .md-ws-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 18px 18px 14px; box-shadow: 0 2px 10px rgba(15,23,42,.04); transition: box-shadow .2s, transform .2s; }
        .md-ws-card:hover { box-shadow: 0 6px 22px rgba(108,60,225,.1); transform: translateY(-2px); }
        .md-ws-card.is-off { background: #f1f5f9; border-color: #cbd5e1; box-shadow: none; }
        .md-ws-card.is-off:hover { box-shadow: 0 4px 14px rgba(100,116,139,.15); transform: translateY(-1px); }
        .md-ws-card.is-off .md-ws-name,
        .md-ws-card.is-off .md-ws-slug,
        .md-ws-card.is-off .md-ws-meta-value,
        .md-ws-card.is-off .md-ws-sub-row { color: #475569; }
        .md-ws-card.is-off .md-ws-meta { background: #e2e8f0; }
        .md-ws-card.is-off .md-ws-meta-item:first-child { border-right-color: #cbd5e1; }
        .md-ws-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 4px; }
        .md-ws-name { font-size: 15px; font-weight: 700; color: var(--text); }
        .md-ws-more-wrap { position: relative; }
        .md-ws-more { border: none; background: transparent; color: var(--muted); cursor: pointer; font-size: 16px; width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .md-ws-more:hover { background: #f1f5f9; color: #334155; }
        .md-ws-menu { position: absolute; right: 0; top: 32px; min-width: 170px; background: #fff; border: 1px solid var(--border); border-radius: 10px; box-shadow: 0 14px 26px rgba(15,23,42,.12); padding: 6px; display: none; z-index: 20; }
        .md-ws-menu.open { display: block; }
        .md-ws-menu-item { width: 100%; border: none; background: transparent; text-align: left; padding: 8px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #334155; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .md-ws-menu-item:hover { background: #f8fafc; }
        .md-ws-menu-item.danger { color: #dc2626; }
        .md-ws-menu-item.danger:hover { background: #fee2e2; }
        .md-ws-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; }
        .md-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .md-badge.active   { background:#dcfce7; color:#166534; }
        .md-badge.trial    { background:#ede9fe; color:#5b21b6; }
        .md-badge.legacy   { background:#fef3c7; color:#92400e; }
        .md-badge.inactive { background:#e2e8f0; color:#475569; }
        .md-badge.suspended{ background:#fee2e2; color:#991b1b; }
        .md-ws-slug { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
        .md-ws-meta { display: grid; grid-template-columns: 1fr 1fr; background: #f8f7ff; border-radius: 10px; overflow: hidden; margin-bottom: 12px; }
        .md-ws-meta-item { padding: 10px 12px; display: flex; align-items: center; gap: 8px; }
        .md-ws-meta-item:first-child { border-right: 1px solid #ede9fe; }
        .md-ws-meta-icon { color: var(--brand-2); font-size: 13px; }
        .md-ws-meta-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .md-ws-meta-value { font-size: 16px; font-weight: 700; color: var(--text); }
        .md-ws-sub-row { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 5px; margin-bottom: 10px; }
        .md-ws-sub-row i { color: var(--brand-2); }
        .md-ws-actions { display: flex; gap: 8px; justify-content: center; border-top: 1px solid var(--border); padding-top: 10px; }
        .md-ws-action-btn { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; text-decoration: none; color: var(--brand); padding: 4px 10px; border-radius: 8px; transition: background .15s, transform .15s; }
        .md-ws-action-btn:hover { background: var(--soft); transform: translateY(-1px); }
        .md-ws-action-btn.destructive { color: #ef4444; }
        .md-ws-action-btn.destructive:hover { background: #fee2e2; }
        .md-ws-action-btn.success { color: #16a34a; }
        .md-ws-action-btn.success:hover { background: #dcfce7; }
        .md-ws-action-btn.is-running { pointer-events:none; opacity:.6; }
        .md-bottom-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 24px; }
        .md-panel { background: #fff; border: 1px solid var(--border); border-radius: 16px; overflow: hidden; box-shadow: 0 2px 10px rgba(15,23,42,.04); }
        .md-panel-head { display: flex; align-items: center; gap: 12px; padding: 18px 20px; }
        .md-panel-head-icon { width: 40px; height: 40px; border-radius: 12px; background: var(--brand-grad); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 17px; flex-shrink: 0; }
        .md-panel-head-icon.green { background: linear-gradient(135deg,#22c55e,#16a34a); }
        .md-panel-title { font-size: 16px; font-weight: 700; color: var(--text); margin: 0 0 1px; }
        .md-panel-sub { font-size: 12px; color: var(--muted); }
        .md-panel-body { padding: 0 20px 20px; }
        .md-status-list { display: flex; flex-direction: column; }
        .md-status-item { display: flex; align-items: center; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 500; }
        .md-status-item:last-child { border-bottom: none; }
        .md-status-left { display: flex; align-items: center; gap: 8px; color: #374151; }
        .md-status-left i { font-size: 14px; }
        .md-status-badge-ok    { color: #16a34a; font-weight: 700; font-size: 12px; }
        .md-status-badge-warn  { color: #dc2626; font-weight: 700; font-size: 12px; }
        .md-status-badge-muted { color: var(--muted); font-weight: 600; font-size: 12px; }
        .md-global-btn { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; padding: 13px 14px; border-radius: 12px; border: none; background: var(--brand-grad); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .2s, transform .2s; margin-bottom: 10px; }
        .md-global-btn:hover { opacity: .88; transform: translateY(-1px); }
        .md-global-btn.disabled { background: #e5e7ef; color: #9ca3af; cursor: not-allowed; pointer-events: none; }
        .md-danger-box { margin-top: 14px; border: 1px solid #fca5a5; border-radius: 12px; padding: 14px; background: #fff9f9; }
        .md-danger-title { display: flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 700; color: #b91c1c; margin-bottom: 4px; }
        .md-danger-sub { font-size: 11px; color: #ef4444; margin-bottom: 10px; }
        .md-cli-list { display: flex; flex-direction: column; gap: 12px; }
        .md-cli-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; background: #fafbff; }
        .md-cli-info { flex: 1; min-width: 0; }
        .md-cli-label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        .md-cli-label i { font-size: 12px; }
        .md-cli-label i.destructive { color: #ef4444; }
        .md-cli-cmd { font-family: Consolas, 'Courier New', monospace; font-size: 12px; color: #1e293b; word-break: break-all; }
        .md-cli-copy { background: none; border: none; cursor: pointer; color: var(--muted); font-size: 14px; flex-shrink: 0; padding: 4px; border-radius: 6px; transition: color .15s, background .15s; }
        .md-cli-copy:hover { color: var(--brand); background: var(--soft); }
        .md-run-log { margin-top: 14px; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .md-run-log-head { padding: 10px 14px; font-size: 12px; font-weight: 700; color: #334155; background: #f8fafc; border-bottom: 1px solid var(--border); }
        .md-run-log-list { max-height: 160px; overflow-y: auto; }
        .md-run-log-item { padding: 8px 14px; border-bottom: 1px solid #eef2f7; font-size: 12px; color: #475569; }
        .md-run-log-item:last-child { border-bottom: none; }
        .md-run-log-time { color: #94a3b8; margin-right: 7px; font-family: Consolas, monospace; }
        .md-filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .md-filter-bar select { height: 36px; border: 1px solid var(--border); border-radius: 9px; padding: 0 10px; background: #fff; color: #374151; font-size: 13px; outline: none; }
        @media (max-width: 1024px) { .md-workspaces-grid { grid-template-columns: repeat(2,1fr); } .md-bottom-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .md-stats-row { grid-template-columns: 1fr; } .md-workspaces-grid { grid-template-columns: 1fr; } .md-container { padding: 16px; } }
    </style>
</head>
<body>
<?php include "inc/toast.php"; ?>

<!-- NAVBAR -->
<nav class="md-navbar">
    <div class="md-navbar-brand">
        <div class="md-navbar-logo"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <div class="md-navbar-title">Super Admin Control</div>
            <div class="md-navbar-sub">Workspace Management &amp; Maintenance</div>
        </div>
    </div>
    <button type="button" class="md-exit-btn" id="maintenanceLogoutBtn" aria-label="Exit Dashboard">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Exit Dashboard
    </button>
</nav>

<div class="md-container">

    <!-- STAT CARDS -->
    <div class="md-stats-row">
        <div class="md-stat-card">
            <div>
                <div class="md-stat-icon"><i class="fa-solid fa-database"></i></div>
            </div>
            <div style="flex:1;padding-left:14px;">
                <div class="md-stat-label">Total Workspaces</div>
                <div class="md-stat-value"><?= (int)$totalWorkspaces ?></div>
            </div>
            <div class="md-stat-trend"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="md-stat-card">
            <div>
                <div class="md-stat-icon green"><i class="fa-solid fa-users"></i></div>
            </div>
            <div style="flex:1;padding-left:14px;">
                <div class="md-stat-label">Total Users</div>
                <div class="md-stat-value"><?= (int)$totalMembers ?></div>
            </div>
            <div class="md-stat-trend"><i class="fa-solid fa-user-slash" style="color:#a3aab8;"></i></div>
        </div>
        <div class="md-stat-card">
            <div>
                <div class="md-stat-icon blue"><i class="fa-solid fa-chart-line"></i></div>
            </div>
            <div style="flex:1;padding-left:14px;">
                <div class="md-stat-label">Avg. Users</div>
                <div class="md-stat-value"><?= $totalWorkspaces > 0 ? round($totalMembers / $totalWorkspaces) : 0 ?></div>
            </div>
            <div class="md-stat-trend"><i class="fa-solid fa-wave-square" style="color:#a3aab8;"></i></div>
        </div>
    </div>

    <!-- WORKSPACES SECTION -->
    <div class="md-section-header">
        <div>
            <div class="md-section-title">Workspaces</div>
            <div class="md-section-sub">Manage and monitor all workspaces</div>
        </div>
        <div class="md-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="workspaceSearch" placeholder="Search workspaces...">
        </div>
    </div>

    <div class="md-filter-bar">
        <select id="workspaceStatusFilter">
            <option value="">All status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </select>
        <select id="workspacePlanFilter">
            <option value="">All plans</option>
            <option value="trial">Trial</option>
            <option value="legacy">Legacy</option>
            <option value="starter">Starter</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
        </select>
    </div>

    <?php if ($queryError !== null) { ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
            Failed to load organizations: <?= htmlspecialchars($queryError) ?>
        </div>
    <?php } elseif (!$tenantEnabled) { ?>
        <p style="color:var(--muted);">Tenant columns are not enabled yet. Run your tenancy migration first.</p>
    <?php } elseif (empty($orgRows)) { ?>
        <p style="color:var(--muted);">No organizations found.</p>
    <?php } else { ?>
    <div class="md-workspaces-grid" id="workspaceTableBody">
        <?php foreach ($orgRows as $org) {
            $statusClass = strtolower((string)$org['status']);
            $planClass   = strtolower((string)$org['plan_code']);
            $workspaceName = trim((string)($org['name'] ?? ''));
            if ($workspaceName === '') {
                $workspaceName = 'this workspace';
            }
            $isWorkspaceOff = $statusClass !== 'active';
            $nextWorkspaceStatus = $isWorkspaceOff ? 'active' : 'inactive';
            $toggleLabel = $isWorkspaceOff ? 'Turn On Workspace' : 'Turn Off Workspace';
            $toggleText = $isWorkspaceOff ? 'Turn On' : 'Turn Off';
            $toggleClass = $isWorkspaceOff ? 'success' : 'destructive';
            $togglePrompt = $isWorkspaceOff
                ? ("Turn ON \"" . $workspaceName . "\"? Users in this workspace will be allowed to login again.")
                : ("Turn OFF \"" . $workspaceName . "\"? Users in this workspace will be blocked from logging in.");
            $toggleHref  = maintenance_build_link('toggle_workspace_status.php', (int)$org['id'])
                . '&set_status=' . urlencode($nextWorkspaceStatus)
                . '&return_to=maintenance_dashboard';
            $deleteHref  = maintenance_build_link('delete_workspace.php', (int)$org['id']) . '&return_to=maintenance_dashboard';
        ?>
        <div class="md-ws-card<?= $isWorkspaceOff ? ' is-off' : '' ?>"
             data-workspace-name="<?= htmlspecialchars(strtolower((string)$org['name'])) ?>"
             data-workspace-slug="<?= htmlspecialchars(strtolower((string)$org['slug'])) ?>"
             data-workspace-status="<?= htmlspecialchars($statusClass) ?>"
             data-workspace-plan="<?= htmlspecialchars($planClass) ?>">

            <div class="md-ws-card-header">
                <div class="md-ws-name"><?= htmlspecialchars((string)$org['name']) ?></div>
                <div class="md-ws-more-wrap">
                    <button type="button" class="md-ws-more js-ws-menu-toggle" aria-label="Workspace options" aria-expanded="false">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="md-ws-menu" role="menu">
                        <button
                            type="button"
                            class="md-ws-menu-item danger js-delete-workspace-btn"
                            data-delete-href="<?= htmlspecialchars($deleteHref) ?>"
                            data-delete-workspace="<?= htmlspecialchars((string)$org['name']) ?>">
                            <i class="fa-solid fa-trash-can"></i>
                            Delete Workspace
                        </button>
                    </div>
                </div>
            </div>

            <div class="md-ws-badges">
                <span class="md-badge <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars((string)$org['status']) ?></span>
                <?php if ($planClass): ?>
                <span class="md-badge <?= htmlspecialchars($planClass) ?>"><?= htmlspecialchars((string)$org['plan_code']) ?></span>
                <?php endif; ?>
            </div>

            <div class="md-ws-slug">/<?= htmlspecialchars((string)$org['slug']) ?></div>

            <div class="md-ws-meta">
                <div class="md-ws-meta-item">
                    <i class="fa-solid fa-database md-ws-meta-icon"></i>
                    <div>
                        <div class="md-ws-meta-label">ID</div>
                        <div class="md-ws-meta-value"><?= (int)$org['id'] ?></div>
                    </div>
                </div>
                <div class="md-ws-meta-item">
                    <i class="fa-solid fa-users md-ws-meta-icon"></i>
                    <div>
                        <div class="md-ws-meta-label">MEMBERS</div>
                        <div class="md-ws-meta-value"><?= (int)$org['member_count'] ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($org['subscription_status'])) { ?>
            <div class="md-ws-sub-row">
                <i class="fa-solid fa-wave-square"></i>
                <strong>Subscription</strong>
                &middot; <?= htmlspecialchars((string)$org['subscription_status']) ?>
                <?php if (!empty($org['seat_limit'])) { ?>
                    &middot; <?= (int)$org['seat_limit'] ?> seats
                <?php } ?>
            </div>
            <?php } ?>

            <div class="md-ws-actions">
                <a href="<?= htmlspecialchars($toggleHref) ?>"
                   target="_self"
                   rel="noopener noreferrer"
                   data-action-label="<?= htmlspecialchars($toggleLabel) ?>"
                   data-action-workspace="<?= htmlspecialchars((string)$org['name']) ?>"
                   data-toggle-href="<?= htmlspecialchars($toggleHref) ?>"
                   data-toggle-prompt="<?= htmlspecialchars($togglePrompt, ENT_QUOTES) ?>"
                   data-toggle-button-text="<?= htmlspecialchars($toggleText) ?>"
                   class="md-ws-action-btn <?= htmlspecialchars($toggleClass) ?> js-toggle-workspace-btn"
                   >
                    <i class="fa-solid fa-power-off"></i> <?= htmlspecialchars($toggleText) ?>
                </a>
            </div>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- BOTTOM 3-COLUMN SECTION -->
    <div class="md-bottom-grid">

        <!-- System Status -->
        <div class="md-panel">
            <div class="md-panel-head">
                <div class="md-panel-head-icon green"><i class="fa-solid fa-wave-square"></i></div>
                <div>
                    <div class="md-panel-title">System Status</div>
                    <div class="md-panel-sub">Real-time monitoring</div>
                </div>
            </div>
            <div class="md-panel-body">
                <div class="md-status-list">
                    <div class="md-status-item">
                        <div class="md-status-left">
                            <i class="fa-regular fa-circle-check" style="color:#22c55e;"></i>
                            Tenant Mode
                        </div>
                        <?php if ($tenantEnabled) { ?>
                            <span class="md-status-badge-ok">ENABLED</span>
                        <?php } else { ?>
                            <span class="md-status-badge-warn">DISABLED</span>
                        <?php } ?>
                    </div>
                    <div class="md-status-item">
                        <div class="md-status-left">
                            <i class="fa-regular fa-circle-xmark" style="color:#ef4444;"></i>
                            Global Override
                        </div>
                        <?php if ($globalOverrideAllowed) { ?>
                            <span class="md-status-badge-ok">ENABLED</span>
                        <?php } else { ?>
                            <span class="md-status-badge-warn">DISABLED</span>
                        <?php } ?>
                    </div>
                    <div class="md-status-item">
                        <div class="md-status-left">
                            <i class="fa-regular fa-clock" style="color:#a78bfa;"></i>
                            Last Activity
                        </div>
                        <span class="md-status-badge-muted" id="lastActivityTime">just now</span>
                    </div>
                </div>
                <div class="md-run-log" style="margin-top:16px;">
                    <div class="md-run-log-head">Recent Actions (This Browser)</div>
                    <div class="md-run-log-list" id="runLogList">
                        <div class="md-run-log-item">No actions run yet.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Actions -->
        <div class="md-panel">
            <div class="md-panel-head">
                <div class="md-panel-head-icon" style="background:linear-gradient(135deg,#f59e0b,#f97316);"><i class="fa-solid fa-bolt"></i></div>
                <div>
                    <div class="md-panel-title">Global Actions</div>
                    <div class="md-panel-sub">System-wide operations</div>
                </div>
            </div>
            <div class="md-panel-body">
                <?php foreach ($globalScripts as $script) { ?>
                <a
                    href="<?= htmlspecialchars($script['path']) ?>"
                    title="<?= htmlspecialchars($script['description']) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="md-global-btn"
                    data-action-label="<?= htmlspecialchars($script['label']) ?>"
                    data-action-workspace="Global"
                >
                    <?php
                        $icon = 'fa-solid fa-play';
                        if (stripos($script['label'],'debug') !== false) $icon = 'fa-solid fa-bug';
                        elseif (stripos($script['label'],'constraint') !== false) $icon = 'fa-solid fa-link';
                        elseif (stripos($script['label'],'schema') !== false) $icon = 'fa-solid fa-table-columns';
                    ?>
                    <i class="<?= $icon ?>"></i> <?= htmlspecialchars($script['label']) ?>
                </a>
                <?php } ?>

                <div class="md-danger-box" id="dangerZone">
                    <div class="md-danger-title">
                        <i class="fa-solid fa-triangle-exclamation"></i> Danger Zone
                    </div>
                    <div class="md-danger-sub">Global reset will affect all workspaces</div>
                    <?php if ($globalOverrideAllowed) { ?>
                        <a
                            href="<?= htmlspecialchars(maintenance_build_link('reset_database.php', null, true)) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            data-action-label="Global Reset"
                            data-action-workspace="Global"
                            class="md-global-btn"
                            style="background:linear-gradient(135deg,#ef4444,#dc2626);margin-bottom:0;"
                            onclick="return confirm('Run GLOBAL reset? This will clear all tenants.');">
                            <i class="fa-solid fa-rotate"></i> Global Reset
                        </a>
                    <?php } else { ?>
                        <button type="button" class="md-global-btn disabled" disabled>
                            Global Reset (Disabled)
                        </button>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- CLI Commands -->
        <div class="md-panel">
            <div class="md-panel-head">
                <div class="md-panel-head-icon" style="background:linear-gradient(135deg,#0f172a,#1e293b);"><i class="fa-solid fa-terminal"></i></div>
                <div>
                    <div class="md-panel-title">CLI Commands</div>
                    <div class="md-panel-sub">Quick copy snippets</div>
                </div>
            </div>
            <div class="md-panel-body">
                <div class="md-cli-list">
                    <div class="md-cli-item">
                        <div class="md-cli-info">
                            <div class="md-cli-label"><i class="fa-solid fa-rotate-left destructive"></i> Reset database for one organization</div>
                            <div class="md-cli-cmd">php reset_database.php --org-id=1</div>
                        </div>
                        <button type="button" class="md-cli-copy" onclick="navigator.clipboard.writeText('php reset_database.php --org-id=1')" title="Copy"><i class="fa-regular fa-copy"></i></button>
                    </div>
                    <div class="md-cli-item">
                        <div class="md-cli-info">
                            <div class="md-cli-label"><i class="fa-solid fa-trash-can destructive"></i> Cleanup orphan task chats</div>
                            <div class="md-cli-cmd">php run_cleanup_orphan_task_chats.php --org-id=1</div>
                        </div>
                        <button type="button" class="md-cli-copy" onclick="navigator.clipboard.writeText('php run_cleanup_orphan_task_chats.php --org-id=1')" title="Copy"><i class="fa-regular fa-copy"></i></button>
                    </div>
                    <div class="md-cli-item">
                        <div class="md-cli-info">
                            <div class="md-cli-label"><i class="fa-solid fa-table-columns"></i> Inspect task chat records</div>
                            <div class="md-cli-cmd">php debug_task_chats.php --org-id=1</div>
                        </div>
                        <button type="button" class="md-cli-copy" onclick="navigator.clipboard.writeText('php debug_task_chats.php --org-id=1')" title="Copy"><i class="fa-regular fa-copy"></i></button>
                    </div>
                    <div class="md-cli-item">
                        <div class="md-cli-info">
                            <div class="md-cli-label"><i class="fa-solid fa-triangle-exclamation destructive"></i> Global reset (requires ALLOW_GLOBAL_MAINTENANCE=1)</div>
                            <div class="md-cli-cmd">php reset_database.php --global=1</div>
                        </div>
                        <button type="button" class="md-cli-copy" onclick="navigator.clipboard.writeText('php reset_database.php --global=1')" title="Copy"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- end .md-bottom-grid -->

</div><!-- end .md-container -->
<button type="button" class="maintenance-logout-btn" id="maintenanceLogoutBtn2" aria-label="Logout" style="display:none;"> id="maintenanceLogoutBtn" aria-label="Logout">
    <i class="fa fa-sign-out" aria-hidden="true"></i>
    <span>Logout</span>
</button>

<div class="maintenance-modal-overlay" id="logoutConfirmModal">
    <div class="maintenance-modal-box">
        <div style="width:46px; height:46px; margin:0 auto 12px; border-radius:50%; background:#FEF3C7; color:#B45309; display:flex; align-items:center; justify-content:center; font-size:18px;">
            <i class="fa fa-sign-out"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:20px; color:#111827;">Logout?</h3>
        <p style="margin:0; font-size:14px; color:#6B7280;">Are you sure you want to logout?</p>
        <div class="maintenance-modal-actions">
            <button type="button" class="maintenance-modal-btn cancel" id="logoutCancelBtn">Cancel</button>
            <button type="button" class="maintenance-modal-btn confirm" id="logoutConfirmBtn">Yes, Logout</button>
        </div>
    </div>
</div>

<div class="maintenance-modal-overlay" id="restrictedErrorModal">
    <div class="maintenance-modal-box">
        <div style="width:46px; height:46px; margin:0 auto 12px; border-radius:50%; background:#FEE2E2; color:#991B1B; display:flex; align-items:center; justify-content:center; font-size:18px;">
            <i class="fa fa-exclamation-triangle"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:20px; color:#111827;">Access Restricted</h3>
        <p style="margin:0; font-size:14px; color:#6B7280;">
            Super admin cannot access <strong><?= htmlspecialchars($restrictedPage) ?></strong>. Use Maintenance Dashboard only.
        </p>
        <div class="maintenance-modal-actions">
            <button type="button" class="maintenance-modal-btn confirm" id="restrictedOkBtn">OK</button>
        </div>
    </div>
</div>

<div class="maintenance-modal-overlay" id="deleteWorkspaceModal">
    <div class="maintenance-modal-box">
        <div style="width:46px; height:46px; margin:0 auto 12px; border-radius:50%; background:#FEE2E2; color:#991B1B; display:flex; align-items:center; justify-content:center; font-size:18px;">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:20px; color:#111827;">Delete Workspace?</h3>
        <p style="margin:0; font-size:14px; color:#6B7280;" id="deleteWorkspaceMessage">
            This will permanently remove workspace users and data.
        </p>
        <div class="maintenance-modal-actions">
            <button type="button" class="maintenance-modal-btn cancel" id="deleteWorkspaceCancelBtn">Cancel</button>
            <button type="button" class="maintenance-modal-btn confirm" id="deleteWorkspaceConfirmBtn">Delete</button>
        </div>
    </div>
</div>

<div class="maintenance-modal-overlay" id="toggleWorkspaceModal">
    <div class="maintenance-modal-box">
        <div style="width:46px; height:46px; margin:0 auto 12px; border-radius:50%; background:#FEE2E2; color:#991B1B; display:flex; align-items:center; justify-content:center; font-size:18px;">
            <i class="fa-solid fa-power-off"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:20px; color:#111827;" id="toggleWorkspaceTitle">Confirm Workspace Action</h3>
        <p style="margin:0; font-size:14px; color:#6B7280;" id="toggleWorkspaceMessage">
            Are you sure you want to continue?
        </p>
        <div class="maintenance-modal-actions">
            <button type="button" class="maintenance-modal-btn cancel" id="toggleWorkspaceCancelBtn">Cancel</button>
            <button type="button" class="maintenance-modal-btn confirm" id="toggleWorkspaceConfirmBtn">Continue</button>
        </div>
    </div>
</div>
<script>
    (function () {
        var searchInput = document.getElementById('workspaceSearch');
        var statusFilter = document.getElementById('workspaceStatusFilter');
        var planFilter = document.getElementById('workspacePlanFilter');
        var tableBody = document.getElementById('workspaceTableBody');
        var dangerToggleBtn = document.getElementById('dangerToggleBtn');
        var dangerZone = document.getElementById('dangerZone');
        var dangerArrow = document.getElementById('dangerArrow');
        var runLogList = document.getElementById('runLogList');
        var actionLinks = document.querySelectorAll('[data-action-label]:not(.js-toggle-workspace-btn)');
        var runLogKey = 'maintenance_dashboard_action_log_v1';
        var logoutBtn = document.getElementById('maintenanceLogoutBtn');
        var logoutConfirmModal = document.getElementById('logoutConfirmModal');
        var logoutCancelBtn = document.getElementById('logoutCancelBtn');
        var logoutConfirmBtn = document.getElementById('logoutConfirmBtn');
        var restrictedErrorModal = document.getElementById('restrictedErrorModal');
        var restrictedOkBtn = document.getElementById('restrictedOkBtn');
        var workspaceMenuToggles = document.querySelectorAll('.js-ws-menu-toggle');
        var deleteWorkspaceButtons = document.querySelectorAll('.js-delete-workspace-btn');
        var deleteWorkspaceModal = document.getElementById('deleteWorkspaceModal');
        var deleteWorkspaceMessage = document.getElementById('deleteWorkspaceMessage');
        var deleteWorkspaceCancelBtn = document.getElementById('deleteWorkspaceCancelBtn');
        var deleteWorkspaceConfirmBtn = document.getElementById('deleteWorkspaceConfirmBtn');
        var pendingDeleteWorkspaceHref = '';
        var pendingDeleteWorkspaceName = '';
        var toggleWorkspaceButtons = document.querySelectorAll('.js-toggle-workspace-btn');
        var toggleWorkspaceModal = document.getElementById('toggleWorkspaceModal');
        var toggleWorkspaceTitle = document.getElementById('toggleWorkspaceTitle');
        var toggleWorkspaceMessage = document.getElementById('toggleWorkspaceMessage');
        var toggleWorkspaceCancelBtn = document.getElementById('toggleWorkspaceCancelBtn');
        var toggleWorkspaceConfirmBtn = document.getElementById('toggleWorkspaceConfirmBtn');
        var pendingToggleWorkspaceHref = '';
        var pendingToggleWorkspaceAction = '';
        var pendingToggleWorkspaceName = '';

        function filterRows() {
            if (!tableBody) return;
            var q = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
            var statusVal = (statusFilter && statusFilter.value ? statusFilter.value : '').toLowerCase();
            var planVal = (planFilter && planFilter.value ? planFilter.value : '').toLowerCase();
            var rows = tableBody.querySelectorAll('.md-ws-card');

            rows.forEach(function (row) {
                var name = row.getAttribute('data-workspace-name') || '';
                var slug = row.getAttribute('data-workspace-slug') || '';
                var status = row.getAttribute('data-workspace-status') || '';
                var plan = row.getAttribute('data-workspace-plan') || '';
                var matchQ = !q || name.indexOf(q) !== -1 || slug.indexOf(q) !== -1;
                var matchStatus = !statusVal || status === statusVal;
                var matchPlan = !planVal || plan === planVal;
                row.style.display = (matchQ && matchStatus && matchPlan) ? '' : 'none';
            });
        }

        function closeAllWorkspaceMenus() {
            var openMenus = document.querySelectorAll('.md-ws-menu.open');
            openMenus.forEach(function (menu) {
                menu.classList.remove('open');
            });
            workspaceMenuToggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            });
        }

        function renderRunLog() {
            if (!runLogList) return;
            var items = [];
            try {
                items = JSON.parse(localStorage.getItem(runLogKey) || '[]');
            } catch (e) {
                items = [];
            }
            if (!items.length) {
                runLogList.innerHTML = '<div class="md-run-log-item">No actions run yet.</div>';
                return;
            }
            runLogList.innerHTML = items.map(function (item) {
                return '<div class="md-run-log-item"><span class="md-run-log-time">' +
                    item.time + '</span>' + item.workspace + ' - ' + item.action + '</div>';
            }).join('');
        }

        function addRunLog(action, workspace) {
            var items = [];
            try {
                items = JSON.parse(localStorage.getItem(runLogKey) || '[]');
            } catch (e) {
                items = [];
            }
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            var ss = String(now.getSeconds()).padStart(2, '0');
            items.unshift({
                time: hh + ':' + mm + ':' + ss,
                action: action || 'Unknown Action',
                workspace: workspace || 'Unknown Workspace'
            });
            items = items.slice(0, 20);
            localStorage.setItem(runLogKey, JSON.stringify(items));
            renderRunLog();
        }

        if (searchInput) searchInput.addEventListener('input', filterRows);
        if (statusFilter) statusFilter.addEventListener('change', filterRows);
        if (planFilter) planFilter.addEventListener('change', filterRows);

        if (dangerToggleBtn && dangerZone && dangerArrow) {
            dangerToggleBtn.addEventListener('click', function () {
                var isOpen = dangerZone.classList.toggle('open');
                dangerArrow.innerHTML = isOpen ? '&#9652;' : '&#9662;';
            });
        }

        workspaceMenuToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var menu = toggle.parentElement ? toggle.parentElement.querySelector('.md-ws-menu') : null;
                var isOpen = menu ? menu.classList.contains('open') : false;
                closeAllWorkspaceMenus();
                if (menu && !isOpen) {
                    menu.classList.add('open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.querySelectorAll('.md-ws-menu').forEach(function (menu) {
            menu.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        });

        document.addEventListener('click', function () {
            closeAllWorkspaceMenus();
        });

        actionLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                if (link.classList.contains('is-running')) {
                    return false;
                }
                link.classList.add('is-running');
                addRunLog(link.getAttribute('data-action-label'), link.getAttribute('data-action-workspace'));
                setTimeout(function () {
                    link.classList.remove('is-running');
                }, 3500);
            });
        });

        function openModal(modal) {
            if (modal) modal.style.display = 'flex';
        }

        function closeModal(modal) {
            if (modal) modal.style.display = 'none';
        }

        deleteWorkspaceButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                pendingDeleteWorkspaceHref = button.getAttribute('data-delete-href') || '';
                pendingDeleteWorkspaceName = button.getAttribute('data-delete-workspace') || 'this workspace';
                if (deleteWorkspaceMessage) {
                    deleteWorkspaceMessage.textContent = 'Delete workspace "' + pendingDeleteWorkspaceName + '"? This will permanently remove the workspace, its users, and tenant data.';
                }
                closeAllWorkspaceMenus();
                openModal(deleteWorkspaceModal);
            });
        });

        toggleWorkspaceButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                pendingToggleWorkspaceHref = button.getAttribute('data-toggle-href') || button.getAttribute('href') || '';
                pendingToggleWorkspaceAction = button.getAttribute('data-action-label') || 'Workspace Action';
                pendingToggleWorkspaceName = button.getAttribute('data-action-workspace') || 'Workspace';

                var promptText = button.getAttribute('data-toggle-prompt') || 'Are you sure you want to continue?';
                var confirmButtonText = button.getAttribute('data-toggle-button-text') || 'Continue';

                if (toggleWorkspaceTitle) {
                    toggleWorkspaceTitle.textContent = pendingToggleWorkspaceAction;
                }
                if (toggleWorkspaceMessage) {
                    toggleWorkspaceMessage.textContent = promptText;
                }
                if (toggleWorkspaceConfirmBtn) {
                    toggleWorkspaceConfirmBtn.textContent = confirmButtonText;
                }

                openModal(toggleWorkspaceModal);
            });
        });

        if (logoutBtn) {
            logoutBtn.addEventListener('click', function () {
                openModal(logoutConfirmModal);
            });
        }
        if (logoutCancelBtn) {
            logoutCancelBtn.addEventListener('click', function () {
                closeModal(logoutConfirmModal);
            });
        }
        if (logoutConfirmBtn) {
            logoutConfirmBtn.addEventListener('click', function () {
                window.location.href = 'logout.php';
            });
        }
        if (logoutConfirmModal) {
            logoutConfirmModal.addEventListener('click', function (e) {
                if (e.target === logoutConfirmModal) closeModal(logoutConfirmModal);
            });
        }
        if (restrictedOkBtn) {
            restrictedOkBtn.addEventListener('click', function () {
                closeModal(restrictedErrorModal);
            });
        }
        if (restrictedErrorModal) {
            restrictedErrorModal.addEventListener('click', function (e) {
                if (e.target === restrictedErrorModal) closeModal(restrictedErrorModal);
            });
        }
        if (deleteWorkspaceCancelBtn) {
            deleteWorkspaceCancelBtn.addEventListener('click', function () {
                closeModal(deleteWorkspaceModal);
            });
        }
        if (deleteWorkspaceConfirmBtn) {
            deleteWorkspaceConfirmBtn.addEventListener('click', function () {
                if (!pendingDeleteWorkspaceHref) {
                    closeModal(deleteWorkspaceModal);
                    return;
                }
                addRunLog('Delete Workspace', pendingDeleteWorkspaceName || 'Unknown Workspace');
                window.location.href = pendingDeleteWorkspaceHref;
            });
        }
        if (deleteWorkspaceModal) {
            deleteWorkspaceModal.addEventListener('click', function (e) {
                if (e.target === deleteWorkspaceModal) closeModal(deleteWorkspaceModal);
            });
        }
        if (toggleWorkspaceCancelBtn) {
            toggleWorkspaceCancelBtn.addEventListener('click', function () {
                closeModal(toggleWorkspaceModal);
            });
        }
        if (toggleWorkspaceConfirmBtn) {
            toggleWorkspaceConfirmBtn.addEventListener('click', function () {
                if (!pendingToggleWorkspaceHref) {
                    closeModal(toggleWorkspaceModal);
                    return;
                }
                addRunLog(pendingToggleWorkspaceAction || 'Workspace Action', pendingToggleWorkspaceName || 'Unknown Workspace');
                window.location.href = pendingToggleWorkspaceHref;
            });
        }
        if (toggleWorkspaceModal) {
            toggleWorkspaceModal.addEventListener('click', function (e) {
                if (e.target === toggleWorkspaceModal) closeModal(toggleWorkspaceModal);
            });
        }
        <?php if ($showRestrictedModal) { ?>
        openModal(restrictedErrorModal);
        <?php } ?>

        renderRunLog();
    })();
</script>
</body>
</html>

