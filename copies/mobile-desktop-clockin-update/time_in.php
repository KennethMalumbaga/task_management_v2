<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';
require_once 'inc/tenant.php';
require_once 'inc/csrf.php';
require_once 'inc/device.php';

if (!isset($_SESSION['id'], $_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!csrf_verify('attendance_ajax_actions', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request']);
    exit;
}

if (taskflow_is_mobile_device()) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Clock-in is available only on desktop with screen capture enabled. You can still use mobile to view tasks and messages.',
        'code' => 'desktop_clock_in_required',
    ]);
    exit;
}

$user_id = (int)$_SESSION['id'];
$organization_id = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;
$organization_id = $organization_id > 0 ? $organization_id : null;
session_write_close();
$today = date('Y-m-d');
$now = date('H:i:s');

try {
    $sql = "SELECT id, time_in
            FROM attendance
            WHERE user_id = ?
              AND att_date = ?
              AND time_in IS NOT NULL
              AND (time_out IS NULL OR time_out = '00:00:00')";
    $params = [$user_id, $today];
    $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organization_id);
    $sql .= $scope['sql'] . "
            ORDER BY id DESC
            LIMIT 1";
    $params = array_merge($params, $scope['params']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $active = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active) {
        echo json_encode([
            'status' => 'success',
            'attendance_id' => (int)$active['id'],
            'time_in' => $active['time_in'],
            'message' => 'Session already active',
        ]);
        exit;
    }

    $orgId = $organization_id;
    $hasOrgColumn = tenant_column_exists($pdo, 'attendance', 'organization_id');
    $hasHeartbeatColumn = tenant_column_exists($pdo, 'attendance', 'last_heartbeat_at');

    if ($hasOrgColumn && $orgId) {
        if ($hasHeartbeatColumn) {
            $insertSql = "INSERT INTO attendance (user_id, att_date, time_in, organization_id, last_heartbeat_at)
                          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $insertParams = [$user_id, $today, $now, $orgId];
        } else {
            $insertSql = "INSERT INTO attendance (user_id, att_date, time_in, organization_id)
                          VALUES (?, ?, ?, ?)";
            $insertParams = [$user_id, $today, $now, $orgId];
        }
    } else {
        if ($hasHeartbeatColumn) {
            $insertSql = "INSERT INTO attendance (user_id, att_date, time_in, last_heartbeat_at)
                          VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            $insertParams = [$user_id, $today, $now];
        } else {
            $insertSql = "INSERT INTO attendance (user_id, att_date, time_in)
                          VALUES (?, ?, ?)";
            $insertParams = [$user_id, $today, $now];
        }
    }

    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute($insertParams);
    $attendance_id = (int)$pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'attendance_id' => $attendance_id,
        'time_in' => $now,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to clock in right now.',
    ]);
}
