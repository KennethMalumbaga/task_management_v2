<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Task.php";

    $users = get_all_users($pdo);
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Users | TaskFlow</title>
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div>
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">Users Directory</h2>
                <span style="color: var(--text-gray); font-size: 14px;">Manage system users</span>
            </div>
            
            <div style="display: flex; gap: 10px;">
                 <a href="#" class="btn-primary" style="background: #E0E7FF; color: var(--primary);">All</a>
                 <a href="#" class="btn-primary" style="background: white; color: var(--text-dark); border: 1px solid var(--border-color);">Admin</a>
                 <a href="#" class="btn-primary" style="background: white; color: var(--text-dark); border: 1px solid var(--border-color);">Employee</a>
                 <a href="add-user.php" class="btn-primary">
                    <i class="fa fa-plus"></i> Add User
                </a>
            </div>
        </div>

        <?php if ($users != 0) { ?>
        <div class="grid-container">
            <?php foreach ($users as $user) { 
                $progress = get_employee_task_progress($pdo, $user['id']);
            ?>
            <div class="user-card">
                <div class="user-card-avatar">
                     <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <h3 style="margin: 0 0 5px 0; font-size: 18px;"><?= htmlspecialchars($user['full_name']) ?></h3>
                
                <?php 
                    $roleClass = "badge-in_progress"; // Default blueish
                    if ($user['role'] == 'admin') $roleClass = "badge-pending"; // Orangeish
                ?>
                <span class="badge <?= $roleClass ?>" style="display: inline-block; margin-bottom: 15px;"><?= ucfirst($user['role']) ?></span>

                <div style="color: var(--text-gray); font-size: 13px; margin-bottom: 20px;">
                    <i class="fa fa-envelope-o"></i> <?= htmlspecialchars($user['username']) ?>
                </div>
                
                <?php if ($progress['total'] > 0) { ?>
                    <div style="margin-bottom: 20px; text-align: left;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
                            <span>Task Progress</span>
                            <span><?= $progress['percentage'] ?>%</span>
                        </div>
                        <div style="background: #e2e8f0; border-radius: 10px; height: 6px; overflow: hidden;">
                            <div style="background: var(--success); height: 100%; width: <?= $progress['percentage'] ?>%;"></div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div style="margin-bottom: 20px; font-size: 13px; color: var(--text-gray);">
                        No tasks assigned
                    </div>
                <?php } ?>

                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a href="messages.php" class="btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fa fa-comment-o"></i> Message
                    </a>
                </div>
                <div style="margin-top: 10px; font-size: 12px;">
                     <a href="edit-user.php?id=<?=$user['id']?>" style="color: var(--text-gray); text-decoration: none; margin-right: 10px;">Edit</a>
                     <a href="delete-user.php?id=<?=$user['id']?>" style="color: var(--danger); text-decoration: none;" onclick="return confirm('Are you sure?')">Delete</a>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
            <!-- Empty state -->
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