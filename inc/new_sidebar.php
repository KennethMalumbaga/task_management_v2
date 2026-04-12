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

    if (!function_exists('tenant_workspace_requires_payment')) {
        require_once __DIR__ . "/tenant.php";
    }

    if (isset($_SESSION['role'], $_SESSION['id']) && isset($pdo)) {
        $currentPageForBillingGate = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        $allowedBillingPages = ['workspace-billing.php', 'logout.php'];
        $orgIdForBillingGate = tenant_get_current_org_id();
        $isCurrentUserSuperAdmin = $_SESSION['role'] === 'admin' && (string)($_SESSION['username'] ?? '') === 'admin';
        if ($orgIdForBillingGate && !$isCurrentUserSuperAdmin) {
            $workspaceAccess = tenant_workspace_access_state(
                $pdo,
                (int)$orgIdForBillingGate,
                $_SESSION['role'] === 'admin'
            );

            if (
                !in_array($currentPageForBillingGate, $allowedBillingPages, true)
                && !empty($workspaceAccess['should_route_to_billing'])
            ) {
                $target = "workspace-billing.php?error="
                    . urlencode((string)($workspaceAccess['billing_gate']['reason'] ?? $workspaceAccess['message']));
                if (!headers_sent()) {
                    header("Location: " . $target);
                } else {
                    echo '<script>window.location.replace(' . json_encode($target) . ');</script>';
                }
                exit();
            }

            if (
                empty($workspaceAccess['can_access_workspace'])
                && empty($workspaceAccess['should_route_to_billing'])
            ) {
                $target = "logout.php?error=" . urlencode((string)($workspaceAccess['message'] ?? tenant_workspace_inactive_message()));
                if (!headers_sent()) {
                    header("Location: " . $target);
                } else {
                    echo '<script>window.location.replace(' . json_encode($target) . ');</script>';
                }
                exit();
            }
        }
    }

    include_once "app/model/Message.php";
    include_once "app/model/GroupMessage.php";
    include_once "app/model/Notification.php";
    include_once "app/model/Task.php";
    include_once "app/model/user.php";
    require_once "app/helpers/notification.php";
    require_once "app/helpers/subscription_reminder.php";
    if (!function_exists('csrf_token')) {
        require_once "inc/csrf.php";
    }

    if (
        isset($_SESSION['role'], $_SESSION['id'])
        && $_SESSION['role'] === 'admin'
        && function_exists('tm_dispatch_workspace_subscription_reminder')
    ) {
        $currentAdminId = (int)$_SESSION['id'];
        $currentOrgId = tenant_get_current_org_id();
        $isCurrentUserSuperAdmin = false;

        try {
            $isCurrentUserSuperAdmin = is_super_admin($currentAdminId, $pdo);
        } catch (Throwable $e) {
            $isCurrentUserSuperAdmin = false;
        }

        if ($currentOrgId && !$isCurrentUserSuperAdmin) {
            tm_dispatch_workspace_subscription_reminder($pdo, (int)$currentOrgId, $currentAdminId, 15);
        }
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
    $presenceHeartbeatCsrfToken = csrf_token('presence_heartbeat');
    $notifRows = get_all_my_notifications($pdo, $_SESSION['id']);
    if (!is_array($notifRows)) {
        $notifRows = [];
    }
    $notifPreview = array_slice($notifRows, 0, 8);
    $notificationNowTs = tm_notification_reference_now($pdo);

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
        'timeline.php' => 'Timeline',
        'user.php' => 'Users',
        'invite-user.php' => 'Invites',
        'workspace-billing.php' => 'Billing',
        'workspace-settings.php' => 'Settings',
        'groups.php' => 'Groups',
        'screenshots.php' => 'Captures',
        'reports.php' => 'Reports',
        'payroll.php' => 'Payroll',
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

    include_once __DIR__ . "/workspace_theme_style.php";
?>

<?php include_once __DIR__ . "/loading_screen.php"; ?>

<!-- Mobile Navbar (Fixed Top) -->
<div class="mobile-navbar">
    <div class="mobile-brand">
        <img src="img/logo.png" alt="TaskFlow" class="brand-logo-mobile">
        <div class="mobile-brand-text">
            <h2>TaskFlow</h2>
            <span>Management System</span>
        </div>
    </div>
    
    <div class="mobile-top-actions">
        <button type="button" class="mobile-icon-btn" id="mobileTopNotifTrigger" title="Notifications" aria-label="Notifications" aria-expanded="false">
            <i class="fa fa-bell-o"></i>
            <?php if($notifUnread > 0){ ?>
                <span class="mobile-unread-badge"><?=$notifUnread?></span>
            <?php } ?>
        </button>
        <a href="messages.php" class="mobile-msg-icon" aria-label="Messages">
            <i class="fa fa-commenting-o"></i>
            <?php if($totalUnread > 0){ ?>
                <span class="mobile-unread-badge"><?=$totalUnread?></span>
            <?php } ?>
        </a>
        <button type="button" class="mobile-profile-trigger" id="mobileTopProfileTrigger" aria-label="Open profile menu" aria-expanded="false">
            <img src="<?= htmlspecialchars($profileImage, ENT_QUOTES) ?>" alt="Profile">
        </button>
        <button class="mobile-toggle-btn" onclick="toggleSidebar()" aria-label="Open menu">
            <i class="fa fa-bars"></i>
        </button>
    </div>
</div>

<div class="mobile-top-notif-dropdown" id="mobileTopNotifDropdown">
    <div class="dash-top-notif-head">
        <div>
            <div class="dash-top-notif-title">Notifications</div>
            <div class="dash-top-notif-sub"><?= (int)$notifUnread ?> unread</div>
        </div>
        <a href="<?= htmlspecialchars($notificationReadAllLink, ENT_QUOTES) ?>" class="dash-top-notif-head-link">Mark all read</a>
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
                $notifWhen = tm_notification_time_ago($notif, $notificationNowTs);
                $isUnread = tm_notification_is_unread($notif);
            ?>
                <a href="<?= htmlspecialchars($notifLink, ENT_QUOTES) ?>" class="dash-top-notif-item <?= $isUnread ? 'unread' : '' ?>">
                    <div class="dash-top-notif-type"><?= htmlspecialchars($notifType) ?></div>
                    <div class="dash-top-notif-msg"><?= htmlspecialchars($notifMessage) ?></div>
                    <div class="dash-top-notif-meta">
                        <span><?= htmlspecialchars($notifWhen) ?></span>
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

<div class="mobile-top-profile-dropdown" id="mobileTopProfileDropdown">
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
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
        <a href="workspace-settings.php" class="dash-top-profile-link">
            <i class="fa fa-cog"></i> Settings
        </a>
    <?php } ?>
    <a href="logout.php" class="dash-top-profile-link danger js-logout-link">
        <i class="fa fa-sign-out"></i> Logout
    </a>
