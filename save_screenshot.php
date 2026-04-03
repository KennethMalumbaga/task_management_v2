<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require 'DB_connection.php';
require_once 'inc/tenant.php';
require_once 'inc/csrf.php';
require_once 'inc/workspace_screenshot_retention.php';

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

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
$organization_id = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;
$organization_id = $organization_id > 0 ? $organization_id : null;
$attendance_id = isset($_POST['attendance_id']) ? (int) $_POST['attendance_id'] : null;
session_write_close();

if ($user_id <= 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// DEBUG LOGGING
$logFile = __DIR__ . '/screenshot_debug.log';
$logEntry = date('Y-m-d H:i:s') . " - Request from User: " . ($user_id ?? 'Unknown') . " - Attendance: " . ($attendance_id ?? 'None') . "\n";

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
if (tenant_column_exists($pdo, 'screenshots', 'organization_id') && $organization_id) {
    $sql = "INSERT INTO screenshots (user_id, attendance_id, image_path, taken_at, organization_id)
            VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $attendance_id ?: null, $relativePath, $organization_id]);
} else {
    $sql = "INSERT INTO screenshots (user_id, attendance_id, image_path, taken_at)
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $attendance_id ?: null, $relativePath]);
}

// CLEANUP: Delete screenshots older than the configured retention window.
// This runs on every save to keep storage managed without a separate scheduler.
$retentionDays = workspace_screenshot_retention_fetch_days($pdo, $organization_id);
$retentionCutoff = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));

// 1. Get files to delete
$sql_cleanup = "SELECT id, image_path FROM screenshots WHERE taken_at < ?";
$cleanupParams = [$retentionCutoff];
$scope = tenant_get_scope($pdo, 'screenshots', '', 'AND', 'organization_id', $organization_id);
$sql_cleanup .= $scope['sql'];
$cleanupParams = array_merge($cleanupParams, $scope['params']);
$stmt_cleanup = $pdo->prepare($sql_cleanup);
$stmt_cleanup->execute($cleanupParams);
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
    $scope = tenant_get_scope($pdo, 'screenshots', '', 'AND', 'organization_id', $organization_id);
    $sql_del_cleanup .= $scope['sql'];
    $stmt_del_cleanup = $pdo->prepare($sql_del_cleanup);
    $stmt_del_cleanup->execute(array_merge([$retentionCutoff], $scope['params']));
    
    // Log cleanup
    $count = count($old_records);
    $logEntry .= "Cleanup: Deleted $count old screenshots (> {$retentionDays} days)\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

echo json_encode(['status' => 'success']);


