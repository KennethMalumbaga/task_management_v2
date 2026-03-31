<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once "inc/csrf.php";
require_once "inc/tenant.php";
require_once "app/mail_config.php";

$incomingPlan = strtolower(trim((string)($_GET['plan'] ?? ($_POST['plan_code'] ?? ''))));
$incomingMode = strtolower(trim((string)($_GET['signup_mode'] ?? ($_POST['signup_mode'] ?? ''))));
if (in_array($incomingMode, ['free-trial', 'free_trial'], true)) {
    $incomingMode = 'trial';
}

if (!in_array($incomingMode, ['trial', 'paid'], true)) {
    if ($incomingPlan === '' || in_array($incomingPlan, ['trial', 'free-trial', 'free_trial'], true)) {
        $incomingMode = 'trial';
    } else {
        $incomingMode = 'paid';
    }
}

$planSeed = $incomingPlan !== '' ? $incomingPlan : 'starter';
$selectedPlan = tenant_resolve_workspace_plan($planSeed, 'starter');
$selectedPlanCode = (string)($selectedPlan['code'] ?? 'starter');
$selectedPlanName = (string)($selectedPlan['name'] ?? 'Starter');
$selectedPlanSeatLimit = (int)($selectedPlan['seat_limit'] ?? 10);
$isTrialSignup = $incomingMode === 'trial';
$googleClientId = trim((string)(getenv('GOOGLE_CLIENT_ID') ?: ''));
$googleSignupEnabled = $googleClientId !== '';
$pendingGoogleSignup = isset($_SESSION['pending_google_signup']) && is_array($_SESSION['pending_google_signup'])
    ? $_SESSION['pending_google_signup']
    : null;
$googleSignupActive = false;
if (is_array($pendingGoogleSignup)) {
    $pendingCreatedAt = isset($pendingGoogleSignup['created_at']) ? (int)$pendingGoogleSignup['created_at'] : 0;
    if ($pendingCreatedAt > 0 && (time() - $pendingCreatedAt) <= 1800) {
        $googleSignupActive = true;
    } else {
        unset($_SESSION['pending_google_signup']);
        $pendingGoogleSignup = null;
    }
}
$prefillFullName = $googleSignupActive
    ? trim((string)($pendingGoogleSignup['full_name'] ?? ''))
    : '';
