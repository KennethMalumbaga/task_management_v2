<?php
    if (
        isset($_SESSION['role'], $_SESSION['username'])
        && $_SESSION['role'] === 'admin'
        && $_SESSION['username'] === 'admin'
    ) {
        $blockedPage = basename($_SERVER['PHP_SELF'] ?? 'workspace');
        $target = "maintenance_dashboard.php?restricted=1&page=" . urlencode($blockedPage);
        if (!headers_sent()) {
            header("Location: " . $target);
        } else {
            echo '<script>window.location.replace(' . json_encode($target) . ');</script>';
        }
        exit();
    }

    include_once "app/model/Message.php";
    include_once "app/model/GroupMessage.php";
    include_once "app/model/Notification.php";
    include_once "app/model/Task.php";
    include_once "app/model/user.php";
    require_once "app/helpers/notification.php";
    if (!function_exists('csrf_token')) {
        require_once "inc/csrf.php";
    }

    $dmUnread = countAllUnread($_SESSION['id'], $pdo);
    $grpUnread = count_all_group_unread($pdo, $_SESSION['id']);
    $totalUnread = $dmUnread + $grpUnread;

    $notifUnread = 0;
    try {
        $notifUnread = (int)count_notification($pdo, $_SESSION['id']);
    } catch (Throwable $e) {
        $notifUnread = 0;
    }

    $notificationReadCsrfToken = csrf_token('notification_read_action');
    $notificationReadAllCsrfToken = csrf_token('notification_read_all_action');
    $notifRows = get_all_my_notifications($pdo, $_SESSION['id']);
    if (!is_array($notifRows)) {
        $notifRows = [];
    }
    $notifPreview = array_slice($notifRows, 0, 8);

    $me = null;
    try {
        $me = get_user_by_id($pdo, $_SESSION['id']);
    } catch (Throwable $e) {
        $me = null;
    }

    $profileImage = 'img/user.png';
    if (!empty($me['profile_image']) && $me['profile_image'] !== 'default.png') {
        $candidate = __DIR__ . '/../uploads/' . $me['profile_image'];
        if (is_file($candidate)) {
            $profileImage = 'uploads/' . $me['profile_image'];
        }
    }
    $displayName = trim((string)($_SESSION['full_name'] ?? ($me['full_name'] ?? 'User')));
    if ($displayName === '') {
        $displayName = 'User';
    }
    $displayEmail = trim((string)($_SESSION['username'] ?? ($me['username'] ?? '')));
    $displayRole = ucfirst((string)($_SESSION['role'] ?? 'user'));

    $currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
    $currentRedirect = $currentPage;
    if (!empty($_SERVER['QUERY_STRING'])) {
        $currentRedirect .= '?' . $_SERVER['QUERY_STRING'];
    }
    $notificationReadAllLink = "app/notification-read-all.php?csrf_token="
        . urlencode($notificationReadAllCsrfToken)
        . "&redirect="
        . urlencode($currentRedirect);

    $topbarTitles = [
        'index.php' => 'Dashboard',
        'tasks.php' => 'Tasks',
        'my_task.php' => 'Tasks',
        'calendar.php' => 'Calendar',
        'messages.php' => 'Messages',
        'user.php' => 'Users',
        'invite-user.php' => 'Invites',
        'workspace-billing.php' => 'Billing',
        'groups.php' => 'Groups',
        'screenshots.php' => 'Captures',
        'profile.php' => 'Profile',
        'edit_profile.php' => 'Edit Profile',
        'notifications.php' => 'Notifications',
        'create_task.php' => 'Create Task',
        'edit-task-employee.php' => 'Task Details'
    ];
    $topbarTitle = $topbarTitles[$currentPage] ?? ucwords(str_replace(['-', '_', '.php'], [' ', ' ', ''], $currentPage));
    if ($topbarTitle === '') {
        $topbarTitle = 'Dashboard';
    }
?>

<!-- Mobile Navbar (Fixed Top) -->
<div class="mobile-navbar">
    <div class="mobile-brand">
        <img src="img/logo.png" alt="TaskFlow" class="brand-logo-mobile">
        <div class="mobile-brand-text">
            <h2>TaskFlow</h2>
            <span>Management System</span>
        </div>
    </div>
    
    <div style="display: flex; align-items: center; gap: 15px;">
        <a href="messages.php" class="mobile-msg-icon">
            <i class="fa fa-commenting-o"></i>
            <?php if($totalUnread > 0){ ?>
                <span class="mobile-unread-badge"><?=$totalUnread?></span>
            <?php } ?>
        </a>
        <button class="mobile-toggle-btn" onclick="toggleSidebar()">
            <i class="fa fa-bars"></i>
        </button>
    </div>
</div>

<!-- Overlay for mobile when sidebar is open -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<script>
    function toggleSidebar() {
        document.querySelector('.dash-sidebar').classList.toggle('show-sidebar');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }
</script>

