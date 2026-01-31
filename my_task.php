<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/Task.php";
    include "app/Model/User.php";

    $tasks = get_all_tasks_by_user($pdo, $_SESSION['id']);
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>My Tasks | TaskFlow</title>
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
        
        <div style="margin-bottom: 24px;">
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">My Tasks</h2>
            <span style="color: var(--text-gray); font-size: 14px;">Tasks assigned to you</span>
        </div>

        <?php if (isset($_GET['success'])) {?>
            <div style="background: #D1FAE5; color: #065F46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
              <?= stripcslashes($_GET['success']); ?>
            </div>
        <?php } ?>

        <div class="table-container">
            <?php if ($tasks != 0) { ?>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=0; foreach ($tasks as $task) { ?>
                    <tr>
                        <td>#<?= $task['id'] ?></td>
                        <td>
                            <div style="font-weight: 500;"><?= htmlspecialchars($task['title']) ?></div>
                        </td>
                         <td>
                            <div style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-gray);">
                                <?= htmlspecialchars($task['description']) ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                $badgeClass = "badge-pending";
                                if ($task['status'] == 'in_progress') $badgeClass = "badge-in_progress";
                                if ($task['status'] == 'completed') $badgeClass = "badge-completed";
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= str_replace('_',' ',$task['status']) ?></span>
                        </td>
                        <td>
                            <?= empty($task['due_date']) ? "" : date("F j, Y", strtotime($task['due_date'])) ?>
                        </td>
                        <td>
                            <?php if ($task['status'] === 'completed') { ?>
                                <button class="btn-outline btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;">Submitted</button>
                            <?php } else { ?>
                                <a href="edit-task-employee.php?id=<?= $task['id'] ?>" class="btn-primary btn-sm">
                                    Open
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php }else { ?>
                <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                    <i class="fa fa-folder-open-o" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No tasks assigned</h3>
                </div>
            <?php  }?>
            
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