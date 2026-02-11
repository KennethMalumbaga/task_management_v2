<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

    include "DB_connection.php";
    include "app/model/Task.php";
    include "app/model/user.php";
    include "app/model/Subtask.php";
    include "app/model/Group.php";

    // --- DATA FETCHING FOR DASHBOARD ---
    
    // 1. Stats and Counts
    if ($_SESSION['role'] == "admin") {
        $num_task = count_tasks($pdo);
        $completed = count_completed_tasks($pdo);
        $num_users = count_users($pdo); // Employees
        $avg_rating = "4.3"; // Mock data as per design
        $top_users = get_top_rated_users($pdo, 5);
        $top_groups = get_top_rated_groups($pdo, 5);
    } else {
        $num_task = count_my_tasks($pdo, $_SESSION['id']);
        $completed = count_my_completed_tasks($pdo, $_SESSION['id']);
        $num_users = count_users($pdo); // Show total team members
        $stats = get_user_rating_stats($pdo, $_SESSION['id']);
        $avg_rating = $stats['avg']; 
    }

    // 2. Recent Tasks (List 2-3 items)
    if ($_SESSION['role'] == "admin") {
         $sql_recent = "SELECT * FROM tasks ORDER BY id DESC LIMIT 2";
         $stmt_recent = $pdo->query($sql_recent);
         $recent_tasks = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    } else {
         $user_id = $_SESSION['id'];
         $sql_recent = "SELECT DISTINCT t.* FROM tasks t
                        JOIN task_assignees ta ON t.id = ta.task_id
                        WHERE ta.user_id=?
                        ORDER BY t.id DESC LIMIT 2";
         $stmt_recent = $pdo->prepare($sql_recent);
         $stmt_recent->execute([$user_id]);
         $recent_tasks = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
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

        /* Mobile Dashboard Optimizations */
        @media (max-width: 768px) {
            .admin-leaderboard-compact {
                max-height: none;
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
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <!-- Top Section: Time Tracker & Welcome -->
        <div class="dash-top-grid">
            
            <!-- Time Tracker Card -->
            <div class="dash-card <?= $_SESSION['role'] == 'admin' ? 'admin-leaderboard-compact' : '' ?>">
                <?php if ($_SESSION['role'] == 'admin') { ?>
                    <div class="leaderboard-split">
                        <div class="leaderboard-pane">
                            <div class="leaderboard-header">
                                <div class="leaderboard-title">
                                    <i class="fa fa-sitemap" style="color: #10B981;"></i>
                                    Top Groups
                                </div>
                            </div>
                            <?php if (!empty($top_groups)) { ?>
                                <div class="leaderboard-list">
                                    <?php foreach ($top_groups as $idx => $g) { 
                                        $rankColor = $idx === 0 ? '#F59E0B' : ($idx === 1 ? '#6366F1' : '#10B981');
                                    ?>
                                    <div class="leaderboard-item">
                                        <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                        <div class="leaderboard-info">
                                            <div class="leaderboard-name"><?= htmlspecialchars($g['group_name']) ?></div>
                                            <div class="leaderboard-meta"><?= (int)$g['member_count'] ?> member<?= ((int)$g['member_count'] !== 1 ? 's' : '') ?> • <?= (int)$g['rated_task_count'] ?> rated task<?= ((int)$g['rated_task_count'] !== 1 ? 's' : '') ?></div>
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

                        <div class="leaderboard-pane">
                            <div class="leaderboard-header">
                                <div class="leaderboard-title">
                                    <i class="fa fa-users" style="color: #4F46E5;"></i>
                                    Top Employees
                                </div>
                            </div>
                            <?php if (!empty($top_users)) { ?>
                                <div class="leaderboard-list">
                                    <?php foreach ($top_users as $idx => $u) { 
                                        $rankColor = $idx === 0 ? '#F59E0B' : ($idx === 1 ? '#6366F1' : '#10B981');
                                        $avatar = !empty($u['profile_image']) ? 'uploads/' . $u['profile_image'] : 'img/user.png';
                                    ?>
                                    <div class="leaderboard-item">
                                        <div class="rank-badge" style="background: <?= $rankColor ?>;">#<?= $idx + 1 ?></div>
                                        <img src="<?= $avatar ?>" class="leaderboard-avatar" alt="User">
                                        <div class="leaderboard-info">
                                            <div class="leaderboard-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div class="leaderboard-meta">
                                                <?= (int)$u['rated_task_count'] ?> task rate<?= ((int)$u['rated_task_count'] !== 1 ? 's' : '') ?>
                                                •
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
                                    <i class="fa fa-info-circle"></i> No user ratings yet.
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="time-tracker-header">
                        <div class="time-tracker-title">
                            <i class="fa fa-clock-o" style="color: #4F46E5;"></i> 
                            Time Tracker
                        </div>
                        <div style="color: #9CA3AF;">
                            <i class="fa fa-camera"></i>
                        </div>
                    </div>

                    <!-- Employee Clock In/Out -->
                    <div style="margin-bottom: 20px;">
                        <button id="btnTimeIn" class="btn-clock-in" style="display: flex;">
                            <i class="fa fa-play"></i> Clock In
                        </button>
                        <button id="btnTimeOut" class="btn-clock-out" disabled style="display: none;">
                            <i class="fa fa-pause"></i> Clock Out/Pause
                        </button>
                    </div>
                    <div class="screenshot-info">
                        <i class="fa fa-camera"></i>
                        <span id="attendanceStatus">Screen captures are taken randomly for activity tracking</span>
                    </div>
                <?php } ?>
            </div>

            <!-- Welcome Card -->
            <div class="dash-card welcome-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h3>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>!</h3>
                        <div class="welcome-role">Role: <?= ucfirst($_SESSION['role']) ?></div>
                        <div style="margin-top: 20px; font-size: 13px; color: #6B7280; line-height: 1.6;">
                            You have <b><?= $num_task - $completed ?></b> active tasks remaining effectively. <br>
                            Keep up the good work!
                        </div>
                    </div>

                    <!-- Attendance Stats Display -->
                    <?php if ($_SESSION['role'] !== 'admin') { 
                        $attStats = get_todays_attendance_stats($pdo, $_SESSION['id']);
                    ?>
                    <div style="text-align: right; background: #EEF2FF; padding: 15px; border-radius: 12px; border: 1px solid #E0E7FF; min-width: 140px;">
                        <!-- Time In -->
                        <div style="margin-bottom: 8px;">
                            <div style="font-size: 10px; color: #6366F1; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Time In</div>
                            <div style="font-size: 18px; font-weight: 700; color: #1F2937;">
                                <i class="fa fa-clock-o" style="color: #6B7280; font-size: 14px; margin-right: 4px;"></i>
                                <span id="statTimeIn"><?= $attStats['time_in'] ?></span>
                            </div>
                            <!-- Time Out -->
                             <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                                <span id="statTimeOutWrapper"><span style="font-size: 10px; opacity: 0.7;">OUT:</span> <span id="statTimeOut"><?= $attStats['time_out'] ?></span></span>
                            </div>
                        </div>

                        <!-- Total Duration: Split Layout -->
                        <div style="border-top: 1px solid #C7D2FE; padding-top: 8px; margin-top: 8px; display: flex; justify-content: space-between; gap: 10px;">
                             <!-- Left: Today -->
                             <div style="text-align: left;">
                                 <div style="font-size: 9px; color: #6366F1; text-transform: uppercase; font-weight: 700;">Today</div>
                                 <div style="font-size: 16px; font-weight: 800; color: #4F46E5;">
                                    <?= $attStats['daily_duration'] ?>
                                 </div>
                             </div>
                             <!-- Right: Overall -->
                             <div style="text-align: right;">
                                 <div style="font-size: 9px; color: #6B7280; text-transform: uppercase; font-weight: 700;">All Time</div>
                                 <div style="font-size: 16px; font-weight: 800; color: #374151;">
                                    <?= $attStats['overall_duration'] ?>
                                 </div>
                             </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

            <!-- Stats Section (Moved Up) -->
            <div class="dash-stats-grid" style="margin-bottom: 24px;">
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

                <!-- Team Members -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Team Members</h4>
                        <span><?= $num_users ?></span>
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
                        // Status Logic
                        $statusClass = "pending";
                        $statusText = str_replace('_', ' ', $task['status']);
                        if ($task['status'] == 'in_progress') $statusClass = "in_progress";
                        
                        $isSubmitted = false;
                        if ($task['status'] == 'completed') {
                            if (isset($task['rating']) && $task['rating'] > 0) {
                                $statusClass = "completed"; $statusText = "completed";
                            } else {
                                $statusClass = "submitted"; $statusText = "submitted for review";
                                $isSubmitted = true;
                            }
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
                <div class="task-card" onclick="location.href='<?=$redirectUrl?>'">
                    
                    <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: start;">
                        <h3 class="task-title" style="margin: 0;"><?= htmlspecialchars($task['title']) ?></h3>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <span class="badge-v2 <?=$statusClass?>"><?= $statusText ?></span>
                    </div>
                    
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
                                <div style="font-size: 10px; font-weight: 700; color: #6366F1; letter-spacing: 0.5px; text-transform: uppercase;">
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
                        <?php if ($leader) {
                            $lStats = get_user_rating_stats($pdo, $leader['user_id']);
                            if($lStats['avg'] > 0) {
                        ?>
                        <div style="color: #F59E0B; font-weight: 600;"><i class="fa fa-star"></i> <?= $lStats['avg'] ?>/5</div>
                        <?php } } ?>
                    </div>
                </div>
                <?php 
                } 
                } else { ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6B7280;">
                        <i class="fa fa-folder-open-o" style="font-size: 48px; opacity: 0.5; margin-bottom: 15px;"></i>
                        <h3>No recent tasks</h3>
                    </div>
                <?php } ?>
            </div>
            
            <div style="margin-top: 15px; text-align: center;">
                 <a href="<?= ($_SESSION['role']=='admin'?'tasks.php':'my_task.php') ?>" style="color: #4F46E5; text-decoration: none; font-size: 14px; font-weight: 500;">
                     View All Tasks <i class="fa fa-arrow-right"></i>
                 </a>
            </div>
        </div>

    </div>

<!-- SCRIPTS PRESERVED FROM ORIGINAL (Minimally required) -->
<script type="text/javascript">
    // Store user ID from PHP session
    var currentUserId = <?= isset($_SESSION['id']) ? $_SESSION['id'] : 'null' ?>;

    const btnIn = document.getElementById('btnTimeIn');
    const btnOut = document.getElementById('btnTimeOut');
    const statusSpan = document.getElementById('attendanceStatus');
    let attendanceId = null;
    let captureWindow = null;

    // Toggle button visibility based on state
    function updateButtonState(isTimedIn) {
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
        
        if (event.data.type === 'CAPTURE_STARTED') {
            statusSpan.textContent = 'Timed in. Screen capture active.';
            statusSpan.className = '';
            statusSpan.style.color = ''; // Reset color
        } else if (event.data.type === 'CAPTURE_STOPPED') {
            statusSpan.textContent = 'Screen capture stopped.';
        } else if (event.data.type === 'CAPTURE_ERROR') {
             statusSpan.textContent = 'Capture error: ' + event.data.message;
             statusSpan.className = 'status-error';
        }
    });

    // Clock In Handler
    if (btnIn) {
        btnIn.addEventListener('click', async function () {
            btnIn.disabled = true;
            statusSpan.textContent = 'Clocking in...';
            statusSpan.style.color = ''; // Reset color
            
            ajax('time_in.php', '', function (res) {
                if (res.status === 'success') {
                    attendanceId = res.attendance_id || null;
                    
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
                        'capture.html?attendanceId=' + attendanceId + '&userId=' + currentUserId,
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
        
        btnOut.disabled = true;
        statusSpan.textContent = 'Clocking out...';
        statusSpan.style.color = ''; // Reset color
        
        // Close capture window
        if (captureWindow && !captureWindow.closed) {
            captureWindow.close();
        }
        
        // Then record time out
        ajax('time_out.php', '', function (res) {
            if (res.status === 'success') {
                statusSpan.textContent = 'Timed out. Session ended.';
                attendanceId = null;
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
        });
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    function setClockedOutUI() {
        attendanceId = null;
        updateButtonState(false);
        statusSpan.textContent = 'Timed out. Session ended.';
        statusSpan.style.color = '';
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
                if (captureWindow && !captureWindow.closed) {
                    captureWindow.close();
                }
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
</script>
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
</body>
</html>
<?php 
} else { 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>