<div class="dash-sidebar">
    <div class="dash-brand">
        <img src="img/logo.png" alt="TaskFlow" class="brand-logo">
        <div class="brand-content">
            <h2>TaskFlow</h2>
            <span>Management System</span>
        </div>
        <button class="mobile-close-btn" onclick="toggleSidebar()">
            <i class="fa fa-times"></i>
        </button>
    </div>
    
    <nav class="dash-nav">
        <?php 
           // Helper to check active state
           function isActive($page) {
               $current = basename($_SERVER['PHP_SELF']);
               return $current === $page ? 'active' : '';
           }
        ?>

        <?php if($_SESSION['role'] == "employee"){ ?>
            <!-- Employee Nav -->
            <a href="index.php" class="dash-nav-item <?= isActive('index.php') ?>">
                <i class="fa fa-th-large"></i> Dashboard
            </a>
            <a href="my_task.php" class="dash-nav-item <?= isActive('my_task.php') ?>">
                <i class="fa fa-check-square-o"></i> Tasks
            </a>
            <!-- Subtasks link removed -->
            <a href="calendar.php" class="dash-nav-item <?= isActive('calendar.php') ?>">
                <i class="fa fa-calendar"></i> Calendar
            </a>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($totalUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$totalUnread?></span>
                <?php } ?>
            </a>
        <?php } else { ?>
            <!-- Admin Nav -->
            <a href="index.php" class="dash-nav-item <?= isActive('index.php') ?>">
                <i class="fa fa-th-large"></i> Dashboard
            </a>
            <a href="tasks.php" class="dash-nav-item <?= isActive('tasks.php') ?>">
                <i class="fa fa-check-square-o"></i> Tasks
            </a>
            <!-- Keep Create Task? Maybe in Tasks page as action -->
            <a href="calendar.php" class="dash-nav-item <?= isActive('calendar.php') ?>">
                <i class="fa fa-calendar"></i> Calendar
            </a>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($totalUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$totalUnread?></span>
                <?php } ?>
            </a>
            <a href="user.php" class="dash-nav-item <?= isActive('user.php') ?>">
                <i class="fa fa-users"></i> Users
            </a>
            <a href="invite-user.php" class="dash-nav-item <?= isActive('invite-user.php') ?>">
                <i class="fa fa-user-plus"></i> Invites
            </a>
            <a href="workspace-billing.php" class="dash-nav-item <?= isActive('workspace-billing.php') ?>">
                <i class="fa fa-credit-card"></i> Billing
            </a>
            <a href="groups.php" class="dash-nav-item <?= isActive('groups.php') ?>">
                <i class="fa fa-object-group"></i> Groups
            </a>
            <a href="screenshots.php" class="dash-nav-item <?= isActive('screenshots.php') ?>">
                <i class="fa fa-camera"></i> Captures
            </a>
        <?php } ?>
    </nav>
</div>

<!-- Desktop Content Topbar -->
<div class="dash-content-topbar">
    <h1 class="dash-content-topbar-title"><?= htmlspecialchars($topbarTitle) ?></h1>
    <div class="dash-top-utility">
    <div class="dash-top-notif-menu" id="dashTopNotifMenu">
        <button type="button" class="dash-top-bell" id="dashTopNotifTrigger" title="Notifications" aria-label="Notifications" aria-expanded="false">
            <i class="fa fa-bell-o"></i>
            <?php if($notifUnread > 0){ ?>
                <span class="dash-top-badge"><?=$notifUnread?></span>
            <?php } ?>
        </button>
        <div class="dash-top-notif-dropdown" id="dashTopNotifDropdown">
            <div class="dash-top-notif-head">
                <div>
                    <div class="dash-top-notif-title">Notifications</div>
                    <div class="dash-top-notif-sub"><?= (int)$notifUnread ?> unread</div>
                </div>
                <a href="<?= htmlspecialchars($notificationReadAllLink, ENT_QUOTES) ?>" class="dash-top-notif-head-link">Read all</a>
            </div>
            <div class="dash-top-notif-list">
                <?php if (empty($notifPreview)) { ?>
                    <div class="dash-top-notif-empty">No notifications yet.</div>
                <?php } else { ?>
                    <?php foreach ($notifPreview as $notif) {
                        $taskId = tm_get_notification_task_id($pdo, $notif);
                        $notifLink = "app/notification-read.php?notification_id=" . urlencode((string)$notif['id']);
                        if ($taskId) {
                            $notifLink .= "&task_id=" . urlencode((string)$taskId);
                        }
                        $notifLink .= "&csrf_token=" . urlencode($notificationReadCsrfToken);
                        $notifType = trim((string)($notif['type'] ?? 'Notification'));
                        $notifMessage = trim((string)($notif['message'] ?? ''));
                        $notifDate = trim((string)($notif['date'] ?? ''));
                        $isUnread = tm_notification_is_unread($notif);
                    ?>
                        <a href="<?= htmlspecialchars($notifLink, ENT_QUOTES) ?>" class="dash-top-notif-item <?= $isUnread ? 'unread' : '' ?>">
                            <div class="dash-top-notif-type"><?= htmlspecialchars($notifType) ?></div>
                            <div class="dash-top-notif-msg"><?= htmlspecialchars($notifMessage) ?></div>
                            <div class="dash-top-notif-meta">
                                <span><?= htmlspecialchars($notifDate) ?></span>
                                <?php if ($isUnread) { ?><span class="dash-top-notif-dot"></span><?php } ?>
                            </div>
                        </a>
                    <?php } ?>
                <?php } ?>
            </div>
            <div class="dash-top-notif-foot">
                <a href="notifications.php">View all notifications</a>
            </div>
        </div>
    </div>

    <div class="dash-top-profile-menu" id="dashTopProfileMenu">
        <button type="button" class="dash-top-profile-trigger" id="dashTopProfileTrigger" aria-label="Open profile menu" aria-expanded="false">
            <img src="<?= htmlspecialchars($profileImage, ENT_QUOTES) ?>" alt="Profile">
        </button>
        <div class="dash-top-profile-dropdown" id="dashTopProfileDropdown">
            <div class="dash-top-profile-head">
                <img src="<?= htmlspecialchars($profileImage, ENT_QUOTES) ?>" alt="Profile">
                <div>
                    <div class="dash-top-profile-name"><?= htmlspecialchars($displayName) ?></div>
                    <?php if($displayEmail !== '') { ?>
                        <div class="dash-top-profile-email"><?= htmlspecialchars($displayEmail) ?></div>
                    <?php } ?>
                    <div class="dash-top-profile-role"><?= htmlspecialchars($displayRole) ?></div>
                </div>
            </div>
            <a href="profile.php" class="dash-top-profile-link">
                <i class="fa fa-user-o"></i> My Profile
            </a>
            <a href="logout.php" class="dash-top-profile-link danger js-logout-link">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        </div>
    </div>
    </div>
