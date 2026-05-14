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
$selectedPlanSeatDisplay = (string)($selectedPlan['seat_display'] ?? tenant_format_seat_limit($selectedPlanSeatLimit, 'N/A'));
$isEnterpriseSignup = $selectedPlanCode === 'enterprise';
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
      <?php include_once __DIR__ . "/inc/loading_screen.php"; ?>
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
                        <br><strong>Selected offer:</strong> Free Trial (2 days, <?= htmlspecialchars($selectedPlanSeatDisplay) ?> team members).
                        <?php if ($isEnterpriseSignup) { ?>
                            <br>Enterprise capacity requests are reviewed after paid checkout.
                        <?php } ?>
                        <br>No payment is required before first login. After trial ends, billing lock will require plan payment.
                    <?php } else { ?>
                        <br><strong>Selected plan:</strong> <?= htmlspecialchars($selectedPlanName) ?> (<?= htmlspecialchars($selectedPlanSeatDisplay) ?> team members).
                        <?php if ($isEnterpriseSignup) { ?>
                            <br>Enter the workspace capacity you want. Super Admin will review it after payment.
                        <?php } ?>
                        <br>After signup, you will continue to PayMongo test checkout before first login.
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

                    <?php if ($isEnterpriseSignup) { ?>
                    <div class="form-group">
                        <label class="form-label">Requested Workspace Capacity</label>
                        <input
                            type="number"
                            class="form-control"
                            name="enterprise_requested_capacity"
                            min="40"
                            max="100000"
                            step="1"
                            placeholder="Example: 75"
                            required
                        >
                        <small style="display:block; margin-top:8px; color:#6B7280;">
                            Minimum 40 members. Super Admin approves the final Enterprise capacity after payment.
                        </small>
                    </div>
                    <?php } ?>

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
                    <div class="terms-consent">
                        <label class="terms-consent-label" for="accept_terms">
                            <input type="checkbox" id="accept_terms" name="accept_terms" value="1" required>
                            <span>
                                I have read and agree to the
                                <a href="#" id="open-terms-modal" class="terms-modal-link">Terms and Conditions</a>.
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary" id="create-workspace-btn" disabled>Create Workspace</button>
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

      <!-- Terms and Conditions Modal -->
      <div id="terms-modal" class="tc-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="tc-modal-title" hidden>
          <div class="tc-modal-box">
              <div class="tc-modal-header">
                  <div>
                      <span class="tc-brand">TaskFlow</span>
                      <h2 id="tc-modal-title">Terms and Conditions</h2>
                  </div>
                  <div class="tc-modal-header-actions">
                      <a href="docs/NSC-TaskFlow-Terms__Conditions.pdf" target="_blank" rel="noopener" class="tc-btn-outline">
                          <i class="fa fa-file-pdf-o"></i> Open PDF
                      </a>
                      <button type="button" id="close-terms-modal" class="tc-close-btn" aria-label="Close">
                          <i class="fa fa-times"></i>
                      </button>
                  </div>
              </div>
              <div class="tc-modal-body">
                  <div class="tc-content">
                      <div class="tc-org-header">
                          <div class="tc-org-info">
                              <strong>Nehemiah Solutions</strong><br>
                              2<sup>nd</sup> Floor DDTC Building, Juan Luna Street, Davao City<br>
                              Website: www.nehemiahsolutions.com<br>
                              Email: nehemiah.solutions.corp@gmail.com &nbsp;|&nbsp; 0916-264-6505
                          </div>
                      </div>

                      <h3 class="tc-doc-title">TASKFLOW: TERMS AND CONDITIONS OF REGISTRATION</h3>

                      <p>Welcome to TaskFlow! TaskFlow is a task and workflow management platform designed to help organizations, teams, and individuals manage projects, assignments, collaboration, productivity, and operational workflows.</p>
                      <p>By accessing, registering, or using the platform, you acknowledge that you have read, understood, and agreed to comply with the following Terms and Conditions:</p>

                      <div class="tc-section">
                          <h4>1. Compliance with the Data Privacy Act of 2012</h4>
                          <p>TaskFlow values and protects the privacy and confidentiality of user and organizational information. All personal data collected, stored, processed, and transmitted through the platform shall be handled in accordance with the Data Privacy Act of 2012 and other applicable laws and regulations.</p>
                          <p>By using the platform, users consent to the collection, storage, processing, and use of their information for legitimate operational, communication, collaboration, reporting, security, and administrative purposes.</p>
                      </div>

                      <div class="tc-section">
                          <h4>2. Authorized Use of the Platform</h4>
                          <p>TaskFlow is intended for lawful and authorized project management, workflow coordination, team collaboration, and productivity-related activities. Users agree not to use the platform for illegal, harmful, fraudulent, abusive, or unauthorized purposes.</p>
                          <p>Any attempt to disrupt, manipulate, reverse-engineer, or gain unauthorized access to the platform or other users' accounts is strictly prohibited and may result in immediate account suspension and legal action.</p>
                      </div>

                      <div class="tc-section">
                          <h4>3. User Account and Workspace Responsibilities</h4>
                          <p>Workspace owners are responsible for maintaining the security of their account credentials. You must not share your login credentials with unauthorized individuals. You are responsible for all activities that occur under your account.</p>
                          <p>Workspace owners are responsible for managing their team members, assigning roles and permissions appropriately, and ensuring that all users under their workspace comply with these Terms and Conditions.</p>
                          <p>TaskFlow reserves the right to suspend or terminate accounts found to be in violation of these terms, without prior notice.</p>
                      </div>

                      <div class="tc-section">
                          <h4>4. Data Ownership and Confidentiality</h4>
                          <p>All data, tasks, files, and content created within a workspace remain the property of the respective workspace owner and organization. TaskFlow does not claim ownership over user-generated content.</p>
                          <p>Users agree to maintain the confidentiality of organizational data accessed through the platform and not to disclose, copy, or distribute such information to unauthorized parties.</p>
                      </div>

                      <div class="tc-section">
                          <h4>5. Intellectual Property</h4>
                          <p>All platform features, designs, interfaces, trademarks, and software components of TaskFlow are the intellectual property of Nehemiah Solutions. Users are granted a limited, non-exclusive, non-transferable license to use the platform solely for its intended purpose.</p>
                          <p>Reproduction, distribution, modification, or creation of derivative works from any part of the TaskFlow platform without explicit written consent is strictly prohibited.</p>
                      </div>

                      <div class="tc-section">
                          <h4>6. Service Availability and Modifications</h4>
                          <p>TaskFlow strives to maintain high availability and performance. However, Nehemiah Solutions does not guarantee uninterrupted access and shall not be liable for downtime caused by maintenance, technical issues, or circumstances beyond its control.</p>
                          <p>Nehemiah Solutions reserves the right to modify, update, or discontinue any features of TaskFlow at any time. Users will be notified of significant changes where reasonably practicable.</p>
                      </div>

                      <div class="tc-section">
                          <h4>7. Limitation of Liability</h4>
                          <p>To the fullest extent permitted by applicable law, Nehemiah Solutions shall not be liable for any indirect, incidental, special, or consequential damages arising from the use or inability to use the platform, including but not limited to loss of data, revenue, or business opportunities.</p>
                          <p>Users acknowledge that they use the platform at their own risk and that Nehemiah Solutions' total liability, if any, shall not exceed the amount paid by the user for the applicable subscription period.</p>
                      </div>

                      <div class="tc-section">
                          <h4>8. Termination</h4>
                          <p>Either party may terminate the account or subscription at any time in accordance with the chosen plan terms. Upon termination, access to the workspace and its data will be revoked. Workspace owners are advised to export their data prior to account closure.</p>
                          <p>Nehemiah Solutions reserves the right to terminate or suspend access immediately if there is evidence of a violation of these Terms and Conditions or applicable law.</p>
                      </div>

                      <div class="tc-section">
                          <h4>9. Governing Law</h4>
                          <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes arising from the use of the platform shall be subject to the exclusive jurisdiction of the courts in Davao City, Philippines.</p>
                      </div>

                      <div class="tc-section">
                          <h4>10. Contact Information</h4>
                          <p>For questions, concerns, or data privacy requests related to these Terms and Conditions, please contact:</p>
                          <ul>
                              <li><strong>Nehemiah Solutions</strong></li>
                              <li>2<sup>nd</sup> Floor DDTC Building, Juan Luna Street, Davao City</li>
                              <li>Email: nehemiah.solutions.corp@gmail.com</li>
                              <li>Phone: 0916-264-6505</li>
                              <li>Website: www.nehemiahsolutions.com</li>
                          </ul>
                      </div>

                      <p class="tc-effective">By registering and using TaskFlow, you confirm that you have read, understood, and agreed to these Terms and Conditions.</p>
                  </div>
              </div>
              <div class="tc-modal-footer">
                  <a href="docs/NSC-TaskFlow-Terms__Conditions.pdf" target="_blank" rel="noopener" class="tc-btn-outline">
                      <i class="fa fa-download"></i> Download PDF
                  </a>
                  <button type="button" id="agree-terms-btn" class="tc-btn-agree">
                      <i class="fa fa-check"></i> I Agree &amp; Close
                  </button>
              </div>
          </div>
      </div>

      <style>
      .terms-modal-link {
          color: #6C3CE1;
          font-weight: 600;
          text-decoration: underline;
      }
      .tc-modal-overlay {
          position: fixed;
          inset: 0;
          z-index: 9999;
          background: rgba(17, 24, 39, 0.6);
          backdrop-filter: blur(2px);
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 16px;
          animation: tcFadeIn 0.18s ease;
      }
      .tc-modal-overlay[hidden] {
          display: none;
      }
      @keyframes tcFadeIn {
          from { opacity: 0; }
          to   { opacity: 1; }
      }
      .tc-modal-box {
          background: #fff;
          border-radius: 16px;
          width: 100%;
          max-width: 760px;
          max-height: 90vh;
          display: flex;
          flex-direction: column;
          box-shadow: 0 25px 60px rgba(0,0,0,0.2);
          animation: tcSlideUp 0.2s ease;
          overflow: hidden;
      }
      @keyframes tcSlideUp {
          from { transform: translateY(24px); opacity: 0; }
          to   { transform: translateY(0);    opacity: 1; }
      }
      .tc-modal-header {
          display: flex;
          align-items: flex-start;
          justify-content: space-between;
          gap: 12px;
          padding: 20px 24px 16px;
          border-bottom: 1px solid #E5E7EB;
          flex-shrink: 0;
      }
      .tc-brand {
          display: block;
          color: #6C3CE1;
          font-size: 13px;
          font-weight: 700;
          letter-spacing: 0.03em;
          margin-bottom: 2px;
      }
      .tc-modal-header h2 {
          margin: 0;
          font-size: 20px;
          font-weight: 700;
          color: #111827;
      }
      .tc-modal-header-actions {
          display: flex;
          align-items: center;
          gap: 8px;
          flex-shrink: 0;
      }
      .tc-btn-outline {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          padding: 7px 12px;
          border: 1px solid #D1D5DB;
          border-radius: 8px;
          background: #fff;
          color: #374151;
          font-size: 13px;
          font-weight: 500;
          text-decoration: none;
          cursor: pointer;
          transition: background 0.15s, border-color 0.15s;
          white-space: nowrap;
      }
      .tc-btn-outline:hover {
          background: #F9FAFB;
          border-color: #9CA3AF;
          color: #111827;
      }
      .tc-close-btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 32px;
          height: 32px;
          border: none;
          border-radius: 8px;
          background: #F3F4F6;
          color: #6B7280;
          cursor: pointer;
          font-size: 14px;
          transition: background 0.15s;
      }
      .tc-close-btn:hover {
          background: #E5E7EB;
          color: #374151;
      }
      .tc-modal-body {
          overflow-y: auto;
          flex: 1;
          padding: 0;
      }
      .tc-content {
          padding: 24px;
          font-family: 'Inter', Arial, sans-serif;
          font-size: 13.5px;
          color: #374151;
          line-height: 1.7;
      }
      .tc-org-header {
          border: 1px solid #E5E7EB;
          border-radius: 10px;
          padding: 14px 18px;
          background: #F9FAFB;
          margin-bottom: 20px;
          font-size: 13px;
          color: #4B5563;
          line-height: 1.6;
      }
      .tc-org-header strong {
          color: #111827;
          font-size: 14px;
      }
      .tc-doc-title {
          font-size: 16px;
          font-weight: 700;
          color: #111827;
          text-align: center;
          margin: 0 0 18px;
          text-transform: uppercase;
          letter-spacing: 0.02em;
      }
      .tc-content > p {
          margin: 0 0 12px;
          color: #4B5563;
      }
      .tc-section {
          margin-bottom: 18px;
      }
      .tc-section h4 {
          margin: 0 0 6px;
          font-size: 14px;
          font-weight: 700;
          color: #111827;
      }
      .tc-section p {
          margin: 0 0 8px;
          color: #4B5563;
      }
      .tc-section ul {
          margin: 6px 0 0 18px;
          padding: 0;
          color: #4B5563;
      }
      .tc-section ul li {
          margin-bottom: 4px;
      }
      .tc-effective {
          margin-top: 20px !important;
          padding: 14px 16px;
          background: #EDE9FE;
          border-left: 3px solid #6C3CE1;
          border-radius: 6px;
          color: #4C1D95 !important;
          font-weight: 500;
          font-size: 13px;
      }
      .tc-modal-footer {
          display: flex;
          align-items: center;
          justify-content: flex-end;
          gap: 10px;
          padding: 14px 24px;
          border-top: 1px solid #E5E7EB;
          background: #F9FAFB;
          flex-shrink: 0;
      }
      .tc-btn-agree {
          display: inline-flex;
          align-items: center;
          gap: 7px;
          padding: 10px 20px;
          border: none;
          border-radius: 8px;
          background: #6C3CE1;
          color: #fff;
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
          transition: background 0.15s;
      }
      .tc-btn-agree:hover {
          background: #5a30c5;
      }
      @media (max-width: 640px) {
          .tc-modal-box { border-radius: 12px; max-height: 95vh; }
          .tc-modal-header { padding: 16px 16px 12px; }
          .tc-content { padding: 16px; }
          .tc-modal-footer { padding: 12px 16px; flex-wrap: wrap; }
          .tc-btn-outline span { display: none; }
      }
      </style>

      <script>
      (function () {
          var overlay = document.getElementById('terms-modal');
          var openBtn = document.getElementById('open-terms-modal');
          var closeBtn = document.getElementById('close-terms-modal');
          var agreeBtn = document.getElementById('agree-terms-btn');
          var checkbox = document.getElementById('accept_terms');
          var submitBtn = document.getElementById('create-workspace-btn');

          function updateSubmitState() {
              if (!submitBtn || !checkbox) {
                  return;
              }
              submitBtn.disabled = !checkbox.checked;
          }

          function openModal(e) {
              if (e) e.preventDefault();
              overlay.removeAttribute('hidden');
              document.body.style.overflow = 'hidden';
              closeBtn.focus();
          }

          function closeModal() {
              overlay.setAttribute('hidden', '');
              document.body.style.overflow = '';
              if (openBtn) openBtn.focus();
          }

          if (openBtn)  openBtn.addEventListener('click', openModal);
          if (closeBtn) closeBtn.addEventListener('click', closeModal);

          if (agreeBtn) {
              agreeBtn.addEventListener('click', function () {
                  if (checkbox) checkbox.checked = true;
                  updateSubmitState();
                  closeModal();
              });
          }

          if (checkbox) {
              checkbox.addEventListener('change', updateSubmitState);
              updateSubmitState();
          }

          overlay.addEventListener('click', function (e) {
              if (e.target === overlay) closeModal();
          });

          document.addEventListener('keydown', function (e) {
              if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) closeModal();
          });
      })();
      </script>
</body>
</html>
