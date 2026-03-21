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
require_once "inc/workspace_theme.php";

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

$themeDefaults = workspace_theme_default_palette();
$themeReady = $tenantEnabled && workspace_theme_schema_ready($pdo);
$themeModeReady = $tenantEnabled && workspace_theme_mode_schema_ready($pdo);
$themePrimary = $themeDefaults['primary'];
$themeSecondary = $themeDefaults['secondary'];
$themeAccent = $themeDefaults['accent'];
$themeMode = workspace_theme_default_mode();
$themeHasCustom = false;

if ($themeReady && $orgId) {
    $themeValues = workspace_theme_fetch($pdo, $orgId);
    if ($themeValues) {
        if (!empty($themeValues['primary'])) {
            $themePrimary = $themeValues['primary'];
            $themeHasCustom = true;
        }
        if (!empty($themeValues['secondary'])) {
            $themeSecondary = $themeValues['secondary'];
            $themeHasCustom = true;
        }
        if (!empty($themeValues['accent'])) {
            $themeAccent = $themeValues['accent'];
            $themeHasCustom = true;
        }
        if (($themeValues['mode'] ?? workspace_theme_default_mode()) !== workspace_theme_default_mode()) {
            $themeMode = workspace_theme_resolve_mode($themeValues['mode'] ?? workspace_theme_default_mode());
            $themeHasCustom = true;
        }
    }
}

$themeAccentLight = workspace_theme_mix_hex($themeAccent, '#ffffff', 0.86) ?: $themeDefaults['accent'];
$canManageTheme = $canManageSeats;
$canManageThemeMode = $canManageTheme && $themeModeReady;

$themePalettes = workspace_theme_preset_palettes();

