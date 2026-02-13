<?php 
    include_once "app/model/Message.php";
    include_once "app/model/GroupMessage.php";
    $dmUnread = countAllUnread($_SESSION['id'], $pdo);
    $grpUnread = count_all_group_unread($pdo, $_SESSION['id']);
    $totalUnread = $dmUnread + $grpUnread;
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
            <a href="profile.php" class="dash-nav-item <?= isActive('profile.php') ?>">
                <i class="fa fa-user-o"></i> Profile
            </a>
            <a href="logout.php" class="dash-nav-item js-logout-link">
                <i class="fa fa-sign-out"></i> Logout
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
            <a href="groups.php" class="dash-nav-item <?= isActive('groups.php') ?>">
                <i class="fa fa-object-group"></i> Groups
            </a>
            <a href="screenshots.php" class="dash-nav-item <?= isActive('screenshots.php') ?>">
                <i class="fa fa-camera"></i> Captures
            </a>
            <a href="profile.php" class="dash-nav-item <?= isActive('profile.php') ?>">
                <i class="fa fa-user-o"></i> Profile
            </a>
            <a href="logout.php" class="dash-nav-item js-logout-link">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        <?php } ?>
    </nav>
</div>

<div id="logoutConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200; align-items:center; justify-content:center;">
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
