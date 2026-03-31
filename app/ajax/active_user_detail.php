<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../model/user.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user']);
    exit;
}

$detail = get_active_user_dashboard_detail($pdo, $userId);
if (!$detail) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'This user is no longer active.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'detail' => $detail,
]);