$workspaceDisplayName = (string)($org['name'] ?? ($_SESSION['organization_name'] ?? 'Workspace'));
$workspacePlanCode = (string)($org['plan_code'] ?? 'N/A');
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
                    <strong><?= htmlspecialchars($subscriptionStateLabel) ?></strong>
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

            <section class="workspace-panel billing-v2-card workspace-theme-card">
                <div class="workspace-panel-head billing-v2-panel-head">
                    <div>
                        <h3 class="workspace-panel-title">Workspace Theme</h3>
                        <p class="workspace-panel-sub">Customize your workspace UI palette and switch between light and dark workspace modes.</p>
                    </div>
                    <?php if (!$canManageTheme) { ?>
                        <span class="billing-v2-readonly-pill">Read-only</span>
                    <?php } elseif ($themeHasCustom) { ?>
                        <span class="workspace-pill soft">Custom</span>
                    <?php } ?>
                </div>

                <?php if (!$themeReady) { ?>
                    <div class="workspace-alert warn">
                        <i class="fa fa-warning"></i>
                        <div>Theme customization requires the workspace theme columns. Run <span class="workspace-inline-code">sql_add_workspace_theme.sql</span> to enable it.</div>
                    </div>
                <?php } else { ?>
                    <div class="workspace-theme-preview">
                        <div class="workspace-theme-swatch" id="themePreviewPrimary" style="background: <?= htmlspecialchars($themePrimary, ENT_QUOTES) ?>"></div>
                        <div class="workspace-theme-swatch" id="themePreviewSecondary" style="background: <?= htmlspecialchars($themeSecondary, ENT_QUOTES) ?>"></div>
                        <div class="workspace-theme-swatch" id="themePreviewAccent" style="background: <?= htmlspecialchars($themeAccent, ENT_QUOTES) ?>"></div>
                        <div class="workspace-theme-gradient" id="themePreviewGradient" style="background: linear-gradient(135deg, <?= htmlspecialchars($themePrimary, ENT_QUOTES) ?> 0%, <?= htmlspecialchars($themeSecondary, ENT_QUOTES) ?> 100%);"></div>
                        <span class="workspace-theme-note">Applies immediately across this workspace.</span>
                    </div>

                    <?php if (!$themeModeReady) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-moon-o"></i>
                            <div>Dark mode needs the <span class="workspace-inline-code">theme_mode</span> column. Run <span class="workspace-inline-code">sql_add_workspace_theme_mode.sql</span> if your workspace already has the original theme columns.</div>
                        </div>
                    <?php } ?>

                    <div class="workspace-theme-palette-grid" id="workspaceThemePaletteGrid">
                        <?php foreach ($themePalettes as $palette) {
                            $pName = (string)($palette['name'] ?? 'Palette');
                            $pPrimary = (string)($palette['primary'] ?? '');
                            $pSecondary = (string)($palette['secondary'] ?? '');
                            $pAccent = (string)($palette['accent'] ?? '');
                            $pMode = workspace_theme_resolve_mode($palette['mode'] ?? workspace_theme_default_mode());
                        ?>
                            <button
                                type="button"
                                class="workspace-theme-palette"
                                data-primary="<?= htmlspecialchars($pPrimary, ENT_QUOTES) ?>"
                                data-secondary="<?= htmlspecialchars($pSecondary, ENT_QUOTES) ?>"
                                data-accent="<?= htmlspecialchars($pAccent, ENT_QUOTES) ?>"
                                data-mode="<?= htmlspecialchars($pMode, ENT_QUOTES) ?>"
                                <?= $canManageTheme ? '' : 'disabled' ?>
                            >
                                <span class="workspace-theme-palette-name"><?= htmlspecialchars($pName) ?></span>
                                <span class="workspace-theme-palette-swatches">
                                    <span class="workspace-theme-palette-swatch" style="background: <?= htmlspecialchars($pPrimary, ENT_QUOTES) ?>"></span>
                                    <span class="workspace-theme-palette-swatch" style="background: <?= htmlspecialchars($pSecondary, ENT_QUOTES) ?>"></span>
                                    <span class="workspace-theme-palette-swatch" style="background: <?= htmlspecialchars($pAccent, ENT_QUOTES) ?>"></span>
                                </span>
                            </button>
                        <?php } ?>
                    </div>

                    <form action="app/update-workspace-theme.php" method="POST" class="workspace-form-grid two-col">
                        <?= csrf_field('workspace_theme_form') ?>

                        <div class="workspace-field">
                            <label for="theme_primary">Primary Color</label>
                            <input
                                type="color"
                                id="theme_primary"
                                name="theme_primary"
                                class="workspace-input workspace-color-input"
                                value="<?= htmlspecialchars($themePrimary, ENT_QUOTES) ?>"
                                <?= $canManageTheme ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="theme_secondary">Secondary Color</label>
                            <input
                                type="color"
                                id="theme_secondary"
                                name="theme_secondary"
                                class="workspace-input workspace-color-input"
                                value="<?= htmlspecialchars($themeSecondary, ENT_QUOTES) ?>"
                                <?= $canManageTheme ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="theme_accent">Accent Color</label>
                            <input
                                type="color"
                                id="theme_accent"
                                name="theme_accent"
                                class="workspace-input workspace-color-input"
                                value="<?= htmlspecialchars($themeAccent, ENT_QUOTES) ?>"
                                <?= $canManageTheme ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="theme_mode">Workspace Mode</label>
                            <select
                                id="theme_mode"
                                name="theme_mode"
                                class="workspace-input"
                                <?= $canManageThemeMode ? '' : 'disabled' ?>
                            >
                                <option value="light" <?= $themeMode === 'light' ? 'selected' : '' ?>>Light</option>
                                <option value="dark" <?= $themeMode === 'dark' ? 'selected' : '' ?>>Dark</option>
                            </select>
                        </div>

                        <div class="workspace-field">
                            <label>Accent Preview</label>
                            <div class="workspace-input" id="themePreviewAccentSoft" style="background: <?= htmlspecialchars($themeAccentLight, ENT_QUOTES) ?>; border-color: <?= htmlspecialchars($themeMode === 'dark' ? '#334155' : '#e5e7eb', ENT_QUOTES) ?>; height: 42px; padding: 0;"></div>
                        </div>

                        <div class="workspace-action-row" style="grid-column: 1 / -1;">
                            <button class="workspace-btn primary" type="submit" name="theme_action" value="save" <?= $canManageTheme ? '' : 'disabled' ?>>
                                <i class="fa fa-paint-brush"></i>
                                Save Theme
                            </button>
                            <button class="workspace-btn ghost" type="submit" name="theme_action" value="reset" <?= $canManageTheme ? '' : 'disabled' ?>>
                                Reset to Default
                            </button>
                        </div>
                    </form>

                    <?php if (!$canManageTheme) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-lock"></i>
                            <div>You currently have read-only access and cannot update the workspace theme.</div>
                        </div>
                    <?php } elseif (!$themeModeReady) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-database"></i>
                            <div>Color palettes are ready. Run <span class="workspace-inline-code">sql_add_workspace_theme_mode.sql</span> once to save dark mode selections too.</div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
    <script>
    (function () {
        var primaryInput = document.getElementById('theme_primary');
        var secondaryInput = document.getElementById('theme_secondary');
        var accentInput = document.getElementById('theme_accent');
        var modeInput = document.getElementById('theme_mode');
        var previewPrimary = document.getElementById('themePreviewPrimary');
        var previewSecondary = document.getElementById('themePreviewSecondary');
        var previewAccent = document.getElementById('themePreviewAccent');
        var previewAccentSoft = document.getElementById('themePreviewAccentSoft');
        var previewGradient = document.getElementById('themePreviewGradient');
        var paletteButtons = Array.prototype.slice.call(document.querySelectorAll('.workspace-theme-palette'));

        if (!primaryInput || !secondaryInput || !accentInput) return;

        function toLowerHex(value) {
            return String(value || '').trim().toLowerCase();
        }

        function updatePreview() {
            var primary = primaryInput.value || '';
            var secondary = secondaryInput.value || '';
            var accent = accentInput.value || '';
            var mode = modeInput ? String(modeInput.value || 'light').toLowerCase() : 'light';

            if (previewPrimary) previewPrimary.style.background = primary;
            if (previewSecondary) previewSecondary.style.background = secondary;
            if (previewAccent) previewAccent.style.background = accent;
            if (previewGradient) {
                previewGradient.style.background = 'linear-gradient(135deg, ' + primary + ' 0%, ' + secondary + ' 100%)';
            }

            if (previewAccentSoft) {
                previewAccentSoft.style.background = accent ? accent + '22' : '';
                previewAccentSoft.style.borderColor = mode === 'dark' ? '#334155' : '#e5e7eb';
            }
        }

        function setActivePalette(activeBtn) {
            paletteButtons.forEach(function (btn) {
                if (btn === activeBtn) {
                    btn.classList.add('is-active');
                } else {
                    btn.classList.remove('is-active');
                }
            });
        }

        function syncActivePalette() {
            var current = {
                primary: toLowerHex(primaryInput.value),
                secondary: toLowerHex(secondaryInput.value),
                accent: toLowerHex(accentInput.value),
                mode: modeInput ? String(modeInput.value || 'light').toLowerCase() : 'light'
            };

            var matched = false;
            paletteButtons.forEach(function (btn) {
                var matches = toLowerHex(btn.getAttribute('data-primary')) === current.primary
                    && toLowerHex(btn.getAttribute('data-secondary')) === current.secondary
                    && toLowerHex(btn.getAttribute('data-accent')) === current.accent
                    && (!modeInput || modeInput.disabled || String(btn.getAttribute('data-mode') || 'light').toLowerCase() === current.mode);
                if (matches && !matched) {
                    setActivePalette(btn);
                    matched = true;
                } else if (!matches) {
                    btn.classList.remove('is-active');
                }
            });
            if (!matched) {
                setActivePalette(null);
            }
        }

        paletteButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var nextPrimary = btn.getAttribute('data-primary') || '';
                var nextSecondary = btn.getAttribute('data-secondary') || '';
                var nextAccent = btn.getAttribute('data-accent') || '';
                var nextMode = btn.getAttribute('data-mode') || 'light';
                primaryInput.value = nextPrimary;
                secondaryInput.value = nextSecondary;
                accentInput.value = nextAccent;
                if (modeInput && !modeInput.disabled) {
                    modeInput.value = nextMode;
                }
                setActivePalette(btn);
                updatePreview();
            });
        });

        [primaryInput, secondaryInput, accentInput, modeInput].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                updatePreview();
                syncActivePalette();
            });
        });

        updatePreview();
        syncActivePalette();
    })();
    </script>
</div>
</body>
</html>
