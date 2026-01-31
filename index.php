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
    <div class="dash-sidebar">
        <div class="dash-brand">
            <h2>TaskFlow</h2>
            <span>Management System</span>
        </div>
        
        <nav class="dash-nav">
            <?php if($_SESSION['role'] == "employee"){ ?>
                <!-- Employee Nav -->
                <a href="index.php" class="dash-nav-item active">
                    <i class="fa fa-th-large"></i> Dashboard
                </a>
                <a href="my_task.php" class="dash-nav-item">
                    <i class="fa fa-check-square-o"></i> Tasks
                </a>
                <a href="my_subtasks.php" class="dash-nav-item">
                     <i class="fa fa-list-alt"></i> Subtasks
                </a>
                <a href="dtr.php" class="dash-nav-item">
                    <i class="fa fa-calendar"></i> Calendar
                </a>
                <a href="notifications.php" class="dash-nav-item">
                    <i class="fa fa-comment-o"></i> Messages
                </a>
                <a href="profile.php" class="dash-nav-item">
                    <i class="fa fa-user-o"></i> Profile
                </a>
            <?php } else { ?>
                <!-- Admin Nav -->
                <a href="index.php" class="dash-nav-item active">
                    <i class="fa fa-th-large"></i> Dashboard
                </a>
                <a href="tasks.php" class="dash-nav-item">
                    <i class="fa fa-check-square-o"></i> Tasks
                </a>
                <a href="create_task.php" class="dash-nav-item">
                    <i class="fa fa-plus-square-o"></i> Create Task
                </a>
                <a href="dtr.php" class="dash-nav-item">
                    <i class="fa fa-calendar"></i> Calendar
                </a>
                <a href="notifications.php" class="dash-nav-item">
                    <i class="fa fa-comment-o"></i> Messages
                </a>
                <a href="user.php" class="dash-nav-item">
                    <i class="fa fa-users"></i> Users
                </a>
                <a href="screenshots.php" class="dash-nav-item">
                    <i class="fa fa-camera"></i> Captures
                </a>
            <?php } ?>
        </nav>

        <div class="dash-sidebar-footer">
            <a href="logout.php" class="dash-logout">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        </div>
    </div>

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
                        Due: <?= htmlspecialchars($task['due_date'] ?? 'No Due Date') ?>
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
    let screenshotTimerId = null;
    let mediaStream = null;
    let isTimingOut = false; // Flag to prevent multiple simultaneous time out calls

    // Toggle button visibility based on state
    function updateButtonState(isTimeIn) {
        if (!btnIn || !btnOut) return;
        if (isTimeIn) {
            btnIn.style.display = 'none';
            btnOut.style.display = 'flex';
            btnOut.disabled = false;
            // Also enable time in button as resume button if needed?
            // For now just swap them.
        } else {
            btnIn.style.display = 'flex';
            btnIn.innerHTML = '<i class="fa fa-play"></i> Clock In'; // Reset text just in case
            btnOut.style.display = 'none';
            btnIn.disabled = false;
        }
    }

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
                    cb({status: 'error', message: 'Network error', status: xhr.status, raw: xhr.responseText});
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

    // Check if extension is available
    var extensionAvailable = false;
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

    if (btnIn) {
        btnIn.addEventListener('click', async function () {
            // Check if already timed in (restored from page load)
            var isAlreadyTimedIn = attendanceId !== null;
            
            // Check if extension is available
            if (extensionAvailable || window.screenshotExtensionAvailable) {
                // If not already timed in, do time in first
                if (!isAlreadyTimedIn) {
                    ajax('time_in.php', '', function (res) {
                        if (res.status === 'success') {
                            attendanceId = res.attendance_id || null;
                            statusSpan.textContent = 'Timed in. Extension will handle screenshots automatically.';
                            updateButtonState(true);
                            
                            // Tell extension to start capturing
                            window.postMessage({
                                type: 'REQUEST_SCREENSHOT',
                                attendanceId: attendanceId,
                                userId: currentUserId,
                                apiUrl: window.location.origin + window.location.pathname.replace('index.php', 'save_screenshot.php')
                            }, window.location.origin);
                        } else {
                            statusSpan.textContent = res.message || 'Error during time in';
                        }
                    });
                } else {
                    // Already timed in, just start extension
                    statusSpan.textContent = 'Starting screen capture...';
                    window.postMessage({
                        type: 'REQUEST_SCREENSHOT',
                        attendanceId: attendanceId,
                        userId: currentUserId,
                        apiUrl: window.location.origin + window.location.pathname.replace('index.php', 'save_screenshot.php')
                    }, window.location.origin);
                    updateButtonState(true);
                }
            } else {
                // Fallback to browser screen share - request permission when Time In is pressed
                statusSpan.textContent = 'Requesting screen access...';
                var stream = await requestScreenShare();
                
                if (!stream) {
                    statusSpan.textContent = 'Screen access denied. Please allow to continue.';
                    return;
                }
                
                // IMPORTANT: Stream is now stored in mediaStream variable globally
                // It will be reused for ALL subsequent screenshots without asking again
                console.log('Screen share granted. Stream stored. Will reuse for all screenshots.');
                
                // If not already timed in, do time in first
                if (!isAlreadyTimedIn) {
                    ajax('time_in.php', '', function (res) {
                        if (res.status === 'success') {
                            attendanceId = res.attendance_id || null;
                            statusSpan.textContent = 'Timed in. Screenshots will be taken automatically.';
                            updateButtonState(true);
                            // Start screenshot loop - mediaStream is already stored globally, will be reused
                            startScreenshotLoop();
                        } else {
                            statusSpan.textContent = res.message || 'Error during time in';
                            // Stop stream if time in failed
                            if (mediaStream) {
                                mediaStream.getTracks().forEach(function (t) { t.stop(); });
                                mediaStream = null;
                            }
                        }
                    });
                } else {
                    // Already timed in, just start screenshot loop
                    statusSpan.textContent = 'Timed in. Screenshots will be taken automatically.';
                    updateButtonState(true);
                    startScreenshotLoop();
                }
            }
        });
    }

    // Helper function to handle time out (used by both manual and automatic time out)
    function performTimeOut() {
        if (!attendanceId || isTimingOut) return; // Already timed out, not timed in, or already timing out
        
        isTimingOut = true; // Set flag to prevent multiple simultaneous calls
        
        ajax('time_out.php', '', function (res) {
            isTimingOut = false; // Reset flag
            
            if (res.status === 'success') {
                statusSpan.textContent = 'Timed out.';
                updateButtonState(false);
                attendanceId = null; // Clear attendance ID
                
                // Stop extension if it's running
                if (extensionAvailable || window.screenshotExtensionAvailable) {
                    window.postMessage({
                        type: 'STOP_SCREENSHOT'
                    }, window.location.origin);
                }
                
                stopScreenshotLoop();
            } else {
                statusSpan.textContent = res.message || 'Error during time out';
            }
        });
    }

    if (btnOut) {
        btnOut.addEventListener('click', function () {
            performTimeOut();
        });
    }

    // Check for active attendance on page load - only restore UI state, don't request screen share
    if (btnIn && btnOut) {
        ajax('check_attendance.php', '', function (res) {
            if (res.status === 'success' && res.has_active_attendance) {
                attendanceId = res.attendance_id || null;
                statusSpan.textContent = 'Timed in (restored). Please press Time In again to start screen sharing.';
                
                // UI Restoration:
                // Enable resume state
                btnIn.style.display = 'flex'; 
                btnIn.innerHTML = '<i class="fa fa-play"></i> Resume Tracking';
                btnOut.style.display = 'flex'; 
                btnOut.disabled = false;
                
            }
        }, 'GET');
    }

    const MIN_INTERVAL_SEC = 25; // minimum seconds between screenshots (random around 30 seconds)
    const MAX_INTERVAL_SEC = 35; // maximum seconds between screenshots (random around 30 seconds)

    async function requestScreenShare() {
        // If stream already exists and is active, return it immediately
        if (mediaStream) {
            var videoTrack = mediaStream.getVideoTracks()[0];
            if (videoTrack && videoTrack.readyState === 'live') {
                return mediaStream; // Stream is still active, reuse it
            } else {
                // Stream ended, clear it
                console.log('Stream ended, clearing...');
                mediaStream = null;
            }
        }

        // Only request new stream if we don't have one
        try {
            console.log('Requesting new screen share...');
            mediaStream = await navigator.mediaDevices.getDisplayMedia({
                video: { cursor: "always" },
                audio: false
            });
            
            // Listen for when user stops sharing manually
            var videoTrack = mediaStream.getVideoTracks()[0];
            videoTrack.addEventListener('ended', function() {
                console.log('User stopped screen sharing');
                mediaStream = null;
                stopScreenshotLoop();
                if (statusSpan) {
                     statusSpan.textContent = 'Screen sharing stopped. You are still timed in.';
                }
                // Do NOT automatically time out. Allow user to re-enable or time out manually.
            });
            
            console.log('Screen share granted, stream active');
            return mediaStream;
        } catch (e) {
            console.error('Screen share denied', e);
            if (statusSpan) {
                statusSpan.textContent = 'Screen share denied. Please allow to continue.';
            }
            return null;
        }
    }

    function getRandomDelayMs() {
        var minMs = MIN_INTERVAL_SEC * 1000; // convert seconds to milliseconds
        var maxMs = MAX_INTERVAL_SEC * 1000; // convert seconds to milliseconds
        return minMs + Math.random() * (maxMs - minMs);
    }

    async function takeScreenshotOnce() {
        // Don't take screenshots if not timed in (no attendanceId)
        if (!attendanceId) {
            stopScreenshotLoop();
            return;
        }

        // Use existing stream - don't request new one unless it's actually ended
        if (!mediaStream) {
            console.log('No active stream, cannot take screenshot');
            stopScreenshotLoop();
            if (statusSpan) {
                 statusSpan.textContent = 'Screen sharing stopped. You are still timed in.';
            }
            return;
        }

        // Check if stream is still active
        var videoTrack = mediaStream.getVideoTracks()[0];
        if (!videoTrack || videoTrack.readyState !== 'live') {
            console.log('Stream is not live, stopping...');
            mediaStream = null;
            stopScreenshotLoop();
            if (statusSpan) {
                 statusSpan.textContent = 'Screen sharing stopped. You are still timed in.';
            }
            return;
        }

        // Stream is active, use it for screenshot (reusing the same stream - no new permission needed)
        console.log('Taking screenshot using existing stream (no permission prompt)');
        var stream = mediaStream;
        var videoTrack = stream.getVideoTracks()[0];
        if (!window.ImageCapture) {
            console.error('ImageCapture API not supported in this browser.');
            return;
        }
        var imageCapture = new ImageCapture(videoTrack);

        try {
            var bitmap = await imageCapture.grabFrame();
            var canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(bitmap, 0, 0);
            var dataUrl = canvas.toDataURL('image/png');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_screenshot.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(
                'attendance_id=' + encodeURIComponent(attendanceId || '') +
                '&image=' + encodeURIComponent(dataUrl)
            );
        } catch (e) {
            console.error('Screenshot failed', e);
        }
    }

    function scheduleNextScreenshot() {
        var delay = getRandomDelayMs();
        screenshotTimerId = setTimeout(async function () {
            await takeScreenshotOnce();
            scheduleNextScreenshot();
        }, delay);
    }

    function startScreenshotLoop() {
        if (screenshotTimerId) return;
        scheduleNextScreenshot();
    }

    function stopScreenshotLoop() {
        if (screenshotTimerId) {
            clearTimeout(screenshotTimerId);
            screenshotTimerId = null;
        }
        if (mediaStream) {
            mediaStream.getTracks().forEach(function (t) { t.stop(); });
            mediaStream = null;
        }
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