</div>

<!-- Overlay for mobile when sidebar is open -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<script>
    function closeMobileTopMenus() {
        var notifDropdown = document.getElementById('mobileTopNotifDropdown');
        var notifTrigger = document.getElementById('mobileTopNotifTrigger');
        var profileDropdown = document.getElementById('mobileTopProfileDropdown');
        var profileTrigger = document.getElementById('mobileTopProfileTrigger');

        if (notifDropdown) notifDropdown.classList.remove('show');
        if (profileDropdown) profileDropdown.classList.remove('show');
        if (notifTrigger) notifTrigger.setAttribute('aria-expanded', 'false');
        if (profileTrigger) profileTrigger.setAttribute('aria-expanded', 'false');
    }

    function toggleSidebar() {
        closeMobileTopMenus();
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
            <div class="dash-nav-section-label">MAIN</div>
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
            <a href="reports.php" class="dash-nav-item <?= isActive('reports.php') ?>">
                <i class="fa fa-clock-o"></i> DTR
            </a>
            <a href="timeline.php" class="dash-nav-item <?= isActive('timeline.php') ?>">
                <i class="fa fa-line-chart"></i> Timeline
            </a>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($totalUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$totalUnread?></span>
                <?php } ?>
            </a>
        <?php } else { ?>
            <!-- Admin Nav -->
            <div class="dash-nav-section-label">MAIN</div>
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
            <a href="timeline.php" class="dash-nav-item <?= isActive('timeline.php') ?>">
                <i class="fa fa-line-chart"></i> Timeline
            </a>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($totalUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$totalUnread?></span>
                <?php } ?>
            </a>
            <div class="dash-nav-section-label">MANAGE</div>
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
            <div class="dash-nav-section-label">MONITOR</div>
            <a href="screenshots.php" class="dash-nav-item <?= isActive('screenshots.php') ?>">
                <i class="fa fa-camera"></i> Captures
            </a>
            <a href="reports.php" class="dash-nav-item <?= isActive('reports.php') ?>">
                <i class="fa fa-bar-chart"></i> Reports
            </a>
            <a href="payroll.php" class="dash-nav-item <?= isActive('payroll.php') ?>">
                <i class="fa fa-money"></i> Payroll
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
                        $notifWhen = tm_notification_time_ago($notif, $notificationNowTs);
                        $isUnread = tm_notification_is_unread($notif);
                    ?>
                        <a href="<?= htmlspecialchars($notifLink, ENT_QUOTES) ?>" class="dash-top-notif-item <?= $isUnread ? 'unread' : '' ?>">
                            <div class="dash-top-notif-type"><?= htmlspecialchars($notifType) ?></div>
                            <div class="dash-top-notif-msg"><?= htmlspecialchars($notifMessage) ?></div>
                            <div class="dash-top-notif-meta">
                                <span><?= htmlspecialchars($notifWhen) ?></span>
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
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
                <a href="workspace-settings.php" class="dash-top-profile-link">
                    <i class="fa fa-cog"></i> Settings
                </a>
            <?php } ?>
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
        var notifTrigger = document.getElementById('mobileTopNotifTrigger');
        var notifDropdown = document.getElementById('mobileTopNotifDropdown');
        var profileTrigger = document.getElementById('mobileTopProfileTrigger');
        var profileDropdown = document.getElementById('mobileTopProfileDropdown');
        if (!notifTrigger || !notifDropdown || !profileTrigger || !profileDropdown) return;

        function closeNotif() {
            notifDropdown.classList.remove('show');
            notifTrigger.setAttribute('aria-expanded', 'false');
        }

        function closeProfile() {
            profileDropdown.classList.remove('show');
            profileTrigger.setAttribute('aria-expanded', 'false');
        }

        function closeMenus() {
            closeNotif();
            closeProfile();
        }

        notifTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            closeProfile();
            var willShow = !notifDropdown.classList.contains('show');
            notifDropdown.classList.toggle('show', willShow);
            notifTrigger.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });

        profileTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            closeNotif();
            var willShow = !profileDropdown.classList.contains('show');
            profileDropdown.classList.toggle('show', willShow);
            profileTrigger.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            var clickedOutsideNotif = !notifDropdown.contains(e.target) && !notifTrigger.contains(e.target);
            var clickedOutsideProfile = !profileDropdown.contains(e.target) && !profileTrigger.contains(e.target);
            if (clickedOutsideNotif && clickedOutsideProfile) {
                closeMenus();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenus();
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

<?php
    $sharedIdleRole = (string)($_SESSION['role'] ?? '');
?>
<?php if ($sharedIdleRole === 'employee') { ?>
<div id="adminClockOutNoticeModal" onclick="if (event.target === this) closeAdminClockOutNoticeModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2201; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div style="background:white; padding:30px; border-radius:14px; width:min(420px, calc(100vw - 32px)); text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="width:50px; height:50px; background:#FEE2E2; color:#DC2626; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
            <i class="fa fa-user-times"></i>
        </div>
        <h3 style="margin:0 0 10px; color:#111827;">Clocked Out by Admin</h3>
        <p id="adminClockOutNoticeMessage" style="color:#6B7280; font-size:14px; margin-bottom:18px; line-height:1.5;">
            You were clocked out by an admin.
        </p>
        <div style="text-align:left; background:#F9FAFB; border:1px solid #E5E7EB; border-radius:10px; padding:14px; margin-bottom:22px;">
            <div style="font-size:12px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;">Remark</div>
            <div id="adminClockOutNoticeRemark" style="font-size:14px; color:#111827; line-height:1.6; white-space:pre-wrap;">No remark was provided.</div>
        </div>
        <div style="display:flex; justify-content:center;">
            <button type="button" onclick="closeAdminClockOutNoticeModal()" style="background:#DC2626; color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">OK</button>
        </div>
    </div>
</div>
<?php } ?>
<div id="sharedIdleCheckModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2200; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:12px; width:370px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <div style="width:50px; height:50px; background:#DBEAFE; color:#1D4ED8; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
            <i class="fa fa-user-o"></i>
        </div>
        <h3 style="margin:0 0 10px; color:#111827;">Are you still there?</h3>
        <p style="color:#6B7280; font-size:14px; margin-bottom:25px; line-height:1.5;">
            We detected no input activity.
            Confirm within <span id="sharedIdleCountdown">10</span> seconds or you will be logged out.
        </p>
        <div style="display:flex; justify-content:center;">
            <button type="button" id="sharedIdleStayBtn" style="background:var(--primary); color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">I'm still here</button>
        </div>
    </div>
</div>
<script>
    (function () {
        if (window.__taskflowAdminClockOutNoticeInitialized) return;
        window.__taskflowAdminClockOutNoticeInitialized = true;

        var role = <?= json_encode($sharedIdleRole, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        if (role !== 'employee') return;

        var ACK_KEY = 'taskflow_admin_clock_out_notice_seen_v1';
        var fallbackSeenSignature = '';
        var activeSignature = '';
        var modal = document.getElementById('adminClockOutNoticeModal');
        var message = document.getElementById('adminClockOutNoticeMessage');
        var remarkEl = document.getElementById('adminClockOutNoticeRemark');

        function normalizeSignaturePart(value) {
            if (value == null) return '';
            return String(value).trim();
        }

        function readSeenSignature() {
            try {
                return localStorage.getItem(ACK_KEY) || fallbackSeenSignature;
            } catch (e) {
                return fallbackSeenSignature;
            }
        }

        function writeSeenSignature(signature) {
            fallbackSeenSignature = signature;
            try {
                localStorage.setItem(ACK_KEY, signature);
            } catch (e) {
                // Ignore storage errors and rely on in-memory fallback.
            }
        }

        function buildAdminClockOutNoticeSignature(payload) {
            if (!payload || !payload.clocked_out_by_admin) return '';

            var parts = [
                normalizeSignaturePart(payload.attendance_record_id || payload.attendance_id || ''),
                normalizeSignaturePart(payload.time_in || ''),
                normalizeSignaturePart(payload.time_out || ''),
                normalizeSignaturePart(payload.admin_clock_out_remark || '')
            ];

            if (!parts[0] && !parts[2] && !parts[3]) {
                return '';
            }

            return parts.join('|');
        }

        window.openAdminClockOutNoticeModal = function (remark, timeOutLabel, signature) {
            var cleanRemark = String(remark || '').trim();
            activeSignature = signature || '';

            if (message) {
                message.textContent = timeOutLabel
                    ? ('You were clocked out by an admin at ' + timeOutLabel + '.')
                    : 'You were clocked out by an admin.';
            }
            if (remarkEl) {
                remarkEl.textContent = cleanRemark || 'No remark was provided.';
            }
            if (modal) {
                modal.style.display = 'flex';
            }
        };

        window.closeAdminClockOutNoticeModal = function () {
            if (activeSignature) {
                writeSeenSignature(activeSignature);
            }
            activeSignature = '';
            if (modal) {
                modal.style.display = 'none';
            }
        };

        window.maybeShowAdminClockOutNotice = function (payload) {
            var signature = buildAdminClockOutNoticeSignature(payload);
            if (!signature) return false;
            if (readSeenSignature() === signature) return false;

            window.openAdminClockOutNoticeModal(
                payload.admin_clock_out_remark || '',
                payload.time_out || '',
                signature
            );
            return true;
        };

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && modal.style.display === 'flex') {
                window.closeAdminClockOutNoticeModal();
            }
        });

        window.addEventListener('storage', function (event) {
            if (event.key !== ACK_KEY) return;
            if (!activeSignature || event.newValue !== activeSignature) return;
            activeSignature = '';
            if (modal) {
                modal.style.display = 'none';
            }
        });
    })();

    (function () {
        if (window.__taskflowSharedIdleInitialized) return;
        window.__taskflowSharedIdleInitialized = true;

        var role = <?= json_encode($sharedIdleRole, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var isEmployeeUser = role === 'employee';
        if (!isEmployeeUser) return;

        window.__taskflowSharedIdleEnabled = true;

        var COUNTDOWN_START_SECONDS = 10;
        var INPUT_STATE_FRESH_MS = 45000;
        var NO_ATTENDANCE_IDLE_THRESHOLD_MS = 60000;
        var DISMISS_SNOOZE_MS = 15000;
        var ATTENDANCE_POLL_MS = 3000;
        var EVALUATE_INTERVAL_MS = 1000;
        var INPUT_STATE_KEY = 'taskflow_capture_input_state';
        var FORCE_STOP_KEY = 'taskflow_force_stop_capture';
        var CAPTURE_BEFORE_IDLE_REQUEST_KEY = 'taskflow_capture_before_idle_logout_req';
        var CAPTURE_BEFORE_IDLE_ACK_KEY = 'taskflow_capture_before_idle_logout_ack';

        var modal = document.getElementById('sharedIdleCheckModal');
        var countdownEl = document.getElementById('sharedIdleCountdown');
        var stayBtn = document.getElementById('sharedIdleStayBtn');

        var hasActiveAttendance = false;
        var activeAttendanceId = null;
        var isModalOpen = false;
        var isLogoutInProgress = false;
        var secondsRemaining = COUNTDOWN_START_SECONDS;
        var countdownTimer = null;
        var attendanceTimer = null;
        var evalTimer = null;
        var lastLocalActivityAt = Date.now();
        var dismissedUntilTs = 0;

        function updateCountdownLabel() {
            if (!countdownEl) return;
            countdownEl.textContent = String(Math.max(0, secondsRemaining));
        }

        function stopCountdown() {
            if (!countdownTimer) return;
            clearInterval(countdownTimer);
            countdownTimer = null;
        }

        function closeIdleModal() {
            if (!isModalOpen) return;
            isModalOpen = false;
            stopCountdown();
            secondsRemaining = COUNTDOWN_START_SECONDS;
            updateCountdownLabel();
            if (modal) modal.style.display = 'none';
        }

        function markLocalActivity() {
            lastLocalActivityAt = Date.now();
        }

        function parseInputStateFromStorage(rawValue) {
            var raw = rawValue;
            if (typeof raw !== 'string') {
                try {
                    raw = localStorage.getItem(INPUT_STATE_KEY);
                } catch (e) {
                    return null;
                }
            }
            if (!raw) return null;
            try {
                var payload = JSON.parse(raw);
                if (!payload || !payload.ts) return null;
                var ts = Number(payload.ts);
                if (!isFinite(ts) || ts <= 0) return null;
                var attendanceId = payload.attendance_id != null ? Number(payload.attendance_id) : null;
                if (activeAttendanceId && attendanceId && Number(activeAttendanceId) !== attendanceId) {
                    return null;
                }
                var thresholdReachedRaw = payload.threshold_reached;
                var thresholdReached = thresholdReachedRaw === true || thresholdReachedRaw === 1 || thresholdReachedRaw === '1';
                return {
                    ts: ts,
                    state: payload.state ? String(payload.state).toLowerCase() : 'unknown',
                    threshold_reached: thresholdReached
                };
            } catch (e) {
                return null;
            }
        }

        function isInputIdleNow() {
            if (!hasActiveAttendance) return false;
            var inputState = parseInputStateFromStorage();
            if (!inputState) return false;
            if ((Date.now() - inputState.ts) > INPUT_STATE_FRESH_MS) return false;
            if (inputState.threshold_reached) return true;
            return inputState.state === 'idle' || inputState.state === 'locked';
        }

        function isNoAttendanceIdleNow() {
            if (hasActiveAttendance) return false;
            return (Date.now() - lastLocalActivityAt) >= NO_ATTENDANCE_IDLE_THRESHOLD_MS;
        }

        function shouldShowIdleModalNow() {
            if (Date.now() < dismissedUntilTs) return false;
            if (hasActiveAttendance) {
                return isInputIdleNow();
            }
            return isNoAttendanceIdleNow();
        }

        function requestCaptureBeforeSharedIdleLogout() {
            return new Promise(function (resolve) {
                var requestId = 'shared_idle_' + Date.now() + '_' + Math.random().toString(16).slice(2);
                var settled = false;

                function cleanup() {
                    window.removeEventListener('storage', onStorageAck);
                }

                function finish(result) {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    resolve(!!result);
                }

                function onStorageAck(event) {
                    if (!event || event.key !== CAPTURE_BEFORE_IDLE_ACK_KEY || !event.newValue) return;
                    try {
                        var payload = JSON.parse(event.newValue);
                        if (!payload || payload.request_id !== requestId) return;
                        finish(!!payload.success);
                    } catch (e) {
                        // ignore malformed payload
                    }
                }

                window.addEventListener('storage', onStorageAck);

                try {
                    localStorage.setItem(CAPTURE_BEFORE_IDLE_REQUEST_KEY, JSON.stringify({
                        ts: Date.now(),
                        request_id: requestId
                    }));
                    setTimeout(function () {
                        try { localStorage.removeItem(CAPTURE_BEFORE_IDLE_REQUEST_KEY); } catch (e) {}
                    }, 1000);
                } catch (e) {
                    finish(false);
                    return;
                }

                setTimeout(function () {
                    finish(false);
                }, 10000);
            });
        }

        async function logoutFromSharedIdle() {
            if (isLogoutInProgress) return;
            isLogoutInProgress = true;
            closeIdleModal();
            if (hasActiveAttendance) {
                await requestCaptureBeforeSharedIdleLogout();
            }
            try {
                localStorage.setItem(FORCE_STOP_KEY, JSON.stringify({
                    ts: Date.now(),
                    reason: 'input_idle_timeout'
                }));
                setTimeout(function () {
                    localStorage.removeItem(FORCE_STOP_KEY);
                }, 1000);
            } catch (e) {
                // no-op
            }
            setTimeout(function () {
                window.location.href = 'logout.php';
            }, 700);
        }

        function openIdleModal() {
            if (isModalOpen || isLogoutInProgress) return;
            isModalOpen = true;
            secondsRemaining = COUNTDOWN_START_SECONDS;
            updateCountdownLabel();
            if (modal) modal.style.display = 'flex';
            stopCountdown();
            countdownTimer = setInterval(function () {
                if (!shouldShowIdleModalNow()) {
                    closeIdleModal();
                    return;
                }
                secondsRemaining -= 1;
                updateCountdownLabel();
                if (secondsRemaining <= 0) {
                    logoutFromSharedIdle();
                }
            }, 1000);
        }

        function evaluateIdleState() {
            if (isLogoutInProgress) return;
            if (shouldShowIdleModalNow()) {
                openIdleModal();
            } else {
                closeIdleModal();
            }
        }

        function pollAttendanceState() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'check_attendance.php', true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status < 200 || xhr.status >= 300) return;
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res && res.status === 'success' && res.has_active_attendance) {
                        hasActiveAttendance = true;
                        activeAttendanceId = Number(res.attendance_id || 0) || null;
                    } else {
                        hasActiveAttendance = false;
                        activeAttendanceId = null;
                    }
                    evaluateIdleState();
                    if (typeof window.maybeShowAdminClockOutNotice === 'function') {
                        window.maybeShowAdminClockOutNotice(res);
                    }
                } catch (e) {
                    // no-op
                }
            };
            xhr.send();
        }

        if (stayBtn) {
            stayBtn.addEventListener('click', function () {
                markLocalActivity();
                dismissedUntilTs = Date.now() + DISMISS_SNOOZE_MS;
                closeIdleModal();
            });
        }

        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, function () {
                markLocalActivity();
            }, true);
        });

        window.addEventListener('storage', function (event) {
            if (event.key === INPUT_STATE_KEY) {
                evaluateIdleState();
            }
        });

        pollAttendanceState();
        attendanceTimer = setInterval(pollAttendanceState, ATTENDANCE_POLL_MS);
        evalTimer = setInterval(evaluateIdleState, EVALUATE_INTERVAL_MS);
    })();

    (function presenceHeartbeat() {
        var csrfToken = <?= json_encode($presenceHeartbeatCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        if (!csrfToken) return;

        var heartbeatUrl = 'app/ajax/presence_heartbeat.php';
        var heartbeatIntervalMs = 25000;
        var inFlight = false;
        var lastSentAt = 0;

        function sendHeartbeat(force) {
            var now = Date.now();
            if (!force && (now - lastSentAt) < 5000) return;
            if (document.hidden) return;
            if (inFlight) return;

            inFlight = true;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', heartbeatUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status >= 200 && xhr.status < 300) {
                    lastSentAt = Date.now();
                }
                inFlight = false;
            };
            xhr.onerror = function () {
                inFlight = false;
            };
            xhr.send('csrf_token=' + encodeURIComponent(csrfToken));
        }

        sendHeartbeat(true);
        setInterval(function () {
            sendHeartbeat(false);
        }, heartbeatIntervalMs);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                sendHeartbeat(true);
            }
        });

        window.addEventListener('focus', function () {
            sendHeartbeat(true);
        });
    })();

    (function refreshNotificationsPreview() {
        var desktopDropdown = document.getElementById('dashTopNotifDropdown');
        var mobileDropdown = document.getElementById('mobileTopNotifDropdown');
        var desktopList = desktopDropdown ? desktopDropdown.querySelector('.dash-top-notif-list') : null;
        var mobileList = mobileDropdown ? mobileDropdown.querySelector('.dash-top-notif-list') : null;
        var desktopSub = desktopDropdown ? desktopDropdown.querySelector('.dash-top-notif-sub') : null;
        var mobileSub = mobileDropdown ? mobileDropdown.querySelector('.dash-top-notif-sub') : null;
        var desktopTrigger = document.getElementById('dashTopNotifTrigger');
        var mobileTrigger = document.getElementById('mobileTopNotifTrigger');

        if (!desktopList && !mobileList) return;

        function setBadge(trigger, badgeClass, count) {
            if (!trigger) return;
            var badge = trigger.querySelector('.' + badgeClass);
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = badgeClass;
                    trigger.appendChild(badge);
                }
                badge.textContent = String(count);
            } else if (badge) {
                badge.remove();
            }
        }

        function syncContent(data) {
            if (!data || data.status !== 'success') return;
            var html = typeof data.html === 'string' ? data.html : '';
            if (desktopList) desktopList.innerHTML = html;
            if (mobileList) mobileList.innerHTML = html;

            var unread = typeof data.unread === 'number' ? data.unread : 0;
            if (desktopSub) desktopSub.textContent = unread + ' unread';
            if (mobileSub) mobileSub.textContent = unread + ' unread';

            setBadge(desktopTrigger, 'dash-top-badge', unread);
            setBadge(mobileTrigger, 'mobile-unread-badge', unread);
        }

        function poll() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'app/ajax/notification_preview.php', true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status < 200 || xhr.status >= 300) return;
                try {
                    var data = JSON.parse(xhr.responseText || '{}');
                    syncContent(data);
                } catch (e) {
                    // ignore parse errors
                }
            };
            xhr.send();
        }

        poll();
        setInterval(poll, 5000);
    })();
</script>

<?php include_once __DIR__ . "/toast.php"; ?>
