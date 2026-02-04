<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Subtask.php";
    $user = get_user_by_id($pdo, $_SESSION['id']);
    $collab_scores = get_collaborative_scores_by_user($pdo, $_SESSION['id']);
    $rating_stats = get_user_rating_stats($pdo, $_SESSION['id']);
?>
<!DOCTYPE html>
<html>
<head>
	<title>Profile | TaskFlow</title>
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
                <!-- Ratings Stats Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <!-- Task Rating Card -->
                    <div style="background: #FFF7ED; padding: 20px; border-radius: 12px; border: 1px solid #FFEDD5;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <i class="fa fa-star" style="color: #F59E0B; font-size: 24px;"></i>
                            <span style="font-size: 28px; font-weight: 700; color: var(--text-dark);"><?= $rating_stats['avg'] ?></span>
                            <span style="color: var(--text-gray); font-size: 14px;">/ 5.0</span>
                        </div>
                        <span style="font-size: 13px; color: var(--text-gray);">Task Rating (<?= $rating_stats['count'] ?> tasks)</span>
                    </div>
                    
                    <!-- Collaborative Score Card -->
                    <div style="background: #F5F3FF; padding: 20px; border-radius: 12px; border: 1px solid #EDE9FE;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <i class="fa fa-users" style="color: #8B5CF6; font-size: 24px;"></i>
                            <span style="font-size: 28px; font-weight: 700; color: var(--text-dark);"><?= $collab_scores['avg'] ?></span>
                            <span style="color: var(--text-gray); font-size: 14px;">/ 5.0</span>
                        </div>
                        <span style="font-size: 13px; color: var(--text-gray);">Collab Score (<?= $collab_scores['count'] ?> subtasks)</span>
                    </div>
                </div>
                
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

                <?php if ($collab_scores['count'] > 0) { ?>
                <hr style="margin: 30px 0; border: 0; border-top: 1px solid #E5E7EB;">
                
                <!-- Collaborative Score Section -->
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; color: var(--text-dark);">
                        <i class="fa fa-users" style="color: #8B5CF6;"></i> Collaborative Score
                    </h3>
                    
                    <!-- Overall Score Card -->
                    <div style="background: linear-gradient(135deg, #EDE9FE, #F3E8FF); padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                        <div style="background: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <span style="font-size: 24px; font-weight: 700; color: #7C3AED;"><?= $collab_scores['avg'] ?></span>
                        </div>
                        <div>
                            <div style="font-size: 16px; font-weight: 600; color: #5B21B6;">Overall Average</div>
                            <div style="font-size: 13px; color: #7C3AED;">Based on <?= $collab_scores['count'] ?> rated subtasks</div>
                        </div>
                    </div>
                    
                    <!-- Project Breakdown -->
                    <?php if (!empty($collab_scores['projects'])) { ?>
                    <div style="font-size: 14px; font-weight: 600; color: var(--text-gray); margin-bottom: 10px;">Performance by Project</div>
                    <?php foreach ($collab_scores['projects'] as $project) { ?>
                    <div style="background: #F9FAFB; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($project['task_title']) ?></div>
                            <div style="font-size: 12px; color: var(--text-gray);"><?= $project['subtask_count'] ?> subtasks rated</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="color: #F59E0B;">
                                <?php 
                                $score = round($project['avg_score']);
                                for($i=1; $i<=5; $i++) { 
                                    echo ($i <= $score) ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star-o"></i>'; 
                                } 
                                ?>
                            </span>
                            <span style="font-weight: 600; color: var(--text-dark);"><?= number_format($project['avg_score'], 1) ?></span>
                        </div>
                    </div>
                    <?php } ?>
                    <?php } ?>
                </div>
                <?php } ?>
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