$prefillEmail = $googleSignupActive
    ? trim((string)($pendingGoogleSignup['email'] ?? ''))
    : '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Create Account | Task Management System</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
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
        .google-signup-shell {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
            min-height: 44px;
        }
        .google-signup-note {
            margin: 0 0 16px;
            color: #6B7280;
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }
        .google-signup-identity {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #D1FAE5;
            background: #ECFDF5;
            border-radius: 12px;
            color: #065F46;
            font-size: 14px;
        }
        .google-signup-identity strong {
            display: block;
            margin-bottom: 4px;
            color: #064E3B;
        }
        .google-signup-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            color: #065F46;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }
        .google-signup-switch:hover {
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
                    <h2>Start managing your tasks in minutes.</h2>
                    <p>Create an account and get instant access to powerful task management tools and team collaboration features.</p>
                    
                    <div class="auth-feature-list">
                        <div class="auth-feature-item">
                            <div class="auth-feature-icon">
                                <i class="fa fa-rocket"></i>
                            </div>
                            <div class="auth-feature-text">
                                <h4>Easy Setup</h4>
                                <p>Register in seconds and start collaborating with your team instantly</p>
                            </div>
                        </div>
                        
                        <div class="auth-feature-item">
                            <div class="auth-feature-icon">
                                <i class="fa fa-shield"></i>
                            </div>
                            <div class="auth-feature-text">
                                <h4>Role-Based Access</h4>
                                <p>Choose your role and get appropriate permissions for your workflow</p>
                            </div>
                        </div>
                        
                        <div class="auth-feature-item">
                            <div class="auth-feature-icon">
                                <i class="fa fa-desktop"></i>
                            </div>
                            <div class="auth-feature-text">
                                <h4>Real-time Monitoring</h4>
                                <p>Track your progress and performance with live updates 24/7</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Signup Form -->
            <div class="auth-right">
                <div class="auth-logos">
                    <img src="img/logo.png" alt="Logo 1" class="auth-logo-img">
                    <img src="img/logo2.png" alt="Logo 2" class="auth-logo-img">
                </div>
                <h3 class="auth-title">Create Account</h3>
                <p class="auth-subtitle">Create a new workspace (owner signup)</p>

                <?php if (isset($_GET['error'])) { ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php } ?>

                <div class="auth-info-box">
                    This form is for workspace owners/admins. Employees should join using an invite link from their admin.
                    <?php if ($isTrialSignup) { ?>
                        <br><strong>Selected offer:</strong> Free Trial (2 days, up to <?= $selectedPlanSeatLimit ?> team members).
                        <br>No payment is required before first login. After trial ends, billing lock will require plan payment.
                    <?php } else { ?>
                        <br><strong>Selected plan:</strong> <?= htmlspecialchars($selectedPlanName) ?> (up to <?= $selectedPlanSeatLimit ?> team members).
                        <br>After signup, you will continue to dummy checkout before first login.
                    <?php } ?>
                </div>

                <?php if ($googleSignupActive) { ?>
                    <div class="google-signup-identity">
                        <strong>Google account connected</strong>
                        <?= htmlspecialchars($prefillFullName) ?><br>
                        <?= htmlspecialchars($prefillEmail) ?>
                        <a class="google-signup-switch" href="app/google-signup-clear.php?plan=<?= urlencode($selectedPlanCode) ?>&signup_mode=<?= urlencode($incomingMode) ?>">
                            <i class="fa fa-refresh"></i> Use another Google account
                        </a>
                    </div>
                <?php } elseif ($googleSignupEnabled) { ?>
                    <div class="auth-social-divider"><span>or</span></div>
                    <form method="POST" action="app/google-signup-init.php" id="google-signup-init-form">
                        <?= csrf_field('google_signup_init_form') ?>
                        <input type="hidden" name="plan_code" value="<?= htmlspecialchars($selectedPlanCode) ?>">
                        <input type="hidden" name="signup_mode" value="<?= htmlspecialchars($incomingMode) ?>">
                        <input type="hidden" name="credential" id="google_signup_credential" value="">
                    </form>
                    <div class="google-signup-shell">
                        <div id="google-signup-button"></div>
                    </div>
                    <p class="google-signup-note">Continue with Google first, then finish your workspace details below.</p>
                <?php } ?>

                <form method="POST" action="app/signup.php">
                    <?= csrf_field('signup_form') ?>
                    <input type="hidden" name="plan_code" value="<?= htmlspecialchars($selectedPlanCode) ?>">
                    <input type="hidden" name="signup_mode" value="<?= htmlspecialchars($incomingMode) ?>">
                    <div class="form-group">
                        <label class="form-label">Workspace Name</label>
                        <input type="text" class="form-control" name="organization_name" placeholder="Acme Team" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" placeholder="John Doe" required value="<?= htmlspecialchars($prefillFullName) ?>" <?= $googleSignupActive ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="user_name" placeholder="you@example.com" required value="<?= htmlspecialchars($prefillEmail) ?>" <?= $googleSignupActive ? 'readonly' : '' ?>>
                    </div>

                    <?php if (!$googleSignupActive) { ?>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-field-wrap">
                            <input type="password" class="form-control" id="signup_password" name="password" placeholder="Create a secure password" required minlength="8">
                            <button type="button" class="password-toggle-btn" data-password-toggle data-target="#signup_password" aria-label="Show password">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small style="display:block; margin-top:8px; color:#6B7280;">
                            Must be at least 8 characters with uppercase, lowercase, number, and symbol.
                        </small>
                    </div>
                    <?php } else { ?>
                    <div class="auth-info-box" style="margin-bottom: 18px;">
                        Your Google account will be used as the owner identity. You can add a regular password later if you want.
                    </div>
                    <?php } ?>
                     
                    <button type="submit" class="btn-primary">Create Workspace</button>
                </form>

                <div class="auth-footer">
                    Already have an account? <a href="login.php" class="auth-link">Log In</a>
                </div>
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

      <?php if ($googleSignupEnabled && !$googleSignupActive) { ?>
      window.handleGoogleSignupResponse = function (response) {
          var credentialInput = document.getElementById('google_signup_credential');
          var form = document.getElementById('google-signup-init-form');
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
                  callback: window.handleGoogleSignupResponse
              });
              google.accounts.id.renderButton(
                  document.getElementById('google-signup-button'),
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
      <?php if ($googleSignupEnabled) { ?>
      <script src="https://accounts.google.com/gsi/client" async defer></script>
      <?php } ?>
</body>
</html>
