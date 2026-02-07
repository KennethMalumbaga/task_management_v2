<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/Notification.php";
    include "app/model/Task.php";

    $notifications = get_all_my_notifications($pdo, $_SESSION['id']);
    $user_role = $_SESSION['role'];

    // Helper function to get task_id from notification
    function get_notification_task_id($pdo, $notification) {
        if (isset($notification['task_id']) && $notification['task_id'] !== null) {
            return $notification['task_id'];
        }
        $message = $notification['message'];
        if (preg_match("/'([^']+)'/", $message, $matches)) {
            $task_title = $matches[1];
            $task = get_task_by_title($pdo, $task_title);
            if ($task != 0) {
                return $task['id'];
            }
        }
        return null;
    }
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Notifications | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
	<?php include "inc/new_sidebar.php" ?>
    
    <!-- Main Content -->
	<div class="dash-main">
        
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px;">
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">Notifications</h2>
        </div>

		<?php if (isset($_GET['success'])) {?>
            <div style="background: #ECFDF5; color: #065F46; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                <?php echo stripcslashes($_GET['success']); ?>
            </div>
		<?php } ?>

        <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
			<?php if ($notifications != 0) { ?>
			<table style="width: 100%; border-collapse: collapse;">
				<thead style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                    <tr>
                        <th style="padding: 12px 24px; text-align: left; font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase;">#</th>
                        <th style="padding: 12px 24px; text-align: left; font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase;">Message</th>
                        <th style="padding: 12px 24px; text-align: left; font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase;">Type</th>
                        <th style="padding: 12px 24px; text-align: left; font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase;">Date</th>
                    </tr>
				</thead>
                <tbody>
				<?php $i=0; foreach ($notifications as $notification) { 
					$task_id = get_notification_task_id($pdo, $notification);
					$is_clickable = $task_id !== null;
					$rowStyle = "border-bottom: 1px solid #E5E7EB; transition: background 0.2s;";
                    if($is_clickable) $rowStyle .= " cursor: pointer;";
				?>
				<tr style="<?=$rowStyle?>" 
                    <?php if($is_clickable){ ?>
                    onclick="handleNotificationClick(<?=$task_id?>)"
                    onmouseover="this.style.background='#F3F4F6'" 
                    onmouseout="this.style.background='white'"
                    <?php } ?>
                >
					<td style="padding: 16px 24px; font-size: 14px; color: #6B7280;"><?=++$i?></td>
					<td style="padding: 16px 24px; font-size: 14px; color: #111827;">
                        <?=$notification['message']?>
                        <?php if($is_clickable) { ?>
                            <i class="fa fa-chevron-right" style="color: #6366F1; margin-left: 8px; font-size: 12px;"></i>
                        <?php } ?>
                    </td>
					<td style="padding: 16px 24px; font-size: 14px; color: #4B5563;">
                        <span style="background: #EEF2FF; color: #4F46E5; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500;">
                            <?=$notification['type']?>
                        </span>
                    </td>
					<td style="padding: 16px 24px; font-size: 14px; color: #6B7280;"><?=$notification['date']?></td>
				</tr>
			   <?php	} ?>
               </tbody>
			</table>
		<?php }else { ?>
            <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <i class="fa fa-bell-o" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>You have zero notifications</h3>
            </div>
		<?php  }?>
        </div>
	</div>

<script type="text/javascript">
	function handleNotificationClick(taskId) {
		if (!taskId) return;
		
		var userRole = '<?=$user_role?>';
		var url = '';
		
		if (userRole === 'admin') {
			// Admin: redirect to edit-task.php to see submitted file
			url = 'edit-task.php?id=' + taskId;
		} else if (userRole === 'employee') {
			// Employee: redirect to edit-task-employee.php to see task details
            // IMPORTANT: Employees might use my_task.php or view pages?
			url = 'edit-task-employee.php?id=' + taskId;
		}
		
		if (url) {
			window.location.href = url;
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

