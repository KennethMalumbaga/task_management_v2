<?php
session_start();
if (!isset($_SESSION['role'], $_SESSION['id'])) {
    header('Location: login.php?error=First login');
    exit;
}
$isAdmin = $_SESSION['role'] === 'admin';
$isEmployee = $_SESSION['role'] === 'employee';
if (!$isAdmin && !$isEmployee) {
    header('Location: login.php?error=First login');
    exit;
}

require_once "DB_connection.php";
require_once "inc/tenant.php";
require_once "app/model/user.php";
require_once "app/model/Group.php";
require_once "app/model/Report.php";
require_once "app/model/AttendanceAdjustment.php";
require_once "inc/csrf.php";

date_default_timezone_set('Asia/Manila');

$workspaceName = trim((string)($_SESSION['organization_name'] ?? 'Workspace'));
$workspaceAddress = '';
$workspaceOrgId = tenant_get_current_org_id();
if ($workspaceOrgId && tenant_table_exists($pdo, 'organizations')) {
    $nameColumn = tenant_column_exists($pdo, 'organizations', 'name') ? 'name' : null;
    $addressColumn = null;
    foreach (['address', 'company_address', 'office_address', 'location', 'billing_address'] as $col) {
        if (tenant_column_exists($pdo, 'organizations', $col)) {
            $addressColumn = $col;
            break;
        }
    }
    $columns = array_values(array_filter([$nameColumn, $addressColumn]));
    if (!empty($columns)) {
        $stmtOrg = $pdo->prepare("SELECT " . implode(', ', $columns) . " FROM organizations WHERE id = ? LIMIT 1");
        $stmtOrg->execute([(int)$workspaceOrgId]);
        $orgRow = $stmtOrg->fetch(PDO::FETCH_ASSOC);
        if ($orgRow) {
            if ($nameColumn && !empty($orgRow[$nameColumn])) {
                $workspaceName = trim((string)$orgRow[$nameColumn]);
            }
            if ($addressColumn && !empty($orgRow[$addressColumn])) {
                $workspaceAddress = trim((string)$orgRow[$addressColumn]);
            }
        }
    }
}
if ($workspaceName === '') {
    $workspaceName = 'Workspace';
}

function report_sanitize_date($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    $errors = DateTime::getLastErrors();
    if (!$dt || (!empty($errors['warning_count']) || !empty($errors['error_count']))) {
        return null;
    }
    return $dt->format('Y-m-d');
}

function report_format_range($startDate, $endDate)
{
    $startLabel = date('M j, Y', strtotime($startDate));
    $endLabel = date('M j, Y', strtotime($endDate));
    return $startLabel . " - " . $endLabel;
}

function report_format_value($value, $decimals = 1, $fallback = '&mdash;')
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    if (!is_numeric($value)) {
        return $fallback;
    }
    return number_format((float)$value, $decimals);
}

function report_format_time($timeValue)
{
    $timeValue = trim((string)$timeValue);
    if ($timeValue === '' || $timeValue === '00:00:00') {
        return '';
    }
    $ts = strtotime($timeValue);
    if ($ts === false) {
        return '';
    }
    return date('g:i A', $ts);
}

function report_get_initials($name)
{
    $clean = trim((string)$name);
    if ($clean === '') {
        return 'U';
    }
    $parts = preg_split('/\s+/', $clean);
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials !== '' ? $initials : 'U';
}

function report_get_color_for_index($index)
{
    $palette = [
        '#6c3ef4',
        '#8b5cf6',
        '#0ea5e9',
        '#ec4899',
        '#10b981',
        '#f59e0b',
        '#f97316',
        '#14b8a6',
        '#22c55e',
        '#ef4444',
    ];
    $count = count($palette);
    if ($count === 0) {
        return '#6c3ef4';
    }
    $idx = $index % $count;
    if ($idx < 0) {
        $idx += $count;
    }
    return $palette[$idx];
}

$monthParam = trim((string)($_GET['month'] ?? ''));
$monthInput = null;
if (preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthInput = $monthParam;
}

$startInput = report_sanitize_date($_GET['start'] ?? '');
$endInput = report_sanitize_date($_GET['end'] ?? '');

if ($monthInput) {
    $monthStart = DateTime::createFromFormat('Y-m', $monthInput);
    if ($monthStart instanceof DateTime) {
        $startDate = $monthStart->format('Y-m-01');
        $endDate = $monthStart->format('Y-m-t');
    } else {
        $monthInput = null;
    }
}

if (!isset($startDate) || !isset($endDate)) {
    if (!$startInput || !$endInput) {
        $today = date('Y-m-d');
        $ts = strtotime($today);
        $weekStart = strtotime('monday this week', $ts);
        if (date('N', $ts) == 1) {
            $weekStart = strtotime(date('Y-m-d', $ts));
        }
        $weekEnd = strtotime('+6 days', $weekStart);
        $startDate = date('Y-m-d', $weekStart);
        $endDate = date('Y-m-d', $weekEnd);
    } else {
        $startDate = $startInput;
        $endDate = $endInput;
    }
}

if ($monthInput === null && $startDate && $endDate && substr($startDate, 0, 7) === substr($endDate, 0, 7)) {
    $monthInput = substr($startDate, 0, 7);
}

if (!$startDate || !$endDate) {
    $today = date('Y-m-d');
    $ts = strtotime($today);
    $weekStart = strtotime('monday this week', $ts);
    if (date('N', $ts) == 1) {
        $weekStart = strtotime(date('Y-m-d', $ts));
    }
    $weekEnd = strtotime('+6 days', $weekStart);
    $startDate = date('Y-m-d', $weekStart);
    $endDate = date('Y-m-d', $weekEnd);
}

if ($startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}

$startTs = $startDate . ' 00:00:00';
$endTs = $endDate . ' 23:59:59';

$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dtrUserId = isset($_GET['dtr_user_id']) ? (int)$_GET['dtr_user_id'] : 0;
if ($isEmployee) {
    $groupId = 0;
    $userId = (int)$_SESSION['id'];
    $dtrUserId = $userId;
} elseif ($dtrUserId <= 0) {
    $dtrUserId = $userId;
}

$groups = [];
if (tenant_table_exists($pdo, 'groups')) {
    $groups = get_all_groups($pdo);
}

$allUsers = get_all_users($pdo, 'employee');
if ($isEmployee) {
    $allUsers = array_values(array_filter($allUsers, function ($user) use ($userId) {
        return (int)($user['id'] ?? 0) === $userId;
    }));
}
$groupMemberIds = [];
if ($groupId > 0) {
    $members = get_group_members($pdo, $groupId);
    foreach ($members as $member) {
        if (($member['user_role'] ?? '') !== 'admin') {
            $groupMemberIds[(int)$member['user_id']] = true;
        }
    }
}

$userOptions = $allUsers;
if ($groupId > 0) {
    $userOptions = array_values(array_filter($allUsers, function ($user) use ($groupMemberIds) {
        return isset($groupMemberIds[(int)$user['id']]);
    }));
}

$filteredUsers = $userOptions;
if ($userId > 0) {
    $filteredUsers = array_values(array_filter($filteredUsers, function ($user) use ($userId) {
        return (int)$user['id'] === $userId;
    }));
}

$reportUserIds = array_map('intval', array_column($filteredUsers, 'id'));

$taskMetrics = report_get_task_metrics($pdo, $reportUserIds, $startDate, $endDate, $startTs, $endTs);
$assigneeRatingMetrics = report_get_assignee_rating_metrics($pdo, $reportUserIds, $startTs, $endTs);
$subtaskMetrics = report_get_subtask_score_metrics($pdo, $reportUserIds, $startTs, $endTs);
$attendanceMetrics = report_get_attendance_metrics($pdo, $reportUserIds, $startDate, $endDate);
$captureMetrics = report_get_capture_metrics($pdo, $reportUserIds, $startDate, $endDate);
$attendanceDaysMap = report_get_attendance_days($pdo, $reportUserIds, $startDate, $endDate);
$adjustmentsReady = attendance_adjustment_ensure_schema($pdo);
$attendanceAdjustments = $adjustmentsReady
    ? attendance_adjustment_get_range_map($pdo, $reportUserIds, $startDate, $endDate)
    : [];

$reportRows = [];
$overall = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'completed_on_time' => 0,
    'overdue' => 0,
    'unrated_completed' => 0,
    'task_rating_sum' => 0,
    'task_rating_count' => 0,
    'assignee_rating_sum' => 0,
    'assignee_rating_count' => 0,
    'subtask_score_sum' => 0,
    'subtask_score_count' => 0,
    'hours' => 0,
    'deducted' => 0,
    'days' => 0,
    'captures' => 0,
];

