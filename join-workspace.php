<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
include "DB_connection.php";
require_once "inc/tenant.php";
require_once "inc/csrf.php";
require_once "app/invite_helpers.php";
require_once "app/mail_config.php";

$token = trim((string)($_GET['token'] ?? ''));
$invite = null;
$inviteError = null;
$prefillEmail = trim((string)($_GET['email'] ?? ''));
$googleClientId = trim((string)(getenv('GOOGLE_CLIENT_ID') ?: ''));
$googleInviteEnabled = $googleClientId !== '';
$pendingGoogleInvite = isset($_SESSION['pending_google_invite_accept']) && is_array($_SESSION['pending_google_invite_accept'])
    ? $_SESSION['pending_google_invite_accept']
    : null;
$googleInviteActive = false;

if (is_array($pendingGoogleInvite)) {
    $pendingCreatedAt = isset($pendingGoogleInvite['created_at']) ? (int)$pendingGoogleInvite['created_at'] : 0;
    $pendingToken = trim((string)($pendingGoogleInvite['token'] ?? ''));

    if ($pendingCreatedAt > 0 && (time() - $pendingCreatedAt) <= 1800 && $pendingToken !== '' && $token !== '' && hash_equals($pendingToken, $token)) {
        $googleInviteActive = true;
    } else {
        unset($_SESSION['pending_google_invite_accept']);
        $pendingGoogleInvite = null;
    }
}

$googleInviteEmail = $googleInviteActive
    ? strtolower(trim((string)($pendingGoogleInvite['email'] ?? '')))
    : '';
$googleInviteFullName = $googleInviteActive
    ? trim((string)($pendingGoogleInvite['full_name'] ?? ''))
    : '';

if ($googleInviteActive && $googleInviteEmail !== '') {
    $prefillEmail = $googleInviteEmail;
}

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
    <style>
        .auth-social-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: #9CA3AF;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .auth-social-divider::before,
        .auth-social-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #E5E7EB;
        }
        .google-invite-shell {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
            min-height: 44px;
        }
        .google-invite-note {
            margin: 0 0 16px;
            color: #6B7280;
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }
        .google-invite-identity {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #D1FAE5;
            background: #ECFDF5;
            border-radius: 12px;
            color: #065F46;
            font-size: 14px;
        }
        .google-invite-identity strong {
            display: block;
            margin-bottom: 4px;
            color: #064E3B;
        }
        .google-invite-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            color: #065F46;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }
        .google-invite-switch:hover {
            color: #047857;
        }
    </style>
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
            <?php if (isset($_GET['success'])) { ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
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
                        This is a one-time join link. Enter your work email and create your password, or continue with Google to join this workspace faster.
                    </div>
                <?php } ?>
                <?php if ($googleInviteActive) { ?>
                    <div class="google-invite-identity">
                        <strong>Google account connected</strong>
                        <?= htmlspecialchars($googleInviteFullName !== '' ? $googleInviteFullName : invite_guess_name_from_email($googleInviteEmail)) ?><br>
                        <?= htmlspecialchars($googleInviteEmail) ?>
                        <a class="google-invite-switch" href="app/google-invite-clear.php?token=<?= urlencode($token) ?>">
                            <i class="fa fa-refresh"></i> Use another Google account
                        </a>
                    </div>
                <?php } elseif ($googleInviteEnabled) { ?>
                    <div class="auth-social-divider"><span>or</span></div>
                    <form method="POST" action="app/google-invite-init.php" id="google-invite-init-form">
                        <?= csrf_field('google_invite_init_form') ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="credential" id="google_invite_credential" value="">
                    </form>
                    <div class="google-invite-shell">
                        <div id="google-invite-button"></div>
                    </div>
                    <p class="google-invite-note">
                        <?php if ($isOpenLink) { ?>
                            Continue with Google to use that Google email for this one-time join link.
                        <?php } else { ?>
                            Continue with the invited Google email for faster workspace access.
                        <?php } ?>
                    </p>
                <?php } ?>

                <form method="POST" action="app/accept-invite.php">
                    <?= csrf_field('accept_workspace_invite_form') ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <?php if ($googleInviteActive) { ?>
                            <input
                                type="email"
                                class="form-control"
                                value="<?= htmlspecialchars($googleInviteEmail) ?>"
                                readonly
                            >
                        <?php } elseif ($isOpenLink) { ?>
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
                            value="<?= htmlspecialchars($googleInviteActive ? ($googleInviteFullName !== '' ? $googleInviteFullName : (string)($invite['full_name'] ?: '')) : (string)($invite['full_name'] ?: '')) ?>"
                            required
                        >
                    </div>

                    <?php if ($googleInviteActive) { ?>
                        <div class="auth-info-box" style="margin-bottom: 18px;">
                            Your Google account will be used for this workspace member account. After joining, you can sign in from the login page with Google.
                        </div>
                    <?php } else { ?>
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
                    <?php } ?>

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

    <?php if ($googleInviteEnabled && !$googleInviteActive) { ?>
    window.handleGoogleInviteResponse = function (response) {
        var credentialInput = document.getElementById('google_invite_credential');
        var form = document.getElementById('google-invite-init-form');
        if (!credentialInput || !form || !response || !response.credential) {
            return;
        }
        credentialInput.value = response.credential;
        form.submit();
    };

    window.onload = (function (previousOnload) {
        return function () {
            if (typeof previousOnload === 'function') {
                previousOnload();
            }
            if (!window.google || !google.accounts || !google.accounts.id) {
                return;
            }
            google.accounts.id.initialize({
                client_id: <?= json_encode($googleClientId) ?>,
                callback: window.handleGoogleInviteResponse
            });
            google.accounts.id.renderButton(
                document.getElementById('google-invite-button'),
                {
                    theme: 'outline',
                    size: 'large',
                    type: 'standard',
                    text: 'signup_with',
                    shape: 'rectangular',
                    width: 320
                }
            );
        };
    })(window.onload);
    <?php } ?>
    </script>
    <?php if ($googleInviteEnabled) { ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php } ?>
</body>
</html>
