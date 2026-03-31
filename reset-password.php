<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once "inc/csrf.php";
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reset Password | Task Management System</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-body">
      <?php include "inc/toast.php"; ?>
      
      <div class="auth-container auth-container-single">
            <div class="auth-right auth-right-single">
                <a href="login.php" class="back-link">
                    <i class="fa fa-arrow-left"></i> Back to Login
                </a>

                <div class="auth-form-shell">
                    <div class="auth-card-header">
                        <div class="auth-icon">
                            <i class="fa fa-lock"></i>
                        </div>
                        <div>
                            <h3 class="auth-title">Reset Password</h3>
                            <p class="auth-subtitle">Create a strong new password for your account.</p>
                        </div>
                    </div>

                    <?php if (isset($_GET['error'])) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                    <?php } ?>
                    
                    <?php if (isset($_GET['token'])) { 
                         $token = $_GET['token'];
                    ?>
                    <form method="POST" action="app/do-reset-password.php">
                        <?= csrf_field('do_reset_password_form') ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div class="password-field-wrap">
                                <input
                                    type="password"
                                    class="form-control"
                                    id="reset_new_password"
                                    name="new_password"
                                    placeholder="At least 8 chars, Aa1!"
                                    minlength="8"
                                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                                    title="Must be at least 8 characters and include uppercase, lowercase, number, and symbol."
                                    required
                                >
                                <button type="button" class="password-toggle-btn" data-password-toggle data-target="#reset_new_password" aria-label="Show password">
                                    <i class="fa fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <p class="auth-helper-text">
                            Use at least 8 characters with uppercase, lowercase, number, and symbol.
                        </p>

                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <div class="password-field-wrap">
                                <input
                                    type="password"
                                    class="form-control"
                                    id="reset_confirm_password"
                                    name="confirm_password"
                                    placeholder="Repeat your new password"
                                    minlength="8"
                                    required
                                >
                                <button type="button" class="password-toggle-btn" data-password-toggle data-target="#reset_confirm_password" aria-label="Show password">
                                    <i class="fa fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">Reset Password</button>
                    </form>
                    <?php } else { ?>
                         <div class="alert alert-danger" role="alert">
                            Invalid request. Token missing.
                        </div>
                    <?php } ?>

                    <div class="auth-footer">
                        Back to <a href="login.php" class="auth-link">Login</a>
                    </div>
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
      </script>
</body>
</html>
