<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] === "admin") {
    require_once "DB_connection.php";
    require_once "app/model/Task.php";
    require_once "app/model/Subtask.php";
    require_once "app/model/user.php";
    require_once "app/model/LeaderFeedback.php";
    require_once "inc/csrf.php";

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
        $tasks = get_all_tasks($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "in_progress") {
        $text = "In Progress";
        $tasks = get_all_tasks($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "Completed") {
        $text = "Completed";
        $tasks = get_all_tasks($pdo);
    } else {
        $tasks = get_all_tasks($pdo);
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

    // Global hero stats should remain stable regardless of active tab filters.
    $allTasksForStats = get_all_tasks($pdo);
    $allTaskCount = is_array($allTasksForStats) ? count($allTasksForStats) : 0;
    $allInProgressCount = 0;
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

    $statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';
    if ($statusFilter === 'Pending' || $statusFilter === 'in_progress' || $statusFilter === 'Completed') {
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
    $shownCompleted = 0;
    $shownInProgress = 0;
    $shownPending = 0;
    $shownDueToday = 0;
    $todayDate = date('Y-m-d');

    if (is_array($tasks)) {
        foreach ($tasks as $taskStat) {
            $taskStatId = (int)($taskStat['id'] ?? 0);
            $resolvedCodeStat = (string)($taskViewStatusMap[$taskStatId]['code'] ?? 'pending');
            if ($resolvedCodeStat === 'completed' || $resolvedCodeStat === 'submitted_review') {
                $shownCompleted++;
            } elseif ($resolvedCodeStat === 'in_progress') {
                $shownInProgress++;
            } else {
                $shownPending++;
            }

            if (!empty($taskStat['due_date'])) {
                $dueDateOnly = date('Y-m-d', strtotime((string)$taskStat['due_date']));
                if ($dueDateOnly === $todayDate) {
                    $shownDueToday++;
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tasks | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/task_redesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Ensure jQuery -->
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
            background: var(--primary-soft-2);
            border: 1px solid var(--primary-soft-4);
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

        .task-resource-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }

        .task-resource-chip,
        .task-resource-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            font-weight: 600;
        }

        .task-resource-chip {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid transparent;
        }

        .task-resource-link {
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            border: 1px solid transparent;
        }

        .task-resource-chip.doc,
        .task-resource-link.doc {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #3730a3;
        }

        .task-resource-chip.file,
        .task-resource-link.file {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .task-resource-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        /* Ensure Modals appear on top */
        .modal-overlay {
            z-index: 2000 !important; /* Force higher z-index */
        }
        /* Modal Overlay for Action Modals */
        .modal-background {
            background: rgba(0,0,0,0.5);
        }

        /* Missing Modal Internal Styles */
        .modal-header-section {
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #E5E7EB;
        }

        .section-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #9CA3AF;
            margin-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-box {
            background: #F9FAFB;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #F3F4F6;
        }
        /* Leader Box Style */
        .leader-box {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--primary-soft-2);
            border: 1px solid var(--primary-soft-4);
            border-radius: 12px;
            margin-bottom: 24px;
        }

        /* Fix modal content width that might have been overridden by task_redesign.css .modal-box */
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90% !important;
            max-width: 900px !important;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
            }
            
             .task-footer i {
                font-size: 10px !important;
             }
        }
    </style>
    <link rel="stylesheet" href="css/tasks-page.css">
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
                        <a href="tasks.php" class="tasks-filter-pill <?= (!isset($_GET['due_date']) && !isset($_GET['status'])) ? 'active' : '' ?>">All</a>
                        <a href="tasks.php?due_date=Due+Today" class="tasks-filter-pill <?= (isset($_GET['due_date']) && $_GET['due_date'] === 'Due Today') ? 'active' : '' ?>">Due Today</a>
                        <a href="tasks.php?due_date=Overdue" class="tasks-filter-pill <?= (isset($_GET['due_date']) && $_GET['due_date'] === 'Overdue') ? 'active' : '' ?>">Overdue</a>
                        <a href="tasks.php?status=Pending" class="tasks-filter-pill <?= (isset($_GET['status']) && $_GET['status'] === 'Pending') ? 'active' : '' ?>">Pending</a>
                        <a href="tasks.php?status=in_progress" class="tasks-filter-pill <?= (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'active' : '' ?>">In Progress</a>
                        <a href="tasks.php?status=Completed" class="tasks-filter-pill <?= (isset($_GET['status']) && $_GET['status'] === 'Completed') ? 'active' : '' ?>">Completed</a>
                    </div>
                </div>
                <div class="tasks-hero-side">
                    <div class="tasks-hero-stats">
                        <div class="tasks-hero-stat">
                            <span>All Tasks</span>
                            <strong><?= (int)$allTaskCount ?></strong>
                            <small>total task<?= $allTaskCount === 1 ? '' : 's' ?> in workspace</small>
                        </div>
                        <div class="tasks-hero-stat">
                            <span>In Progress</span>
                            <strong><?= (int)$allInProgressCount ?></strong>
                            <small>active across all tasks</small>
                        </div>
                        <div class="tasks-hero-stat">
                            <span>Completed</span>
                            <strong><?= (int)$shownCompleted ?></strong>
                            <small>ready for audit/review</small>
                        </div>
                        <div class="tasks-hero-stat">
                            <span>Due Today</span>
                            <strong><?= (int)$shownDueToday ?></strong>
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
                            $taskViewStatus = $taskViewStatusMap[$taskId] ?? [
                                'code' => 'pending',
                                'badge_class' => 'badge-v2 pending',
                                'label' => 'pending',
                                'is_awaiting_review' => false,
                            ];
                            $badgeClass = (string)$taskViewStatus['badge_class'];
                            $statusDisplay = (string)$taskViewStatus['label'];

                            $assignees = get_task_assignees($pdo, $task['id']);
                            $leader = null;
                            $members = [];
                            $googleDocUrl = trim((string)($task['google_doc_url'] ?? ''));
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
                    <div class="task-card" id="task-card-<?=$task['id']?>" onclick="openTaskModal(<?=$task['id']?>)">
                        <div class="task-card-head">
                            <h3 class="task-title"><?= htmlspecialchars($task['title']) ?></h3>
                            <object>
                                <button class="task-delete-btn" onclick="openDeleteModal(event, <?=$task['id']?>, '<?=htmlspecialchars($task['title'], ENT_QUOTES)?>')">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </object>
                        </div>

                        <div class="task-status-row">
                            <span class="<?= $badgeClass ?>"><?= $statusDisplay ?></span>
                        </div>

                        <p class="task-preview-text">
                            <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 100, "...")) ?>
                        </p>

                        <?php if ($googleDocUrl !== '') { ?>
                        <div class="task-resource-row">
                            <a href="<?= htmlspecialchars($googleDocUrl) ?>" target="_blank" rel="noopener noreferrer" class="task-resource-chip doc" onclick="event.stopPropagation();">
                                <i class="fa fa-file-text-o"></i> Working Google Doc
                            </a>
                        </div>
                        <?php } ?>

                        <?php if ($leader) {
                            $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                        ?>
                        <div class="task-leader-box">
                            <img src="<?= $leaderImg ?>" class="task-leader-avatar" alt="Leader">
                            <div>
                                <div class="task-leader-label"><i class="fa fa-crown"></i> Project Leader</div>
                                <div class="task-leader-name"><?= htmlspecialchars($leader['full_name']) ?></div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if (!empty($members)) { ?>
                        <div class="task-members-label">
                            <i class="fa fa-users"></i> Team Members
                        </div>
                        <div class="task-members-row">
                            <div class="task-member-avatars">
                                <?php foreach (array_slice($members, 0, 4) as $m) {
                                    $mImg = !empty($m['profile_image']) ? 'uploads/' . $m['profile_image'] : 'img/user.png';
                                ?>
                                <img src="<?= $mImg ?>" class="task-member-avatar" alt="Member">
                                <?php } ?>
                            </div>
                            <span class="task-member-count"><?= count($members) ?> member<?= count($members)>1?'s':''?></span>
                        </div>
                        <?php } ?>

                        <div class="task-footer">
                            <div class="task-due-meta">
                                <i class="fa fa-calendar-o"></i>
                                <span>Due: <?= empty($task['due_date']) ? 'No Date' : date("M j", strtotime($task['due_date'])) ?></span>
                            </div>
                            <?php if ($task['status'] == 'completed' && isset($task['rating']) && (float)$task['rating'] > 0) { ?>
                            <div class="task-rating-pill"><i class="fa fa-star"></i> <?= number_format((float)$task['rating'], 1) ?>/5</div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php }
                    } else { ?>
                    <div class="tasks-empty-state">
                        <i class="fa fa-folder-open-o"></i>
                        <h3>No tasks found</h3>
                        <p>Try changing filters or create a new task to get started.</p>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </div>
    </div>

    <?php
        $taskLeaderMap = [];
        if (!empty($tasks)) {
            foreach ($tasks as $taskForLeaderMap) {
                $taskLeaderMap[(int)$taskForLeaderMap['id']] = null;
                $taskAssignees = get_task_assignees($pdo, $taskForLeaderMap['id']);
                if ($taskAssignees != 0) {
                    foreach ($taskAssignees as $taskAssignee) {
                        if (($taskAssignee['role'] ?? '') === 'leader') {
                            $taskLeaderMap[(int)$taskForLeaderMap['id']] = [
                                'user_id' => (int)$taskAssignee['user_id'],
                                'full_name' => $taskAssignee['full_name']
                            ];
                            break;
                        }
                    }
                }
            }
        }
    ?>

    <!-- MODALS GENERATED OUTSIDE GRID for Layout Safety -->
    <?php if (!empty($tasks)) { 
        foreach ($tasks as $task) { 
            // Re-calculate necessary variables for Modal
            $taskIdModal = (int)($task['id'] ?? 0);
            $taskViewStatusModal = $taskViewStatusMap[$taskIdModal] ?? [
                'code' => 'pending',
                'badge_class' => 'badge-v2 pending',
                'label' => 'pending',
                'is_awaiting_review' => false,
            ];
            $badgeClass = (string)$taskViewStatusModal['badge_class'];
            $statusDisplay = (string)$taskViewStatusModal['label'];
            $isAwaitingReview = (bool)($taskViewStatusModal['is_awaiting_review'] ?? false);
            $submissionNote = isset($task['submission_note']) ? $task['submission_note'] : null;
            $googleDocUrl = trim((string)($task['google_doc_url'] ?? ''));
            $assignees = get_task_assignees($pdo, $task['id']);
            $leader = null;
            $members = [];
            $leaderFeedbackRows = [];
            if ($assignees != 0) {
                foreach ($assignees as $a) {
                    if ($a['role'] == 'leader') $leader = $a;
                    else $members[] = $a;
                }
            }
            if ($leader) {
                $leaderFeedbackRows = get_leader_feedback_for_task($pdo, $task['id'], $leader['user_id']);
            }
            $subtasks = $taskSubtasksMap[$taskIdModal] ?? [];
            if (!is_array($subtasks)) {
                $subtasks = [];
            }

            // Task-specific rating maps (used in People section below).
            $taskScoreByMember = [];
            if (!empty($subtasks)) {
                foreach ($subtasks as $st) {
                    $memberId = (int)($st['member_id'] ?? 0);
                    $score = isset($st['score']) ? (float)$st['score'] : 0.0;
                    if ($memberId <= 0 || $score <= 0) {
                        continue;
                    }
                    if (!isset($taskScoreByMember[$memberId])) {
                        $taskScoreByMember[$memberId] = ['sum' => 0.0, 'count' => 0];
                    }
                    $taskScoreByMember[$memberId]['sum'] += $score;
                    $taskScoreByMember[$memberId]['count']++;
                }
            }

            $memberFeedbackAvgForLeader = null;
            if (!empty($leaderFeedbackRows)) {
                $feedbackCountTmp = count($leaderFeedbackRows);
                $feedbackSumTmp = 0.0;
                foreach ($leaderFeedbackRows as $fbRowTmp) {
                    $feedbackSumTmp += (float)($fbRowTmp['rating'] ?? 0);
                }
                if ($feedbackCountTmp > 0) {
                    $memberFeedbackAvgForLeader = $feedbackSumTmp / $feedbackCountTmp;
                }
            }

            $leaderTaskAdminRate = null;
            if ($leader && isset($leader['performance_rating']) && (float)$leader['performance_rating'] > 0) {
                $leaderTaskAdminRate = (float)$leader['performance_rating'];
            }

            $leaderTaskCollabRate = null;
            if ($leader) {
                if (function_exists('subtask_blend_leader_admin_member_50_50')) {
                    $leaderTaskCollabRate = subtask_blend_leader_admin_member_50_50($leaderTaskAdminRate, $memberFeedbackAvgForLeader);
                } else if ($leaderTaskAdminRate !== null && $memberFeedbackAvgForLeader !== null) {
                    $leaderTaskCollabRate = ($leaderTaskAdminRate + $memberFeedbackAvgForLeader) / 2;
                } else if ($leaderTaskAdminRate !== null) {
                    $leaderTaskCollabRate = $leaderTaskAdminRate;
                } else if ($memberFeedbackAvgForLeader !== null) {
                    $leaderTaskCollabRate = $memberFeedbackAvgForLeader;
                }
            }

            $leaderTaskRate = $leaderTaskAdminRate;
            if ($leader && $leaderTaskRate === null) {
                $leaderIdTmp = (int)$leader['user_id'];
                if (isset($taskScoreByMember[$leaderIdTmp]) && (int)$taskScoreByMember[$leaderIdTmp]['count'] > 0) {
                    $leaderTaskRate = $taskScoreByMember[$leaderIdTmp]['sum'] / $taskScoreByMember[$leaderIdTmp]['count'];
                } else {
                    $leaderTaskRate = $leaderTaskCollabRate;
                }
            }
    ?>
    <div class="modal-overlay" id="modal-task-<?=$task['id']?>" onclick="if(event.target === this) closeTaskModal(<?=$task['id']?>)">
        <div class="modal-content">
            <button class="close-modal" onclick="closeTaskModal(<?=$task['id']?>)"><i class="fa fa-times"></i></button>

            <!-- Header Section -->
                <div class="modal-header-section">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <h2 style="margin: 0; font-size: 20px; color: #111827;"><?= htmlspecialchars($task['title']) ?></h2>
                </div>
                <span class="<?= $badgeClass ?>" style="font-size: 12px;"><?= $statusDisplay ?></span>
                </div>

            <div style="margin-bottom: 24px;">
                <div class="section-label">Description</div>
                <div style="color: #374151; font-size: 14px; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($task['description'])) ?>
                </div>
            </div>

            <?php if ($googleDocUrl !== '' || !empty($task['template_file'])) { ?>
            <div style="margin-bottom: 24px;">
                <div class="section-label">Task Resources</div>
                <div class="task-resource-list">
                    <?php if ($googleDocUrl !== '') { ?>
                        <a href="<?= htmlspecialchars($googleDocUrl) ?>" target="_blank" rel="noopener noreferrer" class="task-resource-link doc">
                            <i class="fa fa-file-text-o"></i> Open Working Google Doc
                        </a>
                    <?php } ?>
                    <?php if (!empty($task['template_file'])) { ?>
                        <a href="<?= htmlspecialchars($task['template_file']) ?>" target="_blank" rel="noopener noreferrer" class="task-resource-link file">
                            <i class="fa fa-paperclip"></i> Open Attachment
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>

            <div class="info-grid">
                <div class="info-box">
                    <div class="section-label"><i class="fa fa-calendar"></i> Due Date</div>
                    <div style="font-weight: 500; font-size: 14px;"><?= empty($task['due_date']) ? 'No Date' : date("M d, Y", strtotime($task['due_date'])) ?></div>
                </div>
                <div class="info-box">
                    <div class="section-label"><i class="fa fa-clock-o"></i> Created</div>
                    <div style="font-weight: 500; font-size: 14px;"><?= isset($task['created_at']) ? date("M d, Y", strtotime($task['created_at'])) : 'Unknown' ?></div>
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

            <!-- Admin Review Sections -->
            <?php if (!empty($submissionNote)) { ?>
                <div class="admin-review-section">
                        <div class="admin-review-header">
                        <i class="fa fa-paper-plane admin-review-icon"></i>
                        <span class="admin-review-title">Submitted for Admin Review</span>
                    </div>
                    <div class="admin-review-text">
                        <?= htmlspecialchars($submissionNote) ?>
                        <div style="margin-top: 6px; font-size: 12px; color: #60A5FA;">
                            Submitted: <?= isset($task['reviewed_at']) ? date("F j, Y, g:i A", strtotime($task['reviewed_at'])) : 'Recently' ?>
                        </div>
                        <?php if (!empty($task['submission_file'])) { ?>
                            <div style="margin-top: 10px;">
                                <a href="<?= htmlspecialchars($task['submission_file']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; background: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; color: #2563EB; font-weight: 500; font-size: 13px; border: 1px solid #BFDBFE;">
                                    <i class="fa fa-paperclip"></i> View Attached File
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($isAwaitingReview) { ?>
                <div class="awaiting-review-section">
                    <div class="awaiting-review-title">
                        <i class="fa fa-exclamation-circle"></i> Awaiting Admin Review
                    </div>
                    <div class="leader-notes-box">
                        <strong>Leader's Notes:</strong><br>
                        <?= !empty($submissionNote) ? htmlspecialchars($submissionNote) : "No notes provided." ?>
                    </div>
                </div>
            <?php } ?>

            <!-- People -->
            <?php if ($leader) { 
                $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
            ?>
            <div class="section-label">Project Leader</div>
            <div class="leader-box">
                    <img src="<?= $leaderImg ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                    <div style="flex: 1;">
                    <div style="font-size: 10px; font-weight: 700; color: var(--primary-dark); letter-spacing: 0.5px; text-transform: uppercase;">
                        <i class="fa fa-crown" style="margin-right: 4px;"></i> Project Leader
                    </div>
                    <div style="font-weight: 600; color: #1F2937; font-size: 14px; margin-top: 4px; border-bottom: 1px solid var(--primary-soft-4); padding-bottom: 4px; margin-bottom: 4px;">
                        <?= htmlspecialchars($leader['full_name']) ?>
                    </div>
                    <div style="font-size: 11px; color: #6B7280; display: flex; gap: 10px;">
                        <span><i class="fa fa-star" style="color:#F59E0B"></i> <?= $leaderTaskRate !== null ? number_format($leaderTaskRate, 1) : '--' ?>/5</span>
                        <span style="color: var(--primary-dark);"><i class="fa fa-users"></i> Collab: <?= $leaderTaskCollabRate !== null ? number_format($leaderTaskCollabRate, 1) : '--' ?>/5</span>
                    </div>
                    </div>
            </div>
            <?php } ?>
            
            <?php if (!empty($members)) { ?>
                <div class="section-label">Team Members (<?= count($members) ?>)</div>
                <div style="background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 8px; padding: 12px; margin-bottom: 24px;">
                    <?php foreach ($members as $member) { 
                            $memImg = !empty($member['profile_image']) ? 'uploads/' . $member['profile_image'] : 'img/user.png';
                    ?>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; last-child: margin-bottom: 0;">
                            <img src="<?= $memImg ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                            <div>
                            <div style="font-weight: 500; font-size: 13px; color: #1F2937;"><?= htmlspecialchars($member['full_name']) ?></div>
                             <div style="font-size: 11px; color: #6B7280; display: flex; gap: 10px;">
                                <?php
                                    $memberIdTmp = (int)$member['user_id'];
                                    $memberTaskRate = null;
                                    if (isset($member['performance_rating']) && (float)$member['performance_rating'] > 0) {
                                        $memberTaskRate = (float)$member['performance_rating'];
                                    } else if (isset($taskScoreByMember[$memberIdTmp]) && (int)$taskScoreByMember[$memberIdTmp]['count'] > 0) {
                                        $memberTaskRate = $taskScoreByMember[$memberIdTmp]['sum'] / $taskScoreByMember[$memberIdTmp]['count'];
                                    }
                                    $memberTaskCollabRate = null;
                                    if (isset($taskScoreByMember[$memberIdTmp]) && (int)$taskScoreByMember[$memberIdTmp]['count'] > 0) {
                                        $memberTaskCollabRate = $taskScoreByMember[$memberIdTmp]['sum'] / $taskScoreByMember[$memberIdTmp]['count'];
                                    } else {
                                        $memberTaskCollabRate = $memberTaskRate;
                                    }
                                ?>
                                <span><i class="fa fa-star" style="color:#F59E0B"></i> <?= $memberTaskRate !== null ? number_format($memberTaskRate, 1) : '--' ?>/5</span>
                                <span style="color: var(--primary-dark);"><i class="fa fa-users"></i> Collab: <?= $memberTaskCollabRate !== null ? number_format($memberTaskCollabRate, 1) : '--' ?>/5</span>
                            </div>
                            </div>
                    </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($leader) { ?>
                <div class="section-label">Member Feedback for Leader</div>
                <div style="background: #EFF6FF; border: 1px solid #DBEAFE; border-radius: 10px; padding: 12px; margin-bottom: 24px;">
                    <?php if (!empty($leaderFeedbackRows)) { ?>
                        <?php
                            $feedbackCount = count($leaderFeedbackRows);
                            $feedbackSum = 0;
                            foreach ($leaderFeedbackRows as $fbRow) {
                                $feedbackSum += (int)$fbRow['rating'];
                            }
                            $feedbackRawAvg = $feedbackCount > 0 ? ($feedbackSum / $feedbackCount) : null;
                            $feedbackAvg = $feedbackRawAvg !== null ? number_format($feedbackRawAvg, 1) : "0.0";
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #BFDBFE;">
                            <div style="font-size: 12px; font-weight: 600; color: #1E40AF;">
                                <?= $feedbackCount ?> member rating<?= $feedbackCount > 1 ? 's' : '' ?>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #1D4ED8;">
                                <i class="fa fa-star" style="color:#F59E0B;"></i> <?= $feedbackAvg ?>/5
                            </div>
                        </div>
                        <div style="display: grid; gap: 10px;">
                            <?php foreach ($leaderFeedbackRows as $fb) { 
                                $memberImg = !empty($fb['member_profile_image']) ? 'uploads/' . $fb['member_profile_image'] : 'img/user.png';
                                $displayWhen = !empty($fb['updated_at']) ? $fb['updated_at'] : $fb['created_at'];
                            ?>
                                <div style="background: white; border: 1px solid #DBEAFE; border-radius: 8px; padding: 10px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="<?= $memberImg ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                            <div>
                                                <div style="font-size: 13px; font-weight: 600; color: #1F2937;"><?= htmlspecialchars($fb['member_name']) ?></div>
                                                <div style="font-size: 11px; color: #6B7280;">
                                                    <?= !empty($displayWhen) ? date("M j, Y g:i A", strtotime($displayWhen)) : 'No date' ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="font-size: 12px; color: #F59E0B; font-weight: 700; white-space: nowrap;">
                                            <?php for ($i = 1; $i <= 5; $i++) { echo ($i <= (int)$fb['rating']) ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star-o"></i>'; } ?>
                                            <span style="color:#374151; margin-left: 4px;"><?= (int)$fb['rating'] ?>/5</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($fb['comment'])) { ?>
                                        <div style="margin-top: 8px; font-size: 13px; color: #374151; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 6px; padding: 8px;">
                                            <?= nl2br(htmlspecialchars($fb['comment'])) ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div style="font-size: 13px; color: #64748B;">
                            No member feedback submitted for this leader yet.
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <!-- Subtasks Accordion -->
            <div class="subtasks-section">
                    <div class="subtasks-header" onclick="$('#subtaskList-<?=$task['id']?>').slideToggle();" style="cursor: pointer;">
                    <button type="button" class="btn-v2 btn-white" style="width: 100%; justify-content: space-between;">
                        <span><i class="fa fa-chevron-down"></i> View Subtasks (<?= !empty($subtasks) ? count($subtasks) : 0 ?>)</span>
                    </button>
                </div>
                <div id="subtaskList-<?=$task['id']?>" style="display: none; margin-top: 12px;">
                        <?php if (!empty($subtasks)) { foreach($subtasks as $sub) { 
                            $sClass = "pending";
                            if($sub['status']=='completed') $sClass="completed";
                            if($sub['status']=='in_progress') $sClass="in_progress";
                            if($sub['status']=='submitted') $sClass="submitted";
                            if($sub['status']=='revise') $sClass="revision_needed";
                            if($sub['status']=='rejected') $sClass="rejected";
                    ?>
                    <div class="subtask-card" style="padding: 12px; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-weight: 600; font-size: 13px; color: #1F2937;"><?= htmlspecialchars($sub['description']) ?></span>
                            <span class="badge-v2 <?=$sClass?>" style="font-size: 10px;"><?= str_replace('_',' ', $sub['status']) ?></span>
                        </div>
                        <div style="font-size: 12px; color: #6B7280; display: flex; justify-content: space-between;">
                            <span>Assigned: <?= htmlspecialchars($sub['member_name']) ?></span>
                            <?php if($sub['score']) { echo "<span style='color:#F59E0B'><i class='fa fa-star'></i> ".$sub['score']."/5</span>"; } ?>
                        </div>
                        <?php if(!empty($sub['submission_file'])) { ?>
                            <div style="font-size: 12px; margin-top: 5px;">
                                <a href="<?=$sub['submission_file']?>" target="_blank" style="color: var(--primary);">View File</a>
                            </div>
                        <?php } ?>
                    </div>
                    <?php }} else { echo "<div style='color: #9CA3AF; font-size: 13px; padding: 10px;'>No subtasks.</div>"; } ?>
                </div>
            </div>

            <!-- Admin Actions -->
            <?php if ($isAwaitingReview || ($task['status'] == 'completed')) { ?>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #E5E7EB; padding-top: 20px;">
                        <button class="btn-v2 btn-yellow" onclick="openRevisionDialog(<?=$task['id']?>, `<?=htmlspecialchars($task['title'])?>`, <?=count($subtasks)?>)">
                        <i class="fa fa-refresh"></i> Request Revision
                    </button>
                    <button class="btn-v2 btn-green" onclick="openAcceptDialog(<?=$task['id']?>, `<?=htmlspecialchars($task['title'])?>`, <?=count($subtasks)?>)">
                        <i class="fa fa-check"></i> Accept & Rate
                    </button>
                </div>
            <?php } ?>

        </div>
    </div>
    <?php } } ?>

    <!-- SHARED ACTION MODALS -->
    
    <!-- Accept & Rate Modal -->
    <div id="acceptModal" class="modal-overlay" style="display: none; z-index: 2200 !important;">
        <div class="modal-box">
            <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Accept & Rate Task</h3>
            
            <div style="background: #F3F4F6; padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 14px; font-weight: 500;">
                <span id="acceptTaskTitle">Task Title</span>
                <div style="font-size: 12px; color: #6B7280; font-weight: 400; margin-top: 4px;">
                     <span id="acceptSubtaskCount">0</span> completed subtasks
                </div>
            </div>

            <form action="app/admin-review-task.php" method="POST">
                <?= csrf_field('admin_review_task_form') ?>
                <input type="hidden" name="task_id" id="acceptTaskId">
                <input type="hidden" name="action" value="accept">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Task Rating</label>
                    <div class="rating-input task-rating-input" id="taskRatingStars">
                        <i class="fa fa-star" data-value="1"></i>
                        <i class="fa fa-star" data-value="2"></i>
                        <i class="fa fa-star" data-value="3"></i>
                        <i class="fa fa-star" data-value="4"></i>
                        <i class="fa fa-star" data-value="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" value="0">
                </div>

                <div style="margin-bottom: 15px;" id="leaderRatingBlock">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">
                        Leader Rating: <span id="acceptLeaderName" style="font-weight: 600;"></span>
                    </label>
                    <div class="rating-input leader-rating-input" id="leaderRatingStars">
                        <i class="fa fa-star" data-value="1"></i>
                        <i class="fa fa-star" data-value="2"></i>
                        <i class="fa fa-star" data-value="3"></i>
                        <i class="fa fa-star" data-value="4"></i>
                        <i class="fa fa-star" data-value="5"></i>
                    </div>
                    <input type="hidden" name="leader_rating" id="leaderRatingValue" value="0">
                    <div style="font-size: 11px; color: #6B7280; margin-top: 5px;">
                        This rates the leader's collaboration and responsibility.
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Feedback (Optional)</label>
                    <textarea name="feedback" class="form-input-v2" rows="3" placeholder="Add your feedback about the completed task..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-v2 btn-white" onclick="closeActionModal('acceptModal')">Cancel</button>
                    <button type="submit" class="btn-v2 btn-green">Accept & Rate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Revision Modal -->
    <div id="revisionModal" class="modal-overlay" style="display: none; z-index: 2200 !important;">
         <div class="modal-box">
            <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Request Revision</h3>
            
            <div style="background: #F3F4F6; padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 14px; font-weight: 500;">
                <span id="reviseTaskTitle">Task Title</span>
                <div style="font-size: 12px; color: #6B7280; font-weight: 400; margin-top: 4px;">
                     <span id="reviseSubtaskCount">0</span> completed subtasks
                </div>
            </div>

            <form action="app/admin-review-task.php" method="POST">
                <?= csrf_field('admin_review_task_form') ?>
                <input type="hidden" name="task_id" id="reviseTaskId">
                <input type="hidden" name="action" value="revise">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Revision Notes</label>
                    <textarea name="feedback" class="form-input-v2" rows="3" placeholder="Explain what needs to be revised..." required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-v2 btn-white" onclick="closeActionModal('revisionModal')">Cancel</button>
                    <button type="submit" class="btn-v2 btn-yellow">Request Revision</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Task Modal -->
    <div id="deleteTaskModal" class="modal-overlay" style="display: none; z-index: 2300 !important;">
        <div class="modal-box">
            <div style="text-align: center;">
                <i class="fa fa-exclamation-triangle" style="font-size: 48px; color: #EF4444; margin-bottom: 15px;"></i>
                <h3 style="margin: 0; font-size: 20px; color: #111827;">Delete Task?</h3>
                <p style="color: #6B7280; font-size: 14px; margin: 10px 0 20px;">
                    Are you sure you want to delete <span id="deleteTaskTitle" style="font-weight: 600; color: #111827;"></span>? 
                    <br>This action cannot be undone.
                </p>
                <form action="app/delete-task.php" method="POST">
                    <?= csrf_field('delete_task_form') ?>
                    <input type="hidden" name="id" id="deleteTaskId">
                    <div style="display: flex; justify-content: center; gap: 10px;">
                        <button type="button" class="btn-v2 btn-white" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" class="btn-v2 btn-red" style="background: #EF4444;">Delete Task</button>
                    </div>
                </form>
            </div>
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

    <?php include "inc/pages/tasks_scripts.php"; ?>
</body>
</html>
<?php 
} else {
    header("Location: login.php?error=First login");
    exit();
}
?>


