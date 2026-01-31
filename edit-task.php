<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/Model/Task.php";
    include "app/Model/User.php";
    
    if (!isset($_GET['id'])) {
    	 header("Location: tasks.php");
    	 exit();
    }
    $id = $_GET['id'];
    $task = get_task_by_id($pdo, $id);

    if ($task == 0) {
    	 header("Location: tasks.php");
    	 exit();
    }
   $users = get_all_users($pdo);
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Edit Task | TaskFlow</title>
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
    <div class="dash-main" style="background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
        
        <div style="background: white; width: 100%; max-width: 600px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden;">
            
             <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #111827;">Edit Task</h2>
                 <a href="tasks.php" style="color: #6B7280; text-decoration: none; font-size: 20px;">&times;</a>
            </div>

            <form action="app/update-task.php" method="POST" enctype="multipart/form-data" style="padding: 24px;">
                
                <?php if (isset($_GET['error'])) {?>
                    <div style="background: #FEF2F2; color: #991B1B; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                        <?php echo stripcslashes($_GET['error']); ?>
                    </div>
                <?php } ?>
                
                <?php if (isset($_GET['success'])) {?>
                    <div style="background: #ECFDF5; color: #065F46; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                        <?php echo stripcslashes($_GET['success']); ?>
                    </div>
                <?php } ?>

                 <!-- Title -->
                 <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Task Title <span style="color: red;">*</span></label>
                    <input type="text" name="title" required value="<?=$task['title']?>" 
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;">
                </div>

                <!-- Description -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Description <span style="color: red;">*</span></label>
                    <textarea name="description" required rows="4" 
                              style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; resize: vertical; transition: border-color 0.2s;"><?=$task['description']?></textarea>
                </div>
                
                <!-- Due Date -->
                <div style="margin-bottom: 20px;">
                     <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Due Date</label>
                     <input type="date" name="due_date" value="<?=$task['due_date']?>" 
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none;">
                </div>

                <!-- Assigned To -->
                 <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Assigned To</label>
                    <select name="assigned_to" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; background: white;">
                        <option value="0">Select employee</option>
                        <?php if ($users != 0) { foreach ($users as $user) { ?>
                            <option value="<?=$user['id']?>" <?php if($task['assigned_to'] == $user['id']) echo 'selected'; ?>>
                                <?=$user['full_name']?>
                            </option>
                        <?php } } ?>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
					<label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Team Members (Optional)</label>
                    <div style="border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; max-height: 150px; overflow-y: auto; background: white;">
                        <?php 
                        // Prepare array of currently assigned member IDs
                        $current_member_ids = [];
                        $assignees = get_task_assignees($pdo, $task['id']);
                        if ($assignees != 0) {
                            foreach ($assignees as $a) {
                                if ($a['role'] == 'member') {
                                    $current_member_ids[] = $a['user_id'];
                                }
                            }
                        }
                        
                        if ($users != 0) { 
                            foreach ($users as $user) { 
                                // Optional: Don't show the currently selected leader in this list to avoid confusion? 
                                // Or show them disabled? For simplicity, show all, backend handles duplication removal.
                                // Actually, simpler UI:
                        ?>
                        <label style="display: flex; align-items: center; margin-bottom: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="team_members[]" value="<?=$user['id']?>" 
                                <?php if(in_array($user['id'], $current_member_ids)) echo 'checked'; ?>
                                style="margin-right: 8px;">
                            <?=$user['full_name']?>
                        </label>
                        <?php 
                            } 
                        } 
                        ?>
                    </div>
                    <p style="font-size: 12px; color: #6B7280; margin-top: 5px;">Select additional members to work on this task.</p>
                </div>

                 <!-- File -->
                 <div style="margin-bottom: 20px;">
                     <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Attachment</label>
                     <?php if(!empty($task['template_file'])): ?>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <a href="<?=$task['template_file']?>" target="_blank" style="color: #6366F1; font-size: 13px;"><i class="fa fa-download"></i> Current File</a>
                        </div>
                     <?php endif; ?>
                     <input type="file" name="template_file" style="width: 100%; font-size: 14px;">
                </div>

                 <!-- Status -->
                 <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Status</label>
                    <select name="status" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; background: white;">
                        <option value="pending" <?php if($task['status'] == "pending") echo "selected"; ?>>Pending</option>
                        <option value="in_progress" <?php if($task['status'] == "in_progress") echo "selected"; ?>>In Progress</option>
                        <option value="completed" <?php if($task['status'] == "completed") echo "selected"; ?>>Completed</option>
                    </select>
                 </div>

                <input type="hidden" name="id" value="<?=$task['id']?>">

                <!-- Actions -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <a href="tasks.php" style="flex: 1; text-align: center; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; text-decoration: none; font-weight: 500; background: white;">Cancel</a>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; border-radius: 8px; background: #6366F1; color: white; font-weight: 500; cursor: pointer; font-size: 14px;">Update Task</button>
                </div>

            </form>
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