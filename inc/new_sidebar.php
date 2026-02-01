<div class="dash-sidebar">
    <div class="dash-brand">
        <h2>TaskFlow</h2>
        <span>Management System</span>
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
                include_once "app/Model/Message.php";
                $allUnread = countAllUnread($_SESSION['id'], $pdo);
            ?>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($allUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$allUnread?></span>
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
                include_once "app/Model/Message.php";
                $allUnread = countAllUnread($_SESSION['id'], $pdo);
            ?>
            <a href="messages.php" class="dash-nav-item <?= isActive('messages.php') ?>">
                <i class="fa fa-comment-o"></i> Messages
                <?php if($allUnread > 0){ ?>
                    <span class="dash-nav-badge"><?=$allUnread?></span>
                <?php } ?>
            </a>
            <a href="user.php" class="dash-nav-item <?= isActive('user.php') ?>">
                <i class="fa fa-users"></i> Users
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
