<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Task.php";

    if (!isset($_GET['id'])) {
        header("Location: user.php");
        exit();
    }
    
    $user_id = $_GET['id'];
    $user = get_user_by_id($pdo, $user_id);
    include "app/model/Subtask.php";
    $collab_scores = get_collaborative_scores_by_user($pdo, $user_id);

    if ($user == 0) {
        header("Location: user.php");
        exit();
    }

    // Get Stats (Fix: Use task_assignees)
    function count_user_tasks_by_status($pdo, $user_id, $status) {
        $sql = "SELECT COUNT(*) FROM tasks t 
                JOIN task_assignees ta ON t.id = ta.task_id 
                WHERE ta.user_id = ? AND t.status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $status]);
        return $stmt->fetchColumn();
    }

    $completed_tasks = count_user_tasks_by_status($pdo, $user_id, 'completed');
    $in_progress_tasks = count_user_tasks_by_status($pdo, $user_id, 'in_progress');
    $pending_tasks = count_user_tasks_by_status($pdo, $user_id, 'pending');

    // Recent Tasks (Fix: Use task_assignees)
    function get_recent_user_tasks($pdo, $user_id) {
        $sql = "SELECT t.* FROM tasks t 
                JOIN task_assignees ta ON t.id = ta.task_id 
                WHERE ta.user_id = ? 
                ORDER BY t.created_at DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    $recent_tasks = get_recent_user_tasks($pdo, $user_id);

    // Calculate Rating (Fix: Use task_assignees)
    // get_user_rating_stats is now in model/user.php
    
    $rating_stats = get_user_rating_stats($pdo, $user_id);
    $avg_rating = $rating_stats['avg']; // Model function returns formatted avg
    $rated_count = $rating_stats['count'];
    
    // Get total hours worked
    $attendance_stats = get_todays_attendance_stats($pdo, $user_id);
    $total_hours = $attendance_stats['overall_duration'];
 ?>
<!DOCTYPE html>
<html>
<head>
	<title><?= htmlspecialchars($user['full_name']) ?> | TaskFlow</title>
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
        
        <div style="margin-bottom: 20px;">
            <a href="user.php" style="text-decoration: none; color: var(--text-gray); font-size: 14px; display: flex; align-items: center; gap: 5px;">
                <i class="fa fa-arrow-left"></i> Back to Users
            </a>
        </div>

        <div class="dash-card" style="padding: 0; overflow: hidden; max-width: 900px; margin: 0 auto;">
            <div class="profile-banner"></div>
            
            <div class="profile-header-section">
                <div class="profile-avatar-container">
                    <div class="profile-avatar-img" style="overflow: hidden; width: 100px; height: 100px; border-radius: 50%;">
                         <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                            <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                         <?php else: ?>
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                         <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-identity">
                    <h2 class="profile-name-text"><?= htmlspecialchars($user['full_name']) ?></h2>
                    <span class="profile-role-text"><?= ucfirst($user['role']) ?></span>
                    <div class="profile-actions" style="margin-left: auto;">
                        <a href="messages.php?id=<?=$user['id']?>" class="btn-primary" style="background: #4F46E5;">
                            <i class="fa fa-comment"></i> Message
                        </a>
                    </div>
                </div>
            </div>

            <div class="profile-content">
                
                <!-- Compact Stats Grid - Single Row -->
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 30px;">
                    
                    <!-- Completed Tasks -->
                    <div style="background: #ECFDF5; padding: 12px; border-radius: 8px; text-align: center;">
                        <i class="fa fa-check-circle" style="font-size: 20px; color: #10B981; margin-bottom: 6px;"></i>
                        <h3 style="font-size: 20px; margin: 0; color: #065F46;"><?= $completed_tasks ?></h3>
                        <span style="font-size: 11px; color: #065F46; font-weight: 500;">Completed</span>
                    </div>

                    <!-- Pending Tasks -->
                    <div style="background: #FFFBEB; padding: 12px; border-radius: 8px; text-align: center;">
                        <i class="fa fa-clock-o" style="font-size: 20px; color: #F59E0B; margin-bottom: 6px;"></i>
                        <h3 style="font-size: 20px; margin: 0; color: #92400E;"><?= $pending_tasks ?></h3>
                        <span style="font-size: 11px; color: #92400E; font-weight: 500;">Pending</span>
                    </div>

                    <!-- Task Rating -->
                    <div style="background: #FFF7ED; padding: 12px; border-radius: 8px; text-align: center;">
                        <i class="fa fa-star" style="font-size: 20px; color: #F59E0B; margin-bottom: 6px;"></i>
                        <h3 style="font-size: 20px; margin: 0; color: #92400E;"><?= $avg_rating ?></h3>
                        <span style="font-size: 11px; color: #92400E; font-weight: 500;">Rating</span>
                    </div>

                    <!-- Collaborative Score -->
                    <div style="background: #F5F3FF; padding: 12px; border-radius: 8px; text-align: center;">
                        <i class="fa fa-users" style="font-size: 20px; color: #8B5CF6; margin-bottom: 6px;"></i>
                        <h3 style="font-size: 20px; margin: 0; color: #6B21A8;"><?= $collab_scores['avg'] ?></h3>
                        <span style="font-size: 11px; color: #6B21A8; font-weight: 500;">Collab</span>
                    </div>

                    <!-- Total Hours -->
                    <div style="background: #EFF6FF; padding: 12px; border-radius: 8px; text-align: center;">
                        <i class="fa fa-clock-o" style="font-size: 20px; color: #3B82F6; margin-bottom: 6px;"></i>
                        <h3 style="font-size: 20px; margin: 0; color: #1E40AF;"><?= str_replace('Oh ', '0h ', $total_hours) ?></h3>
                        <span style="font-size: 11px; color: #1E40AF; font-weight: 500;">Hours</span>
                    </div>

                </div>
                
                <style>
                    @media (max-width: 768px) {
                        .profile-content > div:first-child {
                            grid-template-columns: repeat(5, 1fr) !important;
                            gap: 6px !important;
                        }
                        .profile-content > div:first-child > div {
                            padding: 8px 4px !important;
                        }
                        .profile-content > div:first-child i {
                            font-size: 16px !important;
                            margin-bottom: 4px !important;
                        }
                        .profile-content > div:first-child h3 {
                            font-size: 16px !important;
                        }
                        .profile-content > div:first-child span {
                            font-size: 9px !important;
                        }
                    }
                </style>

                <div class="profile-grid">
                    <!-- Left Column -->
                    <div>
                         <div class="profile-field-group">
                            <label> <i class="fa fa-envelope-o"></i> Email</label>
                            <div class="field-value" style="background: #F9FAFB; border: none; padding-left: 0; font-weight: 600; font-size: 15px;">
                                <?= htmlspecialchars($user['username']) ?> 
                            </div>
                        </div>
                        
                        <div class="profile-field-group">
                            <label>Full Name</label>
                            <div class="field-value" style="background: #F9FAFB; border: none; padding-left: 0;">
                                 <?= htmlspecialchars($user['full_name']) ?>
                            </div>
                        </div>

                         <div class="profile-field-group">
                            <label>Phone Number</label>
                            <div class="field-value" style="background: #F9FAFB; border: none; padding-left: 0;">
                                <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?>
                            </div>
                        </div>

                        <div class="profile-field-group">
                            <label>Address</label>
                            <div class="field-value" style="background: #F9FAFB; border: none; padding-left: 0;">
                                <?= htmlspecialchars($user['address'] ?? 'Not provided') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">

                        
                        <div class="profile-field-group">
                            <label>Skills</label>
                            <div class="field-value" style="background: #F9FAFB; border-radius: 8px; padding: 15px;">
                                <?= htmlspecialchars($user['skills'] ?? 'No skills listed') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <hr style="margin: 40px 0; border: 0; border-top: 1px solid #E5E7EB;">
                
                <!-- Recent Tasks -->
                <h3 style="margin-bottom: 20px; font-size: 18px; color: var(--text-dark);">Recent Tasks</h3>
                
                <?php if (!empty($recent_tasks)){ ?>
                    <?php foreach ($recent_tasks as $task){ 
                        $statusClass = 'badge-active';
                        if($task['status'] == 'completed') $statusClass = 'badge-success';
                        elseif($task['status'] == 'pending') $statusClass = 'badge-pending';
                    ?>
                    <div class="task-item" style="border: 1px solid #E5E7EB; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: none;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <span class="task-title" style="font-size: 16px; display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($task['title']); ?></span>
                                <?php if($task['status'] == 'completed' && isset($task['rating']) && $task['rating'] > 0) { ?>
                                    <div style="color: #F59E0B; font-size: 14px;">
                                        <?php for($i=1; $i<=5; $i++) { echo ($i <= $task['rating']) ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star-o"></i>'; } ?>
                                        <span style="color: #6B7280; font-size: 12px; margin-left: 4px;">(<?=$task['rating']?>/5)</span>
                                    </div>
                                <?php } ?>
                            </div>
                            <span class="badge <?=$statusClass?>"><?= ucfirst($task['status']) ?></span>
                        </div>
                        <p style="font-size: 14px; color: var(--text-gray); margin: 0 0 10px;"><?= htmlspecialchars(substr($task['description'], 0, 100)) ?>...</p>
                        <div style="font-size: 12px; color: var(--text-gray);">
                            Due: <?= !empty($task['due_date']) ? date("F j, Y", strtotime($task['due_date'])) : 'No Date' ?>
                        </div>
                    </div>

                    <?php } ?>
                <?php } else { ?>
                     <p style="color: var(--text-gray);">No recent tasks</p>
                <?php } ?>

                <?php if (!empty($collab_scores['projects'])) { ?>
                <hr style="margin: 40px 0; border: 0; border-top: 1px solid #E5E7EB;">
                
                <!-- Collaborative Score Breakdown -->
                <h3 style="margin-bottom: 20px; font-size: 18px; color: var(--text-dark);">
                    <i class="fa fa-users" style="color: #8B5CF6;"></i> Collaborative Score by Project
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                    <?php foreach ($collab_scores['projects'] as $project) { ?>
                    <div style="background: #F9FAFB; padding: 15px; border-radius: 8px; border: 1px solid #E5E7EB;">
                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 8px;"><?= htmlspecialchars($project['task_title']) ?></div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 12px; color: var(--text-gray);"><?= $project['subtask_count'] ?> subtasks</span>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span style="color: #F59E0B;">
                                    <?php 
                                    $score = round($project['avg_score']);
                                    for($i=1; $i<=5; $i++) { 
                                        echo ($i <= $score) ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star-o"></i>'; 
                                    } 
                                    ?>
                                </span>
                                <span style="font-weight: 600;"><?= number_format($project['avg_score'], 1) ?></span>
                            </div>
                        </div>
                    </div>
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


