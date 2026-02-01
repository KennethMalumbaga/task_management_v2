<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] === "admin") {
    require_once "DB_connection.php";
    require_once "app/Model/Task.php";
    require_once "app/Model/User.php";

    $text = "Tasks";
    // Filter Logic
    if (isset($_GET['due_date']) && $_GET['due_date'] === "Due Today") {
        $text = "Due Today";
        $tasks = get_all_tasks_due_today($pdo);
    } elseif (isset($_GET['due_date']) && $_GET['due_date'] === "Overdue") {
        $text = "Overdue";
        $tasks = get_all_tasks_overdue($pdo);
    } elseif (isset($_GET['due_date']) && $_GET['due_date'] === "No Deadline") {
        $text = "No Deadline";
        $tasks = get_all_tasks_NoDeadline($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "Pending") {
        $text = "Pending";
        $tasks = get_all_tasks_pending($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "in_progress") {
        $text = "In Progress";
        $tasks = get_all_tasks_in_progress($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "Completed") {
        $text = "Completed";
        $tasks = get_all_tasks_completed($pdo);
    } else {
        $tasks = get_all_tasks($pdo);
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tasks | TaskFlow</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
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
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;"><?= $text ?></h2>
            
            <a href="create_task.php" style="background: #4F46E5; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa fa-plus"></i> Create Task
            </a>
        </div>

        <?php if (isset($_GET['success'])) {?>
            <div style="background: #ECFDF5; color: #065F46; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                <?php echo stripcslashes($_GET['success']); ?>
            </div>
        <?php } ?>
        <?php if (isset($_GET['error'])) {?>
            <div style="background: #FEF2F2; color: #991B1B; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                <?php echo stripcslashes($_GET['error']); ?>
            </div>
        <?php } ?>

        <div class="tasks-wrapper">
            <?php if (!empty($tasks)) { ?>
                <?php foreach ($tasks as $task) { 
                    $badgeClass = "badge-pending";
                    $statusDisplay = str_replace('_',' ',$task['status']);
                    
                    if ($task['status'] == 'in_progress') $badgeClass = "badge-in_progress";
                    if ($task['status'] == 'completed') $badgeClass = "badge-completed";
                    
                    // Logic for "Submitted for Review" visual
                    $isSubmittedForReview = false;
                    if ($task['status'] == 'completed' && ($task['rating'] == 0 || $task['rating'] == NULL)) {
                         $statusDisplay = "submitted for review"; 
                         $badgeClass = "badge-purple"; 
                         $isSubmittedForReview = true;
                    }

                    // Organize Assignees
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
                <div class="task-card" id="task-card-<?=$task['id']?>" style="background: white; border-radius: 12px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #E5E7EB; position: relative;">
                    
                    <!-- Edit Button (Absolute) -->
                    <a href="edit-task.php?id=<?= $task['id'] ?>" style="position: absolute; top: 24px; right: 24px; color: #9CA3AF; text-decoration: none; font-size: 14px; z-index: 10;">
                        <i class="fa fa-pencil"></i>
                    </a>

                    <!-- Header (Clickable) -->
                    <div style="margin-bottom: 0px; display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="toggleTask(<?=$task['id']?>)">
                        <i class="fa fa-chevron-right task-toggle-icon" id="toggle-icon-<?=$task['id']?>" style="color: #6B7280; font-size: 12px;"></i>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;"><?= htmlspecialchars($task['title']) ?></h3>
                        
                        <?php if($isSubmittedForReview) { ?>
                            <span style="background: #F3E8FF; color: #7E22CE; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: lowercase;">submitted_for_review</span>
                        <?php } else { ?>
                            <span class="badge <?= $badgeClass ?>"><?= $statusDisplay ?></span>
                        <?php } ?>
                    </div>

                    <!-- Details (Hidden by Default) -->
                    <div class="task-details" id="task-details-<?=$task['id']?>" style="display: none; margin-top: 24px;">
                        
                        <!-- Description -->
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 24px; padding-left: 20px;">
                            <?= htmlspecialchars($task['description']) ?>
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
                                        <?php $lStats = get_user_rating_stats($pdo, $leader['user_id']); ?>
                                        <i class="fa fa-star"></i> <?= $lStats['avg'] ?>/5
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
                                                <?php $mStats = get_user_rating_stats($pdo, $member['user_id']); ?>
                                                <i class="fa fa-star"></i> <?= $mStats['avg'] ?>/5
                                            </div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>

                            <!-- Footer Info (Due Date & Rating) -->
                            <div style="margin-top: 20px;">
                                <div style="color: #6B7280; font-size: 12px;">
                                    Due: <?= empty($task['due_date']) ? 'No Date' : date("F j, Y", strtotime($task['due_date'])) ?>
                                </div>

                                <!-- Rating & Feedback Display (Task Level) -->
                                <?php if ($task['status'] == 'completed' && $task['rating'] > 0) { ?>
                                    <div style="margin-top: 8px; font-size: 13px; color: #4B5563;">
                                        <span style="color: #F59E0B; font-weight: 600;"><i class="fa fa-star"></i> <?= $task['rating'] ?>/5</span> 
                                        <?php if(!empty($task['review_comment'])) { ?>
                                            - <?= htmlspecialchars($task['review_comment']) ?>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            
                        </div>
                    </div>

                </div>
                <?php } ?>
            <?php } else { ?>
                 <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                    <i class="fa fa-folder-open-o" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No tasks found</h3>
                </div>
            <?php } ?>
        </div>

    </div>

    <!-- Toggle Script -->
    <script>
        function toggleTask(taskId) {
            var details = document.getElementById("task-details-" + taskId);
            var card = document.getElementById("task-card-" + taskId);
            
            if (details.style.display === "none") {
                details.style.display = "block";
                card.classList.add("expanded");
            } else {
                details.style.display = "none";
                card.classList.remove("expanded");
            }
        }
    </script>
</body>
</html>
<?php 
} else {
    header("Location: login.php?error=First login");
    exit();
}
?>
