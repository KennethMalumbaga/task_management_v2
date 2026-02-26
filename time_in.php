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
   DETERMINE SESSION
-------------------------- */
$hour = (int) date('H');

if ($hour >= 5 && $hour < 12) {
    $session = 'morning';
} elseif ($hour >= 12 && $hour < 18) {
    $session = 'afternoon';
} else {
    $session = 'overtime';
}

/* -------------------------
   GET TODAY ATTENDANCE
-------------------------- */
$sql = "SELECT * FROM attendance 
        WHERE user_id = ? AND att_date = ?
        ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id, $today]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------
   IF SESSION ALREADY OPEN
   → RETURN IT (NOT ERROR)
-------------------------- */
if ($att && $att["{$session}_in"] && !$att["{$session}_out"]) {
    echo json_encode([
        'status' => 'success',
        'attendance_id' => $att['id'],
        'session' => $session,
        'time_in' => $att["{$session}_in"],
        'message' => 'Session already active'
    ]);
    exit;
}

/* -------------------------
   CREATE OR UPDATE
-------------------------- */
if (!$att) {
    $sql = "INSERT INTO attendance (user_id, att_date, {$session}_in)
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $today, $now]);
    $attendance_id = $conn->lastInsertId();
} else {
    $sql = "UPDATE attendance SET {$session}_in = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$now, $att['id']]);
    $attendance_id = $att['id'];
}

echo json_encode([
    'status' => 'success',
    'attendance_id' => $attendance_id,
    'session' => $session,
    'time_in' => $now
]);