foreach ($filteredUsers as $user) {
    $uid = (int)$user['id'];
    $taskRow = $taskMetrics[$uid] ?? [];
    $assigneeRow = $assigneeRatingMetrics[$uid] ?? [];
    $subtaskRow = $subtaskMetrics[$uid] ?? [];
    $attendanceRow = $attendanceMetrics[$uid] ?? [];
    $captureRow = $captureMetrics[$uid] ?? [];

    $pending = (int)($taskRow['pending_count'] ?? 0);
    $inProgress = (int)($taskRow['in_progress_count'] ?? 0);
    $completed = (int)($taskRow['completed_count'] ?? 0);
    $completedOnTime = (int)($taskRow['completed_on_time'] ?? 0);
    $overdue = (int)($taskRow['overdue_count'] ?? 0);
    $unratedCompleted = (int)($taskRow['unrated_completed'] ?? 0);

    $taskRatingSum = (float)($taskRow['task_rating_sum'] ?? 0);
    $taskRatingCount = (int)($taskRow['task_rating_count'] ?? 0);
    $taskRatingAvg = $taskRatingCount > 0 ? $taskRatingSum / $taskRatingCount : null;

    $assigneeRatingSum = (float)($assigneeRow['rating_sum'] ?? 0);
    $assigneeRatingCount = (int)($assigneeRow['rating_count'] ?? 0);
    $assigneeRatingAvg = $assigneeRatingCount > 0 ? $assigneeRatingSum / $assigneeRatingCount : null;

    $subtaskScoreSum = (float)($subtaskRow['score_sum'] ?? 0);
    $subtaskScoreCount = (int)($subtaskRow['score_count'] ?? 0);
    $subtaskScoreAvg = $subtaskScoreCount > 0 ? $subtaskScoreSum / $subtaskScoreCount : null;

    $hoursRaw = (float)($attendanceRow['total_hours'] ?? 0);
    $deductedHours = (float)(($attendanceAdjustments[$uid]['hours_deducted'] ?? 0) ?: 0);
    if ($deductedHours < 0) {
        $deductedHours = 0;
    }
    $hours = max(0, $hoursRaw - $deductedHours);
    $days = (int)($attendanceRow['days_count'] ?? 0);
    $captures = (int)($captureRow['capture_count'] ?? 0);

    $captureRate = null;
    if ($hours > 0) {
        $captureRate = $captures / $hours;
    }

    $onTimeRate = null;
    if ($completed > 0) {
        $onTimeRate = round(($completedOnTime / $completed) * 100);
    }

    $reportRows[] = [
        'user' => $user,
        'pending' => $pending,
        'in_progress' => $inProgress,
        'completed' => $completed,
        'overdue' => $overdue,
        'on_time_rate' => $onTimeRate,
        'avg_task_rating' => $taskRatingAvg,
        'avg_assignee_rating' => $assigneeRatingAvg,
        'avg_subtask_score' => $subtaskScoreAvg,
        'hours' => $hours,
        'hours_raw' => $hoursRaw,
        'deducted' => $deductedHours,
        'days' => $days,
        'captures' => $captures,
        'capture_rate' => $captureRate,
        'unrated_completed' => $unratedCompleted,
    ];

    $overall['pending'] += $pending;
    $overall['in_progress'] += $inProgress;
    $overall['completed'] += $completed;
    $overall['completed_on_time'] += $completedOnTime;
    $overall['overdue'] += $overdue;
    $overall['unrated_completed'] += $unratedCompleted;
    $overall['task_rating_sum'] += $taskRatingSum;
    $overall['task_rating_count'] += $taskRatingCount;
    $overall['assignee_rating_sum'] += $assigneeRatingSum;
    $overall['assignee_rating_count'] += $assigneeRatingCount;
    $overall['subtask_score_sum'] += $subtaskScoreSum;
    $overall['subtask_score_count'] += $subtaskScoreCount;
    $overall['hours'] += $hours;
    $overall['deducted'] += $deductedHours;
    $overall['days'] += $days;
    $overall['captures'] += $captures;
}

$reportRowByUserId = [];
foreach ($reportRows as $row) {
    $uid = (int)($row['user']['id'] ?? 0);
    if ($uid > 0) {
        $reportRowByUserId[$uid] = $row;
    }
}

$userVisuals = [];
$userIndex = 0;
foreach ($filteredUsers as $user) {
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) {
        continue;
    }
    $name = trim((string)($user['full_name'] ?? ''));
    if ($name === '') {
        $name = 'User #' . $uid;
    }
    $userVisuals[$uid] = [
        'initials' => report_get_initials($name),
        'color' => report_get_color_for_index($userIndex),
    ];
    $userIndex++;
}

$rangeDates = [];
if ($startDate && $endDate) {
    for ($d = strtotime($startDate); $d <= strtotime($endDate); $d = strtotime('+1 day', $d)) {
        $rangeDates[] = date('Y-m-d', $d);
    }
}
if (count($rangeDates) > 31) {
    $rangeDates = array_slice($rangeDates, 0, 31);
}
$rangeLabel = $startDate ? date('M', strtotime($startDate)) : '';

$overallTaskRatingAvg = $overall['task_rating_count'] > 0
    ? $overall['task_rating_sum'] / $overall['task_rating_count']
    : null;
$overallAssigneeRatingAvg = $overall['assignee_rating_count'] > 0
    ? $overall['assignee_rating_sum'] / $overall['assignee_rating_count']
    : null;
$overallSubtaskScoreAvg = $overall['subtask_score_count'] > 0
    ? $overall['subtask_score_sum'] / $overall['subtask_score_count']
    : null;
$overallOnTimeRate = $overall['completed'] > 0
    ? round(($overall['completed_on_time'] / $overall['completed']) * 100)
    : null;
$overallCaptureRate = $overall['hours'] > 0
    ? $overall['captures'] / $overall['hours']
    : null;

$dtrRows = [];
$dtrTotals = ['raw' => 0, 'deducted' => 0, 'net' => 0];
$dtrUser = null;
$dtrHasSegmented = false;
$dtrAdjustments = [];
$dtrMonthLabel = report_format_range($startDate, $endDate);
if ($startDate && $endDate && substr($startDate, 0, 7) === substr($endDate, 0, 7)) {
    $dtrMonthLabel = date('F Y', strtotime($startDate));
}
if ($dtrUserId > 0) {
    foreach ($allUsers as $u) {
        if ((int)$u['id'] === $dtrUserId) {
            $dtrUser = $u;
            break;
        }
    }
}

if ($dtrUser && !in_array($dtrUserId, $reportUserIds, true)) {
    $dtrUser = null;
    $dtrUserId = 0;
}

