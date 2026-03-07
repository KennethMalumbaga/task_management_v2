<?php
session_start();
date_default_timezone_set('Asia/Manila');

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
$role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
$organization_id = isset($_SESSION['organization_id']) ? (int)$_SESSION['organization_id'] : null;

// Destroy session early to release the session file lock before DB work.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? false)
    );
}
session_destroy();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if ($user_id > 0 && $role === 'employee') {
    try {
        require 'DB_connection.php';
        require_once 'inc/tenant.php';
        require_once 'inc/attendance_pause.php';

        $now = date('H:i:s');

        // Auto clock-out on logout if there is an active attendance record.
        $sql = "SELECT id, att_date, time_in, time_out FROM attendance
                WHERE user_id = ?
                  AND time_in IS NOT NULL
                  AND (time_out IS NULL OR time_out = '00:00:00')";
        $params = [$user_id];
        $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organization_id);
        $sql .= $scope['sql'] . "
                ORDER BY id DESC
                LIMIT 1";
        $params = array_merge($params, $scope['params']);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $att = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($att) {
            $hours = round(
                attendance_pause_calculate_effective_seconds(
                    $pdo,
                    $att,
                    $organization_id,
                    date('Y-m-d') . ' ' . $now
                ) / 3600,
                2
            );
            if ($hours < 0) {
                $hours = 0;
            }

            attendance_pause_close_active($pdo, (int)$att['id'], $organization_id, date('Y-m-d') . ' ' . $now);

            $update = "UPDATE attendance SET time_out = ?, total_hours = ? WHERE id = ?";
            $updateParams = [$now, $hours, $att['id']];
            $scope = tenant_get_scope($pdo, 'attendance', '', 'AND', 'organization_id', $organization_id);
            $update .= $scope['sql'];
            $updateParams = array_merge($updateParams, $scope['params']);
            $pdo->prepare($update)->execute($updateParams);
        }
    } catch (Throwable $e) {
        // Logout should proceed even if attendance update fails.
    }
}

header("Location: login.php");
exit();
