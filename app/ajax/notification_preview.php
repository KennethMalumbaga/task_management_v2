<?php
session_start();
require_once __DIR__ . '/../../inc/performance.php';
performance_monitor_request('dashboard.notification_preview');

header('Content-Type: application/json');

if (!isset($_SESSION['role'], $_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['id'];

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../../inc/csrf.php';
require_once __DIR__ . '/../helpers/notification.php';
require_once __DIR__ . '/../model/Notification.php';

$notificationReadCsrfToken = csrf_token('notification_read_action');
session_write_close();

$unread = 0;
try {
    $unread = (int)count_notification($pdo, $userId);
} catch (Throwable $e) {
    $unread = 0;
}

$notifRows = get_all_my_notifications($pdo, $userId, 8);
if (!is_array($notifRows)) {
    $notifRows = [];
}
$notifPreview = $notifRows;
$notificationNowTs = tm_notification_reference_now($pdo);

$html = '';
if (empty($notifPreview)) {
    $html = '<div class="dash-top-notif-empty">No notifications yet.</div>';
} else {
    foreach ($notifPreview as $notif) {
        $taskId = tm_get_notification_task_id($pdo, $notif);
        $notifLink = "app/notification-read.php?notification_id=" . urlencode((string)$notif['id']);
        if ($taskId) {
            $notifLink .= "&task_id=" . urlencode((string)$taskId);
        }
        $notifLink .= "&csrf_token=" . urlencode($notificationReadCsrfToken);

        $notifType = trim((string)($notif['type'] ?? 'Notification'));
        $notifMessage = trim((string)($notif['message'] ?? ''));
        $notifWhen = tm_notification_time_ago($notif, $notificationNowTs);
        $isUnread = tm_notification_is_unread($notif);

        $html .= '<a href="' . htmlspecialchars($notifLink, ENT_QUOTES) . '" class="dash-top-notif-item ' . ($isUnread ? 'unread' : '') . '">';
        $html .= '<div class="dash-top-notif-type">' . htmlspecialchars($notifType, ENT_QUOTES) . '</div>';
        $html .= '<div class="dash-top-notif-msg">' . htmlspecialchars($notifMessage, ENT_QUOTES) . '</div>';
        $html .= '<div class="dash-top-notif-meta">';
        $html .= '<span>' . htmlspecialchars($notifWhen, ENT_QUOTES) . '</span>';
        if ($isUnread) {
            $html .= '<span class="dash-top-notif-dot"></span>';
        }
        $html .= '</div>';
        $html .= '</a>';
    }
}

echo json_encode([
    'status' => 'success',
    'unread' => $unread,
    'html' => $html
]);
