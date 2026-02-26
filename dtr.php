<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}
require 'DB_connection.php';

$user_id = $_SESSION['id'];

// Determine reference date from GET param or today
$ref = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$ts = strtotime($ref);
// week start: Monday
$weekStart = strtotime('monday this week', $ts);
if (date('N', $ts) == 1) { $weekStart = strtotime(date('Y-m-d', $ts)); }
$weekEnd = strtotime('+6 days', $weekStart);
$startDate = date('Y-m-d', $weekStart);
$endDate = date('Y-m-d', $weekEnd);

// Fetch per-day attendance rows for the week
$sql = "SELECT att_date, morning_in, morning_out, afternoon_in, afternoon_out, overtime_in, overtime_out, total_hours
        FROM attendance
        WHERE user_id = ? AND att_date BETWEEN ? AND ?
        ORDER BY att_date";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id, $startDate, $endDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days = [];
for ($d = strtotime($startDate); $d <= strtotime($endDate); $d = strtotime('+1 day', $d)) {
    $date = date('Y-m-d', $d);
    $days[$date] = [
        'morning_in'=>null,'morning_out'=>null,
        'afternoon_in'=>null,'afternoon_out'=>null,
        'overtime_in'=>null,'overtime_out'=>null,
        'daily_total'=>0
    ];
}

foreach ($rows as $r) {
    $date = $r['att_date'];
    if (!isset($days[$date])) continue;
    if (!empty($r['morning_in'])) $days[$date]['morning_in'] = date('g:i A', strtotime($r['morning_in']));
    if (!empty($r['morning_out'])) $days[$date]['morning_out'] = date('g:i A', strtotime($r['morning_out']));
    if (!empty($r['afternoon_in'])) $days[$date]['afternoon_in'] = date('g:i A', strtotime($r['afternoon_in']));
    if (!empty($r['afternoon_out'])) $days[$date]['afternoon_out'] = date('g:i A', strtotime($r['afternoon_out']));
    if (!empty($r['overtime_in'])) $days[$date]['overtime_in'] = date('g:i A', strtotime($r['overtime_in']));
    if (!empty($r['overtime_out'])) $days[$date]['overtime_out'] = date('g:i A', strtotime($r['overtime_out']));
    $days[$date]['daily_total'] = isset($r['total_hours']) ? (float)$r['total_hours'] : 0;
}

$weekly_total = array_sum(array_column($days, 'daily_total'));

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly DTR</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table{border-collapse:collapse;width:100%}
        td,th{border:1px solid #333;padding:6px;text-align:center}
        .controls{margin:10px 0}
    </style>
</head>
<body>
<h2>Daily Time Record (Week of <?=date('M j, Y', strtotime($startDate))?> - <?=date('M j, Y', strtotime($endDate))?>)</h2>
<div class="controls">
    <a href="?date=<?=date('Y-m-d', strtotime('-7 days', $weekStart))?>">&laquo; Prev Week</a>
    &nbsp;|&nbsp;
    <a href="?date=<?=date('Y-m-d', strtotime('+7 days', $weekStart))?>">Next Week &raquo;</a>
    &nbsp;|&nbsp;
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th colspan="2">Morning</th>
            <th colspan="2">Afternoon</th>
            <th colspan="2">Overtime</th>
            <th>Daily Total (hrs)</th>
            <th>Signature</th>
        </tr>
        <tr>
            <th></th>
            <th>In</th>
            <th>Out</th>
            <th>In</th>
            <th>Out</th>
            <th>In</th>
            <th>Out</th>
            <th></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($days as $date => $d): ?>
        <tr>
            <td><?=date('M j, D', strtotime($date))?></td>
            <td><?=$d['morning_in']?:''?></td>
            <td><?=$d['morning_out']?:''?></td>
            <td><?=$d['afternoon_in']?:''?></td>
            <td><?=$d['afternoon_out']?:''?></td>
            <td><?=$d['overtime_in']?:''?></td>
            <td><?=$d['overtime_out']?:''?></td>
            <td><?=number_format($d['daily_total'],2)?></td>
            <td></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="7">Weekly Total</th>
            <th><?=number_format($weekly_total,2)?></th>
            <th></th>
        </tr>
    </tfoot>
</table>

</body>
</html>
