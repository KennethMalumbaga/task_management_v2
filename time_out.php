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

// Find the ACTIVE session (time_out IS NULL) for today (or recent)
// We prioritize today's active session.
$sql = "SELECT * FROM attendance WHERE user_id=? AND time_out IS NULL ORDER BY id DESC LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode(['status'=>'error','message'=>'No active session to time out']);
    exit;
}

if ($att['time_out']) {
    echo json_encode(['status'=>'success','message'=>'Already timed out']);
    exit;
}

$hours = round((strtotime($now) - strtotime($att['time_in'])) / 3600, 2);

$sql = "UPDATE attendance SET time_out=?, total_hours=? WHERE id=?";
$pdo->prepare($sql)->execute([$now, $hours, $att['id']]);

echo json_encode(['status'=>'success','message'=>'Time out recorded']);
