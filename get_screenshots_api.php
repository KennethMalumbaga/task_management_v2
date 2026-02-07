<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include "DB_connection.php";
include "app/model/user.php";

// Get filter parameters
$filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filter_date = isset($_GET['date']) ? $_GET['date'] : null;

// Build query
$sql = "SELECT s.*, u.full_name, u.username, a.time_in, a.time_out 
        FROM screenshots s 
        INNER JOIN users u ON s.user_id = u.id 
        LEFT JOIN attendance a ON s.attendance_id = a.id 
        WHERE 1=1";
$params = [];

if ($filter_user_id) {
    $sql .= " AND s.user_id = ?";
    $params[] = $filter_user_id;
}

if ($filter_date) {
    $sql .= " AND DATE(s.taken_at) = ?";
    $params[] = $filter_date;
}

$sql .= " ORDER BY s.taken_at DESC";

$stmt = $pdo->prepare($sql);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format screenshots for JSON response
$formatted_screenshots = [];
foreach ($screenshots as $screenshot) {
    $imagePath = $screenshot['image_path'];
    $fileExists = file_exists($imagePath);
    
    // Add cache-busting timestamp to image path
    $imageUrl = null;
    if ($fileExists && file_exists($imagePath)) {
        $mtime = @filemtime($imagePath);
        $imageUrl = $imagePath . '?t=' . ($mtime ? $mtime : time());
    }
    
    $formatted_screenshots[] = [
        'id' => $screenshot['id'],
        'user_id' => $screenshot['user_id'],
        'attendance_id' => $screenshot['attendance_id'],
        'image_path' => $imagePath,
        'image_url' => $imageUrl,
        'file_exists' => $fileExists,
        'full_name' => $screenshot['full_name'],
        'username' => $screenshot['username'],
        'taken_at' => $screenshot['taken_at'],
        'taken_at_formatted' => date('Y-m-d H:i:s', strtotime($screenshot['taken_at'])),
        'time_in' => $screenshot['time_in'] ? date('Y-m-d H:i:s', strtotime($screenshot['time_in'])) : null,
        'time_out' => $screenshot['time_out'] ? date('Y-m-d H:i:s', strtotime($screenshot['time_out'])) : null
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'screenshots' => $formatted_screenshots
]);



