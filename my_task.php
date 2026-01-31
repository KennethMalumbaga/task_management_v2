<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/Task.php";
    include "app/Model/User.php";
    include "app/Model/Subtask.php"; // Include subtask model

    $tasks = get_all_tasks_by_user($pdo, $_SESSION['id']);
    $users = get_all_users($pdo); // For assigning subtasks

    // Helper: Check if user is leader
    function is_leader($pdo, $task_id, $user_id){
        $assignees = get_task_assignees($pdo, $task_id);
        if($assignees != 0){
            foreach($assignees as $a){
                if($a['user_id'] == $user_id && $a['role'] == 'leader') return true;
            }
        }
        return false;
    }
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
    <link rel="stylesheet" href="css/task_redesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">Tasks</h2>
            </div>
             <!-- Only show Create Task if Admin? User requested it in the view, but let's stick to permissions -->
             <?php if($_SESSION['role'] == 'admin') { ?>
                <a href="create_task.php" class="btn-v2 btn-indigo"><i class="fa fa-plus"></i> Create Task</a>
             <?php } ?>
        </div>

        <?php if (isset($_GET['success'])) {?>
            <div style="background: #D1FAE5; color: #065F46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
              <?= stripcslashes($_GET['success']); ?>
            </div>
        <?php } ?>

        <div class="tasks-wrapper">
            <?php if ($tasks != 0) { 
                foreach ($tasks as $task) { 
                    $isLeader = is_leader($pdo, $task['id'], $_SESSION['id']);
                    $subtasks = get_subtasks_by_task($pdo, $task['id']);
                    
                    // Status Badge Class
                    $statusClass = "pending";
                    $statusText = str_replace('_', ' ', $task['status']);
                    
                    if ($task['status'] == 'in_progress') $statusClass = "in_progress";
                    if ($task['status'] == 'completed') $statusClass = "completed";
                    
                    // Check Revision Status
                    if ($task['status'] == 'in_progress' && !empty($task['review_comment'])) {
                        $statusClass = "revision_needed"; // Reuse subtask badge class (Orange)
                        $statusText = "revision requested";
                    }
            ?>
            <div class="task-card-v2" id="task-<?=$task['id']?>">
                <!-- Header -->
                <div class="task-header-v2" onclick="toggleTask(<?=$task['id']?>)">
                    <div class="task-header-left">
                        <i class="fa fa-chevron-right task-toggle-icon" id="icon-<?=$task['id']?>"></i>
                        <span class="task-title-v2"><?= htmlspecialchars($task['title']) ?></span>
                        <span class="badge-v2 <?=$statusClass?>"><?= $statusText ?></span>
                    </div>
                    <?php if($_SESSION['role'] == 'admin') { ?>
                        <a href="edit-task.php?id=<?=$task['id']?>" style="color: #9CA3AF;"><i class="fa fa-pencil"></i></a>
                    <?php } ?>
                </div>

                <!-- Details (Expandable) -->
                <div class="task-details-v2" id="details-<?=$task['id']?>">
                    <div class="task-desc-v2">
                        <?= nl2br(htmlspecialchars($task['description'])) ?>
                    </div>
                    
                    <div class="task-meta-v2">
                        <div>
                            <i class="fa fa-users" style="width: 16px;"></i> 
                            Team: 
                            <?php 
                                $assignees = get_task_assignees($pdo, $task['id']);
                                if($assignees != 0){
                                    $names = array_map(function($a){ return $a['full_name']; }, $assignees);
                                    echo implode(", ", $names);
                                } else {
                                    echo "Unknown User";
                                }
                            ?>
                        </div>
                        <div>
                            <i class="fa fa-calendar" style="width: 16px;"></i> 
                            Due: <?= empty($task['due_date']) ? 'No Date' : date("F j, Y", strtotime($task['due_date'])) ?>
                        </div>
                    </div>

                    <!-- Subtasks Section -->
                    <div class="subtasks-section">
                        <div class="subtasks-header">
                            <div class="subtasks-title">Subtasks</div>
                            <?php if($isLeader && $task['status'] != 'completed') { ?>
                                <button class="btn-v2 btn-indigo-light" onclick="toggleSubtaskForm(<?=$task['id']?>)">
                                    <i class="fa fa-plus"></i> Add Subtask
                                </button>
                            <?php } ?>
                        </div>

                        <!-- Create Subtask Form -->
                        <div class="subtask-create-form" id="subtask-form-<?=$task['id']?>" style="display: none;">
                            <form action="app/add-subtask.php" method="POST">
                                <input type="hidden" name="task_id" value="<?=$task['id']?>">
                                <input type="hidden" name="parent_id" value="<?=$task['id']?>"> <!-- Legacy param name fix -->
                                
                                <div class="form-row">
                                    <input type="text" name="description" placeholder="Subtask title/description" class="form-input-v2" required>
                                </div>
                                
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <select name="member_id" class="form-input-v2" required>
                                        <option value="">Assign to...</option>
                                        <?php if($assignees != 0) { foreach($assignees as $a) { ?>
                                            <option value="<?=$a['user_id']?>"><?=$a['full_name']?></option>
                                        <?php } } ?>
                                    </select>
                                    <input type="date" name="due_date" class="form-input-v2" required>
                                </div>

                                <div style="display: flex; gap: 10px;">
                                    <button class="btn-v2 btn-indigo">Create</button>
                                    <button type="button" class="btn-v2 btn-white" onclick="toggleSubtaskForm(<?=$task['id']?>)">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <!-- Subtasks List -->
                        <?php if (!empty($subtasks)) { 
                            foreach($subtasks as $sub) { 
                                $subStatusClass = "pending";
                                if ($sub['status'] == 'in_progress') $subStatusClass = "in_progress";
                                if ($sub['status'] == 'completed') $subStatusClass = "completed";
                                if ($sub['status'] == 'submitted') $subStatusClass = "submitted";
                                if ($sub['status'] == 'revise') $subStatusClass = "revision_needed"; // Map 'revise' db status to 'revision_needed' css class
                                if ($sub['status'] == 'rejected') $subStatusClass = "rejected";
                        ?>
                        <div class="subtask-card">
                            <div class="subtask-header">
                                <div>
                                    <div class="subtask-title-text"><?= htmlspecialchars(mb_strimwidth($sub['description'], 0, 50, "...")) ?></div> <!-- Using desc as title per screenshot -->
                                    <div class="subtask-meta">
                                        <i class="fa fa-user"></i> Assigned to: <?= htmlspecialchars($sub['member_name']) ?>
                                    </div>
                                    <?php /* Rating stars if needed ? 
                                    <div class="subtask-rating">
                                        <i class="fa fa-star"></i> 5/5
                                    </div>
                                    */ ?>
                                </div>
                                <span class="badge-v2 <?=$subStatusClass?>"><?= str_replace('_',' ', $sub['status']) ?></span>
                            </div>

                            <div class="subtask-desc">
                                <?= htmlspecialchars($sub['description']) ?>
                            </div>

                            <!-- Submission View -->
                             <?php if(!empty($sub['submission_file']) || $sub['status'] == 'submitted' || $sub['status'] == 'completed') { ?>
                                <div class="submission-box">
                                    <span class="submission-label">Submission:</span>
                                    
                                    <?php if(!empty($sub['submission_note'])) { ?>
                                        <div class="submission-text" style="font-style: italic; margin-bottom: 8px;">
                                            "<?= htmlspecialchars($sub['submission_note']) ?>"
                                        </div>
                                    <?php } ?>

                                    <div class="submission-text">
                                        <?php if($sub['submission_file']) { ?>
                                            <a href="<?=$sub['submission_file']?>" target="_blank" style="color: #4F46E5;">View Submission File</a>
                                        <?php } else { ?>
                                            Submitted (No file)
                                        <?php } ?>
                                    </div>
                                    <span class="submission-date">Submitted: <?= $sub['updated_at'] ? date("F j, Y, g:i A", strtotime($sub['updated_at'])) : 'N/A' ?></span>
                                </div>
                             <?php } ?>

                             <!-- Review Feedback View -->
                             <?php if(!empty($sub['feedback'])) { ?>
                                <div class="review-box <?=$sub['status'] == 'completed' ? 'success' : 'warning'?>">
                                    <div class="review-header">
                                        <i class="fa <?=$sub['status'] == 'completed' ? 'fa-check' : 'fa-exclamation-circle'?>"></i> 
                                        Review Feedback:
                                    </div>
                                    <?= htmlspecialchars($sub['feedback']) ?>
                                </div>
                             <?php } ?>

                             <!-- Actions for Leader -->
                             <?php if($isLeader && $sub['status'] == 'submitted') { ?>
                                <div style="margin-top: 15px;">
                                    <form action="app/review-subtask.php" method="POST" id="review-form-<?=$sub['id']?>">
                                        <input type="hidden" name="subtask_id" value="<?=$sub['id']?>">
                                        <input type="hidden" name="parent_id" value="<?=$task['id']?>"> <!-- Redirect back -->
                                        
                                        <textarea name="feedback" class="form-input-v2" rows="2" placeholder="Review feedback..." style="margin-bottom: 10px;"></textarea>
                                        
                                        <div style="display: flex; gap: 8px;">
                                            <button name="action" value="accept" class="btn-v2 btn-green">
                                                <i class="fa fa-check"></i> Accept
                                            </button>
                                            <button name="action" value="revise" class="btn-v2 btn-yellow">
                                                <i class="fa fa-refresh"></i> Request Revision
                                            </button>
                                        </div>
                                    </form>
                                </div>
                             <?php } ?>

                             <!-- Actions for Member (Submit) -->
                              <?php if($_SESSION['id'] == $sub['member_id'] && ($sub['status'] == 'pending' || $sub['status'] == 'in_progress' || $sub['status'] == 'revise')) { ?>
                                <div style="margin-top: 15px;">
                                    <form action="app/update-subtask-submission.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="id" value="<?=$sub['id']?>">
                                        
                                        <div style="margin-bottom: 10px;">
                                            <textarea name="submission_note" class="form-input-v2" rows="2" placeholder="Add a description or note..."></textarea>
                                        </div>

                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <input type="file" name="submission_file" class="form-input-v2" style="width: auto;" required>
                                            <button class="btn-v2 btn-indigo">Submit</button>
                                        </div>
                                    </form>
                                </div>
                              <?php } ?>

                        </div>
                        <?php } } else { ?>
                            <div style="color: #9CA3AF; font-size: 14px; padding: 10px 0;">No subtasks yet.</div>
                        <?php } ?>

                    </div>

                    <!-- Submission for Review (Parent Task) -->
                    <?php 
                        // Check if all subtasks are finished
                        $allSubtasksDone = false;
                        if (!empty($subtasks)) {
                            $allSubtasksDone = true;
                            foreach($subtasks as $sub){
                                if($sub['status'] != 'completed' && $sub['status'] != 'submitted') {
                                    $allSubtasksDone = false; 
                                    break;
                                }
                            }
                        }

                        // Check if revision requested
                        $isRevisionRequested = ($task['status'] == 'in_progress' && !empty($task['review_comment']));
                        
                        // Check if Solo Leader (User is leader, and is the only assignee OR valid logic)
                        // User said: "if he is the only one assigned to the task or do not have a member"
                        // $assignees is array of users.
                        $assigneeCount = ($assignees != 0) ? count($assignees) : 0;
                        //$isLeader defined above checks if *current user* is leader.
                        
                        // Condition: Leader + (Revision Requested OR Ready for Submission)
                        
                        if ($isLeader) {
                            if ($isRevisionRequested) {
                    ?>
                                <!-- Revision Requested UI -->
                                <div style="margin-top: 24px; border: 1px solid #FDBA74; background: #FFF7ED; border-radius: 8px; overflow: hidden;">
                                    <div style="padding: 16px; border-bottom: 1px solid #FFEDD5; display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-exclamation-circle" style="color: #EA580C;"></i>
                                        <span style="color: #9A3412; font-weight: 600; font-size: 14px;">Revision Requested by Admin</span>
                                    </div>
                                    
                                    <div style="padding: 16px;">
                                        <div style="margin-bottom: 16px;">
                                            <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">Admin Feedback:</div>
                                            <div style="color: #4B5563; font-size: 14px; line-height: 1.6;">
                                                <?= nl2br(htmlspecialchars($task['review_comment'])) ?>
                                            </div>
                                        </div>

                                        <?php if (!empty($task['submission_note'])) { ?>
                                            <div style="background: white; border: 1px solid #E5E7EB; border-radius: 6px; padding: 12px;">
                                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Original Submission Notes:</div>
                                                <div style="font-size: 13px; color: #4B5563; font-style: italic;">
                                                    <?= htmlspecialchars($task['submission_note']) ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        
                                        <div style="margin-top: 16px; text-align: right;">
                                             <div style="font-size: 13px; color: #EA580C; margin-bottom: 10px; display: inline-block; text-align: left; float: left; padding-top: 8px;">
                                                Please address the feedback and resubmit when ready.
                                             </div>
                                             <button class="btn-v2 btn-red" style="background: #EA580C;" onclick="openResubmitModal(<?=$task['id']?>, `<?= htmlspecialchars($task['review_comment']) ?>`)">
                                                <i class="fa fa-paper-plane"></i> Resubmit Task
                                             </button>
                                        </div>
                                    </div>
                                </div>

                    <?php   
                            } else if ($task['status'] != 'completed' && $allSubtasksDone && !empty($subtasks)) {
                    ?>
                            <!-- Standard Submission Banner -->
                            <div class="completion-banner">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="background: #D1FAE5; color: #059669; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa fa-check" style="font-size: 12px;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #065F46; font-size: 14px;">All Subtasks Completed!</div>
                                        <div style="font-size: 13px; color: #047857;">You can now submit this task for admin review and rating.</div>
                                    </div>
                                </div>
                                <button class="btn-v2 btn-green" onclick="openTaskSubmissionModal(<?=$task['id']?>)">
                                    <i class="fa fa-paper-plane"></i> Submit Task
                                </button>
                            </div>
                    <?php 
                            } 
                        } // End isLeader check

                        if ($task['status'] == 'completed') { 
                    ?>
                         <!-- Display Completed State if needed, or already handled by badge -->
                         <div style="margin-top: 20px; padding: 15px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; color: #166534; font-size: 14px;">
                            <i class="fa fa-check-circle"></i> This task has been submitted and completed.
                         </div>
                    <?php } ?>

                </div>
            </div>
            <?php } 
            } else { ?>
                <div style="text-align: center; padding: 40px; color: #6B7280;">
                    <i class="fa fa-folder-open-o" style="font-size: 48px; opacity: 0.5; margin-bottom: 15px;"></i>
                    <h3>No tasks found</h3>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Task Submission Modal -->
    <div id="taskSubmissionModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Submit Task for Review</h3>
            <p style="font-size: 14px; color: #6B7280; margin-bottom: 20px;">
                Are you sure you want to submit this task? This will notify the admin.
            </p>
            
            <form action="app/submit-task-review.php" method="POST">
                <input type="hidden" name="task_id" id="modal_task_id">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Submission Notes (Optional)</label>
                    <textarea name="submission_note" class="form-input-v2" rows="3" placeholder="Add any notes for the admin..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-v2 btn-white" onclick="closeTaskSubmissionModal()">Cancel</button>
                    <button type="submit" class="btn-v2 btn-green">Submit for Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resubmit Task Modal -->
    <div id="resubmitModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
             <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Resubmit Task for Review</h3>
             
             <div style="background: #FFF7ED; border: 1px solid #FFEDD5; padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 14px;">
                 <div style="font-weight: 600; color: #9A3412; margin-bottom: 4px;">Admin Feedback:</div>
                 <div id="resubmitFeedback" style="color: #4B5563;"></div>
             </div>

             <form action="app/resubmit-task.php" method="POST">
                <input type="hidden" name="task_id" id="resubmit_task_id">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Revision Notes <span style="color: red;">*</span></label>
                    <textarea name="revision_note" class="form-input-v2" rows="4" placeholder="Explain what changes you made..." required></textarea>
                </div>
                
                <div style="font-size: 12px; color: #6B7280; margin-bottom: 15px;">
                    Describe the revisions you've made to address the feedback.
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-v2 btn-white" onclick="closeResubmitModal()">Cancel</button>
                    <button type="submit" class="btn-v2 btn-red" style="background: #EA580C;">Resubmit for Review</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleTask(id) {
            $("#task-" + id).toggleClass("expanded");
        }

        function toggleSubtaskForm(id) {
            $("#subtask-form-" + id).toggle();
        }

        function openTaskSubmissionModal(taskId) {
            $("#modal_task_id").val(taskId);
            $("#taskSubmissionModal").fadeIn(200);
        }

        function closeTaskSubmissionModal() {
            $("#taskSubmissionModal").fadeOut(200);
        }

        function openResubmitModal(taskId, feedback) {
            $("#resubmit_task_id").val(taskId);
            $("#resubmitFeedback").text(feedback);
            $("#resubmitModal").fadeIn(200);
        }

        function closeResubmitModal() {
            $("#resubmitModal").fadeOut(200);
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