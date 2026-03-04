<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] === "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    require_once "inc/tenant.php";
    require_once "inc/csrf.php";
    require_once "app/invite_helpers.php";
    include "app/mail_config.php";

    $is_super_admin = is_super_admin($_SESSION['id'], $pdo);
    $orgId = tenant_get_current_org_id();
    $hasInviteTable = tenant_table_exists($pdo, 'workspace_invites');

    if (!$orgId) {
        header("Location: index.php?error=" . urlencode("Workspace context is missing."));
        exit();
    }

    $capacity = tenant_check_workspace_capacity($pdo, (int)$orgId);
    $seatUsed = (int)($capacity['seat_used'] ?? 0);
    $seatLimit = isset($capacity['seat_limit']) ? (int)$capacity['seat_limit'] : null;
    $seatsLeft = isset($capacity['seats_left']) ? (int)$capacity['seats_left'] : null;
    $subscriptionStatus = isset($capacity['subscription_status']) && $capacity['subscription_status'] !== null
        ? strtoupper((string)$capacity['subscription_status'])
        : 'N/A';

    $orgName = $_SESSION['organization_name'] ?? "Workspace";
    if (tenant_table_exists($pdo, 'organizations')) {
        $stmtOrg = $pdo->prepare("SELECT name FROM organizations WHERE id = ? LIMIT 1");
        $stmtOrg->execute([$orgId]);
        $orgName = $stmtOrg->fetchColumn() ?: $orgName;
    }

    $invites = [];
    if ($hasInviteTable) {
        $expireStmt = $pdo->prepare(
            "UPDATE workspace_invites
             SET status = 'expired'
             WHERE organization_id = ?
               AND status = 'pending'
               AND expires_at <= NOW()"
        );
        $expireStmt->execute([$orgId]);

        $sql = "SELECT wi.id, wi.email, wi.full_name, wi.role, wi.status, wi.token, wi.expires_at, wi.created_at,
                       u.full_name AS invited_by_name
                FROM workspace_invites wi
                LEFT JOIN users u ON u.id = wi.invited_by
                WHERE wi.organization_id = ?
                ORDER BY wi.id DESC
                LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orgId]);
        $invites = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $pendingInviteCount = 0;
    foreach ($invites as $inviteRow) {
        if (strtolower((string)($inviteRow['status'] ?? '')) === 'pending') {
            $pendingInviteCount++;
        }
    }
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invite Users | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/workspace-panels.css">
</head>
<body class="invites-page">
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <div class="workspace-shell workspace-animate">
        <section class="workspace-hero">
            <div>
                <span class="workspace-eyebrow">
                    <i class="fa fa-user-plus"></i> Workspace Invites
                </span>
                <h2>Invite teammates with a cleaner onboarding flow.</h2>
                <p>Send direct invites, run bulk imports, and issue one-time links while keeping workspace capacity visible at a glance.</p>
            </div>
            <div class="workspace-hero-stats">
                <div class="workspace-hero-stat">
                    <span>Workspace</span>
                    <strong><?= htmlspecialchars((string)$orgName) ?></strong>
                </div>
                <div class="workspace-hero-stat">
                    <span>Subscription</span>
                    <strong><?= htmlspecialchars($subscriptionStatus) ?></strong>
                </div>
                <div class="workspace-hero-stat">
                    <span>Seat Usage</span>
                    <strong><?= $seatUsed ?><?= $seatLimit !== null ? "/" . $seatLimit : "" ?></strong>
                    <?php if ($seatsLeft !== null) { ?>
                        <small><?= max(0, $seatsLeft) ?> seat<?= max(0, $seatsLeft) === 1 ? '' : 's' ?> left</small>
                    <?php } ?>
                </div>
                <div class="workspace-hero-stat">
                    <span>Pending Invites</span>
                    <strong><?= (int)$pendingInviteCount ?></strong>
                    <small><?= count($invites) ?> recent invite<?= count($invites) === 1 ? '' : 's' ?> tracked</small>
                </div>
            </div>
        </section>

        <div class="workspace-alert-stack">
            <?php if (isset($_GET['error'])) { ?>
                <div class="workspace-alert error">
                    <i class="fa fa-exclamation-circle"></i>
                    <div><?= htmlspecialchars($_GET['error']) ?></div>
                </div>
            <?php } ?>
            <?php if (isset($_GET['success'])) { ?>
                <div class="workspace-alert success">
                    <i class="fa fa-check-circle"></i>
                    <div><?= htmlspecialchars($_GET['success']) ?></div>
                </div>
            <?php } ?>
            <?php if (isset($_GET['warn'])) { ?>
                <div class="workspace-alert warn">
                    <i class="fa fa-warning"></i>
                    <div><?= htmlspecialchars($_GET['warn']) ?></div>
                </div>
            <?php } ?>
            <?php if (isset($_GET['manual_link'])) { ?>
                <div class="workspace-alert warn">
                    <i class="fa fa-link"></i>
                    <div>
                        Manual invite link generated.
                        <div class="workspace-inline-code"><?= htmlspecialchars($_GET['manual_link']) ?></div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php if (!$hasInviteTable) { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <h3 class="workspace-panel-title">Invite Setup Required</h3>
                </div>
                <div class="workspace-alert error">
                    <i class="fa fa-database"></i>
                    <div>`workspace_invites` table is missing. Run `sql_create_workspace_invites.sql` or `run_migration_workspace_invites.php` first.</div>
                </div>
            </section>
        <?php } elseif ($is_super_admin) { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <h3 class="workspace-panel-title">Invites Unavailable For Super Admin</h3>
                </div>
                <div class="workspace-alert error">
                    <i class="fa fa-ban"></i>
                    <div>Super Admin cannot send workspace invites from this screen.</div>
                </div>
            </section>
        <?php } elseif (!$capacity['ok']) { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <h3 class="workspace-panel-title">Invites Temporarily Locked</h3>
                </div>
                <div class="workspace-alert warn">
                    <i class="fa fa-warning"></i>
                    <div><?= htmlspecialchars((string)$capacity['reason']) ?></div>
                </div>
            </section>
        <?php } else { ?>
            <div class="invite-action-grid">
                <section class="workspace-panel">
                    <div class="workspace-panel-head">
                        <div>
                            <h3 class="workspace-panel-title">Send New Invite</h3>
                            <p class="workspace-panel-sub">Invite a single teammate with full name and work email.</p>
                        </div>
                    </div>
                    <form action="app/invite-user.php" method="POST" class="workspace-form-grid">
                        <?= csrf_field('invite_user_form') ?>
                        <div class="workspace-form-grid two-col">
                            <div class="workspace-field">
                                <label>Employee Full Name</label>
                                <input class="workspace-input" type="text" name="full_name" placeholder="Jane Doe" required>
                            </div>
                            <div class="workspace-field">
                                <label>Employee Email</label>
                                <input class="workspace-input" type="email" name="email" placeholder="jane@company.com" required>
                            </div>
                        </div>
                        <div>
                            <button class="workspace-btn primary" type="submit">
                                <i class="fa fa-paper-plane"></i> Send Invite
                            </button>
                        </div>
                    </form>
                </section>

                <section class="workspace-panel">
                    <div class="workspace-panel-head">
                        <div>
                            <h3 class="workspace-panel-title">Generate One-time Link</h3>
                            <p class="workspace-panel-sub">Create a single-use join URL. The first valid signup consumes it.</p>
                        </div>
                    </div>
                    <form action="app/generate-invite-link.php" method="POST" class="workspace-form-grid">
                        <?= csrf_field('generate_workspace_join_link_form') ?>
                        <div>
                            <button class="workspace-btn primary" type="submit">
                                <i class="fa fa-link"></i> Generate One-time Link
                            </button>
                        </div>
                    </form>
                    <?php if (isset($_GET['one_time_link'])) { ?>
                        <div class="workspace-alert success" style="margin-top:12px;">
                            <i class="fa fa-check-circle-o"></i>
                            <div>
                                One-time join link generated.
                                <div class="workspace-inline-code"><?= htmlspecialchars($_GET['one_time_link']) ?></div>
                                <div class="workspace-action-row" style="margin-top:8px;">
                                    <button
                                        type="button"
                                        class="workspace-btn ghost mini"
                                        onclick="copyInviteLink('<?= htmlspecialchars((string)$_GET['one_time_link'], ENT_QUOTES) ?>')"
                                    >
                                        <i class="fa fa-copy"></i> Copy Link
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </section>

                <section class="workspace-panel invite-bulk-panel">
                    <div class="workspace-panel-head">
                        <div>
                            <h3 class="workspace-panel-title">Bulk Invite Upload</h3>
                            <p class="workspace-panel-sub">
                                Upload <strong>.xlsx</strong>, <strong>.csv</strong>, or text-based <strong>.pdf</strong>.
                                Include name and email columns, for example <code>Full Name</code> and <code>Email</code>.
                            </p>
                            <div class="workspace-action-row" style="margin-top:10px;">
                                <a
                                    class="workspace-btn ghost mini"
                                    href="app/templates/bulk_invite_template.xlsx"
                                    download="bulk-invite-template.xlsx"
                                >
                                    <i class="fa fa-download"></i> Download Excel Template
                                </a>
                            </div>
                        </div>
                    </div>
                    <form action="app/invite-users-bulk.php" method="POST" enctype="multipart/form-data" class="workspace-form-grid">
                        <?= csrf_field('bulk_invite_form') ?>
                        <div class="workspace-field">
                            <label>Employee File</label>
                            <input
                                class="workspace-input"
                                type="file"
                                name="employees_file"
                                accept=".xlsx,.csv,.pdf"
                                required
                            >
                        </div>
                        <div>
                            <button class="workspace-btn primary" type="submit">
                                <i class="fa fa-upload"></i> Upload And Send Invites
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        <?php } ?>

        <section class="workspace-panel">
            <div class="workspace-panel-head">
                <div>
                    <h3 class="workspace-panel-title">Recent Invites</h3>
                    <p class="workspace-panel-sub">Track invite status, expiry, and quickly copy or revoke active links.</p>
                </div>
                <span class="workspace-pill soft"><?= count($invites) ?> record<?= count($invites) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (empty($invites)) { ?>
                <p class="workspace-empty">No invites yet.</p>
            <?php } else { ?>
                <div class="workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                        <tr>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Join Link</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invites as $invite) {
                            $status = strtolower((string)$invite['status']);
                            $isOpenLink = invite_is_open_link_email((string)$invite['email']);
                            $statusClass = 'st-expired';
                            if ($status === 'pending') {
                                $statusClass = 'st-pending';
                            } elseif ($status === 'accepted') {
                                $statusClass = 'st-accepted';
                            } elseif ($status === 'revoked') {
                                $statusClass = 'st-revoked';
                            }
                            $joinLink = APP_URL . '/join-workspace.php?token=' . $invite['token'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars(invite_format_display_email((string)$invite['email'])) ?></td>
                                <td><?= htmlspecialchars((string)($invite['full_name'] ?: ($isOpenLink ? 'Open registration link' : '-'))) ?></td>
                                <td><span class="workspace-status <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                                <td><?= htmlspecialchars((string)$invite['expires_at']) ?></td>
                                <td><span class="workspace-mono"><?= htmlspecialchars($joinLink) ?></span></td>
                                <td>
                                    <div class="workspace-action-row">
                                        <button type="button" class="workspace-btn ghost mini" onclick="copyInviteLink('<?= htmlspecialchars($joinLink, ENT_QUOTES) ?>')">
                                            <i class="fa fa-copy"></i> Copy Link
                                        </button>
                                        <?php if ($status === 'pending') { ?>
                                            <form action="app/cancel-invite.php" method="POST" class="workspace-inline-form">
                                                <?= csrf_field('revoke_invite_form') ?>
                                                <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                                <button type="submit" class="workspace-btn danger mini" onclick="return confirm('Revoke this invite?')">Revoke</button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>
    </div>
</div>

<div id="inviteToast" class="workspace-toast" role="status" aria-live="polite"></div>
<script>
    var inviteToastTimer = null;

    function showInviteToast(message, type) {
        var toast = document.getElementById('inviteToast');
        if (!toast) {
            alert(message);
            return;
        }
        toast.textContent = message;
        toast.className = 'workspace-toast show ' + (type === 'error' ? 'error' : 'success');
        if (inviteToastTimer) {
            clearTimeout(inviteToastTimer);
        }
        inviteToastTimer = setTimeout(function () {
            toast.className = 'workspace-toast';
        }, 2200);
    }

    function copyInviteLink(link) {
        if (!navigator.clipboard) {
            showInviteToast('Clipboard is not available in this browser.', 'error');
            return;
        }
        navigator.clipboard.writeText(link).then(function () {
            showInviteToast('Invite link copied.', 'success');
        }).catch(function () {
            showInviteToast('Failed to copy invite link.', 'error');
        });
    }
</script>
</body>
</html>
<?php
} else {
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
}
?>
