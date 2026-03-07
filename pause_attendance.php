<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';
require_once 'inc/tenant.php';
require_once 'inc/csrf.php';
require_once 'inc/attendance_pause.php';

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

$userId = (int)$_SESSION['id'];
$organizationId = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;
$organizationId = $organizationId > 0 ? $organizationId : null;
$attendanceId = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;
$pauseReason = trim((string)($_POST['pause_reason'] ?? ''));
session_write_close();

if ($pauseReason === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'A pause reason is required.']);
    exit;
}

if (function_exists('mb_substr')) {
    $pauseReason = mb_substr($pauseReason, 0, 255);
} else {
    $pauseReason = substr($pauseReason, 0, 255);
}

try {
    if (!attendance_pause_ensure_schema($pdo)) {
        throw new RuntimeException('pause_schema_unavailable');
    }

    $today = date('Y-m-d');
    $sql = "SELECT id, att_date, time_in, time_out
            FROM attendance
            WHERE user_id = ?
              AND att_date = ?
              AND time_in IS NOT NULL
              AND (time_out IS NULL OR time_out = '00:00:00')";
    $params = [$userId, $today];
    if ($attendanceId > 0) {
        $sql .= " AND id = ?";
        $params[] = $attendanceId;
    }
    $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organizationId);
    $sql .= $scope['sql'] . "
            ORDER BY id DESC
            LIMIT 1";
    $params = array_merge($params, $scope['params']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'No active attendance session found.']);
        exit;
    }

    $activePause = attendance_pause_get_active($pdo, (int)$attendance['id'], $userId, $organizationId);
    if ($activePause) {
        echo json_encode([
            'status' => 'success',
            'attendance_id' => (int)$attendance['id'],
            'pause_reason' => $activePause['pause_reason'],
            'paused_at' => $activePause['paused_at'],
            'message' => 'Session already paused.',
        ]);
        exit;
    }

    $pausedAt = date('Y-m-d H:i:s');
    $insertSql = "INSERT INTO attendance_pauses (attendance_id, user_id, organization_id, pause_reason, paused_at)
                  VALUES (?, ?, ?, ?, ?)";
    $insertParams = [(int)$attendance['id'], $userId, $organizationId, $pauseReason, $pausedAt];
    $pdo->prepare($insertSql)->execute($insertParams);

    echo json_encode([
        'status' => 'success',
        'attendance_id' => (int)$attendance['id'],
        'pause_reason' => $pauseReason,
        'paused_at' => $pausedAt,
        'message' => 'Session paused.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to pause the session right now.',
    ]);
}
