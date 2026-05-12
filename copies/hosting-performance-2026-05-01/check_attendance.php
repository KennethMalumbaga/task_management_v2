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
require_once 'inc/attendance_pause.php';
require_once 'app/model/user.php';

$user_id = $_SESSION['id'];
$organization_id = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;
$organization_id = $organization_id > 0 ? $organization_id : null;
$lightweight = isset($_GET['light']) && (string)$_GET['light'] === '1';
session_write_close();
$hasHeartbeatColumn = tenant_column_exists($pdo, 'attendance', 'last_heartbeat_at');

// Check if there is an open attendance (no time_out yet)
// Aligning with time_in.php which uses 'time_in' column
$sql = "SELECT id";
if ($hasHeartbeatColumn) {
    $sql .= ", last_heartbeat_at";
}
$sql .= " FROM attendance 
        WHERE user_id = ? 
        AND att_date = CURRENT_DATE 
        AND time_in IS NOT NULL 
        AND (time_out IS NULL OR time_out = '00:00:00')";
$params = [$user_id];
$scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organization_id);
$sql .= $scope['sql'] . "
        LIMIT 1";
$params = array_merge($params, $scope['params']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lightweight) {
    echo json_encode([
        'status' => 'success',
        'has_active_attendance' => (bool)$attendance,
        'attendance_id' => $attendance['id'] ?? null,
    ]);
    exit;
}

if ($attendance) {
    $durationStats = get_todays_attendance_stats($pdo, $user_id);
    $activePause = attendance_pause_get_active($pdo, (int)$attendance['id'], (int)$user_id, $organization_id);
    $lastHeartbeatAt = null;
    $heartbeatAgeSeconds = null;
    if ($hasHeartbeatColumn && !empty($attendance['last_heartbeat_at'])) {
        $lastHeartbeatAt = (string)$attendance['last_heartbeat_at'];
        $parsedTs = strtotime($lastHeartbeatAt);
        if ($parsedTs !== false) {
            $heartbeatAgeSeconds = max(0, time() - $parsedTs);
        }
    }
    echo json_encode([
        'status' => 'success',
        'has_active_attendance' => true,
        'attendance_id' => $attendance['id'],
        'attendance_record_id' => $durationStats['latest_attendance_id'] ?? $attendance['id'],
        'time_in' => $durationStats['time_in'] ?? '--:--',
        'time_out' => $durationStats['time_out'] ?? '--:--',
        'daily_duration' => $durationStats['daily_duration'] ?? null,
        'overall_duration' => $durationStats['overall_duration'] ?? null,
        'is_paused' => $activePause ? true : false,
        'pause_reason' => $activePause['pause_reason'] ?? null,
        'pause_started_at' => $activePause['paused_at'] ?? null,
        'admin_clock_out_remark' => $durationStats['admin_clock_out_remark'] ?? null,
        'clocked_out_by_admin' => !empty($durationStats['clocked_out_by_admin']),
        'last_heartbeat_at' => $lastHeartbeatAt,
        'heartbeat_age_seconds' => $heartbeatAgeSeconds
    ]);
} else {
    $durationStats = get_todays_attendance_stats($pdo, $user_id);
    echo json_encode([
        'status' => 'success',
        'has_active_attendance' => false,
        'attendance_record_id' => $durationStats['latest_attendance_id'] ?? null,
        'time_in' => $durationStats['time_in'] ?? '--:--',
        'time_out' => $durationStats['time_out'] ?? '--:--',
        'daily_duration' => $durationStats['daily_duration'] ?? null,
        'overall_duration' => $durationStats['overall_duration'] ?? null,
        'admin_clock_out_remark' => $durationStats['admin_clock_out_remark'] ?? null,
        'clocked_out_by_admin' => !empty($durationStats['clocked_out_by_admin'])
    ]);
}
