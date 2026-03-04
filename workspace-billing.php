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

function wb_format_short_date($value)
{
    if (empty($value)) {
        return "N/A";
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return "N/A";
    }
    return date("M j, Y", $ts);
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
$subscriptionStatusRaw = strtolower(trim((string)($subscription['status'] ?? '')));
$trialEndsAtRaw = (string)($subscription['trial_ends_at'] ?? '');
$trialEndsTs = $trialEndsAtRaw !== '' ? strtotime($trialEndsAtRaw) : false;
$isFreeTrialStatus = in_array($subscriptionStatusRaw, ['trialing', 'trial'], true);
$isFreeTrialExpired = $isFreeTrialStatus && $trialEndsTs !== false && $trialEndsTs <= time();
$subscriptionStatusText = strtoupper((string)($subscription['status'] ?? 'N/A'));
if ($isFreeTrialStatus) {
    $subscriptionStatusText = $isFreeTrialExpired ? 'FREE TRIAL EXPIRED' : 'FREE TRIAL';
}
$subscriptionBadgeClass = wb_status_badge_class($subscription['status'] ?? 'active');
if ($isFreeTrialExpired) {
    $subscriptionBadgeClass = 'warn';
}
$seatUsageDisplay = $seatLimit !== null ? ($seatUsed . "/" . $seatLimit) : (string)$seatUsed;
$availablePlans = tenant_workspace_plan_catalog();
$resolvedWorkspacePlan = tenant_resolve_workspace_plan($workspacePlanCode, 'starter');
$currentWorkspacePlanCode = (string)($resolvedWorkspacePlan['code'] ?? 'starter');
$currentWorkspacePlanName = (string)($resolvedWorkspacePlan['name'] ?? 'Starter');
$dummyPlanPrices = [
    'starter' => 399,
    'professional' => 799,
    'enterprise' => 1599,
];
$currentPlanPrice = (int)($dummyPlanPrices[strtolower($currentWorkspacePlanCode)] ?? $dummyPlanPrices['starter']);
$periodEndsAtRaw = (string)($subscription['current_period_end'] ?? '');
$periodEndsShort = wb_format_short_date($periodEndsAtRaw);
$trialEndsShort = wb_format_short_date($subscription['trial_ends_at'] ?? null);
$workspaceCreatedShort = wb_format_short_date($org['created_at'] ?? null);
$periodLeftText = wb_days_left_text($subscription['current_period_end'] ?? null);
$trialLeftText = wb_days_left_text($subscription['trial_ends_at'] ?? null);
$isTrialDateExpired = $trialEndsTs !== false && $trialEndsTs <= time();
$blockedStatuses = ['canceled', 'cancelled', 'suspended', 'inactive', 'unpaid', 'incomplete', 'incomplete_expired', 'paused'];
$isSubscriptionBlocked = in_array($subscriptionStatusRaw, $blockedStatuses, true) || $isFreeTrialExpired;
$isFreeTrialActive = $isFreeTrialStatus && !$isFreeTrialExpired;
$subscriptionStateLabel = $isSubscriptionBlocked
    ? ($isFreeTrialExpired ? 'FREE TRIAL EXPIRED' : 'EXPIRED')
    : ($isFreeTrialActive ? 'FREE TRIAL' : (in_array($subscriptionStatusRaw, ['active'], true) ? 'ACTIVE' : strtoupper((string)$subscriptionStatusText)));
$heroSubscriptionValueClass = $isFreeTrialStatus
    ? ($isFreeTrialExpired ? 'billing-v2-value-warn' : 'billing-v2-value-trial')
    : ($isSubscriptionBlocked ? 'billing-v2-value-warn' : 'billing-v2-value-ok');
$snapshotBadgeClass = $isFreeTrialStatus
    ? ($isFreeTrialExpired ? 'is-expired' : 'is-trial')
    : ($isSubscriptionBlocked ? 'is-expired' : 'is-active');
$snapshotBadgeLabel = $isFreeTrialStatus
    ? ($isFreeTrialExpired ? 'Trial Expired' : 'Free Trial')
    : ($isSubscriptionBlocked ? 'Expired' : 'Active');
$displayPlanCode = $isFreeTrialStatus ? 'free_trial' : $currentWorkspacePlanCode;
$showTrialStatusCard = $isFreeTrialStatus || $isSubscriptionBlocked;
$trialCardIsAlert = $isSubscriptionBlocked || $isFreeTrialExpired;
$expiredReferenceDate = $trialEndsShort !== 'N/A' ? $trialEndsShort : $periodEndsShort;
$billingLockReason = '';
if ($isFreeTrialExpired) {
    $billingLockReason = "New member joins are blocked. Existing members retain read-only access. Renew below to restore full workspace access.";
} elseif ($isSubscriptionBlocked) {
    $billingLockReason = "New member joins are blocked. Existing members retain read-only access. Complete renewal below to restore full workspace access.";
}
$planPanelTitle = $isSubscriptionBlocked ? 'Renew Your Subscription' : 'Plan & Seat Capacity';
$planPanelSub = $isSubscriptionBlocked
    ? 'Choose a plan below to reactivate your workspace and restore full member access.'
    : 'Seat capacity is fixed by plan. Switch plans to change workspace limits.';
$checkoutTitle = $isSubscriptionBlocked ? 'Reactivation Checkout' : 'Demo Checkout';
$checkoutSub = $isSubscriptionBlocked
    ? 'Complete payment to immediately restore workspace access.'
    : 'Sandbox-only payment simulation. No real charge is made.';
$checkoutButtonLabel = $isSubscriptionBlocked ? 'Reactivate Workspace Now' : 'Simulate Payment & Activate';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Billing | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/workspace-panels.css">
</head>
<body class="billing-page">
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <div class="workspace-shell workspace-animate billing-v2-shell">
        <?php if ($isSubscriptionBlocked && $billingLockReason !== '') { ?>
            <div class="billing-v2-expired-banner">
                <span class="billing-v2-expired-icon"><i class="fa fa-exclamation-triangle"></i></span>
                <div class="billing-v2-expired-body">
                    <p class="billing-v2-expired-title">Your subscription has expired</p>
                    <p class="billing-v2-expired-sub"><?= htmlspecialchars($billingLockReason) ?></p>
                </div>
                <a class="billing-v2-expired-cta" href="#billingCheckout">Renew Now <i class="fa fa-arrow-right"></i></a>
            </div>
        <?php } ?>

        <section class="workspace-hero billing-v2-hero <?= $isSubscriptionBlocked ? 'is-expired' : '' ?>">
            <div class="billing-v2-hero-left">
                <span class="workspace-eyebrow billing-v2-eyebrow">
                    <i class="fa fa-credit-card"></i> Workspace Billing
                </span>
                <h2>
                    <?php if ($isSubscriptionBlocked) { ?>
                        Your subscription has expired.
                    <?php } else { ?>
                        Control seats, monitor renewal windows, and keep onboarding stable.
                    <?php } ?>
                </h2>
                <p>
                    <?php if ($isSubscriptionBlocked) { ?>
                        New joins are blocked and team invites are paused. Reactivate your plan to restore full access.
                    <?php } else { ?>
                        One place to view plan health, active capacity, and role distribution.
                    <?php } ?>
                </p>
            </div>
            <div class="workspace-hero-stats billing-v2-hero-stats">
                <div class="workspace-hero-stat billing-v2-hero-stat">
                    <span>Workspace</span>
                    <strong><?= htmlspecialchars($workspaceDisplayName) ?></strong>
                </div>
                <div class="workspace-hero-stat billing-v2-hero-stat">
                    <span>Subscription</span>
                    <strong class="<?= $heroSubscriptionValueClass ?>"><?= htmlspecialchars($subscriptionStateLabel) ?></strong>
                </div>
                <div class="workspace-hero-stat billing-v2-hero-stat">
                    <span>Seats</span>
                    <strong class="<?= $isSubscriptionBlocked ? 'billing-v2-value-muted' : '' ?>">
                        <?= $isSubscriptionBlocked ? 'Locked' : htmlspecialchars($seatUsageDisplay) ?>
                    </strong>
                    <small class="<?= $isSubscriptionBlocked ? 'billing-v2-value-muted' : '' ?>">
                        <?php if ($isSubscriptionBlocked) { ?>
                            Access paused
                        <?php } elseif ($seatsLeft !== null) { ?>
                            <?= max(0, $seatsLeft) ?> seat<?= max(0, $seatsLeft) === 1 ? '' : 's' ?> left
                        <?php } else { ?>
                            Seat limit not configured
                        <?php } ?>
                    </small>
                </div>
                <div class="workspace-hero-stat billing-v2-hero-stat">
                    <span>Pending Invites</span>
                    <strong class="<?= $isSubscriptionBlocked ? 'billing-v2-value-muted' : '' ?>">
                        <?= $isSubscriptionBlocked ? 'Paused' : $pendingInvites ?>
                    </strong>
                    <small class="<?= $isSubscriptionBlocked ? 'billing-v2-value-muted' : '' ?>">
                        <?= $isSubscriptionBlocked ? 'Invites disabled' : 'No seat cost until accepted' ?>
                    </small>
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
            <section class="workspace-panel billing-v2-card billing-v2-snapshot">
                <div class="billing-v2-snapshot-head">
                    <div class="billing-v2-card-title-wrap">
                        <h3 class="workspace-panel-title"><?= htmlspecialchars($workspaceDisplayName) ?></h3>
                        <p class="workspace-panel-sub">Workspace status, plan metadata, and billing contact snapshot.</p>
                    </div>
                    <span class="billing-v2-status-badge <?= $snapshotBadgeClass ?>">
                        <span class="billing-v2-status-dot"></span>
                        <?= htmlspecialchars($snapshotBadgeLabel) ?>
                    </span>
                </div>
                <div class="billing-v2-meta-tags">
                    <span class="billing-v2-meta-chip">
                        <span class="billing-v2-meta-icon">#</span>
                        <span class="billing-v2-meta-label">Org ID</span>
                        <strong><?= $workspaceId ?></strong>
                    </span>
                    <span class="billing-v2-meta-chip">
                        <span class="billing-v2-meta-icon"><i class="fa fa-diamond"></i></span>
                        <span class="billing-v2-meta-label">Slug</span>
                        <strong><?= htmlspecialchars($workspaceSlug) ?></strong>
                    </span>
                    <span class="billing-v2-meta-chip <?= $isSubscriptionBlocked ? 'is-warn' : '' ?>">
                        <span class="billing-v2-meta-icon"><i class="fa fa-shield"></i></span>
                        <span class="billing-v2-meta-label">Status</span>
                        <strong><?= htmlspecialchars($workspaceStatus) ?></strong>
                    </span>
                    <span class="billing-v2-meta-chip">
                        <span class="billing-v2-meta-icon"><i class="fa fa-square-o"></i></span>
                        <span class="billing-v2-meta-label">Plan</span>
                        <strong><?= htmlspecialchars($displayPlanCode) ?></strong>
                    </span>
                    <span class="billing-v2-meta-chip">
                        <span class="billing-v2-meta-icon"><i class="fa fa-envelope-o"></i></span>
                        <span class="billing-v2-meta-label">Billing</span>
                        <strong><?= htmlspecialchars($workspaceBillingEmail) ?></strong>
                    </span>
                </div>
            </section>

            <?php if ($capacity && !$capacity['ok']) { ?>
                <div class="workspace-alert warn billing-v2-capacity-alert">
                    <i class="fa fa-warning"></i>
                    <div><?= htmlspecialchars((string)$capacity['reason']) ?></div>
                </div>
            <?php } ?>

            <div class="billing-v2-stats-grid <?= $showTrialStatusCard ? '' : 'no-trial-card' ?>">
                <article class="billing-v2-stat-card">
                    <div class="billing-v2-stat-top">
                        <div>
                            <span class="billing-v2-stat-label">Seats</span>
                            <div class="billing-v2-stat-value <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>">
                                <?php if ($isSubscriptionBlocked) { ?>
                                    &mdash;
                                <?php } else { ?>
                                    <?= $seatUsed ?><span>/<?= $seatLimit !== null ? $seatLimit : '?' ?></span>
                                <?php } ?>
                            </div>
                            <p class="billing-v2-stat-sub">
                                <?php if ($isSubscriptionBlocked) { ?>
                                    Access suspended
                                <?php } else { ?>
                                    Used <?= $seatUsed ?> &middot; <?= $seatsLeft !== null ? max(0, $seatsLeft) : 'N/A' ?> left
                                <?php } ?>
                            </p>
                        </div>
                        <span class="billing-v2-stat-icon <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>"><i class="fa fa-users"></i></span>
                    </div>
                    <div class="billing-v2-progress-track <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>">
                        <span class="billing-v2-progress-bar <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>" style="width: <?= $isSubscriptionBlocked ? 0 : $seatUsagePct ?>%;"></span>
                    </div>
                </article>

                <article class="billing-v2-stat-card">
                    <div class="billing-v2-stat-top">
                        <div>
                            <span class="billing-v2-stat-label">Pending Invites</span>
                            <div class="billing-v2-stat-value <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>">
                                <?= $isSubscriptionBlocked ? '&mdash;' : (string)$pendingInvites ?>
                            </div>
                            <p class="billing-v2-stat-sub">
                                <?= $isSubscriptionBlocked ? 'Invites paused.' : "Don't consume seats yet." ?>
                            </p>
                        </div>
                        <span class="billing-v2-stat-icon <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>"><i class="fa fa-envelope-o"></i></span>
                    </div>
                </article>

                <?php if ($showTrialStatusCard) { ?>
                    <article class="billing-v2-stat-card <?= $trialCardIsAlert ? 'is-alert' : '' ?>">
                        <div class="billing-v2-stat-top">
                            <div>
                                <span class="billing-v2-stat-label <?= $trialCardIsAlert ? 'is-alert' : '' ?>">
                                    <?= $isFreeTrialStatus ? ($isFreeTrialExpired ? 'Free Trial Expired' : 'Free Trial') : 'Subscription Expired' ?>
                                </span>
                                <div class="billing-v2-stat-value compact <?= $trialCardIsAlert ? 'is-alert-date' : '' ?>">
                                    <?= htmlspecialchars($isFreeTrialStatus ? $trialEndsShort : $expiredReferenceDate) ?>
                                </div>
                                <span class="billing-v2-stat-flag <?= $trialCardIsAlert ? '' : 'is-info' ?>">
                                    <i class="fa <?= $trialCardIsAlert ? 'fa-warning' : 'fa-clock-o' ?>"></i>
                                    <?=
                                        htmlspecialchars(
                                            $trialCardIsAlert
                                                ? ($isFreeTrialExpired ? 'Expired' : 'Renewal required')
                                                : ($trialLeftText ?? 'Active')
                                        )
                                    ?>
                                </span>
                            </div>
                            <span class="billing-v2-stat-icon <?= $trialCardIsAlert ? 'is-alert' : '' ?>"><i class="fa fa-hourglass-half"></i></span>
                        </div>
                    </article>
                <?php } ?>

                <article class="billing-v2-stat-card">
                    <div class="billing-v2-stat-top">
                        <div>
                            <span class="billing-v2-stat-label">Current Period End</span>
                            <div class="billing-v2-stat-value compact <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>">
                                <?php if ($isFreeTrialActive) { ?>
                                    Free Trial
                                <?php } else { ?>
                                    <?= htmlspecialchars($isSubscriptionBlocked ? 'Expired' : $periodEndsShort) ?>
                                <?php } ?>
                            </div>
                            <p class="billing-v2-stat-sub">
                                <?= htmlspecialchars($isFreeTrialActive ? ($trialLeftText ?? 'Trial active') : ($isSubscriptionBlocked ? 'No active period' : ($periodLeftText ?? 'N/A'))) ?>
                            </p>
                        </div>
                        <span class="billing-v2-stat-icon <?= $isSubscriptionBlocked ? 'is-muted' : '' ?>"><i class="fa fa-calendar"></i></span>
                    </div>
                </article>

                <article class="billing-v2-stat-card">
                    <div class="billing-v2-stat-top">
                        <div>
                            <span class="billing-v2-stat-label">Workspace Created</span>
                            <div class="billing-v2-stat-value compact"><?= htmlspecialchars($workspaceCreatedShort) ?></div>
                            <p class="billing-v2-stat-sub">For onboarding and trial tracking.</p>
                        </div>
                        <span class="billing-v2-stat-icon"><i class="fa fa-flag"></i></span>
                    </div>
                </article>
            </div>

            <?php if ($isSubscriptionBlocked) { ?>
                <div class="billing-v2-blocked-note">
                    <i class="fa fa-lock"></i>
                    <span><strong>Member joins are currently blocked.</strong> No new users can join this workspace until the subscription is renewed. Existing members retain read-only access.</span>
                </div>
            <?php } ?>

            <div class="billing-v2-bottom-grid">
                <section class="workspace-panel billing-v2-card billing-v2-plan-card-wrap" id="billingCheckout">
                    <div class="workspace-panel-head billing-v2-panel-head">
                        <div>
                            <h3 class="workspace-panel-title"><?= htmlspecialchars($planPanelTitle) ?></h3>
                            <p class="workspace-panel-sub"><?= htmlspecialchars($planPanelSub) ?></p>
                        </div>
                        <?php if (!$canManageSeats) { ?>
                            <span class="billing-v2-readonly-pill">Read-only</span>
                        <?php } ?>
                    </div>

                    <div class="billing-v2-plan-grid">
                        <?php foreach ($availablePlans as $plan) {
                            $planCode = (string)($plan['code'] ?? '');
                            $planName = (string)($plan['name'] ?? $planCode);
                            $planSummary = (string)($plan['summary'] ?? '');
                            $planSeatLimit = (int)($plan['seat_limit'] ?? 0);
                            $isCurrentPlan = !$isFreeTrialStatus && strtolower($planCode) === strtolower($currentWorkspacePlanCode);
                            $isPopularPlan = strtolower($planCode) === 'professional';
                            $planPrice = (int)($dummyPlanPrices[strtolower($planCode)] ?? $currentPlanPrice);
                            if ($planSummary === '') {
                                $planSummary = 'Up to ' . $planSeatLimit . ' members';
                            }
                        ?>
                            <article class="billing-v2-plan-card <?= $isCurrentPlan ? 'is-current' : '' ?> <?= $isPopularPlan ? 'is-popular' : '' ?>">
                                <?php if ($isPopularPlan) { ?>
                                    <span class="billing-v2-popular-ribbon">Popular</span>
                                <?php } ?>
                                <div class="billing-v2-plan-head">
                                    <strong><?= htmlspecialchars($planName) ?></strong>
                                    <?php if ($isCurrentPlan) { ?>
                                        <span class="billing-v2-current-tag"><?= $isSubscriptionBlocked ? 'Was Plan' : 'Current' ?></span>
                                    <?php } ?>
                                </div>
                                <div class="billing-v2-plan-seats"><?= $planSeatLimit ?></div>
                                <p class="billing-v2-plan-desc"><?= htmlspecialchars($planSummary) ?></p>
                                <p class="billing-v2-plan-price">PHP <?= number_format($planPrice) ?><span>/mo</span></p>

                                <?php if ($canManageSeats) { ?>
                                    <form action="app/select-workspace-plan.php" method="POST" class="workspace-inline-form">
                                        <?= csrf_field('workspace_plan_select_form') ?>
                                        <input type="hidden" name="plan_code" value="<?= htmlspecialchars($planCode) ?>">
                                        <button class="workspace-btn mini billing-v2-plan-btn <?= $isCurrentPlan ? 'is-current' : '' ?>" type="submit" <?= $isCurrentPlan ? 'disabled' : '' ?>>
                                            <?php if ($isCurrentPlan) { ?>
                                                <?= $isSubscriptionBlocked ? 'Was Plan' : 'Current Plan' ?>
                                            <?php } else { ?>
                                                Choose <?= htmlspecialchars($planName) ?>
                                            <?php } ?>
                                        </button>
                                    </form>
                                <?php } ?>
                            </article>
                        <?php } ?>
                    </div>

                    <div class="billing-v2-checkout-box <?= $isSubscriptionBlocked ? 'is-urgent' : '' ?>">
                        <div class="billing-v2-checkout-head">
                            <div>
                                <h4 class="billing-v2-checkout-title"><?= htmlspecialchars($checkoutTitle) ?></h4>
                                <p class="billing-v2-checkout-sub"><?= htmlspecialchars($checkoutSub) ?></p>
                            </div>
                            <span class="billing-v2-checkout-badge <?= $isSubscriptionBlocked ? 'is-urgent' : '' ?>"><?= $isSubscriptionBlocked ? 'RENEWAL' : 'DEMO' ?></span>
                        </div>

                        <div class="billing-v2-checkout-summary">
                            <span class="billing-v2-checkout-summary-label">Selected Plan</span>
                            <?php if ($isFreeTrialActive) { ?>
                                <strong>Free Trial</strong>
                                <small>&middot; 2-day access (no charge)</small>
                            <?php } else { ?>
                                <strong><?= htmlspecialchars($currentWorkspacePlanName) ?></strong>
                                <small>&middot; PHP <?= number_format($currentPlanPrice) ?> / month</small>
                            <?php } ?>
                        </div>

                        <?php if ($canManageSeats) { ?>
                            <form action="app/process-dummy-payment.php" method="POST" class="workspace-form-grid two-col billing-v2-checkout-form">
                                <?= csrf_field('workspace_dummy_payment_form') ?>

                                <div class="workspace-field">
                                    <label for="dummy_payment_method">Payment Method</label>
                                    <select id="dummy_payment_method" name="payment_method" class="workspace-input" required>
                                        <option value="gcash">GCash (Demo)</option>
                                        <option value="card">Credit Card (Demo)</option>
                                        <option value="bank_transfer">Bank Transfer (Demo)</option>
                                        <option value="over_the_counter">Over the Counter (Demo)</option>
                                    </select>
                                </div>

                                <div class="workspace-field">
                                    <label for="dummy_reference_note">Reference Note <span>(optional)</span></label>
                                    <input
                                        id="dummy_reference_note"
                                        type="text"
                                        name="reference_note"
                                        maxlength="80"
                                        class="workspace-input"
                                        placeholder="e.g. OR-1001"
                                    >
                                </div>

                                <div class="workspace-action-row">
                                    <button class="workspace-btn primary billing-v2-sim-btn <?= $isSubscriptionBlocked ? 'is-urgent' : '' ?>" type="submit">
                                        <i class="fa fa-shield"></i>
                                        <?= htmlspecialchars($checkoutButtonLabel) ?>
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
                </section>

                <section class="workspace-panel billing-v2-card billing-v2-how-card">
                    <div class="workspace-panel-head billing-v2-panel-head">
                        <div>
                            <h3 class="workspace-panel-title">How Billing Works</h3>
                            <p class="workspace-panel-sub">Quick guide to how limits and invites interact.</p>
                        </div>
                    </div>
                    <ol class="billing-v2-how-list">
                        <li><span class="billing-v2-how-step">1</span><span>Your workspace always has a subscription state and a plan-defined seat limit.</span></li>
                        <li><span class="billing-v2-how-step">2</span><span>Each active member consumes exactly one seat.</span></li>
                        <li><span class="billing-v2-how-step">3</span><span>Changing plan automatically updates seat capacity for the workspace.</span></li>
                        <li><span class="billing-v2-how-step">4</span><span>If seats are full or billing is blocked, new joins are prevented automatically.</span></li>
                    </ol>
                    <div class="billing-v2-how-divider"></div>
                    <?php if ($isSubscriptionBlocked) { ?>
                        <div class="billing-v2-how-note is-warn">
                            <i class="fa fa-warning"></i>
                            <span>Renewing restores all access instantly. No data is lost during expiry.</span>
                        </div>
                    <?php } else { ?>
                        <div class="billing-v2-how-note is-ok">
                            <i class="fa fa-check-circle"></i>
                            <span>Seat changes take effect immediately after plan activation.</span>
                        </div>
                    <?php } ?>
                </section>
            </div>
        <?php } ?>
    </div>
</div>
</body>
</html>
