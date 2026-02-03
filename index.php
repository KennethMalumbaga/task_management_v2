<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

    include "DB_connection.php";
    include "app/Model/Task.php";
    include "app/Model/User.php";
    include "app/Model/Subtask.php";

    // --- DATA FETCHING FOR DASHBOARD ---
    
    // 1. Stats and Counts
    if ($_SESSION['role'] == "admin") {
        $num_task = count_tasks($pdo);
        $completed = count_completed_tasks($pdo);
        $num_users = count_users($pdo); // Employees
        $avg_rating = "4.3"; // Mock data as per design
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
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <!-- Top Section: Time Tracker & Welcome -->
        <div class="dash-top-grid">
            
            <!-- Time Tracker Card -->
            <div class="dash-card">
                <div class="time-tracker-header">
                    <div class="time-tracker-title">
                        <i class="fa fa-clock-o" style="color: #4F46E5;"></i> 
                        Time Tracker
                    </div>
                    <div style="color: #9CA3AF;">
                        <i class="fa fa-camera"></i>
                    </div>
                </div>

                <?php if ($_SESSION['role'] !== 'admin') { ?>
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
                <?php } else { ?>
                     <!-- Admin View -->
                     <button class="btn-clock-in" style="opacity: 0.5; cursor: default;">
                        <i class="fa fa-play"></i> Admin View Only
                    </button>
                     <div class="screenshot-info">
                        <i class="fa fa-info-circle"></i>
                        <span>Tracking is active for employees.</span>
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

        <!-- Tasks Section -->
        <div>
            <div class="tasks-section-header">
                <h3>Tasks</h3>
                <?php if ($_SESSION['role'] == "admin") { ?>
                    <a href="create_task.php" class="btn-create-task">
                        <i class="fa fa-plus"></i> Create Task
                    </a>
                <?php } ?>
            </div>

            <div class="task-list">
                <?php if (!empty($recent_tasks) && count($recent_tasks) > 0) { 
                    foreach($recent_tasks as $task) { 
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
                <div class="task-card" style="background: white; border-radius: 12px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #E5E7EB; position: relative;">
                    
                    <!-- Edit/Action Button -->
                     <?php if ($_SESSION['role'] == "admin") { ?>
                        <a href="edit-task.php?id=<?= $task['id'] ?>" style="position: absolute; top: 24px; right: 24px; color: #9CA3AF; text-decoration: none; font-size: 14px;">
                            <i class="fa fa-pencil"></i>
                        </a>
                     <?php } else { ?>
                        <?php if ($task['status'] != 'completed') { ?>
                            <?php if ($task['status'] == 'in_progress') { ?>
                                <a href="#" class="btn-task-action btn-complete" style="position: absolute; top: 24px; right: 24px; font-size: 13px;">
                                    <i class="fa fa-check"></i> Complete
                                </a>
                            <?php } else { ?>
                                 <span class="badge-pending" style="position: absolute; top: 24px; right: 24px; padding: 4px 8px; border-radius: 4px; font-size: 12px; opacity: 0.7;">Pending</span>
                            <?php } ?>
                        <?php } ?>
                     <?php } ?>

                    <!-- Header -->
                    <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-chevron-right" style="color: #6B7280; font-size: 10px;"></i>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;"><?= htmlspecialchars($task['title']) ?></h3>
                        
                        <?php if($isSubmittedForReview) { ?>
                            <span style="background: #F3E8FF; color: #7E22CE; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: lowercase;">submitted_for_review</span>
                        <?php } else { ?>
                            <span class="badge <?= $badgeClass ?>"><?= $statusDisplay ?></span>
                        <?php } ?>
                    </div>

                    <!-- Description -->
                    <div style="color: #6B7280; font-size: 14px; margin-bottom: 24px; padding-left: 20px;">
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
                                <div style="font-size: 11px; color: #F59E0B; font-weight: 500; display: flex; gap: 10px;">
                                    <?php $lStats = get_user_rating_stats($pdo, $leader['user_id']); ?>
                                    <span><i class="fa fa-star"></i> <?= $lStats['avg'] ?>/5</span>

                                    <?php $lCollab = get_collaborative_scores_by_user($pdo, $leader['user_id']); ?>
                                    <span title="Collaborative Score" style="color: #8B5CF6;"><i class="fa fa-users"></i> Collab: <?= $lCollab['avg'] ?>/5</span>
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
                                        <div style="font-size: 10px; color: #F59E0B; font-weight: 500; display: flex; flex-direction: column;">
                                            <?php $mStats = get_user_rating_stats($pdo, $member['user_id']); ?>
                                            <span><i class="fa fa-star"></i> <?= $mStats['avg'] ?>/5</span>

                                            <?php $mCollab = get_collaborative_scores_by_user($pdo, $member['user_id']); ?>
                                            <span title="Collaborative Score" style="color: #8B5CF6;"><i class="fa fa-users"></i> Collab: <?= $mCollab['avg'] ?>/5</span>
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
                <?php } 
                } else { ?>
                    <div class="task-item" style="text-align: center; color: #9CA3AF;">
                        No recent tasks found.
                    </div>
                <?php } ?>
            </div>
            
            <div style="margin-top: 15px; text-align: center;">
                 <a href="<?= ($_SESSION['role']=='admin'?'tasks.php':'my_task.php') ?>" style="color: #4F46E5; text-decoration: none; font-size: 14px; font-weight: 500;">
                     View All Tasks <i class="fa fa-arrow-right"></i>
                 </a>
            </div>
        </div>

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
                    <span><?= $avg_rating ?></span>
                </div>
                <div class="stat-icon icon-yellow">
                    <i class="fa fa-star-o"></i>
                </div>
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

    // On page load, check for active attendance
    if (btnIn && btnOut) {
        ajax('check_attendance.php', '', function (res) {
            if (res.status === 'success' && res.has_active_attendance) {
                attendanceId = res.attendance_id || null;
                
                // Always show Timed In state (Clock Out button) if DB says we are active.
                // This persists across page refreshes/navigation.
                updateButtonState(true);
                statusSpan.textContent = 'Timed in. Monitoring active.';
            }
        }, 'GET');
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