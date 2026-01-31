<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "employee") {
    include "DB_connection.php";
    include "app/Model/Task.php";
    include "app/Model/User.php";
    include "app/Model/Subtask.php";
    
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
    
    // Check if the current user is assigned to this task
    $assignees = get_task_assignees($pdo, $id);
    $is_assigned = false;
    $is_leader = false;
    if ($assignees != 0) {
        foreach ($assignees as $assignee) {
            if ($assignee['user_id'] == $_SESSION['id']) {
                $is_assigned = true;
                if ($assignee['role'] == 'leader') {
                    $is_leader = true;
                }
                break;
            }
        }
    }
    
    if (!$is_assigned) {
        header("Location: my_task.php");
        exit();
    }
    
   $users = get_all_users($pdo);
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Task Details | TaskFlow</title>
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
        
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
             <div>
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">Task Details</h2>
                <a href="my_task.php" style="color: var(--primary); text-decoration: none; font-size: 14px;"><i class="fa fa-arrow-left"></i> Back to My Tasks</a>
             </div>
             <div>
                 <span class="badge badge-primary"><?= ucfirst($task['status']) ?></span>
             </div>
        </div>

        <?php if (isset($_GET['error'])) {?>
            <div style="background: #FEE2E2; color: #B91C1C; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo stripcslashes($_GET['error']); ?>
            </div>
        <?php } ?>

        <?php if (isset($_GET['success'])) {?>
            <div style="background: #D1FAE5; color: #065F46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo stripcslashes($_GET['success']); ?>
            </div>
        <?php } ?>

        <div class="dash-card">
			<form method="POST" enctype="multipart/form-data" action="app/update-task-employee.php">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-gray); margin-bottom: 5px;">Title</label>
                    <div style="font-size: 16px; font-weight: 600; color: var(--text-dark); background: #F9FAFB; padding: 10px; border-radius: 6px;">
                        <?=$task['title']?>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-gray); margin-bottom: 5px;">Description</label>
                    <div style="font-size: 14px; line-height: 1.6; color: var(--text-dark); background: #F9FAFB; padding: 15px; border-radius: 6px; min-height: 80px;">
                        <?=$task['description']?>
                    </div>
                </div>

                <?php if (!empty($task['template_file'])) { ?>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-gray); margin-bottom: 5px;">Template/Guide File</label>
                    <a href="<?=$task['template_file']?>" target="_blank" class="btn-outline" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px;">
                        <i class="fa fa-download"></i> Download Template
                    </a>
                </div>
                <?php } ?>

                <?php if (!empty($task['review_comment'])) { ?>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-gray); margin-bottom: 5px;">Admin Feedback</label>
                    <div style="background: #FFF5F5; color: #C53030; padding: 15px; border-radius: 6px; border: 1px solid #FECACA;">
                        <i class="fa fa-exclamation-circle"></i> <?=$task['review_comment']?>
                    </div>
                </div>
                <?php } ?>
                
                <?php if ($task['status'] !== 'completed' && $is_leader) { ?>
                    <div style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                        <h4 style="margin: 0 0 15px 0;">Final Submission</h4>
                        <div class="form-input" style="border: 2px dashed var(--border-color); padding: 20px; text-align: center;">
                            <input type="file" name="submission_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip" style="width: auto;">
                            <div style="font-size: 12px; color: var(--text-gray); margin-top: 5px;">Upload your final deliverables here</div>
                        </div>
                        <input type="text" name="id" value="<?=$task['id']?>" hidden>
                        <button class="btn-primary" style="margin-top: 10px;">Submit Project</button>
                    </div>
                <?php } elseif ($task['status'] !== 'completed' && !$is_leader) { ?>
                    <div style="margin-top: 30px; padding: 15px; background: #EFF6FF; border-radius: 6px; color: #1E40AF; font-size: 14px;">
                        <i class="fa fa-info-circle"></i> Note: Only the task leader can make the final submission.
                    </div>
                <?php } ?>
			</form>
            
            <?php 
            $subtasks = get_subtasks_by_task($pdo, $id);
            ?>

            <!-- LEADER: Manage Subtasks -->
            <?php if ($is_leader) { ?>
            
            <div style="margin-top: 40px;">
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">Manage Subtasks</h3>
                
                <!-- Create Subtask -->
                <div style="background: #F9FAFB; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 30px;">
                    <h5 style="margin: 0 0 15px 0;">Create New Subtask</h5>
                    <form method="POST" action="app/add-subtask.php">
                        <input type="hidden" name="task_id" value="<?=$id?>">
                        <input type="hidden" name="parent_id" value="<?=$id?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px;">Assign To</label>
                                <select name="member_id" class="form-input" required style="margin-bottom: 0;">
                                    <option value="">Select Member</option>
                                    <?php 
                                    if ($assignees != 0) {
                                        foreach ($assignees as $assignee) {
                                            echo '<option value="'.$assignee['user_id'].'">'.$assignee['full_name'].' ('.$assignee['role'].')</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px;">Due Date</label>
                                <input type="date" name="due_date" class="form-input" required style="margin-bottom: 0;">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px;">Description</label>
                            <textarea name="description" class="form-input" rows="2" required style="margin-bottom: 0;"></textarea>
                        </div>
                        
                        <button class="btn-primary" style="font-size: 13px;">Add Subtask</button>
                    </form>
                </div>
                
                <!-- List Subtasks -->
                <?php if (!empty($subtasks)) { ?>
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Description</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Submission</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subtasks as $sub) { ?>
                            <tr>
                                <td><?=$sub['member_name']?></td>
                                <td><?= mb_strimwidth($sub['description'], 0, 30, "...") ?></td>
                                <td><?= empty($sub['due_date']) ? "" : date("F j, Y", strtotime($sub['due_date'])) ?></td>
                                <td><span class="badge badge-pending"><?= ucfirst($sub['status']) ?></span></td>
                                <td>
                                    <?php if ($sub['submission_file']) { ?>
                                        <a href="<?=$sub['submission_file']?>" target="_blank" style="color: var(--primary);">View File</a>
                                    <?php } else { echo '<span style="color: #9CA3AF;">None</span>'; } ?>
                                </td>
                                <td>
                                    <?php if ($sub['status'] == 'submitted') { ?>
                                        <button onclick="openReviewModal(<?=$sub['id']?>)" class="btn-primary" style="padding: 4px 10px; font-size: 12px;">Review</button>
                                    <?php } else { echo "-"; } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Review Modal -->
                <div id="reviewModal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div style="background-color: white; margin: 10% auto; padding: 30px; border-radius: 12px; width: 500px; max-width: 90%;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                             <h3 style="margin: 0;">Review Submission</h3>
                             <span onclick="closeReviewModal()" style="font-size: 24px; cursor: pointer;">&times;</span>
                        </div>
                        
                        <form method="POST" action="app/review-subtask.php">
                            <input type="hidden" name="subtask_id" id="reviewSubtaskId">
                            <input type="hidden" name="parent_id" value="<?=$id?>">
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Feedback / Instruction</label>
                                <textarea name="feedback" class="form-input" rows="4" required></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                <button name="action" value="accept" class="btn-primary" style="background: #10B981;">Accept</button>
                                <button name="action" value="revise" class="btn-primary" style="background: #EF4444;">Request Revision</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    function openReviewModal(id) {
                        document.getElementById('reviewSubtaskId').value = id;
                        document.getElementById('reviewModal').style.display = "block";
                    }
                    function closeReviewModal() {
                        document.getElementById('reviewModal').style.display = "none";
                    }
                </script>
                <?php } ?>
            </div>
            <?php } ?>
            
            <!-- MEMBER: My Subtasks List -->
            <?php if (!$is_leader) { 
                $my_subtasks = [];
                if (!empty($subtasks)) {
                    foreach ($subtasks as $sub) {
                        if ($sub['member_id'] == $_SESSION['id']) {
                            $my_subtasks[] = $sub;
                        }
                    }
                }
            ?>
            
            <?php if (!empty($my_subtasks)) { ?>
             <div style="margin-top: 40px;">
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">My Assigned Subtasks</h3>
                
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Submission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_subtasks as $sub) { ?>
                            <tr>
                                <td><?= mb_strimwidth($sub['description'], 0, 50, "...") ?></td>
                                <td><?= empty($sub['due_date']) ? "" : date("F j, Y", strtotime($sub['due_date'])) ?></td>
                                <td><span class="badge badge-pending"><?= ucfirst($sub['status']) ?></span></td>
                                <td>
                                    <a href="submit-subtask.php?id=<?=$sub['id']?>" class="btn-primary" style="padding: 4px 10px; font-size: 12px;">Submit/View</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } } ?>
            
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