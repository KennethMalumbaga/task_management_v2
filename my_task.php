<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/Task.php";
    include "app/model/user.php";
    include "app/model/Subtask.php"; // Include subtask model
    include "app/model/LeaderFeedback.php";
    require_once "inc/csrf.php";

    $tasks = get_all_tasks_by_user($pdo, $_SESSION['id']);
    if (is_array($tasks) && !empty($tasks)) {
        foreach ($tasks as $taskRowForSync) {
            $taskIdForSync = (int)($taskRowForSync['id'] ?? 0);
            if ($taskIdForSync <= 0) {
                continue;
            }
            try {
                subtask_sync_all_phases_for_project_task($pdo, $taskIdForSync, (int)$_SESSION['id']);
            } catch (Throwable $syncErr) {
                // Keep page rendering even if auto-backfill fails for a task.
            }
        }
    }

    function tasks_page_has_started_subtasks($subtasks)
    {
        if (!is_array($subtasks) || empty($subtasks)) {
            return false;
        }

        foreach ($subtasks as $subtaskRow) {
            $subtaskStatus = strtolower(trim((string)($subtaskRow['status'] ?? 'pending')));
            if (in_array($subtaskStatus, ['submitted', 'completed', 'in_progress', 'revise', 'revision_needed', 'rejected'], true)) {
                return true;
            }
        }
        return false;
    }

    function tasks_page_resolve_status($taskRow, $subtasks)
    {
        $taskStatus = strtolower(trim((string)($taskRow['status'] ?? 'pending')));
        $taskRating = isset($taskRow['rating']) ? (float)$taskRow['rating'] : 0.0;

        if ($taskStatus === 'completed' && $taskRating <= 0) {
            return [
                'code' => 'submitted_review',
                'badge_class' => 'badge-v2 submitted',
                'label' => 'submitted for review',
                'is_awaiting_review' => true,
            ];
        }

        if ($taskStatus === 'completed') {
            return [
                'code' => 'completed',
                'badge_class' => 'badge-v2 completed',
                'label' => 'completed',
                'is_awaiting_review' => false,
            ];
        }

        if (tasks_page_has_started_subtasks($subtasks) || $taskStatus === 'in_progress') {
            return [
                'code' => 'in_progress',
                'badge_class' => 'badge-v2 in_progress',
                'label' => 'in progress',
                'is_awaiting_review' => false,
            ];
        }

        return [
            'code' => 'pending',
            'badge_class' => 'badge-v2 pending',
            'label' => 'pending',
            'is_awaiting_review' => false,
        ];
    }

    $text = "Tasks";
    $statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';
    $dueFilter = isset($_GET['due_date']) ? (string)$_GET['due_date'] : '';

    if ($dueFilter === 'Due Today') {
        $text = "Due Today";
    } elseif ($dueFilter === 'Overdue') {
        $text = "Overdue";
    } elseif ($statusFilter === 'Pending') {
        $text = "Pending";
    } elseif ($statusFilter === 'in_progress') {
        $text = "In Progress";
    } elseif ($statusFilter === 'Completed') {
        $text = "Completed";
    }

    $allTasksForStats = is_array($tasks) ? $tasks : [];
    $allTaskCount = is_array($allTasksForStats) ? count($allTasksForStats) : 0;
    $allInProgressCount = 0;
    $allCompletedCount = 0;
    $allDueTodayCount = 0;
    $todayDate = date('Y-m-d');

    if (is_array($allTasksForStats)) {
        foreach ($allTasksForStats as $taskStatGlobal) {
            $taskGlobalId = (int)($taskStatGlobal['id'] ?? 0);
            if ($taskGlobalId <= 0) {
                continue;
            }
            $subtasksGlobal = [];
            try {
                $subtasksGlobal = get_subtasks_by_task($pdo, $taskGlobalId);
            } catch (Throwable $e) {
                $subtasksGlobal = [];
            }
            $globalResolved = tasks_page_resolve_status($taskStatGlobal, is_array($subtasksGlobal) ? $subtasksGlobal : []);
            if (($globalResolved['code'] ?? '') === 'in_progress') {
                $allInProgressCount++;
            }
            if (in_array(($globalResolved['code'] ?? ''), ['completed', 'submitted_review'], true)) {
                $allCompletedCount++;
            }
            if (!empty($taskStatGlobal['due_date'])) {
                $dueDateOnly = date('Y-m-d', strtotime((string)$taskStatGlobal['due_date']));
                if ($dueDateOnly === $todayDate && !in_array(($globalResolved['code'] ?? ''), ['completed', 'submitted_review'], true)) {
                    $allDueTodayCount++;
                }
            }
        }
    }

    $taskSubtasksMap = [];
    $taskViewStatusMap = [];
    if (is_array($tasks)) {
        foreach ($tasks as $taskStatusRow) {
            $taskStatusId = (int)($taskStatusRow['id'] ?? 0);
            if ($taskStatusId <= 0) {
                continue;
            }
            $subtasksForStatus = [];
            try {
                $subtasksForStatus = get_subtasks_by_task($pdo, $taskStatusId);
            } catch (Throwable $e) {
                $subtasksForStatus = [];
            }
            if (!is_array($subtasksForStatus)) {
                $subtasksForStatus = [];
            }
            $taskSubtasksMap[$taskStatusId] = $subtasksForStatus;
            $taskViewStatusMap[$taskStatusId] = tasks_page_resolve_status($taskStatusRow, $subtasksForStatus);
        }
    }

    if ($dueFilter === 'Due Today') {
        $filteredTasks = [];
        foreach ((array)$tasks as $taskFilterRow) {
            $taskFilterId = (int)($taskFilterRow['id'] ?? 0);
            $resolvedCode = (string)($taskViewStatusMap[$taskFilterId]['code'] ?? 'pending');
            $rawTaskStatus = strtolower(trim((string)($taskFilterRow['status'] ?? 'pending')));
            if (!empty($taskFilterRow['due_date'])) {
                $dueDateOnly = date('Y-m-d', strtotime((string)$taskFilterRow['due_date']));
                if ($dueDateOnly === $todayDate && !in_array($resolvedCode, ['completed', 'submitted_review'], true) && $rawTaskStatus !== 'completed') {
                    $filteredTasks[] = $taskFilterRow;
                }
            }
        }
        $tasks = $filteredTasks;
    } elseif ($dueFilter === 'Overdue') {
        $filteredTasks = [];
        foreach ((array)$tasks as $taskFilterRow) {
            $taskFilterId = (int)($taskFilterRow['id'] ?? 0);
            $resolvedCode = (string)($taskViewStatusMap[$taskFilterId]['code'] ?? 'pending');
            $rawTaskStatus = strtolower(trim((string)($taskFilterRow['status'] ?? 'pending')));
            if (!empty($taskFilterRow['due_date'])) {
                $dueDateOnly = date('Y-m-d', strtotime((string)$taskFilterRow['due_date']));
                if ($dueDateOnly < $todayDate && !in_array($resolvedCode, ['completed', 'submitted_review'], true) && $rawTaskStatus !== 'completed') {
                    $filteredTasks[] = $taskFilterRow;
                }
            }
        }
        $tasks = $filteredTasks;
    } elseif ($statusFilter === 'Pending' || $statusFilter === 'in_progress' || $statusFilter === 'Completed') {
        $filteredTasks = [];
        foreach ((array)$tasks as $taskFilterRow) {
            $taskFilterId = (int)($taskFilterRow['id'] ?? 0);
            $resolvedCode = (string)($taskViewStatusMap[$taskFilterId]['code'] ?? 'pending');
            $rawTaskStatus = strtolower(trim((string)($taskFilterRow['status'] ?? 'pending')));

            if ($statusFilter === 'Pending' && ($resolvedCode === 'pending' || $rawTaskStatus === 'pending')) {
                $filteredTasks[] = $taskFilterRow;
            } elseif ($statusFilter === 'in_progress' && ($resolvedCode === 'in_progress' || $rawTaskStatus === 'in_progress')) {
                $filteredTasks[] = $taskFilterRow;
            } elseif (
                $statusFilter === 'Completed'
                && (in_array($resolvedCode, ['completed', 'submitted_review'], true) || $rawTaskStatus === 'completed')
            ) {
                $filteredTasks[] = $taskFilterRow;
            }
        }
        $tasks = $filteredTasks;
    }

    $shownTaskCount = is_array($tasks) ? count($tasks) : 0;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/task_redesign.css">
    <link rel="stylesheet" href="css/tasks-page.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Ensure global modal helpers exist even if later PHP errors truncate output.
        if (typeof window.openTaskModal !== "function") {
            window.openTaskModal = function(taskId) {
                var modal = document.getElementById("modal-task-" + taskId);
                if (modal) {
                    modal.style.display = "flex";
                    document.body.style.overflow = "hidden";
                }
            };
        }
        if (typeof window.closeTaskModal !== "function") {
            window.closeTaskModal = function(taskId) {
                var modal = document.getElementById("modal-task-" + taskId);
                if (modal) {
                    modal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            };
        }
        // Vanilla auto-open fallback (works even if jQuery fails to load).
        document.addEventListener("DOMContentLoaded", function () {
            var params = new URLSearchParams(window.location.search);
            var openTaskId = params.get("open_task");
            if (openTaskId && typeof window.openTaskModal === "function") {
                window.openTaskModal(openTaskId);
                var el = document.getElementById("task-card-" + openTaskId);
                if (el) {
                    el.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            }
        });
    </script>
    <style>
        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #E5E7EB;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .task-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }

        .task-title {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }

        .leader-box-preview {
            background: #F5F3FF;
            border: 1px solid #E0E7FF;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            width: fit-content;
            min-width: 200px;
        }

        .task-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #F3F4F6;
            color: #6B7280;
            font-size: 13px;
        }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        
        /* Secondary modals needs higher z-index */
        #taskSubmissionModal, #resubmitModal {
            z-index: 2200; /* Higher than task details */
        }
        
        /* Modal Box for secondary alerts */
        .modal-box {
            background: white;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Ensure Modals appear on top */
        .modal-overlay {
            z-index: 2000 !important; /* Force higher z-index */
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .close-modal {
            position: absolute;
            top: 24px;
            right: 24px;
            border: none;
            background: none;
            cursor: pointer;
            color: #6B7280;
            font-size: 20px;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: #F3F4F6;
            color: #111827;
        }
        
        .modal-header-section {
            padding-bottom: 24px;
            border-bottom: 1px solid #E5E7EB;
            margin-bottom: 24px;
            padding-right: 40px; /* Space for close button */
        }

        .leader-box {
            background: #F5F3FF; 
            border: 1px solid #E0E7FF;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .section-label {
            font-size: 11px;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .info-box {
            background: #F9FAFB;
            padding: 12px;
            border-radius: 8px;
        }

        /* Subtask & Form Styles */
        .btn-indigo-light { background: #EEF2FF; color: #6C3CE1; border: 1px solid #C7D2FE; }
        .btn-indigo-light:hover { background: #E0E7FF; }
        
        .subtask-card {
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .tasks-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }
            
            .task-card {
                padding: 12px !important;
                border-radius: 8px !important;
            }
            
            .task-title {
                font-size: 13px !important;
                margin-bottom: 4px !important;
                line-height: 1.3 !important;
            }
            
            /* Compact Badge */
            .badge-v2 {
                font-size: 9px !important;
                padding: 2px 6px !important;
            }
            
            .preview-content div[style*="font-size: 14px"] {
                font-size: 11px !important;
                margin-bottom: 10px !important;
                line-height: 1.3 !important;
                height: 2.6em; /* Limit height roughly 2 lines */
                overflow: hidden;
            }
            
            .leader-box-preview {
                min-width: unset !important;
                width: 100% !important;
                padding: 6px !important;
                gap: 8px !important;
                margin-bottom: 8px !important;
            }
            
            .leader-box-preview img {
                width: 24px !important;
                height: 24px !important;
            }
            
            .leader-box-preview div:nth-child(2) div:first-child {
                font-size: 8px !important;
            }
            
            .leader-box-preview div:nth-child(2) div:last-child {
                font-size: 11px !important;
            }
            
            .task-footer {
                margin-top: 10px !important;
                padding-top: 10px !important;
                font-size: 10px !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 5px !important;
            }
            
             .task-footer i {
                font-size: 10px !important;
             }
        }
    </style>
</head>
<body class="tasks-page">
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        <div class="tasks-shell">
            <section class="tasks-hero">
                <div class="tasks-hero-main">
                    <h2><?= htmlspecialchars((string)$text) ?> Board</h2>
                    <p>Track priorities, audit progress, and review completions from one focused control surface designed for daily task operations.</p>
                    <div class="tasks-filter-row">
                        <a href="my_task.php" class="tasks-filter-pill <?= (!isset($_GET['due_date']) && !isset($_GET['status'])) ? 'active' : '' ?>">All</a>
                        <a href="my_task.php?due_date=Due+Today" class="tasks-filter-pill <?= (isset($_GET['due_date']) && $_GET['due_date'] === 'Due Today') ? 'active' : '' ?>">Due Today</a>
                        <a href="my_task.php?due_date=Overdue" class="tasks-filter-pill <?= (isset($_GET['due_date']) && $_GET['due_date'] === 'Overdue') ? 'active' : '' ?>">Overdue</a>
                        <a href="my_task.php?status=Pending" class="tasks-filter-pill <?= (isset($_GET['status']) && $_GET['status'] === 'Pending') ? 'active' : '' ?>">Pending</a>
                        <a href="my_task.php?status=in_progress" class="tasks-filter-pill <?= (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'active' : '' ?>">In Progress</a>
                        <a href="my_task.php?status=Completed" class="tasks-filter-pill <?= (isset($_GET['status']) && $_GET['status'] === 'Completed') ? 'active' : '' ?>">Completed</a>
                    </div>
                </div>
                <div class="tasks-hero-side">
                    <div class="tasks-hero-stats">
                        <div class="tasks-hero-stat">
                            <span>All Tasks</span>
                            <strong><?= (int)$allTaskCount ?></strong>
                            <small>total task<?= $allTaskCount === 1 ? '' : 's' ?> assigned to you</small>
                        </div>
                        <div class="tasks-hero-stat">
                            <span>In Progress</span>
                            <strong><?= (int)$allInProgressCount ?></strong>
                            <small>active across your tasks</small>
                        </div>
                        <div class="tasks-hero-stat">
                            <span>Completed</span>
                            <strong><?= (int)$allCompletedCount ?></strong>
                            <small>ready for audit/review</small>
                        </div>
                        <div class="tasks-hero-stat">
                            <span>Due Today</span>
                            <strong><?= (int)$allDueTodayCount ?></strong>
                            <small>needs immediate focus</small>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (isset($_GET['success'])) {?>
                <div class="tasks-flash success">
                    <i class="fa fa-check-circle"></i>
                    <span><?php echo stripcslashes($_GET['success']); ?></span>
                </div>
            <?php } ?>
            <?php if (isset($_GET['error'])) {?>
                <div class="tasks-flash error">
                    <i class="fa fa-exclamation-circle"></i>
                    <span><?php echo stripcslashes($_GET['error']); ?></span>
                </div>
            <?php } ?>

            <section class="tasks-board">
                <div class="tasks-board-head">
                    <div>
                        <h3><?= htmlspecialchars((string)$text) ?></h3>
                        <p>Open any card to inspect full details, subtasks, ratings, and review actions.</p>
                    </div>
                    <div class="tasks-board-head-actions">
                        <span class="tasks-board-count"><?= (int)$shownTaskCount ?> record<?= $shownTaskCount === 1 ? '' : 's' ?></span>
                        <?php if ($_SESSION['role'] == 'admin') { ?>
                        <a href="create_task.php" class="tasks-board-create-btn">
                            <i class="fa fa-plus"></i> Create Task
                        </a>
                        <?php } ?>
                    </div>
                </div>

                <div class="tasks-grid">
            <?php if (!empty($tasks)) { 
                foreach ($tasks as $task) { 
                    $taskId = (int)($task['id'] ?? 0);
                    $isLeader = is_leader($pdo, $taskId, $_SESSION['id']);
                    $subtasks = $taskSubtasksMap[$taskId] ?? [];
                    $taskViewStatus = $taskViewStatusMap[$taskId] ?? [
                        'code' => 'pending',
                        'badge_class' => 'badge-v2 pending',
                        'label' => 'pending',
                        'is_awaiting_review' => false,
                    ];
                    $badgeClass = (string)$taskViewStatus['badge_class'];
                    $statusDisplay = (string)$taskViewStatus['label'];

                    // Prepare Assignees Data
                    $assignees = get_task_assignees($pdo, $taskId);
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
            <!-- Task Card (Trigger) -->
            <div class="task-card" id="task-card-<?=$taskId?>" onclick="openTaskModal(<?=$taskId?>)">
                
                <!-- Action Buttons (Admin Edit) -->
                <?php if($_SESSION['role'] == 'admin') { ?>
                    <object><a href="tasks.php?open_task=<?=$taskId?>" style="position: absolute; top: 24px; right: 24px; color: #9CA3AF; text-decoration: none; font-size: 14px; z-index: 10;"><i class="fa fa-pencil"></i></a></object>
                <?php } ?>

                <div style="margin-bottom: 12px;">
                    <h3 class="task-title"><?= htmlspecialchars($task['title']) ?></h3>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <span class="<?= $badgeClass ?>"><?= $statusDisplay ?></span>
                </div>
                
                <!-- Preview Content -->
                <div class="preview-content">
                    <div style="color: #6B7280; font-size: 14px; margin-bottom: 16px; line-height: 1.5;">
                        <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 100, "...")) ?>
                    </div>

                    <?php if ($leader) { 
                        $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                    ?>
                    <div class="leader-box-preview">
                        <img src="<?= $leaderImg ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                        <div>
                            <div style="font-size: 10px; font-weight: 700; color: #8B5CF6; letter-spacing: 0.5px; text-transform: uppercase;">
                                <i class="fa fa-crown" style="margin-right: 4px;"></i> Project Leader
                            </div>
                            <div style="font-weight: 600; color: #1F2937; font-size: 13px;">
                                <?= htmlspecialchars($leader['full_name']) ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if (!empty($members)) { ?>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-users" style="color: #059669; font-size: 12px;"></i>
                        <div style="font-size: 12px; font-weight: 600; color: #059669;">Team Members</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                        <div style="display: flex; padding-left: 8px;">
                            <?php foreach (array_slice($members, 0, 4) as $m) { 
                                $mImg = !empty($m['profile_image']) ? 'uploads/' . $m['profile_image'] : 'img/user.png';
                            ?>
                            <img src="<?= $mImg ?>" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; margin-left: -8px; object-fit: cover; background: #E5E7EB;">
                            <?php } ?>
                        </div>
                        <span style="font-size: 12px; color: #6B7280;"><?= count($members) ?> member<?= count($members)>1?'s':''?></span>
                    </div>
                    <?php } ?>
                </div>

                <!-- Footer -->
                <div class="task-footer">
                    <div>Due: <?= empty($task['due_date']) ? 'No Date' : date("M d", strtotime($task['due_date'])) ?></div>
                    <?php if ($task['status'] == 'completed' && isset($task['rating']) && (float)$task['rating'] > 0) { ?>
                    <div style="color: #F59E0B; font-weight: 600;"><i class="fa fa-star"></i> <?= number_format((float)$task['rating'], 1) ?>/5</div>
                    <?php } ?>
                </div>
            </div>

            <!-- MODALS REMOVED FROM HERE, MOVED TO BOTTOM -->
                 <?php } ?>
            <?php } else { ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6B7280;">
                    <i class="fa fa-folder-open-o" style="font-size: 48px; opacity: 0.5; margin-bottom: 15px;"></i>
                    <h3>No tasks found</h3>
                </div>
            <?php } ?>
                </div>
            </section>
        </div>
    </div>

    <!-- MODALS GENERATED OUTSIDE GRID (Second Loop) -->
    <?php if (!empty($tasks)) { 
        foreach ($tasks as $task) { 
            // Re-populate variables needed for Modal
            $taskId = (int)($task['id'] ?? 0);
            $isLeader = is_leader($pdo, $taskId, $_SESSION['id']);
            $subtasks = $taskSubtasksMap[$taskId] ?? [];
            $taskViewStatus = $taskViewStatusMap[$taskId] ?? [
                'code' => 'pending',
                'badge_class' => 'badge-v2 pending',
                'label' => 'pending',
                'is_awaiting_review' => false,
            ];
            $badgeClass = (string)$taskViewStatus['badge_class'];
            $statusDisplay = (string)$taskViewStatus['label'];
            $assignees = get_task_assignees($pdo, $taskId);
            $leader = null; $members = [];
            if ($assignees != 0) {
               foreach ($assignees as $a) { if ($a['role'] == 'leader') $leader = $a; else $members[] = $a; }
            }
    ?>
            <!-- MODAL STRUCTURE -->
            <div class="modal-overlay" id="modal-task-<?=$taskId?>" onclick="if(event.target === this) closeTaskModal(<?=$taskId?>)">
                <div class="modal-content">
                    <button class="close-modal" onclick="closeTaskModal(<?=$taskId?>)"><i class="fa fa-times"></i></button>

                    <div class="modal-header-section">
                        <h2 style="margin: 0 0 10px 0; font-size: 20px; color: #111827;"><?= htmlspecialchars($task['title']) ?></h2>
                        <span class="<?= $badgeClass ?>"><?= $statusDisplay ?></span>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <div class="section-label">Description</div>
                        <div style="color: #374151; font-size: 14px; line-height: 1.6;">
                             <?= nl2br(htmlspecialchars($task['description'])) ?>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-box">
                            <div class="section-label"><i class="fa fa-calendar"></i> Due Date</div>
                            <div style="font-weight: 500; font-size: 14px;"><?= empty($task['due_date']) ? 'No Date' : date("M j, Y", strtotime($task['due_date'])) ?></div>
                        </div>
                        <div class="info-box">
                            <div class="section-label"><i class="fa fa-clock-o"></i> Created</div>
                            <div style="font-weight: 500; font-size: 14px;"><?= isset($task['created_at']) ? date("M j, Y", strtotime($task['created_at'])) : 'Unknown' ?></div>
                        </div>
                    </div>

                    <?php if ($task['status'] == 'completed' && isset($task['rating']) && $task['rating'] > 0) { ?>
                    <div class="rating-feedback-box">
                        <div class="rating-header">
                            <i class="fa fa-star"></i> <?= $task['rating'] ?>/5
                        </div>
                        <div class="rating-feedback-text">
                            <?= !empty($task['review_comment']) ? htmlspecialchars($task['review_comment']) : 'No feedback provided.' ?>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- Profiles In Modal -->
                    <?php if ($leader) { 
                        $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                    ?>
                    <div class="section-label">Project Leader</div>
                    <div class="leader-box">
                        <img src="<?= $leaderImg ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                        <div>
                            <div style="font-size: 10px; font-weight: 700; color: #8B5CF6; letter-spacing: 0.5px; text-transform: uppercase;">
                                <i class="fa fa-crown" style="margin-right: 4px;"></i> Project Leader
                            </div>
                            <div style="font-weight: 600; color: #1F2937; font-size: 14px;">
                                <?= htmlspecialchars($leader['full_name']) ?>
                            </div>
                            <div style="font-size: 11px; color: #6B7280; display: flex; gap: 10px; margin-top: 4px;">
                                <?php $lStats = get_user_rating_stats($pdo, $leader['user_id']); ?>
                                <span><i class="fa fa-star" style="color:#F59E0B"></i> <?= $lStats['avg'] ?>/5</span>
                                <?php $lCollab = get_collaborative_scores_by_user($pdo, $leader['user_id']); ?>
                                <span style="color: #8B5CF6;"><i class="fa fa-users"></i> Collab: <?= $lCollab['avg'] ?>/5</span>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php
                        $isCurrentUserMember = false;
                        foreach ($members as $memberCheck) {
                            if ((int)$memberCheck['user_id'] === (int)$_SESSION['id']) {
                                $isCurrentUserMember = true;
                                break;
                            }
                        }

                        $canRateLeader = !$isLeader
                            && $isCurrentUserMember
                            && $leader
                            && $task['status'] == 'completed';

                        $myLeaderRating = null;
                        if ($canRateLeader) {
                            $myLeaderRating = get_member_leader_feedback($pdo, $task['id'], $leader['user_id'], $_SESSION['id']);
                        }
                    ?>

                    <?php if ($canRateLeader) { ?>
                    <div style="margin-bottom: 24px; border: 1px solid #DBEAFE; background: #EFF6FF; border-radius: 10px; padding: 14px;">
                        <div style="font-size: 12px; font-weight: 700; color: #1D4ED8; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 8px;">
                            Rate Your Leader
                        </div>
                        <?php if ($myLeaderRating) { ?>
                            <div style="font-size: 12px; color: #1E3A8A; margin-bottom: 10px;">
                                Your current rating: <strong><?= (int)$myLeaderRating['rating'] ?>/5</strong>
                                <?php if (!empty($myLeaderRating['updated_at'])) { ?>
                                    (updated <?= date("M j, Y g:i A", strtotime($myLeaderRating['updated_at'])) ?>)
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <form action="app/rate-leader.php" method="POST">
                            <?= csrf_field('rate_leader_form') ?>
                            <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                            <input type="hidden" name="rating" id="leader-rating-<?= (int)$task['id'] ?>" value="<?= $myLeaderRating ? (int)$myLeaderRating['rating'] : 0 ?>">
                            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 8px; align-items: center;">
                                <label style="font-size: 13px; font-weight: 600; color: #1F2937;">Score</label>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <?php for ($r = 1; $r <= 5; $r++) { ?>
                                            <?php $active = ($myLeaderRating && (int)$myLeaderRating['rating'] >= $r); ?>
                                            <i class="fa fa-star leader-star-<?= (int)$task['id'] ?>"
                                               data-value="<?= $r ?>"
                                               style="cursor: pointer; font-size: 22px; color: <?= $active ? '#F59E0B' : '#D1D5DB' ?>;"
                                               onmouseover="previewLeaderStars(<?= (int)$task['id'] ?>, <?= $r ?>)"
                                               onmouseout="restoreLeaderStars(<?= (int)$task['id'] ?>)"
                                               onclick="setLeaderScore(<?= (int)$task['id'] ?>, <?= $r ?>)"></i>
                                        <?php } ?>
                                        <span id="leader-rating-label-<?= (int)$task['id'] ?>" style="margin-left: 4px; font-size: 12px; color: #6B7280;">
                                            <?= $myLeaderRating ? ((int)$myLeaderRating['rating'] . '/5') : 'Not rated' ?>
                                        </span>
                                    </div>
                                </div>

                                <label style="font-size: 13px; font-weight: 600; color: #1F2937;">Comment</label>
                                <textarea name="comment" class="form-input-v2" rows="2" placeholder="Optional feedback about leadership/collaboration..."><?= $myLeaderRating && !empty($myLeaderRating['comment']) ? htmlspecialchars($myLeaderRating['comment']) : '' ?></textarea>
                            </div>
                            <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn-v2 btn-indigo">
                                    <i class="fa fa-paper-plane"></i> <?= $myLeaderRating ? 'Update Rating' : 'Submit Rating' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php } ?>

                    <?php if (!empty($members)) { ?>
                    <div class="section-label">Team Members</div>
                    <div style="background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 8px; padding: 12px; margin-bottom: 24px;">
                        <?php foreach ($members as $member) { 
                            $memImg = !empty($member['profile_image']) ? 'uploads/' . $member['profile_image'] : 'img/user.png';
                        ?>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; last-child: margin-bottom: 0;">
                            <img src="<?= $memImg ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                            <div>
                                <div style="font-weight: 500; font-size: 13px; color: #1F2937;"><?= htmlspecialchars($member['full_name']) ?></div>
                                <div style="font-size: 11px; color: #6B7280; display: flex; gap: 10px; margin-top: 2px;">
                                    <?php $mStats = get_user_rating_stats($pdo, $member['user_id']); ?>
                                    <span><i class="fa fa-star" style="color:#F59E0B"></i> <?= $mStats['avg'] ?>/5</span>
                                    <?php $mCollab = get_collaborative_scores_by_user($pdo, $member['user_id']); ?>
                                    <span style="color: #8B5CF6;"><i class="fa fa-users"></i> Collab: <?= $mCollab['avg'] ?>/5</span>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- SUBTASKS SECTION -->
                    <div class="section-label" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Subtasks</span>
                        <span style="font-size: 12px; color: #6B7280;">Auto-generated from timeline phases</span>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <?php if (!empty($subtasks)) { 
                            foreach($subtasks as $sub) { 
                                $subStatusClass = "pending";
                                if ($sub['status'] == 'in_progress') $subStatusClass = "in_progress";
                                if ($sub['status'] == 'completed') $subStatusClass = "completed";
                                if ($sub['status'] == 'submitted') $subStatusClass = "submitted";
                                if ($sub['status'] == 'revise') $subStatusClass = "revision_needed"; 
                                if ($sub['status'] == 'rejected') $subStatusClass = "rejected";
                        ?>
                        <div class="subtask-card">
                            <div class="subtask-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <div style="font-weight: 500; font-size: 14px; color: #1F2937; margin-bottom: 4px;"><?= htmlspecialchars($sub['description']) ?></div> 
                                    <div style="font-size: 12px; color: #6B7280;">
                                        <i class="fa fa-user"></i> Assigned to: <?= htmlspecialchars($sub['member_name']) ?>
                                    </div>
                                    <?php if (!empty($sub['timeline_phase_name'])) { ?>
                                        <div style="font-size: 12px; color: #6C3CE1;">
                                            <i class="fa fa-flag"></i> Phase: <?= htmlspecialchars((string)$sub['timeline_phase_name']) ?>
                                        </div>
                                    <?php } ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($sub['score'])) { ?>
                                        <span style="color: #F59E0B; font-size: 13px;" title="Performance Score">
                                            <?php for($s=1; $s<=5; $s++) { echo ($s <= $sub['score']) ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star-o"></i>'; } ?>
                                        </span>
                                    <?php } ?>
                                    <span class="badge-v2 <?=$subStatusClass?>"><?= str_replace('_',' ', $sub['status']) ?></span>
                                </div>
                            </div>

                             <!-- Submission View -->
                             <?php if(!empty($sub['submission_file']) || $sub['status'] == 'submitted' || $sub['status'] == 'completed') { ?>
                                <div style="background: #F9FAFB; border-radius: 6px; padding: 10px; margin-top: 10px; border: 1px solid #F3F4F6;">
                                    <span style="font-size: 12px; font-weight: 600; color: #374151;">Submission:</span>
                                    <?php if(!empty($sub['submission_note'])) { ?>
                                        <div style="font-style: italic; font-size: 13px; color: #4B5563; margin: 4px 0;">
                                            "<?= htmlspecialchars($sub['submission_note']) ?>"
                                        </div>
                                    <?php } ?>
                                    <div style="margin-top: 4px;">
                                        <?php if($sub['submission_file']) { ?>
                                            <a href="<?=$sub['submission_file']?>" target="_blank" style="color: #6C3CE1; font-size: 13px;"><i class="fa fa-paperclip"></i> View File</a>
                                        <?php } else { ?>
                                            <span style="font-size: 13px; color: #6B7280;">Submitted (No file)</span>
                                        <?php } ?>
                                    </div>
                                </div>
                             <?php } ?>

                             <!-- Review Feedback View -->
                             <?php if(!empty($sub['feedback'])) { ?>
                                <div style="margin-top: 10px; padding: 10px; border-radius: 6px; font-size: 13px; <?= $sub['status'] == 'completed' ? 'background: #F0FDF4; color: #166534;' : 'background: #FFF7ED; color: #9A3412;' ?>">
                                    <div style="font-weight: 600; margin-bottom: 4px;">
                                        <i class="fa <?=$sub['status'] == 'completed' ? 'fa-check' : 'fa-exclamation-circle'?>"></i> 
                                        Review Feedback:
                                    </div>
                                    <?= htmlspecialchars($sub['feedback']) ?>
                                </div>
                             <?php } ?>

                             <!-- Actions for Leader -->
                             <?php if($isLeader && $sub['status'] == 'submitted') { ?>
                                <div style="margin-top: 15px; border-top: 1px solid #F3F4F6; padding-top: 12px;">
                                    <form action="app/review-subtask.php" method="POST" id="review-form-<?=$sub['id']?>">
                                        <?= csrf_field('review_subtask_form') ?>
                                        <input type="hidden" name="subtask_id" value="<?=$sub['id']?>">
                                        <input type="hidden" name="parent_id" value="<?=$task['id']?>"> 
                                        
                                        <textarea name="feedback" class="form-input-v2" rows="2" placeholder="Review feedback..." style="width: 100%; margin-bottom: 10px; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;"></textarea>
                                        
                                        <?php $canScoreSubtask = ((int)$sub['member_id'] !== (int)$_SESSION['id']); ?>
                                        <?php if ($canScoreSubtask) { ?>
                                            <div style="margin-bottom: 15px;">
                                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px;">Performance Score (for Accept)</label>
                                                <div class="star-rating-<?=$sub['id']?>" style="display: flex; align-items: center; gap: 5px;">
                                                    <?php for($i=1; $i<=5; $i++) { ?>
                                                        <label style="cursor: pointer; font-size: 24px; color: #D1D5DB; transition: color 0.15s;"
                                                               onmouseover="highlightStars(<?=$sub['id']?>, <?=$i?>)"
                                                               onmouseout="resetStars(<?=$sub['id']?>)">
                                                            <input type="radio" name="score" value="<?=$i?>" style="display: none;" onclick="setScore(<?=$sub['id']?>, <?=$i?>)">
                                                            <i class="fa fa-star star-<?=$sub['id']?>-<?=$i?>"></i>
                                                        </label>
                                                    <?php } ?>
                                                    <span id="score-label-<?=$sub['id']?>" style="margin-left: 8px; font-size: 13px; color: #6B7280;">Not rated</span>
                                                </div>
                                            </div>
                                        <?php } else { ?>
                                            <div style="margin-bottom: 15px; background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; border-radius: 8px; padding: 10px; font-size: 12px;">
                                                <i class="fa fa-info-circle"></i> Self-scoring is disabled for leader-assigned subtasks.
                                            </div>
                                        <?php } ?>
                                        
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
                                <div style="margin-top: 15px; border-top: 1px solid #F3F4F6; padding-top: 12px;">
                                    <form action="app/update-subtask-submission.php" method="POST" enctype="multipart/form-data">
                                        <?= csrf_field('update_subtask_submission_form') ?>
                                        <input type="hidden" name="id" value="<?=$sub['id']?>">
                                        
                                        <div style="margin-bottom: 10px;">
                                            <textarea name="submission_note" class="form-input-v2" rows="2" placeholder="Add a description or note..." style="width: 100%; padding: 8px; border: 1px solid #D1D5DB; border-radius: 6px;"></textarea>
                                        </div>

                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <input type="file" name="submission_file" class="form-input-v2" style="width: auto;" required>
                                            <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">(up to 50MB)</div>
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

                    <!-- TASK SUBMISSION (Final) -->
                     <?php 
                        $allSubtasksDone = false;
                        if (!empty($subtasks)) {
                            $allSubtasksDone = true;
                            foreach($subtasks as $sub){
                                if($sub['status'] != 'completed' && $sub['status'] != 'submitted') {
                                    $allSubtasksDone = false; break;
                                }
                            }
                        }
                        $isRevisionRequested = ($task['status'] == 'in_progress' && !empty($task['review_comment']));
                        
                        if ($isLeader) {
                            if ($isRevisionRequested) {
                    ?>
                                <div style="margin-top: 24px; border: 1px solid #FDBA74; background: #FFF7ED; border-radius: 8px; overflow: hidden;">
                                    <div style="padding: 16px; border-bottom: 1px solid #FFEDD5; display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-exclamation-circle" style="color: #EA580C;"></i>
                                        <span style="color: #9A3412; font-weight: 600; font-size: 14px;">Revision Requested by Admin</span>
                                    </div>
                                    <div style="padding: 16px;">
                                        <div style="margin-bottom: 16px;">
                                            <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">Admin Feedback:</div>
                                            <div style="color: #4B5563; font-size: 14px; line-height: 1.6;"><?= nl2br(htmlspecialchars($task['review_comment'])) ?></div>
                                        </div>
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
                            <div class="completion-banner" style="margin-top: 20px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
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
                        if ($task['status'] == 'completed' && !empty($task['review_comment']) && (empty($task['rating']) || $task['rating'] == 0)) { 
                    ?>
                        <div style="margin-top: 20px; padding: 15px; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px;">
                             <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px;">Admin Detailed Feedback:</div>
                             <div style="color: #4B5563; font-size: 14px; font-style: italic;">
                                "<?= htmlspecialchars($task['review_comment']) ?>"
                            </div>
                        </div>
                    <?php } ?>
                </div>

            </div>
    <?php }} ?>

    <!-- Task Submission Modal -->
    <div id="taskSubmissionModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Submit Task for Review</h3>
            <p style="font-size: 14px; color: #6B7280; margin-bottom: 20px;">
                Are you sure you want to submit this task? This will notify the admin.
            </p>
            
            <form action="app/submit-task-review.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field('submit_task_review_form') ?>
                <input type="hidden" name="task_id" id="modal_task_id">
                 <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Attach New File (Optional) <span style="font-size: 11px; color: #6B7280; font-weight: normal;">(up to 50MB)</span></label>
                    <input type="file" name="submission_file" class="form-input-v2" style="width: 100%;">
                </div>
                
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

             <form action="app/resubmit-task.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field('resubmit_task_form') ?>
                <input type="hidden" name="task_id" id="resubmit_task_id">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Attach New File (Optional) <span style="font-size: 11px; color: #6B7280; font-weight: normal;">(up to 50MB)</span></label>
                    <input type="file" name="submission_file" class="form-input-v2" style="width: 100%;">
                </div>

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

    <!-- Validation Error Modal -->
    <div id="validationErrorModal" class="modal-overlay" style="display: none; z-index: 2400 !important;">
        <div class="modal-box">
            <div style="text-align: center;">
                <i class="fa fa-exclamation-triangle" style="font-size: 48px; color: #EF4444; margin-bottom: 15px;"></i>
                <h3 style="margin: 0; font-size: 20px; color: #111827;">Rating Required</h3>
                <p id="validationErrorText" style="color: #6B7280; font-size: 14px; margin: 10px 0 20px;"></p>
                <div style="display: flex; justify-content: center;">
                    <button type="button" class="btn-v2 btn-red" style="background: #EF4444;" onclick="closeValidationModal()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <?php include "inc/pages/my_task_scripts.php"; ?>
</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>


