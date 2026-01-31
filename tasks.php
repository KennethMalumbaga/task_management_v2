<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] === "admin") {
    require_once "DB_connection.php";
    require_once "app/Model/Task.php";
    require_once "app/Model/User.php";

    $text = "All Tasks";
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div>
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;"><?= $text ?></h2>
                <span style="color: var(--text-gray); font-size: 14px;">Manage your team tasks</span>
            </div>
            <a href="create_task.php" class="btn-primary">
                <i class="fa fa-plus"></i> Create Task
            </a>
        </div>

        <div class="table-container">
            <?php if ($tasks != 0) { ?>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach ($tasks as $task) { ?>
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
                            $assignees = get_task_assignees($pdo, $task['id']);
                            if ($assignees != 0) {
                                echo '<div class="user-profile-sm">';
                                foreach ($assignees as $a) {
                                    echo '<div class="user-avatar-sm" title="'.htmlspecialchars($a['full_name']).'">'.strtoupper(substr($a['full_name'],0,1)).'</div>';
                                }
                                echo '</div>';
                            } else {
                                echo '<span style="color: #9CA3AF;">Unassigned</span>';
                            }
                            ?>
                        </td>
                        <td><?= $task['due_date'] ?: 'No Deadline' ?></td>
                        <td>
                            <?php 
                                $badgeClass = "badge-pending";
                                if ($task['status'] == 'in_progress') $badgeClass = "badge-in_progress";
                                if ($task['status'] == 'completed') $badgeClass = "badge-completed";
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= str_replace('_',' ',$task['status']) ?></span>
                        </td>
                        <td>
                            <a href="edit-task.php?id=<?= $task['id'] ?>" class="btn-outline btn-sm">
                                <i class="fa fa-pencil"></i>
                            </a>
                            <a href="delete-task.php?id=<?= $task['id'] ?>" class="btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger);" onclick="return confirm('Are you sure?');">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
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
