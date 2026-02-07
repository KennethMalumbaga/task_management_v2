<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/Subtask.php";
    include "app/model/Task.php";
    include "app/model/user.php";

    $subtasks = get_subtasks_by_member($pdo, $_SESSION['id']);
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>My Subtasks | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
	<?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
	<div class="dash-main">
        
        <div style="margin-bottom: 24px;">
			<h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">My Subtasks</h2>
            <span style="color: var(--text-gray); font-size: 14px;">smaller components of main tasks assigned to you</span>
		</div>

        <?php if (isset($_GET['success'])) {?>
            <div style="background: #D1FAE5; color: #065F46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo stripcslashes($_GET['success']); ?>
            </div>
		<?php } ?>

        <div class="table-container">
			<?php if ($subtasks != 0 && !empty($subtasks)) { ?>
			<table class="custom-table">
				<thead>
                    <tr>
                        <th>Main Task</th>
                        <th>Description</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
				<?php $i=0; foreach ($subtasks as $sub) { ?>
				<tr>
					<td>
                        <div style="font-weight: 500;"><?= htmlspecialchars($sub['task_title']) ?></div>
                    </td>
					<td>
                        <div style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-gray);">
                            <?= htmlspecialchars($sub['description']) ?>
                        </div>
                    </td>
	                <td><?= !empty($sub['due_date']) ? date("F j, Y", strtotime($sub['due_date'])) : 'No Deadline' ?></td>
					<td>
                        <span class="badge badge-pending"><?= ucfirst($sub['status']) ?></span>
                    </td>
					<td>
                        <!-- Using submit-subtask.php or maybe we should modernize that too? 
                             For now link to it, but styling might be old there.
                             Ideally should point to a modal or new page. -->
                        <a href="my_task.php?open_task=<?=$sub['task_id']?>" class="btn-primary btn-sm" style="padding: 6px 12px; font-size: 12px;">
                            View / Submit
                        </a>
					</td>
				</tr>
			   <?php } ?>
               </tbody>
			</table>
		<?php }else { ?>
            <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <i class="fa fa-list-alt" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>No assigned subtasks</h3>
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


