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
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Invite Users | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .card {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .alert-box {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }
        .alert-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }
        .alert-warn {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            color: #92400E;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .input-field {
            width: 100%;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            padding: 10px 12px;
            outline: none;
        }
        .input-field:focus {
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .btn-primary-lite {
            background: #4F46E5;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary-lite:hover {
            background: #4338CA;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 10px 8px;
            border-bottom: 1px solid #E5E7EB;
            font-size: 13px;
            vertical-align: top;
        }
        th {
            color: #6B7280;
            font-weight: 600;
        }
        .status-pill {
            display: inline-block;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 999px;
            font-weight: 600;
        }
        .st-pending {
            background: #EEF2FF;
            color: #4338CA;
        }
        .st-accepted {
            background: #ECFDF5;
            color: #065F46;
        }
        .st-revoked, .st-expired {
            background: #F3F4F6;
            color: #374151;
        }
        .mini-btn {
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            padding: 5px 8px;
            background: #fff;
            color: #374151;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
        }
        .mini-btn-danger {
            border-color: #FCA5A5;
            color: #B91C1C;
            background: #FEF2F2;
        }
        .mono {
            font-family: Consolas, monospace;
            font-size: 11px;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 3px 6px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <div class="card">
        <p style="margin:0; color:#6B7280;">
            Workspace: <strong><?= htmlspecialchars((string)$orgName) ?></strong>
        </p>
        <p style="margin:6px 0 0; color:#6B7280; font-size:13px;">
            Subscription: <strong><?= htmlspecialchars($subscriptionStatus) ?></strong>
            <?php if ($seatLimit !== null) { ?>
                | Seats: <strong><?= $seatUsed ?>/<?= $seatLimit ?></strong>
                <?php if ($seatsLeft !== null) { ?>
                    (<?= max(0, $seatsLeft) ?> left)
                <?php } ?>
            <?php } ?>
        </p>
    </div>

    <?php if (isset($_GET['error'])) { ?>
        <div class="alert-box alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php } ?>
    <?php if (isset($_GET['success'])) { ?>
        <div class="alert-box alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php } ?>
    <?php if (isset($_GET['warn'])) { ?>
        <div class="alert-box alert-warn"><?= htmlspecialchars($_GET['warn']) ?></div>
    <?php } ?>
    <?php if (isset($_GET['manual_link'])) { ?>
        <div class="alert-box alert-warn">
            Manual invite link:
            <span class="mono"><?= htmlspecialchars($_GET['manual_link']) ?></span>
        </div>
    <?php } ?>
    <?php if (isset($_GET['one_time_link'])) { ?>
        <div class="alert-box alert-success">
            One-time join link:
            <span class="mono"><?= htmlspecialchars($_GET['one_time_link']) ?></span>
        </div>
    <?php } ?>

    <?php if (!$hasInviteTable) { ?>
        <div class="card alert-box alert-error">
            `workspace_invites` table is missing. Run `sql_create_workspace_invites.sql` or `run_migration_workspace_invites.php` first.
        </div>
    <?php } elseif ($is_super_admin) { ?>
        <div class="card alert-box alert-error">
            Super Admin cannot send workspace invites from this screen.
        </div>
    <?php } elseif (!$capacity['ok']) { ?>
        <div class="card alert-box alert-warn">
            <?= htmlspecialchars((string)$capacity['reason']) ?>
        </div>
    <?php } else { ?>
        <div class="card">
            <h3 style="margin-top:0;">Send New Invite</h3>
            <form action="app/invite-user.php" method="POST">
                <?= csrf_field('invite_user_form') ?>
                <div class="form-row">
                    <div>
                        <label style="display:block; margin-bottom:6px; font-size:13px; color:#374151;">Employee Full Name</label>
                        <input class="input-field" type="text" name="full_name" placeholder="Jane Doe" required>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:6px; font-size:13px; color:#374151;">Employee Email</label>
                        <input class="input-field" type="email" name="email" placeholder="jane@company.com" required>
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <button class="btn-primary-lite" type="submit">
                        <i class="fa fa-paper-plane"></i> Send Invite
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Bulk Invite Upload</h3>
            <p style="margin: 0 0 12px; color:#6B7280; font-size:13px;">
                Upload employee list as <strong>.xlsx</strong>, <strong>.csv</strong>, or text-based <strong>.pdf</strong>.
                Include columns for name and email (for example: <code>Full Name</code>, <code>Email</code>).
            </p>
            <form action="app/invite-users-bulk.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field('bulk_invite_form') ?>
                <input
                    class="input-field"
                    type="file"
                    name="employees_file"
                    accept=".xlsx,.csv,.pdf"
                    required
                >
                <div style="margin-top: 12px;">
                    <button class="btn-primary-lite" type="submit">
                        <i class="fa fa-upload"></i> Upload and Send Invites
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Generate One-time Join Link</h3>
            <p style="margin: 0 0 12px; color:#6B7280; font-size:13px;">
                Create a single-use link you can send directly to one employee. The first valid signup consumes the link.
            </p>
            <form action="app/generate-invite-link.php" method="POST">
                <?= csrf_field('generate_workspace_join_link_form') ?>
                <button class="btn-primary-lite" type="submit">
                    <i class="fa fa-link"></i> Generate One-time Link
                </button>
            </form>
        </div>
    <?php } ?>

    <div class="card">
        <h3 style="margin-top:0;">Recent Invites</h3>
        <?php if (empty($invites)) { ?>
            <p style="color:#6B7280;">No invites yet.</p>
        <?php } else { ?>
            <div class="table-wrap">
                <table>
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
                            <td><span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                            <td><?= htmlspecialchars((string)$invite['expires_at']) ?></td>
                            <td><span class="mono"><?= htmlspecialchars($joinLink) ?></span></td>
                            <td>
                                <button type="button" class="mini-btn" onclick="copyInviteLink('<?= htmlspecialchars($joinLink, ENT_QUOTES) ?>')">Copy Link</button>
                                <?php if ($status === 'pending') { ?>
                                    <form action="app/cancel-invite.php" method="POST" style="display:inline-block; margin-left:4px;">
                                        <?= csrf_field('revoke_invite_form') ?>
                                        <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                        <button type="submit" class="mini-btn mini-btn-danger" onclick="return confirm('Revoke this invite?')">Revoke</button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<script>
    function copyInviteLink(link) {
        if (!navigator.clipboard) {
            alert('Clipboard is not available in this browser.');
            return;
        }
        navigator.clipboard.writeText(link).then(function () {
            alert('Invite link copied.');
        }).catch(function () {
            alert('Failed to copy invite link.');
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
