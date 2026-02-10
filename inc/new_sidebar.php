<!-- Mobile Navbar (Fixed Top) -->
<div class="mobile-navbar">
    <div class="mobile-brand">
        <img src="img/logo.png" alt="TaskFlow" class="brand-logo-mobile">
        <div class="mobile-brand-text">
            <h2>TaskFlow</h2>
            <span>Management System</span>
        </div>
    </div>
    <button class="mobile-toggle-btn" onclick="toggleSidebar()">
        <i class="fa fa-bars"></i>
    </button>
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
            <?php 
                include_once "app/model/Message.php";
                include_once "app/model/GroupMessage.php";
                $dmUnread = countAllUnread($_SESSION['id'], $pdo);
                $grpUnread = count_all_group_unread($pdo, $_SESSION['id']);
                $totalUnread = $dmUnread + $grpUnread;
            ?>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($totalUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$totalUnread?></span>
                <?php } ?>
            </a>
            <a href="profile.php" class="dash-nav-item <?= isActive('profile.php') ?>">
                <i class="fa fa-user-o"></i> Profile
            </a>
            <a href="logout.php" class="dash-nav-item">
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
            <?php 
                include_once "app/model/Message.php";
                include_once "app/model/GroupMessage.php";
                $dmUnread = countAllUnread($_SESSION['id'], $pdo);
                $grpUnread = count_all_group_unread($pdo, $_SESSION['id']);
                $totalUnread = $dmUnread + $grpUnread;
            ?>
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
            <a href="logout.php" class="dash-nav-item">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        <?php } ?>
    </nav>
</div>
