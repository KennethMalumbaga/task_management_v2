<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';
require_once 'inc/tenant.php';
require_once 'inc/csrf.php';
require_once 'inc/attendance_pause.php';

// Only allow admins
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!csrf_verify('admin_clock_out_action', $_POST['csrf_token'] ?? null, false)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request']);
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
        AND (time_out IS NULL OR time_out = '00:00:00')";
$params = [$user_id, $today];
$scope = tenant_get_scope($pdo, 'attendance');
$sql .= $scope['sql'] . "
        LIMIT 1";
$params = array_merge($params, $scope['params']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode(['status' => 'error', 'message' => 'No active session for this user']);
    exit;
}

// Calculate total hours, excluding paused time.
$effectiveSeconds = attendance_pause_calculate_effective_seconds(
    $pdo,
    $att,
    tenant_get_current_org_id(),
    $today . ' ' . $now
);
$hours = round($effectiveSeconds / 3600, 2);
if ($hours < 0) {
    $hours = 0;
}

attendance_pause_close_active($pdo, (int)$att['id'], tenant_get_current_org_id(), $today . ' ' . $now);

// Update attendance record
$sql = "UPDATE attendance SET time_out = ?, total_hours = ? WHERE id = ?";
$params = [$now, $hours, $att['id']];
$scope = tenant_get_scope($pdo, 'attendance');
$sql .= $scope['sql'];
$params = array_merge($params, $scope['params']);
$pdo->prepare($sql)->execute($params);

echo json_encode([
    'status' => 'success',
    'message' => 'User clocked out successfully',
    'time_out' => date('h:i A', strtotime($now)),
    'total_hours' => $hours
]);
