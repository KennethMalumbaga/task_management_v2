<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/Task.php";

    // --- 1. Date & Calendar Logic ---
    $currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $timestamp = strtotime($currentDate);
    
    $gridYear = isset($_GET['year']) ? $_GET['year'] : date('Y', $timestamp);
    $gridMonth = isset($_GET['month']) ? $_GET['month'] : date('m', $timestamp);
    
    $gridTimestamp = strtotime("$gridYear-$gridMonth-01");
    
    $monthName = date('F', $gridTimestamp);
    $daysInMonth = date('t', $gridTimestamp);
    $dayOfWeek = date('w', $gridTimestamp); 
    // Adjust for Monday start (0=Mon, 6=Sun)
    $dayOfWeek = ($dayOfWeek + 6) % 7; 

    // Prev/Next Month Links
    $prevMonthTimestamp = strtotime("-1 month", $gridTimestamp);
    $prevMonth = date('m', $prevMonthTimestamp);
    $prevYear = date('Y', $prevMonthTimestamp);
    
    $nextMonthTimestamp = strtotime("+1 month", $gridTimestamp);
    $nextMonth = date('m', $nextMonthTimestamp);
    $nextYear = date('Y', $nextMonthTimestamp);

    // --- 2. Fetch Tasks ---
    if ($_SESSION['role'] == 'admin') {
        $allTasks = get_all_tasks($pdo);
    } else {
        $allTasks = get_all_tasks_by_user($pdo, $_SESSION['id']);
    }

    // --- 3. Group Tasks by Date ---
    $tasksByDate = [];
    $tasksForSelectedDate = [];

    if ($allTasks) {
        foreach ($allTasks as $task) {
            if (!empty($task['due_date'])) {
                $tDate = $task['due_date']; // Y-m-d
                $tasksByDate[$tDate][] = $task;
                
                if ($tDate === $currentDate) {
                    $tasksForSelectedDate[] = $task;
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
	<title>Calendar | TaskFlow</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .calendar-wrapper { display: flex; gap: 30px; height: 100%; flex-direction: column; }
        @media(min-width: 992px) {
             .calendar-wrapper { flex-direction: row; }
        }
        .calendar-widget { flex: 1; padding-right: 30px; }
        .calendar-widget-inner { background: #fff; border-radius: 12px; }
        
        @media(min-width: 992px) {
             .calendar-widget { border-right: 1px solid var(--border-color); }
        }

        .calendar-tasks { flex: 1; }
        .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; }
        .cal-head { font-weight: 600; font-size: 13px; color: var(--text-gray); padding-bottom: 10px; }
        
        .cal-day { 
            height: 45px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px; 
            position: relative;
            background: #FAFAFA;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.2s;
        }
        .cal-day:hover { background: #E0E7FF; color: var(--primary); }
        
        .cal-day.active { background: var(--primary); color: white; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
        .cal-day.empty { background: transparent; cursor: default; }

        .cal-day.has-task::after { 
            content: ''; 
            position: absolute; 
            bottom: 6px; 
            width: 5px; 
            height: 5px; 
            background: var(--danger); 
            border-radius: 50%; 
        }
        .cal-day.active.has-task::after { background: var(--white); }

        /* Task List Item in Calendar Page */
        .cal-task-item {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s;
        }
        .cal-task-item:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        <h2 style="margin-bottom: 24px;">Task Calendar</h2>
        
        <div class="dash-card calendar-layout">
            <div class="calendar-wrapper">
                
                <!-- Calendar Widget -->
                <div class="calendar-widget">
                    <div class="cal-header">
                        <a href="calendar.php?month=<?=$prevMonth?>&year=<?=$prevYear?>&date=<?=$prevYear?>-<?=$prevMonth?>-01" class="btn-outline btn-sm">
                            <i class="fa fa-chevron-left"></i>
                        </a>
                        <h3 style="margin: 0; min-width: 150px; text-align: center;"> <?= $monthName ?> <?= $gridYear ?> </h3>
                        <a href="calendar.php?month=<?=$nextMonth?>&year=<?=$nextYear?>&date=<?=$nextYear?>-<?=$nextMonth?>-01" class="btn-outline btn-sm">
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                    
                    <div class="cal-grid">
                        <div class="cal-head">MON</div>
                        <div class="cal-head">TUE</div>
                        <div class="cal-head">WED</div>
                        <div class="cal-head">THU</div>
                        <div class="cal-head">FRI</div>
                        <div class="cal-head">SAT</div>
                        <div class="cal-head">SUN</div>
                        
                        <?php 
                        // Empty cells before start of month
                        for ($i = 0; $i < $dayOfWeek; $i++) {
                            echo '<div class="cal-day empty"></div>';
                        }

                        // Days of Month
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $dateStr = sprintf("%s-%s-%02d", $gridYear, $gridMonth, $day);
                            $isActive = ($dateStr === $currentDate) ? 'active' : '';
                            $hasTask = isset($tasksByDate[$dateStr]) ? 'has-task' : '';
                            
                            // Link to select date
                            echo "<a href='calendar.php?month=$gridMonth&year=$gridYear&date=$dateStr' class='cal-day $isActive $hasTask'>$day</a>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Tasks List for Selected Day -->
                <div class="calendar-tasks">
                    <h3 style="margin-top: 0; margin-bottom: 20px;">
                        Tasks Deadlines for <?= date('F j, Y', strtotime($currentDate)) ?>
                    </h3>
                    
                    <?php if (count($tasksForSelectedDate) > 0) { ?>
                        <?php foreach ($tasksForSelectedDate as $task) { 
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
                        <div class="task-card" style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E5E7EB; position: relative;">
                            
                             <!-- Edit/View Button -->
                             <?php if($_SESSION['role'] === 'admin'){ ?>
                                <a href="edit-task.php?id=<?=$task['id']?>" style="position: absolute; top: 20px; right: 20px; color: #9CA3AF; text-decoration: none; font-size: 14px;"><i class="fa fa-pencil"></i></a>
                             <?php } else { ?>
                                <a href="edit-task-employee.php?id=<?=$task['id']?>" style="position: absolute; top: 20px; right: 20px; color: #9CA3AF; text-decoration: none; font-size: 14px;"><i class="fa fa-eye"></i></a>
                             <?php } ?>

                            <!-- Header -->
                            <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                                <i class="fa fa-chevron-right" style="color: #6B7280; font-size: 10px;"></i>
                                <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #111827;"><?= htmlspecialchars($task['title']) ?></h3>
                                
                                <?php if($isSubmittedForReview) { ?>
                                    <span style="background: #F3E8FF; color: #7E22CE; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: lowercase;">submitted_for_review</span>
                                <?php } else { ?>
                                    <span class="badge <?= $badgeClass ?>" style="font-size: 10px; padding: 2px 8px;"><?= $statusDisplay ?></span>
                                <?php } ?>
                            </div>

                            <!-- Description -->
                            <div style="color: #6B7280; font-size: 13px; margin-bottom: 20px; padding-left: 18px;">
                                <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 80, "...")) ?>
                            </div>

                            <div style="padding-left: 18px;">
                                
                                <!-- Project Leader Section -->
                                <?php if ($leader) { 
                                    $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                                ?>
                                <div style="background: #F5F3FF; border: 1px solid #E0E7FF; border-radius: 8px; padding: 10px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                                    <img src="<?= $leaderImg ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                    <div>
                                        <div style="font-size: 9px; font-weight: 700; color: #6366F1; letter-spacing: 0.5px; text-transform: uppercase;">
                                            <i class="fa fa-crown" style="margin-right: 4px;"></i> Project Leader
                                        </div>
                                        <div style="font-weight: 600; color: #1F2937; font-size: 13px;">
                                            <?= htmlspecialchars($leader['full_name']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>

                                <!-- Team Members Section -->
                                <?php if (!empty($members)) { ?>
                                <div style="margin-bottom: 12px;">
                                    <div style="font-size: 10px; font-weight: 600; color: #059669; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
                                        <i class="fa fa-users" style="margin-right: 4px;"></i> Team
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php foreach ($members as $member) { 
                                            $memImg = !empty($member['profile_image']) ? 'uploads/' . $member['profile_image'] : 'img/user.png';
                                        ?>
                                        <div style="background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 8px; padding: 6px 10px; display: flex; align-items: center; gap: 6px;">
                                            <img src="<?= $memImg ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                                            <div style="font-weight: 500; color: #1F2937; font-size: 12px;">
                                                <?= htmlspecialchars($member['full_name']) ?>
                                            </div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php } ?>
                            
                                <!-- Rating -->
                                <?php if ($task['status'] == 'completed' && $task['rating'] > 0) { ?>
                                    <div style="margin-top: 8px; font-size: 13px; color: #4B5563;">
                                        <span style="color: #F59E0B; font-weight: 600;"><i class="fa fa-star"></i> <?= $task['rating'] ?>/5</span> 
                                    </div>
                                <?php } ?>

                            </div>

                        </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-gray); border: 1px dashed var(--border-color); border-radius: 12px;">
                            <i class="fa fa-calendar-check-o" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                            <p>No tasks due on this day</p>
                            <?php if ($_SESSION['role'] == 'admin') { ?>
                                <a href="create_task.php" class="btn-primary btn-sm">Create Task</a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>
