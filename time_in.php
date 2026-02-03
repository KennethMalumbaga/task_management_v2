<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'employee') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['id'];
$today   = date('Y-m-d');
$now     = date('H:i:s');

/* CHECK ACTIVE SESSION */
// Only block if there is a session that is NOT clocked out
$sql = "SELECT id FROM attendance
        WHERE user_id = ? AND att_date = ? AND time_out IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $today]);
$active_att = $stmt->fetch(PDO::FETCH_ASSOC);

/* ALREADY TIMED IN */
if ($active_att) {
    echo json_encode(['status'=>'success','message'=>'Already timed in', 'attendance_id' => $active_att['id']]);
    exit;
}

/* INSERT NEW SESSION */
// If no active session, insert new one (even if others exist for today)
$sql = "INSERT INTO attendance (user_id, att_date, time_in)
        VALUES (?, ?, ?)";
$pdo->prepare($sql)->execute([$user_id, $today, $now]);

// Get the inserted attendance ID
$new_attendance_id = $pdo->lastInsertId();

echo json_encode(['status'=>'success','message'=>'Time in recorded', 'attendance_id' => $new_attendance_id]);
exit;

