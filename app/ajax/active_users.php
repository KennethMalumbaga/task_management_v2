<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../../inc/tenant.php';

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

$sql = "SELECT a.user_id, a.time_in, u.full_name, u.profile_image
        FROM attendance a
        INNER JOIN users u ON a.user_id = u.id
        WHERE a.att_date = ?
          AND a.time_in IS NOT NULL
          AND (a.time_out IS NULL OR a.time_out = '00:00:00')
          AND u.role = 'employee'";
$params = [$today];

$scope_att = tenant_get_scope($pdo, 'attendance', 'a');
$sql .= $scope_att['sql'];
$params = array_merge($params, $scope_att['params']);

$scope_user = tenant_get_scope($pdo, 'users', 'u');
$sql .= $scope_user['sql'];
$params = array_merge($params, $scope_user['params']);

$sql .= " ORDER BY a.time_in DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

    $initials = '';
    $parts = preg_split('/\s+/', $name);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
    if ($initials === '') {
        $initials = 'U';
    }

    $avatarPath = '';
    if (!empty($u['profile_image'])) {
        $safe = basename($u['profile_image']);
        if ($safe !== '') {
            $candidate = __DIR__ . '/../../uploads/' . $safe;
            if (is_file($candidate)) {
                $avatarPath = 'uploads/' . $safe;
            }
        }
    }

    $html .= '<div class="admin-user-row" data-user-id="' . $userId . '">';
    $html .= '<div class="admin-user-rank">' . ($idx + 1) . '</div>';
    $html .= '<div class="admin-user-avatar">';
    if ($avatarPath !== '') {
        $html .= '<img src="' . htmlspecialchars($avatarPath, ENT_QUOTES) . '" alt="User">';
    } else {
        $html .= '<span class="admin-user-avatar-initials">' . htmlspecialchars($initials, ENT_QUOTES) . '</span>';
    }
    $html .= '<span class="admin-user-online"></span>';
    $html .= '</div>';
    $html .= '<div class="admin-user-info">';
    $html .= '<div class="admin-user-name">' . htmlspecialchars($name, ENT_QUOTES) . '</div>';
    $html .= '<div class="admin-user-meta">Clocked in at ' . htmlspecialchars($timeInLabel, ENT_QUOTES) . '</div>';
    $html .= '</div>';
    $html .= '<div class="admin-user-actions">';
    $html .= '<button type="button" class="admin-btn admin-btn-clockout admin-clockout-btn" data-user-id="' . $userId . '" data-user-name="' . htmlspecialchars($name, ENT_QUOTES) . '">';
    $html .= '<i class="fa fa-sign-out"></i> Clock Out</button>';
    $html .= '<a class="admin-btn admin-btn-capture" href="screenshots.php?open_user_id=' . $userId . '&user_id=' . $userId . '">';
    $html .= 'View Captures <i class="fa fa-arrow-right"></i></a>';
    $html .= '</div>';
    $html .= '</div>';
}

echo json_encode([
    'status' => 'success',
    'count' => $count,
    'html' => $html
]);
