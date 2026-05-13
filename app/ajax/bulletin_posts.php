<?php
session_start();
require_once __DIR__ . '/../../inc/performance.php';
performance_monitor_request('dashboard.bulletin_posts');

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['role'], $_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['id'];
$userRole = (string)$_SESSION['role'];
session_write_close();

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../model/Bulletin.php';

try {
    $posts = get_recent_bulletin_posts($pdo, 30, $userId, $userRole);
    echo json_encode([
        'status' => 'success',
        'posts' => is_array($posts) ? $posts : [],
    ]);
} catch (Throwable $e) {
    error_log('Dashboard bulletin posts fetch failed: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to load bulletin posts.',
        'posts' => [],
    ]);
}
