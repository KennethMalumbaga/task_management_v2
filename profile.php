<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/User.php";
    $user = get_user_by_id($pdo, $_SESSION['id']);
?>
<!DOCTYPE html>
<html>
<head>
	<title>Profile | TaskFlow</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div class="dash-card" style="padding: 0; overflow: hidden; max-width: 900px; margin: 0;">
            <div class="profile-banner"></div>
            
            <div class="profile-header-section">
                <div class="profile-avatar-container">
                    <div class="profile-avatar-img">
                         <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                            <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                         <?php else: ?>
                            <i class="fa fa-user" style="font-size: 50px; color: #ccc;"></i>
                         <?php endif; ?>
                    </div>
                    <div class="profile-camera-icon">
                        <i class="fa fa-camera"></i>
                    </div>
                </div>
                
                <div class="profile-identity">
                    <h2 class="profile-name-text"><?= htmlspecialchars($user['full_name']) ?></h2>
                    <span class="profile-role-text"><?= htmlspecialchars($user['role'] ?? 'Admin') ?></span>
                </div>

                <div class="profile-actions">
                    <a href="edit_profile.php" class="btn-primary" style="background: #4F46E5;">
                        <i class="fa fa-pencil"></i> Edit Profile
                    </a>
                </div>
            </div>

            <div class="profile-content">
                <div class="profile-grid">
                    <!-- Left Column -->
                    <div class="profile-field-group">
                        <label> <i class="fa fa-envelope-o"></i> Email</label>
                        <div class="field-value">
                            <?= htmlspecialchars($user['username']) ?> 
                        </div>
                    </div>



                    <div class="profile-field-group">
                        <label>Full Name</label>
                        <div class="field-value">
                             <?= htmlspecialchars($user['full_name']) ?>
                        </div>
                    </div>
                    
                    <div class="profile-field-group">
                        <label>Phone Number</label>
                        <div class="field-value">
                            <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?>
                        </div>
                    </div>

                     <div class="profile-field-group">
                        <label>Skills</label>
                        <div class="field-value">
                            <?= htmlspecialchars($user['skills'] ?? 'No skills listed') ?>
                        </div>
                    </div>

                    <div class="profile-field-group">
                        <label>Address</label>
                        <div class="field-value">
                            <?= htmlspecialchars($user['address'] ?? 'Not provided') ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>