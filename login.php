<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once "inc/csrf.php";

$hasPendingVerification = isset($_SESSION['pending_login_verification']) && is_array($_SESSION['pending_login_verification']);
$pendingVerification = $hasPendingVerification ? $_SESSION['pending_login_verification'] : [];
$verificationEmailMasked = $hasPendingVerification ? (string)($pendingVerification['email_masked'] ?? '') : '';
$verificationError = isset($_GET['verify_error']) ? trim((string)$_GET['verify_error']) : '';
$verificationSuccess = isset($_GET['verify_success']) ? trim((string)$_GET['verify_success']) : '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login | Task Management System</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/auth.css">
    <style>
        .login-back-link-fixed {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4B5563;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 8px 12px;
            backdrop-filter: blur(2px);
        }
        .login-back-link-fixed:hover {
            color: #111827;
            border-color: #D1D5DB;
        }
        @media (max-width: 768px) {
            .login-back-link-fixed {
                top: 10px;
                left: 10px;
                font-size: 13px;
                padding: 7px 10px;
            }
        }
        .verification-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .verification-modal-card {
            width: 100%;
            max-width: 430px;
            background: #fff;
            border-radius: 14px;
            padding: 28px 24px 22px;
            box-shadow: 0 24px 60px rgba(17, 24, 39, 0.3);
            text-align: center;
        }
        .verification-title {
            margin: 0 0 8px;
            color: #5d69c7;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .verification-subtitle {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 15px;
        }
        .verification-subtitle strong {
            color: #4f46e5;
            word-break: break-all;
        }
        .verification-code-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 16px 0 10px;
        }
        .verification-digit {
            height: 62px;
            text-align: center;
            font-size: 34px;
            font-weight: 800;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f1f3f8;
            color: #5d69c7;
            outline: none;
        }
        .verification-digit:focus {
            border-color: #8b92df;
            box-shadow: 0 0 0 3px rgba(139, 146, 223, 0.25);
        }
        .verification-resend {
            margin: 6px 0 14px;
            color: #6b7280;
            font-size: 14px;
        }
        .verification-resend form {
            display: inline;
        }
        .verification-resend-btn {
            background: none;
            border: none;
            color: #5d69c7;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
            text-decoration: underline;
        }
        .verification-resend-btn:hover {
            color: #4f46e5;
        }
        .verification-submit {
            width: 100%;
            background: linear-gradient(90deg, #a7afff, #8e7cf6);
            border: none;
            border-radius: 10px;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            font-weight: 700;
            height: 48px;
            cursor: pointer;
        }
        .verification-submit:hover {
            filter: brightness(0.97);
        }
        .verification-alert {
            margin: 10px 0;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            text-align: left;
        }
        .verification-alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }
        .verification-alert.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .verification-alert.client {
            display: none;
        }
        @media (max-width: 500px) {
            .verification-title {
                font-size: 25px;
            }
            .verification-digit {
                height: 56px;
                font-size: 30px;
            }
        }
    </style>
