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

/* CHECK TODAY RECORD */
$sql = "SELECT id, time_in FROM attendance
        WHERE user_id = ? AND att_date = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $today]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

/* INSERT IF NONE */
if (!$att) {
    $sql = "INSERT INTO attendance (user_id, att_date, time_in)
            VALUES (?, ?, ?)";
    $pdo->prepare($sql)->execute([$user_id, $today, $now]);
    
    // Get the inserted attendance ID
    $new_attendance_id = $pdo->lastInsertId();

    echo json_encode(['status'=>'success','message'=>'Time in recorded', 'attendance_id' => $new_attendance_id]);
    exit;
}

/* ALREADY TIMED IN */
if ($att['time_in']) {
    echo json_encode(['status'=>'success','message'=>'Already timed in', 'attendance_id' => $att['id']]);
    exit;
}

