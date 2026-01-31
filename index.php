<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

    include "DB_connection.php";
    include "app/Model/Task.php";
    include "app/Model/User.php";

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
        $avg_rating = "4.3"; 
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
                <h3>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>!</h3>
                <div class="welcome-role">Role: <?= ucfirst($_SESSION['role']) ?></div>
                <div style="margin-top: 20px; font-size: 13px; color: #6B7280; line-height: 1.6;">
                    You have <b><?= $num_task - $completed ?></b> active tasks remaining effectively. <br>
                    Keep up the good work!
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
                        if ($task['status'] == 'in_progress') $badgeClass = "badge-in_progress";
                        if ($task['status'] == 'completed') $badgeClass = "badge-completed";
                ?>
                <div class="task-item">
                    <div class="task-header">
                         <i class="fa fa-chevron-right" style="font-size: 10px; color: #9CA3AF;"></i>
                         <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                         <span class="task-badge <?= $badgeClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $task['status'])) ?></span>
                    </div>
                    
                    <div class="task-desc">
                        <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 100, "...")) ?>
                    </div>

                    <div class="task-meta">
                        Due: <?= empty($task['due_date']) ? 'No Due Date' : date("F j, Y", strtotime($task['due_date'])) ?>
                    </div>
                    
                    <div class="task-actions">
                         <?php if ($_SESSION['role'] == "admin") { ?>
                            <a href="edit-task.php?id=<?= $task['id'] ?>" class="btn-task-action" style="background: #F3F4F6; color: #374151;">
                                <i class="fa fa-pencil"></i> Edit
                            </a>
                         <?php } else { ?>
                            <?php if ($task['status'] != 'completed') { ?>
                                <?php if ($task['status'] == 'in_progress') { ?>
                                    <a href="#" class="btn-task-action btn-complete">
                                        <i class="fa fa-check"></i> Complete
                                    </a>
                                    <a href="#" class="btn-task-action btn-pause">
                                        <i class="fa fa-pause"></i> Pause
                                    </a>
                                <?php } else { ?>
                                     <a href="#" class="btn-task-action btn-start">
                                        <i class="fa fa-play"></i> Start
                                    </a>
                                <?php } ?>
                            <?php } ?>
                         <?php } ?>
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

<!-- SCRIPTS PRESERVED FROM ORIGINAL -->
<script type="text/javascript">
    // Store user ID from PHP session
    var currentUserId = <?= isset($_SESSION['id']) ? $_SESSION['id'] : 'null' ?>;

    // Attendance + Screenshot logic (employees)
    const btnIn = document.getElementById('btnTimeIn');
    const btnOut = document.getElementById('btnTimeOut');
    const statusSpan = document.getElementById('attendanceStatus');
    let attendanceId = null;
    let extensionAvailable = false;

    // Listen for extension ready event
    window.addEventListener('screenshotExtensionReady', function() {
        extensionAvailable = true;
        console.log('Screenshot extension detected');
    });

    // Check for extension after page load
    setTimeout(function() {
        if (window.screenshotExtensionAvailable) {
            extensionAvailable = true;
        }
    }, 1000);

    // Toggle button visibility based on state
    function updateButtonState(isTimedIn) {
        if (!btnIn || !btnOut) return;
        if (isTimedIn) {
            btnIn.style.display = 'none';
            btnOut.style.display = 'flex';
            btnOut.disabled = false;
        } else {
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

    // Listen for extension responses
    window.addEventListener('message', function(event) {
        if (event.origin !== window.location.origin) return;
        
        if (event.data.type === 'SCREENSHOT_RESPONSE') {
            if (event.data.status === 'started') {
                statusSpan.textContent = 'Screen capture active. Taking screenshots...';
            } else {
                statusSpan.textContent = 'Screen capture failed. Please try again.';
            }
        } else if (event.data.type === 'CAPTURE_STATUS') {
            // Handle capture status check response
            if (event.data.isCapturing) {
                attendanceId = event.data.attendanceId;
                statusSpan.textContent = 'Screen capture active (restored).';
                updateButtonState(true);
            }
        }
    });

    // Clock In Handler
    if (btnIn) {
        btnIn.addEventListener('click', async function () {
            btnIn.disabled = true;
            statusSpan.textContent = 'Requesting screen share...';
            
            if (extensionAvailable || window.screenshotExtensionAvailable) {
                // Extension flow: Request screen share first (extension handles the prompt)
                // Then time_in is called
                ajax('time_in.php', '', function (res) {
                    if (res.status === 'success') {
                        attendanceId = res.attendance_id || null;
                        
                        // Now start screen capture via extension
                        window.postMessage({
                            type: 'REQUEST_SCREENSHOT',
                            attendanceId: attendanceId,
                            userId: currentUserId,
                            apiUrl: window.location.origin + window.location.pathname.replace('index.php', 'save_screenshot.php')
                        }, window.location.origin);
                        
                        statusSpan.textContent = 'Timed in. Starting screen capture...';
                        updateButtonState(true);
                    } else {
                        statusSpan.textContent = res.message || 'Error during time in';
                        btnIn.disabled = false;
                    }
                });
            } else {
                // No extension - show warning
                statusSpan.textContent = 'Extension not installed. Please install the Employee Screenshot Monitor extension.';
                btnIn.disabled = false;
            }
        });
    }

    // Clock Out Handler
    if (btnOut) {
        btnOut.addEventListener('click', function () {
            btnOut.disabled = true;
            statusSpan.textContent = 'Clocking out...';
            
            // Stop screen capture first
            if (extensionAvailable || window.screenshotExtensionAvailable) {
                window.postMessage({
                    type: 'STOP_SCREENSHOT'
                }, window.location.origin);
            }
            
            // Then record time out
            ajax('time_out.php', '', function (res) {
                if (res.status === 'success') {
                    statusSpan.textContent = 'Timed out. Screen capture stopped.';
                    attendanceId = null;
                    updateButtonState(false);
                } else {
                    statusSpan.textContent = res.message || 'Error during time out';
                    btnOut.disabled = false;
                }
            });
        });
    }

    // On page load, check for active attendance
    if (btnIn && btnOut) {
        ajax('check_attendance.php', '', function (res) {
            if (res.status === 'success' && res.has_active_attendance) {
                attendanceId = res.attendance_id || null;
                updateButtonState(true);
                
                // Check if extension is still capturing
                if (extensionAvailable || window.screenshotExtensionAvailable) {
                    window.postMessage({
                        type: 'CHECK_CAPTURE_STATUS'
                    }, window.location.origin);
                    statusSpan.textContent = 'Timed in. Checking screen capture status...';
                } else {
                    statusSpan.textContent = 'Timed in (restored). Install extension to resume screen capture.';
                }
            }
        }, 'GET');
    }
</script>
</body>
</html>
<?php 
} else { 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>