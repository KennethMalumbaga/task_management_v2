<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';
require_once 'inc/tenant.php';
require_once 'inc/csrf.php';

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

$user_id = (int)$_SESSION['id'];
$attendance_id = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;
$organization_id = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;

if ($attendance_id <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid attendance id']);
    exit;
}

// Release session lock immediately after auth/csrf checks.
session_write_close();

try {
    $sql = "SELECT id
            FROM attendance
            WHERE id = ?
              AND user_id = ?
              AND time_in IS NOT NULL
              AND (time_out IS NULL OR time_out = '00:00:00')";
    $params = [$attendance_id, $user_id];
    $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organization_id);
    $sql .= $scope['sql'] . "
            LIMIT 1";
    $params = array_merge($params, $scope['params']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $active = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$active) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Attendance session is not active']);
        exit;
    }

    $persisted = false;
    if (tenant_column_exists($pdo, 'attendance', 'last_heartbeat_at')) {
        $updateSql = "UPDATE attendance
                      SET last_heartbeat_at = CURRENT_TIMESTAMP
                      WHERE id = ?";
        $updateParams = [$attendance_id];
        $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organization_id);
        $updateSql .= $scope['sql'];
        $updateParams = array_merge($updateParams, $scope['params']);
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($updateParams);
        $persisted = true;
    }

    echo json_encode([
        'status' => 'success',
        'attendance_id' => $attendance_id,
        'heartbeat_at' => date('c'),
        'persisted' => $persisted,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to record heartbeat right now.',
    ]);
}
