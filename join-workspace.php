<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include "DB_connection.php";
require_once "inc/tenant.php";
require_once "inc/csrf.php";
require_once "app/invite_helpers.php";

$token = trim((string)($_GET['token'] ?? ''));
$invite = null;
$inviteError = null;
$prefillEmail = trim((string)($_GET['email'] ?? ''));

if ($token === '') {
    $inviteError = "Invitation token is missing.";
} elseif (!tenant_table_exists($pdo, 'workspace_invites')) {
    $inviteError = "Invitation system is not available yet.";
} else {
    $stmt = $pdo->prepare(
        "SELECT wi.id, wi.organization_id, wi.email, wi.full_name, wi.role, wi.status, wi.expires_at,
                o.name AS organization_name, o.status AS organization_status
         FROM workspace_invites wi
         JOIN organizations o ON o.id = wi.organization_id
         WHERE wi.token = ?
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$invite) {
        $inviteError = "Invalid invitation link.";
    } else {
        $status = strtolower((string)$invite['status']);
        $orgStatus = strtolower((string)($invite['organization_status'] ?? 'active'));
        $expiresAt = strtotime((string)$invite['expires_at']);

        if ($status !== 'pending') {
            $inviteError = "This invitation is no longer active.";
        } elseif ($expiresAt !== false && $expiresAt <= time()) {
            $inviteError = "This invitation has expired. Ask your admin to send a new one.";
        } elseif ($orgStatus !== 'active') {
            $inviteError = "This workspace is currently unavailable.";
        } else {
            $capacity = tenant_check_workspace_capacity($pdo, (int)$invite['organization_id']);
            if (!$capacity['ok']) {
                $inviteError = (string)$capacity['reason'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join Workspace | Task Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-body">
    <?php include "inc/toast.php"; ?>
    <div class="auth-container">
        <!-- Left Side: Branding -->
        <div class="auth-left">
            <div class="auth-left-content">
                <h2>Manage tasks, track time, and boost productivity effortlessly.</h2>
                <p>Empower your team with real-time collaboration, smart task management, and performance insights.</p>
                
                <div class="auth-feature-list">
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">
                            <i class="fa fa-check-circle-o"></i>
                        </div>
                        <div class="auth-feature-text">
                            <h4>Task Management</h4>
                            <p>Create, assign, and track tasks with subtasks and deadlines</p>
                        </div>
                    </div>
                    
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">
                            <i class="fa fa-clock-o"></i>
                        </div>
                        <div class="auth-feature-text">
                            <h4>Time Tracking</h4>
                            <p>Monitor work hours with automatic screen capture for accountability</p>
                        </div>
                    </div>
                    
                    <div class="auth-feature-item">
                        <div class="auth-feature-icon">
                            <i class="fa fa-line-chart"></i>
                        </div>
                        <div class="auth-feature-text">
                            <h4>Performance Analytics</h4>
                            <p>Track team performance with ratings and detailed reports</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="auth-right">
            <div class="auth-logos">
                <img src="img/logo.png" alt="Logo 1" class="auth-logo-img">
                <img src="img/logo2.png" alt="Logo 2" class="auth-logo-img">
            </div>
            <h3 class="auth-title">Join Workspace</h3>
            <p class="auth-subtitle">Create your account and join your team</p>

            <?php if (isset($_GET['error'])) { ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php } ?>

            <?php if ($inviteError !== null) { ?>
                <div class="alert alert-danger"><?= htmlspecialchars($inviteError) ?></div>
                <div class="auth-footer">
                    Back to <a href="login.php" class="auth-link">Login</a>
                </div>
            <?php } else { ?>
                <?php $isOpenLink = invite_is_open_link_email((string)$invite['email']); ?>
                <div class="auth-info-box">
                    You are invited to join <strong><?= htmlspecialchars((string)$invite['organization_name']) ?></strong>
                    as <strong><?= htmlspecialchars((string)$invite['role']) ?></strong>.
                </div>
                <?php if ($isOpenLink) { ?>
                    <div class="auth-info-box">
                        This is a one-time join link. Enter your work email and create your password to join this workspace.
                    </div>
                <?php } ?>

                <form method="POST" action="app/accept-invite.php">
                    <?= csrf_field('accept_workspace_invite_form') ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <?php if ($isOpenLink) { ?>
                            <input
                                type="email"
                                class="form-control"
                                name="email"
                                value="<?= htmlspecialchars($prefillEmail) ?>"
                                placeholder="you@company.com"
                                required
                            >
                        <?php } else { ?>
                            <input type="email" class="form-control" value="<?= htmlspecialchars((string)$invite['email']) ?>" readonly>
                        <?php } ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input
                            type="text"
                            class="form-control"
                            name="full_name"
                            value="<?= htmlspecialchars((string)($invite['full_name'] ?: '')) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-field-wrap">
                            <input
                                type="password"
                                class="form-control"
                                id="join_workspace_password"
                                name="password"
                                placeholder="At least 8 chars, Aa1!"
                                minlength="8"
                                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                                title="Must be at least 8 characters and include uppercase, lowercase, number, and symbol."
                                required
                            >
                            <button type="button" class="password-toggle-btn" data-password-toggle data-target="#join_workspace_password" aria-label="Show password">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="password-field-wrap">
                            <input
                                type="password"
                                class="form-control"
                                id="join_workspace_confirm_password"
                                name="confirm_password"
                                placeholder="Repeat password"
                                minlength="8"
                                required
                            >
                            <button type="button" class="password-toggle-btn" data-password-toggle data-target="#join_workspace_confirm_password" aria-label="Show password">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Join Workspace</button>
                </form>

                <div class="auth-footer">
                    Already have an account? <a href="login.php" class="auth-link">Login</a>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
    (function () {
        var toggles = document.querySelectorAll('[data-password-toggle]');
        if (!toggles.length) {
            return;
        }

        function updateToggleState(button, input) {
            var visible = input.type === 'text';
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            button.setAttribute('title', visible ? 'Hide password' : 'Show password');
            var icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', visible);
                icon.classList.toggle('fa-eye-slash', !visible);
            }
        }

        toggles.forEach(function (button) {
            var target = button.getAttribute('data-target');
            var input = target ? document.querySelector(target) : null;
            if (!input) {
                return;
            }

            updateToggleState(button, input);
            button.addEventListener('click', function () {
                input.type = input.type === 'password' ? 'text' : 'password';
                updateToggleState(button, input);
            });
        });
    })();
    </script>
</body>
</html>
