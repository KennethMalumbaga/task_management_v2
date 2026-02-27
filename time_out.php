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
    $scope = tenant_get_scope($pdo, 'attendance');
    $sql .= $scope['sql'] . "
            ORDER BY id DESC
            LIMIT 1";
    $params = array_merge($params, $scope['params']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Already timed out',
        ]);
        exit;
    }

    $hours = round((strtotime($now) - strtotime($att['time_in'])) / 3600, 2);
    if ($hours < 0) {
        $hours = 0;
    }

    $updateSql = "UPDATE attendance SET time_out = ?, total_hours = ? WHERE id = ?";
    $updateParams = [$now, $hours, (int)$att['id']];
    $scope = tenant_get_scope($pdo, 'attendance');
    $updateSql .= $scope['sql'];
    $updateParams = array_merge($updateParams, $scope['params']);
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);

    echo json_encode([
        'status' => 'success',
        'time_out' => $now,
        'total_hours' => $hours,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to clock out right now.',
    ]);
}

