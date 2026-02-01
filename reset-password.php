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
      
      <div class="auth-container">
            <div class="auth-icon">
                <i class="fa fa-lock"></i>
            </div>
            <h3 class="auth-title">Reset Password</h3>
            <p class="auth-subtitle">Enter your new password</p>

            <?php if (isset($_GET['error'])) { ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php } ?>
            
            <?php if (isset($_GET['token'])) { 
                 $token = $_GET['token'];
            ?>
            <form method="POST" action="app/do-reset-password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="New Password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
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
</body>
</html>
