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
    <style>
        .wb-card {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .wb-title {
            margin: 0;
            font-size: 24px;
            color: #111827;
        }
        .wb-sub {
            margin: 6px 0 0;
            color: #6B7280;
            font-size: 13px;
        }
        .wb-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(240px, 1fr));
            gap: 14px;
        }
        .wb-stat {
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 14px;
            background: #F9FAFB;
        }
        .wb-label {
            color: #6B7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 6px;
        }
        .wb-value {
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
        }
        .wb-hint {
            margin-top: 6px;
            color: #6B7280;
            font-size: 12px;
        }
        .wb-pill {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .wb-pill.ok {
            background: #DCFCE7;
            color: #166534;
        }
        .wb-pill.warn {
            background: #FEF3C7;
            color: #92400E;
        }
        .wb-pill.danger {
            background: #FEE2E2;
            color: #991B1B;
        }
        .wb-alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .wb-alert.error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }
        .wb-alert.warn {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            color: #92400E;
        }
        .wb-alert.success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }
        .wb-alert.info {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            color: #1E40AF;
        }
        .wb-progress {
            margin-top: 8px;
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: #E5E7EB;
            overflow: hidden;
        }
        .wb-progress > span {
            display: block;
            height: 100%;
            background: #4F46E5;
        }
        .wb-list {
            margin: 0;
            padding-left: 18px;
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
        }
        .wb-form-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: end;
            margin-top: 10px;
        }
        .wb-input-label {
            display: block;
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .4px;
            font-weight: 600;
        }
        .wb-input {
            width: 100%;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .wb-input:focus {
            border-color: #4F46E5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .wb-btn {
            border: none;
            border-radius: 8px;
            background: #4F46E5;
            color: #fff;
            padding: 11px 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .wb-btn:hover {
            background: #4338CA;
        }
        @media (max-width: 900px) {
            .wb-grid {
                grid-template-columns: 1fr;
            }
            .wb-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <div class="wb-card">
        <p class="wb-sub">
            One place to see your plan status, seat usage, and renewal/trial dates.
        </p>
    </div>

    <?php if ($isSuperAdmin) { ?>
        <div class="wb-alert info">
            You are signed in as Super Admin. You are currently viewing one workspace context.
        </div>
    <?php } ?>

    <?php if (!empty($flashSuccess)) { ?>
        <div class="wb-alert success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php } ?>

    <?php if (!empty($flashError)) { ?>
        <div class="wb-alert error"><?= htmlspecialchars($flashError) ?></div>
    <?php } ?>

    <?php if ($error !== null) { ?>
        <div class="wb-alert error"><?= htmlspecialchars($error) ?></div>
    <?php } else { ?>
        <div class="wb-card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <div>
                    <div style="font-size:22px; font-weight:700; color:#111827;"><?= htmlspecialchars((string)$org['name']) ?></div>
                    <div class="wb-sub">
                        slug: <?= htmlspecialchars((string)$org['slug']) ?> | org_id: <?= (int)$org['id'] ?>
                    </div>
                </div>
                <div class="wb-pill <?= wb_status_badge_class($subscription['status'] ?? 'active') ?>">
                    <?= strtoupper((string)($subscription['status'] ?? 'ACTIVE')) ?>
                </div>
            </div>
            <div class="wb-sub" style="margin-top:8px;">
                Workspace status: <strong><?= htmlspecialchars((string)$org['status']) ?></strong> |
                Plan code: <strong><?= htmlspecialchars((string)$org['plan_code']) ?></strong> |
                Billing email: <strong><?= htmlspecialchars((string)($org['billing_email'] ?: 'N/A')) ?></strong>
            </div>
        </div>

        <?php if ($capacity && !$capacity['ok']) { ?>
            <div class="wb-alert warn">
                <?= htmlspecialchars((string)$capacity['reason']) ?>
            </div>
        <?php } ?>

        <div class="wb-grid">
            <div class="wb-stat">
                <div class="wb-label">Seats</div>
                <div class="wb-value">
                    <?= $seatUsed ?><?= $seatLimit !== null ? "/" . $seatLimit : "" ?>
                </div>
                <div class="wb-hint">
                    Used <?= $seatUsed ?> seat<?= $seatUsed !== 1 ? 's' : '' ?>
                    <?php if ($seatsLeft !== null) { ?>
                        , <?= max(0, $seatsLeft) ?> left
                    <?php } ?>
                </div>
                <?php if ($seatLimit !== null && $seatLimit > 0) { ?>
                    <div class="wb-progress"><span style="width: <?= $seatUsagePct ?>%;"></span></div>
                <?php } ?>
            </div>

            <div class="wb-stat">
                <div class="wb-label">Pending Invites</div>
                <div class="wb-value"><?= $pendingInvites ?></div>
                <div class="wb-hint">Pending invites do not consume seats until accepted.</div>
            </div>

            <div class="wb-stat">
                <div class="wb-label">Trial Ends</div>
                <div class="wb-value" style="font-size:18px;"><?= wb_format_datetime($subscription['trial_ends_at'] ?? null) ?></div>
                <?php $trialLeft = wb_days_left_text($subscription['trial_ends_at'] ?? null); ?>
                <?php if ($trialLeft !== null) { ?>
                    <div class="wb-hint"><?= htmlspecialchars($trialLeft) ?></div>
                <?php } ?>
            </div>

            <div class="wb-stat">
                <div class="wb-label">Current Period End</div>
                <div class="wb-value" style="font-size:18px;"><?= wb_format_datetime($subscription['current_period_end'] ?? null) ?></div>
                <?php $periodLeft = wb_days_left_text($subscription['current_period_end'] ?? null); ?>
                <?php if ($periodLeft !== null) { ?>
                    <div class="wb-hint"><?= htmlspecialchars($periodLeft) ?></div>
                <?php } ?>
            </div>

            <div class="wb-stat">
                <div class="wb-label">Member Roles</div>
                <div class="wb-value" style="font-size:18px;">
                    Owners <?= $ownerCount ?> | Admins <?= $adminCount ?> | Members <?= $memberCount ?>
                </div>
                <div class="wb-hint">Members are counted from workspace membership records.</div>
            </div>

            <div class="wb-stat">
                <div class="wb-label">Workspace Created</div>
                <div class="wb-value" style="font-size:18px;"><?= wb_format_datetime($org['created_at'] ?? null) ?></div>
                <div class="wb-hint">Use this for onboarding and trial tracking.</div>
            </div>
        </div>

        <div class="wb-card" style="margin-top: 16px;">
            <h3 style="margin:0 0 6px; font-size:18px; color:#111827;">Manage Seats (Manual)</h3>
            <p class="wb-sub" style="margin:0;">
                Use this when you manually upgrade/downgrade a workspace plan and need to change how many users can join.
            </p>

            <?php if (!$canManageSeats) { ?>
                <div class="wb-alert info" style="margin-top:12px;">
                    You currently have read-only access for workspace billing settings.
                </div>
            <?php } else { ?>
                <?php
                    $currentSeatLimit = ($seatLimit !== null && $seatLimit > 0) ? $seatLimit : 10;
                    $minSeatLimit = max(1, $seatUsed);
                ?>
                <form action="app/update-workspace-seat-limit.php" method="POST" style="margin-top:12px;">
                    <?= csrf_field('workspace_seat_limit_form') ?>
                    <div class="wb-form-grid">
                        <div>
                            <label class="wb-input-label">Seat Limit</label>
                            <input
                                class="wb-input"
                                type="number"
                                name="seat_limit"
                                min="<?= $minSeatLimit ?>"
                                max="5000"
                                value="<?= $currentSeatLimit ?>"
                                required
                            >
                            <div class="wb-hint">
                                Current members: <?= $seatUsed ?>.
                                Minimum allowed right now: <?= $minSeatLimit ?> (you cannot set below active members).
                            </div>
                        </div>
                        <div>
                            <button class="wb-btn" type="submit">
                                <i class="fa fa-save"></i> Update Seats
                            </button>
                        </div>
                    </div>
                </form>
            <?php } ?>
        </div>

        <div class="wb-card" style="margin-top: 16px;">
            <h3 style="margin:0 0 10px; font-size:18px; color:#111827;">How This Works (Simple)</h3>
            <ol class="wb-list">
                <li>Your workspace has a subscription status and a seat limit.</li>
                <li>Each user in your workspace consumes one seat.</li>
                <li>You can invite users only when there are seats left and the plan/trial is active.</li>
                <li>If seats are full or trial/plan is inactive, new joins are blocked automatically.</li>
            </ol>
        </div>
    <?php } ?>
</div>
</body>
</html>
