<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Task.php";

    // Modifying logic to exclude admins by default from the directory view
    $role_filter = isset($_GET['role']) ? $_GET['role'] : 'employee'; 
    if ($role_filter == 'all') {
        // If 'all' is requested, we still might want to hide admins based on user request "admin is not in users directory"
        // So we force 'employee' or we filter the result. 
        // Let's assume 'all' means all non-admins for this directory context.
        $role_filter = 'employee';
    }
    
    // However, if we want to show 'all' as in 'all employees' vs 'specific role employees', but we only have 'employee' role really besides admin.
    // The previous code had "Admin" button. User said "admin is not in users directory".
    // So usually directory is for employees.
    
    $users = get_all_users($pdo, $role_filter);

    // Helper to get rating (Fix: Use task_assignees to include members)
    // function get_user_avg_rating($pdo, $user_id) ... REMOVED, using Model function now
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Users Directory | TaskFlow</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .filter-active {
            background: #4F46E5 !important;
            color: white !important;
            border-color: #4F46E5 !important;
        }
        .user-card-action-btn {
            width: 100%; 
            display: flex; 
            justify-content: center; 
            padding: 8px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-msg {
            background: #4F46E5; 
            color: white;
        }
        .btn-view {
            background: #F3F4F6; 
            color: #374151;
            margin-top: 8px;
        }
        .btn-view:hover {
            background: #E5E7EB;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">Users Directory</h2>
                
                <div style="display: flex; gap: 10px;">
                     <!-- Removed Admin filter button as requested -->
                     <a href="user.php?role=employee" class="btn-outline filter-active">Employees</a>
                </div>
            </div>
        </div>

        <?php if (!empty($users)) { ?>
        <div class="grid-container">
            <?php foreach ($users as $user) { 
                // Double check to skip admins if get_all_users returns them (depending on implementation of 'all')
                if ($user['role'] == 'admin') continue;

                $stats = get_user_rating_stats($pdo, $user['id']);
                $avg_rating = $stats['avg'];
            ?>
            <div class="user-card" style="display: flex; flex-direction: column; height: 100%;">
                
                <div class="user-card-avatar">
                     <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                        <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                     <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                     <?php endif; ?>
                </div>
                
                <h3 style="margin: 0 0 5px 0; font-size: 18px; color: var(--text-dark);"><?= htmlspecialchars($user['full_name']) ?></h3>
                
                <?php 
                    $roleClass = "badge-in_progress"; 
                ?>
                <div style="margin-bottom: 5px;">
                     <span class="badge <?= $roleClass ?>"><?= ucfirst($user['role']) ?></span>
                </div>
                
                <div style="color: #F59E0B; font-size: 13px; margin-bottom: 15px;">
                     <i class="fa fa-star"></i> <?= $avg_rating ?> / 5.0
                </div>

                <div style="color: var(--text-gray); font-size: 13px; margin-bottom: 10px;">
                    <i class="fa fa-envelope-o"></i> <?= htmlspecialchars($user['username']) ?>
                </div>


                
                <div style="margin-top: auto; padding-top: 15px;">
                    <div style="font-size: 12px; color: var(--text-gray); margin-bottom: 10px;">
                        <strong>Skills:</strong> 
                        <?= !empty($user['skills']) ? htmlspecialchars(substr($user['skills'], 0, 30)) . (strlen($user['skills']) > 30 ? '...' : '') : 'Not listed' ?>
                    </div>

                    <a href="messages.php?id=<?=$user['id']?>" class="user-card-action-btn btn-msg">
                        <i class="fa fa-comment-o" style="margin-right: 5px;"></i> Message
                    </a>
                    <a href="user_details.php?id=<?=$user['id']?>" class="user-card-action-btn btn-view">
                        View Profile
                    </a>
                </div>

            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
             <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <h3>No users found</h3>
            </div>
        <?php } ?>

    </div>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>