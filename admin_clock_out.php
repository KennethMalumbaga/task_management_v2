<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require 'DB_connection.php';
require_once 'inc/tenant.php';
require_once 'inc/csrf.php';
require_once 'inc/attendance_pause.php';
require_once 'app/model/Notification.php';

if (!function_exists('admin_clock_out_remark_schema_ready')) {
    function admin_clock_out_remark_schema_ready(PDO $pdo): bool
    {
        static $schemaReady = null;

        if ($schemaReady !== null) {
            return $schemaReady;
        }

        $schemaReady = tenant_column_exists($pdo, 'attendance', 'admin_clock_out_remark');
        if ($schemaReady) {
            return true;
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE attendance ADD COLUMN admin_clock_out_remark VARCHAR(255) NULL AFTER time_out");
            } elseif ($driver === 'pgsql') {
                $pdo->exec("ALTER TABLE attendance ADD COLUMN admin_clock_out_remark VARCHAR(255) NULL");
            }
        } catch (Throwable $e) {
            // Best effort only. If DDL is blocked, the clock-out can still complete.
        }

        $schemaReady = tenant_column_exists($pdo, 'attendance', 'admin_clock_out_remark');
        return $schemaReady;
    }
}

// Only allow admins
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!csrf_verify('admin_clock_out_action', $_POST['csrf_token'] ?? null, false)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$remark = trim((string)($_POST['remark'] ?? ''));
$remark = preg_replace('/\s+/', ' ', $remark) ?: $remark;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit;
}

if ($remark === '') {
    echo json_encode(['status' => 'error', 'message' => 'Remark is required']);
    exit;
}

$remarkLength = function_exists('mb_strlen') ? mb_strlen($remark) : strlen($remark);
if ($remarkLength > 255) {
    echo json_encode(['status' => 'error', 'message' => 'Remark must be 255 characters or less']);
    exit;
}

$today = date('Y-m-d');
$now = date('H:i:s');

// Find active attendance session for this user today
$sql = "SELECT * FROM attendance 
        WHERE user_id = ? 
        AND att_date = ? 
        AND time_in IS NOT NULL 
        AND (time_out IS NULL OR time_out = '00:00:00')";
$params = [$user_id, $today];
$scope = tenant_get_scope($pdo, 'attendance');
$sql .= $scope['sql'] . "
        LIMIT 1";
$params = array_merge($params, $scope['params']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode(['status' => 'error', 'message' => 'No active session for this user']);
    exit;
}

// Calculate total hours, excluding paused time.
$effectiveSeconds = attendance_pause_calculate_effective_seconds(
    $pdo,
    $att,
    tenant_get_current_org_id(),
    $today . ' ' . $now
);
$hours = round($effectiveSeconds / 3600, 2);
if ($hours < 0) {
    $hours = 0;
}

attendance_pause_close_active($pdo, (int)$att['id'], tenant_get_current_org_id(), $today . ' ' . $now);

// Update attendance record
$setParts = [
    'time_out = ?',
    'total_hours = ?',
];
$params = [$now, $hours];

if (admin_clock_out_remark_schema_ready($pdo)) {
    $setParts[] = 'admin_clock_out_remark = ?';
    $params[] = $remark;
}

$sql = "UPDATE attendance SET " . implode(', ', $setParts) . " WHERE id = ?";
$params[] = $att['id'];
$scope = tenant_get_scope($pdo, 'attendance');
$sql .= $scope['sql'];
$params = array_merge($params, $scope['params']);
$pdo->prepare($sql)->execute($params);

// Notify the user (do not block clock out if notification fails)
try {
    $adminName = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin'));
    if ($adminName === '') {
        $adminName = 'Admin';
    }
    $timeLabel = date('h:i A', strtotime($now));
    $message = "You were clocked out by {$adminName} at {$timeLabel}. Remark: {$remark}";
    insert_notification($pdo, [$message, $user_id, 'Clock Out']);
} catch (Throwable $e) {
    // Ignore notification failures
}

echo json_encode([
    'status' => 'success',
    'message' => 'User clocked out successfully',
    'time_out' => date('h:i A', strtotime($now)),
    'total_hours' => $hours
]);
