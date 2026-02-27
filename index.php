<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

    include "DB_connection.php";
    include "app/model/Task.php";
    include "app/model/user.php";
    include "app/model/Subtask.php";
    include "app/model/Group.php";
    include "app/model/Bulletin.php";
    require_once "inc/csrf.php";

    // --- DATA FETCHING FOR DASHBOARD ---
    
    // 1. Stats and Counts
    if ($_SESSION['role'] == "admin") {
        $num_task = count_tasks($pdo);
        $completed = count_completed_tasks($pdo);
        $num_users = count_users($pdo); // Employees
        $avgSql = "SELECT AVG(rating) FROM tasks WHERE status = 'completed' AND rating IS NOT NULL AND rating > 0";
        $scope = tenant_get_scope($pdo, 'tasks');
        $avgSql .= $scope['sql'];
        $avgStmt = $pdo->prepare($avgSql);
        $avgStmt->execute($scope['params']);
        $avgVal = $avgStmt->fetchColumn();
        $avg_rating = $avgVal ? number_format((float)$avgVal, 1) : "0.0";
        $top_users = get_top_rated_users($pdo, 5);
        $top_groups = get_top_rated_groups($pdo, 5);
        $collabSql = "SELECT AVG(score) FROM subtasks WHERE score IS NOT NULL AND score > 0";
        $scope = tenant_get_scope($pdo, 'subtasks');
        $collabSql .= $scope['sql'];
        $collab_stmt = $pdo->prepare($collabSql);
        $collab_stmt->execute($scope['params']);
        $collab_avg = $collab_stmt->fetchColumn();
        $collaborative_rate = $collab_avg ? number_format($collab_avg, 1) : "0.0";
    } else {
        $num_task = count_my_tasks($pdo, $_SESSION['id']);
        $completed = count_my_completed_tasks($pdo, $_SESSION['id']);
        $num_users = count_users($pdo); // Show total team members
        $stats = get_user_rating_stats($pdo, $_SESSION['id']);
        $avg_rating = $stats['avg'];
        $top_users = get_top_rated_users($pdo, 5);
        $top_groups = get_top_rated_groups($pdo, 5);
        $collab_stats = get_collaborative_scores_by_user($pdo, $_SESSION['id']);
        $collaborative_rate = $collab_stats['avg'];
    }

    // 2. Recent Tasks (show up to 3 latest items)
    $recentTaskLimit = 3;
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

    $attendanceAjaxCsrfToken = csrf_token('attendance_ajax_actions');
    $bulletinPostCsrfToken = csrf_token('bulletin_post_action');
    $bulletinDeleteCsrfToken = csrf_token('bulletin_delete_action');
    $bulletinPosts = get_recent_bulletin_posts($pdo, 30);
    $bulletinPostsJson = json_encode($bulletinPosts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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
            background: #EEF2FF;
            color: #6C3CE1;
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
            border-bottom: 1px solid #EEF2FF;
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
                                    $rankColor = $idx === 0 ? '#6C3CE1' : ($idx === 1 ? '#8B5CF6' : '#7C3AED');
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
                                    $rankColor = $idx === 0 ? '#6C3CE1' : ($idx === 1 ? '#8B5CF6' : '#7C3AED');
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
                <div class="dash-card employee-time-tracker-card">
                    <div class="ctt-header">
                        <div class="ctt-title">
                            <i class="fa fa-clock-o" style="color:#6C3CE1;"></i>
                            Time Tracker
                        </div>
                        <span class="ctt-camera"><i class="fa fa-camera"></i> Screen captures on</span>
                    </div>

                    <div class="ctt-row">
                        <button id="btnTimeIn" class="btn-clock-in" style="display:flex;">
                            <i class="fa fa-play"></i> Clock In
                        </button>
                        <button id="btnTimeOut" class="btn-clock-out" disabled style="display:none;">
                            <i class="fa fa-pause"></i> Clock Out/Pause
                        </button>
                        <div class="ctt-stats">
                            <div class="ctt-stat">
                                <div class="ctt-label">Today</div>
                                <div class="ctt-value"><?= htmlspecialchars((string)$attStats['daily_duration']) ?></div>
                            </div>
                            <div class="ctt-divider"></div>
                            <div class="ctt-stat ctt-stat-right">
                                <div class="ctt-label">All Time</div>
                                <div class="ctt-value"><?= htmlspecialchars((string)$attStats['overall_duration']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="employee-attendance-note">
                        <i class="fa fa-camera"></i>
                        <span id="attendanceStatus">Screen captures taken randomly</span>
                    </div>

                    <div class="ctt-box">
                        <div class="ctt-box-row">
                            <div class="employee-time-title">
                                <i class="fa fa-clock-o" style="color:#6B7280; font-size:14px; margin-right:4px;"></i>
                                <span id="statTimeIn"><?= $attStats['time_in'] ?></span>
                            </div>
                            <div class="ctt-time-label">TIME IN</div>
                        </div>
                        <div class="ctt-time-out">OUT: <span id="statTimeOut"><?= $attStats['time_out'] ?></span></div>
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
                                    $rankColor = $idx === 0 ? '#6C3CE1' : ($idx === 1 ? '#8B5CF6' : '#7C3AED');
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
                                    $rankColor = $idx === 0 ? '#6C3CE1' : ($idx === 1 ? '#8B5CF6' : '#7C3AED');
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
            <?php if ($_SESSION['role'] == 'admin') { ?>
            <!-- Stats Section -->
            <div class="dash-stats-grid">
                <!-- Total Tasks -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Total Tasks</h4>
                        <span><?= $num_task ?></span>
                    </div>
                    <div class="stat-icon icon-blue">
                        <i class="fa fa-check-square-o"></i>
                    </div>
                </div>

                <!-- Completed Tasks -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Completed Tasks</h4>
                        <span><?= $completed ?></span>
                    </div>
                    <div class="stat-icon icon-green">
                        <i class="fa fa-clock-o"></i>
                    </div>
                </div>

                <!-- Collaborative Rate -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Collaborative Rate</h4>
                        <span><?= $collaborative_rate ?></span>
                    </div>
                    <div class="stat-icon icon-purple">
                        <i class="fa fa-users"></i>
                    </div>
                </div>

                <!-- Avg Rating -->
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
            <?php } ?>

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

                            $subtasksForStatus = [];
                            try {
                                $subtasksForStatus = get_subtasks_by_task($pdo, $task['id']);
                            } catch (Throwable $e) {
                                $subtasksForStatus = [];
                            }
                            if (!is_array($subtasksForStatus)) {
                                $subtasksForStatus = [];
                            }

                            $hasStartedSubtask = false;
                            foreach ($subtasksForStatus as $subtaskRow) {
                                $subtaskStatus = strtolower(trim((string)($subtaskRow['status'] ?? 'pending')));
                                if (in_array($subtaskStatus, ['submitted', 'completed', 'in_progress', 'revise', 'revision_needed', 'rejected'], true)) {
                                    $hasStartedSubtask = true;
                                    break;
                                }
                            }

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
                            $assignees = get_task_assignees($pdo, $task['id']);
                            $leader = null;
                            $members = [];
                            if ($assignees != 0) {
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
        </div>
        </div>

    </div>

<!-- SCRIPTS PRESERVED FROM ORIGINAL (Minimally required) -->
<script type="text/javascript">
    // Store user ID from PHP session
    var currentUserId = <?= isset($_SESSION['id']) ? $_SESSION['id'] : 'null' ?>;
    var isEmployeeUser = <?= (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') ? 'true' : 'false' ?>;
    var isAdminUser = <?= (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'true' : 'false' ?>;
    var attendanceAjaxCsrfToken = <?= json_encode($attendanceAjaxCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var bulletinPostCsrfToken = <?= json_encode($bulletinPostCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var bulletinDeleteCsrfToken = <?= json_encode($bulletinDeleteCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var bulletinPosts = <?= $bulletinPostsJson ?: '[]' ?>;
    var bulletinTagLabels = { ann: 'Announcement', rem: 'Reminder', alt: 'Alert' };

    const btnIn = document.getElementById('btnTimeIn');
    const btnOut = document.getElementById('btnTimeOut');
    const statusSpan = document.getElementById('attendanceStatus');
    let attendanceId = null;
    let captureWindow = null;
    let hasActiveAttendance = false;
    let isAutoClockOutInProgress = false;
    let isManualClockOutInProgress = false;
    const idleCheckThresholdMs = 100000; // 100 seconds
    let idleCheckTimer = null;
    let isIdleCheckModalOpen = false;
    const clockInNavWarningKey = 'taskflow_nav_clockin_warned_once_user_' + String(currentUserId || 'guest');
    let hasSeenClockInNavWarning = false;
    let pendingNavTarget = null;
    let pendingBulletinDeleteId = null;
    try {
        hasSeenClockInNavWarning = sessionStorage.getItem(clockInNavWarningKey) === '1';
    } catch (e) {
        hasSeenClockInNavWarning = false;
    }

    function markClockInNavWarningSeen() {
        hasSeenClockInNavWarning = true;
        try {
            sessionStorage.setItem(clockInNavWarningKey, '1');
        } catch (e) {
            // no-op
        }
    }

    // Toggle button visibility based on state
    function updateButtonState(isTimedIn) {
        hasActiveAttendance = !!isTimedIn;
        if (!btnIn || !btnOut) return;
        if (isTimedIn) {
            btnIn.style.display = 'none';
            btnOut.style.display = 'flex';
            btnOut.style.marginTop = '0px'; 
            btnOut.innerHTML = '<i class="fa fa-pause"></i> Clock Out/Pause';
            btnOut.disabled = false;
        } else {
            console.log("Resetting to Clock In state");
            btnIn.style.display = 'flex';
            btnIn.innerHTML = '<i class="fa fa-play"></i> Clock In';
            btnOut.style.display = 'none';
            btnIn.disabled = false;
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
        if (!statusSpan) return;
        
        if (event.data.type === 'CAPTURE_STARTED') {
            statusSpan.textContent = 'Timed in. Screen capture active.';
            statusSpan.className = '';
            statusSpan.style.color = ''; // Reset color
        } else if (event.data.type === 'CAPTURE_STOPPED') {
            statusSpan.textContent = 'Screen capture stopped.';
            if (!isManualClockOutInProgress && (!event.data.reason || event.data.reason !== 'attendance_ended')) {
                autoClockOutDueToCaptureIssue('Screen sharing stopped. You have been clocked out.');
            }
        } else if (event.data.type === 'CAPTURE_ERROR') {
             autoClockOutDueToCaptureIssue('Screen share denied/canceled. You have been clocked out.');
        }
    });

    function autoClockOutDueToCaptureIssue(message) {
        var fallbackMessage = 'You were clocked out because screen sharing was canceled or stopped.';
        if (isAutoClockOutInProgress || isManualClockOutInProgress) return;
        if (!hasActiveAttendance && !attendanceId) {
            return;
        }

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

    // Clock In Handler
    if (btnIn) {
        btnIn.addEventListener('click', async function () {
            btnIn.disabled = true;
            statusSpan.textContent = 'Clocking in...';
            statusSpan.style.color = ''; // Reset color
            
            ajax('time_in.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
                if (res.status === 'success') {
                    attendanceId = res.attendance_id || null;
                    hasActiveAttendance = true;
                    
                    // Instant UI Update
                    var now = new Date();
                    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    var el = document.getElementById('statTimeIn');
                    if(el) el.innerText = timeStr;
                    var elOut = document.getElementById('statTimeOut');
                    if(elOut) elOut.innerText = '--:--';
                    
                    // Open capture window
                    // Width/Height small, bottom right or minimized
                    const width = 400;
                    const height = 300;
                    const left = screen.width - width;
                    const top = screen.height - height;
                    
                    captureWindow = window.open(
                        'capture.html?attendanceId=' + encodeURIComponent(attendanceId) + '&userId=' + encodeURIComponent(currentUserId) + '&csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken),
                        'TaskFlowCapture',
                        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top
                    );

                    updateButtonState(true);
                } else {
                    statusSpan.textContent = res.message || 'Error during time in';
                    statusSpan.style.color = '#EF4444';
                    btnIn.disabled = false;
                }
            });
        });
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
        isManualClockOutInProgress = true;
        
        btnOut.disabled = true;
        statusSpan.textContent = 'Clocking out...';
        statusSpan.style.color = ''; // Reset color

        // Signal other tabs/windows (including capture.html) to stop immediately.
        signalCaptureStop('manual_clock_out');
        
        // Close capture window
        if (captureWindow && !captureWindow.closed) {
            captureWindow.close();
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
        updateButtonState(false);
        if (statusSpan) {
            statusSpan.textContent = message || 'Timed out. Session ended.';
            statusSpan.className = isError ? 'status-error' : '';
            statusSpan.style.color = isError ? '#EF4444' : '';
        }
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
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
        }
        idleCheckTimer = setTimeout(function () {
            openIdleCheckModal();
        }, idleCheckThresholdMs);
    }

    function openIdleCheckModal() {
        const modal = document.getElementById('idleCheckModal');
        if (!modal || isIdleCheckModalOpen) return;
        isIdleCheckModalOpen = true;
        modal.style.display = 'flex';
    }

    function closeIdleCheckModal() {
        const modal = document.getElementById('idleCheckModal');
        if (modal) modal.style.display = 'none';
        isIdleCheckModalOpen = false;
        startIdleCheckTimer();
    }

    function onDashboardUserActivity() {
        if (isIdleCheckModalOpen) return;
        startIdleCheckTimer();
    }

    function setupIdleCheckPrompt() {
        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];
        activityEvents.forEach(function (eventName) {
            document.addEventListener(eventName, onDashboardUserActivity, true);
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (idleCheckTimer) clearTimeout(idleCheckTimer);
                return;
            }
            if (!isIdleCheckModalOpen) {
                startIdleCheckTimer();
            }
        });

        startIdleCheckTimer();
    }

    // On page load, check for active attendance
    if (btnIn && btnOut) {
        ajax('check_attendance.php', '', function (res) {
            if (res.status === 'success' && res.has_active_attendance) {
                attendanceId = res.attendance_id || null;
                
                // Always show Timed In state (Clock Out button) if DB says we are active.
                // This persists across page refreshes/navigation.
                updateButtonState(true);
                statusSpan.textContent = 'Timed in. Monitoring active.';
            } else if (res.status === 'success') {
                setClockedOutUI();
            }
        }, 'GET');
    }

    // Keep UI in sync if admin clocks out the user (SSE with fallback)
    if (btnIn && btnOut) {
        function applyAttendanceState(payload) {
            if (!payload) return;
            if (payload.has_active_attendance) {
                attendanceId = payload.attendance_id || attendanceId;
                hasActiveAttendance = true;
                updateButtonState(true);
                if (statusSpan) {
                    statusSpan.textContent = 'Timed in. Monitoring active.';
                }
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
                    captureWindow.close();
                }
                hasActiveAttendance = false;
                setClockedOutUI();
                if (payload.time_out) {
                    var elOut2 = document.getElementById('statTimeOut');
                    if (elOut2) elOut2.innerText = payload.time_out;
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

        var source = new EventSource('sse_my_attendance.php');
        source.onmessage = function (event) {
            try {
                var data = JSON.parse(event.data || '{}');
                if (data && data.status === 'success') {
                    applyAttendanceState(data);
                }
            } catch (e) {
                // ignore parse errors
            }
        };
        source.onerror = function () {
            source.close();
            fallbackPoll();
            setInterval(fallbackPoll, 5000);
        };
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
            syncAdminBulletinHeightToLeaderboard();
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
    setupIdleCheckPrompt();
</script>
<!-- Bulletin Post Modal -->
<div id="bulletinPostModal" class="bulletin-modal-overlay">
    <div class="bulletin-modal">
        <h3><i class="fa fa-thumb-tack" style="color:#6C3CE1; margin-right:6px;"></i> New Bulletin Post</h3>
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
            <button onclick="continueNavAfterClockInWarning()" style="background:#6C3CE1; color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Continue</button>
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
            <button onclick="closeAutoClockOutModal()" style="background:#6C3CE1; color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Dismiss</button>
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
            You have been idle for 100 seconds on the dashboard.
        </p>
        <div style="display:flex; justify-content:center;">
            <button onclick="closeIdleCheckModal()" style="background:#6C3CE1; color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">I'm still here</button>
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


