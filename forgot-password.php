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
            <!-- Left Side: Branding -->
            <div class="auth-left">
                <div class="auth-left-content">
                    <p style="font-size: 14px; margin-bottom: 20px;">Streamline Your Workflow</p>
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

            <!-- Right Side: Reset Form -->
            <div class="auth-right">
                <a href="login.php" class="back-link">
                    <i class="fa fa-arrow-left"></i> Back to Login
                </a>

                <div class="auth-logos">
                    <img src="img/logo.png" alt="Logo 1" class="auth-logo-img">
                    <div class="logo-sep"></div>
                    <img src="img/logo2.png" alt="Logo 2" class="auth-logo-img">
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
                        <label class="form-label">Email Address</label>
                        <div class="input-with-icon">
                            <i class="fa fa-envelope-o input-icon"></i>
                            <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Send Reset Link</button>
                </form>

                <div class="auth-footer">
                    Remember your password? <a href="login.php" class="auth-link">Login</a>
                </div>
            </div>
      </div>
</body>
</html>
