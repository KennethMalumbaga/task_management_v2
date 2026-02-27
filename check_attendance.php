<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'], $_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require 'DB_connection.php';
require_once 'inc/tenant.php';

$user_id = $_SESSION['id'];

// Check if there is an open attendance (no time_out yet)
// Aligning with time_in.php which uses 'time_in' column
$sql = "SELECT id FROM attendance 
        WHERE user_id = ? 
        AND att_date = CURRENT_DATE 
        AND time_in IS NOT NULL 
        AND (time_out IS NULL OR time_out = '00:00:00')";
$params = [$user_id];
$scope = tenant_get_scope($pdo, 'attendance');
$sql .= $scope['sql'] . "
        LIMIT 1";
$params = array_merge($params, $scope['params']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if ($attendance) {
    echo json_encode([
        'status' => 'success',
        'has_active_attendance' => true,
        'attendance_id' => $attendance['id']
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'has_active_attendance' => false
    ]);
}

