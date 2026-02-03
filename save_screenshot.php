<?php
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require 'DB_connection.php';

$user_id = $_SESSION['id'];
$attendance_id = isset($_POST['attendance_id']) ? (int) $_POST['attendance_id'] : null;

// DEBUG LOGGING
$logFile = __DIR__ . '/screenshot_debug.log';
$logEntry = date('Y-m-d H:i:s') . " - Request from User: " . ($user_id ?? 'Unknown') . " - Attendance: " . ($attendance_id ?? 'None') . "\n";

if (!isset($_SESSION['id'])) {
    $logEntry .= "Error: Unauthorized (No Session)\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$imageData = $_POST['image'] ?? '';
if (empty($imageData)) {
    $logEntry .= "Error: No image data\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'No image data provided']);
    exit;
}

// Expect "data:image/png;base64,...."
if (strpos($imageData, 'base64,') !== false) {
    $imageData = substr($imageData, strpos($imageData, 'base64,') + 7);
}

$binary = base64_decode($imageData);
if ($binary === false) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to decode image']);
    exit;
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'screenshots';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'screenshots';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// ALWAYS create new screenshot filename for history
// Format: userID_attendanceID_timestamp_unique.png
$filenameOnly = $user_id . '_' . ($attendance_id ? $attendance_id : '0') . '_' . time() . '_' . uniqid() . '.png';
$relativePath = 'screenshots/' . $filenameOnly;
$fullPath = $dir . DIRECTORY_SEPARATOR . $filenameOnly;

// Save the new screenshot
if (file_put_contents($fullPath, $binary) === false) {
    $logEntry .= "Error: Failed to write file to $fullPath\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save image']);
    exit;
}

$logEntry .= "Success: Saved to $fullPath\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Insert new screenshot record (Append history)
$sql = "INSERT INTO screenshots (user_id, attendance_id, image_path, taken_at)
        VALUES (?, ?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $attendance_id ?: null, $relativePath]);

// CLEANUP: Delete screenshots older than 7 days
// This runs on every save to keep storage managed
$seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

// 1. Get files to delete
$sql_cleanup = "SELECT id, image_path FROM screenshots WHERE taken_at < ?";
$stmt_cleanup = $pdo->prepare($sql_cleanup);
$stmt_cleanup->execute([$seven_days_ago]);
$old_records = $stmt_cleanup->fetchAll(PDO::FETCH_ASSOC);

if (!empty($old_records)) {
    // 2. Delete physical files
    foreach ($old_records as $rec) {
        if (!empty($rec['image_path'])) {
            $file_to_delete = __DIR__ . DIRECTORY_SEPARATOR . $rec['image_path'];
            if (file_exists($file_to_delete)) {
                @unlink($file_to_delete);
            }
        }
    }
    
    // 3. Delete DB records
    $sql_del_cleanup = "DELETE FROM screenshots WHERE taken_at < ?";
    $stmt_del_cleanup = $pdo->prepare($sql_del_cleanup);
    $stmt_del_cleanup->execute([$seven_days_ago]);
    
    // Log cleanup
    $count = count($old_records);
    $logEntry .= "Cleanup: Deleted $count old screenshots (> 7 days)\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

echo json_encode(['status' => 'success']);


