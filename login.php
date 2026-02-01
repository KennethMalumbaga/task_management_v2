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
</head>
<body class="auth-body">
      
      <div class="auth-container">
            <div class="auth-icon">
                <i class="fa fa-sign-in"></i> <!-- Using sign in icon instead of logo for now -->
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
                    <input type="password" class="form-control" name="password" placeholder="........" required>
                </div>
                
                <div style="margin-bottom: 15px; text-align: right;">
                    <a href="forgot-password.php" style="color: #666; font-size: 14px; text-decoration: none;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary">Log In</button>
            </form>

            <div class="auth-footer">
                Don't have an account? <a href="signup.php" class="auth-link">Sign Up</a>
            </div>
      </div>
</body>
</html>