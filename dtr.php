<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

require 'DB_connection.php';

$user_id = $_SESSION['id'];

/* -------------------------
   FETCH ATTENDANCE RECORDS
-------------------------- */
$sql = "SELECT att_date, time_in, time_out, total_hours
        FROM attendance
        WHERE user_id = ?
        ORDER BY att_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   COMPUTE GRAND TOTAL
-------------------------- */
$grand_total = 0;
foreach ($rows as $r) {
    $grand_total += (float) $r['total_hours'];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Daily Time Record</title>
<style>
body{font-family:Arial,sans-serif}
table{border-collapse:collapse;width:100%;margin-top:20px}
th,td{border:1px solid #000;padding:8px;text-align:center}
th{background:#f0f0f0}
h2{text-align:center}
.total-row{font-weight:bold}
.print-btn{margin:10px 0}
</style>
</head>

<body>

<h2>DAILY TIME RECORD</h2>

<div class="print-btn">
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<table>
<thead>
<tr>
    <th>Date</th>
    <th>Time In</th>
    <th>Time Out</th>
    <th>Total Hours</th>
    <th>Signature</th>
</tr>
</thead>

<tbody>
<?php if (count($rows) === 0): ?>
<tr>
    <td colspan="5">No attendance records</td>
</tr>
<?php else: ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?= date('M d, Y', strtotime($r['att_date'])) ?></td>
    <td><?= $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '' ?></td>
    <td><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '' ?></td>
    <td><?= number_format($r['total_hours'], 2) ?></td>
    <td></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>

<tfoot>
<tr class="total-row">
    <td colspan="3">TOTAL HOURS</td>
    <td><?= number_format($grand_total, 2) ?></td>
    <td></td>
</tr>
</tfoot>
</table>

</body>
</html>
