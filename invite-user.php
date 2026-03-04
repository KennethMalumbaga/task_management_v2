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

    $oneTimeLink = isset($_GET['one_time_link']) ? trim((string)$_GET['one_time_link']) : '';
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
                <section class="workspace-panel invite-method-card invite-method-send">
                    <div class="invite-method-head">
                        <span class="invite-method-icon">
                            <i class="fa fa-paper-plane-o"></i>
                        </span>
                        <div>
                            <h3 class="workspace-panel-title">Send New Invite</h3>
                            <p class="workspace-panel-sub">Invite a teammate directly by name &amp; email</p>
                        </div>
                    </div>
                    <div class="invite-method-divider"></div>
                    <form action="app/invite-user.php" method="POST" class="workspace-form-grid invite-method-form" id="sendInviteForm" novalidate>
                        <?= csrf_field('invite_user_form') ?>
                        <div class="workspace-field">
                            <label>Employee Full Name</label>
                            <input
                                class="workspace-input"
                                type="text"
                                name="full_name"
                                id="sendInviteFullName"
                                placeholder="Jane Doe"
                                autocomplete="name"
                                maxlength="80"
                                required
                            >
                        </div>
                        <div class="workspace-field">
                            <label>Work Email</label>
                            <input
                                class="workspace-input"
                                type="email"
                                name="email"
                                id="sendInviteEmail"
                                placeholder="jane@company.com"
                                autocomplete="email"
                                inputmode="email"
                                maxlength="254"
                                required
                            >
                        </div>
                        <div class="invite-method-actions">
                            <button class="workspace-btn primary invite-submit-btn" type="submit">
                                <i class="fa fa-paper-plane"></i> Send Invite
                            </button>
                        </div>
                    </form>
                </section>

                <section class="workspace-panel invite-method-card invite-method-link">
                    <div class="invite-method-head">
                        <span class="invite-method-icon">
                            <i class="fa fa-link"></i>
                        </span>
                        <div>
                            <h3 class="workspace-panel-title">One-time Link</h3>
                            <p class="workspace-panel-sub">Single-use join URL - consumed on first signup</p>
                        </div>
                    </div>
                    <div class="invite-method-divider"></div>
                    <form action="app/generate-invite-link.php" method="POST" class="workspace-form-grid invite-method-form">
                        <?= csrf_field('generate_workspace_join_link_form') ?>
                        <div class="invite-link-display">
                            <?php if ($oneTimeLink !== '') { ?>
                                <div class="invite-link-box">
                                    <span class="invite-link-text"><?= htmlspecialchars($oneTimeLink) ?></span>
                                </div>
                            <?php } else { ?>
                                <div class="invite-link-placeholder">
                                    <div class="invite-link-dots">
                                        <span class="invite-link-dot"></span>
                                        <span class="invite-link-dot"></span>
                                        <span class="invite-link-dot"></span>
                                    </div>
                                    <p class="invite-link-placeholder-text">No link generated yet</p>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($oneTimeLink !== '') { ?>
                            <div class="invite-link-copy-row">
                                <button
                                    type="button"
                                    class="workspace-btn ghost invite-copy-link-btn"
                                    onclick="copyInviteLink('<?= htmlspecialchars($oneTimeLink, ENT_QUOTES) ?>')"
                                >
                                    <i class="fa fa-copy"></i> Copy Link
                                </button>
                            </div>
                        <?php } ?>
                        <div class="invite-method-actions">
                            <button class="workspace-btn primary invite-generate-btn" type="submit">
                                <i class="fa fa-link"></i> Generate One-time Link
                            </button>
                        </div>
                    </form>
                </section>

                <section class="workspace-panel invite-method-card invite-bulk-panel">
                    <div class="invite-method-head">
                        <span class="invite-method-icon">
                            <i class="fa fa-cloud-upload"></i>
                        </span>
                        <div>
                            <h3 class="workspace-panel-title">Bulk Invite Upload</h3>
                            <p class="workspace-panel-sub">Import via <span class="invite-ext-chip">.xlsx</span>, <span class="invite-ext-chip">.csv</span>, or <span class="invite-ext-chip">.pdf</span></p>
                        </div>
                    </div>
                    <div class="invite-method-divider"></div>
                    <form action="app/invite-users-bulk.php" method="POST" enctype="multipart/form-data" class="workspace-form-grid invite-method-form">
                        <?= csrf_field('bulk_invite_form') ?>
                        <label class="invite-dropzone" id="bulkDropZone">
                            <input
                                class="invite-dropzone-input"
                                type="file"
                                name="employees_file"
                                id="bulkFileInput"
                                accept=".xlsx,.csv,.pdf"
                                required
                            >
                            <div class="invite-upload-idle" id="bulkUploadIdle">
                                <span class="invite-dropzone-icon"><i class="fa fa-cloud-upload"></i></span>
                                <span class="invite-dropzone-text">Drop file here or <strong>browse</strong></span>
                                <span class="invite-dropzone-types">XLSX - CSV - PDF</span>
                            </div>
                            <div class="invite-upload-success" id="bulkUploadSuccess" style="display:none;">
                                <div class="invite-file-preview">
                                    <div class="invite-file-icon-wrap" id="bulkFileIconWrap"><i class="fa fa-file-o"></i></div>
                                    <div class="invite-file-info">
                                        <div class="invite-file-name" id="bulkFileName">employees.xlsx</div>
                                        <div class="invite-file-meta" id="bulkFileMeta">0 KB - XLSX</div>
                                        <div class="invite-file-progress">
                                            <div class="invite-file-progress-bar" id="bulkProgressBar"></div>
                                        </div>
                                        <div class="invite-file-status" id="bulkFileStatus">Uploading...</div>
                                    </div>
                                    <button class="invite-file-remove" id="bulkFileRemove" type="button" aria-label="Remove selected file">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </label>
                        <div class="invite-template-row">
                            <a
                                class="workspace-btn ghost invite-template-btn"
                                href="app/templates/bulk_invite_template.xlsx"
                                download="bulk-invite-template.xlsx"
                            >
                                <i class="fa fa-download"></i> Download Template
                            </a>
                        </div>
                        <p class="invite-bulk-note">Full Name + Email columns required</p>
                        <div class="invite-method-actions">
                            <button class="workspace-btn primary invite-submit-btn" type="submit">
                                <i class="fa fa-upload"></i> Upload And Send Invites
                            </button>
                        </div>
                    </form>
                    <div class="bulk-invite-hover-guide" aria-hidden="true">
                        <div class="bulk-invite-hover-guide-head">
                            <span class="bulk-invite-hover-guide-head-icon">
                                <i class="fa fa-info-circle"></i>
                            </span>
                            <div>
                                <p class="bulk-invite-hover-guide-heading">How to Bulk Invite</p>
                                <p class="bulk-invite-hover-guide-subheading">Follow these 4 steps</p>
                            </div>
                        </div>
                        <ol class="bulk-invite-hover-guide-steps">
                            <li class="bulk-invite-hover-guide-step">
                                <span class="bulk-invite-hover-guide-badge">1</span>
                                <div>
                                    <p class="bulk-invite-hover-guide-step-title">Download the template</p>
                                    <p class="bulk-invite-hover-guide-step-text">
                                        Click <strong>Download</strong> to get the pre-formatted <code>.xlsx</code> file.
                                    </p>
                                </div>
                            </li>
                            <li class="bulk-invite-hover-guide-step">
                                <span class="bulk-invite-hover-guide-badge">2</span>
                                <div>
                                    <p class="bulk-invite-hover-guide-step-title">Fill in employee details</p>
                                    <p class="bulk-invite-hover-guide-step-text">
                                        Add <code>Full Name</code> and <code>Email</code> - one row per person. Do not rename headers.
                                    </p>
                                </div>
                            </li>
                            <li class="bulk-invite-hover-guide-step">
                                <span class="bulk-invite-hover-guide-badge">3</span>
                                <div>
                                    <p class="bulk-invite-hover-guide-step-title">Save your file</p>
                                    <p class="bulk-invite-hover-guide-step-text">
                                        Keep as <code>.xlsx</code> or <code>.csv</code>. Max <strong>45 rows</strong> per upload.
                                    </p>
                                </div>
                            </li>
                            <li class="bulk-invite-hover-guide-step">
                                <span class="bulk-invite-hover-guide-badge">4</span>
                                <div>
                                    <p class="bulk-invite-hover-guide-step-title">Upload &amp; send</p>
                                    <p class="bulk-invite-hover-guide-step-text">
                                        Choose your file below, then click <strong>Upload And Send Invites</strong>.
                                    </p>
                                </div>
                            </li>
                        </ol>
                        <div class="bulk-invite-hover-guide-sample">
                            <p class="bulk-invite-hover-guide-sample-title">Expected Format</p>
                            <div class="bulk-invite-hover-guide-table-wrap">
                                <table class="bulk-invite-hover-guide-table">
                                    <thead>
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td>Jane Doe</td>
                                        <td>jane@company.com</td>
                                    </tr>
                                    <tr>
                                        <td>John Smith</td>
                                        <td>john@company.com</td>
                                    </tr>
                                    <tr>
                                        <td>Maria Garcia</td>
                                        <td>maria@company.com</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        <?php } ?>

        <section class="workspace-panel recent-invites-card">
            <div class="recent-invites-head">
                <div>
                    <h3 class="workspace-panel-title">Recent Invites</h3>
                    <p class="workspace-panel-sub">Track invite status, expiry, and quickly copy or revoke active links.</p>
                </div>
                <div class="recent-invites-head-right">
                    <div class="recent-invites-filters" id="inviteStatusFilters" role="tablist" aria-label="Filter invites by status">
                        <button type="button" class="invite-filter-btn is-active" data-filter="all">All</button>
                        <button type="button" class="invite-filter-btn" data-filter="pending">Pending</button>
                        <button type="button" class="invite-filter-btn" data-filter="accepted">Accepted</button>
                        <button type="button" class="invite-filter-btn" data-filter="expired">Expired</button>
                    </div>
                    <span class="recent-invites-records" id="inviteRecordsBadge"><?= count($invites) ?> Record<?= count($invites) === 1 ? '' : 's' ?></span>
                </div>
            </div>
            <?php if (empty($invites)) { ?>
                <p class="workspace-empty recent-invites-empty">No invites yet.</p>
            <?php } else { ?>
                <div class="recent-invites-table-wrap">
                    <table class="recent-invites-table">
                        <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Join Link</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="recentInvitesBody">
                        <?php
                        $inviteRowIndex = 0;
                        foreach ($invites as $invite) {
                            $status = strtolower((string)$invite['status']);
                            $statusPillClass = 'invite-status-expired';
                            if ($status === 'pending') {
                                $statusPillClass = 'invite-status-pending';
                            } elseif ($status === 'accepted') {
                                $statusPillClass = 'invite-status-accepted';
                            } elseif ($status === 'revoked') {
                                $statusPillClass = 'invite-status-revoked';
                            }

                            $isOpenLink = invite_is_open_link_email((string)$invite['email']);
                            $displayName = (string)($invite['full_name'] ?: ($isOpenLink ? 'Open registration link' : '-'));
                            $displayEmail = invite_format_display_email((string)$invite['email']);
                            $joinLink = APP_URL . '/join-workspace.php?token=' . $invite['token'];
                            $token = (string)($invite['token'] ?? '');
                            $shortToken = $token !== '' ? '...' . substr($token, 0, 16) . '...' : 'join-workspace.php';

                            $expiresRaw = (string)$invite['expires_at'];
                            $expiresDate = $expiresRaw;
                            $expiresTime = '';
                            $expiresTs = strtotime($expiresRaw);
                            if ($expiresTs !== false) {
                                $expiresDate = strtoupper(date('M j, Y', $expiresTs));
                                $expiresTime = date('H:i:s', $expiresTs);
                            }

                            $initials = '?';
                            if (!$isOpenLink) {
                                $parts = preg_split('/\s+/', trim($displayName)) ?: [];
                                $letters = '';
                                foreach ($parts as $part) {
                                    if ($part === '') {
                                        continue;
                                    }
                                    $letters .= strtoupper(function_exists('mb_substr') ? (string)mb_substr($part, 0, 1) : substr($part, 0, 1));
                                    if (strlen($letters) >= 2) {
                                        break;
                                    }
                                }
                                if ($letters !== '') {
                                    $initials = $letters;
                                }
                            }

                            $avatarToneClass = 'invite-avatar-tone-' . (($inviteRowIndex % 6) + 1);
                            $inviteRowIndex++;
                            ?>
                            <tr class="invite-table-row" data-status="<?= htmlspecialchars($status) ?>">
                                <td>
                                    <div class="invite-recipient">
                                        <span class="invite-avatar <?= $avatarToneClass ?>">
                                            <?php if ($isOpenLink) { ?>
                                                <i class="fa fa-link"></i>
                                            <?php } else { ?>
                                                <?= htmlspecialchars($initials) ?>
                                            <?php } ?>
                                        </span>
                                        <div class="invite-recipient-main">
                                            <div class="invite-recipient-name"><?= htmlspecialchars($displayName) ?></div>
                                            <?php if ($isOpenLink) { ?>
                                                <span class="invite-one-time-pill">one-time link</span>
                                            <?php } else { ?>
                                                <div class="invite-recipient-email"><?= htmlspecialchars($displayEmail) ?></div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="invite-status-pill <?= $statusPillClass ?>">
                                        <span class="invite-status-dot"></span>
                                        <?= htmlspecialchars(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="invite-expires">
                                        <i class="fa fa-clock-o"></i>
                                        <div>
                                            <div class="invite-expires-date"><?= htmlspecialchars($expiresDate) ?></div>
                                            <div class="invite-expires-time"><?= htmlspecialchars($expiresTime) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="invite-link-chip" title="<?= htmlspecialchars($joinLink) ?>">
                                        <i class="fa fa-link"></i>
                                        <span><?= htmlspecialchars($shortToken) ?></span>
                                    </span>
                                </td>
                                <td>
                                    <div class="invite-actions">
                                        <button type="button" class="invite-action-btn copy" onclick="copyInviteLink('<?= htmlspecialchars($joinLink, ENT_QUOTES) ?>')">
                                            <i class="fa fa-copy"></i> Copy
                                        </button>
                                        <?php if ($status === 'pending') { ?>
                                            <form action="app/cancel-invite.php" method="POST" class="invite-inline-form">
                                                <?= csrf_field('revoke_invite_form') ?>
                                                <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                                <button type="submit" class="invite-action-btn revoke" onclick="return confirm('Revoke this invite?')">
                                                    <i class="fa fa-times-circle-o"></i> Revoke
                                                </button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="recent-invites-footer">
                    <span class="recent-invites-range" id="inviteRangeLabel">Showing 1-<?= min(4, count($invites)) ?> of <?= count($invites) ?> invites</span>
                    <div class="recent-invites-pagination" id="invitePagination"></div>
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

    var sendInviteForm = document.getElementById('sendInviteForm');
    var sendInviteFullName = document.getElementById('sendInviteFullName');
    var sendInviteEmail = document.getElementById('sendInviteEmail');

    function normalizeInviteName(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    function isValidInviteFullName(value) {
        var normalized = normalizeInviteName(value);
        if (normalized.length < 3 || normalized.length > 80) return false;
        if (normalized.indexOf(' ') === -1) return false;
        var namePattern = /^[A-Za-z\u00C0-\u024F](?:[A-Za-z\u00C0-\u024F'.\-]*[A-Za-z\u00C0-\u024F])?(?:\s+[A-Za-z\u00C0-\u024F](?:[A-Za-z\u00C0-\u024F'.\-]*[A-Za-z\u00C0-\u024F])?)+$/;
        return namePattern.test(normalized);
    }

    function isValidInviteEmail(value) {
        var normalized = (value || '').trim().toLowerCase();
        if (normalized === '' || normalized.length > 254) return false;
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        return emailPattern.test(normalized);
    }

    function validateInviteFullNameField() {
        if (!sendInviteFullName) return true;
        var normalized = normalizeInviteName(sendInviteFullName.value);
        sendInviteFullName.value = normalized;

        if (isValidInviteFullName(normalized)) {
            sendInviteFullName.setCustomValidity('');
            return true;
        }

        sendInviteFullName.setCustomValidity('Enter a valid full name (first and last name, letters only).');
        return false;
    }

    function validateInviteEmailField() {
        if (!sendInviteEmail) return true;
        var normalized = (sendInviteEmail.value || '').trim().toLowerCase();
        sendInviteEmail.value = normalized;

        if (isValidInviteEmail(normalized)) {
            sendInviteEmail.setCustomValidity('');
            return true;
        }

        sendInviteEmail.setCustomValidity('Enter a valid work email address.');
        return false;
    }

    if (sendInviteFullName) {
        sendInviteFullName.addEventListener('input', function () {
            sendInviteFullName.setCustomValidity('');
        });
        sendInviteFullName.addEventListener('blur', function () {
            validateInviteFullNameField();
        });
    }

    if (sendInviteEmail) {
        sendInviteEmail.addEventListener('input', function () {
            sendInviteEmail.setCustomValidity('');
        });
        sendInviteEmail.addEventListener('blur', function () {
            validateInviteEmailField();
        });
    }

    if (sendInviteForm) {
        sendInviteForm.addEventListener('submit', function (e) {
            var validName = validateInviteFullNameField();
            var validEmail = validateInviteEmailField();
            if (!validName || !validEmail) {
                e.preventDefault();
                sendInviteForm.reportValidity();
            }
        });
    }

    var bulkDropZone = document.getElementById('bulkDropZone');
    var bulkFileInput = document.getElementById('bulkFileInput');
    var bulkUploadIdle = document.getElementById('bulkUploadIdle');
    var bulkUploadSuccess = document.getElementById('bulkUploadSuccess');
    var bulkFileNameEl = document.getElementById('bulkFileName');
    var bulkFileMetaEl = document.getElementById('bulkFileMeta');
    var bulkProgressBar = document.getElementById('bulkProgressBar');
    var bulkFileStatus = document.getElementById('bulkFileStatus');
    var bulkFileIconWrap = document.getElementById('bulkFileIconWrap');
    var bulkFileRemove = document.getElementById('bulkFileRemove');
    var bulkUploadTimer = null;

    var BULK_EXT_ICON_CLASSES = {
        xlsx: 'fa-file-excel-o',
        csv: 'fa-file-text-o',
        pdf: 'fa-file-pdf-o',
        default: 'fa-file-o'
    };
    var BULK_ALLOWED_EXTENSIONS = ['xlsx', 'csv', 'pdf'];

    function bulkFormatSize(bytes) {
        if (!bytes || bytes < 1024) return (bytes || 0) + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function bulkShowUploadIdle() {
        if (bulkUploadTimer) {
            clearInterval(bulkUploadTimer);
            bulkUploadTimer = null;
        }

        if (bulkUploadIdle) bulkUploadIdle.style.display = 'flex';
        if (bulkUploadSuccess) bulkUploadSuccess.style.display = 'none';
        if (bulkProgressBar) bulkProgressBar.style.width = '0%';
        if (bulkFileStatus) bulkFileStatus.textContent = 'Uploading...';
        if (bulkDropZone) bulkDropZone.classList.remove('has-file');
    }

    function bulkSimulateUpload(file) {
        if (!file) {
            bulkShowUploadIdle();
            return;
        }

        var parts = file.name.split('.');
        var ext = parts.length > 1 ? parts.pop().toLowerCase() : '';
        var extLabel = ext ? ext.toUpperCase() : 'FILE';
        var iconClass = BULK_EXT_ICON_CLASSES[ext] || BULK_EXT_ICON_CLASSES.default;

        if (bulkFileNameEl) bulkFileNameEl.textContent = file.name;
        if (bulkFileMetaEl) bulkFileMetaEl.textContent = bulkFormatSize(file.size) + ' - ' + extLabel;
        if (bulkFileIconWrap) bulkFileIconWrap.innerHTML = '<i class="fa ' + iconClass + '"></i>';
        if (bulkFileStatus) bulkFileStatus.textContent = 'Uploading...';
        if (bulkProgressBar) bulkProgressBar.style.width = '0%';

        if (bulkUploadIdle) bulkUploadIdle.style.display = 'none';
        if (bulkUploadSuccess) bulkUploadSuccess.style.display = 'block';
        if (bulkDropZone) bulkDropZone.classList.add('has-file');

        var pct = 0;
        if (bulkUploadTimer) clearInterval(bulkUploadTimer);
        bulkUploadTimer = setInterval(function () {
            pct += Math.random() * 12 + 4;
            if (pct >= 100) {
                pct = 100;
                clearInterval(bulkUploadTimer);
                bulkUploadTimer = null;
                if (bulkFileStatus) bulkFileStatus.textContent = 'Upload complete';
            }
            if (bulkProgressBar) bulkProgressBar.style.width = pct + '%';
        }, 80);
    }

    function bulkValidateSelectedFile(file) {
        if (!file || !file.name) {
            return 'Please choose a file to upload.';
        }

        var parts = file.name.split('.');
        var ext = parts.length > 1 ? parts.pop().toLowerCase() : '';
        if (BULK_ALLOWED_EXTENSIONS.indexOf(ext) === -1) {
            return 'Only .xlsx, .csv, and .pdf files are allowed.';
        }

        return '';
    }

    function bulkRejectSelectedFile(message) {
        if (bulkFileInput) {
            bulkFileInput.value = '';
            bulkFileInput.setCustomValidity(message);
            bulkFileInput.reportValidity();
        }
        bulkShowUploadIdle();
    }

    if (bulkFileInput) {
        bulkFileInput.addEventListener('change', function (e) {
            if (e.target.files && e.target.files[0]) {
                var file = e.target.files[0];
                var bulkError = bulkValidateSelectedFile(file);
                if (bulkError) {
                    bulkRejectSelectedFile(bulkError);
                    return;
                }
                bulkFileInput.setCustomValidity('');
                bulkSimulateUpload(file);
            } else {
                bulkShowUploadIdle();
            }
        });
    }

    if (bulkDropZone) {
        bulkDropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            bulkDropZone.classList.add('drag-over');
        });

        bulkDropZone.addEventListener('dragleave', function () {
            bulkDropZone.classList.remove('drag-over');
        });

        bulkDropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            bulkDropZone.classList.remove('drag-over');
            var file = (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) ? e.dataTransfer.files[0] : null;
            if (!file) return;

            var bulkError = bulkValidateSelectedFile(file);
            if (bulkError) {
                bulkRejectSelectedFile(bulkError);
                return;
            }

            if (bulkFileInput) {
                try {
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    bulkFileInput.files = dt.files;
                    bulkFileInput.setCustomValidity('');
                } catch (err) {
                    // Keep visual feedback even when file assignment is blocked.
                }
            }
            bulkSimulateUpload(file);
        });
    }

    if (bulkFileRemove) {
        bulkFileRemove.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (bulkFileInput) bulkFileInput.value = '';
            bulkShowUploadIdle();
        });
    }

    var inviteStatusFilters = document.getElementById('inviteStatusFilters');
    var recentInvitesBody = document.getElementById('recentInvitesBody');
    var inviteRows = recentInvitesBody ? Array.prototype.slice.call(recentInvitesBody.querySelectorAll('.invite-table-row')) : [];
    var inviteRecordsBadge = document.getElementById('inviteRecordsBadge');
    var inviteRangeLabel = document.getElementById('inviteRangeLabel');
    var invitePagination = document.getElementById('invitePagination');
    var invitePageSize = 4;
    var inviteState = {
        filter: 'all',
        page: 1
    };

    function inviteRecordLabel(count) {
        return count + ' Record' + (count === 1 ? '' : 's');
    }

    function inviteRowMatchesFilter(row, filterValue) {
        if (!row) return false;
        if (filterValue === 'all') return true;

        var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
        if (filterValue === 'expired') {
            return rowStatus === 'expired' || rowStatus === 'revoked';
        }

        return rowStatus === filterValue;
    }

    function inviteCreatePageButton(labelHtml, targetPage, disabled, active) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'invite-page-btn' + (active ? ' is-active' : '') + (disabled ? ' is-disabled' : '');
        btn.innerHTML = labelHtml;
        btn.disabled = !!disabled;
        if (!disabled) {
            btn.addEventListener('click', function () {
                inviteState.page = targetPage;
                applyInviteTableState();
            });
        }
        return btn;
    }

    function inviteRenderPagination(totalPages) {
        if (!invitePagination) return;
        invitePagination.innerHTML = '';

        var prevDisabled = inviteState.page <= 1;
        invitePagination.appendChild(inviteCreatePageButton('<i class="fa fa-angle-left"></i>', Math.max(1, inviteState.page - 1), prevDisabled, false));

        for (var i = 1; i <= totalPages; i++) {
            invitePagination.appendChild(inviteCreatePageButton(String(i), i, false, i === inviteState.page));
        }

        var nextDisabled = inviteState.page >= totalPages;
        invitePagination.appendChild(inviteCreatePageButton('<i class="fa fa-angle-right"></i>', Math.min(totalPages, inviteState.page + 1), nextDisabled, false));
    }

    function applyInviteTableState() {
        if (!inviteRows.length) {
            if (inviteRecordsBadge) inviteRecordsBadge.textContent = inviteRecordLabel(0);
            if (inviteRangeLabel) inviteRangeLabel.textContent = 'Showing 0 of 0 invites';
            if (invitePagination) invitePagination.innerHTML = '';
            return;
        }

        var filteredRows = inviteRows.filter(function (row) {
            return inviteRowMatchesFilter(row, inviteState.filter);
        });

        var totalFiltered = filteredRows.length;
        var totalPages = Math.max(1, Math.ceil(totalFiltered / invitePageSize));
        if (inviteState.page > totalPages) inviteState.page = totalPages;
        if (inviteState.page < 1) inviteState.page = 1;

        for (var r = 0; r < inviteRows.length; r++) {
            inviteRows[r].style.display = 'none';
        }

        var startIndex = (inviteState.page - 1) * invitePageSize;
        var endIndex = Math.min(startIndex + invitePageSize, totalFiltered);
        for (var i = startIndex; i < endIndex; i++) {
            filteredRows[i].style.display = 'table-row';
        }

        if (inviteRecordsBadge) {
            inviteRecordsBadge.textContent = inviteRecordLabel(totalFiltered);
        }

        if (inviteRangeLabel) {
            if (totalFiltered === 0) {
                inviteRangeLabel.textContent = 'Showing 0 of 0 invites';
            } else {
                inviteRangeLabel.textContent = 'Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + totalFiltered + ' invites';
            }
        }

        inviteRenderPagination(totalPages);
    }

    if (inviteStatusFilters) {
        var filterButtons = inviteStatusFilters.querySelectorAll('.invite-filter-btn');
        for (var b = 0; b < filterButtons.length; b++) {
            filterButtons[b].addEventListener('click', function () {
                var chosen = (this.getAttribute('data-filter') || 'all').toLowerCase();
                inviteState.filter = chosen;
                inviteState.page = 1;

                for (var x = 0; x < filterButtons.length; x++) {
                    filterButtons[x].classList.remove('is-active');
                }
                this.classList.add('is-active');

                applyInviteTableState();
            });
        }
    }

    applyInviteTableState();
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
