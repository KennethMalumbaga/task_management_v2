<?php
session_start();
date_default_timezone_set('Asia/Manila');

require 'DB_connection.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['id'];
$today = date('Y-m-d');
$now   = date('H:i:s');

/* -------------------------
   GET TODAY ATTENDANCE
-------------------------- */
$sql = "SELECT * FROM attendance
        WHERE user_id = ? AND att_date = ?
        ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id, $today]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No attendance record for today'
    ]);
    exit;
}

/* -------------------------
   DETERMINE OPEN SESSION
-------------------------- */
if ($att['morning_in'] && !$att['morning_out']) {
    $session = 'morning';
} elseif ($att['afternoon_in'] && !$att['afternoon_out']) {
    $session = 'afternoon';
} elseif ($att['overtime_in'] && !$att['overtime_out']) {
    $session = 'overtime';
} else {
    // Already timed out (idempotent behavior)
    echo json_encode([
        'status' => 'success',
        'message' => 'Already timed out'
    ]);
    exit;
}

/* -------------------------
   CLOSE SESSION
-------------------------- */
$sql = "UPDATE attendance SET {$session}_out = ?
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$now, $att['id']]);

/* -------------------------
   RECALCULATE TOTAL HOURS
-------------------------- */
$total = 0;

if ($att['morning_in'] && ($session === 'morning' || $att['morning_out'])) {
    $out = ($session === 'morning') ? $now : $att['morning_out'];
    $total += (strtotime($out) - strtotime($att['morning_in'])) / 3600;
}
if ($att['afternoon_in'] && ($session === 'afternoon' || $att['afternoon_out'])) {
    $out = ($session === 'afternoon') ? $now : $att['afternoon_out'];
    $total += (strtotime($out) - strtotime($att['afternoon_in'])) / 3600;
}
if ($att['overtime_in'] && ($session === 'overtime' || $att['overtime_out'])) {
    $out = ($session === 'overtime') ? $now : $att['overtime_out'];
    $total += (strtotime($out) - strtotime($att['overtime_in'])) / 3600;
}

$sql = "UPDATE attendance SET total_hours = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([round($total, 2), $att['id']]);

echo json_encode([
    'status' => 'success',
    'session_closed' => $session,
    'time_out' => $now
]);
