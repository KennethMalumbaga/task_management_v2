<?php
session_start();
require_once "../../inc/csrf.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'members' => []]);
    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'members' => []]);
    exit;
}

if (!csrf_verify('chat_ajax_actions', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'members' => []]);
    exit;
}

$groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
if ($groupId <= 0) {
    echo json_encode(['ok' => false, 'members' => []]);
    exit;
}

$userId = (int)$_SESSION['id'];
session_write_close();

include "../../DB_connection.php";
include "../model/Group.php";

if (!is_user_in_group($pdo, $groupId, $userId)) {
    echo json_encode(['ok' => false, 'members' => []]);
    exit;
}

$members = get_group_members($pdo, $groupId);
$result = [];
foreach ($members as $member) {
    if ((int)($member['user_id'] ?? 0) === $userId) {
        continue;
    }

    $name = trim((string)($member['full_name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $profileImage = trim((string)($member['profile_image'] ?? ''));
    $role = trim((string)($member['user_role'] ?? 'member'));
    if ($role === '') {
        $role = 'member';
    }
    $result[] = [
        'id' => (int)$member['user_id'],
        'full_name' => $name,
        'profile_image' => $profileImage,
        'user_role' => $role
    ];
}

echo json_encode(['ok' => true, 'members' => $result]);
exit;
