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
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;"><?= $text ?></h2>
            
            <a href="create_task.php" class="btn-primary">
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
                    // If completed, check if it has been rated. If NOT rated, show "submitted for review" (purple)
                    // If rated, show "completed" (green) + stars.
                    
                    $isSubmittedForReview = false;
                    if ($task['status'] == 'completed' && ($task['rating'] == 0 || $task['rating'] == NULL)) {
                         $statusDisplay = "submitted for review"; // Lowercase per screenshot
                         $badgeClass = "badge-purple"; // We will add a style or just inline it
                         $isSubmittedForReview = true;
                    }
                ?>
                <div class="task-card" style="background: white; border-radius: 12px; padding: 24px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E5E7EB; position: relative;">
                    
                    <!-- Edit Button -->
                    <a href="edit-task.php?id=<?= $task['id'] ?>" style="position: absolute; top: 20px; right: 20px; color: #9CA3AF; text-decoration: none;">
                        <i class="fa fa-pencil"></i>
                    </a>

                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-chevron-right" style="color: #6B7280; font-size: 12px;"></i>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;"><?= htmlspecialchars($task['title']) ?></h3>
                        
                        <?php if($isSubmittedForReview) { ?>
                            <span style="background: #F3E8FF; color: #7E22CE; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: lowercase;">submitted_for_review</span>
                        <?php } else { ?>
                            <span class="badge <?= $badgeClass ?>"><?= $statusDisplay ?></span>
                        <?php } ?>
                    </div>

                    <div style="color: #4B5563; font-size: 14px; margin-bottom: 16px; padding-left: 20px;">
                        <?= htmlspecialchars($task['description']) ?>
                    </div>

                    <div style="padding-left: 20px; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #6B7280; font-size: 13px;">
                            <i class="fa fa-users"></i> Team: 
                            <?php
                            $assignees = get_task_assignees($pdo, $task['id']);
                            if ($assignees != 0) {
                                foreach ($assignees as $a) {
                                    echo htmlspecialchars($a['full_name']) . ', ';
                                }
                            } else {
                                echo 'Unknown User';
                            }
                            ?>
                        </div>
                        <div style="margin-top: 5px; color: #6B7280; font-size: 13px;">
                            Due: <?= empty($task['due_date']) ? 'No Date' : date("n/j/Y", strtotime($task['due_date'])) ?>
                        </div>
                        
                        <!-- Rating & Feedback Display -->
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
                <?php } ?>
            <?php } else { ?>
                 <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                    <i class="fa fa-folder-open-o" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No tasks found</h3>
                </div>
            <?php } ?>
        </div>

    </div>

</body>
</html>
<?php 
} else {
    header("Location: login.php?error=First login");
    exit();
}
?>
