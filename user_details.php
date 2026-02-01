<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Task.php";

    if (!isset($_GET['id'])) {
        header("Location: user.php");
        exit();
    }
    
    $user_id = $_GET['id'];
    $user = get_user_by_id($pdo, $user_id);

    if ($user == 0) {
        header("Location: user.php");
        exit();
    }

    // Get Stats
    function count_user_tasks_by_status($pdo, $user_id, $status) {
        $sql = "SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $status]);
        return $stmt->fetchColumn();
    }

    $completed_tasks = count_user_tasks_by_status($pdo, $user_id, 'completed');
    $in_progress_tasks = count_user_tasks_by_status($pdo, $user_id, 'in_progress');
    $pending_tasks = count_user_tasks_by_status($pdo, $user_id, 'pending');

    // Recent Tasks
    function get_recent_user_tasks($pdo, $user_id) {
        $sql = "SELECT * FROM tasks WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    $recent_tasks = get_recent_user_tasks($pdo, $user_id);

    // Calculate Rating
    function get_user_rating_stats($pdo, $user_id) {
        $sql = "SELECT COUNT(*) as count, AVG(rating) as avg FROM tasks WHERE assigned_to = ? AND status = 'completed' AND rating > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $rating_stats = get_user_rating_stats($pdo, $user_id);
    $avg_rating = $rating_stats['avg'] ? number_format($rating_stats['avg'], 1) : "0.0";
    $rated_count = $rating_stats['count'];
 ?>
<!DOCTYPE html>
<html>
<head>
	<title><?= htmlspecialchars($user['full_name']) ?> | TaskFlow</title>
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
                
                <!-- Stats Row -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr) 1.5fr; gap: 20px; margin-bottom: 40px; flex-wrap: wrap;">
                    
                    <!-- Stats Cards -->
                    <div style="background: #ECFDF5; padding: 20px; border-radius: 12px; position: relative;">
                        <span style="font-size: 13px; font-weight: 600; color: #065F46;">Completed</span>
                        <h2 style="font-size: 28px; margin: 5px 0 0; color: #065F46;"><?= $completed_tasks ?></h2>
                        <i class="fa fa-check-square-o" style="position: absolute; right: 20px; top: 20px; font-size: 24px; color: #10B981;"></i>
                    </div>

                    <div style="background: #EFF6FF; padding: 20px; border-radius: 12px; position: relative;">
                         <span style="font-size: 13px; font-weight: 600; color: #1E40AF;">In Progress</span>
                        <h2 style="font-size: 28px; margin: 5px 0 0; color: #1E40AF;"><?= $in_progress_tasks ?></h2>
                        <i class="fa fa-calendar" style="position: absolute; right: 20px; top: 20px; font-size: 24px; color: #3B82F6;"></i>
                    </div>

                    <div style="background: #FFFBEB; padding: 20px; border-radius: 12px; position: relative;">
                         <span style="font-size: 13px; font-weight: 600; color: #92400E;">Pending</span>
                        <h2 style="font-size: 28px; margin: 5px 0 0; color: #92400E;"><?= $pending_tasks ?></h2>
                        <i class="fa fa-clock-o" style="position: absolute; right: 20px; top: 20px; font-size: 24px; color: #F59E0B;"></i>
                    </div>

                    <!-- Rating Card -->
                    <div style="background: #FFF7ED; padding: 20px; border-radius: 12px; border: 1px solid #FFEDD5;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <i class="fa fa-star" style="color: #F59E0B; font-size: 24px;"></i>
                            <span style="font-size: 28px; font-weight: 700; color: var(--text-dark);"><?= $avg_rating ?></span>
                            <span style="color: var(--text-gray); font-size: 14px;">/ 5.0</span>
                        </div>
                        <span style="font-size: 13px; color: var(--text-gray);">Based on <?= $rated_count ?> rated tasks</span>
                    </div>

                </div>

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
