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
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --brand: #6c3ce1;
            --brand-2: #8b5cf6;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --warn-bg: #fee2e2;
            --warn-text: #991b1b;
            --soft: #eef2ff;
            --soft-border: #c7d2fe;
        }
        body {
            margin: 0;
            padding: 16px;
            font-family: Inter, Arial, sans-serif;
            background: radial-gradient(circle at top left, #f8fbff 0%, var(--bg) 45%);
            color: var(--text);
            position: relative;
            overflow-x: hidden;
        }
        body::before,
        body::after {
            content: "";
            position: fixed;
            border-radius: 999px;
            filter: blur(4px);
            z-index: -1;
            pointer-events: none;
        }
        body::before {
            width: 220px;
            height: 220px;
            right: -80px;
            top: 70px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.18) 0%, rgba(139, 92, 246, 0) 70%);
        }
        body::after {
            width: 260px;
            height: 260px;
            left: -100px;
            bottom: 20px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, rgba(59, 130, 246, 0) 70%);
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
        }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-bottom: 14px;
            overflow: hidden;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
            position: relative;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .panel::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--brand), var(--brand-2));
            opacity: 0.85;
        }
        .panel:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
        }
        .panel-head {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
        }
        .panel-body {
            padding: 14px 18px;
        }
        .hero {
            background: linear-gradient(135deg, #f7f3ff 0%, #eef2ff 70%, #f8fafc 100%);
        }
        .hero .panel-body {
            position: relative;
        }
        .hero .panel-body::after {
            content: "";
            position: absolute;
            right: -40px;
            bottom: -60px;
            width: 190px;
            height: 190px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.14) 0%, rgba(139, 92, 246, 0) 70%);
            pointer-events: none;
        }
        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            gap: 12px;
        }
        .maintenance-logout-btn {
            position: fixed;
            right: 16px;
            top: 16px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 10px;
            border: 1px solid #fca5a5;
            background: #ef4444;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: all .2s ease;
            z-index: 1100;
        }
        .maintenance-logout-btn:hover {
            border-color: #f87171;
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
            background: #dc2626;
        }
        .maintenance-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
        }
        .maintenance-modal-box {
            background: #fff;
            width: min(92vw, 380px);
            border-radius: 12px;
            padding: 22px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .maintenance-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 14px;
        }
        .maintenance-modal-btn {
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .maintenance-modal-btn.cancel {
            background: #F3F4F6;
            color: #374151;
        }
        .maintenance-modal-btn.confirm {
            background: #EF4444;
            color: #fff;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 32px;
            line-height: 1.15;
            letter-spacing: -.4px;
        }
        h2 {
            margin: 0;
            font-size: 21px;
        }
        p {
            margin: 6px 0;
            color: var(--muted);
            font-size: 14px;
        }
        .subtitle {
            margin: 0;
            max-width: 900px;
            color: #334155;
        }
        .note {
            background: var(--soft);
            border: 1px solid var(--soft-border);
            color: #3730a3;
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 12px;
            font-size: 13px;
        }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .status-row {
            margin-top: 10px;
        }
        .pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 6px;
            background: #e5e7eb;
            color: #111827;
        }
        .ok {
            background: var(--success-bg);
            color: var(--success-text);
        }
        .warn {
            background: var(--warn-bg);
            color: var(--warn-text);
        }
        .neutral {
            background: #e2e8f0;
            color: #334155;
        }
        .panel-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
        }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .toolbar input,
        .toolbar select {
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0 10px;
            background: #fff;
            color: #334155;
            font-size: 13px;
        }
        .toolbar input {
            min-width: 240px;
        }
        .panel-title .title-mark {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            box-shadow: 0 6px 14px rgba(139, 92, 246, 0.28);
            position: relative;
        }
        .panel-title .title-mark::after {
            content: "";
            position: absolute;
            inset: 5px;
            border-radius: 3px;
            border: 1px solid rgba(255, 255, 255, 0.85);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 13px;
        }
        th {
            color: #475569;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            font-size: 12px;
            background: #f8fafc;
        }
        tbody tr:hover {
            background: #f8fafc;
        }
        tbody tr:nth-child(even) {
            background: #fcfdff;
        }
        tbody tr:nth-child(even):hover {
            background: #f4f8ff;
        }
        .workspace-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.15;
        }
        .workspace-slug {
            margin-top: 3px;
            color: #64748b;
            font-size: 13px;
        }
        .muted-small {
            color: #64748b;
            font-size: 12px;
        }
        .num-strong {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }
        .actions a {
            display: inline-block;
            margin: 3px 6px 3px 0;
            padding: 7px 10px;
            border-radius: 9px;
            text-decoration: none;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            font-size: 12px;
            font-weight: 600;
            transition: all .2s ease;
        }
        .actions a:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.15);
            filter: saturate(115%);
        }
        .actions a.is-running {
            pointer-events: none;
            opacity: .65;
            position: relative;
        }
        .actions a.is-running::after {
            content: "Running...";
            margin-left: 6px;
            font-weight: 700;
        }
        .actions a.destructive {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .actions a.global {
            background: #f5f3ff;
            border-color: #ddd6fe;
            color: #5b21b6;
        }
        code {
            background: #eef2f7;
            border-radius: 6px;
            padding: 3px 7px;
            font-size: 12px;
        }
        .cli-list {
            display: grid;
            gap: 12px;
        }
        .cli-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            border-radius: 12px;
            background: #08152f;
            color: #93c5fd;
            padding: 12px 14px;
            border: 1px solid #132547;
            position: relative;
        }
        .cli-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 8px;
            bottom: 8px;
            width: 3px;
            border-radius: 6px;
            background: linear-gradient(180deg, #00ffa2, #22d3ee);
        }
        .cli-item .cmd {
            color: #00ffa2;
            font-family: Consolas, monospace;
            font-size: 15px;
            word-break: break-word;
            padding-left: 8px;
        }
        .cli-item .desc {
            color: #94a3b8;
            font-size: 13px;
            margin-top: 4px;
            padding-left: 8px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }
        .accordion {
            margin-top: 12px;
            border: 1px solid #fecaca;
            background: #fff7f7;
            border-radius: 10px;
            overflow: hidden;
        }
        .accordion-btn {
            width: 100%;
            background: transparent;
            border: none;
            text-align: left;
            padding: 10px 12px;
            color: #991b1b;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .accordion-body {
            display: none;
            padding: 0 12px 12px;
        }
        .accordion.open .accordion-body {
            display: block;
        }
        .run-log {
            margin-top: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }
        .run-log-head {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            background: #f8fafc;
        }
        .run-log-list {
            max-height: 180px;
            overflow-y: auto;
        }
        .run-log-item {
            padding: 9px 12px;
            border-bottom: 1px solid #eef2f7;
            font-size: 12px;
            color: #475569;
        }
        .run-log-item:last-child {
            border-bottom: none;
        }
        .run-log-time {
            color: #94a3b8;
            margin-right: 8px;
            font-family: Consolas, monospace;
        }
        .stat-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #e2e8f0, #cbd5e1);
        }
        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            color: #fff;
            flex-shrink: 0;
        }
        .stat-icon.w { background: linear-gradient(135deg, #8B5CF6, #8b5cf6); }
        .stat-icon.a { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .stat-icon.m { background: linear-gradient(135deg, #0ea5e9, #3b82f6); }
        .stat-label {
            color: #64748b;
            font-size: 14px;
        }
        .stat-value {
            font-size: 30px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .stat-content {
            min-width: 0;
        }
        @media (max-width: 900px) {
            body { padding: 12px; }
            h1 { font-size: 27px; }
            h2 { font-size: 20px; }
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }
            .toolbar input {
                min-width: 0;
                width: 100%;
            }
            th, td { font-size: 12px; }
            .actions a { margin-bottom: 4px; }
            .stats {
                grid-template-columns: 1fr;
            }
            .panel-body, .panel-head {
                padding: 14px;
            }
            .cli-item .cmd {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
<?php include "inc/toast.php"; ?>
<div class="container">
    <div class="panel hero">
        <div class="panel-body">
            <div class="topbar">
                <div>
                    <h1>Maintenance Dashboard</h1>
                    <p class="subtitle">Run maintenance and debug tools per workspace without manually typing <code>--org-id</code>.</p>
                    <div class="status-row">
                        <?php if ($tenantEnabled) { ?>
                            <span class="pill ok">Tenant mode enabled</span>
                        <?php } else { ?>
                            <span class="pill warn">Tenant mode not detected</span>
                        <?php } ?>
                        <?php if ($globalOverrideAllowed) { ?>
                            <span class="pill warn">Global override enabled</span>
                        <?php } else { ?>
                            <span class="pill neutral">Global override disabled</span>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="note">
                Global scripts and global reset are powerful. In tenant mode, prefer per-workspace actions.
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><span class="title-mark" aria-hidden="true"></span>Workspace Actions</h2>
        </div>
        <div class="panel-body">
        <div class="toolbar">
            <input type="text" id="workspaceSearch" placeholder="Search workspace name or slug...">
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
            <div class="error">Failed to load organizations: <?= htmlspecialchars($queryError) ?></div>
        <?php } elseif (!$tenantEnabled) { ?>
            <p>Tenant columns are not enabled yet. Run your tenancy migration first.</p>
        <?php } elseif (empty($orgRows)) { ?>
            <p>No organizations found.</p>
        <?php } else { ?>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Workspace</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Members</th>
                    <th>Subscription</th>
                    <th>Run Script</th>
                </tr>
                </thead>
                <tbody id="workspaceTableBody">
                <?php foreach ($orgRows as $org) { ?>
                    <tr
                        data-workspace-name="<?= htmlspecialchars(strtolower((string)$org['name'])) ?>"
                        data-workspace-slug="<?= htmlspecialchars(strtolower((string)$org['slug'])) ?>"
                        data-workspace-status="<?= htmlspecialchars(strtolower((string)$org['status'])) ?>"
                        data-workspace-plan="<?= htmlspecialchars(strtolower((string)$org['plan_code'])) ?>"
                    >
                        <td><code><?= (int)$org['id'] ?></code></td>
                        <td>
                            <div class="workspace-name"><?= htmlspecialchars((string)$org['name']) ?></div>
                            <div class="workspace-slug">slug: <?= htmlspecialchars((string)$org['slug']) ?></div>
                        </td>
                        <td><span class="pill ok" style="margin:0;"><?= htmlspecialchars((string)$org['status']) ?></span></td>
                        <td><span class="pill neutral" style="margin:0;"><?= htmlspecialchars((string)$org['plan_code']) ?></span></td>
                        <td><span class="num-strong"><?= (int)$org['member_count'] ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars((string)($org['subscription_status'] ?? '-')) ?></strong>
                            <?php if (!empty($org['seat_limit'])) { ?>
                                <br><span class="muted-small">seats: <?= (int)$org['seat_limit'] ?></span>
                            <?php } ?>
                        </td>
                        <td class="actions">
                            <?php foreach ($tenantScripts as $script) {
                                $href = maintenance_build_link($script['path'], (int)$org['id']);
                                $linkTarget = '_blank';
                                if ($script['path'] === 'reset_database.php') {
                                    $href .= '&return_to=maintenance_dashboard';
                                    $linkTarget = '_self';
                                }
                                $confirm = '';
                                if (!empty($script['destructive'])) {
                                    $confirm = "return confirm('Run {$script['label']} for workspace ID {$org['id']}? This can delete data.');";
                                }
                                ?>
                                <a
                                    href="<?= htmlspecialchars($href) ?>"
                                    title="<?= htmlspecialchars($script['description']) ?>"
                                    target="<?= htmlspecialchars($linkTarget) ?>"
                                    rel="noopener noreferrer"
                                    data-action-label="<?= htmlspecialchars($script['label']) ?>"
                                    data-action-workspace="<?= htmlspecialchars((string)$org['name']) ?>"
                                    class="<?= !empty($script['destructive']) ? 'destructive' : '' ?>"
                                    <?= $confirm ? 'onclick="' . htmlspecialchars($confirm) . '"' : '' ?>
                                ><?= htmlspecialchars($script['label']) ?></a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><span class="title-mark" aria-hidden="true"></span>Global Scripts</h2>
            <p>These are not tenant-scoped diagnostics.</p>
        </div>
        <div class="panel-body">
            <div class="actions">
            <?php foreach ($globalScripts as $script) { ?>
                <a
                    href="<?= htmlspecialchars($script['path']) ?>"
                    title="<?= htmlspecialchars($script['description']) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="global"
                    data-action-label="<?= htmlspecialchars($script['label']) ?>"
                    data-action-workspace="Global"
                ><?= htmlspecialchars($script['label']) ?></a>
            <?php } ?>
            </div>
            <div class="accordion" id="dangerZone">
                <button type="button" class="accordion-btn" id="dangerToggleBtn">
                    <span>Danger Zone: Global Reset</span>
                    <span id="dangerArrow">&#9662;</span>
                </button>
                <div class="accordion-body">
                    <p style="margin-top:10px;">
                        <strong>Global reset link:</strong>
                        <?php if ($globalOverrideAllowed) { ?>
                            <a
                                class="destructive"
                                href="<?= htmlspecialchars(maintenance_build_link('reset_database.php', null, true)) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                data-action-label="Global Reset"
                                data-action-workspace="Global"
                                onclick="return confirm('Run GLOBAL reset? This will clear all tenants.');"
                            >reset_database.php?global=1</a>
                        <?php } else { ?>
                            <code>Disabled (set ALLOW_GLOBAL_MAINTENANCE=1 to enable)</code>
                        <?php } ?>
                    </p>
                </div>
            </div>
            <div class="run-log">
                <div class="run-log-head">Recent Actions (This Browser)</div>
                <div class="run-log-list" id="runLogList">
                    <div class="run-log-item">No actions run yet.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><span class="title-mark" aria-hidden="true"></span>CLI Examples</h2>
            <p>Manual command-line scripts for advanced operations.</p>
        </div>
        <div class="panel-body">
            <div class="cli-list">
                <div class="cli-item">
                    <div>
                        <div class="cmd">php reset_database.php --org-id=1</div>
                        <div class="desc">Reset database for one organization</div>
                    </div>
                </div>
                <div class="cli-item">
                    <div>
                        <div class="cmd">php run_cleanup_orphan_task_chats.php --org-id=1</div>
                        <div class="desc">Cleanup orphan task chats</div>
                    </div>
                </div>
                <div class="cli-item">
                    <div>
                        <div class="cmd">php debug_task_chats.php --org-id=1</div>
                        <div class="desc">Inspect task chat records</div>
                    </div>
                </div>
                <div class="cli-item">
                    <div>
                        <div class="cmd">php reset_database.php --global=1</div>
                        <div class="desc">Global reset (requires ALLOW_GLOBAL_MAINTENANCE=1)</div>
                    </div>
                </div>
            </div>
            <div class="note" style="margin-top:14px;">
                Note: Run commands from the project root and keep backups before destructive operations.
            </div>
            <div class="stats">
                <div class="stat-card">
                    <span class="stat-icon w"><i class="fa fa-database" aria-hidden="true"></i></span>
                    <div class="stat-content">
                        <div class="stat-label">Total Workspaces</div>
                        <div class="stat-value"><?= (int)$totalWorkspaces ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon a"><i class="fa fa-check-circle-o" aria-hidden="true"></i></span>
                    <div class="stat-content">
                        <div class="stat-label">Active Workspaces</div>
                        <div class="stat-value"><?= (int)$activeWorkspaces ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon m"><i class="fa fa-users" aria-hidden="true"></i></span>
                    <div class="stat-content">
                        <div class="stat-label">Total Members</div>
                        <div class="stat-value"><?= (int)$totalMembers ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<button type="button" class="maintenance-logout-btn" id="maintenanceLogoutBtn" aria-label="Logout">
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
        var actionLinks = document.querySelectorAll('.actions a, .destructive');
        var runLogKey = 'maintenance_dashboard_action_log_v1';
        var logoutBtn = document.getElementById('maintenanceLogoutBtn');
        var logoutConfirmModal = document.getElementById('logoutConfirmModal');
        var logoutCancelBtn = document.getElementById('logoutCancelBtn');
        var logoutConfirmBtn = document.getElementById('logoutConfirmBtn');
        var restrictedErrorModal = document.getElementById('restrictedErrorModal');
        var restrictedOkBtn = document.getElementById('restrictedOkBtn');

        function filterRows() {
            if (!tableBody) return;
            var q = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
            var statusVal = (statusFilter && statusFilter.value ? statusFilter.value : '').toLowerCase();
            var planVal = (planFilter && planFilter.value ? planFilter.value : '').toLowerCase();
            var rows = tableBody.querySelectorAll('tr');

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

        function renderRunLog() {
            if (!runLogList) return;
            var items = [];
            try {
                items = JSON.parse(localStorage.getItem(runLogKey) || '[]');
            } catch (e) {
                items = [];
            }
            if (!items.length) {
                runLogList.innerHTML = '<div class="run-log-item">No actions run yet.</div>';
                return;
            }
            runLogList.innerHTML = items.map(function (item) {
                return '<div class="run-log-item"><span class="run-log-time">' +
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
        <?php if ($showRestrictedModal) { ?>
        openModal(restrictedErrorModal);
        <?php } ?>

        renderRunLog();
    })();
</script>
</body>
</html>