if ($dtrUserId > 0 && $dtrUser) {
    $dtrHasSegmented = tenant_column_exists($pdo, 'attendance', 'morning_in')
        && tenant_column_exists($pdo, 'attendance', 'morning_out')
        && tenant_column_exists($pdo, 'attendance', 'afternoon_in')
        && tenant_column_exists($pdo, 'attendance', 'afternoon_out')
        && tenant_column_exists($pdo, 'attendance', 'overtime_in')
        && tenant_column_exists($pdo, 'attendance', 'overtime_out');

    $dtrAdjustments = $adjustmentsReady
        ? attendance_adjustment_get_daily_map($pdo, $dtrUserId, $startDate, $endDate)
        : [];

    $fields = $dtrHasSegmented
        ? "att_date, morning_in, morning_out, afternoon_in, afternoon_out, overtime_in, overtime_out, total_hours"
        : "att_date, time_in, time_out, total_hours";

    $sql = "SELECT $fields FROM attendance WHERE user_id = ? AND att_date BETWEEN ? AND ?";
    $params = [$dtrUserId, $startDate, $endDate];
    $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);
    $sql .= " ORDER BY att_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byDate = [];
    foreach ($rows as $row) {
        $dateKey = (string)($row['att_date'] ?? '');
        if ($dateKey === '') {
            continue;
        }
        if (!isset($byDate[$dateKey])) {
            $byDate[$dateKey] = [
                'time_in' => null,
                'time_out' => null,
                'morning_in' => null,
                'morning_out' => null,
                'afternoon_in' => null,
                'afternoon_out' => null,
                'overtime_in' => null,
                'overtime_out' => null,
                'raw_hours' => 0,
            ];
        }

        $byDate[$dateKey]['raw_hours'] += (float)($row['total_hours'] ?? 0);

        if ($dtrHasSegmented) {
            foreach (['morning_in', 'afternoon_in', 'overtime_in'] as $field) {
                $candidate = trim((string)($row[$field] ?? ''));
                if ($candidate === '' || $candidate === '00:00:00') {
                    continue;
                }
                $current = $byDate[$dateKey][$field];
                if ($current === null || strtotime($candidate) < strtotime($current)) {
                    $byDate[$dateKey][$field] = $candidate;
                }
            }
            foreach (['morning_out', 'afternoon_out', 'overtime_out'] as $field) {
                $candidate = trim((string)($row[$field] ?? ''));
                if ($candidate === '' || $candidate === '00:00:00') {
                    continue;
                }
                $current = $byDate[$dateKey][$field];
                if ($current === null || strtotime($candidate) > strtotime($current)) {
                    $byDate[$dateKey][$field] = $candidate;
                }
            }
        } else {
            $timeIn = trim((string)($row['time_in'] ?? ''));
            if ($timeIn !== '') {
                $currentIn = $byDate[$dateKey]['time_in'];
                if ($currentIn === null || strtotime($timeIn) < strtotime($currentIn)) {
                    $byDate[$dateKey]['time_in'] = $timeIn;
                }
            }
            $timeOut = trim((string)($row['time_out'] ?? ''));
            if ($timeOut !== '' && $timeOut !== '00:00:00') {
                $currentOut = $byDate[$dateKey]['time_out'];
                if ($currentOut === null || strtotime($timeOut) > strtotime($currentOut)) {
                    $byDate[$dateKey]['time_out'] = $timeOut;
                }
            }
        }
    }

    for ($d = strtotime($startDate); $d <= strtotime($endDate); $d = strtotime('+1 day', $d)) {
        $dateKey = date('Y-m-d', $d);
        $row = $byDate[$dateKey] ?? [
            'time_in' => null,
            'time_out' => null,
            'morning_in' => null,
            'morning_out' => null,
            'afternoon_in' => null,
            'afternoon_out' => null,
            'overtime_in' => null,
            'overtime_out' => null,
            'raw_hours' => 0,
        ];

        $deducted = (float)($dtrAdjustments[$dateKey]['hours_deducted'] ?? 0);
        if ($deducted < 0) {
            $deducted = 0;
        }
        $net = max(0, (float)$row['raw_hours'] - $deducted);

        $dtrRows[] = [
            'date' => $dateKey,
            'day_label' => date('M j, D', $d),
            'time_in' => $row['time_in'],
            'time_out' => $row['time_out'],
            'morning_in' => $row['morning_in'],
            'morning_out' => $row['morning_out'],
            'afternoon_in' => $row['afternoon_in'],
            'afternoon_out' => $row['afternoon_out'],
            'overtime_in' => $row['overtime_in'],
            'overtime_out' => $row['overtime_out'],
            'raw_hours' => (float)$row['raw_hours'],
            'deducted' => $deducted,
            'net_hours' => $net,
            'reason' => trim((string)($dtrAdjustments[$dateKey]['reason'] ?? '')),
        ];

        $dtrTotals['raw'] += (float)$row['raw_hours'];
        $dtrTotals['deducted'] += $deducted;
        $dtrTotals['net'] += $net;
    }
}

$selectedMetrics = null;
$selectedVisual = null;
if ($dtrUserId > 0 && $dtrUser) {
    $selectedMetrics = $reportRowByUserId[$dtrUserId] ?? null;
    $selectedVisual = $userVisuals[$dtrUserId] ?? null;
}
$dtrPrintMonthLabel = '';
if ($monthInput) {
    $dtrPrintMonthLabel = strtoupper(date('F Y', strtotime($monthInput . '-01')));
} elseif ($startDate) {
    $dtrPrintMonthLabel = strtoupper(date('F Y', strtotime($startDate)));
} elseif ($dtrMonthLabel) {
    $dtrPrintMonthLabel = strtoupper((string)$dtrMonthLabel);
}
$queryBase = [
    'start' => $startDate,
    'end' => $endDate,
];
if ($monthInput) {
    $queryBase['month'] = $monthInput;
}
if ($groupId > 0) {
    $queryBase['group_id'] = $groupId;
}
if ($userId > 0) {
    $queryBase['user_id'] = $userId;
}
$queryBaseNoUser = $queryBase;
unset($queryBaseNoUser['dtr_user_id']);
$overviewLink = 'reports.php?' . http_build_query($queryBaseNoUser) . '#dtrSection';

$prevMonthLink = null;
$nextMonthLink = null;
if ($dtrUserId > 0 && $dtrUser) {
    $monthSource = $monthInput ?: ($startDate ? substr($startDate, 0, 7) : null);
    $monthDate = $monthSource ? DateTime::createFromFormat('Y-m', $monthSource) : null;
    if ($monthDate instanceof DateTime) {
        $prevMonth = (clone $monthDate)->modify('-1 month');
        $nextMonth = (clone $monthDate)->modify('+1 month');
        $prevQuery = $queryBase;
        $prevQuery['month'] = $prevMonth->format('Y-m');
        $prevQuery['start'] = $prevMonth->format('Y-m-01');
        $prevQuery['end'] = $prevMonth->format('Y-m-t');
        $nextQuery = $queryBase;
        $nextQuery['month'] = $nextMonth->format('Y-m');
        $nextQuery['start'] = $nextMonth->format('Y-m-01');
        $nextQuery['end'] = $nextMonth->format('Y-m-t');
        $prevQuery['dtr_user_id'] = $dtrUserId;
        $nextQuery['dtr_user_id'] = $dtrUserId;
        $prevMonthLink = 'reports.php?' . http_build_query($prevQuery) . '#dtrSection';
        $nextMonthLink = 'reports.php?' . http_build_query($nextQuery) . '#dtrSection';
    }
}

$exportType = isset($_GET['export']) ? (string)$_GET['export'] : '';
if (!$isAdmin) {
    $exportType = '';
}
if ($exportType === 'dtr_csv' && $dtrUserId > 0 && !empty($dtrRows)) {
    $filename = "dtr_" . $dtrUserId . "_" . $startDate . "_to_" . $endDate . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    $csvWidth = 10;
    $emit = function (array $cells) use ($out, $csvWidth) {
        if (count($cells) < $csvWidth) {
            $cells = array_pad($cells, $csvWidth, '');
        }
        fputcsv($out, $cells);
    };
    $formatRatio = function ($value, $precision = 2) {
        if ($value === null || $value === '') {
            return '';
        }
        $num = number_format((float)$value, $precision, '.', '');
        $num = rtrim(rtrim($num, '0'), '.');
        return $num === '' ? '0' : $num;
    };
    $pickTime = function (array $times, $mode) {
        $valid = [];
        foreach ($times as $time) {
            $time = trim((string)$time);
            if ($time === '' || $time === '00:00:00') {
                continue;
            }
            $valid[] = $time;
        }
        if (!$valid) {
            return '';
        }
        $sorted = $valid;
        usort($sorted, function ($a, $b) use ($mode) {
            $cmp = strtotime($a) <=> strtotime($b);
            return $mode === 'latest' ? -$cmp : $cmp;
        });
        return $sorted[0] ?? '';
    };
    $formatTime = function ($time) {
        $time = trim((string)$time);
        if ($time === '' || $time === '00:00:00') {
            return '';
        }
        $ts = strtotime($time);
        if ($ts === false) {
            return '';
        }
        return date('h:i A', $ts);
    };

    $employeeName = trim((string)($dtrUser['full_name'] ?? 'Employee'));
    if ($employeeName === '') {
        $employeeName = 'Employee';
    }
    $employeeEmail = trim((string)($dtrUser['username'] ?? ''));
    $periodLabel = $dtrMonthLabel;
    $activeDays = 0;
    foreach ($dtrRows as $row) {
        if ((float)$row['raw_hours'] > 0) {
            $activeDays++;
        }
    }

    $emit([strtoupper($workspaceName)]);
    $emit(['DAILY TIME RECORD - ' . strtoupper($periodLabel)]);
    $emit([]);
    $emit([]);
    $emit(['', 'EMPLOYEE', '', '', 'EMAIL', '', '', 'PERIOD']);
    $emit(['', $employeeName, '', '', $employeeEmail, '', '', $periodLabel]);
    $emit([]);
    $emit([]);
    $emit([]);
    $emit(['', 'ACTIVE DAYS', '', 'GROSS TOTAL', '', 'DEDUCTED', '', 'NET TOTAL']);
    $emit(['', $activeDays, '', $formatRatio($dtrTotals['raw'], 2), '', $formatRatio($dtrTotals['deducted'], 2), '', $formatRatio($dtrTotals['net'], 2)]);
    $emit([]);
    $emit([]);
    $emit([]);
    $emit(['', 'DATE', 'DAY', 'TIME IN', 'TIME OUT', 'GROSS HRS', 'DEDUCTED', 'NET HRS', 'STATUS']);

    foreach ($dtrRows as $row) {
        $dateKey = $row['date'];
        $dateLabel = date('M j', strtotime($dateKey));
        $dayLabel = date('D', strtotime($dateKey));
        if ($dtrHasSegmented) {
            $timeInRaw = $pickTime([$row['morning_in'], $row['afternoon_in'], $row['overtime_in']], 'earliest');
            $timeOutRaw = $pickTime([$row['morning_out'], $row['afternoon_out'], $row['overtime_out']], 'latest');
        } else {
            $timeInRaw = $row['time_in'];
            $timeOutRaw = $row['time_out'];
        }
        $timeIn = $formatTime($timeInRaw);
        $timeOut = $formatTime($timeOutRaw);
        if ($timeIn === '') {
            $timeIn = '-';
        }
        if ($timeOut === '') {
            $timeOut = '-';
        }
        $isWeekend = date('N', strtotime($dateKey)) >= 6;
        $status = $isWeekend ? 'Weekend' : ((float)$row['raw_hours'] > 0 ? 'Present' : 'Absent');
        $emit([
            '',
            $dateLabel,
            $dayLabel,
            $timeIn,
            $timeOut,
            $formatRatio($row['raw_hours'], 2),
            $formatRatio($row['deducted'], 2),
            $formatRatio($row['net_hours'], 2),
            $status,
        ]);
    }

    $emit(['', 'TOTALS', '', '', '', $formatRatio($dtrTotals['raw'], 2), $formatRatio($dtrTotals['deducted'], 2), $formatRatio($dtrTotals['net'], 2), $activeDays]);
    $emit(['', '', '', '', '', 'Gross', 'Deducted', 'Net Hrs', 'Active Days']);
    $emit([]);
    $emit([]);
    $emit(['', '', '', '', '', '', $employeeName]);
    $emit(['', 'Prepared by', '', '', '', '', 'Employee Signature']);

    fclose($out);
    exit;
}

