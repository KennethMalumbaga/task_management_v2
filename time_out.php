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

$sql = "SELECT * FROM attendance WHERE user_id=? AND att_date=?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $today]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$att || !$att['time_in']) {
    echo json_encode(['status'=>'error','message'=>'No time in']);
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
