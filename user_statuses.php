<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include "DB_connection.php";
include "app/Model/User.php";

$raw = $_POST['user_ids'] ?? '';
$raw = is_string($raw) ? $raw : '';
$parts = array_filter(array_map('trim', explode(',', $raw)));
$ids = [];
foreach ($parts as $p) {
    if (ctype_digit($p)) {
        $ids[] = (int)$p;
    }
}

if (empty($ids)) {
    echo json_encode(['status' => 'success', 'users' => []]);
    exit();
}

$users = [];
foreach ($ids as $id) {
    $clocked_in = is_user_clocked_in($pdo, $id);
    $attendance = get_todays_attendance_stats($pdo, $id);
    $users[] = [
        'id' => $id,
        'clocked_in' => (bool)$clocked_in,
        'daily_duration' => str_replace('Oh ', '0h ', $attendance['daily_duration'] ?? '0h 0m')
    ];
}

echo json_encode(['status' => 'success', 'users' => $users]);
