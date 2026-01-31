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
                             if ($task['status'] == 'in_progress') $badgeClass = "badge-in_progress";
                             if ($task['status'] == 'completed') $badgeClass = "badge-completed";
                        ?>
                        <div class="cal-task-item">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">
                                    <?= htmlspecialchars($task['title']) ?>
                                </div>
                                <div style="font-size: 13px; color: var(--text-gray);">
                                    <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 50, "...")) ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(str_replace('_',' ', $task['status'])) ?></span>
                                <div style="margin-top: 5px;">
                                    <?php if($_SESSION['role'] === 'admin'){ ?>
                                        <a href="edit-task.php?id=<?=$task['id']?>" class="btn-primary btn-sm" style="padding: 4px 8px;">Edit</a>
                                    <?php } else { ?>
                                         <a href="edit-task-employee.php?id=<?=$task['id']?>" class="btn-primary btn-sm" style="padding: 4px 8px;">View</a>
                                    <?php } ?>
                                </div>
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
