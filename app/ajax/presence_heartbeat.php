<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../inc/csrf.php';
if (!csrf_verify('presence_heartbeat', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request']);
    exit;
}

$userId = (int)($_SESSION['id'] ?? 0);
$organizationId = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;

session_write_close();

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../model/user.php';

date_default_timezone_set('Asia/Manila');

try {
    user_presence_touch($pdo, $userId, $organizationId);
    echo json_encode([
        'status' => 'success',
        'touched_at' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to update presence right now.']);
}
