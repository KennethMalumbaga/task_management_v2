<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

    require_once "inc/performance.php";
    performance_monitor_request('dashboard.index');

    include "DB_connection.php";
    include "app/model/Task.php";
    include "app/model/user.php";
    include "app/model/Subtask.php";
    include "app/model/Group.php";
    include "app/model/Bulletin.php";
    require_once "inc/csrf.php";
    require_once "inc/tenant.php";
    require_once "inc/device.php";
    require_once "inc/workspace_screenshot_interval.php";

    // --- DATA FETCHING FOR DASHBOARD ---
    
    // 1. Stats and Counts
    $top_users = [];
    $top_groups = [];
    if ($_SESSION['role'] == "admin") {
        $num_task = count_tasks($pdo);
        $completed = count_completed_tasks($pdo);
        $num_users = count_users($pdo); // Employees
    } else {
        $num_task = count_my_tasks($pdo, $_SESSION['id']);
        $completed = count_my_completed_tasks($pdo, $_SESSION['id']);
        $num_users = count_users($pdo); // Show total team members
        if ((int)$num_task > 0) {
            $stats = get_user_rating_stats($pdo, $_SESSION['id']);
            $avg_rating = $stats['avg'];
            $collab_stats = get_collaborative_scores_by_user($pdo, $_SESSION['id']);
            $collaborative_rate = $collab_stats['avg'];
        } else {
            $stats = ['count' => 0, 'avg' => '0.0'];
            $avg_rating = '0.0';
            $collab_stats = ['count' => 0, 'avg' => '0.0', 'projects' => []];
            $collaborative_rate = '0.0';
        }
    }

    // 2. Recent Tasks (admin shows more for scrollable lists)
    $recentTaskLimit = ($_SESSION['role'] === 'admin') ? 8 : 3;
    if ($_SESSION['role'] == "admin") {
         $sql_recent = "SELECT * FROM tasks WHERE 1=1";
         $scope = tenant_get_scope($pdo, 'tasks');
         $sql_recent .= $scope['sql'] . " ORDER BY id DESC LIMIT " . (int)$recentTaskLimit;
         $stmt_recent = $pdo->prepare($sql_recent);
         $stmt_recent->execute($scope['params']);
         $recent_tasks = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    } else {
         $user_id = $_SESSION['id'];
         $sql_recent = "SELECT DISTINCT t.* FROM tasks t
                        JOIN task_assignees ta ON t.id = ta.task_id
                        WHERE ta.user_id=?";
         $params_recent = [$user_id];
         $scope = tenant_get_scope($pdo, 'tasks', 't');
         $sql_recent .= $scope['sql'] . "
                        ORDER BY t.id DESC LIMIT " . (int)$recentTaskLimit;
         $params_recent = array_merge($params_recent, $scope['params']);
         $stmt_recent = $pdo->prepare($sql_recent);
         $stmt_recent->execute($params_recent);
         $recent_tasks = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    }

    $recentTaskIds = array_values(array_filter(array_map(function ($task) {
        return isset($task['id']) ? (int)$task['id'] : 0;
    }, $recent_tasks ?? [])));
    $recentTaskAssigneesMap = function_exists('get_task_assignees_map')
        ? get_task_assignees_map($pdo, $recentTaskIds)
        : [];
    $recentTaskStartedSubtaskMap = function_exists('get_task_started_subtask_map')
        ? get_task_started_subtask_map($pdo, $recentTaskIds)
        : [];

    $attendanceAjaxCsrfToken = csrf_token('attendance_ajax_actions');
    $bulletinPostCsrfToken = csrf_token('bulletin_post_action');
    $bulletinDeleteCsrfToken = csrf_token('bulletin_delete_action');
    $active_users = [];
    $workspaceCaptureInterval = workspace_screenshot_interval_fetch_minutes($pdo, tenant_get_current_org_id());
    $isMobileClockInDevice = taskflow_is_mobile_device();

    // Fetch leaderboard widgets last so a single failing aggregate query
    // does not prevent the rest of the dashboard from rendering.
    if ((int)$num_task > 0) {
        try {
            $top_users = get_top_rated_users($pdo, 5);
        } catch (Throwable $e) {
            error_log('Dashboard top users fetch failed: ' . $e->getMessage());
            $top_users = [];
        }

        try {
            $top_groups = get_top_rated_groups($pdo, 5);
        } catch (Throwable $e) {
            error_log('Dashboard top groups fetch failed: ' . $e->getMessage());
            $top_groups = [];
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>TaskFlow Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/task_redesign.css">
    <style>
        .admin-leaderboard-compact {
            padding: 16px 18px;
            max-height: 290px;
            overflow: hidden;
        }
        .leaderboard-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            height: 100%;
        }
        .leaderboard-pane {
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .leaderboard-pane .leaderboard-header {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #F3F4F6;
        }
        .leaderboard-pane .leaderboard-list {
            gap: 6px;
            overflow-y: auto;
            max-height: 220px;
            padding-right: 4px;
        }
        .leaderboard-pane .leaderboard-item {
            padding: 6px 8px;
            border-radius: 8px;
        }
        .leaderboard-pane .leaderboard-name {
            font-size: 12px;
        }
        .leaderboard-pane .leaderboard-meta {
            font-size: 10px;
        }
        .leaderboard-pane .leaderboard-rating {
            font-size: 13px;
        }
        .leaderboard-pane .leaderboard-avatar {
            width: 28px;
            height: 28px;
        }
        .leaderboard-pane .rank-badge {
            width: 22px;
            height: 22px;
            font-size: 10px;
        }
        .welcome-role-badge {
            display: inline-block;
            background: var(--primary-soft-3);
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 999px;
            margin-left: 6px;
        }
        .overview-divider {
            margin: 14px 0;
            border: none;
            border-top: 1px solid #E5E7EB;
        }
        .employee-overview-card {
            padding: 24px 28px;
        }
        .employee-attendance-box {
            margin-top: 10px;
            background: #E9EEFA;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #D8E2FF;
        }
        .employee-attendance-note {
            margin-top: 6px;
            font-size: 11px;
            color: #6B7280;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .employee-right-panels {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 12px;
            min-height: 100%;
        }
        .employee-leaderboard-card {
            padding: 16px;
            max-height: 460px;
            overflow: hidden;
        }
        .employee-leaderboard-card .leaderboard-list {
            max-height: 360px;
            overflow-y: auto;
        }
        .employee-leaderboard-card.groups .leaderboard-list {
            max-height: 360px;
            overflow-y: visible;
        }
        .employee-leaderboard-card .leaderboard-header {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--primary-soft-3);
            padding-bottom: 8px;
        }
        .employee-leaderboard-card .leaderboard-item {
            background: #F3F4F6;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }
        .employee-leaderboard-card .leaderboard-rating {
            min-width: 42px;
            justify-content: flex-end;
        }
        .employee-leaderboard-card .rank-badge {
            width: 26px;
            height: 26px;
            font-size: 12px;
        }
        .employee-leaderboard-card .leaderboard-name {
            font-size: 14px;
            font-weight: 700;
        }
        .employee-leaderboard-card .leaderboard-meta {
            font-size: 12px;
            color: #6B7280;
        }
        .employee-leaderboard-card .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 3px;
        }
        .employee-time-title {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            line-height: 1.1;
        }

        /* Mobile Dashboard Optimizations */
        @media (max-width: 768px) {
            .admin-leaderboard-compact {
                max-height: none;
            }
            .employee-right-panels {
                grid-template-columns: 1fr;
            }
            .employee-leaderboard-card {
                max-height: none;
            }
            .employee-leaderboard-card .leaderboard-list {
                max-height: 180px;
            }
            .leaderboard-split {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .leaderboard-pane .leaderboard-list {
                max-height: 150px;
            }
            .tasks-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }
            .dashboard-recent-board .tasks-grid .task-card:nth-child(n+3) {
                display: none !important;
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
            
            .badge-v2 {
                font-size: 9px !important;
                padding: 2px 6px !important;
            }
            
            .preview-content div[style*="font-size: 14px"] {
                font-size: 11px !important;
                margin-bottom: 10px !important;
                line-height: 1.3 !important;
                height: 2.6em; 
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

            /* Team Members Section */
            .preview-content div[style*="display: flex; align-items: center; gap: 8px;"] {
                gap: 4px !important;
            }
            
            .preview-content div[style*="color: #059669; font-size: 12px;"] {
                font-size: 10px !important;
            }
            
            .preview-content div[style*="font-size: 12px; font-weight: 600; color: #059669;"] {
                font-size: 10px !important;
            }

            .preview-content img[style*="width: 32px; height: 32px;"] {
                width: 24px !important;
                height: 24px !important;
            }
            
            .task-footer {
                margin-top: 10px !important;
                padding-top: 10px !important;
                font-size: 10px !important;
            }

            /* Stats Optimization - One Row */
            .dash-stats-grid {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin-top: 20px !important;
            }

            .stat-card {
                padding: 10px 4px !important;
                flex-direction: column !important;
                text-align: center !important;
                justify-content: center !important;
                height: auto !important;
                min-height: 80px !important;
            }

            .stat-card .stat-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 14px !important;
                margin: 0 auto 6px !important;
                order: -1 !important; /* Move icon to top */
            }

            .stat-info h4 {
                font-size: 8px !important;
                margin-bottom: 2px !important;
                white-space: nowrap !important;
            }

            .stat-info span {
                font-size: 16px !important;
            }

            /* Minimize Create Task Button */
            .tasks-section-header {
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
            }

            .btn-create-task {
                width: auto !important;
                padding: 6px 12px !important;
                font-size: 11px !important;
                display: inline-flex !important;
                margin-top: 0 !important;
            }
            .btn-create-task i {
                font-size: 10px !important;
            }
        }
    </style>
    <link rel="stylesheet" href="css/dashboard-page.css">
</head>
<body class="dashboard-page <?= ($_SESSION['role'] === 'admin') ? 'role-admin' : 'role-employee' ?>">
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        <div class="dashboard-shell">
        
        <!-- Top Section: Dashboard Panels -->
        <?php if ($_SESSION['role'] == 'admin') { ?>
            <div class="admin-dashboard">
                <div class="admin-top-row">
                    <div class="dash-card admin-card admin-bulletin-card">
                        <div class="bulletin-head">
                            <div class="bulletin-head-left">
                                <div class="bulletin-icon-box"><i class="fa fa-thumb-tack"></i></div>
                                <span class="bulletin-title">Bulletin Board</span>
                            </div>
                            <button type="button" class="btn-post" onclick="openBulletinPostModal()">
                                <i class="fa fa-plus"></i> Post
                            </button>
                        </div>
                        <div class="bulletin-list" id="bulletinList"></div>
                    </div>

                    <div class="admin-top-right">
                        <div class="dash-card admin-card admin-stats-card">
                            <div class="admin-stats-grid">
                                <div class="admin-stat-item">
                                    <div class="admin-stat-icon is-purple"><i class="fa fa-check"></i></div>
                                    <div>
                                        <div class="admin-stat-value"><?= $num_task ?></div>
                                        <div class="admin-stat-label">Total Tasks</div>
                                    </div>
                                </div>
                                <div class="admin-stat-item">
                                    <div class="admin-stat-icon is-green"><i class="fa fa-bullseye"></i></div>
                                    <div>
                                        <div class="admin-stat-value"><?= $completed ?></div>
                                        <div class="admin-stat-label">Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dash-card admin-card admin-leaderboard-card">
                            <div class="admin-tab-bar">
                                <button type="button" class="admin-tab-btn active" id="adminTabEmployees" onclick="switchAdminLeaderboardTab('employees')">Top Employees</button>
                                <button type="button" class="admin-tab-btn" id="adminTabGroups" onclick="switchAdminLeaderboardTab('groups')">Top Groups</button>
                            </div>

                            <div class="admin-tab-panel" id="adminPanelEmployees">
                                <?php if (!empty($top_users)) { ?>
                                    <div class="leaderboard-list">
                                        <?php foreach (array_slice($top_users, 0, 6) as $idx => $u) {
                                            $rankColor = $idx === 0 ? '#F59E0B' : ($idx === 1 ? '#9CA3AF' : ($idx === 2 ? '#CD7C2F' : 'var(--primary-strong)'));
                                            $avatar = !empty($u['profile_image']) ? 'uploads/' . $u['profile_image'] : 'img/user.png';
                                            $ratedCount = (int)$u['rated_task_count'];
                                        ?>
                                        <div class="leaderboard-item">
                                            <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                            <img src="<?= $avatar ?>" class="leaderboard-avatar" alt="User">
                                            <div class="leaderboard-info">
                                                <div class="leaderboard-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                                <div class="leaderboard-meta">
                                                    <?= $ratedCount ?> rated task<?= ($ratedCount !== 1 ? 's' : '') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div class="leaderboard-empty">
                                        <i class="fa fa-info-circle"></i> No employee ratings yet.
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="admin-tab-panel" id="adminPanelGroups" style="display:none;">
                                <?php if (!empty($top_groups)) { ?>
                                    <div class="leaderboard-list">
                                        <?php foreach (array_slice($top_groups, 0, 6) as $idx => $g) {
                                            $rankColor = $idx === 0 ? '#F59E0B' : ($idx === 1 ? '#9CA3AF' : ($idx === 2 ? '#CD7C2F' : 'var(--primary-strong)'));
                                            $memberCount = (int)$g['member_count'];
                                            $ratedTaskCount = (int)$g['rated_task_count'];
                                        ?>
                                        <div class="leaderboard-item">
                                            <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                            <div class="leaderboard-info">
                                                <div class="leaderboard-name"><?= htmlspecialchars($g['group_name']) ?></div>
                                                <div class="leaderboard-meta">
                                                    <?= $memberCount ?> member<?= ($memberCount !== 1 ? 's' : '') ?>
                                                    &bull;
                                                    <?= $ratedTaskCount ?> rated task<?= ($ratedTaskCount !== 1 ? 's' : '') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div class="leaderboard-empty">
                                        <i class="fa fa-info-circle"></i> No group ratings yet.
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-bottom-row">
                    <div class="dash-card admin-card admin-recent-card">
                        <div class="admin-card-head">
                            <div>
                                <h3>Recent Tasks</h3>
                                <span class="admin-card-subtitle"><?= count($recent_tasks) ?> total tasks</span>
                            </div>
                            <a href="create_task.php" class="admin-card-action">
                                <i class="fa fa-plus"></i> Create Task
                            </a>
                        </div>

                        <div class="admin-tasks-list">
                            <?php if (!empty($recent_tasks) && count($recent_tasks) > 0) { 
                                $taskIconStyles = [
                                    'linear-gradient(135deg,var(--primary-strong),var(--primary-muted))',
                                    'linear-gradient(135deg,#10b981,#34d399)',
                                    'linear-gradient(135deg,#f59e0b,#fbbf24)',
                                    'linear-gradient(135deg,#ec4899,#f472b6)',
                                    'linear-gradient(135deg,#0ea5e9,#38bdf8)'
                                ];
                                foreach($recent_tasks as $idx => $task) { 
                                    $statusClass = "pending";
                                    $statusText = "pending";
                                    $taskStatusRaw = strtolower(trim((string)($task['status'] ?? 'pending')));
                                    $taskRating = isset($task['rating']) ? (float)$task['rating'] : 0.0;

                                    $taskId = (int)($task['id'] ?? 0);
                                    $hasStartedSubtask = !empty($recentTaskStartedSubtaskMap[$taskId]);

                                    if ($taskStatusRaw === 'completed' && $taskRating <= 0) {
                                        $statusClass = "submitted";
                                        $statusText = "submitted";
                                    } elseif ($taskStatusRaw === 'completed') {
                                        $statusClass = "completed";
                                        $statusText = "completed";
                                    } elseif ($hasStartedSubtask || $taskStatusRaw === 'in_progress') {
                                        $statusClass = "in_progress";
                                        $statusText = "in progress";
                                    }

                                    $redirectUrl = "tasks.php?open_task=" . $task['id'];

                                    $assignees = $recentTaskAssigneesMap[$taskId] ?? [];
                                    $leader = null;
                                    if (!empty($assignees)) {
                                        foreach ($assignees as $a) {
                                            if ($a['role'] == 'leader') { $leader = $a; break; }
                                        }
                                    }

                                    $leaderName = $leader ? trim((string)$leader['full_name']) : '';
                                    $leaderInitials = '';
                                    if ($leaderName !== '') {
                                        $parts = preg_split('/\s+/', $leaderName);
                                        foreach ($parts as $part) {
                                            if ($part === '') continue;
                                            $leaderInitials .= mb_strtoupper(mb_substr($part, 0, 1));
                                            if (mb_strlen($leaderInitials) >= 2) break;
                                        }
                                    }
                                    if ($leaderInitials === '') {
                                        $leaderInitials = 'TL';
                                    }

                                    $iconStyle = $taskIconStyles[$idx % count($taskIconStyles)];
                                    $iconMap = [
                                        'completed' => 'fa-check',
                                        'in_progress' => 'fa-fire',
                                        'submitted' => 'fa-paper-plane',
                                        'pending' => 'fa-clock-o'
                                    ];
                                    $iconName = $iconMap[$statusClass] ?? 'fa-tasks';
                            ?>
                            <div class="admin-task-item" onclick="navigateWithClockInGuard('<?= $redirectUrl ?>')">
                                <div class="admin-task-icon" style="background: <?= $iconStyle ?>;">
                                    <i class="fa <?= $iconName ?>"></i>
                                </div>
                                <div class="admin-task-info">
                                    <div class="admin-task-name"><?= htmlspecialchars($task['title']) ?></div>
                                    <div class="admin-task-meta">
                                        <span class="admin-task-label">LEADER</span>
                                        <?php if ($leader) { 
                                            $leaderAvatar = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : '';
                                        ?>
                                            <?php if ($leaderAvatar) { ?>
                                                <img src="<?= $leaderAvatar ?>" class="admin-task-leader-avatar" alt="Leader">
                                            <?php } else { ?>
                                                <span class="admin-task-leader-avatar is-initials"><?= htmlspecialchars($leaderInitials) ?></span>
                                            <?php } ?>
                                            <span><?= htmlspecialchars($leaderName) ?></span>
                                        <?php } else { ?>
                                            <span class="admin-task-unassigned">Unassigned</span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <span class="admin-task-status status-<?= $statusClass ?>"><?= $statusText ?></span>
                            </div>
                            <?php } } else { ?>
                                <div class="admin-empty-state">
                                    <i class="fa fa-folder-open-o"></i>
                                    <span>No recent tasks yet.</span>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="admin-card-footer">
                            <a href="tasks.php" class="admin-view-all-link">
                                View All Tasks <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="dash-card admin-card admin-active-card">
                        <div class="admin-card-head">
                            <h3><span class="admin-active-dot"></span> Active Users</h3>
                            <span class="admin-active-count">Loading</span>
                        </div>

                        <div class="admin-active-list">
                            <?php if (!empty($active_users)) { 
                                foreach ($active_users as $idx => $u) {
                                    $userName = trim((string)($u['full_name'] ?? ''));
                                    $avatarPath = user_profile_image_url($u['profile_image'] ?? '');
                                    $timeInRaw = trim((string)($u['time_in'] ?? ''));
                                    $timeInLabel = $timeInRaw !== '' ? date('h:i A', strtotime($timeInRaw)) : '--:--';
                                    $isPaused = !empty($u['is_paused']);
                                    $pauseReason = trim((string)($u['pause_reason'] ?? ''));
                                    $pauseLabel = $pauseReason !== '' ? $pauseReason : 'Paused';
                                    $initials = user_display_initials($userName);
                            ?>
                            <div class="admin-user-row<?= $isPaused ? ' is-paused' : '' ?>" data-user-id="<?= (int)$u['user_id'] ?>" data-user-name="<?= htmlspecialchars($userName) ?>">
                                <div class="admin-user-rank"><?= $idx + 1 ?></div>
                                <div class="admin-user-avatar">
                                    <?php if ($avatarPath) { ?>
                                        <img src="<?= $avatarPath ?>" alt="User">
                                    <?php } else { ?>
                                        <span class="admin-user-avatar-initials"><?= htmlspecialchars($initials) ?></span>
                                    <?php } ?>
                                    <span class="admin-user-online<?= $isPaused ? ' is-paused' : '' ?>"></span>
                                </div>
                                <div class="admin-user-info">
                                    <div class="admin-user-name"><?= htmlspecialchars($userName) ?></div>
                                    <div class="admin-user-meta">Clocked in at <?= htmlspecialchars($timeInLabel) ?></div>
                                </div>
                                <div class="admin-user-actions">
                                    <?php if ($isPaused) { ?>
                                        <div class="admin-user-note is-paused" title="<?= htmlspecialchars($pauseLabel) ?>">
                                            <i class="fa fa-pause"></i>
                                            <span><?= htmlspecialchars($pauseLabel) ?></span>
                                        </div>
                                    <?php } ?>
                                    <div class="admin-user-action-buttons">
                                        <button type="button" class="admin-btn admin-btn-clockout admin-clockout-btn" data-user-id="<?= (int)$u['user_id'] ?>" data-user-name="<?= htmlspecialchars($userName) ?>">
                                            <i class="fa fa-sign-out"></i> Clock Out
                                        </button>
                                        <a class="admin-btn admin-btn-capture" href="screenshots.php?open_user_id=<?= (int)$u['user_id'] ?>&user_id=<?= (int)$u['user_id'] ?>">
                                            View Captures <i class="fa fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php } } else { ?>
                                <div class="admin-empty-state">
                                    <i class="fa fa-spinner fa-spin"></i>
                                    <span>Loading active users...</span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($_SESSION['role'] != 'admin') { ?>
        <div class="dash-top-grid">
            <?php if ($_SESSION['role'] == 'admin') { ?>
            <div class="dash-card admin-leaderboard-compact">
                <div class="admin-leaderboard-tabs">
                    <div class="admin-tab-bar">
                        <button type="button" class="admin-tab-btn active" id="adminTabEmployees" onclick="switchAdminLeaderboardTab('employees')">Top Employees</button>
                        <button type="button" class="admin-tab-btn" id="adminTabGroups" onclick="switchAdminLeaderboardTab('groups')">Top Groups</button>
                    </div>

                    <div class="admin-tab-panel" id="adminPanelEmployees">
                        <?php if (!empty($top_users)) { ?>
                            <div class="leaderboard-list">
                                <?php foreach (array_slice($top_users, 0, 6) as $idx => $u) {
                                    $rankColor = $idx === 0 ? 'var(--primary)' : ($idx === 1 ? 'var(--primary-dark)' : 'var(--primary-strong)');
                                    $avatar = !empty($u['profile_image']) ? 'uploads/' . $u['profile_image'] : 'img/user.png';
                                ?>
                                <div class="leaderboard-item">
                                    <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                    <img src="<?= $avatar ?>" class="leaderboard-avatar" alt="User">
                                    <div class="leaderboard-info">
                                        <div class="leaderboard-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <div class="leaderboard-meta">
                                            <?= (int)$u['rated_task_count'] ?> task rate<?= ((int)$u['rated_task_count'] !== 1 ? 's' : '') ?>
                                            &bull;
                                            <?= (int)$u['collab_score_count'] ?> collaborative rate<?= ((int)$u['collab_score_count'] !== 1 ? 's' : '') ?>
                                        </div>
                                    </div>
                                    <div class="leaderboard-rating">
                                        <i class="fa fa-star" style="color:#F59E0B;"></i> <?= htmlspecialchars($u['avg_rating']) ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="leaderboard-empty">
                                <i class="fa fa-info-circle"></i> No employee ratings yet.
                            </div>
                        <?php } ?>
                    </div>

                    <div class="admin-tab-panel" id="adminPanelGroups" style="display:none;">
                        <?php if (!empty($top_groups)) { ?>
                            <div class="leaderboard-list">
                                <?php foreach (array_slice($top_groups, 0, 6) as $idx => $g) {
                                    $rankColor = $idx === 0 ? 'var(--primary)' : ($idx === 1 ? 'var(--primary-dark)' : 'var(--primary-strong)');
                                ?>
                                <div class="leaderboard-item">
                                    <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                    <div class="leaderboard-info">
                                        <div class="leaderboard-name"><?= htmlspecialchars($g['group_name']) ?></div>
                                        <div class="leaderboard-meta">
                                            <?= (int)$g['member_count'] ?> member<?= ((int)$g['member_count'] !== 1 ? 's' : '') ?>
                                            &bull;
                                            <?= (int)$g['rated_task_count'] ?> rated task<?= ((int)$g['rated_task_count'] !== 1 ? 's' : '') ?>
                                        </div>
                                    </div>
                                    <div class="leaderboard-rating">
                                        <i class="fa fa-star" style="color:#F59E0B;"></i> <?= htmlspecialchars($g['avg_rating']) ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="leaderboard-empty">
                                <i class="fa fa-info-circle"></i> No group ratings yet.
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="dash-card bulletin-card">
                <div class="bulletin-head">
                    <div class="bulletin-head-left">
                        <div class="bulletin-icon-box"><i class="fa fa-thumb-tack"></i></div>
                        <span class="bulletin-title">Bulletin Board</span>
                    </div>
                    <button type="button" class="btn-post" onclick="openBulletinPostModal()">
                        <i class="fa fa-plus"></i> Post
                    </button>
                </div>
                <div class="bulletin-list" id="bulletinList"></div>
            </div>
            <?php } else { ?>
            <?php $attStats = get_todays_attendance_stats($pdo, $_SESSION['id']); ?>
            <div class="employee-left-stack">
                <div class="dash-card employee-time-tracker-card is-idle" id="employeeTimeTrackerCard">
                    <div class="ctt-shell">
                        <div class="ctt-header">
                            <div class="ctt-title">
                                <span class="ctt-status-indicator" aria-hidden="true">
                                    <span class="ctt-status-dot"></span>
                                </span>
                                <span class="ctt-title-text">Time Tracker</span>
                            </div>
                            <span class="ctt-camera">
                                <span class="ctt-camera-dot" aria-hidden="true"></span>
                                <span id="captureStatusLabel">Screen captures on</span>
                            </span>
                        </div>

                        <div class="ctt-row">
                            <div class="ctt-stats">
                                <div class="ctt-stat ctt-stat-today">
                                    <div class="ctt-label">Today</div>
                                    <div class="ctt-value" id="statDurationToday"><?= htmlspecialchars((string)$attStats['daily_duration']) ?></div>
                                </div>
                                <div class="ctt-stat ctt-stat-alltime">
                                    <div class="ctt-label">All Time</div>
                                    <div class="ctt-value" id="statDurationAllTime"><?= htmlspecialchars((string)$attStats['overall_duration']) ?></div>
                                </div>
                            </div>

                            <div class="ctt-stage">
                                <div class="clockin-setup-banner" id="clockInMobileModeBanner" <?= $isMobileClockInDevice ? '' : 'hidden' ?>>
                                    <div class="clockin-setup-banner-copy">
                                        <span class="clockin-setup-banner-icon">
                                            <i class="fa fa-mobile"></i>
                                        </span>
                                        <div>
                                            <p class="clockin-setup-banner-title">Mobile Companion Mode</p>
                                            <p class="clockin-setup-banner-text">Use mobile for tasks and messages. Clock In requires desktop screen capture.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="clockin-setup-banner" id="clockInSetupBanner" hidden>
                                    <div class="clockin-setup-banner-copy">
                                        <span class="clockin-setup-banner-icon">
                                            <i class="fa fa-exclamation-triangle"></i>
                                        </span>
                                        <div>
                                            <p class="clockin-setup-banner-title">Extension Required</p>
                                            <p class="clockin-setup-banner-text">Install the screen capture extension to unlock Clock In.</p>
                                        </div>
                                    </div>
                                    <button type="button" class="clockin-setup-banner-btn" id="clockInSetupBannerBtn">
                                        Setup <i class="fa fa-arrow-right"></i>
                                    </button>
                                </div>

                                <div class="employee-attendance-note" id="attendanceStatusBanner">
                                    <span class="employee-attendance-note-icon" id="attendanceStatusIcon">
                                        <i class="fa fa-camera"></i>
                                    </span>
                                    <span id="attendanceStatus">Screen captures taken randomly</span>
                                </div>

                                <div class="clockin-action-stack">
                                    <div class="clockin-setup-anchor" id="clockInSetupAnchor">
                                        <button id="btnTimeIn" class="btn-clock-in" type="button" style="display:flex;">
                                            <span class="clockin-button-main">
                                                <i id="clockInButtonIcon" class="fa fa-play"></i>
                                                <span id="clockInButtonLabel">Clock In</span>
                                            </span>
                                            <span class="clockin-button-lock-note" id="clockInButtonLockNote">Install extension first</span>
                                        </button>

                                        <div class="clockin-setup-hover" id="clockInSetupHover" aria-hidden="true">
                                            <span class="clockin-setup-hover-arrow" aria-hidden="true"></span>
                                            <div class="clockin-setup-hover-top">
                                                <span class="clockin-setup-hover-top-icon">
                                                    <i class="fa fa-lock"></i>
                                                </span>
                                                <div>
                                                    <p class="clockin-setup-hover-kicker">Setup Required</p>
                                                    <p class="clockin-setup-hover-title">Unlock Clock In in 5 steps</p>
                                                </div>
                                            </div>
                                            <div class="clockin-setup-hover-body">
                                                <div class="clockin-setup-tab-row is-compact">
                                                    <button type="button" class="clockin-setup-tab-btn is-active" data-clockin-tab-button="video">
                                                        <i class="fa fa-video-camera"></i> Video
                                                    </button>
                                                    <button type="button" class="clockin-setup-tab-btn" data-clockin-tab-button="slides">
                                                        <i class="fa fa-clone"></i> Steps
                                                    </button>
                                                </div>

                                                <div class="clockin-setup-tab-panel is-active" data-clockin-panel="video" data-clockin-scope="compact">
                                                    <div class="clockin-guide-video-shell is-compact" data-clockin-video-shell>
                                                        <video class="clockin-guide-video" data-clockin-video preload="metadata" muted playsinline>
                                                            <source src="videos/extension-guide.mp4" type="video/mp4">
                                                        </video>
                                                        <button class="clockin-guide-video-toggle" data-clockin-video-toggle type="button" aria-label="Play clock-in setup guide">
                                                            <span class="clockin-guide-video-toggle-disc">
                                                                <i class="fa fa-play"></i>
                                                            </span>
                                                        </button>
                                                        <button class="clockin-guide-video-pause" data-clockin-video-pause type="button" aria-label="Pause clock-in setup guide">
                                                            <i class="fa fa-pause"></i>
                                                        </button>
                                                        <span class="clockin-guide-video-badge">
                                                            <i class="fa fa-play"></i>
                                                            Guide
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="clockin-setup-tab-panel" data-clockin-panel="slides" data-clockin-scope="compact">
                                                    <div class="clockin-guide-slideshow is-compact" data-clockin-slideshow="compact">
                                                        <button type="button" class="clockin-guide-slide-nav is-prev" data-clockin-slide-nav="-1" aria-label="Previous setup step">
                                                            <i class="fa fa-angle-left"></i>
                                                        </button>
                                                        <button type="button" class="clockin-guide-slide-nav is-next" data-clockin-slide-nav="1" aria-label="Next setup step">
                                                            <i class="fa fa-angle-right"></i>
                                                        </button>
                                                        <div class="clockin-guide-slide-icon"></div>
                                                        <div class="clockin-guide-slide-label"></div>
                                                        <div class="clockin-guide-slide-desc"></div>
                                                        <div class="clockin-guide-slide-counter"></div>
                                                    </div>
                                                    <div class="clockin-guide-slide-dots" data-clockin-slide-dots="compact"></div>
                                                </div>

                                                <button type="button" class="clockin-setup-primary-btn" id="clockInSetupOpenGuideBtn">
                                                    View Full Setup Guide <i class="fa fa-arrow-right"></i>
                                                </button>
                                                <button type="button" class="clockin-setup-link-btn" id="clockInSetupHideHoverBtn">
                                                    Don't show this hover again
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="clock-session-actions" id="clockSessionActions" hidden>
                                        <button id="btnPauseSession" class="clock-session-btn btn-clock-pause" type="button">
                                            <i class="fa fa-pause"></i> Pause
                                        </button>
                                        <button id="btnResumeSession" class="clock-session-btn btn-clock-resume" type="button" hidden>
                                            <i class="fa fa-play"></i> Resume
                                        </button>
                                        <button id="btnTimeOut" class="clock-session-btn btn-clock-out" type="button" disabled>
                                            <i class="fa fa-stop"></i> Clock Out
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ctt-box">
                            <div class="ctt-box-row">
                                <div class="employee-time-title">
                                    <span class="ctt-session-icon" aria-hidden="true">
                                        <i class="fa fa-clock-o"></i>
                                    </span>
                                    <div class="ctt-session-times">
                                        <span class="ctt-time-in-value" id="statTimeIn"><?= $attStats['time_in'] ?></span>
                                        <div class="ctt-time-out">OUT: <span id="statTimeOut"><?= $attStats['time_out'] ?></span></div>
                                    </div>
                                </div>
                                <div class="ctt-time-label">TIME IN</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dash-card employee-tabbed-leaderboard">
                    <div class="admin-tab-bar">
                        <button type="button" class="admin-tab-btn active" id="employeeTabEmployees" onclick="switchEmployeeLeaderboardTab('employees')">Top Employees</button>
                        <button type="button" class="admin-tab-btn" id="employeeTabGroups" onclick="switchEmployeeLeaderboardTab('groups')">Top Groups</button>
                    </div>

                    <div class="admin-tab-panel" id="employeePanelEmployees">
                        <?php if (!empty($top_users)) { ?>
                            <div class="leaderboard-list">
                                <?php foreach (array_slice($top_users, 0, 4) as $idx => $u) {
                                    $rankColor = $idx === 0 ? 'var(--primary)' : ($idx === 1 ? 'var(--primary-dark)' : 'var(--primary-strong)');
                                    $avatar = !empty($u['profile_image']) ? 'uploads/' . $u['profile_image'] : 'img/user.png';
                                ?>
                                <div class="leaderboard-item">
                                    <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                    <img src="<?= $avatar ?>" class="leaderboard-avatar" alt="User">
                                    <div class="leaderboard-info">
                                        <div class="leaderboard-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <div class="leaderboard-meta">
                                            <?= (int)$u['rated_task_count'] ?> task rate<?= ((int)$u['rated_task_count'] !== 1 ? 's' : '') ?>
                                            &bull;
                                            <?= (int)$u['collab_score_count'] ?> collaborative rate<?= ((int)$u['collab_score_count'] !== 1 ? 's' : '') ?>
                                        </div>
                                    </div>
                                    <div class="leaderboard-rating">
                                        <i class="fa fa-star" style="color:#F59E0B;"></i> <?= htmlspecialchars($u['avg_rating']) ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="leaderboard-empty">
                                <i class="fa fa-info-circle"></i> No employee ratings yet.
                            </div>
                        <?php } ?>
                    </div>

                    <div class="admin-tab-panel" id="employeePanelGroups" style="display:none;">
                        <?php if (!empty($top_groups)) { ?>
                            <div class="leaderboard-list">
                                <?php foreach (array_slice($top_groups, 0, 4) as $idx => $g) {
                                    $rankColor = $idx === 0 ? 'var(--primary)' : ($idx === 1 ? 'var(--primary-dark)' : 'var(--primary-strong)');
                                ?>
                                <div class="leaderboard-item">
                                    <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                    <div class="leaderboard-info">
                                        <div class="leaderboard-name"><?= htmlspecialchars($g['group_name']) ?></div>
                                        <div class="leaderboard-meta">
                                            <?= (int)$g['member_count'] ?> member<?= ((int)$g['member_count'] !== 1 ? 's' : '') ?>
                                            &bull;
                                            <?= (int)$g['rated_task_count'] ?> rated task<?= ((int)$g['rated_task_count'] !== 1 ? 's' : '') ?>
                                        </div>
                                    </div>
                                    <div class="leaderboard-rating">
                                        <i class="fa fa-star" style="color:#F59E0B;"></i> <?= htmlspecialchars($g['avg_rating']) ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="leaderboard-empty">
                                <i class="fa fa-info-circle"></i> No group ratings yet.
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="dash-card bulletin-card">
                <div class="bulletin-head">
                    <div class="bulletin-head-left">
                        <div class="bulletin-icon-box"><i class="fa fa-thumb-tack"></i></div>
                        <span class="bulletin-title">Bulletin Board</span>
                    </div>
                </div>
                <div class="bulletin-list" id="bulletinList"></div>
                <div class="dash-stats-grid employee-inline-stats">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Total Tasks</h4>
                            <span><?= $num_task ?></span>
                        </div>
                        <div class="stat-icon icon-blue">
                            <i class="fa fa-check-square-o"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Completed Tasks</h4>
                            <span><?= $completed ?></span>
                        </div>
                        <div class="stat-icon icon-green">
                            <i class="fa fa-clock-o"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Collaborative Rate</h4>
                            <span><?= $collaborative_rate ?></span>
                        </div>
                        <div class="stat-icon icon-purple">
                            <i class="fa fa-users"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Avg Rating</h4>
                            <span style="display:flex; align-items:center; gap:4px;"><?= $avg_rating ?></span>
                        </div>
                        <div class="stat-icon icon-yellow">
                            <i class="fa fa-star-o"></i>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <?php if ($_SESSION['role'] != 'admin') { ?>
            <section class="dashboard-recent-board">
                <div class="tasks-section-header">
                    <h3>Recent Tasks</h3>
                    <?php if ($_SESSION['role'] == "admin") { ?>
                        <a href="create_task.php" class="btn-create-task">
                            <i class="fa fa-plus"></i> Create Task
                        </a>
                    <?php } ?>
                </div>

                <!-- Tasks Grid (Updated Layout) -->
                <div class="tasks-grid">
                    <?php if (!empty($recent_tasks) && count($recent_tasks) > 0) { 
                        foreach($recent_tasks as $task) { 
                            // Status Logic (match Tasks page derived state from subtasks)
                            $statusClass = "pending";
                            $statusText = "pending";
                            $taskStatusRaw = strtolower(trim((string)($task['status'] ?? 'pending')));
                            $taskRating = isset($task['rating']) ? (float)$task['rating'] : 0.0;

                            $taskId = (int)($task['id'] ?? 0);
                            $hasStartedSubtask = !empty($recentTaskStartedSubtaskMap[$taskId]);

                            if ($taskStatusRaw === 'completed' && $taskRating <= 0) {
                                $statusClass = "submitted";
                                $statusText = "submitted for review";
                            } elseif ($taskStatusRaw === 'completed') {
                                $statusClass = "completed";
                                $statusText = "completed";
                            } elseif ($hasStartedSubtask || $taskStatusRaw === 'in_progress') {
                                $statusClass = "in_progress";
                                $statusText = "IN PROGRESS";
                            }

                            // Determine Redirect URL
                            $redirectUrl = ($_SESSION['role'] == 'admin') 
                                ? "tasks.php?open_task=" . $task['id'] 
                                : "my_task.php?open_task=" . $task['id'];

                            // Organize Assignees
                            $assignees = $recentTaskAssigneesMap[$taskId] ?? [];
                            $leader = null;
                            $members = [];
                            if (!empty($assignees)) {
                                foreach ($assignees as $a) {
                                    if ($a['role'] == 'leader') $leader = $a;
                                    else $members[] = $a;
                                }
                            }
                    ?>
                    <!-- Task Card -->
                    <div class="task-card" onclick="navigateWithClockInGuard('<?=$redirectUrl?>')">
                        
                        <div class="dashboard-task-head">
                            <h3 class="task-title"><?= htmlspecialchars($task['title']) ?></h3>
                        </div>
                        
                        <div class="dashboard-task-status">
                            <span class="badge-v2 <?=$statusClass?>"><?= $statusText ?></span>
                        </div>
                        
                        <div class="preview-content">
                            <div class="dashboard-task-desc">
                                <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 100, "...")) ?>
                            </div>

                            <?php if ($leader) { 
                                $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                            ?>
                            <div class="leader-box-preview">
                                <img src="<?= $leaderImg ?>" class="dashboard-leader-avatar" alt="Leader">
                                <div>
                                    <div class="dashboard-leader-label">
                                        <i class="fa fa-crown"></i> Project Leader
                                    </div>
                                    <div class="dashboard-leader-name">
                                        <?= htmlspecialchars($leader['full_name']) ?>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>

                            <?php if (!empty($members)) { ?>
                            <div class="dashboard-team-label">
                                <i class="fa fa-users"></i>
                                <div>Team Members</div>
                            </div>
                            <div class="dashboard-team-row">
                                <div class="dashboard-team-avatars">
                                    <?php foreach (array_slice($members, 0, 4) as $m) { 
                                        $mImg = !empty($m['profile_image']) ? 'uploads/' . $m['profile_image'] : 'img/user.png';
                                    ?>
                                    <img src="<?= $mImg ?>" class="dashboard-team-avatar" alt="Member">
                                    <?php } ?>
                                </div>
                                <span class="dashboard-team-count"><?= count($members) ?> member<?= count($members)>1?'s':''?></span>
                            </div>
                            <?php } ?>
                        </div>

                        <!-- Footer -->
                        <div class="task-footer">
                            <div>Due: <?= empty($task['due_date']) ? 'No Date' : date("M d", strtotime($task['due_date'])) ?></div>
                            <?php if ($task['status'] == 'completed' && isset($task['rating']) && (float)$task['rating'] > 0) { ?>
                            <div class="dashboard-task-rating"><i class="fa fa-star"></i> <?= number_format((float)$task['rating'], 1) ?>/5</div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php 
                    } 
                    } else { ?>
                        <div class="dashboard-empty-state">
                            <i class="fa fa-folder-open-o"></i>
                            <h3>No recent tasks</h3>
                        </div>
                    <?php } ?>
                </div>
                
                <div class="dashboard-view-all-wrap">
                     <a href="<?= ($_SESSION['role']=='admin'?'tasks.php':'my_task.php') ?>" class="dashboard-view-all-link">
                         View All Tasks <i class="fa fa-arrow-right"></i>
                     </a>
                </div>
            </section>
        <?php } ?>
        </div>
        </div>

    </div>

    <?php if ($_SESSION['role'] === 'admin') { ?>
    <div class="admin-clockout-modal" id="adminClockOutModal" style="display:none;">
        <div class="admin-clockout-dialog" role="dialog" aria-modal="true" aria-labelledby="adminClockOutTitle">
            <button type="button" class="admin-clockout-close" id="adminClockOutClose" aria-label="Close clock out confirmation">
                <i class="fa fa-times"></i>
            </button>
            <div class="admin-clockout-kicker">Confirm Action</div>
            <h3 id="adminClockOutTitle">Clock Out User?</h3>
            <p>Are you sure you want to clock out <strong id="adminClockOutName">this user</strong>?</p>
            <div class="admin-clockout-field">
                <label class="admin-clockout-label" for="adminClockOutRemark">Remark</label>
                <textarea
                    id="adminClockOutRemark"
                    class="admin-clockout-textarea"
                    maxlength="255"
                    placeholder="Tell the user why you are clocking them out."
                ></textarea>
                <div class="admin-clockout-help">This remark is required and will be shared with the user.</div>
            </div>
            <div class="admin-clockout-actions">
                <button type="button" class="admin-btn admin-btn-ghost" id="adminClockOutCancel">Cancel</button>
                <button type="button" class="admin-btn admin-btn-danger" id="adminClockOutConfirm">Yes, Clock Out</button>
            </div>
        </div>
    </div>

    <div class="admin-user-detail-modal" id="adminUserDetailModal" style="display:none;">
        <div class="admin-user-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="adminUserDetailTitle">
            <button type="button" class="admin-clockout-close" id="adminUserDetailClose" aria-label="Close user details">
                <i class="fa fa-times"></i>
            </button>

            <div class="admin-user-detail-head">
                <div class="admin-user-detail-avatar" id="adminUserDetailAvatar">
                    <img id="adminUserDetailAvatarImage" alt="" hidden>
                    <span id="adminUserDetailAvatarInitials">U</span>
                </div>
                <div class="admin-user-detail-head-copy">
                    <h3 id="adminUserDetailTitle">User Details</h3>
                    <p id="adminUserDetailSubtitle"></p>
                </div>
            </div>

            <div class="admin-user-detail-content" id="adminUserDetailContent" hidden>
                <div class="admin-user-detail-grid">
                    <div class="admin-user-detail-stat">
                        <span class="admin-user-detail-label">Last Time In</span>
                        <strong id="adminUserDetailTimeIn">--</strong>
                    </div>
                    <div class="admin-user-detail-stat">
                        <span class="admin-user-detail-label">Last Screen Capture</span>
                        <strong id="adminUserDetailLastScreenshot">No screen captures yet</strong>
                    </div>
                    <div class="admin-user-detail-stat">
                        <span class="admin-user-detail-label">Status</span>
                        <span class="admin-user-detail-status-chip is-active" id="adminUserDetailStatusChip">Active</span>
                    </div>
                </div>

                <div class="admin-user-detail-reason" id="adminUserDetailReasonWrap" hidden>
                    <span class="admin-user-detail-label">Pause Reason</span>
                    <div class="admin-user-detail-reason-pill" id="adminUserDetailReason">--</div>
                </div>

                <div class="admin-user-detail-capture-shell">
                    <a class="admin-user-detail-capture-preview is-empty" id="adminUserDetailCapturePreviewLink" href="screenshots.php">
                        <img id="adminUserDetailCapturePreviewImage" alt="Latest screen capture preview" hidden>
                        <div class="admin-user-detail-capture-empty" id="adminUserDetailCaptureEmpty">
                            <i class="fa fa-picture-o"></i>
                            <span>No screen capture available yet.</span>
                        </div>
                    </a>
                    <div class="admin-user-detail-capture-copy">
                        <div class="admin-user-detail-label">Latest Capture</div>
                        <strong id="adminUserDetailCaptureTitle">Waiting for first screen capture</strong>
                        <p id="adminUserDetailCaptureMeta">As soon as a screen capture is saved, it will appear here.</p>
                    </div>
                </div>

                <div class="admin-user-detail-actions">
                    <a class="admin-btn admin-btn-capture" id="adminUserDetailCaptureLink" href="screenshots.php">
                        View Captures <i class="fa fa-arrow-right"></i>
                    </a>
                    <button type="button" class="admin-btn admin-btn-ghost" id="adminUserDetailDone">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

<!-- SCRIPTS PRESERVED FROM ORIGINAL (Minimally required) -->
<script type="text/javascript">
    // Store user ID from PHP session
    var currentUserId = <?= isset($_SESSION['id']) ? $_SESSION['id'] : 'null' ?>;
    var isEmployeeUser = <?= (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') ? 'true' : 'false' ?>;
    var isAdminUser = <?= (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'true' : 'false' ?>;
    var attendanceAjaxCsrfToken = <?= json_encode($attendanceAjaxCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var bulletinPostCsrfToken = <?= json_encode($bulletinPostCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var bulletinDeleteCsrfToken = <?= json_encode($bulletinDeleteCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var adminClockOutCsrfToken = <?= json_encode((isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? csrf_token('admin_clock_out_action') : '') ?>;
    var bulletinPosts = [];
    var bulletinPostsLoaded = false;
    var bulletinPostsDirty = false;
    var bulletinTagLabels = { ann: 'Announcement', rem: 'Reminder', alt: 'Alert' };
    var workspaceCaptureIntervalConfig = <?= json_encode([
        'min_minutes' => (int)($workspaceCaptureInterval['min_minutes'] ?? workspace_screenshot_interval_default_min_minutes()),
        'max_minutes' => (int)($workspaceCaptureInterval['max_minutes'] ?? workspace_screenshot_interval_default_max_minutes()),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var isMobileClockInDevice = <?= $isMobileClockInDevice ? 'true' : 'false' ?>;

    const timeTrackerCard = document.getElementById('employeeTimeTrackerCard');
    const btnIn = document.getElementById('btnTimeIn');
    const btnOut = document.getElementById('btnTimeOut');
    const btnPauseSession = document.getElementById('btnPauseSession');
    const btnResumeSession = document.getElementById('btnResumeSession');
    const clockSessionActions = document.getElementById('clockSessionActions');
    const statusSpan = document.getElementById('attendanceStatus');
    const attendanceStatusBanner = document.getElementById('attendanceStatusBanner');
    const attendanceStatusIcon = document.getElementById('attendanceStatusIcon');
    const captureStatusLabel = document.getElementById('captureStatusLabel');
    const durationTodayEl = document.getElementById('statDurationToday');
    const durationAllEl = document.getElementById('statDurationAllTime');
    const btnInIcon = document.getElementById('clockInButtonIcon');
    const btnInLabel = document.getElementById('clockInButtonLabel');
    const btnInLockNote = document.getElementById('clockInButtonLockNote');
    const clockInSetupAnchor = document.getElementById('clockInSetupAnchor');
    const clockInSetupHover = document.getElementById('clockInSetupHover');
    const clockInMobileModeBanner = document.getElementById('clockInMobileModeBanner');
    const clockInSetupOpenGuideBtn = document.getElementById('clockInSetupOpenGuideBtn');
    const clockInSetupHideHoverBtn = document.getElementById('clockInSetupHideHoverBtn');
    const clockInSetupBanner = document.getElementById('clockInSetupBanner');
    const clockInSetupBannerBtn = document.getElementById('clockInSetupBannerBtn');
    let clockInSetupModal = document.getElementById('clockInSetupModal');
    let clockInSetupCloseBtn = document.getElementById('clockInSetupCloseBtn');
    let clockInSetupDownloadBtn = document.getElementById('clockInSetupDownloadBtn');
    let clockInSetupDownloadCard = document.getElementById('clockInSetupDownloadCard');
    let clockInSetupDownloadIcon = document.getElementById('clockInSetupDownloadIcon');
    let clockInSetupDownloadTitle = document.getElementById('clockInSetupDownloadTitle');
    let clockInSetupDownloadText = document.getElementById('clockInSetupDownloadText');
    let clockInSetupStatusCard = document.getElementById('clockInSetupStatusCard');
    let clockInSetupStatusCheck = document.getElementById('clockInSetupStatusCheck');
    let clockInSetupStatusTitle = document.getElementById('clockInSetupStatusTitle');
    let clockInSetupStatusText = document.getElementById('clockInSetupStatusText');
    let clockInSetupPrimaryBtn = document.getElementById('clockInSetupPrimaryBtn');
    let clockInSetupDismissHoverBtn = document.getElementById('clockInSetupDismissHoverBtn');
    let pauseSessionModal = document.getElementById('pauseSessionModal');
    let pauseSessionCloseBtn = document.getElementById('pauseSessionCloseBtn');
    let pauseSessionCancelBtn = document.getElementById('pauseSessionCancelBtn');
    let pauseSessionConfirmBtn = document.getElementById('pauseSessionConfirmBtn');
    let pauseSessionLunchBtn = document.getElementById('pauseSessionLunchBtn');
    let pauseSessionReasonInput = document.getElementById('pauseSessionReasonInput');
    let clockInGuideTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-tab-button]'));
    let clockInGuidePanels = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-panel]'));
    let clockInGuideVideoShells = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-video-shell]'));
    let clockInGuideSlideshows = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-slideshow]'));
    let attendanceId = null;
    let captureWindow = null;
    let hasActiveAttendance = false;
    let isAttendancePaused = false;
    let activePauseReason = '';
    let activePauseStartedAt = '';
    let isPauseRequestInProgress = false;
    let isResumeRequestInProgress = false;
    let pauseSessionUseLunch = false;
    let isAutoClockOutInProgress = false;
    let isManualClockOutInProgress = false;
    let isClockInRequestInProgress = false;
    let suppressAdminClockOutToastUntil = 0;
    let durationBaseTodayMinutes = null;
    let durationBaseAllMinutes = null;
    let durationLastTickMs = Date.now();
    const idleCheckThresholdMs = 15 * 60 * 1000; // 15 minutes
    const idleCheckCountdownStartSeconds = 5 * 60; // logout at about 20 minutes
    let idleCheckTimer = null;
    let idleCheckCountdownTimer = null;
    let idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
    let lastDashboardActivityAt = Date.now();
    let isIdleCheckModalOpen = false;
    let isIdleLogoutInProgress = false;
    let pendingIdleCaptureResolve = null;
    let pendingIdleCaptureTimer = null;
    const defaultDocumentTitle = document.title;
    let lastIdleNotificationPermissionRequestAt = 0;
    const idleNotificationPermissionRequestCooldownMs = 30000;
    let idleWarningNotification = null;
    const captureHeartbeatStorageKey = 'taskflow_capture_heartbeat';
    const captureInputStateStorageKey = 'taskflow_capture_input_state';
    const captureHeartbeatFreshMs = 60000;
    const captureInputStateFreshMs = 45000;
    let lastCaptureHeartbeatAt = 0;
    let lastCaptureInputState = 'unknown';
    let lastCaptureInputStateAt = 0;
    let lastCaptureInputThresholdReached = false;
    const clockInNavWarningKey = 'taskflow_nav_clockin_warned_once_user_' + String(currentUserId || 'guest');
    const clockInGuideHoverDisabledKey = 'taskflow_clockin_hover_guide_disabled_user_' + String(currentUserId || 'guest');
    const clockInExtensionDownloadKey = 'taskflow_clockin_extension_downloaded_user_' + String(currentUserId || 'guest');
    const clockInGuideSteps = [
        {
            iconClass: 'fa-download',
            label: 'Step 1: Download the Extension',
            desc: 'Use the download button below to get the screen capture extension zip file.'
        },
        {
            iconClass: 'fa-puzzle-piece',
            label: 'Step 2: Open Chrome Extensions',
            desc: 'Open chrome://extensions in Google Chrome.'
        },
        {
            iconClass: 'fa-wrench',
            label: 'Step 3: Enable Developer Mode',
            desc: 'Turn on Developer mode at the top-right of the Extensions page.'
        },
        {
            iconClass: 'fa-folder-open',
            label: 'Step 4: Load Unpacked',
            desc: 'Extract the zip, click Load unpacked, then select the extracted extension folder.'
        },
        {
            iconClass: 'fa-check-circle',
            label: 'Step 5: Refresh and Clock In',
            desc: 'Refresh this page after loading the extension. Clock In unlocks as soon as the extension is detected.'
        }
    ];
    let hasSeenClockInNavWarning = false;
    let pendingNavTarget = null;
    let pendingBulletinDeleteId = null;
    let isCaptureExtensionAvailable = !!window.screenshotExtensionAvailable;
    let clockInGuideHoverDisabled = false;
    let clockInExtensionDownloaded = false;
    let clockInGuideTab = 'video';
    let clockInGuideSlideIndex = 0;
    let clockInGuideSlideTimer = null;
    let clockInSetupHoverHideTimer = null;
    let hasClockInSetupBindingsInitialized = false;
    try {
        hasSeenClockInNavWarning = sessionStorage.getItem(clockInNavWarningKey) === '1';
    } catch (e) {
        hasSeenClockInNavWarning = false;
    }
    try {
        clockInGuideHoverDisabled = localStorage.getItem(clockInGuideHoverDisabledKey) === '1';
        clockInExtensionDownloaded = localStorage.getItem(clockInExtensionDownloadKey) === '1';
    } catch (e) {
        clockInGuideHoverDisabled = false;
        clockInExtensionDownloaded = false;
    }

    function setStoredClockInGuideFlag(key, enabled) {
        try {
            if (enabled) {
                localStorage.setItem(key, '1');
            } else {
                localStorage.removeItem(key);
            }
        } catch (e) {
            // no-op
        }
    }

    function refreshClockInSetupDeferredElements() {
        clockInSetupModal = document.getElementById('clockInSetupModal');
        clockInSetupCloseBtn = document.getElementById('clockInSetupCloseBtn');
        clockInSetupDownloadBtn = document.getElementById('clockInSetupDownloadBtn');
        clockInSetupDownloadCard = document.getElementById('clockInSetupDownloadCard');
        clockInSetupDownloadIcon = document.getElementById('clockInSetupDownloadIcon');
        clockInSetupDownloadTitle = document.getElementById('clockInSetupDownloadTitle');
        clockInSetupDownloadText = document.getElementById('clockInSetupDownloadText');
        clockInSetupStatusCard = document.getElementById('clockInSetupStatusCard');
        clockInSetupStatusCheck = document.getElementById('clockInSetupStatusCheck');
        clockInSetupStatusTitle = document.getElementById('clockInSetupStatusTitle');
        clockInSetupStatusText = document.getElementById('clockInSetupStatusText');
        clockInSetupPrimaryBtn = document.getElementById('clockInSetupPrimaryBtn');
        clockInSetupDismissHoverBtn = document.getElementById('clockInSetupDismissHoverBtn');
        pauseSessionModal = document.getElementById('pauseSessionModal');
        pauseSessionCloseBtn = document.getElementById('pauseSessionCloseBtn');
        pauseSessionCancelBtn = document.getElementById('pauseSessionCancelBtn');
        pauseSessionConfirmBtn = document.getElementById('pauseSessionConfirmBtn');
        pauseSessionLunchBtn = document.getElementById('pauseSessionLunchBtn');
        pauseSessionReasonInput = document.getElementById('pauseSessionReasonInput');
        clockInGuideTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-tab-button]'));
        clockInGuidePanels = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-panel]'));
        clockInGuideVideoShells = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-video-shell]'));
        clockInGuideSlideshows = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-slideshow]'));
    }

    refreshClockInSetupDeferredElements();

    function isClockInSetupLocked() {
        return !hasActiveAttendance && (isMobileClockInDevice || !isCaptureExtensionAvailable);
    }

    function syncClockInGuideVideoState(shell) {
        if (!shell) return;
        var video = shell.querySelector('[data-clockin-video]');
        if (!video) return;
        shell.classList.toggle('is-playing', !video.paused && !video.ended);
    }

    function pauseClockInGuideVideo(scope) {
        clockInGuideVideoShells.forEach(function (shell) {
            var shellScope = shell.parentNode && shell.parentNode.getAttribute('data-clockin-scope');
            if (scope && shellScope !== scope) return;
            var video = shell.querySelector('[data-clockin-video]');
            if (video && !video.paused) {
                video.pause();
            }
            syncClockInGuideVideoState(shell);
        });
    }

    function playClockInGuideVideo(scope) {
        clockInGuideVideoShells.forEach(function (shell) {
            var shellScope = shell.parentNode && shell.parentNode.getAttribute('data-clockin-scope');
            if (scope && shellScope !== scope) return;
            var video = shell.querySelector('[data-clockin-video]');
            if (!video) return;
            if (video.ended) {
                video.currentTime = 0;
            }
            var playPromise = video.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise.then(function () {
                    syncClockInGuideVideoState(shell);
                }).catch(function () {
                    syncClockInGuideVideoState(shell);
                });
                return;
            }
            syncClockInGuideVideoState(shell);
        });
    }

    function toggleClockInGuideVideo(shell) {
        if (!shell) return;
        var video = shell.querySelector('[data-clockin-video]');
        if (!video) return;
        if (video.paused || video.ended) {
            if (video.ended) {
                video.currentTime = 0;
            }
            var playPromise = video.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise.then(function () {
                    syncClockInGuideVideoState(shell);
                }).catch(function () {
                    syncClockInGuideVideoState(shell);
                });
                return;
            }
            syncClockInGuideVideoState(shell);
            return;
        }
        video.pause();
        syncClockInGuideVideoState(shell);
    }

    function ensureClockInGuideSlideTimer() {
        if (clockInGuideSlideTimer) {
            clearInterval(clockInGuideSlideTimer);
        }
        clockInGuideSlideTimer = setInterval(function () {
            clockInGuideSlideIndex = (clockInGuideSlideIndex + 1) % clockInGuideSteps.length;
            renderClockInGuideSlides();
        }, 3500);
    }

    function buildClockInGuideDots(container) {
        if (!container) return;
        container.innerHTML = '';
        clockInGuideSteps.forEach(function (step, index) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'clockin-guide-slide-dot' + (index === clockInGuideSlideIndex ? ' is-active' : '');
            dot.setAttribute('aria-label', 'Show clock-in setup step ' + String(index + 1));
            dot.addEventListener('click', function () {
                clockInGuideSlideIndex = index;
                renderClockInGuideSlides();
                ensureClockInGuideSlideTimer();
            });
            container.appendChild(dot);
        });
    }

    function renderClockInGuideSlides() {
        var step = clockInGuideSteps[clockInGuideSlideIndex];
        clockInGuideSlideshows.forEach(function (slideshow) {
            var iconEl = slideshow.querySelector('.clockin-guide-slide-icon');
            var labelEl = slideshow.querySelector('.clockin-guide-slide-label');
            var descEl = slideshow.querySelector('.clockin-guide-slide-desc');
            var counterEl = slideshow.querySelector('.clockin-guide-slide-counter');
            if (iconEl) {
                iconEl.innerHTML = '<i class="fa ' + step.iconClass + '"></i>';
            }
            if (labelEl) {
                labelEl.textContent = step.label;
            }
            if (descEl) {
                descEl.textContent = step.desc;
            }
            if (counterEl) {
                counterEl.textContent = String(clockInGuideSlideIndex + 1) + '/' + String(clockInGuideSteps.length);
            }

            var size = slideshow.getAttribute('data-clockin-slideshow') || '';
            var dots = document.querySelector('[data-clockin-slide-dots="' + size + '"]');
            buildClockInGuideDots(dots);
        });
    }

    function setClockInGuideTab(tabName) {
        clockInGuideTab = tabName === 'slides' ? 'slides' : 'video';
        clockInGuideTabButtons.forEach(function (button) {
            var isActive = button.getAttribute('data-clockin-tab-button') === clockInGuideTab;
            button.classList.toggle('is-active', isActive);
        });
        clockInGuidePanels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-clockin-panel') === clockInGuideTab;
            panel.classList.toggle('is-active', isActive);
        });

        if (clockInGuideTab === 'video') {
            if (clockInSetupAnchor && clockInSetupAnchor.classList.contains('is-hover-guide-visible')) {
                playClockInGuideVideo('compact');
            }
            if (clockInSetupModal && !clockInSetupModal.hidden) {
                playClockInGuideVideo('full');
            }
        } else {
            pauseClockInGuideVideo('compact');
            pauseClockInGuideVideo('full');
        }
    }

    function syncClockInSetupDownloadCard() {
        if (!clockInSetupDownloadCard) return;
        clockInSetupDownloadCard.classList.toggle('is-downloaded', clockInExtensionDownloaded);
        if (clockInSetupDownloadIcon) {
            clockInSetupDownloadIcon.className = 'fa ' + (clockInExtensionDownloaded ? 'fa-check' : 'fa-archive');
        }
        if (clockInSetupDownloadTitle) {
            clockInSetupDownloadTitle.textContent = clockInExtensionDownloaded
                ? 'Extension package downloaded'
                : 'TaskFlow Screen Capture Extension';
        }
        if (clockInSetupDownloadText) {
            clockInSetupDownloadText.textContent = clockInExtensionDownloaded
                ? 'Next: extract the zip, open chrome://extensions, then load the folder as an unpacked extension.'
                : 'Download the zip file first, then load the extracted folder in Chrome.';
        }
        if (clockInSetupDownloadBtn) {
            clockInSetupDownloadBtn.textContent = clockInExtensionDownloaded ? 'Download Again' : 'Download';
        }
    }

    function syncClockInSetupStatusCard() {
        if (!clockInSetupStatusCard) return;
        clockInSetupStatusCard.classList.toggle('is-ready', isCaptureExtensionAvailable);
        if (clockInSetupStatusTitle) {
            clockInSetupStatusTitle.textContent = isCaptureExtensionAvailable
                ? 'Extension detected'
                : 'Extension not detected yet';
        }
        if (clockInSetupStatusText) {
            clockInSetupStatusText.textContent = isCaptureExtensionAvailable
                ? 'This page can see the screen capture extension. Clock In is unlocked now.'
                : 'Load it unpacked in chrome://extensions, then refresh this page to unlock Clock In.';
        }
        if (clockInSetupStatusCheck) {
            clockInSetupStatusCheck.innerHTML = '<i class="fa ' + (isCaptureExtensionAvailable ? 'fa-check' : 'fa-refresh') + '"></i>';
        }
        if (clockInSetupPrimaryBtn) {
            clockInSetupPrimaryBtn.textContent = isCaptureExtensionAvailable
                ? 'Clock In Is Now Unlocked'
                : 'Refresh Page After Install';
        }
    }

    function closeClockInSetupHover() {
        if (!clockInSetupAnchor) return;
        if (clockInSetupHoverHideTimer) {
            clearTimeout(clockInSetupHoverHideTimer);
            clockInSetupHoverHideTimer = null;
        }
        clockInSetupAnchor.classList.remove('is-hover-guide-visible');
        if (clockInSetupHover) {
            clockInSetupHover.setAttribute('aria-hidden', 'true');
        }
        pauseClockInGuideVideo('compact');
    }

    function openClockInSetupHover() {
        if (!clockInSetupAnchor || !clockInSetupHover) return;
        if (clockInGuideHoverDisabled || !isClockInSetupLocked()) return;
        if (window.matchMedia && !window.matchMedia('(hover: hover)').matches) return;
        if (clockInSetupModal && !clockInSetupModal.hidden) return;
        if (clockInSetupHoverHideTimer) {
            clearTimeout(clockInSetupHoverHideTimer);
            clockInSetupHoverHideTimer = null;
        }
        clockInSetupAnchor.classList.add('is-hover-guide-visible');
        clockInSetupHover.setAttribute('aria-hidden', 'false');
        if (clockInGuideTab === 'video') {
            playClockInGuideVideo('compact');
        }
    }

    function scheduleClockInSetupHoverClose() {
        if (!clockInSetupAnchor) return;
        if (clockInSetupHoverHideTimer) {
            clearTimeout(clockInSetupHoverHideTimer);
        }
        clockInSetupHoverHideTimer = setTimeout(function () {
            closeClockInSetupHover();
        }, 180);
    }

    function openClockInSetupModal(preferredTab) {
        if (isMobileClockInDevice && !hasActiveAttendance) {
            if (statusSpan) {
                statusSpan.className = '';
                statusSpan.textContent = 'Clock-in requires desktop screen capture. Mobile can still be used for tasks and messages.';
                statusSpan.style.color = '#B45309';
            }
            return;
        }
        if (!clockInSetupModal) return;
        if (preferredTab) {
            setClockInGuideTab(preferredTab);
        }
        clockInSetupModal.hidden = false;
        document.body.classList.add('is-clockin-setup-modal-open');
        closeClockInSetupHover();
        syncClockInSetupDownloadCard();
        syncClockInSetupStatusCard();
        if (clockInGuideTab === 'video') {
            playClockInGuideVideo('full');
        }
    }

    function closeClockInSetupModal() {
        if (!clockInSetupModal) return;
        clockInSetupModal.hidden = true;
        document.body.classList.remove('is-clockin-setup-modal-open');
        pauseClockInGuideVideo('full');
    }

    function hideClockInSetupHoverGuide() {
        clockInGuideHoverDisabled = true;
        setStoredClockInGuideFlag(clockInGuideHoverDisabledKey, true);
        closeClockInSetupHover();
    }

    function syncClockInSetupUi() {
        var locked = isClockInSetupLocked();
        if (timeTrackerCard) {
            timeTrackerCard.classList.toggle('is-setup-locked', locked);
        }
        if (clockInSetupBanner) {
            clockInSetupBanner.hidden = !locked || isMobileClockInDevice;
        }
        if (clockInMobileModeBanner) {
            clockInMobileModeBanner.hidden = !isMobileClockInDevice || hasActiveAttendance;
        }
        if (btnIn) {
            btnIn.classList.toggle('is-locked', locked);
            btnIn.disabled = !!isClockInRequestInProgress || (!!isMobileClockInDevice && !hasActiveAttendance);
        }
        if (btnInLabel) {
            btnInLabel.textContent = isClockInRequestInProgress
                ? 'Clocking in...'
                : (isMobileClockInDevice && !hasActiveAttendance ? 'Desktop Required' : 'Clock In');
        }
        if (btnInIcon) {
            btnInIcon.className = 'fa ' + (isClockInRequestInProgress ? 'fa-spinner fa-spin' : (isMobileClockInDevice && !hasActiveAttendance ? 'fa-desktop' : 'fa-play'));
        }
        if (btnInLockNote) {
            btnInLockNote.textContent = isMobileClockInDevice ? 'Use desktop' : 'Install extension first';
            btnInLockNote.style.display = locked ? 'inline-flex' : 'none';
        }
        if (statusSpan && isMobileClockInDevice && !hasActiveAttendance && !isClockInRequestInProgress) {
            statusSpan.className = '';
            statusSpan.textContent = 'Mobile companion mode. Clock-in requires desktop screen capture.';
            statusSpan.style.color = '#B45309';
        }
        syncClockInSetupDownloadCard();
        syncClockInSetupStatusCard();
        if (!locked) {
            closeClockInSetupHover();
        }
    }

    function setCaptureExtensionAvailability(isAvailable) {
        isCaptureExtensionAvailable = !!isAvailable;
        syncClockInSetupUi();
    }

    function clearIdleCheckStateForPause() {
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
            idleCheckTimer = null;
        }
        stopIdleCheckCountdown();
        closeIdleWarningNotification();
        isIdleCheckModalOpen = false;
        idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
        updateIdleCheckCountdownLabel();
        updateIdleAlertIndicators();
        var idleModal = document.getElementById('idleCheckModal');
        if (idleModal) {
            idleModal.style.display = 'none';
        }
    }

    function syncPauseSessionUi() {
        syncTimeTrackerCardState();

        if (clockSessionActions) {
            clockSessionActions.hidden = !hasActiveAttendance;
        }

        if (btnPauseSession) {
            btnPauseSession.hidden = !hasActiveAttendance || isAttendancePaused;
            btnPauseSession.disabled = !hasActiveAttendance || isPauseRequestInProgress || isResumeRequestInProgress || isManualClockOutInProgress;
        }

        if (btnResumeSession) {
            btnResumeSession.hidden = !hasActiveAttendance || !isAttendancePaused;
            btnResumeSession.disabled = !hasActiveAttendance || isResumeRequestInProgress || isPauseRequestInProgress || isManualClockOutInProgress;
        }

        if (btnOut) {
            btnOut.disabled = !hasActiveAttendance || isManualClockOutInProgress || isPauseRequestInProgress || isResumeRequestInProgress;
        }

        if (captureStatusLabel) {
            if (isAttendancePaused) {
                captureStatusLabel.textContent = 'Screen capture paused';
            } else if (hasActiveAttendance) {
                captureStatusLabel.textContent = 'Screen capture active';
            } else {
                captureStatusLabel.textContent = 'Screen captures on';
            }
        }

        if (attendanceStatusBanner) {
            attendanceStatusBanner.classList.remove('is-active', 'is-paused');
            if (hasActiveAttendance && isAttendancePaused) {
                attendanceStatusBanner.classList.add('is-paused');
            } else if (hasActiveAttendance) {
                attendanceStatusBanner.classList.add('is-active');
            }
        }

        if (attendanceStatusIcon) {
            attendanceStatusIcon.innerHTML = hasActiveAttendance && isAttendancePaused
                ? '<i class="fa fa-pause"></i>'
                : '<i class="fa fa-camera"></i>';
        }

        if (!statusSpan) return;
        if (isClockInRequestInProgress || isPauseRequestInProgress || isResumeRequestInProgress || isManualClockOutInProgress || isAutoClockOutInProgress) {
            return;
        }
        if (hasActiveAttendance && isAttendancePaused) {
            statusSpan.className = '';
            statusSpan.textContent = activePauseReason
                ? 'Session paused. Reason: ' + activePauseReason + '.'
                : 'Session paused.';
            statusSpan.style.color = '#B45309';
            return;
        }
        if (hasActiveAttendance) {
            statusSpan.className = '';
            statusSpan.textContent = 'Timed in. Screen capture active.';
            statusSpan.style.color = '';
        }
    }

    function syncTimeTrackerCardState() {
        if (!timeTrackerCard) return;
        timeTrackerCard.classList.remove('is-idle', 'is-running', 'is-paused');
        if (hasActiveAttendance && isAttendancePaused) {
            timeTrackerCard.classList.add('is-paused');
            return;
        }
        if (hasActiveAttendance) {
            timeTrackerCard.classList.add('is-running');
            return;
        }
        timeTrackerCard.classList.add('is-idle');
    }

    function setAttendancePauseState(isPaused, reason, pausedAt) {
        isAttendancePaused = !!isPaused && !!hasActiveAttendance;
        activePauseReason = isAttendancePaused ? String(reason || '').trim() : '';
        activePauseStartedAt = isAttendancePaused ? String(pausedAt || '') : '';

        if (isAttendancePaused) {
            lastCaptureHeartbeatAt = 0;
            lastCaptureInputState = 'unknown';
            lastCaptureInputStateAt = 0;
            lastCaptureInputThresholdReached = false;
            clearIdleCheckStateForPause();
        } else if (hasActiveAttendance && !isIdleLogoutInProgress) {
            lastDashboardActivityAt = Date.now();
            startIdleCheckTimer();
        }

        syncPauseSessionUi();
    }

    function getPauseSessionReasonValue() {
        if (pauseSessionUseLunch) {
            return 'Lunch';
        }
        if (!pauseSessionReasonInput) {
            return '';
        }
        return String(pauseSessionReasonInput.value || '').trim();
    }

    function syncPauseSessionModalUi() {
        var finalReason = getPauseSessionReasonValue();
        var canSubmit = finalReason.length > 0;

        if (pauseSessionLunchBtn) {
            pauseSessionLunchBtn.classList.toggle('is-active', pauseSessionUseLunch);
        }

        if (pauseSessionReasonInput) {
            pauseSessionReasonInput.disabled = pauseSessionUseLunch;
            pauseSessionReasonInput.classList.toggle('has-value', !pauseSessionUseLunch && String(pauseSessionReasonInput.value || '').trim() !== '');
        }

        if (pauseSessionConfirmBtn) {
            pauseSessionConfirmBtn.disabled = !canSubmit;
            pauseSessionConfirmBtn.classList.toggle('is-enabled', canSubmit);
        }
    }

    function openPauseSessionModal() {
        refreshClockInSetupDeferredElements();
        if (!pauseSessionModal || !hasActiveAttendance || isAttendancePaused) return;
        pauseSessionUseLunch = false;
        if (pauseSessionReasonInput) {
            pauseSessionReasonInput.value = '';
        }
        syncPauseSessionModalUi();
        pauseSessionModal.hidden = false;
        document.body.classList.add('is-pause-session-modal-open');
        if (pauseSessionReasonInput && typeof pauseSessionReasonInput.focus === 'function') {
            setTimeout(function () {
                pauseSessionReasonInput.focus();
            }, 20);
        }
    }

    function closePauseSessionModal() {
        if (!pauseSessionModal) return;
        pauseSessionModal.hidden = true;
        document.body.classList.remove('is-pause-session-modal-open');
        pauseSessionUseLunch = false;
        if (pauseSessionReasonInput) {
            pauseSessionReasonInput.value = '';
        }
        syncPauseSessionModalUi();
    }

    function openCaptureWindowForAttendance(options) {
        var opts = options || {};
        if (!attendanceId) return;

        if (opts.replaceExisting && captureWindow && !captureWindow.closed) {
            try {
                captureWindow.close();
            } catch (e) {
                // no-op
            }
            captureWindow = null;
        }

        if (captureWindow && !captureWindow.closed) {
            try {
                captureWindow.focus();
            } catch (e) {
                // no-op
            }
            return;
        }

        var maxWidth = screen.availWidth || screen.width || 1280;
        var maxHeight = screen.availHeight || screen.height || 720;
        var width = Math.min(1200, Math.max(720, maxWidth - 80));
        var height = Math.min(800, Math.max(560, maxHeight - 120));
        var left = Math.max(0, Math.round((maxWidth - width) / 2));
        var top = Math.max(0, Math.round((maxHeight - height) / 2));
        var captureUrl = 'capture.html?attendanceId=' + encodeURIComponent(attendanceId) +
            '&userId=' + encodeURIComponent(currentUserId) +
            '&csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken) +
            '&capture_min_minutes=' + encodeURIComponent((workspaceCaptureIntervalConfig && workspaceCaptureIntervalConfig.min_minutes) || 20) +
            '&capture_max_minutes=' + encodeURIComponent((workspaceCaptureIntervalConfig && workspaceCaptureIntervalConfig.max_minutes) || 30);
        if (opts.resumeMode) {
            captureUrl += '&resume=1';
        }

        captureWindow = window.open(
            captureUrl,
            'TaskFlowCapture',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
        );

        if (!captureWindow && opts.resumeMode) {
            isResumeRequestInProgress = false;
            if (statusSpan) {
                statusSpan.textContent = 'Unable to open the screen sharing window. Session is still paused.';
                statusSpan.style.color = '#B45309';
            }
            syncPauseSessionUi();
        }
    }

    function stopCaptureWindowForPause(reason) {
        signalCaptureStop(reason || 'attendance_paused');
        if (captureWindow && !captureWindow.closed) {
            setTimeout(function () {
                try {
                    captureWindow.close();
                } catch (e) {
                    // no-op
                }
                captureWindow = null;
            }, 700);
        } else {
            captureWindow = null;
        }
    }

    function markClockInNavWarningSeen() {
        hasSeenClockInNavWarning = true;
        try {
            sessionStorage.setItem(clockInNavWarningKey, '1');
        } catch (e) {
            // no-op
        }
    }

    function parseServerTimeToMs(value) {
        if (!value) return 0;
        var raw = String(value).trim();
        if (!raw) return 0;
        var ms = Date.parse(raw);
        if (!isNaN(ms)) return ms;
        ms = Date.parse(raw.replace(' ', 'T'));
        return isNaN(ms) ? 0 : ms;
    }

    function setLastCaptureHeartbeat(ms, updateActivityClock) {
        var parsedMs = Number(ms);
        if (!isFinite(parsedMs) || parsedMs <= 0) return;
        if (parsedMs > lastCaptureHeartbeatAt) {
            lastCaptureHeartbeatAt = parsedMs;
        }
        if (!updateActivityClock) return;
        if ((Date.now() - parsedMs) > captureHeartbeatFreshMs) return;
        if (!hasFreshCaptureInputState()) return;
        if (lastCaptureInputThresholdReached) return;
        if (lastCaptureInputState !== 'active') return;
        lastDashboardActivityAt = Math.max(lastDashboardActivityAt, parsedMs);
    }

    function getCaptureHeartbeatFromStorage(rawValue) {
        var raw = rawValue;
        if (typeof raw !== 'string') {
            try {
                raw = localStorage.getItem(captureHeartbeatStorageKey);
            } catch (e) {
                return null;
            }
        }
        if (!raw) return null;
        try {
            var payload = JSON.parse(raw);
            if (!payload || !payload.ts) return null;
            var heartbeatTs = Number(payload.ts);
            if (!isFinite(heartbeatTs) || heartbeatTs <= 0) return null;
            var heartbeatAttendanceId = payload.attendance_id != null ? Number(payload.attendance_id) : null;
            if (attendanceId && heartbeatAttendanceId && Number(attendanceId) !== heartbeatAttendanceId) {
                return null;
            }
            return {
                ts: heartbeatTs,
                attendance_id: heartbeatAttendanceId
            };
        } catch (e) {
            return null;
        }
    }

    function refreshCaptureHeartbeatFromStorage(updateActivityClock) {
        var heartbeat = getCaptureHeartbeatFromStorage();
        if (!heartbeat) return;
        setLastCaptureHeartbeat(heartbeat.ts, !!updateActivityClock);
    }

    function syncHeartbeatFromAttendancePayload(payload) {
        if (!payload) return;
        if (payload.last_heartbeat_at) {
            setLastCaptureHeartbeat(parseServerTimeToMs(payload.last_heartbeat_at), true);
        }
        if (payload.heartbeat_age_seconds !== null && payload.heartbeat_age_seconds !== undefined) {
            var ageSeconds = Number(payload.heartbeat_age_seconds);
            if (isFinite(ageSeconds) && ageSeconds >= 0) {
                setLastCaptureHeartbeat(Date.now() - (ageSeconds * 1000), true);
            }
        }
    }

    function hasFreshCaptureHeartbeat() {
        if (!hasActiveAttendance) return false;
        refreshCaptureHeartbeatFromStorage(true);
        if (!lastCaptureHeartbeatAt) return false;
        return (Date.now() - lastCaptureHeartbeatAt) <= captureHeartbeatFreshMs;
    }

    function setLastCaptureInputState(state, ts, thresholdReached, updateActivityClock) {
        var parsedTs = Number(ts);
        if (!isFinite(parsedTs) || parsedTs <= 0) return;
        if (parsedTs < lastCaptureInputStateAt) return;

        lastCaptureInputStateAt = parsedTs;
        lastCaptureInputState = (state ? String(state) : 'unknown').toLowerCase();
        lastCaptureInputThresholdReached = !!thresholdReached;

        if (updateActivityClock && !lastCaptureInputThresholdReached && lastCaptureInputState === 'active') {
            if ((Date.now() - parsedTs) <= captureInputStateFreshMs) {
                lastDashboardActivityAt = Math.max(lastDashboardActivityAt, parsedTs);
            }
        }
    }

    function getCaptureInputStateFromStorage(rawValue) {
        var raw = rawValue;
        if (typeof raw !== 'string') {
            try {
                raw = localStorage.getItem(captureInputStateStorageKey);
            } catch (e) {
                return null;
            }
        }
        if (!raw) return null;
        try {
            var payload = JSON.parse(raw);
            if (!payload || !payload.ts) return null;
            var inputTs = Number(payload.ts);
            if (!isFinite(inputTs) || inputTs <= 0) return null;
            var inputAttendanceId = payload.attendance_id != null ? Number(payload.attendance_id) : null;
            if (attendanceId && inputAttendanceId && Number(attendanceId) !== inputAttendanceId) {
                return null;
            }
            var state = payload.state ? String(payload.state).toLowerCase() : 'unknown';
            var thresholdReachedRaw = payload.threshold_reached;
            var thresholdReached = thresholdReachedRaw === true || thresholdReachedRaw === 1 || thresholdReachedRaw === '1';
            return {
                ts: inputTs,
                attendance_id: inputAttendanceId,
                state: state,
                threshold_reached: thresholdReached
            };
        } catch (e) {
            return null;
        }
    }

    function refreshCaptureInputStateFromStorage(updateActivityClock) {
        var inputState = getCaptureInputStateFromStorage();
        if (!inputState) return;
        setLastCaptureInputState(inputState.state, inputState.ts, inputState.threshold_reached, !!updateActivityClock);
    }

    function hasFreshCaptureInputState() {
        refreshCaptureInputStateFromStorage(true);
        if (!lastCaptureInputStateAt) return false;
        return (Date.now() - lastCaptureInputStateAt) <= captureInputStateFreshMs;
    }

    function isCaptureInputIdle() {
        if (!hasFreshCaptureInputState()) return false;
        if (lastCaptureInputThresholdReached) return true;
        return lastCaptureInputState === 'locked';
    }

    function canTreatCaptureAsActive() {
        if (!hasFreshCaptureHeartbeat()) return false;
        if (!hasFreshCaptureInputState()) return false;
        if (lastCaptureInputThresholdReached) return false;
        return lastCaptureInputState === 'active';
    }

    // Toggle button visibility based on state
    function updateButtonState(isTimedIn) {
        hasActiveAttendance = !!isTimedIn;
        if (!btnIn || !btnOut) return;
        if (isTimedIn) {
            closeClockInSetupHover();
            closeClockInSetupModal();
            btnIn.style.display = 'none';
            if (clockSessionActions) {
                clockSessionActions.hidden = false;
            }
            btnOut.disabled = false;
            syncPauseSessionUi();
        } else {
            lastCaptureHeartbeatAt = 0;
            lastCaptureInputState = 'unknown';
            lastCaptureInputStateAt = 0;
            lastCaptureInputThresholdReached = false;
            isAttendancePaused = false;
            activePauseReason = '';
            activePauseStartedAt = '';
            console.log("Resetting to Clock In state");
            isClockInRequestInProgress = false;
            isPauseRequestInProgress = false;
            isResumeRequestInProgress = false;
            btnIn.style.display = 'flex';
            if (clockSessionActions) {
                clockSessionActions.hidden = true;
            }
            syncClockInSetupUi();
            syncPauseSessionUi();
        }
    }

    // Simple AJAX helper
    function ajax(url, data, cb, method) {
        var xhr = new XMLHttpRequest();
        var useMethod = method || 'POST';
        xhr.open(useMethod, url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        cb(JSON.parse(xhr.responseText));
                    } catch (e) {
                        cb({status: 'error', message: 'Invalid JSON response', raw: xhr.responseText});
                    }
                } else {
                    cb({status: 'error', message: 'Network error', statusCode: xhr.status, raw: xhr.responseText});
                }
            }
        };
        if (useMethod === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(data);
        } else {
            xhr.send();
        }
    }

    // Listen for messages from capture window
    window.addEventListener('message', function(event) {
        // Only accept from same origin
        if (event.origin !== window.location.origin) return;
        if (event.data && event.data.type === 'CAPTURE_BEFORE_LOGOUT_DONE') {
            if (pendingIdleCaptureResolve) {
                var resolveFn = pendingIdleCaptureResolve;
                pendingIdleCaptureResolve = null;
                if (pendingIdleCaptureTimer) {
                    clearTimeout(pendingIdleCaptureTimer);
                    pendingIdleCaptureTimer = null;
                }
                resolveFn(!!event.data.success);
            }
            return;
        }
        if (!statusSpan) return;
        
        if (event.data.type === 'CAPTURE_STARTED') {
            if (event.source) {
                captureWindow = event.source;
            }
            if (event.data.resume_mode && isResumeRequestInProgress) {
                ajax(
                    'resume_attendance.php',
                    'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken) + '&attendance_id=' + encodeURIComponent(attendanceId || ''),
                    function (res) {
                        isResumeRequestInProgress = false;
                        if (res.status === 'success') {
                            setAttendancePauseState(false, '', '');
                            statusSpan.textContent = 'Timed in. Screen capture active.';
                            statusSpan.className = '';
                            statusSpan.style.color = '';
                            lastDashboardActivityAt = Date.now();
                            startIdleCheckTimer();
                            return;
                        }

                        statusSpan.textContent = res.message || 'Unable to resume the session.';
                        statusSpan.style.color = '#EF4444';
                        syncPauseSessionUi();
                        stopCaptureWindowForPause('attendance_paused');
                    }
                );
                return;
            }
            statusSpan.textContent = 'Timed in. Screen capture active.';
            statusSpan.className = '';
            statusSpan.style.color = ''; // Reset color
        } else if (event.data.type === 'CAPTURE_STOPPED') {
            captureWindow = null;
            var stopReason = event.data.reason || '';
            if (stopReason === 'attendance_paused' || stopReason === 'attendance_ended' || stopReason === 'attendance_inactive' || stopReason === 'idle_logout') {
                syncPauseSessionUi();
                return;
            }
            statusSpan.textContent = 'Screen capture stopped.';
            if (!isManualClockOutInProgress) {
                autoClockOutDueToCaptureIssue('Screen sharing stopped. You have been clocked out.');
            }
        } else if (event.data.type === 'CAPTURE_ERROR') {
            captureWindow = null;
            if (event.data.resume_mode && isResumeRequestInProgress) {
                isResumeRequestInProgress = false;
                statusSpan.textContent = 'Resume canceled. Session is still paused.';
                statusSpan.style.color = '#B45309';
                syncPauseSessionUi();
                return;
            }
            autoClockOutDueToCaptureIssue('Screen share denied/canceled. You have been clocked out.');
        }
    });

    window.addEventListener('storage', function (event) {
        if (event.key === captureHeartbeatStorageKey && event.newValue) {
            var heartbeat = getCaptureHeartbeatFromStorage(event.newValue);
            if (!heartbeat) return;
            setLastCaptureHeartbeat(heartbeat.ts, true);
            if (isIdleCheckModalOpen && canTreatCaptureAsActive()) {
                closeIdleCheckModal();
                return;
            }
            if (hasActiveAttendance && !isAttendancePaused && !isIdleCheckModalOpen && !isIdleLogoutInProgress) {
                startIdleCheckTimer();
            }
            return;
        }

        if (event.key === captureInputStateStorageKey && event.newValue) {
            var inputState = getCaptureInputStateFromStorage(event.newValue);
            if (!inputState) return;
            setLastCaptureInputState(inputState.state, inputState.ts, inputState.threshold_reached, true);
            if (isIdleCheckModalOpen && canTreatCaptureAsActive()) {
                closeIdleCheckModal();
                return;
            }
            if (hasActiveAttendance && !isAttendancePaused && !isIdleCheckModalOpen && !isIdleLogoutInProgress) {
                startIdleCheckTimer();
            }
        }
    });

    function autoClockOutDueToCaptureIssue(message) {
        var fallbackMessage = 'You were clocked out because screen sharing was canceled or stopped.';
        if (isAutoClockOutInProgress || isManualClockOutInProgress) return;
        if (!hasActiveAttendance && !attendanceId) {
            return;
        }

        markSelfClockOutToastSuppression();
        isAutoClockOutInProgress = true;
        if (statusSpan) statusSpan.textContent = 'Clocking out...';

        ajax('time_out.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
            attendanceId = null;
            var autoMessage = (res && res.status === 'success') ? fallbackMessage : message;
            setClockedOutUI();
            openAutoClockOutModal(autoMessage);

            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            var elOut = document.getElementById('statTimeOut');
            if (elOut) elOut.innerText = timeStr;

            isAutoClockOutInProgress = false;
        });
    }

    function downloadCaptureExtensionPackage() {
        var downloadLink = document.createElement('a');
        downloadLink.href = 'extension.zip';
        downloadLink.download = 'taskflow-screen-capture-extension.zip';
        downloadLink.rel = 'noopener';
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        clockInExtensionDownloaded = true;
        setStoredClockInGuideFlag(clockInExtensionDownloadKey, true);
        syncClockInSetupDownloadCard();
        if (statusSpan && !hasActiveAttendance) {
            statusSpan.className = '';
            statusSpan.textContent = 'Extension download started. Install it, then refresh this page.';
            statusSpan.style.color = '#B45309';
        }
    }

    // Clock In Handler
    if (btnIn) {
        btnIn.addEventListener('click', async function () {
            if (isClockInSetupLocked()) {
                openClockInSetupModal('video');
                return;
            }

            requestIdleNotificationPermission();
            isClockInRequestInProgress = true;
            syncClockInSetupUi();
            statusSpan.className = '';
            statusSpan.textContent = 'Clocking in...';
            statusSpan.style.color = ''; // Reset color
            
            ajax('time_in.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
                if (res.status === 'success') {
                    attendanceId = res.attendance_id || null;
                    hasActiveAttendance = true;
                    isClockInRequestInProgress = false;
                    setLastCaptureHeartbeat(Date.now(), true);
                    setLastCaptureInputState('active', Date.now(), false, true);
                    
                    // Instant UI Update
                    var now = new Date();
                    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    var el = document.getElementById('statTimeIn');
                    if(el) el.innerText = timeStr;
                    var elOut = document.getElementById('statTimeOut');
                    if(elOut) elOut.innerText = '--:--';
                    
                    setAttendancePauseState(false, '', '');
                    openCaptureWindowForAttendance();
                    updateButtonState(true);
                } else {
                    isClockInRequestInProgress = false;
                    statusSpan.textContent = res.message || 'Error during time in';
                    statusSpan.style.color = '#EF4444';
                    syncClockInSetupUi();
                }
            });
        });
    }

    if (btnPauseSession) {
        btnPauseSession.addEventListener('click', function () {
            if (!hasActiveAttendance || isAttendancePaused || isPauseRequestInProgress || isResumeRequestInProgress || isManualClockOutInProgress) {
                return;
            }
            openPauseSessionModal();
        });
    }

    if (btnResumeSession) {
        btnResumeSession.addEventListener('click', function () {
            if (!hasActiveAttendance || !isAttendancePaused || isResumeRequestInProgress || isPauseRequestInProgress || isManualClockOutInProgress) {
                return;
            }
            isResumeRequestInProgress = true;
            syncPauseSessionUi();
            if (statusSpan) {
                statusSpan.textContent = 'Select the entire screen to resume monitoring...';
                statusSpan.style.color = '';
            }
            openCaptureWindowForAttendance({ resumeMode: true });
        });
    }

    function markSelfClockOutToastSuppression() {
        suppressAdminClockOutToastUntil = Date.now() + 12000;
    }

    // Clock Out Handler
    if (btnOut) {
        btnOut.addEventListener('click', function () {
            // Show Confirmation Modal
            document.getElementById('confirmModal').style.display = 'flex';
        });
    }
    
    // Actual Clock Out Logic
    function confirmClockOut() {
        document.getElementById('confirmModal').style.display = 'none';
        closePauseSessionModal();
        isManualClockOutInProgress = true;
        markSelfClockOutToastSuppression();
        
        btnOut.disabled = true;
        statusSpan.textContent = 'Clocking out...';
        statusSpan.style.color = ''; // Reset color

        // Signal other tabs/windows (including capture.html) to stop immediately.
        signalCaptureStop('manual_clock_out');
        
        // Close capture window
        if (captureWindow && !captureWindow.closed) {
            setTimeout(function () {
                try {
                    captureWindow.close();
                } catch (e) {
                    // no-op
                }
                captureWindow = null;
            }, 700);
        }
        
        // Then record time out
        ajax('time_out.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
            if (res.status === 'success') {
                statusSpan.textContent = 'Timed out. Session ended.';
                attendanceId = null;
                hasActiveAttendance = false;
                updateButtonState(false);
                
                // Instant UI Update
                var now = new Date();
                var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                var elOut = document.getElementById('statTimeOut');
                if(elOut) elOut.innerText = timeStr;
                
            } else {
                statusSpan.textContent = res.message || 'Error during time out';
                statusSpan.style.color = '#EF4444';
                btnOut.disabled = false;
            }
            isManualClockOutInProgress = false;
        });
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    function setClockedOutUI(message, isError) {
        attendanceId = null;
        hasActiveAttendance = false;
        lastCaptureHeartbeatAt = 0;
        lastCaptureInputState = 'unknown';
        lastCaptureInputStateAt = 0;
        lastCaptureInputThresholdReached = false;
        closePauseSessionModal();
        updateButtonState(false);
        if (statusSpan) {
            statusSpan.textContent = message || 'Timed out. Session ended.';
            statusSpan.className = isError ? 'status-error' : '';
            statusSpan.style.color = isError ? '#EF4444' : '';
        }
    }

    function parseDurationMinutes(text) {
        var value = String(text || '').trim();
        if (!value) return 0;
        var hoursMatch = value.match(/(\d+)\s*h/i);
        var minsMatch = value.match(/(\d+)\s*m/i);
        var hours = hoursMatch ? parseInt(hoursMatch[1], 10) : 0;
        var mins = minsMatch ? parseInt(minsMatch[1], 10) : 0;
        if (isNaN(hours)) hours = 0;
        if (isNaN(mins)) mins = 0;
        return (hours * 60) + mins;
    }

    function formatDurationMinutes(totalMinutes) {
        var safeMinutes = Math.max(0, parseInt(totalMinutes, 10) || 0);
        var hours = Math.floor(safeMinutes / 60);
        var minutes = safeMinutes % 60;
        return hours + 'h ' + minutes + 'm';
    }

    function syncDurationBaseFromDom() {
        if (!durationTodayEl || !durationAllEl) return;
        durationBaseTodayMinutes = parseDurationMinutes(durationTodayEl.textContent);
        durationBaseAllMinutes = parseDurationMinutes(durationAllEl.textContent);
        durationLastTickMs = Date.now();
    }

    function applyDurationPayload(payload) {
        if (!payload) return;
        if (!durationTodayEl || !durationAllEl) return;
        if (typeof payload.daily_duration === 'string') {
            durationBaseTodayMinutes = parseDurationMinutes(payload.daily_duration);
            durationTodayEl.textContent = formatDurationMinutes(durationBaseTodayMinutes);
        }
        if (typeof payload.overall_duration === 'string') {
            durationBaseAllMinutes = parseDurationMinutes(payload.overall_duration);
            durationAllEl.textContent = formatDurationMinutes(durationBaseAllMinutes);
        }
        durationLastTickMs = Date.now();
    }

    function tickDurationCounters() {
        if (!durationTodayEl || !durationAllEl) return;
        if (durationBaseTodayMinutes === null || durationBaseAllMinutes === null) {
            syncDurationBaseFromDom();
        }
        if (!hasActiveAttendance || isAttendancePaused) {
            durationLastTickMs = Date.now();
            return;
        }
        var now = Date.now();
        var elapsed = now - durationLastTickMs;
        if (elapsed < 60000) return;
        var delta = Math.floor(elapsed / 60000);
        durationBaseTodayMinutes += delta;
        durationBaseAllMinutes += delta;
        durationLastTickMs += delta * 60000;
        durationTodayEl.textContent = formatDurationMinutes(durationBaseTodayMinutes);
        durationAllEl.textContent = formatDurationMinutes(durationBaseAllMinutes);
    }

    if (durationTodayEl && durationAllEl) {
        syncDurationBaseFromDom();
        setInterval(tickDurationCounters, 10000);
    }

    function signalCaptureStop(reason) {
        try {
            localStorage.setItem('taskflow_force_stop_capture', JSON.stringify({
                ts: Date.now(),
                reason: reason || 'clock_out'
            }));
            setTimeout(function () {
                localStorage.removeItem('taskflow_force_stop_capture');
            }, 1000);
        } catch (e) {
            // no-op
        }
    }

    function startIdleCheckTimer() {
        if (window.__taskflowSharedIdleEnabled) return;
        if (isAttendancePaused) return;
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
        }
        if (isIdleCheckModalOpen || isIdleLogoutInProgress) return;
        if (hasFreshCaptureHeartbeat() && isCaptureInputIdle()) {
            openIdleCheckModal();
            return;
        }
        if (canTreatCaptureAsActive()) {
            var heartbeatAgeMs = Math.max(0, Date.now() - lastCaptureHeartbeatAt);
            var untilHeartbeatStaleMs = Math.max(1000, captureHeartbeatFreshMs - heartbeatAgeMs + 500);
            var inputAgeMs = lastCaptureInputStateAt ? Math.max(0, Date.now() - lastCaptureInputStateAt) : captureInputStateFreshMs;
            var untilInputRefreshMs = Math.max(1000, captureInputStateFreshMs - inputAgeMs + 500);
            var untilStaleMs = Math.min(untilHeartbeatStaleMs, untilInputRefreshMs);
            idleCheckTimer = setTimeout(function () {
                startIdleCheckTimer();
            }, untilStaleMs);
            return;
        }
        var elapsedMs = Date.now() - lastDashboardActivityAt;
        var remainingMs = idleCheckThresholdMs - elapsedMs;
        if (remainingMs <= 0) {
            openIdleCheckModal();
            return;
        }
        idleCheckTimer = setTimeout(function () {
            openIdleCheckModal();
        }, remainingMs);
    }

    function updateIdleCheckCountdownLabel() {
        var countdownLabel = document.getElementById('idleCheckCountdown');
        if (countdownLabel) {
            countdownLabel.textContent = String(Math.max(0, idleCheckSecondsRemaining));
        }
        updateIdleAlertIndicators();
    }

    function closeIdleWarningNotification() {
        if (!idleWarningNotification) return;
        try {
            idleWarningNotification.close();
        } catch (e) {
            // no-op
        }
        idleWarningNotification = null;
    }

    function updateIdleAlertIndicators() {
        if (isIdleCheckModalOpen && document.hidden) {
            document.title = 'TaskFlow: Confirm (' + String(Math.max(0, idleCheckSecondsRemaining)) + 's)';
            return;
        }
        document.title = defaultDocumentTitle;
    }

    function requestIdleNotificationPermission() {
        if (!isEmployeeUser) return;
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'default') return;
        var now = Date.now();
        if ((now - lastIdleNotificationPermissionRequestAt) < idleNotificationPermissionRequestCooldownMs) return;
        lastIdleNotificationPermissionRequestAt = now;
        try {
            var permissionPromise = Notification.requestPermission();
            if (permissionPromise && typeof permissionPromise.catch === 'function') {
                permissionPromise.catch(function () {
                    // no-op
                });
            }
        } catch (e) {
            // no-op
        }
    }

    function notifyIdleWhileHidden() {
        updateIdleAlertIndicators();
        if (!document.hidden) return;
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        closeIdleWarningNotification();
        try {
            idleWarningNotification = new Notification('TaskFlow idle warning', {
                body: 'Confirm within ' + String(idleCheckCountdownStartSeconds) + ' seconds or you will be logged out.',
                tag: 'taskflow-idle-warning',
                requireInteraction: true
            });
            idleWarningNotification.onclick = function () {
                window.focus();
                closeIdleWarningNotification();
            };
        } catch (e) {
            // no-op
        }
    }

    function stopIdleCheckCountdown() {
        if (idleCheckCountdownTimer) {
            clearInterval(idleCheckCountdownTimer);
            idleCheckCountdownTimer = null;
        }
    }

    function requestCaptureBeforeIdleLogout() {
        return new Promise(function (resolve) {
            if (!captureWindow || captureWindow.closed) {
                resolve(false);
                return;
            }
            if (pendingIdleCaptureResolve) {
                var previousResolve = pendingIdleCaptureResolve;
                pendingIdleCaptureResolve = null;
                if (pendingIdleCaptureTimer) {
                    clearTimeout(pendingIdleCaptureTimer);
                    pendingIdleCaptureTimer = null;
                }
                previousResolve(false);
            }
            pendingIdleCaptureResolve = resolve;
            pendingIdleCaptureTimer = setTimeout(function () {
                if (!pendingIdleCaptureResolve) return;
                var timeoutResolve = pendingIdleCaptureResolve;
                pendingIdleCaptureResolve = null;
                pendingIdleCaptureTimer = null;
                timeoutResolve(false);
            }, 10000);
            try {
                captureWindow.postMessage({ type: 'CAPTURE_NOW_BEFORE_LOGOUT' }, window.location.origin);
            } catch (e) {
                if (pendingIdleCaptureTimer) {
                    clearTimeout(pendingIdleCaptureTimer);
                    pendingIdleCaptureTimer = null;
                }
                pendingIdleCaptureResolve = null;
                resolve(false);
            }
        });
    }

    async function logoutFromIdleTimeout() {
        if (isIdleLogoutInProgress) return;
        isIdleLogoutInProgress = true;
        stopIdleCheckCountdown();
        isIdleCheckModalOpen = false;
        closeIdleWarningNotification();
        updateIdleAlertIndicators();
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
            idleCheckTimer = null;
        }
        await requestCaptureBeforeIdleLogout();
        signalCaptureStop('idle_logout');
        setTimeout(function () {
            if (typeof window.__tmShowLoadingScreen === 'function') {
                window.__tmShowLoadingScreen();
            }
            window.location.href = 'logout.php';
        }, 700);
    }

    function startIdleCheckCountdown() {
        stopIdleCheckCountdown();
        idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
        updateIdleCheckCountdownLabel();
        idleCheckCountdownTimer = setInterval(function () {
            if (canTreatCaptureAsActive()) {
                closeIdleCheckModal();
                return;
            }
            idleCheckSecondsRemaining -= 1;
            updateIdleCheckCountdownLabel();
            if (idleCheckSecondsRemaining <= 0) {
                logoutFromIdleTimeout();
            }
        }, 1000);
    }

    function openIdleCheckModal() {
        if (window.__taskflowSharedIdleEnabled) return;
        const modal = document.getElementById('idleCheckModal');
        if (!modal || isIdleCheckModalOpen) return;
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
            idleCheckTimer = null;
        }
        isIdleCheckModalOpen = true;
        modal.style.display = 'flex';
        startIdleCheckCountdown();
        notifyIdleWhileHidden();
    }

    function closeIdleCheckModal() {
        const modal = document.getElementById('idleCheckModal');
        if (modal) modal.style.display = 'none';
        stopIdleCheckCountdown();
        closeIdleWarningNotification();
        isIdleCheckModalOpen = false;
        idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
        updateIdleCheckCountdownLabel();
        lastDashboardActivityAt = Date.now();
        if (isAttendancePaused) return;
        startIdleCheckTimer();
    }

    function onDashboardUserActivity() {
        requestIdleNotificationPermission();
        if (isIdleCheckModalOpen) return;
        lastDashboardActivityAt = Date.now();
        startIdleCheckTimer();
    }

    function setupIdleCheckPrompt() {
        if (!isEmployeeUser) return;
        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];
        activityEvents.forEach(function (eventName) {
            document.addEventListener(eventName, onDashboardUserActivity, true);
        });

        document.addEventListener('visibilitychange', function () {
            updateIdleAlertIndicators();
            if (document.hidden && isIdleCheckModalOpen) {
                notifyIdleWhileHidden();
            }
            if (!document.hidden && !isIdleCheckModalOpen) {
                startIdleCheckTimer();
            }
            if (!document.hidden) {
                closeIdleWarningNotification();
            }
        });

        refreshCaptureHeartbeatFromStorage(true);
        refreshCaptureInputStateFromStorage(true);
        lastDashboardActivityAt = Date.now();
        startIdleCheckTimer();
    }

    // On page load, check for active attendance
    if (btnIn && btnOut) {
        ajax('check_attendance.php', '', function (res) {
            if (res.status === 'success' && res.has_active_attendance) {
                attendanceId = res.attendance_id || null;
                syncHeartbeatFromAttendancePayload(res);
                refreshCaptureHeartbeatFromStorage(true);
                refreshCaptureInputStateFromStorage(true);
                hasActiveAttendance = true;
                setAttendancePauseState(!!res.is_paused, res.pause_reason || '', res.pause_started_at || '');
                updateButtonState(true);
            } else if (res.status === 'success') {
                setClockedOutUI();
            }
        }, 'GET');
    }

    if (window.screenshotExtensionAvailable) {
        setCaptureExtensionAvailability(true);
    }

    window.addEventListener('screenshotExtensionReady', function () {
        setCaptureExtensionAvailability(true);
    });

    function initClockInSetupBindings() {
        if (hasClockInSetupBindingsInitialized) return;
        hasClockInSetupBindingsInitialized = true;
        refreshClockInSetupDeferredElements();

        clockInGuideVideoShells.forEach(function (shell) {
            var toggle = shell.querySelector('[data-clockin-video-toggle]');
            var pause = shell.querySelector('[data-clockin-video-pause]');
            var video = shell.querySelector('[data-clockin-video]');
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleClockInGuideVideo(shell);
                });
            }
            if (pause) {
                pause.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (video) {
                        video.pause();
                    }
                    syncClockInGuideVideoState(shell);
                });
            }
            if (video) {
                video.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggleClockInGuideVideo(shell);
                });
                video.addEventListener('play', function () {
                    syncClockInGuideVideoState(shell);
                });
                video.addEventListener('pause', function () {
                    syncClockInGuideVideoState(shell);
                });
                video.addEventListener('ended', function () {
                    syncClockInGuideVideoState(shell);
                });
                syncClockInGuideVideoState(shell);
            }
        });

        clockInGuideTabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setClockInGuideTab(button.getAttribute('data-clockin-tab-button'));
            });
        });

        clockInGuideSlideshows.forEach(function (slideshow) {
            var navButtons = Array.prototype.slice.call(slideshow.querySelectorAll('[data-clockin-slide-nav]'));
            navButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var dir = Number(button.getAttribute('data-clockin-slide-nav') || '0');
                    if (!dir) return;
                    clockInGuideSlideIndex = (clockInGuideSlideIndex + dir + clockInGuideSteps.length) % clockInGuideSteps.length;
                    renderClockInGuideSlides();
                    ensureClockInGuideSlideTimer();
                });
            });
        });

        if (clockInSetupAnchor) {
            clockInSetupAnchor.addEventListener('mouseenter', openClockInSetupHover);
            clockInSetupAnchor.addEventListener('mouseleave', scheduleClockInSetupHoverClose);
            clockInSetupAnchor.addEventListener('focusin', openClockInSetupHover);
            clockInSetupAnchor.addEventListener('focusout', function (e) {
                if (!e.relatedTarget || !clockInSetupAnchor.contains(e.relatedTarget)) {
                    scheduleClockInSetupHoverClose();
                }
            });
        }

        if (clockInSetupBannerBtn) {
            clockInSetupBannerBtn.addEventListener('click', function () {
                openClockInSetupModal('video');
            });
        }

        if (clockInSetupOpenGuideBtn) {
            clockInSetupOpenGuideBtn.addEventListener('click', function () {
                openClockInSetupModal(clockInGuideTab);
            });
        }

        if (clockInSetupHideHoverBtn) {
            clockInSetupHideHoverBtn.addEventListener('click', function () {
                hideClockInSetupHoverGuide();
            });
        }

        if (pauseSessionLunchBtn) {
            pauseSessionLunchBtn.addEventListener('click', function () {
                pauseSessionUseLunch = !pauseSessionUseLunch;
                if (pauseSessionUseLunch && pauseSessionReasonInput) {
                    pauseSessionReasonInput.value = '';
                }
                syncPauseSessionModalUi();
                if (!pauseSessionUseLunch && pauseSessionReasonInput) {
                    pauseSessionReasonInput.focus();
                }
            });
        }

        if (pauseSessionReasonInput) {
            pauseSessionReasonInput.addEventListener('input', function () {
                if (String(pauseSessionReasonInput.value || '').trim() !== '') {
                    pauseSessionUseLunch = false;
                }
                syncPauseSessionModalUi();
            });
        }

        if (pauseSessionCloseBtn) {
            pauseSessionCloseBtn.addEventListener('click', function () {
                closePauseSessionModal();
            });
        }

        if (pauseSessionCancelBtn) {
            pauseSessionCancelBtn.addEventListener('click', function () {
                closePauseSessionModal();
            });
        }

        if (pauseSessionConfirmBtn) {
            pauseSessionConfirmBtn.addEventListener('click', function () {
                var pauseReason = getPauseSessionReasonValue();
                if (!pauseReason || !attendanceId || !hasActiveAttendance || isAttendancePaused || isPauseRequestInProgress) {
                    syncPauseSessionModalUi();
                    return;
                }

                isPauseRequestInProgress = true;
                syncPauseSessionUi();
                if (statusSpan) {
                    statusSpan.textContent = 'Pausing session...';
                    statusSpan.style.color = '';
                }

                ajax(
                    'pause_attendance.php',
                    'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken) +
                        '&attendance_id=' + encodeURIComponent(attendanceId || '') +
                        '&pause_reason=' + encodeURIComponent(pauseReason),
                    function (res) {
                        isPauseRequestInProgress = false;
                        if (res.status === 'success') {
                            setAttendancePauseState(true, res.pause_reason || pauseReason, res.paused_at || '');
                            closePauseSessionModal();
                            stopCaptureWindowForPause('attendance_paused');
                            if (statusSpan) {
                                statusSpan.textContent = 'Session paused. Reason: ' + (activePauseReason || pauseReason) + '.';
                                statusSpan.style.color = '#B45309';
                            }
                            return;
                        }

                        if (statusSpan) {
                            statusSpan.textContent = res.message || 'Unable to pause the session.';
                            statusSpan.style.color = '#EF4444';
                        }
                        syncPauseSessionUi();
                    }
                );
            });
        }

        if (clockInSetupDismissHoverBtn) {
            clockInSetupDismissHoverBtn.addEventListener('click', function () {
                hideClockInSetupHoverGuide();
            });
        }

        if (clockInSetupCloseBtn) {
            clockInSetupCloseBtn.addEventListener('click', function () {
                closeClockInSetupModal();
            });
        }

        if (clockInSetupModal) {
            clockInSetupModal.addEventListener('click', function (e) {
                if (e.target === clockInSetupModal) {
                    closeClockInSetupModal();
                }
            });
        }

        if (pauseSessionModal) {
            pauseSessionModal.addEventListener('click', function (e) {
                if (e.target === pauseSessionModal) {
                    closePauseSessionModal();
                }
            });
        }

        if (clockInSetupDownloadBtn) {
            clockInSetupDownloadBtn.addEventListener('click', function () {
                downloadCaptureExtensionPackage();
                syncClockInSetupUi();
            });
        }

        if (clockInSetupPrimaryBtn) {
            clockInSetupPrimaryBtn.addEventListener('click', function () {
                if (isCaptureExtensionAvailable) {
                    closeClockInSetupModal();
                    if (btnIn) {
                        btnIn.focus();
                    }
                    return;
                }
                window.location.reload();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && clockInSetupModal && !clockInSetupModal.hidden) {
                closeClockInSetupModal();
            }
            if (e.key === 'Escape' && pauseSessionModal && !pauseSessionModal.hidden) {
                closePauseSessionModal();
            }
            if (e.key === 'Escape') {
                var adminNoticeModal = document.getElementById('adminClockOutNoticeModal');
                if (adminNoticeModal && adminNoticeModal.style.display === 'flex') {
                    closeAdminClockOutNoticeModal();
                }
            }
        });

        renderClockInGuideSlides();
        ensureClockInGuideSlideTimer();
        setClockInGuideTab(clockInGuideTab);
        syncClockInSetupUi();
        syncPauseSessionModalUi();
        syncPauseSessionUi();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initClockInSetupBindings);
    } else {
        initClockInSetupBindings();
    }

    // Keep UI in sync if admin clocks out the user (SSE with fallback)
    if (btnIn && btnOut) {
        function applyAttendanceState(payload) {
            if (!payload) return;
            var wasActive = !!hasActiveAttendance;
            if (payload.has_active_attendance) {
                attendanceId = payload.attendance_id || attendanceId;
                syncHeartbeatFromAttendancePayload(payload);
                refreshCaptureHeartbeatFromStorage(true);
                refreshCaptureInputStateFromStorage(true);
                hasActiveAttendance = true;
                applyDurationPayload(payload);
                setAttendancePauseState(!!payload.is_paused, payload.pause_reason || '', payload.pause_started_at || '');
                if (payload.is_paused && captureWindow && !captureWindow.closed) {
                    stopCaptureWindowForPause('attendance_paused');
                }
                updateButtonState(true);
                if (payload.time_in) {
                    var elIn = document.getElementById('statTimeIn');
                    if (elIn) elIn.innerText = payload.time_in;
                }
                if (payload.time_out) {
                    var elOut = document.getElementById('statTimeOut');
                    if (elOut) elOut.innerText = payload.time_out;
                }
            } else {
                if (hasActiveAttendance || attendanceId || (captureWindow && !captureWindow.closed)) {
                    signalCaptureStop('attendance_inactive');
                }
                if (captureWindow && !captureWindow.closed) {
                    setTimeout(function () {
                        try {
                            captureWindow.close();
                        } catch (e) {
                            // no-op
                        }
                        captureWindow = null;
                    }, 700);
                }
                hasActiveAttendance = false;
                durationLastTickMs = Date.now();
                setClockedOutUI();
                if (payload.time_out) {
                    var elOut2 = document.getElementById('statTimeOut');
                    if (elOut2) elOut2.innerText = payload.time_out;
                }
            }

            var adminClockOutNoticeShown = false;
            if (!hasActiveAttendance && payload.clocked_out_by_admin && typeof window.maybeShowAdminClockOutNotice === 'function') {
                adminClockOutNoticeShown = !!window.maybeShowAdminClockOutNotice(payload);
            }

            if (wasActive && !hasActiveAttendance) {
                var now = Date.now();
                if (
                    now > suppressAdminClockOutToastUntil &&
                    !isManualClockOutInProgress &&
                    !isAutoClockOutInProgress &&
                    !isIdleLogoutInProgress
                ) {
                    if (payload.clocked_out_by_admin) {
                        if (!adminClockOutNoticeShown && typeof window.maybeShowAdminClockOutNotice !== 'function' && typeof showToast === 'function') {
                            showToast('You were clocked out by an admin.', 'warning');
                        }
                    } else if (typeof showToast === 'function') {
                        showToast('You were clocked out by an admin.', 'warning');
                    }
                }
            }
        }

        function fallbackPoll() {
            ajax('check_attendance.php', '', function (res) {
                if (res.status === 'success') {
                    applyAttendanceState(res);
                }
            }, 'GET');
        }

        fallbackPoll();
        setInterval(function () {
            if (!document.hidden) {
                fallbackPoll();
            }
        }, 30000);
    }

    function closeModal() {
        document.getElementById('pausedModal').style.display = 'none';
    }

    function navigateWithClockInGuard(targetHref) {
        if (shouldAskClockInConfirmation(targetHref)) {
            pendingNavTarget = targetHref || null;
            openNavClockInModal();
            return false;
        }
        window.location.href = targetHref;
        return true;
    }

    function shouldAskClockInConfirmation(targetHref) {
        if (!isEmployeeUser) return false;
        if (hasActiveAttendance) return false;
        if (isClockInSetupLocked && isClockInSetupLocked()) return false;
        if (hasSeenClockInNavWarning) return false;
        if (!targetHref) return false;
        if (targetHref.startsWith('#') || targetHref.toLowerCase().startsWith('javascript:')) return false;

        const targetUrl = new URL(targetHref, window.location.href);
        const targetPath = targetUrl.pathname.toLowerCase();
        if (targetPath.endsWith('/logout.php') || targetPath === 'logout.php') return false;

        const currentUrl = new URL(window.location.href);
        return targetUrl.pathname !== currentUrl.pathname || targetUrl.search !== currentUrl.search;
    }

    function openNavClockInModal() {
        const modal = document.getElementById('navClockInModal');
        markClockInNavWarningSeen();
        if (modal) modal.style.display = 'flex';
    }

    function closeNavClockInModal() {
        const modal = document.getElementById('navClockInModal');
        if (modal) modal.style.display = 'none';
    }

    function continueNavAfterClockInWarning() {
        const target = pendingNavTarget;
        closeNavClockInModal();
        pendingNavTarget = null;
        if (target) {
            window.location.href = target;
        }
    }

    function openAutoClockOutModal(message) {
        var modal = document.getElementById('autoClockOutModal');
        var text = document.getElementById('autoClockOutMessage');
        if (text) text.textContent = message || 'You were clocked out because screen sharing was canceled or stopped.';
        if (modal) modal.style.display = 'flex';
    }

    function closeAutoClockOutModal() {
        var modal = document.getElementById('autoClockOutModal');
        if (modal) modal.style.display = 'none';
    }

    function switchAdminLeaderboardTab(tabName) {
        var employeesTab = document.getElementById('adminTabEmployees');
        var groupsTab = document.getElementById('adminTabGroups');
        var employeesPanel = document.getElementById('adminPanelEmployees');
        var groupsPanel = document.getElementById('adminPanelGroups');
        if (!employeesTab || !groupsTab || !employeesPanel || !groupsPanel) return;

        var showEmployees = tabName !== 'groups';
        employeesTab.classList.toggle('active', showEmployees);
        groupsTab.classList.toggle('active', !showEmployees);
        employeesPanel.style.display = showEmployees ? 'flex' : 'none';
        groupsPanel.style.display = showEmployees ? 'none' : 'flex';
    }

    function switchEmployeeLeaderboardTab(tabName) {
        var employeesTab = document.getElementById('employeeTabEmployees');
        var groupsTab = document.getElementById('employeeTabGroups');
        var employeesPanel = document.getElementById('employeePanelEmployees');
        var groupsPanel = document.getElementById('employeePanelGroups');
        if (!employeesTab || !groupsTab || !employeesPanel || !groupsPanel) return;

        var showEmployees = tabName !== 'groups';
        employeesTab.classList.toggle('active', showEmployees);
        groupsTab.classList.toggle('active', !showEmployees);
        employeesPanel.style.display = showEmployees ? 'flex' : 'none';
        groupsPanel.style.display = showEmployees ? 'none' : 'flex';
        requestAnimationFrame(applyBulletinAndTileHeights);
    }

    (function bindAdminActiveUserActions() {
        if (!isAdminUser) return;
        var list = document.querySelector('.admin-active-list');
        if (!list) return;

        var clockoutModal = document.getElementById('adminClockOutModal');
        var clockoutName = document.getElementById('adminClockOutName');
        var clockoutRemark = document.getElementById('adminClockOutRemark');
        var clockoutConfirm = document.getElementById('adminClockOutConfirm');
        var clockoutCancel = document.getElementById('adminClockOutCancel');
        var clockoutClose = document.getElementById('adminClockOutClose');

        var detailModal = document.getElementById('adminUserDetailModal');
        var detailClose = document.getElementById('adminUserDetailClose');
        var detailDone = document.getElementById('adminUserDetailDone');
        var detailAvatarImage = document.getElementById('adminUserDetailAvatarImage');
        var detailAvatarInitials = document.getElementById('adminUserDetailAvatarInitials');
        var detailTitle = document.getElementById('adminUserDetailTitle');
        var detailSubtitle = document.getElementById('adminUserDetailSubtitle');
        var detailContent = document.getElementById('adminUserDetailContent');
        var detailTimeIn = document.getElementById('adminUserDetailTimeIn');
        var detailLastScreenshot = document.getElementById('adminUserDetailLastScreenshot');
        var detailStatusChip = document.getElementById('adminUserDetailStatusChip');
        var detailReasonWrap = document.getElementById('adminUserDetailReasonWrap');
        var detailReason = document.getElementById('adminUserDetailReason');
        var detailCapturePreviewLink = document.getElementById('adminUserDetailCapturePreviewLink');
        var detailCapturePreviewImage = document.getElementById('adminUserDetailCapturePreviewImage');
        var detailCaptureEmpty = document.getElementById('adminUserDetailCaptureEmpty');
        var detailCaptureTitle = document.getElementById('adminUserDetailCaptureTitle');
        var detailCaptureMeta = document.getElementById('adminUserDetailCaptureMeta');
        var detailCaptureLink = document.getElementById('adminUserDetailCaptureLink');

        var pendingBtn = null;
        var pendingUserId = '';
        var openDetailUserId = '';
        var detailRequestToken = 0;

        function updateCount(overrideCount) {
            var count = typeof overrideCount === 'number' ? overrideCount : list.querySelectorAll('.admin-user-row').length;
            var badge = document.querySelector('.admin-active-count');
            if (badge) {
                badge.textContent = count + ' online';
            }
        }

        function openClockoutModal(btn) {
            pendingBtn = btn;
            pendingUserId = btn.getAttribute('data-user-id') || '';
            if (clockoutName) {
                clockoutName.textContent = btn.getAttribute('data-user-name') || 'this user';
            }
            if (clockoutRemark) {
                clockoutRemark.value = '';
            }
            if (clockoutModal) {
                clockoutModal.style.display = 'flex';
                if (clockoutRemark) {
                    setTimeout(function () {
                        clockoutRemark.focus();
                    }, 0);
                }
            }
        }

        function closeClockoutModal() {
            if (clockoutRemark) {
                clockoutRemark.value = '';
            }
            if (clockoutModal) {
                clockoutModal.style.display = 'none';
            }
            pendingBtn = null;
            pendingUserId = '';
        }

        function syncPendingButton() {
            if (!pendingUserId) {
                pendingBtn = null;
                return null;
            }

            var refreshedBtn = list.querySelector('.admin-clockout-btn[data-user-id="' + pendingUserId + '"]');
            pendingBtn = refreshedBtn || null;
            return pendingBtn;
        }

        function getClockoutRemark() {
            return clockoutRemark ? clockoutRemark.value.trim() : '';
        }

        function resetDetailView() {
            if (detailAvatarImage) {
                detailAvatarImage.hidden = true;
                detailAvatarImage.removeAttribute('src');
            }
            if (detailAvatarInitials) {
                detailAvatarInitials.hidden = false;
                detailAvatarInitials.textContent = 'U';
            }
            if (detailTimeIn) {
                detailTimeIn.textContent = '--';
            }
            if (detailLastScreenshot) {
                detailLastScreenshot.textContent = 'No screen captures yet';
            }
            if (detailStatusChip) {
                detailStatusChip.textContent = 'Active';
                detailStatusChip.className = 'admin-user-detail-status-chip is-active';
            }
            if (detailReasonWrap) {
                detailReasonWrap.hidden = true;
            }
            if (detailReason) {
                detailReason.textContent = '--';
            }
            if (detailCapturePreviewLink) {
                detailCapturePreviewLink.href = 'screenshots.php';
                detailCapturePreviewLink.classList.add('is-empty');
            }
            if (detailCapturePreviewImage) {
                detailCapturePreviewImage.hidden = true;
                detailCapturePreviewImage.removeAttribute('src');
            }
            if (detailCaptureEmpty) {
                detailCaptureEmpty.hidden = false;
            }
            if (detailCaptureTitle) {
                detailCaptureTitle.textContent = 'Waiting for first screen capture';
            }
            if (detailCaptureMeta) {
                detailCaptureMeta.textContent = 'As soon as a screen capture is saved, it will appear here.';
            }
            if (detailCaptureLink) {
                detailCaptureLink.href = 'screenshots.php';
            }
        }

        function setDetailState(message, tone, allowHtml) {
            var textMessage = message || '';
            if (allowHtml) {
                var tempNode = document.createElement('div');
                tempNode.innerHTML = textMessage;
                textMessage = tempNode.textContent || tempNode.innerText || '';
            }
            if (detailSubtitle) {
                detailSubtitle.textContent = textMessage;
            }
            if (detailContent) {
                detailContent.hidden = true;
            }
        }

        function showDetailContent() {
            if (detailContent) {
                detailContent.hidden = false;
            }
        }

        function closeDetailModal() {
            openDetailUserId = '';
            detailRequestToken += 1;
            resetDetailView();
            if (detailTitle) {
                detailTitle.textContent = 'User Details';
            }
            if (detailSubtitle) {
                detailSubtitle.textContent = '';
            }
            if (detailContent) {
                detailContent.hidden = true;
            }
            if (detailModal) {
                detailModal.style.display = 'none';
            }
        }

        function renderDetail(detail) {
            if (detailTitle) {
                detailTitle.textContent = detail.full_name || 'User';
            }
            if (detailSubtitle) {
                detailSubtitle.textContent = detail.username ? ('@' + detail.username) : 'Active employee';
            }
            if (detailAvatarInitials) {
                detailAvatarInitials.hidden = false;
                detailAvatarInitials.textContent = detail.initials || 'U';
            }
            if (detailAvatarImage) {
                if (detail.avatar_url) {
                    detailAvatarImage.src = detail.avatar_url;
                    detailAvatarImage.hidden = false;
                    if (detailAvatarInitials) {
                        detailAvatarInitials.hidden = true;
                    }
                } else {
                    detailAvatarImage.hidden = true;
                    detailAvatarImage.removeAttribute('src');
                }
            }
            if (detailTimeIn) {
                detailTimeIn.textContent = detail.last_time_in_label || '--';
            }
            if (detailLastScreenshot) {
                detailLastScreenshot.textContent = detail.last_screenshot_label || 'No screen captures yet';
            }
            if (detailStatusChip) {
                detailStatusChip.textContent = detail.status_label || 'Active';
                detailStatusChip.className = 'admin-user-detail-status-chip ' + ((detail.status === 'paused') ? 'is-paused' : 'is-active');
            }
            if (detailReasonWrap) {
                detailReasonWrap.hidden = !(detail.status === 'paused' && detail.pause_reason);
            }
            if (detailReason) {
                detailReason.textContent = detail.pause_reason || '--';
            }
            if (detailCaptureLink) {
                detailCaptureLink.href = detail.captures_url || 'screenshots.php';
            }
            if (detailCapturePreviewLink) {
                detailCapturePreviewLink.href = detail.captures_url || 'screenshots.php';
                detailCapturePreviewLink.classList.toggle('is-empty', !detail.last_screenshot_url);
            }
            if (detailCapturePreviewImage) {
                if (detail.last_screenshot_url) {
                    detailCapturePreviewImage.src = detail.last_screenshot_url;
                    detailCapturePreviewImage.hidden = false;
                } else {
                    detailCapturePreviewImage.hidden = true;
                    detailCapturePreviewImage.removeAttribute('src');
                }
            }
            if (detailCaptureEmpty) {
                detailCaptureEmpty.hidden = !!detail.last_screenshot_url;
            }
            if (detailCaptureTitle) {
                detailCaptureTitle.textContent = detail.last_screenshot_label || 'No screen captures yet';
            }
            if (detailCaptureMeta) {
                detailCaptureMeta.textContent = detail.last_screenshot_note || 'No screen capture available yet.';
            }

            showDetailContent();
        }

        function loadUserDetail(userId, showLoading) {
            if (!userId || !detailModal) return;

            var requestToken = ++detailRequestToken;

            fetch('app/ajax/active_user_detail.php?user_id=' + encodeURIComponent(userId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (requestToken !== detailRequestToken || openDetailUserId !== String(userId)) {
                    return;
                }
                if (!data || data.status !== 'success' || !data.detail) {
                    setDetailState((data && data.message) ? data.message : 'Unable to load this user right now.', 'error', false);
                    return;
                }

                renderDetail(data.detail);
            })
            .catch(function () {
                if (requestToken !== detailRequestToken || openDetailUserId !== String(userId)) {
                    return;
                }
                setDetailState('Unable to load this user right now.', 'error', false);
            });
        }

        function openDetailModal(row) {
            if (!detailModal || !row) return;

            openDetailUserId = row.getAttribute('data-user-id') || '';
            if (!openDetailUserId) return;

            resetDetailView();
            if (detailTitle) {
                detailTitle.textContent = row.getAttribute('data-user-name') || 'User Details';
            }
            if (detailSubtitle) {
                detailSubtitle.textContent = '';
            }
            detailModal.style.display = 'flex';
            showDetailContent();
            loadUserDetail(openDetailUserId, true);
        }

        function doClockOut(btn, remark) {
            var userId = btn.getAttribute('data-user-id');
            var userName = btn.getAttribute('data-user-name') || 'this user';
            if (!userId) return;

            remark = typeof remark === 'string' ? remark.trim() : '';
            if (!remark) {
                alert('Please enter a remark before clocking out ' + userName + '.');
                if (clockoutRemark) {
                    clockoutRemark.focus();
                }
                return;
            }

            var originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Clocking out...';

            var body = new URLSearchParams();
            body.append('user_id', userId);
            body.append('remark', remark);
            body.append('csrf_token', adminClockOutCsrfToken || '');

            fetch('admin_clock_out.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.status === 'success') {
                    if (typeof showToast === 'function') {
                        showToast(userName + ' clocked out successfully.', 'success');
                    }
                    if (openDetailUserId === String(userId)) {
                        closeDetailModal();
                    }
                    var row = btn.closest('.admin-user-row');
                    if (row) {
                        row.style.opacity = '0.5';
                        row.style.pointerEvents = 'none';
                        setTimeout(function () {
                            row.remove();
                            updateCount();
                        }, 250);
                    } else {
                        updateCount();
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    alert((data && data.message) ? data.message : ('Unable to clock out ' + userName + '.'));
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('Failed to clock out user. Please try again.');
            });
        }

        list.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.admin-clockout-btn');
            if (btn) {
                ev.preventDefault();
                if (btn.disabled) return;

                if (clockoutModal) {
                    openClockoutModal(btn);
                } else {
                    var userName = btn.getAttribute('data-user-name') || 'this user';
                    var fallbackRemark = window.prompt('Add a remark for clocking out ' + userName + ':', '');
                    if (fallbackRemark !== null) {
                        doClockOut(btn, fallbackRemark);
                    }
                }
                return;
            }

            if (ev.target.closest('.admin-btn-capture')) {
                return;
            }

            var row = ev.target.closest('.admin-user-row');
            if (!row || !list.contains(row)) {
                return;
            }

            openDetailModal(row);
        });

        if (clockoutConfirm) {
            clockoutConfirm.addEventListener('click', function () {
                if (!pendingBtn) return;

                var btn = pendingBtn;
                var remark = getClockoutRemark();
                if (!remark) {
                    alert('Please enter a remark before clocking out this user.');
                    if (clockoutRemark) {
                        clockoutRemark.focus();
                    }
                    return;
                }
                closeClockoutModal();
                doClockOut(btn, remark);
            });
        }

        if (clockoutCancel) {
            clockoutCancel.addEventListener('click', closeClockoutModal);
        }

        if (clockoutClose) {
            clockoutClose.addEventListener('click', closeClockoutModal);
        }

        if (clockoutModal) {
            clockoutModal.addEventListener('click', function (ev) {
                if (ev.target === clockoutModal) {
                    closeClockoutModal();
                }
            });
        }

        if (detailDone) {
            detailDone.addEventListener('click', closeDetailModal);
        }

        if (detailClose) {
            detailClose.addEventListener('click', closeDetailModal);
        }

        if (detailModal) {
            detailModal.addEventListener('click', function (ev) {
                if (ev.target === detailModal) {
                    closeDetailModal();
                }
            });
        }

        function refreshActiveUsers() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'app/ajax/active_users.php', true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status < 200 || xhr.status >= 300) return;
                var data = null;
                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    data = null;
                }
                if (!data || data.status !== 'success' || typeof data.html !== 'string') return;

                var prevScroll = list.scrollTop;
                list.innerHTML = data.html;
                list.scrollTop = Math.min(prevScroll, list.scrollHeight || 0);
                updateCount(typeof data.count === 'number' ? data.count : undefined);

                if (pendingUserId) {
                    var refreshedPendingBtn = syncPendingButton();
                    if (!refreshedPendingBtn || refreshedPendingBtn.disabled) {
                        closeClockoutModal();
                    }
                }

                if (openDetailUserId) {
                    var activeRow = list.querySelector('.admin-user-row[data-user-id="' + openDetailUserId + '"]');
                    if (!activeRow) {
                        closeDetailModal();
                    } else {
                        loadUserDetail(openDetailUserId, false);
                    }
                }
            };
            xhr.send();
        }

        var scheduleInitialActiveUsersRefresh = window.requestIdleCallback
            ? function () {
                window.requestIdleCallback(refreshActiveUsers, { timeout: 1500 });
            }
            : function () {
                window.setTimeout(refreshActiveUsers, 800);
            };
        scheduleInitialActiveUsersRefresh();
        setInterval(function () {
            if (!document.hidden) {
                refreshActiveUsers();
            }
        }, 30000);
    })();

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function limitBulletinListToThreeVisibleItems() {
        var list = document.getElementById('bulletinList');
        if (!list) return;

        var posts = list.querySelectorAll('.bpost');
        var listStyle = window.getComputedStyle(list);
        var paddingTop = parseFloat(listStyle.paddingTop) || 0;
        var paddingBottom = parseFloat(listStyle.paddingBottom) || 0;
        var gap = parseFloat(listStyle.rowGap || listStyle.gap) || 0;
        var targetHeight = 0;

        if (posts.length >= 3) {
            var thirdItemBottom = posts[2].offsetTop + posts[2].offsetHeight;
            targetHeight = Math.ceil(thirdItemBottom + paddingBottom + gap);
        } else {
            var baseItemHeight = 82;
            if (posts.length > 0) {
                baseItemHeight = posts[0].offsetHeight || baseItemHeight;
            } else {
                var emptyState = list.querySelector('.bulletin-empty');
                if (emptyState) {
                    baseItemHeight = Math.max(72, Math.floor((emptyState.offsetHeight || 0) / 3));
                }
            }
            targetHeight = Math.ceil((baseItemHeight * 3) + (gap * 2) + paddingTop + paddingBottom);
        }

        list.style.overflowY = posts.length > 3 ? 'auto' : 'hidden';
        list.style.minHeight = targetHeight + 'px';
        list.style.maxHeight = targetHeight + 'px';

        if (posts.length === 0) {
            var emptyStateEl = list.querySelector('.bulletin-empty');
            if (emptyStateEl) {
                emptyStateEl.style.minHeight = Math.max(0, targetHeight - paddingTop - paddingBottom) + 'px';
            }
        }
    }

    function limitAdminLeaderboardToFourVisibleItems() {
        if (!isAdminUser) return;

        var employeesPanel = document.getElementById('adminPanelEmployees');
        var groupsPanel = document.getElementById('adminPanelGroups');
        var activePanel = null;
        if (employeesPanel && employeesPanel.style.display !== 'none') {
            activePanel = employeesPanel;
        } else if (groupsPanel && groupsPanel.style.display !== 'none') {
            activePanel = groupsPanel;
        } else {
            activePanel = employeesPanel || groupsPanel;
        }
        if (!activePanel) return;

        var list = activePanel.querySelector('.leaderboard-list');
        if (!list) return;

        var items = list.querySelectorAll('.leaderboard-item');
        list.style.overflowY = 'auto';

        if (items.length <= 4) {
            list.style.maxHeight = 'none';
            return;
        }

        var listStyle = window.getComputedStyle(list);
        var paddingBottom = parseFloat(listStyle.paddingBottom) || 0;
        var gap = parseFloat(listStyle.rowGap || listStyle.gap) || 0;
        var fourthItemBottom = items[3].offsetTop + items[3].offsetHeight;
        var targetHeight = Math.ceil(fourthItemBottom + paddingBottom + gap);

        list.style.maxHeight = targetHeight + 'px';
    }

    function syncAdminBulletinHeightToLeaderboard() {
        if (!isAdminUser) return;
        if (window.innerWidth <= 1180) {
            var smallScreenBulletinList = document.getElementById('bulletinList');
            if (smallScreenBulletinList) {
                smallScreenBulletinList.style.maxHeight = 'none';
                smallScreenBulletinList.style.minHeight = '';
            }
            return;
        }

        var employeesPanel = document.getElementById('adminPanelEmployees');
        var groupsPanel = document.getElementById('adminPanelGroups');
        var activePanel = null;
        if (employeesPanel && employeesPanel.style.display !== 'none') {
            activePanel = employeesPanel;
        } else if (groupsPanel && groupsPanel.style.display !== 'none') {
            activePanel = groupsPanel;
        } else {
            activePanel = employeesPanel || groupsPanel;
        }

        var leaderboardList = activePanel ? activePanel.querySelector('.leaderboard-list') : null;
        var bulletinList = document.getElementById('bulletinList');
        if (!leaderboardList || !bulletinList) return;

        var leaderRect = leaderboardList.getBoundingClientRect();
        var bulletinRect = bulletinList.getBoundingClientRect();
        var targetHeight = Math.floor(leaderRect.bottom - bulletinRect.top);
        if (targetHeight <= 120) return;

        var posts = bulletinList.querySelectorAll('.bpost');
        bulletinList.style.overflowY = posts.length > 4 ? 'auto' : 'hidden';
        bulletinList.style.minHeight = targetHeight + 'px';
        bulletinList.style.maxHeight = targetHeight + 'px';

        if (posts.length === 0) {
            var style = window.getComputedStyle(bulletinList);
            var paddingTop = parseFloat(style.paddingTop) || 0;
            var paddingBottom = parseFloat(style.paddingBottom) || 0;
            var emptyState = bulletinList.querySelector('.bulletin-empty');
            if (emptyState) {
                emptyState.style.minHeight = Math.max(0, targetHeight - paddingTop - paddingBottom) + 'px';
            }
        }
    }

    function applyBulletinAndTileHeights() {
        if (isAdminUser) {
            limitAdminLeaderboardToFourVisibleItems();
            if (document.querySelector('.admin-dashboard')) {
                limitBulletinListToThreeVisibleItems();
            } else {
                syncAdminBulletinHeightToLeaderboard();
            }
            return;
        }
        var employeeBulletinCard = document.querySelector('.bulletin-card');
        if (employeeBulletinCard) {
            employeeBulletinCard.style.height = '';
        }
        limitBulletinListToThreeVisibleItems();
    }

    function renderBulletins() {
        var list = document.getElementById('bulletinList');
        if (!list) return;

        if (!bulletinPostsLoaded) {
            list.style.maxHeight = 'none';
            list.innerHTML = '<div class=\"bulletin-empty\"><i class=\"fa fa-spinner fa-spin\" style=\"font-size:22px; display:block; margin-bottom:8px;\"></i>Loading posts...</div>';
            requestAnimationFrame(applyBulletinAndTileHeights);
            return;
        }

        if (!Array.isArray(bulletinPosts) || bulletinPosts.length === 0) {
            list.style.maxHeight = 'none';
            list.innerHTML = '<div class=\"bulletin-empty\"><i class=\"fa fa-inbox\" style=\"font-size:22px; display:block; margin-bottom:8px;\"></i>No posts yet.</div>';
            requestAnimationFrame(applyBulletinAndTileHeights);
            return;
        }

        list.innerHTML = bulletinPosts.map(function (post) {
            var type = (post && post.type) ? String(post.type) : 'ann';
            if (!bulletinTagLabels[type]) {
                type = 'ann';
            }
            var postId = post && post.id ? parseInt(post.id, 10) : 0;
            var deleteAction = '';
            if (isAdminUser && postId > 0) {
                deleteAction = '<button type=\"button\" class=\"bdelete\" title=\"Delete post\" onclick=\"deleteBulletinPost(' + postId + ')\"><i class=\"fa fa-trash\"></i></button>';
            }

            return '' +
                '<div class=\"bpost ' + type + '\">' +
                    '<div class=\"bpost-top\">' +
                        '<span class=\"btag ' + type + '\">' + escapeHtml(bulletinTagLabels[type]) + '</span>' +
                        '<div class=\"bmeta\">' +
                            '<span class=\"btime\">' + escapeHtml(post.time || '') + '</span>' +
                            deleteAction +
                        '</div>' +
                    '</div>' +
                    '<div class=\"btitle\">' + escapeHtml(post.title || '') + '</div>' +
                    '<div class=\"bbody\">' + escapeHtml(post.body || '') + '</div>' +
                '</div>';
        }).join('');

        requestAnimationFrame(applyBulletinAndTileHeights);
    }

    function loadBulletins() {
        ajax('app/ajax/bulletin_posts.php', null, function (res) {
            bulletinPostsLoaded = true;
            if (!bulletinPostsDirty && res && res.status === 'success' && Array.isArray(res.posts)) {
                bulletinPosts = res.posts;
            } else if (!bulletinPostsDirty) {
                bulletinPosts = [];
            }
            renderBulletins();
        }, 'GET');
    }

    function openBulletinPostModal() {
        var modal = document.getElementById('bulletinPostModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeBulletinPostModal() {
        var modal = document.getElementById('bulletinPostModal');
        if (modal) modal.style.display = 'none';
    }

    function submitBulletinPost() {
        if (!isAdminUser) return;
        var typeEl = document.getElementById('bulletinType');
        var titleEl = document.getElementById('bulletinTitle');
        var bodyEl = document.getElementById('bulletinBody');
        var submitBtn = document.querySelector('#bulletinPostModal .bulletin-btn-submit');
        if (!typeEl || !titleEl || !bodyEl) return;

        var type = typeEl.value;
        var title = titleEl.value.trim();
        var body = bodyEl.value.trim();
        if (!title || !body) {
            alert('Please fill in title and message.');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting...';
        }

        var payload = 'csrf_token=' + encodeURIComponent(bulletinPostCsrfToken) +
            '&type=' + encodeURIComponent(type) +
            '&title=' + encodeURIComponent(title) +
            '&body=' + encodeURIComponent(body);

        ajax('bulletin_post.php', payload, function (res) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post to All Users';
            }

            if (!res || res.status !== 'success' || !res.post) {
                alert((res && res.message) ? res.message : 'Unable to publish bulletin post.');
                return;
            }

            bulletinPostsDirty = true;
            bulletinPosts.unshift(res.post);
            renderBulletins();
            closeBulletinPostModal();
            titleEl.value = '';
            bodyEl.value = '';
        });
    }

    function deleteBulletinPost(postId) {
        if (!isAdminUser) return;
        var id = parseInt(postId, 10);
        if (!id) return;

        pendingBulletinDeleteId = id;
        openBulletinDeleteModal();
    }

    function openBulletinDeleteModal() {
        var modal = document.getElementById('bulletinDeleteModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeBulletinDeleteModal() {
        var modal = document.getElementById('bulletinDeleteModal');
        if (modal) modal.style.display = 'none';
        pendingBulletinDeleteId = null;
    }

    function confirmBulletinDelete() {
        if (!isAdminUser) return;
        var id = parseInt(pendingBulletinDeleteId, 10);
        if (!id) {
            closeBulletinDeleteModal();
            return;
        }
        var btn = document.getElementById('bulletinDeleteConfirmBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Deleting...';
        }

        var payload = 'csrf_token=' + encodeURIComponent(bulletinDeleteCsrfToken) +
            '&post_id=' + encodeURIComponent(String(id));

        ajax('bulletin_delete.php', payload, function (res) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Delete';
            }
            if (!res || res.status !== 'success') {
                alert((res && res.message) ? res.message : 'Unable to delete bulletin post.');
                return;
            }

            bulletinPostsDirty = true;
            bulletinPosts = (bulletinPosts || []).filter(function (item) {
                return parseInt(item && item.id ? item.id : 0, 10) !== id;
            });
            renderBulletins();
            closeBulletinDeleteModal();
        });
    }

    if (isEmployeeUser) {
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (!link) return;
            if (link.hasAttribute('download')) return;
            const href = link.getAttribute('href');
            if (shouldAskClockInConfirmation(href)) {
                e.preventDefault();
                pendingNavTarget = href || null;
                openNavClockInModal();
            }
        }, true);
    }

    document.addEventListener('click', function (event) {
        var postModalEl = document.getElementById('bulletinPostModal');
        if (postModalEl && event.target === postModalEl) {
            closeBulletinPostModal();
        }
        var deleteModalEl = document.getElementById('bulletinDeleteModal');
        if (deleteModalEl && event.target === deleteModalEl) {
            closeBulletinDeleteModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        closeBulletinPostModal();
        closeBulletinDeleteModal();
    });

    window.addEventListener('resize', function () {
        applyBulletinAndTileHeights();
    });

    switchAdminLeaderboardTab('employees');
    switchEmployeeLeaderboardTab('employees');
    renderBulletins();
    var scheduleInitialBulletinLoad = window.requestIdleCallback
        ? function () {
            window.requestIdleCallback(loadBulletins, { timeout: 1500 });
        }
        : function () {
            window.setTimeout(loadBulletins, 700);
        };
    scheduleInitialBulletinLoad();
    if (isEmployeeUser && !window.__taskflowSharedIdleEnabled) {
        setupIdleCheckPrompt();
    }
</script>
<!-- Bulletin Post Modal -->
<div id="bulletinPostModal" class="bulletin-modal-overlay">
    <div class="bulletin-modal">
        <h3><i class="fa fa-thumb-tack" style="color:var(--primary); margin-right:6px;"></i> New Bulletin Post</h3>
        <label for="bulletinType">Type</label>
        <select id="bulletinType">
            <option value="ann">Announcement</option>
            <option value="rem">Reminder</option>
            <option value="alt">Alert</option>
        </select>
        <label for="bulletinTitle">Title</label>
        <input type="text" id="bulletinTitle" placeholder="e.g. Team Meeting this Friday">
        <label for="bulletinBody">Message</label>
        <textarea id="bulletinBody" placeholder="Write your message here..."></textarea>
        <div class="bulletin-modal-actions">
            <button type="button" class="bulletin-btn-cancel" onclick="closeBulletinPostModal()">Cancel</button>
            <button type="button" class="bulletin-btn-submit" onclick="submitBulletinPost()">Post to All Users</button>
        </div>
    </div>
</div>
<!-- Bulletin Delete Modal -->
<div id="bulletinDeleteModal" class="bulletin-modal-overlay">
    <div class="bulletin-modal bulletin-delete-modal">
        <div class="bulletin-delete-icon-wrap">
            <i class="fa fa-trash bulletin-delete-icon"></i>
        </div>
        <h3>Delete Announcement?</h3>
        <p class="bulletin-delete-text">This post will be removed for everyone.</p>
        <div class="bulletin-modal-actions">
            <button type="button" class="bulletin-btn-cancel" onclick="closeBulletinDeleteModal()">Cancel</button>
            <button type="button" id="bulletinDeleteConfirmBtn" class="bulletin-btn-danger" onclick="confirmBulletinDelete()">Delete</button>
        </div>
    </div>
</div>
<!-- Confirmation Modal -->
<div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:12px; width:350px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="width:50px; height:50px; background:#FEE2E2; color:#DC2626; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
            <i class="fa fa-power-off"></i>
        </div>
        <h3 style="margin:0 0 10px; color:#111827;">Clock Out?</h3>
        <p style="color:#6B7280; font-size:14px; margin-bottom:25px; line-height:1.5;">
            Are you sure you want to end your current session?
        </p>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button onclick="closeConfirmModal()" style="background:#F3F4F6; color:#374151; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
            <button onclick="confirmClockOut()" style="background:#EF4444; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Yes, Clock Out</button>
        </div>
    </div>
</div>

<!-- Navigation Warning Modal (Employee only) -->
<div id="navClockInModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1002; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:12px; width:360px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="width:50px; height:50px; background:#FEF3C7; color:#D97706; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
            <i class="fa fa-exclamation-triangle"></i>
        </div>
        <h3 style="margin:0 0 10px; color:#111827;">You are not clocked in</h3>
        <p style="color:#6B7280; font-size:14px; margin-bottom:25px; line-height:1.5;">
            Are you sure you want to go to another page? You have not clocked in yet.
        </p>
        <div style="display:flex; justify-content:center; gap:10px;">
            <button onclick="closeNavClockInModal()" style="background:#F3F4F6; color:#374151; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Dismiss</button>
            <button onclick="continueNavAfterClockInWarning()" style="background:var(--primary); color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Continue</button>
        </div>
    </div>
</div>

<!-- Auto Clock Out Modal -->
<div id="autoClockOutModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1003; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:12px; width:370px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="width:50px; height:50px; background:#FEE2E2; color:#DC2626; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
            <i class="fa fa-exclamation-circle"></i>
        </div>
        <h3 style="margin:0 0 10px; color:#111827;">Clocked Out</h3>
        <p id="autoClockOutMessage" style="color:#6B7280; font-size:14px; margin-bottom:25px; line-height:1.5;">
            You were clocked out because screen sharing was canceled or stopped.
        </p>
        <div style="display:flex; justify-content:center;">
            <button onclick="closeAutoClockOutModal()" style="background:var(--primary); color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Dismiss</button>
        </div>
    </div>
</div>

<!-- Idle Check Modal -->
<div id="idleCheckModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1004; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:12px; width:370px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="width:50px; height:50px; background:#DBEAFE; color:#1D4ED8; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
            <i class="fa fa-user-o"></i>
        </div>
        <h3 style="margin:0 0 10px; color:#111827;">Are you still there?</h3>
        <p style="color:#6B7280; font-size:14px; margin-bottom:25px; line-height:1.5;">
            You have been idle for 15 minutes on the dashboard.
            Confirm within <span id="idleCheckCountdown">300</span> seconds or you will be logged out.
        </p>
        <div style="display:flex; justify-content:center;">
            <button onclick="closeIdleCheckModal()" style="background:var(--primary); color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">I'm still here</button>
        </div>
    </div>
</div>

<!-- Pause Session Modal -->
<div id="pauseSessionModal" class="pause-session-modal" hidden>
    <div class="pause-session-dialog" role="dialog" aria-modal="true" aria-labelledby="pauseSessionModalTitle">
        <div class="pause-session-head">
            <button type="button" class="pause-session-close" id="pauseSessionCloseBtn" aria-label="Close pause session dialog">
                <i class="fa fa-times"></i>
            </button>
            <p class="pause-session-kicker">Pausing Session</p>
            <h3 class="pause-session-title" id="pauseSessionModalTitle">Why are you pausing?</h3>
            <p class="pause-session-copy">A reason is required before pausing your session.</p>
        </div>

        <div class="pause-session-body">
            <div class="pause-session-section">
                <p class="pause-session-section-label">Quick Select</p>
                <button type="button" class="pause-session-quick-btn" id="pauseSessionLunchBtn">
                    <span class="pause-session-quick-icon">
                        <i class="fa fa-cutlery"></i>
                    </span>
                    <span class="pause-session-quick-copy">
                        <strong>Lunch</strong>
                        <small>Taking a lunch break</small>
                    </span>
                    <span class="pause-session-quick-check">
                        <i class="fa fa-check"></i>
                    </span>
                </button>
            </div>

            <div class="pause-session-divider">
                <span></span>
                <strong>or type a reason</strong>
                <span></span>
            </div>

            <div class="pause-session-field">
                <textarea id="pauseSessionReasonInput" class="pause-session-textarea" rows="3" placeholder="e.g. Doctor's appointment, quick errand..."></textarea>
            </div>

            <div class="pause-session-actions">
                <button type="button" class="pause-session-cancel" id="pauseSessionCancelBtn">Cancel</button>
                <button type="button" class="pause-session-confirm" id="pauseSessionConfirmBtn" disabled>
                    <i class="fa fa-pause"></i> Confirm Pause
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Clock In Setup Modal -->
<div id="clockInSetupModal" class="clockin-setup-modal" hidden>
    <div class="clockin-setup-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="clockInSetupModalTitle">
        <div class="clockin-setup-modal-head">
            <button type="button" class="clockin-setup-modal-close" id="clockInSetupCloseBtn" aria-label="Close clock-in setup guide">
                <i class="fa fa-times"></i>
            </button>
            <p class="clockin-setup-modal-kicker">Required Setup</p>
            <h3 class="clockin-setup-modal-title" id="clockInSetupModalTitle">Install Screen Capture Extension</h3>
            <p class="clockin-setup-modal-copy">Clock In stays locked until this page can detect the extension.</p>
        </div>

        <div class="clockin-setup-modal-body">
            <div class="clockin-setup-tab-row">
                <button type="button" class="clockin-setup-tab-btn is-active" data-clockin-tab-button="video">
                    <i class="fa fa-video-camera"></i> Video Guide
                </button>
                <button type="button" class="clockin-setup-tab-btn" data-clockin-tab-button="slides">
                    <i class="fa fa-clone"></i> Step-by-Step
                </button>
            </div>

            <div class="clockin-setup-tab-panel is-active" data-clockin-panel="video" data-clockin-scope="full">
                <div class="clockin-guide-video-shell" data-clockin-video-shell>
                    <video class="clockin-guide-video" data-clockin-video preload="metadata" muted playsinline>
                        <source src="videos/extension-guide.mp4" type="video/mp4">
                    </video>
                    <button class="clockin-guide-video-toggle" data-clockin-video-toggle type="button" aria-label="Play clock-in setup guide">
                        <span class="clockin-guide-video-toggle-disc">
                            <i class="fa fa-play"></i>
                        </span>
                    </button>
                    <button class="clockin-guide-video-pause" data-clockin-video-pause type="button" aria-label="Pause clock-in setup guide">
                        <i class="fa fa-pause"></i>
                    </button>
                    <span class="clockin-guide-video-badge">
                        <i class="fa fa-play"></i>
                        Guide
                    </span>
                </div>
                <p class="clockin-guide-video-caption">Watch the setup once, then load the extension in Chrome and refresh this page.</p>
            </div>

            <div class="clockin-setup-tab-panel" data-clockin-panel="slides" data-clockin-scope="full">
                <div class="clockin-guide-slideshow" data-clockin-slideshow="full">
                    <button type="button" class="clockin-guide-slide-nav is-prev" data-clockin-slide-nav="-1" aria-label="Previous setup step">
                        <i class="fa fa-angle-left"></i>
                    </button>
                    <button type="button" class="clockin-guide-slide-nav is-next" data-clockin-slide-nav="1" aria-label="Next setup step">
                        <i class="fa fa-angle-right"></i>
                    </button>
                    <div class="clockin-guide-slide-icon"></div>
                    <div class="clockin-guide-slide-label"></div>
                    <div class="clockin-guide-slide-desc"></div>
                    <div class="clockin-guide-slide-counter"></div>
                </div>
                <div class="clockin-guide-slide-dots" data-clockin-slide-dots="full"></div>
            </div>

            <div class="clockin-setup-download-card" id="clockInSetupDownloadCard">
                <span class="clockin-setup-download-icon">
                    <i id="clockInSetupDownloadIcon" class="fa fa-archive"></i>
                </span>
                <div class="clockin-setup-download-copy">
                    <p class="clockin-setup-download-title" id="clockInSetupDownloadTitle">TaskFlow Screen Capture Extension</p>
                    <p class="clockin-setup-download-text" id="clockInSetupDownloadText">Download the zip file first, then load the extracted folder in Chrome.</p>
                </div>
                <button type="button" class="clockin-setup-download-btn" id="clockInSetupDownloadBtn">Download</button>
            </div>

            <div class="clockin-setup-status-card" id="clockInSetupStatusCard">
                <span class="clockin-setup-status-check" id="clockInSetupStatusCheck">
                    <i class="fa fa-refresh"></i>
                </span>
                <div class="clockin-setup-status-copy">
                    <p class="clockin-setup-status-title" id="clockInSetupStatusTitle">Extension not detected yet</p>
                    <p class="clockin-setup-status-text" id="clockInSetupStatusText">Load it unpacked in chrome://extensions, then refresh this page to unlock Clock In.</p>
                </div>
            </div>

            <button type="button" class="clockin-setup-modal-primary" id="clockInSetupPrimaryBtn">Refresh Page After Install</button>
            <button type="button" class="clockin-setup-modal-link" id="clockInSetupDismissHoverBtn">Don't show the hover guide again</button>
        </div>
    </div>
</div>
</body>
</html>
<?php 
} else { 
   header("Location: landing.php");
   exit();
}
?>
