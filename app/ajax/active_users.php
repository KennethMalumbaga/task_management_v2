<?php
session_start();
require_once __DIR__ . '/../../inc/performance.php';
performance_monitor_request('dashboard.active_users');

header('Content-Type: application/json');

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

session_write_close();

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../model/user.php';

$rows = get_active_users_with_pause_state($pdo);

$html = '';
$count = count($rows);

if ($count === 0) {
    $html = '<div class="admin-empty-state"><i class="fa fa-user-o"></i><span>No active users right now.</span></div>';
    echo json_encode(['status' => 'success', 'count' => 0, 'html' => $html]);
    exit;
}

foreach ($rows as $idx => $u) {
    $userId = (int)($u['user_id'] ?? 0);
    $name = trim((string)($u['full_name'] ?? ''));
    if ($name === '') {
        $name = 'User';
    }
    $timeInRaw = trim((string)($u['time_in'] ?? ''));
    $timeInLabel = $timeInRaw !== '' ? date('h:i A', strtotime($timeInRaw)) : '--:--';
    $isPaused = !empty($u['is_paused']);
    $pauseReason = trim((string)($u['pause_reason'] ?? ''));
    $pauseLabel = $pauseReason !== '' ? $pauseReason : 'Paused';
    $initials = user_display_initials($name);

    $avatarPath = user_profile_image_url($u['profile_image'] ?? '');

    $html .= '<div class="admin-user-row' . ($isPaused ? ' is-paused' : '') . '" data-user-id="' . $userId . '" data-user-name="' . htmlspecialchars($name, ENT_QUOTES) . '">';
    $html .= '<div class="admin-user-rank">' . ($idx + 1) . '</div>';
    $html .= '<div class="admin-user-avatar">';
    if ($avatarPath !== '') {
        $html .= '<img src="' . htmlspecialchars($avatarPath, ENT_QUOTES) . '" alt="User">';
    } else {
        $html .= '<span class="admin-user-avatar-initials">' . htmlspecialchars($initials, ENT_QUOTES) . '</span>';
    }
    $html .= '<span class="admin-user-online' . ($isPaused ? ' is-paused' : '') . '"></span>';
    $html .= '</div>';
    $html .= '<div class="admin-user-info">';
    $html .= '<div class="admin-user-name">' . htmlspecialchars($name, ENT_QUOTES) . '</div>';
    $html .= '<div class="admin-user-meta">Clocked in at ' . htmlspecialchars($timeInLabel, ENT_QUOTES) . '</div>';
    $html .= '</div>';
    $html .= '<div class="admin-user-actions">';
    if ($isPaused) {
        $html .= '<div class="admin-user-note is-paused" title="' . htmlspecialchars($pauseLabel, ENT_QUOTES) . '">';
        $html .= '<i class="fa fa-pause"></i>';
        $html .= '<span>' . htmlspecialchars($pauseLabel, ENT_QUOTES) . '</span>';
        $html .= '</div>';
    }
    $html .= '<div class="admin-user-action-buttons">';
    $html .= '<button type="button" class="admin-btn admin-btn-clockout admin-clockout-btn" data-user-id="' . $userId . '" data-user-name="' . htmlspecialchars($name, ENT_QUOTES) . '">';
    $html .= '<i class="fa fa-sign-out"></i> Clock Out</button>';
    $html .= '<a class="admin-btn admin-btn-capture" href="screenshots.php?open_user_id=' . $userId . '&user_id=' . $userId . '">';
    $html .= 'View Captures <i class="fa fa-arrow-right"></i></a>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
}

echo json_encode([
    'status' => 'success',
    'count' => $count,
    'html' => $html
]);
