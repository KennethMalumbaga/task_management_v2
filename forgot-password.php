<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Forgot Password | Task Management System</title>
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
                <i class="fa fa-low-vision"></i>
            </div>
            <h3 class="auth-title">Forgot Password</h3>
            <p class="auth-subtitle">Enter your email to reset your password</p>

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

            <form method="POST" action="app/req-reset-password.php">
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                </div>
                
                <button type="submit" class="btn-primary">Send Reset Link</button>
            </form>

            <div class="auth-footer">
                Remember your password? <a href="login.php" class="auth-link">Login</a>
            </div>
      </div>
</body>
</html>