if ($exportType === 'dtr_pdf') {
    if (!$dtrUser || empty($dtrRows)) {
        header('Location: reports.php?error=Select a user and date range for DTR export.');
        exit;
    }

    $dtrPdfTitle = htmlspecialchars($dtrUser['full_name'] ?? 'Employee', ENT_QUOTES);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>DTR PDF | <?= $dtrPdfTitle ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="css/reports-page.css">
        <style>
            body { background: #fff; }
            .report-dtr-card { border: none; }
            .report-dtr-header, .report-dtr-actions { display: none !important; }
            .dtr-editable { border-bottom: 1px solid #111827; }
            @page { size: A4; margin: 12mm; }
            @media print {
                .report-dtr-table th:last-child,
                .report-dtr-table td:last-child {
                    display: table-cell !important;
                }
            }
        </style>
    </head>
    <body class="reports-page print-dtr-only">
        <div class="report-dtr-card">
            <div class="dtr-form-header">
                <div class="dtr-company-name dtr-editable" contenteditable="true" data-placeholder="Company Name"><?= htmlspecialchars($workspaceName) ?></div>
                <?php if ($workspaceAddress !== '') { ?>
                    <div class="dtr-company-address dtr-editable" contenteditable="true" data-placeholder="Company Address"><?= htmlspecialchars($workspaceAddress) ?></div>
                <?php } else { ?>
                    <div class="dtr-company-address dtr-editable dtr-address-empty" contenteditable="true" data-placeholder="Company Address">&nbsp;</div>
                <?php } ?>
                <div class="dtr-form-title">DAILY TIME RECORD</div>
                <div class="dtr-meta-grid">
                    <div class="dtr-meta-row">
                        <span>Name:</span>
                        <span class="dtr-meta-value"><?= htmlspecialchars($dtrUser['full_name'] ?? 'Employee') ?></span>
                    </div>
                    <div class="dtr-meta-row">
                        <span>Department:</span>
                        <span class="dtr-editable" contenteditable="true" data-placeholder="Department">Department</span>
                    </div>
                    <div class="dtr-meta-row">
                        <span>Month:</span>
                        <span class="dtr-meta-value"><?= htmlspecialchars($dtrMonthLabel) ?></span>
                    </div>
                </div>
            </div>
            <div class="report-dtr-table-wrap">
                <table class="report-dtr-table">
                    <thead>
                        <?php if ($dtrHasSegmented) { ?>
                            <tr>
                                <th rowspan="2">Date</th>
                                <th colspan="2">Morning</th>
                                <th colspan="2">Afternoon</th>
                                <th colspan="2">Overtime</th>
                                <th rowspan="2">Daily Total</th>
                                <th rowspan="2">Deducted</th>
                                <th rowspan="2">Net Total</th>
                                <th rowspan="2">Signature</th>
                                <th rowspan="2">Reason</th>
                            </tr>
                            <tr>
                                <th>In</th>
                                <th>Out</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>In</th>
                                <th>Out</th>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Daily Total</th>
                                <th>Deducted</th>
                                <th>Net Total</th>
                                <th>Signature</th>
                                <th>Reason</th>
                            </tr>
                        <?php } ?>
                    </thead>
                    <tbody>
                        <?php foreach ($dtrRows as $row) {
                            $deductedValue = number_format((float)$row['deducted'], 2);
                            $netValue = number_format((float)$row['net_hours'], 2);
                            $rawValue = number_format((float)$row['raw_hours'], 2);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['day_label']) ?></td>
                            <?php if ($dtrHasSegmented) { ?>
                                <td><?= report_format_time($row['morning_in']) ?></td>
                                <td><?= report_format_time($row['morning_out']) ?></td>
                                <td><?= report_format_time($row['afternoon_in']) ?></td>
                                <td><?= report_format_time($row['afternoon_out']) ?></td>
                                <td><?= report_format_time($row['overtime_in']) ?></td>
                                <td><?= report_format_time($row['overtime_out']) ?></td>
                            <?php } else { ?>
                                <td><?= report_format_time($row['time_in']) ?></td>
                                <td><?= report_format_time($row['time_out']) ?></td>
                            <?php } ?>
                            <td><?= $rawValue ?></td>
                            <td><?= $deductedValue ?></td>
                            <td><?= $netValue ?></td>
                            <td class="dtr-sign-cell"></td>
                            <td class="dtr-reason-cell"><?= htmlspecialchars($row['reason']) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="<?= $dtrHasSegmented ? 7 : 3 ?>">Totals</th>
                            <th><?= number_format($dtrTotals['raw'], 2) ?></th>
                            <th><?= number_format($dtrTotals['deducted'], 2) ?></th>
                            <th><?= number_format($dtrTotals['net'], 2) ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="dtr-signature-blocks">
                <div class="dtr-signature">
                    <div class="dtr-sign-line"></div>
                    <div class="dtr-sign-label">Prepared By</div>
                </div>
            </div>
        </div>
        <script>
            window.print();
        </script>
    </body>
    </html>
    <?php
    exit;
}

if ($exportType === 'csv') {
    $filename = "report_" . $startDate . "_to_" . $endDate . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    $csvWidth = 16;
    $emit = function (array $cells) use ($out, $csvWidth) {
        if (count($cells) < $csvWidth) {
            $cells = array_pad($cells, $csvWidth, '');
        }
        fputcsv($out, $cells);
    };
    $formatNumber = function ($value, $precision = 2) {
        if ($value === null || $value === '') {
            return '';
        }
        $num = number_format((float)$value, $precision, '.', '');
        $num = rtrim(rtrim($num, '0'), '.');
        return $num === '' ? '0' : $num;
    };
    $formatOnTime = function ($percent, $precision = 3) use ($formatNumber) {
        if ($percent === null || $percent === '') {
            return '';
        }
        return $formatNumber(((float)$percent) / 100, $precision);
    };

    $rangeLabel = report_format_range($startDate, $endDate);
    if ($startDate && $endDate && substr($startDate, 0, 7) === substr($endDate, 0, 7)) {
        $rangeLabel = date('M', strtotime($startDate)) . ' ' . date('j', strtotime($startDate)) . '-' . date('j', strtotime($endDate)) . ', ' . date('Y', strtotime($startDate));
    }
    $emit([strtoupper($workspaceName)]);
    $emit(['PER-USER BREAKDOWN - WEEKLY PERFORMANCE & UTILIZATION - ' . strtoupper($rangeLabel) . ' - ' . strtoupper((string)date_default_timezone_get())]);
    $emit([]);
    $emit([]);
    $emit(['', 'TOTAL USERS', '', 'TOTAL COMPLETED', '', 'TOTAL OVERDUE', '', 'TOTAL PENDING', '', 'TOTAL NET HRS', '', 'TOTAL DEDUCTED HRS', '', 'AVG TASK RATING']);
    $emit([
        '',
        count($reportRows),
        '',
        $overall['completed'],
        '',
        $overall['overdue'],
        '',
        $overall['pending'],
        '',
        $formatNumber($overall['hours'], 2),
        '',
        $formatNumber($overall['deducted'], 2),
        '',
        $formatNumber($overallTaskRatingAvg, 2),
    ]);
    $emit([]);
    $emit([]);
    $emit([]);
    $emit(['', 'USER PERFORMANCE DETAILS']);
    $emit(['', 'Use Excel filters (Data -> Filter) to sort and filter by any column']);
    $emit([]);
    $emit(['', 'IDENTITY', '', '', 'TASK METRICS', '', '', '', 'QUALITY SCORES', '', '', '', 'TIME & HOURS']);
    $emit([]);
    $emit(['', '#', 'NAME', 'EMAIL', 'COMPLETED', 'PENDING', 'IN PROGRESS', 'OVERDUE', 'ON-TIME %', 'AVG RATING', 'ASSIGNEE RTG', 'SUBTASK SCORE', 'NET HOURS', 'DEDUCTED', 'CAP / HR']);

    $overallOnTimeFraction = $overall['completed'] > 0 ? ($overall['completed_on_time'] / $overall['completed']) : null;

    $rowIndex = 1;
    foreach ($reportRows as $row) {
        $user = $row['user'];
        $name = trim((string)($user['full_name'] ?? ''));
        if ($name === '') {
            $name = 'User #' . (int)($user['id'] ?? 0);
        }
        $email = trim((string)($user['username'] ?? ''));
        $emit([
            '',
            $rowIndex,
            $name,
            $email,
            $row['completed'],
            $row['pending'],
            $row['in_progress'],
            $row['overdue'],
            $formatOnTime($row['on_time_rate'], 3),
            $row['avg_task_rating'] === null ? '-' : $formatNumber($row['avg_task_rating'], 1),
            $row['avg_assignee_rating'] === null ? '-' : $formatNumber($row['avg_assignee_rating'], 1),
            $row['avg_subtask_score'] === null ? '-' : $formatNumber($row['avg_subtask_score'], 1),
            $formatNumber($row['hours'], 2),
            $formatNumber($row['deducted'], 2),
            $row['capture_rate'] === null ? '' : $formatNumber($row['capture_rate'], 2),
        ]);
        $rowIndex++;
    }

    $emit([
        '',
        'TOTALS - ALL USERS',
        '',
        '',
        $overall['completed'],
        $overall['pending'],
        $overall['in_progress'],
        $overall['overdue'],
        $formatNumber($overallOnTimeFraction, 3),
        $formatNumber($overallTaskRatingAvg, 2),
        $formatNumber($overallAssigneeRatingAvg, 2),
        $formatNumber($overallSubtaskScoreAvg, 1),
        $formatNumber($overall['hours'], 2),
        $formatNumber($overall['deducted'], 2),
        $formatNumber($overallCaptureRate, 3),
    ]);
    $emit([
        '',
        '',
        '',
        '',
        'Completed',
        'Pending',
        'In Progress',
        'Overdue',
        'Avg On-Time',
        'Avg Rating',
        'Avg Asgn Rtg',
        'Avg Subtask',
        'Total Net Hrs',
        'Total Deducted',
        'Avg Cap/Hr',
    ]);
    fclose($out);
    exit;
}

$exportLink = null;
$dtrExportLink = null;
$dtrPdfLink = null;
if ($isAdmin) {
    $exportLink = 'reports.php?' . http_build_query(array_merge($queryBase, ['export' => 'csv']));
    if ($dtrUserId > 0 && $dtrUser) {
        $dtrExportLink = 'reports.php?' . http_build_query(array_merge($queryBase, ['export' => 'dtr_csv', 'dtr_user_id' => $dtrUserId]));
    }
    if ($dtrUserId > 0 && $dtrUser) {
        $dtrPdfLink = 'reports.php?' . http_build_query(array_merge($queryBase, ['export' => 'dtr_pdf', 'dtr_user_id' => $dtrUserId]));
    }
}
$dtrCsrfToken = csrf_token('attendance_deduction_form');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/reports-page.css">
</head>
<body class="reports-page">
    <?php include "inc/new_sidebar.php"; ?>

    <div class="dash-main">
        <div class="page-body reports-shell">
            <?php if ($isAdmin) { ?>
            <div class="report-header reports-header">
                <div class="report-header-left">
                    <h1>Weekly Performance &amp; Utilization</h1>
                    <p>
                        <?= htmlspecialchars(report_format_range($startDate, $endDate)) ?>
                        <span class="tz-badge"><i class="fa fa-globe"></i> Asia/Manila</span>
                    </p>
                </div>
                <div class="report-actions reports-actions">
                    <a class="btn btn-outline" href="<?= htmlspecialchars($exportLink, ENT_QUOTES) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export CSV
                    </a>
                    <button type="button" class="btn btn-outline" onclick="window.print()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print
                    </button>
                </div>
            </div>

            <form class="filters-card reports-filters" method="GET" action="reports.php">
                <div class="filter-group report-field">
                    <label for="startDate">Start Date</label>
                    <input type="date" id="startDate" name="start" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="filter-group report-field">
                    <label for="endDate">End Date</label>
                    <input type="date" id="endDate" name="end" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="filter-group report-field">
                    <label for="monthPicker">Month</label>
                    <input type="month" id="monthPicker" name="month" value="<?= htmlspecialchars($monthInput ?? '') ?>">
                </div>
                <div class="filter-group report-field">
                    <label for="groupSelect">Group</label>
                    <select id="groupSelect" name="group_id">
                        <option value="0">All groups</option>
                        <?php foreach ($groups as $group) {
                            $gid = (int)($group['id'] ?? 0);
                            if ($gid <= 0) {
                                continue;
                            }
                        ?>
                            <option value="<?= $gid ?>" <?= $gid === $groupId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name'] ?? ('Group #' . $gid)) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filter-group report-field">
                    <label for="userSelect">User</label>
                    <select id="userSelect" name="user_id">
                        <option value="0">All users</option>
                        <?php foreach ($userOptions as $optUser) {
                            $uid = (int)($optUser['id'] ?? 0);
                            if ($uid <= 0) {
                                continue;
                            }
                            $label = trim((string)($optUser['full_name'] ?? ''));
                            if ($label === '') {
                                $label = 'User #' . $uid;
                            }
                        ?>
                            <option value="<?= $uid ?>" <?= $uid === $userId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filter-actions report-field-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Apply Filters
                    </button>
                    <a class="btn btn-ghost" href="reports.php">Reset</a>
                </div>
            </form>

            <?php if (isset($_GET['success'])) { ?>
                <div class="reports-alert reports-alert-success">
                    <i class="fa fa-check-circle"></i>
                    <?= htmlspecialchars((string)$_GET['success']) ?>
                </div>
            <?php } ?>

            <?php if (isset($_GET['error'])) { ?>
                <div class="reports-alert reports-alert-error">
                    <i class="fa fa-exclamation-triangle"></i>
                    <?= htmlspecialchars((string)$_GET['error']) ?>
                </div>
            <?php } ?>

            <?php if (empty($reportRows)) { ?>
                <div class="reports-alert">
                    <i class="fa fa-info-circle"></i>
                    No users match the selected filters for this date range.
                </div>
            <?php } ?>

            <div class="metrics-grid reports-stats-grid">
                <div class="metric-card stat-card">
                    <div class="metric-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <div class="metric-label">Tasks Completed</div>
                        <div class="metric-value"><?= number_format($overall['completed']) ?></div>
                        <div class="metric-sub"><?= $overallOnTimeRate === null ? 'No completions yet' : ($overallOnTimeRate . '% on time') ?></div>
                    </div>
                </div>
                <div class="metric-card stat-card">
                    <div class="metric-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <div class="metric-label">Tasks In Progress</div>
                        <div class="metric-value"><?= number_format($overall['in_progress']) ?></div>
                        <div class="metric-sub"><?= number_format($overall['pending']) ?> pending in range</div>
                    </div>
                </div>
                <div class="metric-card stat-card">
                    <div class="metric-icon red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <div>
                        <div class="metric-label">Overdue Tasks</div>
                        <div class="metric-value danger"><?= number_format($overall['overdue']) ?></div>
                        <div class="metric-sub"><?= number_format($overall['unrated_completed']) ?> completed but unrated</div>
                    </div>
                </div>
                <div class="metric-card stat-card">
                    <div class="metric-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <div class="metric-label">Total Hours (Net)</div>
                        <div class="metric-value"><?= report_format_value($overall['hours'], 2, '0.00') ?></div>
                        <div class="metric-sub">
                            <?= number_format($overall['days']) ?> active day<?= $overall['days'] === 1 ? '' : 's' ?> &middot;
                            <?= report_format_value($overall['deducted'], 2, '0.00') ?> hrs deducted
                        </div>
                    </div>
                </div>
                <div class="metric-card stat-card">
                    <div class="metric-icon amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <div>
                        <div class="metric-label">Avg Task Rating</div>
                        <div class="metric-value"><?= report_format_value($overallTaskRatingAvg, 1) ?></div>
                        <div class="metric-sub"><?= number_format($overall['task_rating_count']) ?> rated tasks</div>
                    </div>
                </div>
                <div class="metric-card stat-card">
                    <div class="metric-icon violet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                    </div>
                    <div>
                        <div class="metric-label">Captures Per Hour</div>
                        <div class="metric-value"><?= report_format_value($overallCaptureRate, 2) ?></div>
                        <div class="metric-sub"><?= number_format($overall['captures']) ?> total captures</div>
                    </div>
                </div>
            </div>

            <div class="table-card report-table-card">
                <div class="table-card-header report-table-header">
                    <div>
                        <div class="section-title">Per-User Breakdown</div>
                        <div class="section-sub">Performance, time, and quality metrics for the selected range.</div>
                    </div>
                    <span class="badge badge-purple">
                        <?= number_format(count($reportRows)) ?> user<?= count($reportRows) === 1 ? '' : 's' ?>
                    </span>
                </div>
                <div class="table-scroll report-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Completed</th>
                                <th>Pending</th>
                                <th>In Progress</th>
                                <th>Overdue</th>
                                <th>On-Time %</th>
                                <th>Avg Rating</th>
                                <th>Assignee Rating</th>
                                <th>Subtask Score</th>
                                <th>Net Hours</th>
                                <th>Deducted</th>
                                <th>Cap/Hr</th>
                                <th>Profile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportRows as $row) {
                                $user = $row['user'];
                                $uid = (int)($user['id'] ?? 0);
                                $name = trim((string)($user['full_name'] ?? ''));
                                if ($name === '') {
                                    $name = 'User #' . $uid;
                                }
                                $profileLink = $uid > 0 ? ("user_details.php?id=" . urlencode((string)$uid)) : '';
                                $viewDtrLink = $uid > 0
                                    ? ('reports.php?' . http_build_query(array_merge($queryBaseNoUser, ['dtr_user_id' => $uid])) . '#dtrSection')
                                    : '#';
                                $userTitle = 'Captures: ' . number_format($row['captures']) . ' | Needs rating: ' . number_format($row['unrated_completed']);
                            ?>
                            <tr>
                                <td>
                                    <div class="user-cell" title="<?= htmlspecialchars($userTitle, ENT_QUOTES) ?>">
                                        <div class="user-name">
                                            <?php if ($profileLink !== '') { ?>
                                                <a class="user-link" href="<?= htmlspecialchars($profileLink, ENT_QUOTES) ?>"><?= htmlspecialchars($name) ?></a>
                                            <?php } else { ?>
                                                <?= htmlspecialchars($name) ?>
                                            <?php } ?>
                                        </div>
                                        <div class="user-email"><?= htmlspecialchars($user['username'] ?? '') ?></div>
                                    </div>
                                </td>
                                <td class="<?= $row['completed'] > 0 ? '' : 'num-zero' ?>"><?= number_format($row['completed']) ?></td>
                                <td class="<?= $row['pending'] > 0 ? '' : 'num-zero' ?>"><?= number_format($row['pending']) ?></td>
                                <td class="<?= $row['in_progress'] > 0 ? '' : 'num-zero' ?>"><?= number_format($row['in_progress']) ?></td>
                                <td>
                                    <?php if ($row['overdue'] > 0) { ?>
                                        <span class="chip chip-red"><?= number_format($row['overdue']) ?></span>
                                    <?php } else { ?>
                                        <span class="chip chip-grey">0</span>
                                    <?php } ?>
                                </td>
                                <td class="<?= $row['on_time_rate'] === null ? 'num-zero' : '' ?>">
                                    <?= $row['on_time_rate'] === null ? '&mdash;' : ($row['on_time_rate'] . '%') ?>
                                </td>
                                <td class="<?= $row['avg_task_rating'] === null ? 'num-zero' : '' ?>">
                                    <?= report_format_value($row['avg_task_rating'], 1) ?>
                                </td>
                                <td class="<?= $row['avg_assignee_rating'] === null ? 'num-zero' : '' ?>">
                                    <?= report_format_value($row['avg_assignee_rating'], 1) ?>
                                </td>
                                <td class="<?= $row['avg_subtask_score'] === null ? 'num-zero' : '' ?>">
                                    <?= report_format_value($row['avg_subtask_score'], 1) ?>
                                </td>
                                <td><?= report_format_value($row['hours'], 2, '0.00') ?></td>
                                <td class="<?= $row['deducted'] > 0 ? 'cell-warning' : 'num-zero' ?>">
                                    <?= report_format_value($row['deducted'], 2, '0.00') ?>
                                </td>
                                <td class="<?= $row['capture_rate'] === null ? 'num-zero' : '' ?>">
                                    <?= $row['capture_rate'] === null ? '&mdash;' : report_format_value($row['capture_rate'], 2) ?>
                                </td>
                                <td>
                                    <?php if ($uid > 0) { ?>
                                        <a class="view-btn" href="<?= htmlspecialchars($viewDtrLink, ENT_QUOTES) ?>">View DTR</a>
                                    <?php } else { ?>
                                        &mdash;
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php } ?>
            <div class="dtr-section" id="dtrSection">
                <div class="dtr-header">
                    <div class="dtr-title-area">
                        <div class="section-title">Daily Time Record</div>
                        <div class="section-sub">
                            <?= $isAdmin ? 'Compare all users at a glance, or drill into individual records to view and adjust.' : 'View your daily time record for the selected range.' ?>
                        </div>
                    </div>
                    <div class="dtr-header-actions" <?= ($dtrUserId > 0 && $dtrUser) ? '' : 'style="display:none;"' ?>>
                        <?php if ($isAdmin && $dtrExportLink) { ?>
                            <a class="btn btn-outline" href="<?= htmlspecialchars($dtrExportLink, ENT_QUOTES) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                CSV
                            </a>
                        <?php } ?>
                        <?php if ($isAdmin && $dtrPdfLink) { ?>
                            <a class="btn btn-outline" href="<?= htmlspecialchars($dtrPdfLink, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                PDF
                            </a>
                        <?php } ?>
                        <button type="button" class="btn btn-outline" onclick="printDtrOnly()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Print
                        </button>
                    </div>
                </div>

                <?php if ($isAdmin) { ?>
                <div class="user-tabs" id="userTabs">
                    <a class="user-tab <?= $dtrUserId > 0 ? '' : 'active' ?>" href="<?= htmlspecialchars($overviewLink, ENT_QUOTES) ?>" id="tab-overview">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Overview
                    </a>
                    <?php foreach ($filteredUsers as $tabIndex => $tabUser) {
                        $tabUid = (int)($tabUser['id'] ?? 0);
                        if ($tabUid <= 0) {
                            continue;
                        }
                        $tabName = trim((string)($tabUser['full_name'] ?? ''));
                        if ($tabName === '') {
                            $tabName = 'User #' . $tabUid;
                        }
                        $tabVisual = $userVisuals[$tabUid] ?? ['initials' => report_get_initials($tabName), 'color' => report_get_color_for_index($tabIndex)];
                        $tabLink = 'reports.php?' . http_build_query(array_merge($queryBaseNoUser, ['dtr_user_id' => $tabUid])) . '#dtrSection';
                    ?>
                        <a class="user-tab <?= $tabUid === $dtrUserId ? 'active' : '' ?>" href="<?= htmlspecialchars($tabLink, ENT_QUOTES) ?>" id="tab-<?= $tabUid ?>">
                            <div class="tab-avatar" style="background:<?= htmlspecialchars($tabVisual['color'], ENT_QUOTES) ?>"><?= htmlspecialchars($tabVisual['initials']) ?></div>
                            <?= htmlspecialchars($tabName) ?>
                        </a>
                    <?php } ?>
                </div>
                <?php } ?>

                <?php if ($dtrUserId > 0 && $dtrUser) {
                    $selectedName = trim((string)($dtrUser['full_name'] ?? 'Employee'));
                    if ($selectedName === '') {
                        $selectedName = 'Employee';
                    }
                    $selectedEmail = trim((string)($dtrUser['username'] ?? ''));
                    $selectedInitials = $selectedVisual['initials'] ?? report_get_initials($selectedName);
                    $selectedColor = $selectedVisual['color'] ?? report_get_color_for_index(0);
                    $selectedDays = $selectedMetrics ? (int)$selectedMetrics['days'] : 0;
                ?>
                    <div id="dtrContent">
                        <div class="dtr-user-bar">
                            <div class="dtr-user-info">
                                <div class="dtr-avatar" style="background:<?= htmlspecialchars($selectedColor, ENT_QUOTES) ?>"><?= htmlspecialchars($selectedInitials) ?></div>
                                <div>
                                    <div class="dtr-user-name"><?= htmlspecialchars($selectedName) ?></div>
                                    <div class="dtr-user-dept"><?= htmlspecialchars($selectedEmail !== '' ? $selectedEmail : 'Company Name - Company Address') ?></div>
                                </div>
                            </div>
                            <div class="dtr-meta-pills">
                                <div class="month-nav">
                                    <?php if ($prevMonthLink) { ?>
                                        <a class="month-nav-btn" href="<?= htmlspecialchars($prevMonthLink, ENT_QUOTES) ?>">&lsaquo;</a>
                                    <?php } else { ?>
                                        <span class="month-nav-btn">&lsaquo;</span>
                                    <?php } ?>
                                    <span class="month-nav-label"><?= htmlspecialchars($dtrMonthLabel) ?></span>
                                    <?php if ($nextMonthLink) { ?>
                                        <a class="month-nav-btn" href="<?= htmlspecialchars($nextMonthLink, ENT_QUOTES) ?>">&rsaquo;</a>
                                    <?php } else { ?>
                                        <span class="month-nav-btn">&rsaquo;</span>
                                    <?php } ?>
                                </div>
                                <div class="dtr-pill">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    Net totals reflect deductions
                                </div>
                            </div>
                        </div>

                        <div class="dtr-stats">
                            <div class="dtr-stat">
                                <div class="dtr-stat-label">Gross Hours</div>
                                <div class="dtr-stat-value good"><?= number_format($dtrTotals['raw'], 2) ?></div>
                            </div>
                            <div class="dtr-stat">
                                <div class="dtr-stat-label">Total Deducted</div>
                                <div class="dtr-stat-value <?= $dtrTotals['deducted'] > 0 ? 'warn' : '' ?>"><?= number_format($dtrTotals['deducted'], 2) ?></div>
                            </div>
                            <div class="dtr-stat">
                                <div class="dtr-stat-label">Net Total</div>
                                <div class="dtr-stat-value"><?= number_format($dtrTotals['net'], 2) ?></div>
                            </div>
                            <div class="dtr-stat">
                                <div class="dtr-stat-label">Active Days</div>
                                <div class="dtr-stat-value"><?= number_format($selectedDays) ?></div>
                            </div>
                        </div>

                        <div class="dtr-form-header">
                            <div class="dtr-company-name<?= $isAdmin ? ' dtr-editable' : '' ?>" contenteditable="<?= $isAdmin ? 'true' : 'false' ?>" data-placeholder="Company Name"><?= htmlspecialchars($workspaceName) ?></div>
                            <?php if ($workspaceAddress !== '') { ?>
                                <div class="dtr-company-address<?= $isAdmin ? ' dtr-editable' : '' ?>" contenteditable="<?= $isAdmin ? 'true' : 'false' ?>" data-placeholder="Company Address"><?= htmlspecialchars($workspaceAddress) ?></div>
                            <?php } else { ?>
                                <div class="dtr-company-address dtr-address-empty<?= $isAdmin ? ' dtr-editable' : '' ?>" contenteditable="<?= $isAdmin ? 'true' : 'false' ?>" data-placeholder="Company Address">&nbsp;</div>
                            <?php } ?>
                            <div class="dtr-form-title">DAILY TIME RECORD</div>
                            <div class="dtr-meta-grid">
                                <div class="dtr-meta-row">
                                    <span>Name:</span>
                                    <span class="dtr-meta-value"><?= htmlspecialchars($selectedName) ?></span>
                                </div>
                                <div class="dtr-meta-row">
                                    <span>Department:</span>
                                    <span class="<?= $isAdmin ? 'dtr-editable' : 'dtr-meta-value' ?>" contenteditable="<?= $isAdmin ? 'true' : 'false' ?>" data-placeholder="Department">Department</span>
                                </div>
                                <div class="dtr-meta-row">
                                    <span>Month:</span>
                                    <span class="dtr-meta-value"><?= htmlspecialchars($dtrMonthLabel) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dtr-table-wrap">
                            <?php $dtrTableColspan = $dtrHasSegmented ? 10 : 6; ?>
                            <table class="dtr-table">
                                <thead>
                                    <?php if ($dtrHasSegmented) { ?>
                                        <tr class="dtr-print-only-row">
                                            <th class="dtr-print-month-title" colspan="<?= $dtrTableColspan ?>"><?= htmlspecialchars($dtrPrintMonthLabel) ?></th>
                                        </tr>
                                        <tr>
                                            <th rowspan="2">Date</th>
                                            <th colspan="2">Morning</th>
                                            <th colspan="2">Afternoon</th>
                                            <th colspan="2">Overtime</th>
                                            <th rowspan="2">Daily Total</th>
                                            <th rowspan="2">Deducted</th>
                                            <th rowspan="2" class="dtr-col-net">Net Total</th>
                                            <th rowspan="2" class="dtr-signature-col">Signature</th>
                                            <th rowspan="2" class="dtr-col-adjust">Adj. Deduction</th>
                                            <th rowspan="2" class="dtr-col-reason">Reason</th>
                                            <th rowspan="2" class="dtr-col-action">Action</th>
                                        </tr>
                                        <tr>
                                            <th>In</th>
                                            <th>Out</th>
                                            <th>In</th>
                                            <th>Out</th>
                                            <th>In</th>
                                            <th>Out</th>
                                        </tr>
                                    <?php } else { ?>
                                        <tr class="dtr-print-only-row">
                                            <th class="dtr-print-month-title" colspan="<?= $dtrTableColspan ?>"><?= htmlspecialchars($dtrPrintMonthLabel) ?></th>
                                        </tr>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Daily Total</th>
                                            <th>Deducted</th>
                                            <th class="dtr-col-net">Net Total</th>
                                            <th class="dtr-signature-col">Signature</th>
                                            <th class="dtr-col-adjust">Adj. Deduction</th>
                                            <th class="dtr-col-reason">Reason</th>
                                            <th class="dtr-col-action">Action</th>
                                        </tr>
                                    <?php } ?>
                                </thead>
                                <tbody>
                                    <?php foreach ($dtrRows as $row) {
                                        $dateKey = $row['date'];
                                        $safeDateKey = str_replace('-', '', $dateKey);
                                        $formId = 'dtr-form-' . (int)$dtrUserId . '-' . $safeDateKey;
                                        $deductedValue = number_format((float)$row['deducted'], 2);
                                        $netValue = number_format((float)$row['net_hours'], 2);
                                        $rawValue = number_format((float)$row['raw_hours'], 2);
                                        $dayLabel = date('D', strtotime($dateKey));
                                        $dateLabel = date('M j', strtotime($dateKey));
                                        $isWeekend = date('N', strtotime($dateKey)) >= 6;
                                        $rowClass = $isWeekend ? 'weekend-row' : ((float)$row['raw_hours'] > 0 ? 'active-row' : '');
                                        $timeIn = $dtrHasSegmented ? report_format_time($row['morning_in']) : report_format_time($row['time_in']);
                                        $timeOut = $dtrHasSegmented ? report_format_time($row['morning_out']) : report_format_time($row['time_out']);
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td>
                                            <div class="dtr-date"><?= htmlspecialchars($dateLabel) ?><div class="day"><?= htmlspecialchars($dayLabel) ?></div></div>
                                        </td>
                                        <?php if ($dtrHasSegmented) { ?>
                                            <?php
                                                $morningIn = report_format_time($row['morning_in']);
                                                $morningOut = report_format_time($row['morning_out']);
                                                $afternoonIn = report_format_time($row['afternoon_in']);
                                                $afternoonOut = report_format_time($row['afternoon_out']);
                                                $overtimeIn = report_format_time($row['overtime_in']);
                                                $overtimeOut = report_format_time($row['overtime_out']);
                                            ?>
                                            <td class="time-cell <?= $morningIn === '' ? 'empty' : '' ?>"><?= $morningIn !== '' ? $morningIn : '&mdash;' ?></td>
                                            <td class="time-cell <?= $morningOut === '' ? 'empty' : '' ?>"><?= $morningOut !== '' ? $morningOut : '&mdash;' ?></td>
                                            <td class="time-cell <?= $afternoonIn === '' ? 'empty' : '' ?>"><?= $afternoonIn !== '' ? $afternoonIn : '&mdash;' ?></td>
                                            <td class="time-cell <?= $afternoonOut === '' ? 'empty' : '' ?>"><?= $afternoonOut !== '' ? $afternoonOut : '&mdash;' ?></td>
                                            <td class="time-cell <?= $overtimeIn === '' ? 'empty' : '' ?>"><?= $overtimeIn !== '' ? $overtimeIn : '&mdash;' ?></td>
                                            <td class="time-cell <?= $overtimeOut === '' ? 'empty' : '' ?>"><?= $overtimeOut !== '' ? $overtimeOut : '&mdash;' ?></td>
                                        <?php } else { ?>
                                            <td class="time-cell <?= $timeIn === '' ? 'empty' : '' ?>"><?= $timeIn !== '' ? $timeIn : '&mdash;' ?></td>
                                            <td class="time-cell <?= $timeOut === '' ? 'empty' : '' ?>"><?= $timeOut !== '' ? $timeOut : '&mdash;' ?></td>
                                        <?php } ?>
                                        <td class="hours-cell"><?= $rawValue ?></td>
                                        <td class="deduct-cell <?= $row['deducted'] > 0 ? '' : 'none' ?>"><?= $deductedValue ?></td>
                                        <td class="hours-cell dtr-col-net"><?= $netValue ?></td>
                                        <td class="dtr-signature-cell"></td>
                                        <?php if ($isAdmin) { ?>
                                            <td class="dtr-col-adjust">
                                                <input
                                                    class="deduct-input"
                                                    type="number"
                                                    name="deduct_hours"
                                                    step="0.25"
                                                    min="0"
                                                    max="24"
                                                    value="<?= htmlspecialchars($row['deducted']) ?>"
                                                    form="<?= $formId ?>"
                                                >
                                            </td>
                                            <td class="dtr-col-reason">
                                                <input
                                                    class="reason-input"
                                                    type="text"
                                                    name="reason"
                                                    placeholder="Reason..."
                                                    value="<?= htmlspecialchars($row['reason']) ?>"
                                                    form="<?= $formId ?>"
                                                >
                                            </td>
                                            <td class="dtr-col-action">
                                                <form id="<?= $formId ?>" method="POST" action="app/update-attendance-deduction.php">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($dtrCsrfToken, ENT_QUOTES) ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int)$dtrUserId ?>">
                                                    <input type="hidden" name="att_date" value="<?= htmlspecialchars($dateKey) ?>">
                                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars(basename($_SERVER['PHP_SELF']) . '?' . http_build_query(array_merge($queryBase, ['dtr_user_id' => $dtrUserId])), ENT_QUOTES) ?>">
                                                </form>
                                                <button type="submit" class="save-row-btn" form="<?= $formId ?>">Save</button>
                                            </td>
                                        <?php } else { ?>
                                            <td class="deduct-cell dtr-col-adjust <?= $row['deducted'] > 0 ? '' : 'none' ?>"><?= $deductedValue ?></td>
                                            <td class="dtr-reason-cell dtr-col-reason"><?= $row['reason'] !== '' ? htmlspecialchars($row['reason']) : '&mdash;' ?></td>
                                            <td class="dtr-col-action">&mdash;</td>
                                        <?php } ?>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="dtr-footer">
                            <div class="dtr-totals">
                                <div class="dtr-total-item">
                                    <div class="dtr-total-label">Gross Total</div>
                                    <div class="dtr-total-value"><?= number_format($dtrTotals['raw'], 2) ?></div>
                                </div>
                                <div class="dtr-total-item">
                                    <div class="dtr-total-label">Deducted</div>
                                    <div class="dtr-total-value"><?= number_format($dtrTotals['deducted'], 2) ?></div>
                                </div>
                                <div class="dtr-total-item">
                                    <div class="dtr-total-label">Net Total</div>
                                    <div class="dtr-total-value"><?= number_format($dtrTotals['net'], 2) ?></div>
                                </div>
                            </div>
                           
                        </div>

                        <div class="dtr-signature-blocks dtr-print-signatures">
                            <div class="dtr-signature">
                                <div class="dtr-sign-line"></div>
                                <div class="dtr-sign-label">Employee Signature</div>
                            </div>
                            <div class="dtr-signature">
                                <div class="dtr-sign-line"></div>
                                <div class="dtr-sign-label">Supervisor Signature</div>
                            </div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div id="dtrOverview">
                        <?php if (empty($reportRows)) { ?>
                            <div class="dtr-empty">
                                <div class="dtr-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11h8M8 15h5M8 7h8"/></svg>
                                </div>
                                <h3>No DTR data yet</h3>
                                <p>Select a user from the breakdown table once available to view their records.</p>
                            </div>
                        <?php } else { ?>
                            <div class="all-users-summary">
                                <div class="all-users-grid">
                                    <?php foreach ($reportRows as $row) {
                                        $user = $row['user'];
                                        $uid = (int)($user['id'] ?? 0);
                                        if ($uid <= 0) {
                                            continue;
                                        }
                                        $name = trim((string)($user['full_name'] ?? ''));
                                        if ($name === '') {
                                            $name = 'User #' . $uid;
                                        }
                                        $email = trim((string)($user['username'] ?? ''));
                                        $visual = $userVisuals[$uid] ?? ['initials' => report_get_initials($name), 'color' => report_get_color_for_index(0)];
                                        $cardLink = 'reports.php?' . http_build_query(array_merge($queryBaseNoUser, ['dtr_user_id' => $uid])) . '#dtrSection';
                                        $deductedClass = $row['deducted'] > 0 ? 'red' : '';
                                    ?>
                                        <a class="user-summary-card" href="<?= htmlspecialchars($cardLink, ENT_QUOTES) ?>">
                                            <div class="user-summary-top">
                                                <div class="user-sum-avatar" style="background:<?= htmlspecialchars($visual['color'], ENT_QUOTES) ?>"><?= htmlspecialchars($visual['initials']) ?></div>
                                                <div>
                                                    <div class="user-sum-name"><?= htmlspecialchars($name) ?></div>
                                                    <div class="user-sum-email"><?= htmlspecialchars($email) ?></div>
                                                </div>
                                            </div>
                                            <div class="activity-bar">
                                                <span class="activity-bar-label"><?= htmlspecialchars($rangeLabel) ?></span>
                                                <?php foreach ($rangeDates as $dateKey) {
                                                    $isWeekend = date('N', strtotime($dateKey)) >= 6;
                                                    $isActive = isset($attendanceDaysMap[$uid][$dateKey]);
                                                    $dotClass = 'act-dot';
                                                    if ($isWeekend) {
                                                        $dotClass .= ' weekend';
                                                    } elseif ($isActive) {
                                                        $dotClass .= ' active';
                                                    }
                                                ?>
                                                    <div class="<?= $dotClass ?>"></div>
                                                <?php } ?>
                                            </div>
                                            <div class="user-summary-stats">
                                                <div class="user-sum-stat">
                                                    <div class="user-sum-stat-label">Net Hrs</div>
                                                    <div class="user-sum-stat-value green"><?= report_format_value($row['hours'], 2, '0.00') ?></div>
                                                </div>
                                                <div class="user-sum-stat">
                                                    <div class="user-sum-stat-label">Deducted</div>
                                                    <div class="user-sum-stat-value <?= $deductedClass ?>"><?= report_format_value($row['deducted'], 2, '0.00') ?></div>
                                                </div>
                                                <div class="user-sum-stat">
                                                    <div class="user-sum-stat-label">Active Days</div>
                                                    <div class="user-sum-stat-value"><?= number_format($row['days']) ?></div>
                                                </div>
                                            </div>
                                            <div class="view-dtr-link">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                                                View Full DTR
                                            </div>
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <?php if ($isAdmin) { ?>
            <div class="alerts-grid">
                <div class="alert-card">
                    <div class="alert-icon amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                    </div>
                    <div class="alert-body">
                        <h4>Needs Attention</h4>
                        <p>Completed tasks missing ratings in this range.</p>
                        <div class="alert-count"><?= number_format($overall['unrated_completed']) ?></div>
                    </div>
                </div>
                <div class="alert-card">
                    <div class="alert-icon red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="alert-body">
                        <h4>Overdue Backlog</h4>
                        <p>Overdue tasks across selected users.</p>
                        <div class="alert-count red"><?= number_format($overall['overdue']) ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div></div>
    </div>

    <script>
        function printDtrOnly() {
            document.body.classList.add('print-dtr-only');
            window.print();
            setTimeout(function () {
                document.body.classList.remove('print-dtr-only');
            }, 300);
        }

        (function () {
            var monthPicker = document.getElementById('monthPicker');
            var startInput = document.getElementById('startDate');
            var endInput = document.getElementById('endDate');
            if (!monthPicker || !startInput || !endInput) return;

            monthPicker.addEventListener('change', function () {
                if (!monthPicker.value) return;
                var parts = monthPicker.value.split('-');
                if (parts.length < 2) return;
                var year = parseInt(parts[0], 10);
                var month = parseInt(parts[1], 10);
                if (!year || !month) return;
                var firstDay = year.toString().padStart(4, '0') + '-' + parts[1] + '-01';
                var lastDate = new Date(year, month, 0).getDate();
                var lastDay = year.toString().padStart(4, '0') + '-' + parts[1] + '-' + String(lastDate).padStart(2, '0');
                startInput.value = firstDay;
                endInput.value = lastDay;
            });

            function clearMonth() {
                if (monthPicker.value) {
                    monthPicker.value = '';
                }
            }

            startInput.addEventListener('change', clearMonth);
            endInput.addEventListener('change', clearMonth);
        })();
    </script>
</body>
</html>