</div>

<div id="logoutConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; width:min(92vw, 360px); border-radius:12px; padding:22px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
        <div style="width:46px; height:46px; margin:0 auto 12px; border-radius:50%; background:#FEF3C7; color:#B45309; display:flex; align-items:center; justify-content:center; font-size:18px;">
            <i class="fa fa-sign-out"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:20px; color:#111827;">Logout?</h3>
        <p style="margin:0 0 16px; font-size:14px; color:#6B7280;">Are you sure you want to logout?</p>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button type="button" id="logoutCancelBtn" style="border:none; border-radius:8px; background:#F3F4F6; color:#374151; padding:10px 16px; font-weight:600; cursor:pointer;">Cancel</button>
            <button type="button" id="logoutConfirmBtn" style="border:none; border-radius:8px; background:#EF4444; color:#fff; padding:10px 16px; font-weight:600; cursor:pointer;">Yes, Logout</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var trigger = document.getElementById('dashTopNotifTrigger');
        var dropdown = document.getElementById('dashTopNotifDropdown');
        var profileDropdown = document.getElementById('dashTopProfileDropdown');
        var profileTrigger = document.getElementById('dashTopProfileTrigger');
        if (!trigger || !dropdown) return;

        function closeMenu() {
            dropdown.classList.remove('show');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (profileDropdown) {
                profileDropdown.classList.remove('show');
            }
            if (profileTrigger) {
                profileTrigger.setAttribute('aria-expanded', 'false');
            }
            var willShow = !dropdown.classList.contains('show');
            dropdown.classList.toggle('show', willShow);
            trigger.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });
    })();

    (function () {
        var trigger = document.getElementById('dashTopProfileTrigger');
        var dropdown = document.getElementById('dashTopProfileDropdown');
        var notifDropdown = document.getElementById('dashTopNotifDropdown');
        var notifTrigger = document.getElementById('dashTopNotifTrigger');
        if (!trigger || !dropdown) return;

        function closeMenu() {
            dropdown.classList.remove('show');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (notifDropdown) {
                notifDropdown.classList.remove('show');
            }
            if (notifTrigger) {
                notifTrigger.setAttribute('aria-expanded', 'false');
            }
            var willShow = !dropdown.classList.contains('show');
            dropdown.classList.toggle('show', willShow);
            trigger.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });
    })();

    (function () {
        var links = document.querySelectorAll('a.js-logout-link');
        if (!links.length) return;

        var modal = document.getElementById('logoutConfirmModal');
        var cancelBtn = document.getElementById('logoutCancelBtn');
        var confirmBtn = document.getElementById('logoutConfirmBtn');
        var pendingHref = 'logout.php';

        function openModal(href) {
            pendingHref = href || 'logout.php';
            if (modal) modal.style.display = 'flex';
        }

        function closeModal() {
            if (modal) modal.style.display = 'none';
        }

        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                openModal(link.getAttribute('href'));
            });
        });

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                try {
                    localStorage.setItem('taskflow_force_stop_capture', String(Date.now()));
                    for (var i = sessionStorage.length - 1; i >= 0; i--) {
                        var key = sessionStorage.key(i);
                        if (key && key.indexOf('taskflow_nav_clockin_warned_once_user_') === 0) {
                            sessionStorage.removeItem(key);
                        }
                    }
                } catch (e) {}
                window.location.href = pendingHref;
            });
        }
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }
    })();
</script>

<?php include_once __DIR__ . "/toast.php"; ?>