</head>
<body class="auth-body">
      <?php include "inc/toast.php"; ?>

      <a href="landing.php" class="login-back-link-fixed">
          <i class="fa fa-arrow-left"></i> Back to Landing
      </a>

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

            <!-- Right Side: Login Form -->
            <div class="auth-right">
                <div class="auth-logos">
                    <img src="img/logo.png" alt="Logo 1" class="auth-logo-img">
                    <img src="img/logo2.png" alt="Logo 2" class="auth-logo-img">
                </div>
                <h3 class="auth-title">Welcome Back</h3>
                <p class="auth-subtitle">Task Management System</p>

                <?php if (isset($_GET['error'])) { ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php } ?>
                
                <?php if (isset($_GET['success'])) { ?>
                    <div class="alert alert-success" role="alert">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php } ?>

                <form method="POST" action="app/login.php">
                    <?= csrf_field('login_form') ?>
                    
                    <?php if (isset($_GET['first_time'])) { ?>
                        <div class="auth-info-box">
                            <strong>First time here?</strong> Create an account to explore the full-featured task management system with role-based access, time tracking, and team collaboration.
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" name="user_name" placeholder="you@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-field-wrap">
                            <input type="password" class="form-control" id="login_password" name="password" placeholder="........" required>
                            <button type="button" class="password-toggle-btn" data-password-toggle data-target="#login_password" aria-label="Show password">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px; text-align: right;">
                        <a href="forgot-password.php" style="color: #666; font-size: 14px; text-decoration: none;">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-primary">Log In</button>
                </form>

                <div class="auth-footer">
                    Need a workspace? <a href="signup.php" class="auth-link">Create one</a><br>
                    Got an invite link? Open it and set your password to join your team.
                </div>
            </div>
      </div>

      <?php if ($hasPendingVerification) { ?>
      <div class="verification-modal-overlay" id="verification-modal" role="dialog" aria-modal="true" aria-labelledby="verification-title">
          <div class="verification-modal-card">
              <h4 class="verification-title" id="verification-title">Enter Verification Code</h4>
              <p class="verification-subtitle">
                  We have sent a code to <strong><?= htmlspecialchars($verificationEmailMasked) ?></strong>
              </p>

              <?php if ($verificationSuccess !== '') { ?>
                  <div class="verification-alert success"><?= htmlspecialchars($verificationSuccess) ?></div>
              <?php } ?>
              <?php if ($verificationError !== '') { ?>
                  <div class="verification-alert error"><?= htmlspecialchars($verificationError) ?></div>
              <?php } ?>
              <div class="verification-alert error client" id="verification-client-error"></div>

              <form method="POST" action="app/verify-login-code.php" id="verification-code-form" autocomplete="off">
                  <?= csrf_field('verify_login_code_form') ?>
                  <input type="hidden" name="verification_code" id="verification_code" value="">

                  <div class="verification-code-grid">
                      <input type="text" class="verification-digit" maxlength="1" inputmode="numeric" autocomplete="one-time-code" aria-label="Code digit 1">
                      <input type="text" class="verification-digit" maxlength="1" inputmode="numeric" aria-label="Code digit 2">
                      <input type="text" class="verification-digit" maxlength="1" inputmode="numeric" aria-label="Code digit 3">
                      <input type="text" class="verification-digit" maxlength="1" inputmode="numeric" aria-label="Code digit 4">
                  </div>

                  <button type="submit" class="verification-submit">Verify</button>
              </form>

              <div class="verification-resend">
                  Didn&apos;t get a code?
                  <form method="POST" action="app/resend-login-code.php">
                      <?= csrf_field('resend_login_code_form') ?>
                      <button type="submit" class="verification-resend-btn">Click to Resend</button>
                  </form>
              </div>
          </div>
      </div>

      <script>
      (function () {
          var form = document.getElementById('verification-code-form');
          if (!form) {
              return;
          }

          var inputs = Array.prototype.slice.call(form.querySelectorAll('.verification-digit'));
          var hiddenInput = document.getElementById('verification_code');
          var clientError = document.getElementById('verification-client-error');

          function normalizeValue(raw) {
              return String(raw || '').replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
          }

          function updateHiddenCode() {
              hiddenInput.value = inputs.map(function (input) {
                  return normalizeValue(input.value).slice(0, 1);
              }).join('');
          }

          function showClientError(message) {
              if (!clientError) {
                  return;
              }
              clientError.textContent = message;
              clientError.style.display = message ? 'block' : 'none';
          }

          inputs.forEach(function (input, index) {
              input.addEventListener('input', function () {
                  var value = normalizeValue(input.value);
                  input.value = value.slice(0, 1);
                  updateHiddenCode();

                  if (input.value !== '' && index < inputs.length - 1) {
                      inputs[index + 1].focus();
                  }
              });

              input.addEventListener('keydown', function (event) {
                  if (event.key === 'Backspace' && input.value === '' && index > 0) {
                      inputs[index - 1].focus();
                  }
                  if (event.key === 'ArrowLeft' && index > 0) {
                      event.preventDefault();
                      inputs[index - 1].focus();
                  }
                  if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                      event.preventDefault();
                      inputs[index + 1].focus();
                  }
              });

              input.addEventListener('paste', function (event) {
                  event.preventDefault();
                  var pasted = normalizeValue((event.clipboardData || window.clipboardData).getData('text'));
                  if (!pasted) {
                      return;
                  }

                  var chars = pasted.split('').slice(0, inputs.length);
                  inputs.forEach(function (field, i) {
                      field.value = chars[i] ? chars[i] : '';
                  });
                  updateHiddenCode();

                  var nextIndex = Math.min(chars.length, inputs.length - 1);
                  inputs[nextIndex].focus();
              });
          });

          form.addEventListener('submit', function (event) {
              updateHiddenCode();
              if (hiddenInput.value.length !== 4) {
                  event.preventDefault();
                  showClientError('Enter the full 4-digit verification code.');
                  inputs[0].focus();
                  return;
              }
              showClientError('');
          });

          setTimeout(function () {
              inputs[0].focus();
          }, 40);
      })();
      </script>
      <?php } ?>

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
                  icon.classList.toggle('fa-eye', !visible);
                  icon.classList.toggle('fa-eye-slash', visible);
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
