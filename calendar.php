<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/Task.php";
    include "app/model/Subtask.php";
    include "app/model/Group.php";
    include "app/model/CalendarMeeting.php";
    include "app/helpers/google_calendar.php";
    include "inc/csrf.php";

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
    $calendarCells = $dayOfWeek + $daysInMonth;
    $targetCells = $calendarCells > 35 ? 42 : 35;
    $gridStartTimestamp = strtotime("-{$dayOfWeek} days", $gridTimestamp);
    $gridEndTimestamp = strtotime('+' . ($targetCells - 1) . ' days', $gridStartTimestamp);
    $gridStartDate = date('Y-m-d', $gridStartTimestamp);
    $gridEndDate = date('Y-m-d', $gridEndTimestamp);

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
    $calendarLeaderTasks = $_SESSION['role'] === 'admin' ? [] : get_tasks_led_by_user($pdo, (int)$_SESSION['id']);
    $calendarMeetingTaskOptions = $_SESSION['role'] === 'admin' ? $allTasks : $calendarLeaderTasks;
    $calendarCanCreateMeeting = $_SESSION['role'] === 'admin' || !empty($calendarLeaderTasks);
    $allSubtasks = get_calendar_subtasks_visible_to_user($pdo, (int)$_SESSION['id'], (string)$_SESSION['role'], $gridStartDate, $gridEndDate);

    $allMeetings = calendar_meetings_get_between($pdo, $gridStartDate, $gridEndDate, (int)$_SESSION['id'], (string)$_SESSION['role']);
    $meetingsForSelectedDate = calendar_meetings_get_for_date($pdo, $currentDate, (int)$_SESSION['id'], (string)$_SESSION['role']);
    $meetingsByDate = [];
    foreach ($allMeetings as $meeting) {
        $meetingDate = trim((string)($meeting['meeting_date'] ?? ''));
        if ($meetingDate !== '') {
            $meetingsByDate[$meetingDate][] = $meeting;
        }
    }
    $calendarGroups = $_SESSION['role'] === 'admin' ? get_all_groups($pdo) : [];

    $calendarStatusError = trim((string)($_GET['error'] ?? ''));
    $calendarStatusSuccess = trim((string)($_GET['success'] ?? ''));

    // --- 3. Group Tasks by Date ---
    $tasksByDate = [];
    $tasksForSelectedDate = [];
    $subtasksByDate = [];
    $subtasksForSelectedDate = [];

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

    if ($allSubtasks) {
        foreach ($allSubtasks as $subtask) {
            if (!empty($subtask['due_date'])) {
                $sDate = $subtask['due_date'];
                $subtasksByDate[$sDate][] = $subtask;

                if ($sDate === $currentDate) {
                    $subtasksForSelectedDate[] = $subtask;
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
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .calendar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
            box-shadow: 0 6px 12px rgba(var(--primary-rgb), 0.2);
        }
        .cal-nav-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(var(--primary-rgb), 0.28);
        }
        .cal-action-btn {
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            padding: 10px 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            color: #fff;
            box-shadow: 0 10px 18px rgba(20, 184, 166, 0.22);
        }
        .cal-action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(20, 184, 166, 0.28);
        }
        .cal-action-btn.secondary {
            background: #ffffff;
            color: #0f766e;
            border: 1px solid #99f6e4;
            box-shadow: none;
        }
        .cal-action-btn.secondary:hover {
            box-shadow: none;
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
            color: var(--primary);
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
            background: var(--primary-soft-3);
            color: #64748b;
        }
        .cal-day:not(.is-outside):hover {
            background: var(--primary-soft-2);
        }
        .cal-day.active {
            background: var(--primary-soft-5);
            outline: 2px solid var(--primary);
            outline-offset: -2px;
        }
        .cal-day.today {
            box-shadow: inset 0 0 0 2px var(--primary-muted);
        }
        .cal-day-num {
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
        }
        .cal-day.today .cal-day-num {
            color: var(--primary);
            font-weight: 800;
        }
        .cal-day.active .cal-day-num {
            color: var(--primary-deep);
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
        .cal-chip.meeting {
            background: #ccfbf1;
            color: #115e59;
        }
        .cal-chip.subtask {
            background: #ede9fe;
            color: #6d28d9;
        }
        .cal-chip.other {
            background: var(--primary-soft);
            color: var(--primary-ink);
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
        }
        .cal-tasks-head-copy {
            min-width: 0;
        }
        .cal-tasks-head h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }
        .cal-tasks-head p {
            margin: 4px 0 0;
            font-size: 12px;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.84);
        }
        .cal-tasks-body {
            padding: 12px;
            flex: 1;
        }
        .cal-section {
            margin-bottom: 18px;
        }
        .cal-section:last-child {
            margin-bottom: 0;
        }
        .cal-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }
        .cal-section-head h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #334155;
        }
        .cal-section-head span {
            font-size: 11px;
            color: #64748b;
            font-weight: 700;
        }
        .cal-section-empty {
            border: 1px dashed #d7dee7;
            border-radius: 12px;
            padding: 14px;
            background: #f8fafc;
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }
        .cal-section-empty strong {
            color: #334155;
        }
        .cal-meeting-item {
            border-radius: 14px;
            border: 1px solid #bfdbfe;
            background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 8px 18px rgba(59, 130, 246, 0.08);
        }
        .cal-meeting-item:last-child {
            margin-bottom: 0;
        }
        .cal-meeting-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .cal-meeting-title {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .cal-meeting-host {
            margin: 0;
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }
        .cal-meeting-time {
            flex-shrink: 0;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .cal-meeting-audience {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            border-radius: 999px;
            background: #ecfeff;
            color: #0f766e;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
        }
        .cal-meeting-desc {
            margin: 0 0 12px;
            font-size: 13px;
            color: #334155;
            line-height: 1.5;
        }
        .cal-meeting-links {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cal-inline-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
            border-radius: 999px;
            padding: 8px 12px;
        }
        .cal-inline-link.meet {
            background: #0f766e;
            color: #ffffff;
        }
        .cal-inline-link.secondary {
            background: #ffffff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .cal-inline-link:hover {
            opacity: 0.92;
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
            background: var(--primary-soft-5);
            border-color: #e9d5ff;
        }
        .cal-task-item.tone-neutral {
            background: #f3f4f6;
            border-color: #e5e7eb;
        }
        .cal-task-item.tone-phase {
            background: #f5f3ff;
            border-color: #ddd6fe;
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
        .cal-feedback {
            margin-bottom: 14px;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
            border: 1px solid transparent;
        }
        .cal-feedback.error {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .cal-feedback.success {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .cal-modal-shell {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 22px;
            background: rgba(15, 23, 42, 0.48);
            z-index: 1200;
        }
        .cal-modal-shell.is-open {
            display: flex;
        }
        .cal-modal-card {
            width: min(100%, 520px);
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.22);
            overflow: hidden;
        }
        .cal-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px 12px;
        }
        .cal-modal-head h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }
        .cal-modal-head p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }
        .cal-modal-close {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 15px;
        }
        .cal-modal-form {
            padding: 0 20px 20px;
        }
        .cal-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .cal-form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .cal-form-field.full {
            grid-column: 1 / -1;
        }
        .cal-form-field label {
            font-size: 12px;
            font-weight: 800;
            color: #334155;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .cal-form-field input,
        .cal-form-field select,
        .cal-form-field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 11px 12px;
            font: inherit;
            color: #0f172a;
            background: #ffffff;
        }
        .cal-form-field textarea {
            resize: vertical;
            min-height: 88px;
        }
        .cal-form-help {
            margin: 12px 0 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            color: #475569;
            line-height: 1.5;
        }
        .cal-modal-actions {
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
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
            .cal-tasks-head {
                flex-direction: column;
                align-items: stretch;
            }
            .cal-task-item {
                flex-direction: column;
                align-items: stretch;
            }
            .cal-form-grid {
                grid-template-columns: 1fr;
            }
            .cal-modal-shell {
                padding: 12px;
            }
            .cal-task-side {
                text-align: left;
            }
        }
    </style>
</head>
<body class="calendar-page">
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        <div class="dash-card calendar-layout calendar-shell">
            <?php if ($calendarStatusError !== '') { ?>
                <div class="cal-feedback error"><?= htmlspecialchars($calendarStatusError) ?></div>
            <?php } ?>
            <?php if ($calendarStatusSuccess !== '') { ?>
                <div class="cal-feedback success"><?= htmlspecialchars($calendarStatusSuccess) ?></div>
            <?php } ?>

            <div class="calendar-top-controls">
                <div>
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
                <?php if ($calendarCanCreateMeeting) { ?>
                    <div class="calendar-actions">
                        <button type="button" class="cal-action-btn" id="calendarCreateMeetingBtn" data-meeting-date="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>">
                            <i class="fa fa-video-camera"></i> Create Meeting
                        </button>
                    </div>
                <?php } ?>
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
                                $daySubtasks = $subtasksByDate[$dateStr] ?? [];
                                $dayMeetings = $meetingsByDate[$dateStr] ?? [];
                                $overflowCount = 0;
                                $shownChips = 0;

                                echo "<a href='calendar.php?month={$gridMonth}&year={$gridYear}&date={$dateStr}' class='cal-day {$isActive} {$isToday}'>";
                                echo "<span class='cal-day-num'>{$day}</span>";
                                echo "<span class='cal-day-chips'>";

                                if (!empty($daySubtasks) && $shownChips < 2) {
                                    $firstSubtask = $daySubtasks[0];
                                    $phaseLabel = trim((string)($firstSubtask['timeline_phase_name'] ?? ''));
                                    if ($phaseLabel === '') {
                                        $phaseLabel = trim((string)($firstSubtask['description'] ?? 'Phase'));
                                    }
                                    if ($phaseLabel === '') {
                                        $phaseLabel = 'Phase';
                                    }
                                    $subtaskLabel = mb_strimwidth($phaseLabel, 0, 18, '...');
                                    echo "<span class='cal-chip subtask'>" . htmlspecialchars($subtaskLabel) . "</span>";
                                    $shownChips++;
                                    $overflowCount += max(0, count($daySubtasks) - 1);
                                } elseif (!empty($daySubtasks)) {
                                    $overflowCount += count($daySubtasks);
                                }

                                if (!empty($dayMeetings) && $shownChips < 2) {
                                    $firstMeeting = $dayMeetings[0];
                                    $meetingTitle = trim((string)($firstMeeting['title'] ?? 'Meeting'));
                                    if ($meetingTitle === '') {
                                        $meetingTitle = 'Meeting';
                                    }
                                    $meetingTime = trim((string)($firstMeeting['start_time'] ?? ''));
                                    $meetingPrefix = $meetingTime !== '' ? date('g:i A', strtotime($meetingTime)) . ' ' : '';
                                    $meetingLabel = mb_strimwidth($meetingPrefix . $meetingTitle, 0, 18, '...');
                                    echo "<span class='cal-chip meeting'>" . htmlspecialchars($meetingLabel) . "</span>";
                                    $shownChips++;
                                    $overflowCount += max(0, count($dayMeetings) - 1);
                                } elseif (!empty($dayMeetings)) {
                                    $overflowCount += count($dayMeetings);
                                }

                                if (!empty($dayTasks) && $shownChips < 2) {
                                    $chips = array_slice($dayTasks, 0, 2 - $shownChips);
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
                                        $shownChips++;
                                    }
                                    $overflowCount += max(0, count($dayTasks) - count($chips));
                                } elseif (!empty($dayTasks)) {
                                    $overflowCount += count($dayTasks);
                                }

                                if ($overflowCount > 0) {
                                    echo "<span class='cal-chip more'>+{$overflowCount}</span>";
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
                        <div class="cal-tasks-head-copy">
                            <h3><i class="fa fa-list-ul" aria-hidden="true"></i> Agenda for <?= date('F j, Y', strtotime($currentDate)) ?></h3>
                            <p>Create a meeting straight from the selected date, then reopen the Meet link here anytime.</p>
                        </div>
                        <?php if ($calendarCanCreateMeeting) { ?>
                            <button type="button" class="cal-action-btn secondary" data-meeting-date="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>">
                                <i class="fa fa-plus-circle"></i> Create Meeting
                            </button>
                        <?php } ?>
                    </div>
                    <div class="cal-tasks-body">
                        <div class="cal-section">
                            <div class="cal-section-head">
                                <h4>Meetings</h4>
                                <span><?= count($meetingsForSelectedDate) ?> scheduled</span>
                            </div>

                            <?php if (!empty($meetingsForSelectedDate)) { ?>
                                <?php foreach ($meetingsForSelectedDate as $meeting) {
                                    $creatorAvatar = 'img/user.png';
                                    if (!empty($meeting['creator_profile_image'])) {
                                        $creatorAvatar = 'uploads/' . $meeting['creator_profile_image'];
                                    }
                                    $meetingStart = trim((string)($meeting['start_time'] ?? ''));
                                    $meetingEnd = trim((string)($meeting['end_time'] ?? ''));
                                    $timeRange = '';
                                    if ($meetingStart !== '' && $meetingEnd !== '') {
                                        $timeRange = date('g:i A', strtotime($meetingStart)) . ' - ' . date('g:i A', strtotime($meetingEnd));
                                    }
                                ?>
                                    <div class="cal-meeting-item">
                                        <div class="cal-meeting-top">
                                            <div>
                                                <p class="cal-meeting-title"><?= htmlspecialchars((string)($meeting['title'] ?? 'Workspace meeting')) ?></p>
                                                <p class="cal-meeting-host">
                                                    <img src="<?= htmlspecialchars($creatorAvatar, ENT_QUOTES) ?>" alt="Organizer" class="cal-task-member-avatar" style="width:22px;height:22px;vertical-align:middle;margin-right:6px;">
                                                    Organized by <?= htmlspecialchars((string)($meeting['creator_name'] ?? 'Workspace member')) ?>
                                                </p>
                                                <span class="cal-meeting-audience">
                                                    <i class="fa fa-users"></i>
                                                    <?php if (($meeting['audience_type'] ?? 'everyone') === 'group') { ?>
                                                        Group<?= !empty($meeting['group_name']) ? ': ' . htmlspecialchars((string)$meeting['group_name']) : '' ?>
                                                    <?php } elseif (($meeting['audience_type'] ?? 'everyone') === 'task') { ?>
                                                        Task<?= !empty($meeting['task_name']) ? ': ' . htmlspecialchars((string)$meeting['task_name']) : '' ?>
                                                    <?php } else { ?>
                                                        Everyone
                                                    <?php } ?>
                                                </span>
                                            </div>
                                            <?php if ($timeRange !== '') { ?>
                                                <span class="cal-meeting-time"><?= htmlspecialchars($timeRange) ?></span>
                                            <?php } ?>
                                        </div>
                                        <?php if (!empty($meeting['description'])) { ?>
                                            <p class="cal-meeting-desc"><?= nl2br(htmlspecialchars((string)$meeting['description'])) ?></p>
                                        <?php } ?>
                                        <div class="cal-meeting-links">
                                            <?php if (!empty($meeting['google_meet_url'])) { ?>
                                                <a href="<?= htmlspecialchars((string)$meeting['google_meet_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" class="cal-inline-link meet">
                                                    <i class="fa fa-video-camera"></i> Join Google Meet
                                                </a>
                                            <?php } ?>
                                            <?php if (!empty($meeting['google_calendar_url'])) { ?>
                                                <a href="<?= htmlspecialchars((string)$meeting['google_calendar_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" class="cal-inline-link secondary">
                                                    <i class="fa fa-calendar"></i> Open in Google Calendar
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } else { ?>
                                <div class="cal-section-empty">
                                    <strong>No meetings yet for this day.</strong><br>
                                    Use <em>Create Meeting</em> to open Google Calendar authorization once, generate a Google Meet link, and save it back here.
                                </div>
                            <?php } ?>
                        </div>

                        <div class="cal-section">
                            <div class="cal-section-head">
                                <h4>Subtasks and Phases</h4>
                                <span><?= count($subtasksForSelectedDate) ?> due</span>
                            </div>

                            <?php if (!empty($subtasksForSelectedDate)) { ?>
                                <?php
                                    $subtaskRedirectPage = ($_SESSION['role'] == 'admin') ? 'tasks.php' : 'my_task.php';
                                    foreach ($subtasksForSelectedDate as $subtask) {
                                        $redirectUrl = $subtaskRedirectPage . '?open_task=' . (int)$subtask['task_id'];
                                        $subtaskToneClass = 'tone-phase';
                                        $subtaskStatus = strtolower(trim((string)($subtask['status'] ?? 'pending')));
                                        if ($subtaskStatus === 'completed') {
                                            $subtaskToneClass = 'tone-success';
                                        } elseif ($subtaskStatus === 'submitted') {
                                            $subtaskToneClass = 'tone-review';
                                        } elseif ($subtaskStatus === 'in_progress') {
                                            $subtaskToneClass = 'tone-info';
                                        } elseif ($subtaskStatus === 'pending') {
                                            $subtaskToneClass = 'tone-phase';
                                        }

                                        $phaseName = trim((string)($subtask['timeline_phase_name'] ?? ''));
                                        $phaseMeta = subtask_google_workspace_meta($subtask);
                                        $phaseTypeLabel = $phaseName !== ''
                                            ? $phaseName
                                            : trim((string)($subtask['description'] ?? 'Subtask'));
                                        $taskName = trim((string)($subtask['task_title'] ?? 'Task'));
                                        $memberName = trim((string)($subtask['member_name'] ?? 'Unassigned member'));
                                    ?>
                                    <div class="cal-task-item <?= htmlspecialchars($subtaskToneClass, ENT_QUOTES) ?>" onclick="location.href='<?= htmlspecialchars($redirectUrl, ENT_QUOTES) ?>'">
                                        <div class="cal-task-left">
                                            <div class="cal-task-member-avatar" style="width:42px;height:42px;font-size:13px;background:#ffffff;color:#6d28d9;border:2px solid rgba(109,40,217,0.15);">
                                                <i class="fa <?= htmlspecialchars((string)($phaseMeta['phase_icon'] ?? 'fa-list-alt'), ENT_QUOTES) ?>"></i>
                                            </div>
                                            <div class="cal-task-meta">
                                                <p class="cal-task-name"><?= htmlspecialchars($phaseTypeLabel) ?></p>
                                                <p class="cal-task-assignee"><?= htmlspecialchars($taskName) ?> • <?= htmlspecialchars($memberName) ?></p>
                                                <p class="cal-task-desc">
                                                    <?= htmlspecialchars((string)($phaseMeta['phase_label'] ?? 'Phase')) ?>
                                                    <?php if (!empty($subtask['description']) && $phaseName !== trim((string)$subtask['description'])) { ?>
                                                        • <?= htmlspecialchars(mb_strimwidth((string)$subtask['description'], 0, 56, '...')) ?>
                                                    <?php } ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="cal-task-side">
                                            <span class="cal-task-side-label">Subtask Status</span>
                                            <span class="cal-task-side-value"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)($subtask['status'] ?? 'pending')))) ?></span>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } else { ?>
                                <div class="cal-section-empty">
                                    <strong>No subtasks or timeline phases due on this day.</strong><br>
                                    Leaders will see all member phases for the tasks they lead, while admins can see all workspace subtasks here.
                                </div>
                            <?php } ?>
                        </div>

                        <div class="cal-section">
                            <div class="cal-section-head">
                                <h4>Task Deadlines</h4>
                                <span><?= count($tasksForSelectedDate) ?> due</span>
                            </div>

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
    </div>

    <?php if ($calendarCanCreateMeeting) { ?>
    <div class="cal-modal-shell" id="calendarMeetingModal" aria-hidden="true">
        <div class="cal-modal-card" role="dialog" aria-modal="true" aria-labelledby="calendarMeetingModalTitle">
            <div class="cal-modal-head">
                <div>
                    <h3 id="calendarMeetingModalTitle">Create Meeting</h3>
                    <p>Pick the selected calendar date, add the meeting title and time, and TaskFlow will create a Google Meet-backed event for you.</p>
                </div>
                <button type="button" class="cal-modal-close" id="calendarMeetingCloseBtn" aria-label="Close meeting form">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <form class="cal-modal-form" method="post" action="app/google-calendar-meeting.php">
                <?= csrf_field('calendar_meeting_form') ?>
                <div class="cal-form-grid">
                    <div class="cal-form-field full">
                        <label for="calendarMeetingTitle">Meeting Name</label>
                        <input type="text" id="calendarMeetingTitle" name="title" maxlength="255" placeholder="Weekly project sync" required>
                    </div>
                    <div class="cal-form-field">
                        <label for="calendarMeetingDate">Date</label>
                        <input type="date" id="calendarMeetingDate" name="meeting_date" value="<?= htmlspecialchars($currentDate, ENT_QUOTES) ?>" required>
                    </div>
                    <div class="cal-form-field">
                        <label for="calendarMeetingTimezone">Timezone</label>
                        <input type="text" id="calendarMeetingTimezone" name="timezone" value="Asia/Manila" required>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin') { ?>
                        <div class="cal-form-field">
                            <label for="calendarMeetingAudience">Audience</label>
                            <select id="calendarMeetingAudience" name="audience_type">
                                <option value="everyone">Everyone</option>
                                <option value="group">Specific Group</option>
                                <option value="task">Specific Task</option>
                            </select>
                        </div>
                        <div class="cal-form-field" id="calendarMeetingGroupField" style="display:none;">
                            <label for="calendarMeetingGroup">Group</label>
                            <select id="calendarMeetingGroup" name="group_id">
                                <option value="">Select a group</option>
                                <?php foreach ($calendarGroups as $groupRow) { ?>
                                    <option value="<?= (int)$groupRow['id'] ?>"><?= htmlspecialchars((string)($groupRow['name'] ?? 'Group')) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="cal-form-field" id="calendarMeetingTaskField" style="display:none;">
                            <label for="calendarMeetingTask">Task</label>
                            <select id="calendarMeetingTask" name="task_id">
                                <option value="">Select a task</option>
                                <?php foreach ($calendarMeetingTaskOptions as $taskOption) { ?>
                                    <option value="<?= (int)$taskOption['id'] ?>"><?= htmlspecialchars((string)($taskOption['title'] ?? 'Task')) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } else { ?>
                        <input type="hidden" name="audience_type" value="task">
                        <div class="cal-form-field">
                            <label for="calendarMeetingTask">Task</label>
                            <select id="calendarMeetingTask" name="task_id" required>
                                <option value="">Select one of your led tasks</option>
                                <?php foreach ($calendarMeetingTaskOptions as $taskOption) { ?>
                                    <option value="<?= (int)$taskOption['id'] ?>"><?= htmlspecialchars((string)($taskOption['title'] ?? 'Task')) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
                    <div class="cal-form-field">
                        <label for="calendarMeetingStart">Start Time</label>
                        <input type="time" id="calendarMeetingStart" name="start_time" required>
                    </div>
                    <div class="cal-form-field">
                        <label for="calendarMeetingEnd">End Time</label>
                        <input type="time" id="calendarMeetingEnd" name="end_time" required>
                    </div>
                    <div class="cal-form-field full">
                        <label for="calendarMeetingDescription">Description</label>
                        <textarea id="calendarMeetingDescription" name="description" placeholder="Agenda, reminders, or a short note for the team."></textarea>
                    </div>
                </div>
                <div class="cal-form-help">
                    <?php if ($_SESSION['role'] === 'admin') { ?>
                        Google Meet links are created through Google Calendar. Admins can target the whole workspace, one group, or one task team.
                    <?php } else { ?>
                        Leaders create meetings for one of their led tasks. Only the members assigned to that task, plus the leader and admins, will see the meeting in TaskFlow.
                    <?php } ?>
                </div>
                <div class="cal-modal-actions">
                    <button type="button" class="cal-action-btn secondary" id="calendarMeetingCancelBtn">Cancel</button>
                    <button type="submit" class="cal-action-btn">
                        <i class="fa fa-video-camera"></i> Create in Google Meet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('calendarMeetingModal');
            var closeBtn = document.getElementById('calendarMeetingCloseBtn');
            var cancelBtn = document.getElementById('calendarMeetingCancelBtn');
            var dateInput = document.getElementById('calendarMeetingDate');
            var audienceInput = document.getElementById('calendarMeetingAudience');
            var groupField = document.getElementById('calendarMeetingGroupField');
            var groupSelect = document.getElementById('calendarMeetingGroup');
            var taskField = document.getElementById('calendarMeetingTaskField');
            var taskSelect = document.getElementById('calendarMeetingTask');
            var openButtons = document.querySelectorAll('[data-meeting-date]');

            function openMeetingModal(dateValue) {
                if (!modal) {
                    return;
                }

                if (dateInput && dateValue) {
                    dateInput.value = dateValue;
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeMeetingModal() {
                if (!modal) {
                    return;
                }

                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            function syncAudienceField() {
                var audienceValue = audienceInput ? audienceInput.value : 'task';
                var isGroup = audienceValue === 'group';
                var isTask = audienceValue === 'task';

                if (groupField) {
                    groupField.style.display = isGroup ? 'flex' : 'none';
                }
                if (groupSelect) {
                    groupSelect.required = isGroup;
                    if (!isGroup) {
                        groupSelect.value = '';
                    }
                }

                if (taskField) {
                    taskField.style.display = isTask ? 'flex' : 'none';
                }
                if (taskSelect) {
                    taskSelect.required = isTask;
                    if (!isTask && audienceInput) {
                        taskSelect.value = '';
                    }
                }
            }

            openButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    openMeetingModal(button.getAttribute('data-meeting-date') || '');
                });
            });

            if (audienceInput) {
                audienceInput.addEventListener('change', syncAudienceField);
            }
            syncAudienceField();

            if (closeBtn) {
                closeBtn.addEventListener('click', closeMeetingModal);
            }
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeMeetingModal);
            }
            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeMeetingModal();
                    }
                });
            }
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeMeetingModal();
                }
            });
        })();
    </script>
    <?php } ?>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>


