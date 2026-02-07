<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/user.php";
    $user = get_user_by_id($pdo, $_SESSION['id']);
    
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Edit Profile | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        <form action="app/update-profile.php" method="POST" enctype="multipart/form-data">
            
            <div class="dash-card" style="padding: 0; overflow: visible; max-width: 900px; margin: 0;">
                <div class="profile-banner"></div>
                
                <div class="profile-header-section">
                    <div class="profile-avatar-container">
                        <div class="profile-avatar-img" style="overflow: hidden; width: 100px; height: 100px; border-radius: 50%;">
                             <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                                <img src="uploads/<?=$user['profile_image']?>" alt="Profile" id="profileDisplay" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                             <?php else: ?>
                                <img src="" alt="Profile" id="profileDisplay" style="display:none; width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <i class="fa fa-user" id="profileIcon" style="font-size: 50px; color: #ccc;"></i>
                             <?php endif; ?>
                        </div>
                        <label for="profileImageInput" class="profile-camera-icon">
                            <i class="fa fa-camera"></i>
                        </label>
                        <input type="file" name="profile_image" id="profileImageInput" style="display: none;" onchange="displayImage(this)">
                    </div>
                    
                    <div class="profile-identity">
                        <h2 class="profile-name-text"><?= htmlspecialchars($user['full_name']) ?></h2>
                        <span class="profile-role-text"><?= htmlspecialchars($user['role'] ?? 'Admin') ?></span>
                    </div>

                    <div class="profile-actions" style="display: flex; gap: 10px;">
                        <a href="profile.php" class="btn-outline" style="padding: 8px 20px;">Cancel</a>
                        <button type="submit" class="btn-primary" style="padding: 8px 25px;">Save</button>
                    </div>
                </div>

                <div class="profile-content">
                    
                    <!-- Alert Messages -->
                    <?php if (isset($_GET['warning'])) {?>
                        <div style="background: #FFFBEB; color: #B45309; padding: 10px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCD34D;">
                            <i class="fa fa-exclamation-triangle"></i> <?php echo stripcslashes($_GET['warning']); ?>
                        </div>
                    <?php } ?>
                    <?php if (isset($_GET['error'])) {?>
                        <div style="background: #FEF2F2; color: #991B1B; padding: 10px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #F87171;">
                            <?php echo stripcslashes($_GET['error']); ?>
                        </div>
                    <?php } ?>
                    <?php if (isset($_GET['success'])) {?>
                        <div style="background: #ECFDF5; color: #065F46; padding: 10px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #34D399;">
                            <?php echo stripcslashes($_GET['success']); ?>
                        </div>
                    <?php } ?>

                    <div class="profile-grid">
                        
                        <!-- Email (Read Only) -->
                        <div class="profile-field-group">
                            <label><i class="fa fa-envelope-o"></i> Email</label>
                            <input type="text" class="field-value" style="width: 100%; box-sizing: border-box;" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                        </div>



                        <!-- Full Name -->
                        <div class="profile-field-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="field-value" style="width: 100%; box-sizing: border-box;" value="<?= htmlspecialchars($user['full_name']) ?>" placeholder="Full Name">
                        </div>
                        
                        <!-- Phone -->
                        <div class="profile-field-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="field-value" style="width: 100%; box-sizing: border-box;" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Enter phone number">
                        </div>

                        <!-- Address -->
                         <div class="profile-field-group">
                            <label>Address</label>
                            <input type="text" name="address" class="field-value" style="width: 100%; box-sizing: border-box;" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Enter address">
                        </div>
                        
                        <!-- Skills -->
                        <div class="profile-field-group" style="grid-column: span 2;">
                            <label>Skills</label>
                            <textarea name="skills" class="field-value" style="width: 100%; box-sizing: border-box; height: 80px;" placeholder="List your skills (comma separated)"><?= htmlspecialchars($user['skills'] ?? '') ?></textarea>
                        </div>

                    </div>

                    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #E5E7EB;">
                    
                    <!-- Password Section -->
                    <div style="background: #F9FAFB; padding: 20px; border-radius: 12px;">
                        <h4 style="margin-top: 0; color: #374151; font-size: 16px;">Change Password <span style="font-weight: 400; font-size: 13px; color: #6B7280;">(Leave empty if you don't want to change)</span></h4>
                        <div class="profile-grid" style="margin-top: 15px;">
                            <div class="profile-field-group">
                                <label>Old Password</label>
                                <input type="password" name="password" class="field-value" style="width: 100%; box-sizing: border-box;" placeholder="Current password">
                            </div>
                            <div class="profile-field-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="field-value" style="width: 100%; box-sizing: border-box;" placeholder="New password">
                            </div>
                            <div class="profile-field-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" class="field-value" style="width: 100%; box-sizing: border-box;" placeholder="Confirm new password">
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </form>

    </div>

    <!-- Image Preview Script -->
    <script>
        function displayImage(e) {
            if (e.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e){
                    var display = document.querySelector('#profileDisplay');
                    var icon = document.querySelector('#profileIcon');
                    
                    display.setAttribute('src', e.target.result);
                    display.style.display = 'block';
                    if(icon) icon.style.display = 'none';
                }
                reader.readAsDataURL(e.files[0]);
            }
        }
    </script>
</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>

