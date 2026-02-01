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
    <style>
        /* Collapsible Styles */
        .task-card .task-details {
            display: none;
        }
        .task-card.expanded .task-details {
            display: block;
        }
        .task-toggle-icon {
            transition: transform 0.2s ease;
        }
        .task-card.expanded .task-toggle-icon {
            transform: rotate(90deg);
        }
    </style>
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

                    // Prepare Assignees Data (Moved to top for visibility)
                    $assignees = get_task_assignees($pdo, $task['id']);
                    $leader = null;
                    $members = [];
                    if ($assignees != 0) {
                        foreach ($assignees as $a) {
                            if ($a['role'] == 'leader') {
                                $leader = $a;
                            } else {
                                $members[] = $a;
                            }
                        }
                    }
            ?>
            <div class="task-card" style="background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #E5E7EB; position: relative;" id="task-card-<?=$task['id']?>">
                
                <!-- Action Buttons (Admin Edit) -->
                <?php if($_SESSION['role'] == 'admin') { ?>
                    <a href="edit-task.php?id=<?=$task['id']?>" style="position: absolute; top: 24px; right: 24px; color: #9CA3AF; text-decoration: none; font-size: 14px; z-index: 10;"><i class="fa fa-pencil"></i></a>
                <?php } ?>

                <!-- 1. Header (Clickable Toggle) -->
                <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="toggleTask(<?=$task['id']?>)">
                    <i class="fa fa-chevron-right task-toggle-icon" id="toggle-icon-<?=$task['id']?>" style="color: #6B7280; font-size: 12px;"></i>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;"><?= htmlspecialchars($task['title']) ?></h3>
                    <span class="badge-v2 <?=$statusClass?>"><?= $statusText ?></span>
                </div>
                
                <!-- 2. Visible Task Summary (Dashboard Style) -->
                <div onclick="toggleTask(<?=$task['id']?>)" style="cursor: pointer;">
                    <!-- Description (Truncated) -->
                    <div style="color: #6B7280; font-size: 14px; margin-bottom: 16px; padding-left: 20px;">
                        <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 100, "...")) ?>
                    </div>

                    <div style="padding-left: 20px;">
                         <!-- Project Leader Section -->
                        <?php if ($leader) { 
                            $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                        ?>
                        <div style="background: #F5F3FF; border: 1px solid #E0E7FF; border-radius: 8px; padding: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
                            <img src="<?= $leaderImg ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            <div>
                                <div style="font-size: 10px; font-weight: 700; color: #6366F1; letter-spacing: 0.5px; text-transform: uppercase;">
                                    <i class="fa fa-crown" style="margin-right: 4px;"></i> Project Leader
                                </div>
                                <div style="font-weight: 600; color: #1F2937; font-size: 14px;">
                                    <?= htmlspecialchars($leader['full_name']) ?>
                                </div>
                                 <div style="font-size: 11px; color: #F59E0B; font-weight: 500;">
                                    <i class="fa fa-star"></i> 4.2/5
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <!-- Team Members Section -->
                        <?php if (!empty($members)) { ?>
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 11px; font-weight: 600; color: #059669; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
                                <i class="fa fa-users" style="margin-right: 4px;"></i> Team Members
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                                <?php foreach ($members as $member) { 
                                    $memImg = !empty($member['profile_image']) ? 'uploads/' . $member['profile_image'] : 'img/user.png';
                                ?>
                                <div style="background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 8px; padding: 10px; display: flex; align-items: center; gap: 10px;">
                                    <img src="<?= $memImg ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: 500; color: #1F2937; font-size: 13px;">
                                            <?= htmlspecialchars($member['full_name']) ?>
                                        </div>
                                        <div style="font-size: 10px; color: #F59E0B; font-weight: 500;">
                                            <i class="fa fa-star"></i> 4.5/5
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>

                        <!-- Footer Meta (Due Date & Rating) -->
                        <div style="margin-bottom: 16px;">
                             <div style="color: #6B7280; font-size: 13px;">
                                Due: <?= empty($task['due_date']) ? 'No Date' : date("F j, Y", strtotime($task['due_date'])) ?>
                            </div>
                        </div>

                         <?php 
                            // Rating is visible on main card if completed
                            if ($task['status'] == 'completed') { 
                        ?>
                             <div style="margin-top: 10px; padding: 12px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0px; color: #166534; font-weight: 600; font-size: 13px;">
                                    <i class="fa fa-check-circle"></i> Task Completed
                                    <?php if(isset($task['rating']) && $task['rating'] > 0) { ?>
                                        <span style="color: #4B5563; margin-left: auto;">
                                            <span style="color: #F59E0B; font-size: 14px;">
                                                <?php for($i=1; $i<=5; $i++) { echo ($i <= $task['rating']) ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star-o"></i>'; } ?>
                                            </span>
                                        </span>
                                    <?php } ?>
                                </div>
                             </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- 3. Collapsible Details (Subtasks & Actions) -->
                <div class="task-details" id="task-details-<?=$task['id']?>" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #E5E7EB;">
                    
                    <!-- Separate Full Description if needed, or just Subtasks -->
                    <!-- Let's show Full Description here if it's long, or just always for clarity -->
                    <div style="margin-bottom: 20px; padding-left: 20px;">
                        <h4 style="font-size: 14px; color: #374151; margin-bottom: 8px;">Description</h4>
                        <div style="color: #4B5563; font-size: 14px;">
                            <?= nl2br(htmlspecialchars($task['description'])) ?>
                        </div>
                    </div>

                    <!-- 4. Subtasks Section -->
                    <div class="subtasks-section" style="padding-left: 20px;">
                        <div class="subtasks-header" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="subtasks-title" style="font-weight: 600; color: #374151;">Subtasks</div>
                            <?php if($isLeader && $task['status'] != 'completed') { ?>
                                <button class="btn-v2 btn-indigo-light" onclick="toggleSubtaskForm(<?=$task['id']?>)">
                                    <i class="fa fa-plus"></i> Add Subtask
                                </button>
                            <?php } ?>
                        </div>

                        <!-- Create Subtask Form -->
                        <div class="subtask-create-form" id="subtask-form-<?=$task['id']?>" style="display: none; background: #F9FAFB; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <form action="app/add-subtask.php" method="POST">
                                <input type="hidden" name="task_id" value="<?=$task['id']?>">
                                <input type="hidden" name="parent_id" value="<?=$task['id']?>"> 
                                <div class="form-row" style="margin-bottom: 10px;">
                                    <input type="text" name="description" placeholder="Subtask title/description" class="form-input-v2" required style="width: 100%; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;">
                                </div>
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                    <select name="member_id" class="form-input-v2" required style="padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;">
                                        <option value="">Assign to...</option>
                                        <?php if($assignees != 0) { foreach($assignees as $a) { ?>
                                            <option value="<?=$a['user_id']?>"><?=$a['full_name']?></option>
                                        <?php } } ?>
                                    </select>
                                    <input type="date" name="due_date" class="form-input-v2" required style="padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;">
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
                                if ($sub['status'] == 'revise') $subStatusClass = "revision_needed"; 
                                if ($sub['status'] == 'rejected') $subStatusClass = "rejected";
                        ?>
                        <div class="subtask-card" style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                            <div class="subtask-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <div class="subtask-title-text" style="font-weight: 500; font-size: 14px; color: #1F2937; margin-bottom: 4px;"><?= htmlspecialchars(mb_strimwidth($sub['description'], 0, 50, "...")) ?></div> 
                                    <div class="subtask-meta" style="font-size: 12px; color: #6B7280;">
                                        <i class="fa fa-user"></i> Assigned to: <?= htmlspecialchars($sub['member_name']) ?>
                                    </div>
                                </div>
                                <span class="badge-v2 <?=$subStatusClass?>"><?= str_replace('_',' ', $sub['status']) ?></span>
                            </div>

                            <div class="subtask-desc" style="font-size: 13px; color: #4B5563; margin-bottom: 10px;">
                                <?= htmlspecialchars($sub['description']) ?>
                            </div>

                             <!-- Submission View -->
                             <?php if(!empty($sub['submission_file']) || $sub['status'] == 'submitted' || $sub['status'] == 'completed') { ?>
                                <div class="submission-box" style="background: white; border: 1px solid #E5E7EB; border-radius: 6px; padding: 10px; margin-top: 10px;">
                                    <span class="submission-label" style="font-size: 12px; font-weight: 600; color: #374151;">Submission:</span>
                                    <?php if(!empty($sub['submission_note'])) { ?>
                                        <div class="submission-text" style="font-style: italic; font-size: 13px; color: #4B5563; margin: 4px 0;">
                                            "<?= htmlspecialchars($sub['submission_note']) ?>"
                                        </div>
                                    <?php } ?>
                                    <div class="submission-text">
                                        <?php if($sub['submission_file']) { ?>
                                            <a href="<?=$sub['submission_file']?>" target="_blank" style="color: #4F46E5; font-size: 13px;">View Submission File</a>
                                        <?php } else { ?>
                                            <span style="font-size: 13px; color: #6B7280;">Submitted (No file)</span>
                                        <?php } ?>
                                    </div>
                                </div>
                             <?php } ?>

                             <!-- Review Feedback View -->
                             <?php if(!empty($sub['feedback'])) { ?>
                                <div class="review-box" style="margin-top: 10px; padding: 10px; border-radius: 6px; font-size: 13px; <?= $sub['status'] == 'completed' ? 'background: #F0FDF4; color: #166534;' : 'background: #FFF7ED; color: #9A3412;' ?>">
                                    <div class="review-header" style="font-weight: 600; margin-bottom: 4px;">
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
                                        <input type="hidden" name="parent_id" value="<?=$task['id']?>"> 
                                        
                                        <textarea name="feedback" class="form-input-v2" rows="2" placeholder="Review feedback..." style="width: 100%; margin-bottom: 10px; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;"></textarea>
                                        
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
                                            <textarea name="submission_note" class="form-input-v2" rows="2" placeholder="Add a description or note..." style="width: 100%; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;"></textarea>
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

                    <!-- 5. Task Submission Logic (Parent Task) -->
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
                        
                        // Condition: Leader + (Revision Requested OR Ready for Submission)
                        if ($isLeader) {
                            if ($isRevisionRequested) {
                    ?>
                                <!-- Revision Requested UI -->
                                <div style="margin-top: 24px; border: 1px solid #FDBA74; background: #FFF7ED; border-radius: 8px; overflow: hidden; margin-left: 20px;">
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
                                        
                                        <?php if(!empty($task['submission_note'])) { ?>
                                            <div style="background: white; border: 1px solid #E5E7EB; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Original Submission Notes:</div>
                                                <div style="font-size: 13px; color: #4B5563; font-style: italic;">
                                                    "<?= htmlspecialchars($task['submission_note']) ?>"
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div style="text-align: right;">
                                             <button class="btn-v2 btn-red" style="background: #EA580C;" onclick="openResubmitModal(<?=$task['id']?>, `<?= htmlspecialchars($task['review_comment']) ?>`)">
                                                <i class="fa fa-paper-plane"></i> Resubmit Task
                                             </button>
                                        </div>
                                    </div>
                                </div>

                    <?php   
                            } else if ($task['status'] != 'completed' && $allSubtasksDone && !empty($subtasks)) {
                    ?>
                            <!-- Submission Banner -->
                            <div class="completion-banner" style="margin-top: 20px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center; margin-left: 20px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="background: #D1FAE5; color: #059669; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa fa-check"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #065F46; font-size: 14px;">All Subtasks Completed!</div>
                                        <div style="font-size: 13px; color: #047857;">You can now submit this task for admin review.</div>
                                    </div>
                                </div>
                                <button class="btn-v2 btn-green" onclick="openTaskSubmissionModal(<?=$task['id']?>)">
                                    <i class="fa fa-paper-plane"></i> Submit Task
                                </button>
                            </div>
                    <?php 
                            } 
                        } 
                        
                        // Rating in Details if needed (already in main Summary)
                        if ($task['status'] == 'completed' && !empty($task['review_comment'])) { 
                    ?>
                        <div style="margin-top: 20px; margin-left: 20px; padding: 15px; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px;">
                             <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">Admin Detailed Feedback:</div>
                             <div style="color: #4B5563; font-size: 14px; font-style: italic;">
                                "<?= htmlspecialchars($task['review_comment']) ?>"
                            </div>
                        </div>
                    <?php } ?>

                </div>
            </div>
                <?php } ?>
            <?php } else { ?>
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
        function toggleTask(taskId) {
            var details = document.getElementById("task-details-" + taskId);
            var card = document.getElementById("task-card-" + taskId);
            
            if (!details) return; // Guard clause

            if (details.style.display === "none") {
                details.style.display = "block";
                card.classList.add("expanded");
            } else {
                details.style.display = "none";
                card.classList.remove("expanded");
            }
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