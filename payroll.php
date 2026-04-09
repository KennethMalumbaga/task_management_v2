<?php
session_start();
if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=First login');
    exit;
}

require_once "DB_connection.php";
require_once "inc/tenant.php";
require_once "inc/csrf.php";
require_once "app/model/user.php";
require_once "app/model/Report.php";
require_once "app/model/AttendanceAdjustment.php";
require_once "app/model/Payroll.php";

date_default_timezone_set('Asia/Manila');

user_compensation_ensure_schema($pdo);
attendance_adjustment_ensure_schema($pdo);
payroll_deduction_ensure_schema($pdo);
payroll_government_settings_ensure_schema($pdo);

function payroll_normalize_month($raw)
{
    $raw = trim((string)$raw);
    if (!preg_match('/^\d{4}-\d{2}$/', $raw)) {
        return date('Y-m');
    }

    $month = DateTime::createFromFormat('Y-m', $raw);
    return $month instanceof DateTime ? $month->format('Y-m') : date('Y-m');
}

function payroll_money($value)
{
    return number_format((float)$value, 2);
}

function payroll_hours($value)
{
    return number_format((float)$value, 2);
}

function payroll_item_title(array $item)
{
    $title = trim((string)($item['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $types = payroll_deduction_types();
    $typeKey = payroll_deduction_normalize_type($item['deduction_type'] ?? 'other');
    return $types[$typeKey] ?? 'Deduction';
}

function payroll_deduction_value_label(array $item)
{
    $amount = max(0, (float)($item['amount'] ?? 0));
    $amountMode = payroll_deduction_normalize_amount_mode($item['amount_mode'] ?? 'fixed');
    if ($amountMode === 'percent') {
        return payroll_money($amount) . '% of gross';
    }

    return 'PHP ' . payroll_money($amount);
}

function payroll_normalize_section($raw)
{
    $allowed = [
        'computation',
        'table',
        'rate_manager',
        'deductions',
        'payslip',
    ];

    $section = strtolower(trim((string)$raw));
    return in_array($section, $allowed, true) ? $section : 'computation';
}

function payroll_build_url($month, $userId = 0, $section = 'computation')
{
    $query = [
        'month' => payroll_normalize_month($month),
        'section' => payroll_normalize_section($section),
    ];

    $userId = (int)$userId;
    if ($userId > 0) {
        $query['user_id'] = $userId;
    }

    return 'payroll.php?' . http_build_query($query);
}

$workspaceName = trim((string)($_SESSION['organization_name'] ?? 'TaskFlow Workspace'));
$workspaceOrgId = tenant_get_current_org_id();
if ($workspaceOrgId && tenant_table_exists($pdo, 'organizations') && tenant_column_exists($pdo, 'organizations', 'name')) {
    $stmtWorkspace = $pdo->prepare("SELECT name FROM organizations WHERE id = ? LIMIT 1");
    $stmtWorkspace->execute([(int)$workspaceOrgId]);
    $workspaceDbName = trim((string)$stmtWorkspace->fetchColumn());
    if ($workspaceDbName !== '') {
        $workspaceName = $workspaceDbName;
    }
}
if ($workspaceName === '') {
    $workspaceName = 'TaskFlow Workspace';
}

$month = payroll_normalize_month($_GET['month'] ?? date('Y-m'));
$activeSection = payroll_normalize_section($_GET['section'] ?? 'computation');
$monthDate = DateTime::createFromFormat('Y-m', $month) ?: new DateTime('first day of this month');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');
$periodLabel = $monthDate->format('F Y');
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$allUsers = array_values(array_filter(get_all_users($pdo, 'employee'), function ($user) {
    return (int)($user['id'] ?? 0) > 0;
}));

$selectedUser = null;
foreach ($allUsers as $employee) {
    if ((int)($employee['id'] ?? 0) === $selectedUserId) {
        $selectedUser = $employee;
        break;
    }
}

$employeeIds = array_map('intval', array_column($allUsers, 'id'));
$attendanceMetrics = report_get_attendance_metrics($pdo, $employeeIds, $startDate, $endDate);
$timeAdjustments = attendance_adjustment_get_range_map($pdo, $employeeIds, $startDate, $endDate);
$governmentSettings = payroll_government_settings_get($pdo);
$governmentCatalog = payroll_government_deduction_catalog();
$manualDeductionItems = payroll_deduction_get_range_items($pdo, $employeeIds, $startDate, $endDate);

$manualItemsByUser = [];
foreach ($manualDeductionItems as $item) {
    $uid = (int)($item['user_id'] ?? 0);
    if ($uid <= 0) {
        continue;
    }
    if (!isset($manualItemsByUser[$uid])) {
        $manualItemsByUser[$uid] = [];
    }
    $manualItemsByUser[$uid][] = $item;
}

$summary = [
    'employees' => 0,
    'missing_rates' => 0,
    'gross_pay' => 0,
    'time_deduction_amount' => 0,
    'government_deductions' => 0,
    'custom_deductions' => 0,
    'other_deductions' => 0,
    'total_deductions' => 0,
    'net_pay' => 0,
];

$payrollRows = [];
foreach ($allUsers as $employee) {
    $uid = (int)($employee['id'] ?? 0);
    if ($uid <= 0) {
        continue;
    }

    $rawHours = max(0, (float)($attendanceMetrics[$uid]['total_hours'] ?? 0));
    $attendanceDays = max(0, (int)($attendanceMetrics[$uid]['days_count'] ?? 0));
    $deductedHours = max(0, (float)($timeAdjustments[$uid]['hours_deducted'] ?? 0));
    $deductedHours = min($deductedHours, $rawHours);
    $payableHours = max(0, $rawHours - $deductedHours);
    $hasRate = isset($employee['hourly_rate']) && $employee['hourly_rate'] !== null && $employee['hourly_rate'] !== '';
    $hourlyRate = $hasRate ? round((float)$employee['hourly_rate'], 2) : null;
    $grossPay = $hourlyRate !== null ? $rawHours * $hourlyRate : 0;
    $timeDeductionAmount = $hourlyRate !== null ? $deductedHours * $hourlyRate : 0;
    $payAfterTime = max(0, $grossPay - $timeDeductionAmount);

    $governmentBreakdown = payroll_compute_government_deductions($grossPay, $governmentSettings);
    $governmentItems = $governmentBreakdown['items'] ?? [];
    $governmentDeductions = max(0, (float)($governmentBreakdown['total'] ?? 0));

    $employeeDeductionItems = payroll_deduction_resolve_items($manualItemsByUser[$uid] ?? [], $grossPay);
    $customDeductions = 0;
    $deductionTitles = [];
    foreach ($employeeDeductionItems as $item) {
        $customDeductions += max(0, (float)($item['computed_amount'] ?? 0));
        $deductionTitles[] = payroll_item_title($item);
    }
    $otherDeductions = $governmentDeductions + $customDeductions;
    $totalDeductions = $timeDeductionAmount + $governmentDeductions + $customDeductions;
    $netPay = max(0, $grossPay - $totalDeductions);
    $deductionTitles = array_values(array_unique(array_filter($deductionTitles)));
    $deductionSummaryParts = [];
    if (!empty($governmentItems)) {
        $deductionSummaryParts[] = count($governmentItems) . ' gov item(s)';
    }
    if (!empty($deductionTitles)) {
        $customSummary = implode(', ', array_slice($deductionTitles, 0, 2));
        if (count($deductionTitles) > 2) {
            $customSummary .= ' +' . (count($deductionTitles) - 2) . ' more';
        }
        $deductionSummaryParts[] = $customSummary;
    }
    $deductionSummary = !empty($deductionSummaryParts) ? implode(' | ', $deductionSummaryParts) : 'No extra deductions';

    $payrollRows[] = [
        'user' => $employee,
        'attendance_days' => $attendanceDays,
        'raw_hours' => $rawHours,
        'deducted_hours' => $deductedHours,
        'payable_hours' => $payableHours,
        'hourly_rate' => $hourlyRate,
        'gross_pay' => $grossPay,
        'time_deduction_amount' => $timeDeductionAmount,
        'pay_after_time' => $payAfterTime,
        'government_deductions' => $governmentDeductions,
        'government_deduction_items' => $governmentItems,
        'custom_deductions' => $customDeductions,
        'other_deductions' => $otherDeductions,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay,
        'has_rate' => $hasRate,
        'manual_deduction_items' => $employeeDeductionItems,
        'manual_deduction_count' => count($employeeDeductionItems),
        'custom_deduction_items' => $employeeDeductionItems,
        'custom_deduction_count' => count($employeeDeductionItems),
        'deduction_summary' => $deductionSummary,
    ];

    $summary['employees']++;
    if (!$hasRate) {
        $summary['missing_rates']++;
    }
    $summary['gross_pay'] += $grossPay;
    $summary['time_deduction_amount'] += $timeDeductionAmount;
    $summary['government_deductions'] += $governmentDeductions;
    $summary['custom_deductions'] += $customDeductions;
    $summary['other_deductions'] += $otherDeductions;
    $summary['total_deductions'] += $totalDeductions;
    $summary['net_pay'] += $netPay;
}

usort($payrollRows, function ($a, $b) {
    return strcasecmp((string)($a['user']['full_name'] ?? ''), (string)($b['user']['full_name'] ?? ''));
});

$selectedPayrollRow = null;
if ($selectedUserId > 0) {
    foreach ($payrollRows as $row) {
        if ((int)($row['user']['id'] ?? 0) === $selectedUserId) {
            $selectedPayrollRow = $row;
            break;
        }
    }
}

$resolvedCustomDeductionItems = [];
foreach ($payrollRows as $row) {
    foreach (($row['custom_deduction_items'] ?? []) as $item) {
        $item['user_id'] = (int)($row['user']['id'] ?? 0);
        $resolvedCustomDeductionItems[] = $item;
    }
}

$displayPayrollRows = $selectedPayrollRow ? [$selectedPayrollRow] : $payrollRows;
$displayManualDeductionItems = $selectedPayrollRow
    ? ($selectedPayrollRow['custom_deduction_items'] ?? [])
    : $resolvedCustomDeductionItems;

$selectedGovernmentDeductions = $selectedPayrollRow
    ? ($selectedPayrollRow['government_deduction_items'] ?? [])
    : [];

$selectedCustomDeductions = $selectedPayrollRow
    ? ($selectedPayrollRow['custom_deduction_items'] ?? [])
    : [];

$displaySummary = [
    'employees' => 0,
    'missing_rates' => 0,
    'gross_pay' => 0,
    'time_deduction_amount' => 0,
    'government_deductions' => 0,
    'custom_deductions' => 0,
    'other_deductions' => 0,
    'total_deductions' => 0,
    'net_pay' => 0,
];
foreach ($displayPayrollRows as $row) {
    $displaySummary['employees']++;
    if (empty($row['has_rate'])) {
        $displaySummary['missing_rates']++;
    }
    $displaySummary['gross_pay'] += (float)($row['gross_pay'] ?? 0);
    $displaySummary['time_deduction_amount'] += (float)($row['time_deduction_amount'] ?? 0);
    $displaySummary['government_deductions'] += (float)($row['government_deductions'] ?? 0);
    $displaySummary['custom_deductions'] += (float)($row['custom_deductions'] ?? 0);
    $displaySummary['other_deductions'] += (float)($row['other_deductions'] ?? 0);
    $displaySummary['total_deductions'] += (float)($row['total_deductions'] ?? 0);
    $displaySummary['net_pay'] += (float)($row['net_pay'] ?? 0);
}

$defaultDeductionDate = date('Y-m-d');
if ($defaultDeductionDate < $startDate || $defaultDeductionDate > $endDate) {
    $defaultDeductionDate = $startDate;
}

$deductionTypes = payroll_deduction_types();
$deductionAmountModes = payroll_deduction_amount_modes();
$deductionPeriodLabels = payroll_deduction_period_labels();
$manualDeductionCount = count($manualDeductionItems);
$generatedAtLabel = date('M j, Y g:i A');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .payroll-shell { max-width: 1280px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
        .payroll-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06); }
        .payroll-hero { padding: 28px; display: grid; grid-template-columns: 1.2fr .8fr; gap: 20px; background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color: #fff; }
        .payroll-eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,0.14); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .payroll-hero h2 { margin: 14px 0 10px; font-size: 30px; line-height: 1.15; }
        .payroll-hero p { margin: 0; max-width: 720px; color: rgba(255,255,255,0.82); }
        .payroll-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .payroll-meta-item { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.14); border-radius: 14px; padding: 16px; }
        .payroll-meta-item span { display: block; font-size: 12px; color: rgba(255,255,255,0.75); text-transform: uppercase; letter-spacing: .08em; }
        .payroll-meta-item strong { display: block; margin-top: 8px; font-size: 22px; }
        .payroll-filters { padding: 20px 24px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; align-items: end; }
        .payroll-field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 7px; text-transform: uppercase; letter-spacing: .08em; }
        .payroll-field input, .payroll-field select, .payroll-field textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 12px; padding: 11px 13px; font: inherit; color: #0f172a; box-sizing: border-box; }
        .payroll-field textarea { min-height: 92px; resize: vertical; }
        .payroll-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .payroll-btn { border: none; border-radius: 12px; padding: 11px 16px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .payroll-btn.primary { background: #2563eb; color: #fff; }
        .payroll-btn.secondary { background: #e2e8f0; color: #0f172a; }
        .payroll-btn.danger { background: #fee2e2; color: #b91c1c; }
        .payroll-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; padding: 0 24px 24px; }
        .payroll-stat { padding: 18px; border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .payroll-stat span { display: block; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .08em; }
        .payroll-stat strong { display: block; margin-top: 10px; font-size: 24px; color: #0f172a; }
        .payroll-section-head { padding: 24px 24px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .payroll-section-head h3 { margin: 0; font-size: 20px; color: #0f172a; }
        .payroll-section-head p { margin: 6px 0 0; color: #64748b; }
        .payroll-table-wrap { padding: 18px 24px 24px; overflow: auto; }
        .payroll-table { width: 100%; min-width: 980px; border-collapse: collapse; }
        .payroll-table th, .payroll-table td { padding: 14px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        .payroll-table th { font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: .08em; background: #f8fafc; position: sticky; top: 0; }
        .payroll-user { display: flex; align-items: center; gap: 12px; }
        .payroll-avatar { width: 42px; height: 42px; border-radius: 50%; background: #dbeafe; color: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; overflow: hidden; }
        .payroll-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .payroll-user-name { font-weight: 700; color: #0f172a; }
        .payroll-user-email { font-size: 12px; color: #64748b; margin-top: 2px; }
        .payroll-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .payroll-badge.good { background: #dcfce7; color: #166534; }
        .payroll-badge.warn { background: #fef3c7; color: #92400e; }
        .payroll-empty { padding: 32px 24px; text-align: center; color: #64748b; }
        .payroll-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding: 0 24px 24px; }
        .payroll-breakdown { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .payroll-break-item { padding: 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .payroll-break-item span { display: block; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .08em; }
        .payroll-break-item strong { display: block; margin-top: 8px; font-size: 22px; color: #0f172a; }
        .payroll-inline-form { display: flex; flex-direction: column; gap: 14px; padding: 22px; }
        .payroll-deduction-list { padding: 0 24px 24px; }
        .payroll-deduction-item { display: grid; grid-template-columns: 120px 150px 1fr 120px 90px; gap: 12px; align-items: center; padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
        .payroll-deduction-item:last-child { border-bottom: none; }
        .payroll-gov-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; padding: 0 24px 24px; }
        .payroll-gov-card { display:flex; align-items:flex-start; gap:12px; padding:18px; border-radius:16px; background:#f8fafc; border:1px solid #e2e8f0; }
        .payroll-gov-card input[type="checkbox"] { width:18px; height:18px; margin-top:2px; accent-color:#2563eb; }
        .payroll-gov-card strong { display:block; color:#0f172a; font-size:16px; }
        .payroll-gov-card span { display:block; margin-top:4px; color:#64748b; font-size:13px; }
        .payroll-form-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:14px; }
        .payroll-inline-muted { font-size:12px; color:#64748b; }
        .payroll-alert { margin: 0 auto 18px; max-width: 1280px; padding: 14px 18px; border-radius: 14px; border: 1px solid; display: flex; align-items: center; gap: 10px; }
        .payroll-alert.error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .payroll-alert.success { background: #ecfdf5; color: #166534; border-color: #bbf7d0; }
        .payroll-tab-nav { display:flex; gap:10px; flex-wrap:wrap; padding: 0 24px 22px; }
        .payroll-tab-link {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:10px 14px;
            border-radius:999px;
            background:#f8fafc;
            border:1px solid #e2e8f0;
            color:#334155;
            text-decoration:none;
            font-size:13px;
            font-weight:700;
        }
        .payroll-tab-link.active {
            background:#eff6ff;
            border-color:#bfdbfe;
            color:#1d4ed8;
        }
        .payroll-quick-note {
            display:inline-flex;
            align-items:center;
            gap:7px;
            margin-top:10px;
            padding:8px 12px;
            border-radius:999px;
            background:#eff6ff;
            color:#1d4ed8;
            font-size:12px;
            font-weight:700;
        }
        .payroll-small {
            display:block;
            margin-top:4px;
            font-size:12px;
            color:#64748b;
        }
        .payroll-action-stack {
            display:flex;
            flex-direction:column;
            gap:8px;
        }
        .payroll-tab-panel-hidden {
            display:none;
        }
        .payroll-formula-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
            gap:14px;
            padding:0 24px 24px;
        }
        .payroll-formula-card {
            padding:18px;
            border-radius:16px;
            background:#f8fafc;
            border:1px solid #e2e8f0;
        }
        .payroll-formula-card span {
            display:block;
            font-size:12px;
            color:#64748b;
            text-transform:uppercase;
            letter-spacing:.08em;
            font-weight:700;
        }
        .payroll-formula-card strong {
            display:block;
            margin-top:8px;
            font-size:19px;
            color:#0f172a;
        }
        .payroll-formula-card small {
            display:block;
            margin-top:8px;
            color:#475569;
            font-size:12px;
            line-height:1.6;
        }
        .payroll-subtable {
            width:100%;
            border-collapse:collapse;
            font-size:14px;
        }
        .payroll-subtable th,
        .payroll-subtable td {
            padding:12px 10px;
            border-bottom:1px solid #e5e7eb;
            text-align:left;
            vertical-align:top;
        }
        .payroll-subtable th {
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:#475569;
        }
        .payroll-print-sheet {
            display:none;
            padding:28px;
            color:#111827;
            background:#fff;
        }
        .payroll-payslip-sheet {
            display:none;
            max-width:760px;
            margin:0 auto;
            padding:18px 8px 0;
            color:#111827;
            background:#fff;
        }
        .payroll-payslip-sheet.preview {
            display:block;
            max-width:none;
            margin:0;
            padding:0;
        }
        .payroll-print-head {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            margin-bottom:18px;
        }
        .payroll-print-title {
            margin:0;
            font-size:28px;
            font-weight:800;
        }
        .payroll-print-subtitle {
            margin-top:6px;
            color:#64748b;
            font-size:14px;
        }
        .payroll-print-meta {
            text-align:right;
            font-size:13px;
            color:#475569;
            line-height:1.7;
        }
        .payroll-print-summary {
            display:grid;
            grid-template-columns:repeat(4, minmax(0, 1fr));
            gap:12px;
            margin-bottom:18px;
        }
        .payroll-print-stat {
            border:1px solid #d1d5db;
            border-radius:12px;
            padding:14px;
        }
        .payroll-print-stat span {
            display:block;
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:#64748b;
            font-weight:700;
        }
        .payroll-print-stat strong {
            display:block;
            margin-top:8px;
            font-size:20px;
        }
        .payroll-print-block {
            margin-top:22px;
        }
        .payroll-print-block h3 {
            margin:0 0 10px;
            font-size:18px;
        }
        .payroll-print-table {
            width:100%;
            border-collapse:collapse;
            font-size:12px;
        }
        .payroll-print-table th,
        .payroll-print-table td {
            border:1px solid #d1d5db;
            padding:8px 9px;
            text-align:left;
            vertical-align:top;
        }
        .payroll-print-table th {
            background:#f8fafc;
            text-transform:uppercase;
            letter-spacing:.06em;
            font-size:10px;
        }
        .payroll-print-footnote {
            margin-top:16px;
            font-size:12px;
            color:#64748b;
        }
        .payroll-payslip-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:18px;
            margin-bottom:22px;
        }
        .payroll-payslip-company {
            font-size:18px;
            font-weight:800;
            margin:0;
        }
        .payroll-payslip-period {
            margin-top:4px;
            color:#475569;
            font-size:14px;
        }
        .payroll-payslip-employee {
            text-align:right;
            font-size:14px;
            line-height:1.5;
        }
        .payroll-payslip-name {
            font-weight:800;
            font-size:18px;
        }
        .payroll-payslip-section {
            margin-top:22px;
        }
        .payroll-payslip-section-title {
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:#64748b;
            font-weight:800;
            margin-bottom:8px;
        }
        .payroll-payslip-line {
            display:flex;
            justify-content:space-between;
            gap:16px;
            padding:10px 0;
            border-bottom:1px solid #e5e7eb;
            font-size:14px;
        }
        .payroll-payslip-line:last-child {
            border-bottom:none;
        }
        .payroll-payslip-line strong {
            font-size:15px;
        }
        .payroll-payslip-total {
            margin-top:16px;
            padding:18px 20px;
            border:1px solid #d1d5db;
            border-radius:16px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
        }
        .payroll-payslip-total span {
            font-size:16px;
            font-weight:700;
        }
        .payroll-payslip-total strong {
            font-size:34px;
            line-height:1;
        }
        .payroll-payslip-footnote {
            margin-top:24px;
            text-align:center;
            color:#64748b;
            font-size:12px;
        }
        .payroll-payslip-toolbar {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:18px;
        }
        @media (max-width: 1100px) {
            .payroll-hero, .payroll-detail-grid, .payroll-summary { grid-template-columns: 1fr; }
            .payroll-filters { grid-template-columns: 1fr; }
            .payroll-breakdown { grid-template-columns: 1fr 1fr; }
            .payroll-gov-grid,
            .payroll-form-grid { grid-template-columns: 1fr 1fr; }
            .payroll-formula-grid { grid-template-columns: 1fr 1fr; }
            .payroll-print-summary { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 720px) {
            .payroll-breakdown { grid-template-columns: 1fr; }
            .payroll-meta { grid-template-columns: 1fr; }
            .payroll-formula-grid { grid-template-columns: 1fr; }
            .payroll-gov-grid,
            .payroll-form-grid { grid-template-columns: 1fr; }
            .payroll-deduction-item { grid-template-columns: 1fr; gap: 6px; }
            .payroll-print-summary { grid-template-columns: 1fr; }
            .payroll-payslip-header,
            .payroll-payslip-toolbar {
                flex-direction:column;
                align-items:flex-start;
            }
            .payroll-payslip-employee {
                text-align:left;
            }
        }
        @media print {
            @page {
                size: <?= $selectedPayrollRow ? 'portrait' : 'landscape' ?>;
                margin: 10mm;
            }

            html, body {
                background:#fff !important;
            }

            body {
                display:block;
                min-height:auto;
                color:#111827;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .dash-sidebar,
            .dash-content-topbar,
            .mobile-navbar,
            .sidebar-overlay,
            .mobile-top-notif-dropdown,
            .mobile-top-profile-dropdown,
            .payroll-screen-only,
            .payroll-alert {
                display:none !important;
            }

            .dash-main {
                margin:0 !important;
                padding:0 !important;
                width:100% !important;
            }

            .payroll-shell {
                max-width:none;
                display:block;
            }

            .payroll-card {
                border:none;
                box-shadow:none;
                border-radius:0;
                overflow:visible;
            }

            .payroll-print-sheet {
                display:block !important;
            }

            .payroll-payslip-sheet {
                display:block !important;
            }
            .payroll-tab-panel-hidden.payroll-print-panel {
                display:block !important;
            }
        }
    </style>
</head>
<body class="payroll-page">
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <?php if (isset($_GET['error'])) { ?>
        <div class="payroll-alert error">
            <i class="fa fa-exclamation-circle"></i>
            <div><?= htmlspecialchars((string)$_GET['error']) ?></div>
        </div>
    <?php } ?>
    <?php if (isset($_GET['success'])) { ?>
        <div class="payroll-alert success">
            <i class="fa fa-check-circle"></i>
            <div><?= htmlspecialchars((string)$_GET['success']) ?></div>
        </div>
    <?php } ?>

    <div class="payroll-shell">
        <section class="payroll-card payroll-hero payroll-screen-only">
            <div>
                <span class="payroll-eyebrow"><i class="fa fa-money"></i> Payroll System</span>
                <h2>Compute payroll from hourly rate, time deductions, government deductions, and custom deductions.</h2>
                <p>Each employee's pay is calculated from their hourly rate, reduced by attendance deduction hours, then reduced again by standard government deductions and custom deductions like cash advances, loans, laptops, uniforms, smartphones, or other recurring items for the selected month.</p>
            </div>
            <div class="payroll-meta">
                <div class="payroll-meta-item">
                    <span>Payroll Period</span>
                    <strong><?= htmlspecialchars($periodLabel) ?></strong>
                </div>
                <div class="payroll-meta-item">
                    <span>Employees</span>
                    <strong><?= (int)$displaySummary['employees'] ?></strong>
                </div>
                <div class="payroll-meta-item">
                    <span>Missing Rates</span>
                    <strong><?= (int)$displaySummary['missing_rates'] ?></strong>
                </div>
                <div class="payroll-meta-item">
                    <span>Custom Deductions</span>
                    <strong><?= count($displayManualDeductionItems) ?></strong>
                </div>
            </div>
        </section>

        <section class="payroll-card payroll-screen-only">
            <form class="payroll-filters" method="GET">
                <input type="hidden" name="section" value="<?= htmlspecialchars($activeSection) ?>">
                <div class="payroll-field">
                    <label for="month">Month</label>
                    <input id="month" type="month" name="month" value="<?= htmlspecialchars($month) ?>">
                </div>
                <div class="payroll-field">
                    <label for="user_id">Focus Employee</label>
                    <select id="user_id" name="user_id">
                        <option value="0">All employees</option>
                        <?php foreach ($allUsers as $employee) { ?>
                            <option value="<?= (int)$employee['id'] ?>" <?= (int)$employee['id'] === $selectedUserId ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$employee['full_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="payroll-actions">
                    <button class="payroll-btn primary" type="submit">
                        <i class="fa fa-filter"></i> Apply
                    </button>
                    <a class="payroll-btn secondary" href="<?= htmlspecialchars(payroll_build_url(date('Y-m'), 0, $activeSection), ENT_QUOTES) ?>">
                        <i class="fa fa-refresh"></i> Reset
                    </a>
                    <button class="payroll-btn secondary" type="button" onclick="window.print()">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </form>

            <div class="payroll-tab-nav">
                <a class="payroll-tab-link <?= $activeSection === 'computation' ? 'active' : '' ?>" href="<?= htmlspecialchars(payroll_build_url($month, $selectedUserId, 'computation'), ENT_QUOTES) ?>"><i class="fa fa-calculator"></i> Computation</a>
                <a class="payroll-tab-link <?= $activeSection === 'table' ? 'active' : '' ?>" href="<?= htmlspecialchars(payroll_build_url($month, $selectedUserId, 'table'), ENT_QUOTES) ?>"><i class="fa fa-table"></i> Payroll Table</a>
                <a class="payroll-tab-link <?= $activeSection === 'rate_manager' ? 'active' : '' ?>" href="<?= htmlspecialchars(payroll_build_url($month, $selectedUserId, 'rate_manager'), ENT_QUOTES) ?>"><i class="fa fa-money"></i> Rate Manager</a>
                <a class="payroll-tab-link <?= $activeSection === 'deductions' ? 'active' : '' ?>" href="<?= htmlspecialchars(payroll_build_url($month, $selectedUserId, 'deductions'), ENT_QUOTES) ?>"><i class="fa fa-minus-circle"></i> Deductions</a>
                <a class="payroll-tab-link <?= $activeSection === 'payslip' ? 'active' : '' ?>" href="<?= htmlspecialchars(payroll_build_url($month, $selectedUserId, 'payslip'), ENT_QUOTES) ?>"><i class="fa fa-file-text-o"></i> Payslip Preview</a>
            </div>

            <div class="payroll-summary">
                <div class="payroll-stat">
                    <span>Gross Pay</span>
                    <strong><?= payroll_money($displaySummary['gross_pay']) ?></strong>
                </div>
                <div class="payroll-stat">
                    <span>Time Deduction</span>
                    <strong><?= payroll_money($displaySummary['time_deduction_amount']) ?></strong>
                </div>
                <div class="payroll-stat">
                    <span>Gov Deductions</span>
                    <strong><?= payroll_money($displaySummary['government_deductions']) ?></strong>
                </div>
                <div class="payroll-stat">
                    <span>Custom Deductions</span>
                    <strong><?= payroll_money($displaySummary['custom_deductions']) ?></strong>
                </div>
                <div class="payroll-stat">
                    <span>Total Deductions</span>
                    <strong><?= payroll_money($displaySummary['total_deductions']) ?></strong>
                </div>
                <div class="payroll-stat">
                    <span>Net Pay</span>
                    <strong><?= payroll_money($displaySummary['net_pay']) ?></strong>
                </div>
                <div class="payroll-stat">
                    <span>Rates Pending</span>
                    <strong><?= (int)$displaySummary['missing_rates'] ?></strong>
                </div>
            </div>
        </section>

        <?php if ($activeSection === 'computation') { ?>
        <section class="payroll-card payroll-screen-only" id="payrollComputation">
            <div class="payroll-section-head">
                <div>
                    <h3>Payroll Computation</h3>
                    <p>This shows the live payroll flow: gross pay first, then time deductions, then standard government deductions, then custom deductions, then the final net pay.</p>
                </div>
            </div>
            <div class="payroll-formula-grid">
                <?php if ($selectedPayrollRow) { ?>
                    <div class="payroll-formula-card">
                        <span>Step 1</span>
                        <strong>Gross Pay</strong>
                        <small><?= payroll_hours($selectedPayrollRow['raw_hours']) ?> hrs x PHP <?= $selectedPayrollRow['has_rate'] ? payroll_money($selectedPayrollRow['hourly_rate']) : '0.00' ?></small>
                        <small>PHP <?= payroll_money($selectedPayrollRow['gross_pay']) ?></small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 2</span>
                        <strong>Time Deduction</strong>
                        <small><?= payroll_hours($selectedPayrollRow['deducted_hours']) ?> hrs x PHP <?= $selectedPayrollRow['has_rate'] ? payroll_money($selectedPayrollRow['hourly_rate']) : '0.00' ?></small>
                        <small>PHP <?= payroll_money($selectedPayrollRow['time_deduction_amount']) ?></small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 3</span>
                        <strong>Government Deductions</strong>
                        <small><?= count($selectedPayrollRow['government_deduction_items'] ?? []) ?> active default item(s)</small>
                        <small>PHP <?= payroll_money($selectedPayrollRow['government_deductions']) ?></small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 4</span>
                        <strong>Custom Deductions</strong>
                        <small><?= (int)$selectedPayrollRow['custom_deduction_count'] ?> recurring or one-time item(s) for <?= htmlspecialchars($periodLabel) ?></small>
                        <small>PHP <?= payroll_money($selectedPayrollRow['custom_deductions']) ?></small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 5</span>
                        <strong>Net Pay</strong>
                        <small>Gross pay - total deductions</small>
                        <small>PHP <?= payroll_money($selectedPayrollRow['net_pay']) ?></small>
                    </div>
                <?php } else { ?>
                    <div class="payroll-formula-card">
                        <span>Step 1</span>
                        <strong>Gross Pay</strong>
                        <small>Raw hours x employee hourly rate</small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 2</span>
                        <strong>Time Deduction</strong>
                        <small>Deducted hours x employee hourly rate</small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 3</span>
                        <strong>Government Deductions</strong>
                        <small>SSS, PhilHealth, Pag-IBIG, and withholding tax can be turned on or off from the deductions tab</small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 4</span>
                        <strong>Custom Deductions</strong>
                        <small>Cash advance, loan, laptop, smartphone, uniform, or other items can be fixed or percentage-based, and one-time or monthly</small>
                    </div>
                    <div class="payroll-formula-card">
                        <span>Step 5</span>
                        <strong>Net Pay</strong>
                        <small>Gross pay - time deduction - government deductions - custom deductions</small>
                    </div>
                <?php } ?>
            </div>
        </section>
        <?php } ?>

        <?php if ($activeSection === 'table') { ?>
        <section class="payroll-card payroll-screen-only" id="payrollTableSection">
            <div class="payroll-section-head">
                <div>
                    <h3>Payroll Overview</h3>
                    <p>Monthly payroll combines attendance, attendance deduction hours, standard government deductions, and employee-specific custom deductions.</p>
                    <?php if ($selectedUser) { ?>
                        <span class="payroll-quick-note">
                            <i class="fa fa-user"></i>
                            Focus employee: <?= htmlspecialchars((string)$selectedUser['full_name']) ?>
                        </span>
                    <?php } ?>
                </div>
                <div class="payroll-actions">
                    <button class="payroll-btn secondary" type="button" onclick="window.print()">
                        <i class="fa fa-print"></i> Print Payroll
                    </button>
                </div>
            </div>
            <?php if (empty($displayPayrollRows)) { ?>
                <div class="payroll-empty">No employees found for this workspace.</div>
            <?php } else { ?>
                <div class="payroll-table-wrap">
                    <table class="payroll-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Rate / Hr</th>
                                <th>Days</th>
                                <th>Raw Hrs</th>
                                <th>Deducted Hrs</th>
                                <th>Payable Hrs</th>
                                <th>Gross Pay</th>
                                <th>Time Deduction</th>
                                <th>Gov Deductions</th>
                                <th>Custom Deductions</th>
                                <th>Total Deductions</th>
                                <th>Net Pay</th>
                                <th>Deduction Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($displayPayrollRows as $row) {
                                $employee = $row['user'];
                                $uid = (int)($employee['id'] ?? 0);
                                $profileImage = trim((string)($employee['profile_image'] ?? ''));
                                $profileUrl = '';
                                if ($profileImage !== '' && $profileImage !== 'default.png' && is_file(__DIR__ . '/uploads/' . $profileImage)) {
                                    $profileUrl = 'uploads/' . rawurlencode($profileImage);
                                }
                                $manageLink = payroll_build_url($month, $uid, 'rate_manager');
                                $payslipLink = payroll_build_url($month, $uid, 'payslip');
                            ?>
                                <tr>
                                    <td>
                                        <div class="payroll-user">
                                            <div class="payroll-avatar">
                                                <?php if ($profileUrl !== '') { ?>
                                                    <img src="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string)($employee['full_name'] ?? 'Employee'), ENT_QUOTES) ?>">
                                                <?php } else { ?>
                                                    <?= htmlspecialchars(user_display_initials((string)($employee['full_name'] ?? 'Employee'))) ?>
                                                <?php } ?>
                                            </div>
                                            <div>
                                                <div class="payroll-user-name"><?= htmlspecialchars((string)($employee['full_name'] ?? 'Employee')) ?></div>
                                                <div class="payroll-user-email"><?= htmlspecialchars((string)($employee['username'] ?? '')) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['has_rate']) { ?>
                                            <span class="payroll-badge good"><?= payroll_money($row['hourly_rate']) ?></span>
                                        <?php } else { ?>
                                            <span class="payroll-badge warn">Needs Rate</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= (int)$row['attendance_days'] ?></td>
                                    <td><?= payroll_hours($row['raw_hours']) ?></td>
                                    <td><?= payroll_hours($row['deducted_hours']) ?></td>
                                    <td><?= payroll_hours($row['payable_hours']) ?></td>
                                    <td><?= payroll_money($row['gross_pay']) ?></td>
                                    <td><?= payroll_money($row['time_deduction_amount']) ?></td>
                                    <td><?= payroll_money($row['government_deductions']) ?></td>
                                    <td><?= payroll_money($row['custom_deductions']) ?></td>
                                    <td><?= payroll_money($row['total_deductions']) ?></td>
                                    <td><strong><?= payroll_money($row['net_pay']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars((string)$row['deduction_summary']) ?>
                                        <span class="payroll-small"><?= count($row['government_deduction_items'] ?? []) ?> gov, <?= (int)$row['custom_deduction_count'] ?> custom</span>
                                    </td>
                                    <td>
                                        <div class="payroll-action-stack">
                                            <a class="payroll-btn secondary" href="<?= htmlspecialchars($manageLink, ENT_QUOTES) ?>">Manage</a>
                                            <a class="payroll-btn secondary" href="<?= htmlspecialchars($payslipLink, ENT_QUOTES) ?>">Payslip</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6">Totals</th>
                                <th><?= payroll_money($displaySummary['gross_pay']) ?></th>
                                <th><?= payroll_money($displaySummary['time_deduction_amount']) ?></th>
                                <th><?= payroll_money($displaySummary['government_deductions']) ?></th>
                                <th><?= payroll_money($displaySummary['custom_deductions']) ?></th>
                                <th><?= payroll_money($displaySummary['total_deductions']) ?></th>
                                <th><?= payroll_money($displaySummary['net_pay']) ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php } ?>
        </section>
        <?php } ?>

        <?php if ($activeSection === 'rate_manager') { ?>
        <section class="payroll-card payroll-screen-only" id="rateManager">
            <div class="payroll-section-head">
                <div>
                    <h3>Rate Manager</h3>
                    <p>Select an employee to update the hourly rate used by the payroll computation for <?= htmlspecialchars($periodLabel) ?>.</p>
                </div>
            </div>

            <?php if ($selectedUser && $selectedPayrollRow) { ?>
                <div style="padding:0 24px 24px;">
                    <div class="payroll-card" style="box-shadow:none;">
                        <div class="payroll-inline-form">
                            <div class="payroll-user">
                                <div class="payroll-avatar"><?= htmlspecialchars(user_display_initials((string)($selectedUser['full_name'] ?? 'Employee'))) ?></div>
                                <div>
                                    <div class="payroll-user-name"><?= htmlspecialchars((string)($selectedUser['full_name'] ?? 'Employee')) ?></div>
                                    <div class="payroll-user-email"><?= htmlspecialchars((string)($selectedUser['username'] ?? '')) ?></div>
                                </div>
                            </div>

                            <div class="payroll-breakdown">
                                <div class="payroll-break-item">
                                    <span>Hourly Rate</span>
                                    <strong><?= $selectedPayrollRow['has_rate'] ? payroll_money($selectedPayrollRow['hourly_rate']) : 'Not Set' ?></strong>
                                </div>
                                <div class="payroll-break-item">
                                    <span>Payable Hours</span>
                                    <strong><?= payroll_hours($selectedPayrollRow['payable_hours']) ?></strong>
                                </div>
                                <div class="payroll-break-item">
                                    <span>Time Deduction</span>
                                    <strong><?= payroll_money($selectedPayrollRow['time_deduction_amount']) ?></strong>
                                </div>
                                <div class="payroll-break-item">
                                    <span>Net Pay</span>
                                    <strong><?= payroll_money($selectedPayrollRow['net_pay']) ?></strong>
                                </div>
                            </div>

                            <form method="POST" action="app/update-user-rate.php">
                                <?= csrf_field('update_user_hourly_rate_form') ?>
                                <input type="hidden" name="user_id" value="<?= (int)$selectedUser['id'] ?>">
                                <input type="hidden" name="return_context" value="payroll">
                                <input type="hidden" name="return_month" value="<?= htmlspecialchars($month) ?>">
                                <input type="hidden" name="return_user_id" value="<?= (int)$selectedUser['id'] ?>">
                                <input type="hidden" name="return_section" value="rate_manager">

                                <div class="payroll-field">
                                    <label for="rate_manager_hourly_rate">Update Hourly Rate</label>
                                    <input
                                        id="rate_manager_hourly_rate"
                                        type="number"
                                        name="hourly_rate"
                                        min="0"
                                        step="0.01"
                                        value="<?= $selectedPayrollRow['has_rate'] ? htmlspecialchars((string)$selectedPayrollRow['hourly_rate']) : '' ?>"
                                        placeholder="0.00"
                                    >
                                </div>
                                <div class="payroll-actions" style="margin-top:14px;">
                                    <button class="payroll-btn primary" type="submit">
                                        <i class="fa fa-save"></i> Save Rate
                                    </button>
                                    <a class="payroll-btn secondary" href="<?= htmlspecialchars(payroll_build_url($month, (int)$selectedUser['id'], 'deductions'), ENT_QUOTES) ?>">
                                        <i class="fa fa-minus-circle"></i> Open Deductions
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="payroll-empty">Choose an employee from the payroll filter first so you can update the hourly rate for that employee.</div>
            <?php } ?>
        </section>
        <?php } ?>

        <?php if ($activeSection === 'deductions') { ?>
        <section class="payroll-card payroll-screen-only" id="deductionLedger">
            <div class="payroll-section-head">
                <div>
                    <h3>Deductions</h3>
                    <p>Manage the default government deductions and add custom recurring or one-time deductions that apply to payroll for <?= htmlspecialchars($periodLabel) ?>.</p>
                </div>
            </div>
            <div class="payroll-section-head">
                <div>
                    <h3>Standard Government Deductions</h3>
                    <p>Toggle which mandatory deductions apply by default when computing payroll and payslips.</p>
                </div>
            </div>
            <form method="POST" action="app/update-payroll-government-settings.php">
                <?= csrf_field('payroll_government_settings_form') ?>
                <input type="hidden" name="redirect_month" value="<?= htmlspecialchars($month) ?>">
                <input type="hidden" name="redirect_user_id" value="<?= (int)$selectedUserId ?>">
                <input type="hidden" name="redirect_section" value="deductions">

                <div class="payroll-gov-grid">
                    <?php foreach ($governmentCatalog as $govKey => $govConfig) {
                        $settingKey = (string)($govConfig['setting_key'] ?? '');
                    ?>
                        <label class="payroll-gov-card">
                            <input type="checkbox" name="<?= htmlspecialchars($settingKey) ?>" value="1" <?= !empty($governmentSettings[$settingKey]) ? 'checked' : '' ?>>
                            <div>
                                <strong><?= htmlspecialchars((string)($govConfig['label'] ?? ucfirst($govKey))) ?></strong>
                                <span><?= htmlspecialchars((string)($govConfig['description'] ?? '')) ?></span>
                            </div>
                        </label>
                    <?php } ?>
                </div>
                <div class="payroll-actions" style="padding:0 24px 24px;">
                    <button class="payroll-btn primary" type="submit">
                        <i class="fa fa-save"></i> Save Government Deductions
                    </button>
                </div>
            </form>

            <div class="payroll-section-head">
                <div>
                    <h3>Custom Deductions</h3>
                    <p>Add recurring deductions like loans, uniforms, cash advances, laptops, smartphones, or other deductions per employee.</p>
                </div>
            </div>
            <div style="padding:0 24px 24px;">
                <div class="payroll-card" style="box-shadow:none;" id="deductionManager">
                    <form class="payroll-inline-form" method="POST" action="app/add-payroll-deduction.php">
                        <?= csrf_field('payroll_add_deduction_form') ?>
                        <input type="hidden" name="redirect_month" value="<?= htmlspecialchars($month) ?>">
                        <input type="hidden" name="redirect_section" value="deductions">

                        <div class="payroll-form-grid">
                            <div class="payroll-field">
                                <label for="user_id_custom_deduction">Employee</label>
                                <select id="user_id_custom_deduction" name="user_id" required>
                                    <option value="">Select employee</option>
                                    <?php foreach ($allUsers as $employee) { ?>
                                        <option value="<?= (int)$employee['id'] ?>" <?= (int)$employee['id'] === $selectedUserId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)($employee['full_name'] ?? 'Employee')) ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="payroll-field">
                                <label for="title">Description</label>
                                <input id="title" type="text" name="title" maxlength="150" placeholder="SSS loan, uniform balance, cash advance" required>
                            </div>
                            <div class="payroll-field">
                                <label for="amount">Amount</label>
                                <input id="amount" type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="payroll-field">
                                <label for="deduction_type">Category</label>
                                <select id="deduction_type" name="deduction_type">
                                    <?php foreach ($deductionTypes as $typeKey => $typeLabel) { ?>
                                        <option value="<?= htmlspecialchars($typeKey) ?>"><?= htmlspecialchars($typeLabel) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="payroll-field">
                                <label for="amount_mode">Type</label>
                                <select id="amount_mode" name="amount_mode">
                                    <?php foreach ($deductionAmountModes as $modeKey => $modeLabel) { ?>
                                        <option value="<?= htmlspecialchars($modeKey) ?>"><?= htmlspecialchars($modeLabel) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="payroll-field">
                                <label for="apply_period">Apply Period</label>
                                <select id="apply_period" name="apply_period">
                                    <?php foreach ($deductionPeriodLabels as $periodKey => $periodLabelItem) { ?>
                                        <option value="<?= htmlspecialchars($periodKey) ?>"><?= htmlspecialchars($periodLabelItem) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="payroll-field">
                                <label for="deduction_date">Start Date</label>
                                <input id="deduction_date" type="date" name="deduction_date" value="<?= htmlspecialchars($defaultDeductionDate) ?>" max="<?= htmlspecialchars($endDate) ?>" required>
                            </div>
                            <div class="payroll-field" style="grid-column: span 2;">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" maxlength="500" placeholder="Optional remarks for this deduction"></textarea>
                            </div>
                        </div>

                        <div class="payroll-actions">
                            <button class="payroll-btn primary" type="submit">
                                <i class="fa fa-plus"></i> Add Deduction
                            </button>
                            <?php if ($selectedUserId > 0) { ?>
                                <a class="payroll-btn secondary" href="<?= htmlspecialchars(payroll_build_url($month, $selectedUserId, 'rate_manager'), ENT_QUOTES) ?>">
                                    <i class="fa fa-money"></i> Open Rate Manager
                                </a>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="payroll-section-head">
                <div>
                    <h3>Custom Deduction Ledger</h3>
                    <p>This ledger lists every active custom deduction included in payroll for <?= htmlspecialchars($periodLabel) ?>.</p>
                </div>
            </div>
            <?php if (empty($displayManualDeductionItems)) { ?>
                <div class="payroll-empty">No custom deductions are active for this payroll period.</div>
            <?php } else { ?>
                <div class="payroll-table-wrap">
                    <table class="payroll-subtable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Start Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Apply Period</th>
                                <th>Saved Value</th>
                                <th>Payroll Amount</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($displayManualDeductionItems as $item) {
                                $uid = (int)($item['user_id'] ?? 0);
                                $itemUser = null;
                                foreach ($payrollRows as $row) {
                                    if ((int)($row['user']['id'] ?? 0) === $uid) {
                                        $itemUser = $row['user'];
                                        break;
                                    }
                                }
                                $typeKey = payroll_deduction_normalize_type($item['deduction_type'] ?? 'other');
                                $amountModeKey = payroll_deduction_normalize_amount_mode($item['amount_mode'] ?? 'fixed');
                                $periodKey = payroll_deduction_normalize_period($item['apply_period'] ?? 'once');
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($itemUser['full_name'] ?? 'Employee')) ?></td>
                                    <td><?= htmlspecialchars(date('M j, Y', strtotime((string)$item['deduction_date']))) ?></td>
                                    <td><?= htmlspecialchars($deductionTypes[$typeKey] ?? 'Other') ?></td>
                                    <td><?= htmlspecialchars(payroll_item_title($item)) ?></td>
                                    <td><?= htmlspecialchars($deductionAmountModes[$amountModeKey] ?? 'Fixed amount') ?></td>
                                    <td><?= htmlspecialchars($deductionPeriodLabels[$periodKey] ?? 'One-time') ?></td>
                                    <td><?= htmlspecialchars(payroll_deduction_value_label($item)) ?></td>
                                    <td>PHP <?= payroll_money($item['computed_amount'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string)($item['notes'] ?? '')) ?></td>
                                    <td>
                                        <form method="POST" action="app/delete-payroll-deduction.php" onsubmit="return confirm('Delete this payroll deduction?');">
                                            <?= csrf_field('payroll_delete_deduction_form') ?>
                                            <input type="hidden" name="deduction_id" value="<?= (int)$item['id'] ?>">
                                            <input type="hidden" name="redirect_month" value="<?= htmlspecialchars($month) ?>">
                                            <input type="hidden" name="redirect_user_id" value="<?= (int)$selectedUserId ?>">
                                            <input type="hidden" name="redirect_section" value="deductions">
                                            <button class="payroll-btn danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>
        <?php } ?>

        <?php if ($activeSection === 'payslip') { ?>
        <section class="payroll-card payroll-screen-only" id="payslipPreview">
            <div class="payroll-section-head">
                <div>
                    <h3>Payslip Preview</h3>
                    <p>This preview uses the same payroll computation and deduction lines as the printable payslip.</p>
                </div>
            </div>

            <?php if (!$selectedPayrollRow) { ?>
                <div class="payroll-empty">Select one employee from the payroll filter to preview a payslip.</div>
            <?php } else { ?>
                <div class="payroll-section">
                    <div class="payroll-payslip-sheet preview">
                        <div class="payroll-payslip-toolbar">
                            <div class="payroll-quick-note">
                                <i class="fa fa-file-text-o"></i>
                                Payslip preview for <?= htmlspecialchars((string)$selectedPayrollRow['user']['full_name']) ?>
                            </div>
                            <div class="payroll-actions">
                                <button class="payroll-btn primary" type="button" onclick="window.print()">
                                    <i class="fa fa-print"></i> Print Payslip
                                </button>
                            </div>
                        </div>

                        <div class="payroll-payslip-header">
                            <div>
                                <div class="payroll-payslip-company"><?= htmlspecialchars($workspaceName) ?></div>
                                <div class="payroll-payslip-period">Payslip for <?= htmlspecialchars($periodLabel) ?></div>
                            </div>
                            <div class="payroll-payslip-employee">
                                <div class="payroll-payslip-name"><?= htmlspecialchars((string)($selectedPayrollRow['user']['full_name'] ?? 'Employee')) ?></div>
                                <div><?= htmlspecialchars((string)($selectedPayrollRow['user']['username'] ?? '')) ?></div>
                                <div>User ID #<?= (int)($selectedPayrollRow['user']['id'] ?? 0) ?></div>
                            </div>
                        </div>

                        <div class="payroll-payslip-section">
                            <div class="payroll-payslip-section-title">Earnings</div>
                            <div class="payroll-payslip-line">
                                <span>Hourly rate</span>
                                <span><?= $selectedPayrollRow['has_rate'] ? 'PHP ' . payroll_money($selectedPayrollRow['hourly_rate']) : 'Not set' ?></span>
                            </div>
                            <div class="payroll-payslip-line">
                                <span>Attendance days</span>
                                <span><?= (int)$selectedPayrollRow['attendance_days'] ?></span>
                            </div>
                            <div class="payroll-payslip-line">
                                <span>Time rendered</span>
                                <span><?= payroll_hours($selectedPayrollRow['raw_hours']) ?> hrs</span>
                            </div>
                            <div class="payroll-payslip-line">
                                <span>Gross pay</span>
                                <span>PHP <?= payroll_money($selectedPayrollRow['gross_pay']) ?></span>
                            </div>
                        </div>

                        <div class="payroll-payslip-section">
                            <div class="payroll-payslip-section-title">Deductions</div>
                            <div class="payroll-payslip-line">
                                <span>Time deduction (<?= payroll_hours($selectedPayrollRow['deducted_hours']) ?> hrs)</span>
                                <span>PHP <?= payroll_money($selectedPayrollRow['time_deduction_amount']) ?></span>
                            </div>
                            <?php foreach ($selectedGovernmentDeductions as $item) { ?>
                                <div class="payroll-payslip-line">
                                    <span><?= htmlspecialchars((string)($item['label'] ?? 'Government deduction')) ?></span>
                                    <span>PHP <?= payroll_money($item['amount'] ?? 0) ?></span>
                                </div>
                            <?php } ?>
                            <?php foreach ($selectedCustomDeductions as $item) { ?>
                                <div class="payroll-payslip-line">
                                    <span><?= htmlspecialchars(payroll_item_title($item)) ?> (<?= htmlspecialchars(payroll_deduction_value_label($item)) ?>)</span>
                                    <span>PHP <?= payroll_money($item['computed_amount'] ?? 0) ?></span>
                                </div>
                            <?php } ?>
                            <?php if (empty($selectedGovernmentDeductions) && empty($selectedCustomDeductions)) { ?>
                                <div class="payroll-payslip-line">
                                    <span>Additional deductions</span>
                                    <span>PHP 0.00</span>
                                </div>
                            <?php } ?>
                            <?php if (!empty($selectedGovernmentDeductions)) { ?>
                                <div class="payroll-payslip-line">
                                    <span>Government deductions subtotal</span>
                                    <span>PHP <?= payroll_money($selectedPayrollRow['government_deductions']) ?></span>
                                </div>
                            <?php } ?>
                            <?php if (!empty($selectedCustomDeductions)) { ?>
                                <div class="payroll-payslip-line">
                                    <span>Custom deductions subtotal</span>
                                    <span>PHP <?= payroll_money($selectedPayrollRow['custom_deductions']) ?></span>
                                </div>
                            <?php } ?>
                            <div class="payroll-payslip-line">
                                <span><strong>Total deductions</strong></span>
                                <span><strong>PHP <?= payroll_money($selectedPayrollRow['total_deductions']) ?></strong></span>
                            </div>
                        </div>

                        <div class="payroll-payslip-section">
                            <div class="payroll-payslip-section-title">Summary</div>
                            <div class="payroll-payslip-line">
                                <span>Pay after time deduction</span>
                                <span>PHP <?= payroll_money($selectedPayrollRow['pay_after_time']) ?></span>
                            </div>
                            <div class="payroll-payslip-line">
                                <span>Payable time</span>
                                <span><?= payroll_hours($selectedPayrollRow['payable_hours']) ?> hrs</span>
                            </div>
                            <div class="payroll-payslip-line">
                                <span>Generated</span>
                                <span><?= htmlspecialchars($generatedAtLabel) ?></span>
                            </div>
                        </div>

                        <div class="payroll-payslip-total">
                            <span>Net pay</span>
                            <strong>PHP <?= payroll_money($selectedPayrollRow['net_pay']) ?></strong>
                        </div>

                        <div class="payroll-payslip-footnote">
                            Computer-generated payslip. No signature required.
                        </div>
                    </div>
                </div>
            <?php } ?>
        </section>
        <?php } ?>

        <section class="payroll-card payroll-print-panel payroll-tab-panel-hidden" id="payrollPrintSection">
            <div class="payroll-section payroll-screen-only">
                <div class="payroll-section-head">
                    <div>
                        <h3>Printable Payroll</h3>
                        <p><?= $selectedPayrollRow ? 'Use the print button to print the selected employee payslip.' : 'Use the print button to open a clean payroll register table.' ?></p>
                    </div>
                    <div class="payroll-actions">
                        <button class="payroll-btn primary" type="button" onclick="window.print()">
                            <i class="fa fa-print"></i> <?= $selectedPayrollRow ? 'Print Payslip' : 'Print Payroll Register' ?>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($selectedPayrollRow) { ?>
                <div class="payroll-payslip-sheet">
                    <div class="payroll-payslip-header">
                        <div>
                            <div class="payroll-payslip-company"><?= htmlspecialchars($workspaceName) ?></div>
                            <div class="payroll-payslip-period">Payslip for <?= htmlspecialchars($periodLabel) ?></div>
                        </div>
                        <div class="payroll-payslip-employee">
                            <div class="payroll-payslip-name"><?= htmlspecialchars((string)($selectedPayrollRow['user']['full_name'] ?? 'Employee')) ?></div>
                            <div><?= htmlspecialchars((string)($selectedPayrollRow['user']['username'] ?? '')) ?></div>
                            <div>User ID #<?= (int)($selectedPayrollRow['user']['id'] ?? 0) ?></div>
                        </div>
                    </div>

                    <div class="payroll-payslip-section">
                        <div class="payroll-payslip-section-title">Earnings</div>
                        <div class="payroll-payslip-line">
                            <span>Hourly rate</span>
                            <span><?= $selectedPayrollRow['has_rate'] ? 'PHP ' . payroll_money($selectedPayrollRow['hourly_rate']) : 'Not set' ?></span>
                        </div>
                        <div class="payroll-payslip-line">
                            <span>Attendance days</span>
                            <span><?= (int)$selectedPayrollRow['attendance_days'] ?></span>
                        </div>
                        <div class="payroll-payslip-line">
                            <span>Time rendered</span>
                            <span><?= payroll_hours($selectedPayrollRow['raw_hours']) ?> hrs</span>
                        </div>
                        <div class="payroll-payslip-line">
                            <span>Gross pay</span>
                            <span>PHP <?= payroll_money($selectedPayrollRow['gross_pay']) ?></span>
                        </div>
                    </div>

                    <div class="payroll-payslip-section">
                        <div class="payroll-payslip-section-title">Deductions</div>
                        <div class="payroll-payslip-line">
                            <span>Time deduction (<?= payroll_hours($selectedPayrollRow['deducted_hours']) ?> hrs)</span>
                            <span>PHP <?= payroll_money($selectedPayrollRow['time_deduction_amount']) ?></span>
                        </div>
                        <?php foreach ($selectedGovernmentDeductions as $item) { ?>
                            <div class="payroll-payslip-line">
                                <span><?= htmlspecialchars((string)($item['label'] ?? 'Government deduction')) ?></span>
                                <span>PHP <?= payroll_money($item['amount'] ?? 0) ?></span>
                            </div>
                        <?php } ?>
                        <?php foreach ($selectedCustomDeductions as $item) { ?>
                            <div class="payroll-payslip-line">
                                <span><?= htmlspecialchars(payroll_item_title($item)) ?> (<?= htmlspecialchars(payroll_deduction_value_label($item)) ?>)</span>
                                <span>PHP <?= payroll_money($item['computed_amount'] ?? 0) ?></span>
                            </div>
                        <?php } ?>
                        <?php if (empty($selectedGovernmentDeductions) && empty($selectedCustomDeductions)) { ?>
                            <div class="payroll-payslip-line">
                                <span>Additional deductions</span>
                                <span>PHP 0.00</span>
                            </div>
                        <?php } ?>
                        <?php if (!empty($selectedGovernmentDeductions)) { ?>
                            <div class="payroll-payslip-line">
                                <span>Government deductions subtotal</span>
                                <span>PHP <?= payroll_money($selectedPayrollRow['government_deductions']) ?></span>
                            </div>
                        <?php } ?>
                        <?php if (!empty($selectedCustomDeductions)) { ?>
                            <div class="payroll-payslip-line">
                                <span>Custom deductions subtotal</span>
                                <span>PHP <?= payroll_money($selectedPayrollRow['custom_deductions']) ?></span>
                            </div>
                        <?php } ?>
                        <div class="payroll-payslip-line">
                            <span><strong>Total deductions</strong></span>
                            <span><strong>PHP <?= payroll_money($selectedPayrollRow['total_deductions']) ?></strong></span>
                        </div>
                    </div>

                    <div class="payroll-payslip-section">
                        <div class="payroll-payslip-section-title">Summary</div>
                        <div class="payroll-payslip-line">
                            <span>Pay after time deduction</span>
                            <span>PHP <?= payroll_money($selectedPayrollRow['pay_after_time']) ?></span>
                        </div>
                        <div class="payroll-payslip-line">
                            <span>Payable time</span>
                            <span><?= payroll_hours($selectedPayrollRow['payable_hours']) ?> hrs</span>
                        </div>
                        <div class="payroll-payslip-line">
                            <span>Generated</span>
                            <span><?= htmlspecialchars($generatedAtLabel) ?></span>
                        </div>
                    </div>

                    <div class="payroll-payslip-total">
                        <span>Net pay</span>
                        <strong>PHP <?= payroll_money($selectedPayrollRow['net_pay']) ?></strong>
                    </div>

                    <div class="payroll-payslip-footnote">
                        Computer-generated payslip. No signature required.
                    </div>
                </div>
            <?php } else { ?>
                <div class="payroll-print-sheet">
                    <div class="payroll-print-head">
                        <div>
                            <h2 class="payroll-print-title"><?= htmlspecialchars($workspaceName) ?></h2>
                            <div class="payroll-print-subtitle">Payroll Register for <?= htmlspecialchars($periodLabel) ?></div>
                        </div>
                        <div class="payroll-print-meta">
                            <div><strong>Generated:</strong> <?= htmlspecialchars($generatedAtLabel) ?></div>
                            <div><strong>Employees:</strong> <?= (int)$displaySummary['employees'] ?></div>
                            <div><strong>Focus employee:</strong> All Employees</div>
                        </div>
                    </div>

                    <div class="payroll-print-block">
                        <h3>Payroll Table</h3>
                        <table class="payroll-print-table">
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Rate / Hr</th>
                                    <th>Days</th>
                                    <th>Time Rendered</th>
                                    <th>Time Deducted</th>
                                    <th>Payable Time</th>
                                    <th>Gross Pay</th>
                                    <th>Time Deduction</th>
                                    <th>Gov Deductions</th>
                                    <th>Custom Deductions</th>
                                    <th>Total Deductions</th>
                                    <th>Net Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($displayPayrollRows as $row) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($row['user']['full_name'] ?? 'Employee')) ?></td>
                                        <td><?= $row['has_rate'] ? 'PHP ' . payroll_money($row['hourly_rate']) : 'Not set' ?></td>
                                        <td><?= (int)$row['attendance_days'] ?></td>
                                        <td><?= payroll_hours($row['raw_hours']) ?> hrs</td>
                                        <td><?= payroll_hours($row['deducted_hours']) ?> hrs</td>
                                        <td><?= payroll_hours($row['payable_hours']) ?> hrs</td>
                                        <td>PHP <?= payroll_money($row['gross_pay']) ?></td>
                                        <td>PHP <?= payroll_money($row['time_deduction_amount']) ?></td>
                                        <td>PHP <?= payroll_money($row['government_deductions']) ?></td>
                                        <td>PHP <?= payroll_money($row['custom_deductions']) ?></td>
                                        <td>PHP <?= payroll_money($row['total_deductions']) ?></td>
                                        <td>PHP <?= payroll_money($row['net_pay']) ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="6">Totals</th>
                                    <th>PHP <?= payroll_money($displaySummary['gross_pay']) ?></th>
                                    <th>PHP <?= payroll_money($displaySummary['time_deduction_amount']) ?></th>
                                    <th>PHP <?= payroll_money($displaySummary['government_deductions']) ?></th>
                                    <th>PHP <?= payroll_money($displaySummary['custom_deductions']) ?></th>
                                    <th>PHP <?= payroll_money($displaySummary['total_deductions']) ?></th>
                                    <th>PHP <?= payroll_money($displaySummary['net_pay']) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </section>
    </div>
</div>
</body>
</html>
