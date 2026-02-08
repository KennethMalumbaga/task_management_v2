<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';

// Only allow admins
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit;
}

$today = date('Y-m-d');
$now = date('H:i:s');

// Find active attendance session for this user today
$sql = "SELECT * FROM attendance 
        WHERE user_id = ? 
        AND att_date = ? 
        AND time_in IS NOT NULL 
        AND (time_out IS NULL OR time_out = '00:00:00')
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $today]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode(['status' => 'error', 'message' => 'No active session for this user']);
    exit;
}

// Calculate total hours
$hours = round((strtotime($now) - strtotime($att['time_in'])) / 3600, 2);

// Update attendance record
$sql = "UPDATE attendance SET time_out = ?, total_hours = ? WHERE id = ?";
$pdo->prepare($sql)->execute([$now, $hours, $att['id']]);

echo json_encode([
    'status' => 'success',
    'message' => 'User clocked out successfully',
    'time_out' => date('h:i A', strtotime($now)),
    'total_hours' => $hours
]);
