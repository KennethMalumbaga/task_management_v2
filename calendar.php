<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/Task.php";

    // --- 1. Date & Calendar Logic ---
    $currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $timestamp = strtotime($currentDate);
    
    $gridYear = isset($_GET['year']) ? $_GET['year'] : date('Y', $timestamp);
    $gridMonth = isset($_GET['month']) ? $_GET['month'] : date('m', $timestamp);
    
    $gridTimestamp = strtotime("$gridYear-$gridMonth-01");
    
    $monthName = date('F', $gridTimestamp);
    $daysInMonth = date('t', $gridTimestamp);
    // Sunday-start index: 0 = Sunday, 6 = Saturday
    $dayOfWeek = (int)date('w', $gridTimestamp);

    // Prev/Next Month Links
    $prevMonthTimestamp = strtotime("-1 month", $gridTimestamp);
    $prevMonth = date('m', $prevMonthTimestamp);
    $prevYear = date('Y', $prevMonthTimestamp);
    
    $nextMonthTimestamp = strtotime("+1 month", $gridTimestamp);
    $nextMonth = date('m', $nextMonthTimestamp);
    $nextYear = date('Y', $nextMonthTimestamp);

    $todayDate = date('Y-m-d');
    $todayLabel = date('l, F j, Y');

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .calendar-shell {
            background: #eef2f5;
            border: 1px solid #d9e2ea;
            border-radius: 18px;
            padding: 16px;
        }
        .calendar-page-head {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            margin-bottom: 10px;
        }
        .calendar-role-pill {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            color: #2a2c56;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: capitalize;
            padding: 7px 14px;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
        }

        .calendar-wrapper {
            display: grid;
            grid-template-columns: minmax(0, 1.58fr) minmax(320px, 1fr);
            gap: 20px;
            align-items: stretch;
        }
        .calendar-top-controls {
            margin-bottom: 10px;
        }
        .calendar-widget {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .cal-month-nav {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .cal-nav-btn {
            background: linear-gradient(135deg, #6c3ce1 0%, #7c4dff 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            padding: 6px 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 6px 12px rgba(108, 60, 225, 0.2);
        }
        .cal-nav-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(108, 60, 225, 0.28);
        }
        .cal-month-title {
            margin: 0 8px;
            font-size: 24px;
            font-weight: 800;
            color: #1f2459;
            line-height: 1.2;
            white-space: nowrap;
        }
        .cal-today-indicator {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
        }
        .cal-today-indicator i {
            color: #6c3ce1;
        }

        .cal-board {
            border: 1px solid #d3d8df;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }
        .cal-weekdays,
        .cal-dates {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
        .cal-head {
            min-height: 36px;
            padding: 9px 6px;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #6c3ce1 0%, #7c4dff 100%);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            letter-spacing: 0.04em;
        }
        .cal-head:last-child {
            border-right: none;
        }

        .cal-day {
            min-height: 64px;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 5px;
            background: #ffffff;
            text-decoration: none;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: background 0.15s ease;
        }
        .cal-day:nth-child(7n) {
            border-right: none;
        }
        .cal-day.is-outside {
            background: #f8fafc;
            color: #94a3b8;
            cursor: pointer;
        }
        .cal-day.is-outside:hover {
            background: #eef2ff;
            color: #64748b;
        }
        .cal-day:not(.is-outside):hover {
            background: #f7f3ff;
        }
        .cal-day.active {
            background: #f2ecff;
            outline: 2px solid #6c3ce1;
            outline-offset: -2px;
        }
        .cal-day.today {
            box-shadow: inset 0 0 0 2px #a78bfa;
        }
        .cal-day-num {
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
        }
        .cal-day.today .cal-day-num {
            color: #6c3ce1;
            font-weight: 800;
        }
        .cal-day.active .cal-day-num {
            color: #4f46e5;
            font-weight: 800;
        }
        .cal-day-chips {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-height: 22px;
        }
        .cal-chip {
            font-size: 9px;
            font-weight: 700;
            line-height: 1;
            border-radius: 999px;
            padding: 4px 7px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cal-chip.pending {
            background: #fee2e2;
            color: #b91c1c;
        }
        .cal-chip.in-progress {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .cal-chip.completed {
            background: #dcfce7;
            color: #166534;
        }
        .cal-chip.other {
            background: #ede9fe;
            color: #5b21b6;
        }
        .cal-chip.more {
            background: #e5e7eb;
            color: #374151;
            text-align: center;
        }

        .calendar-tasks {
            background: #fff;
            border: 1px solid #d3d8df;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
            min-height: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .cal-tasks-head {
            padding: 14px 16px;
            background: linear-gradient(135deg, #6c3ce1 0%, #7c4dff 100%);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
        }
        .cal-tasks-head h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }
        .cal-tasks-body {
            padding: 12px;
            flex: 1;
        }
        .cal-task-item {
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid transparent;
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .cal-task-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 14px rgba(15, 23, 42, 0.09);
        }
        .cal-task-item.tone-success {
            background: #dcfce7;
            border-color: #bbf7d0;
        }
        .cal-task-item.tone-danger {
            background: #fee2e2;
            border-color: #fecaca;
        }
        .cal-task-item.tone-info {
            background: #dbeafe;
            border-color: #bfdbfe;
        }
        .cal-task-item.tone-review {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        .cal-task-item.tone-neutral {
            background: #f3f4f6;
            border-color: #e5e7eb;
        }
        .cal-task-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .cal-task-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            flex-shrink: 0;
        }
        .cal-task-meta {
            min-width: 0;
        }
        .cal-task-name {
            margin: 0 0 2px;
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cal-task-assignee {
            margin: 0;
            font-size: 13px;
            color: #1f2937;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cal-task-desc {
            margin: 3px 0 0;
            font-size: 12px;
            color: #4b5563;
            font-style: italic;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cal-task-members {
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        .cal-task-member-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.95);
            background: #e5e7eb;
            color: #334155;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            flex-shrink: 0;
        }
        .cal-task-member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cal-task-member-more {
            font-size: 10px;
            font-weight: 700;
            color: #475569;
            background: #e2e8f0;
            border-radius: 999px;
            padding: 2px 6px;
            line-height: 1.2;
        }
        .cal-task-member-names {
            margin: 4px 0 0;
            font-size: 11px;
            color: #334155;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cal-task-side {
            text-align: right;
            flex-shrink: 0;
        }
        .cal-task-side-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 2px;
        }
        .cal-task-side-value {
            display: block;
            font-size: 11px;
            color: #6b7280;
            font-weight: 600;
        }
        .cal-empty-state {
            text-align: center;
            padding: 34px 18px;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
            color: #6b7280;
            background: #f9fafb;
        }
        .cal-empty-state i {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.55;
            display: block;
        }

        @media (max-width: 1150px) {
            .cal-month-title {
                font-size: 21px;
            }
            .calendar-wrapper {
                grid-template-columns: 1fr;
            }
            .calendar-top-controls {
                margin-bottom: 8px;
            }
        }
        @media (max-width: 640px) {
            .calendar-shell {
                padding: 12px;
            }
            .cal-month-nav {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .cal-month-title {
                width: 100%;
                margin: 4px 0 0;
                text-align: center;
                order: 3;
            }
            .cal-today-indicator {
                display: flex;
            }
            .cal-day {
                min-height: 56px;
                padding: 6px 4px;
            }
            .cal-chip {
                font-size: 9px;
            }
            .cal-task-item {
                flex-direction: column;
                align-items: stretch;
            }
            .cal-task-side {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        <div class="dash-card calendar-layout calendar-shell">
            <div class="calendar-top-controls">
                    <div class="cal-month-nav">
                        <a href="calendar.php?month=<?=$prevMonth?>&year=<?=$prevYear?>&date=<?=$prevYear?>-<?=$prevMonth?>-01" class="cal-nav-btn">
                            <i class="fa fa-chevron-left"></i> Prev
                        </a>
                        <h3 class="cal-month-title"><?= $monthName ?> <?= $gridYear ?></h3>
                        <a href="calendar.php?month=<?=$nextMonth?>&year=<?=$nextYear?>&date=<?=$nextYear?>-<?=$nextMonth?>-01" class="cal-nav-btn">
                            Next <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                    <div class="cal-today-indicator">
                        <i class="fa fa-calendar"></i>
                        Today is <?= htmlspecialchars($todayLabel) ?>
                    </div>
            </div>

            <div class="calendar-wrapper">
                
                <!-- Calendar Widget -->
                <div class="calendar-widget">
                    <div class="cal-board">
                        <div class="cal-weekdays">
                            <div class="cal-head">SUN</div>
                            <div class="cal-head">MON</div>
                            <div class="cal-head">TUE</div>
                            <div class="cal-head">WED</div>
                            <div class="cal-head">THU</div>
                            <div class="cal-head">FRI</div>
                            <div class="cal-head">SAT</div>
                        </div>
                        <div class="cal-dates">
                            <?php
                            $prevMonthDays = (int)date('t', $prevMonthTimestamp);
                            $calendarCells = $dayOfWeek + $daysInMonth;
                            $targetCells = $calendarCells > 35 ? 42 : 35;
                            $trailingCells = max(0, $targetCells - $calendarCells);

                            // Leading days from previous month
                            for ($i = 0; $i < $dayOfWeek; $i++) {
                                $prevDayNum = $prevMonthDays - $dayOfWeek + $i + 1;
                                $prevDateStr = sprintf("%s-%s-%02d", $prevYear, $prevMonth, $prevDayNum);
                                echo "<a href='calendar.php?month={$prevMonth}&year={$prevYear}&date={$prevDateStr}' class='cal-day is-outside'><span class='cal-day-num'>{$prevDayNum}</span></a>";
                            }

                            // Current month days
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $dateStr = sprintf("%s-%s-%02d", $gridYear, $gridMonth, $day);
                                $isActive = ($dateStr === $currentDate) ? 'active' : '';
                                $isToday = ($dateStr === $todayDate) ? 'today' : '';
                                $dayTasks = $tasksByDate[$dateStr] ?? [];

                                echo "<a href='calendar.php?month={$gridMonth}&year={$gridYear}&date={$dateStr}' class='cal-day {$isActive} {$isToday}'>";
                                echo "<span class='cal-day-num'>{$day}</span>";
                                echo "<span class='cal-day-chips'>";

                                if (!empty($dayTasks)) {
                                    $chips = array_slice($dayTasks, 0, 2);
                                    foreach ($chips as $chipTask) {
                                        $chipStatusRaw = strtolower((string)($chipTask['status'] ?? ''));
                                        $chipClass = 'other';
                                        if ($chipStatusRaw === 'pending') {
                                            $chipClass = 'pending';
                                        } elseif ($chipStatusRaw === 'in_progress') {
                                            $chipClass = 'in-progress';
                                        } elseif ($chipStatusRaw === 'completed') {
                                            $chipClass = 'completed';
                                        }
                                        $chipText = trim((string)($chipTask['title'] ?? 'Task'));
                                        if ($chipText === '') {
                                            $chipText = 'Task';
                                        }
                                        $chipText = mb_strimwidth($chipText, 0, 16, '...');
                                        echo "<span class='cal-chip {$chipClass}'>" . htmlspecialchars($chipText) . "</span>";
                                    }

                                    if (count($dayTasks) > 2) {
                                        $moreCount = count($dayTasks) - 2;
                                        echo "<span class='cal-chip more'>+{$moreCount}</span>";
                                    }
                                }

                                echo "</span>";
                                echo "</a>";
                            }

                            // Trailing days from next month
                            for ($i = 1; $i <= $trailingCells; $i++) {
                                $nextDateStr = sprintf("%s-%s-%02d", $nextYear, $nextMonth, $i);
                                echo "<a href='calendar.php?month={$nextMonth}&year={$nextYear}&date={$nextDateStr}' class='cal-day is-outside'><span class='cal-day-num'>{$i}</span></a>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Tasks List for Selected Day -->
                <div class="calendar-tasks">
                    <div class="cal-tasks-head">
                        <i class="fa fa-list-ul" aria-hidden="true"></i>
                        <h3>Tasks Deadlines for <?= date('F j, Y', strtotime($currentDate)) ?></h3>
                    </div>
                    <div class="cal-tasks-body">
                    
                    <?php if (count($tasksForSelectedDate) > 0) { 
                        $redirectPage = ($_SESSION['role'] == 'admin') ? 'tasks.php' : 'my_task.php';
                    ?>
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
                            
                            $redirectUrl = "$redirectPage?open_task=" . $task['id'];
                        ?>
                        <?php
                            $displayAvatar = 'img/user.png';
                            $displayAssignee = 'No assignee';
                            if ($leader) {
                                $displayAvatar = !empty($leader['profile_image']) ? ('uploads/' . $leader['profile_image']) : 'img/user.png';
                                $displayAssignee = $leader['full_name'];
                            } elseif (!empty($members)) {
                                $firstMember = $members[0];
                                $displayAvatar = !empty($firstMember['profile_image']) ? ('uploads/' . $firstMember['profile_image']) : 'img/user.png';
                                $displayAssignee = $firstMember['full_name'];
                            }

                            $toneClass = 'tone-neutral';
                            if ($isSubmittedForReview) {
                                $toneClass = 'tone-review';
                            } elseif ($task['status'] == 'completed') {
                                $toneClass = 'tone-success';
                            } elseif ($task['status'] == 'in_progress') {
                                $toneClass = 'tone-info';
                            } elseif ($task['status'] == 'pending') {
                                $toneClass = 'tone-danger';
                            }
                        ?>
                        <div class="cal-task-item <?= $toneClass ?>" onclick="location.href='<?=$redirectUrl?>'">
                            <div class="cal-task-left">
                                <img src="<?= htmlspecialchars($displayAvatar, ENT_QUOTES) ?>" alt="Task assignee" class="cal-task-avatar">
                                <div class="cal-task-meta">
                                    <p class="cal-task-name"><?= htmlspecialchars($task['title']) ?></p>
                                    <p class="cal-task-desc"><?= htmlspecialchars(mb_strimwidth($task['description'], 0, 60, "...")) ?></p>
                                    <p class="cal-task-assignee"><?= htmlspecialchars($displayAssignee) ?></p>
                                    <?php if (!empty($members)) { ?>
                                        <div class="cal-task-members" aria-label="Task members">
                                            <?php
                                                $memberNameList = [];
                                                $memberPreview = array_slice($members, 0, 6);
                                                foreach ($memberPreview as $member) {
                                                    $memberName = trim((string)($member['full_name'] ?? ''));
                                                    if ($memberName === '') {
                                                        $memberName = 'Member';
                                                    }
                                                    $memberNameList[] = $memberName;
                                                    $memberImage = (!empty($member['profile_image']) && $member['profile_image'] !== 'default.png')
                                                        ? ('uploads/' . $member['profile_image'])
                                                        : '';
                                            ?>
                                                <span class="cal-task-member-avatar" title="<?= htmlspecialchars($memberName, ENT_QUOTES) ?>">
                                                    <?php if ($memberImage !== '') { ?>
                                                        <img src="<?= htmlspecialchars($memberImage, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($memberName, ENT_QUOTES) ?>">
                                                    <?php } else { ?>
                                                        <?= htmlspecialchars(strtoupper(substr($memberName, 0, 1))) ?>
                                                    <?php } ?>
                                                </span>
                                            <?php } ?>
                                            <?php if (count($members) > 6) { ?>
                                                <span class="cal-task-member-more">+<?= (int)(count($members) - 6) ?></span>
                                            <?php } ?>
                                        </div>
                                        <p class="cal-task-member-names">
                                            Members: <?= htmlspecialchars(implode(', ', $memberNameList)) ?><?= count($members) > 6 ? ', ...' : '' ?>
                                        </p>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="cal-task-side">
                                <span class="cal-task-side-label"><?= $isSubmittedForReview ? 'Review' : 'Status' ?></span>
                                <span class="cal-task-side-value"><?= htmlspecialchars(ucwords((string)$statusDisplay)) ?></span>
                            </div>
                        </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="cal-empty-state">
                            <i class="fa fa-calendar-check-o"></i>
                            <p>No tasks due on this day</p>
                            <?php if ($_SESSION['role'] == 'admin') { ?>
                                <a href="create_task.php?due_date=<?= urlencode((string)$currentDate) ?>" class="btn-primary btn-sm">Create Task</a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    </div>
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


