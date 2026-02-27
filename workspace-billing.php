<?php
session_start();

if (!isset($_SESSION['role']) || !isset($_SESSION['id']) || $_SESSION['role'] !== "admin") {
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
}

include "DB_connection.php";
include "app/model/user.php";
require_once "inc/tenant.php";
require_once "inc/csrf.php";

function wb_format_datetime($value)
{
    if (empty($value)) {
        return "N/A";
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return "N/A";
    }
    return date("M j, Y g:i A", $ts);
}

function wb_days_left_text($value)
{
    if (empty($value)) {
        return null;
    }
    $targetTs = strtotime((string)$value);
    if ($targetTs === false) {
        return null;
    }
    $seconds = $targetTs - time();
    $days = (int)floor($seconds / 86400);
    if ($days < 0) {
        return "Expired";
    }
    if ($days === 0) {
        return "Less than 1 day left";
    }
    if ($days === 1) {
        return "1 day left";
    }
    return $days . " days left";
}

function wb_status_badge_class($status)
{
    $status = strtolower(trim((string)$status));
    if (in_array($status, ['active', 'trialing', 'trial'], true)) {
        return 'ok';
    }
    if (in_array($status, ['past_due', 'unpaid', 'incomplete', 'paused'], true)) {
        return 'warn';
    }
    return 'danger';
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$tenantEnabled = tenant_column_exists($pdo, 'users', 'organization_id') && tenant_table_exists($pdo, 'organizations');
$orgId = tenant_get_current_org_id();
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageSeats = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));

$error = null;
$org = null;
$subscription = null;
$capacity = null;
$seatUsed = 0;
$seatLimit = null;
$seatsLeft = null;
$seatUsagePct = 0;
$pendingInvites = 0;
$ownerCount = 0;
$adminCount = 0;
$memberCount = 0;
$flashSuccess = isset($_GET['success']) ? trim((string)$_GET['success']) : null;
$flashError = isset($_GET['error']) ? trim((string)$_GET['error']) : null;

if (!$tenantEnabled) {
    $error = "Workspace billing is unavailable until tenant migration is enabled.";
} elseif (!$orgId) {
    $error = "Workspace context is missing. Please log in again.";
} else {
    try {
        $stmtOrg = $pdo->prepare(
            "SELECT id, name, slug, status, plan_code, billing_email, created_at
             FROM organizations
             WHERE id = ?
             LIMIT 1"
        );
        $stmtOrg->execute([(int)$orgId]);
        $org = $stmtOrg->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$org) {
            $error = "Workspace was not found.";
        } else {
            $subscription = tenant_ensure_subscription($pdo, (int)$orgId);
            $capacity = tenant_check_workspace_capacity($pdo, (int)$orgId);

            $seatUsed = (int)($capacity['seat_used'] ?? 0);
            $seatLimit = isset($capacity['seat_limit']) ? (int)$capacity['seat_limit'] : null;
            $seatsLeft = isset($capacity['seats_left']) ? (int)$capacity['seats_left'] : null;
            if ($seatLimit !== null && $seatLimit > 0) {
                $seatUsagePct = (int)min(100, round(($seatUsed / $seatLimit) * 100));
            }

            if (tenant_table_exists($pdo, 'organization_members')) {
                $stmtMembers = $pdo->prepare(
                    "SELECT
                        SUM(CASE WHEN role = 'owner' THEN 1 ELSE 0 END) AS owner_count,
                        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
                        SUM(CASE WHEN role = 'member' THEN 1 ELSE 0 END) AS member_count
                     FROM organization_members
                     WHERE organization_id = ?"
                );
                $stmtMembers->execute([(int)$orgId]);
                $counts = $stmtMembers->fetch(PDO::FETCH_ASSOC) ?: [];
                $ownerCount = (int)($counts['owner_count'] ?? 0);
                $adminCount = (int)($counts['admin_count'] ?? 0);
                $memberCount = (int)($counts['member_count'] ?? 0);
            }

            if (tenant_table_exists($pdo, 'workspace_invites')) {
                $stmtInv = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM workspace_invites
                     WHERE organization_id = ?
                       AND status = 'pending'
                       AND expires_at > NOW()"
                );
                $stmtInv->execute([(int)$orgId]);
                $pendingInvites = (int)$stmtInv->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        $error = "Unable to load workspace billing details right now.";
    }
}

$workspaceDisplayName = (string)($org['name'] ?? ($_SESSION['organization_name'] ?? 'Workspace'));
$workspaceSlug = (string)($org['slug'] ?? 'N/A');
$workspaceId = isset($org['id']) ? (int)$org['id'] : 0;
$workspaceStatus = (string)($org['status'] ?? 'N/A');
$workspacePlanCode = (string)($org['plan_code'] ?? 'N/A');
$workspaceBillingEmail = (string)(!empty($org['billing_email']) ? $org['billing_email'] : 'N/A');
$subscriptionStatusText = strtoupper((string)($subscription['status'] ?? 'N/A'));
$seatUsageDisplay = $seatLimit !== null ? ($seatUsed . "/" . $seatLimit) : (string)$seatUsed;
$availablePlans = tenant_workspace_plan_catalog();
$resolvedWorkspacePlan = tenant_resolve_workspace_plan($workspacePlanCode, 'starter');
$currentWorkspacePlanCode = (string)($resolvedWorkspacePlan['code'] ?? 'starter');
$currentWorkspacePlanName = (string)($resolvedWorkspacePlan['name'] ?? 'Starter');
$dummyPlanPrices = [
    'starter' => 399,
    'professional' => 799,
    'enterprise' => 1499,
];
$currentPlanPrice = (int)($dummyPlanPrices[strtolower($currentWorkspacePlanCode)] ?? $dummyPlanPrices['starter']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Billing | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/workspace-panels.css">
</head>
<body class="billing-page">
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <div class="workspace-shell workspace-animate">
        <section class="workspace-hero">
            <div>
                <span class="workspace-eyebrow">
                    <i class="fa fa-credit-card"></i> Workspace Billing
                </span>
                <h2>Control seats, monitor renewal windows, and keep onboarding stable.</h2>
                <p>One place to view plan health, active capacity, and role distribution before you invite additional members.</p>
            </div>
            <div class="workspace-hero-stats">
                <div class="workspace-hero-stat">
                    <span>Workspace</span>
                    <strong><?= htmlspecialchars($workspaceDisplayName) ?></strong>
                </div>
                <div class="workspace-hero-stat">
                    <span>Subscription</span>
                    <strong><?= htmlspecialchars($subscriptionStatusText) ?></strong>
                </div>
                <div class="workspace-hero-stat">
                    <span>Seats</span>
                    <strong><?= $seatUsageDisplay ?></strong>
                    <small>
                        <?php if ($seatsLeft !== null) { ?>
                            <?= max(0, $seatsLeft) ?> seat<?= max(0, $seatsLeft) === 1 ? '' : 's' ?> left
                        <?php } else { ?>
                            Seat limit not configured
                        <?php } ?>
                    </small>
                </div>
                <div class="workspace-hero-stat">
                    <span>Pending Invites</span>
                    <strong><?= $pendingInvites ?></strong>
                    <small>Pending invites do not consume seats yet</small>
                </div>
            </div>
        </section>

        <?php if ($isSuperAdmin || !empty($flashSuccess) || !empty($flashError)) { ?>
            <div class="workspace-alert-stack">
                <?php if ($isSuperAdmin) { ?>
                    <div class="workspace-alert info">
                        <i class="fa fa-info-circle"></i>
                        <div>You are signed in as Super Admin and currently viewing one workspace context.</div>
                    </div>
                <?php } ?>
                <?php if (!empty($flashSuccess)) { ?>
                    <div class="workspace-alert success">
                        <i class="fa fa-check-circle"></i>
                        <div><?= htmlspecialchars($flashSuccess) ?></div>
                    </div>
                <?php } ?>
                <?php if (!empty($flashError)) { ?>
                    <div class="workspace-alert error">
                        <i class="fa fa-exclamation-circle"></i>
                        <div><?= htmlspecialchars($flashError) ?></div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($error !== null) { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <h3 class="workspace-panel-title">Billing Details Unavailable</h3>
                </div>
                <div class="workspace-alert error">
                    <i class="fa fa-exclamation-triangle"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            </section>
        <?php } else { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <div>
                        <h3 class="workspace-panel-title"><?= htmlspecialchars($workspaceDisplayName) ?></h3>
                        <p class="workspace-panel-sub">Workspace status, plan metadata, and billing contact snapshot.</p>
                    </div>
                    <span class="workspace-pill <?= wb_status_badge_class($subscription['status'] ?? 'active') ?>">
                        <?= htmlspecialchars($subscriptionStatusText) ?>
                    </span>
                </div>
                <div class="workspace-meta-row">
                    <span class="workspace-meta-chip"><i class="fa fa-hashtag"></i> Org ID <strong><?= $workspaceId ?></strong></span>
                    <span class="workspace-meta-chip"><i class="fa fa-tag"></i> Slug <strong><?= htmlspecialchars($workspaceSlug) ?></strong></span>
                    <span class="workspace-meta-chip"><i class="fa fa-shield"></i> Status <strong><?= htmlspecialchars($workspaceStatus) ?></strong></span>
                    <span class="workspace-meta-chip"><i class="fa fa-cubes"></i> Plan <strong><?= htmlspecialchars($currentWorkspacePlanCode) ?></strong></span>
                    <span class="workspace-meta-chip"><i class="fa fa-envelope-o"></i> Billing <strong><?= htmlspecialchars($workspaceBillingEmail) ?></strong></span>
                </div>
            </section>

            <?php if ($capacity && !$capacity['ok']) { ?>
                <div class="workspace-alert warn">
                    <i class="fa fa-warning"></i>
                    <div><?= htmlspecialchars((string)$capacity['reason']) ?></div>
                </div>
            <?php } ?>

            <div class="billing-stats-grid">
                <article class="workspace-stat-card">
                    <div class="workspace-stat-head">
                        <span class="workspace-stat-label">Seats</span>
                        <span class="workspace-stat-icon"><i class="fa fa-users"></i></span>
                    </div>
                    <div class="workspace-stat-value"><?= $seatUsageDisplay ?></div>
                    <p class="workspace-stat-hint">
                        Used <?= $seatUsed ?> seat<?= $seatUsed !== 1 ? 's' : '' ?>
                        <?php if ($seatsLeft !== null) { ?>
                            , <?= max(0, $seatsLeft) ?> left
                        <?php } ?>
                    </p>
                    <?php if ($seatLimit !== null && $seatLimit > 0) { ?>
                        <div class="workspace-progress"><span style="width: <?= $seatUsagePct ?>%;"></span></div>
                    <?php } ?>
                </article>

                <article class="workspace-stat-card">
                    <div class="workspace-stat-head">
                        <span class="workspace-stat-label">Pending Invites</span>
                        <span class="workspace-stat-icon"><i class="fa fa-user-plus"></i></span>
                    </div>
                    <div class="workspace-stat-value"><?= $pendingInvites ?></div>
                    <p class="workspace-stat-hint">Pending invites do not consume seats until accepted.</p>
                </article>

                <article class="workspace-stat-card">
                    <div class="workspace-stat-head">
                        <span class="workspace-stat-label">Trial Ends</span>
                        <span class="workspace-stat-icon"><i class="fa fa-hourglass-half"></i></span>
                    </div>
                    <div class="workspace-stat-value compact"><?= wb_format_datetime($subscription['trial_ends_at'] ?? null) ?></div>
                    <?php $trialLeft = wb_days_left_text($subscription['trial_ends_at'] ?? null); ?>
                    <?php if ($trialLeft !== null) { ?>
                        <p class="workspace-stat-hint"><?= htmlspecialchars($trialLeft) ?></p>
                    <?php } ?>
                </article>

                <article class="workspace-stat-card">
                    <div class="workspace-stat-head">
                        <span class="workspace-stat-label">Current Period End</span>
                        <span class="workspace-stat-icon"><i class="fa fa-calendar"></i></span>
                    </div>
                    <div class="workspace-stat-value compact"><?= wb_format_datetime($subscription['current_period_end'] ?? null) ?></div>
                    <?php $periodLeft = wb_days_left_text($subscription['current_period_end'] ?? null); ?>
                    <?php if ($periodLeft !== null) { ?>
                        <p class="workspace-stat-hint"><?= htmlspecialchars($periodLeft) ?></p>
                    <?php } ?>
                </article>

                <article class="workspace-stat-card">
                    <div class="workspace-stat-head">
                        <span class="workspace-stat-label">Workspace Created</span>
                        <span class="workspace-stat-icon"><i class="fa fa-flag"></i></span>
                    </div>
                    <div class="workspace-stat-value compact"><?= wb_format_datetime($org['created_at'] ?? null) ?></div>
                    <p class="workspace-stat-hint">Use this timestamp for onboarding and trial tracking.</p>
                </article>
            </div>

            <div class="workspace-split-grid">
                <section class="workspace-panel">
                    <div class="workspace-panel-head">
                        <div>
                            <h3 class="workspace-panel-title">Plan & Seat Capacity</h3>
                            <p class="workspace-panel-sub">Seat capacity is fixed by plan. Choose a plan to change workspace limits.</p>
                        </div>
                    </div>

                    <div class="workspace-plan-picker">
                        <p class="workspace-panel-sub">Choose a plan to automatically set seat capacity for this workspace.</p>
                        <div class="workspace-plan-grid">
                            <?php foreach ($availablePlans as $plan) {
                                $planCode = (string)($plan['code'] ?? '');
                                $planName = (string)($plan['name'] ?? $planCode);
                                $planSummary = (string)($plan['summary'] ?? '');
                                $planSeatLimit = (int)($plan['seat_limit'] ?? 0);
                                $isCurrentPlan = strtolower($planCode) === strtolower($currentWorkspacePlanCode);
                            ?>
                                <article class="workspace-plan-card <?= $isCurrentPlan ? 'is-current' : '' ?>">
                                    <div class="workspace-plan-head">
                                        <strong><?= htmlspecialchars($planName) ?></strong>
                                        <?php if ($isCurrentPlan) { ?>
                                            <span class="workspace-pill soft">Current</span>
                                        <?php } ?>
                                    </div>
                                    <div class="workspace-plan-seats"><?= $planSeatLimit ?></div>
                                    <p class="workspace-stat-hint"><?= htmlspecialchars($planSummary) ?></p>

                                    <?php if ($canManageSeats) { ?>
                                        <form action="app/select-workspace-plan.php" method="POST" class="workspace-inline-form">
                                            <?= csrf_field('workspace_plan_select_form') ?>
                                            <input type="hidden" name="plan_code" value="<?= htmlspecialchars($planCode) ?>">
                                            <button class="workspace-btn <?= $isCurrentPlan ? 'ghost' : 'primary' ?> mini" type="submit" <?= $isCurrentPlan ? 'disabled' : '' ?>>
                                                <?php if ($isCurrentPlan) { ?>
                                                    Current Plan
                                                <?php } else { ?>
                                                    Choose <?= htmlspecialchars($planName) ?>
                                                <?php } ?>
                                            </button>
                                        </form>
                                    <?php } ?>
                                </article>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="workspace-dummy-billing">
                        <div class="workspace-panel-head">
                            <div>
                                <h4 class="workspace-panel-title">Temporary Dummy Checkout</h4>
                                <p class="workspace-panel-sub">Sandbox-only payment simulation for local testing. No real charge is made.</p>
                            </div>
                            <span class="workspace-pill soft">Demo</span>
                        </div>

                        <div class="workspace-dummy-summary">
                            <span>Current Plan</span>
                            <strong><?= htmlspecialchars($currentWorkspacePlanName) ?></strong>
                            <small>Simulated amount: PHP <?= number_format($currentPlanPrice) ?> / month</small>
                        </div>

                        <?php if ($canManageSeats) { ?>
                            <form action="app/process-dummy-payment.php" method="POST" class="workspace-form-grid two-col">
                                <?= csrf_field('workspace_dummy_payment_form') ?>

                                <div class="workspace-field">
                                    <label for="dummy_payment_method">Demo Payment Method</label>
                                    <select id="dummy_payment_method" name="payment_method" class="workspace-input" required>
                                        <option value="gcash">GCash (Demo)</option>
                                        <option value="card">Credit/Debit Card (Demo)</option>
                                        <option value="bank_transfer">Bank Transfer (Demo)</option>
                                        <option value="over_the_counter">Over the Counter (Demo)</option>
                                    </select>
                                </div>

                                <div class="workspace-field">
                                    <label for="dummy_reference_note">Reference Note (Optional)</label>
                                    <input
                                        id="dummy_reference_note"
                                        type="text"
                                        name="reference_note"
                                        maxlength="80"
                                        class="workspace-input"
                                        placeholder="Example: OR-1001"
                                    >
                                </div>

                                <div class="workspace-action-row">
                                    <button class="workspace-btn primary" type="submit">
                                        <i class="fa fa-check-circle"></i>
                                        Simulate Payment & Activate
                                    </button>
                                </div>
                            </form>
                        <?php } else { ?>
                            <div class="workspace-alert info">
                                <i class="fa fa-lock"></i>
                                <div>You currently have read-only access and cannot run dummy checkout.</div>
                            </div>
                        <?php } ?>
                    </div>

                    <?php if (!$canManageSeats) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-lock"></i>
                            <div>You currently have read-only access for workspace billing settings.</div>
                        </div>
                    <?php } ?>
                </section>

                <section class="workspace-panel">
                    <div class="workspace-panel-head">
                        <div>
                            <h3 class="workspace-panel-title">How Billing Works</h3>
                            <p class="workspace-panel-sub">Quick guide to how limits and invites interact.</p>
                        </div>
                    </div>
                    <ol class="workspace-list">
                        <li><span class="workspace-step">1</span><span>Your workspace always has a subscription state and a plan-defined seat limit.</span></li>
                        <li><span class="workspace-step">2</span><span>Each active member consumes exactly one seat.</span></li>
                        <li><span class="workspace-step">3</span><span>Changing plan automatically updates seat capacity for the workspace.</span></li>
                        <li><span class="workspace-step">4</span><span>If seats are full or billing status is blocked, new joins are prevented automatically.</span></li>
                    </ol>
                </section>
            </div>
        <?php } ?>
    </div>
</div>
</body>
</html>
