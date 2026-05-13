<?php

session_start();
header('Content-Type: application/json');
require_once "../../inc/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

if (!csrf_verify('chat_ajax_actions', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

include "../../DB_connection.php";
include "../model/user.php";
include "../model/Message.php";
include "../model/GroupMessage.php";
include "../model/Group.php";
include "../model/ChatVisibility.php";

$currentUserId = (int)$_SESSION['id'];
$chatType = trim((string)($_POST['chat_type'] ?? ''));

if ($chatType === 'group') {
    $groupId = (int)($_POST['group_id'] ?? 0);
    if ($groupId <= 0 || !is_user_in_group($pdo, $groupId, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }

    mark_group_as_read($pdo, $groupId, $currentUserId);
    chat_visibility_hide_group_thread($pdo, $currentUserId, $groupId);
    echo json_encode(['ok' => true]);
    exit;
}

if ($chatType === 'user') {
    $otherUserId = (int)($_POST['user_id'] ?? 0);
    if (
        $otherUserId <= 0
        || $otherUserId === $currentUserId
        || !user_is_workspace_member($pdo, $currentUserId)
        || !user_is_workspace_member($pdo, $otherUserId)
    ) {
        http_response_code($otherUserId <= 0 ? 400 : 403);
        echo json_encode(['ok' => false]);
        exit;
    }

    mark_chat_conversation_as_read($currentUserId, $otherUserId, $pdo);
    chat_visibility_hide_user_thread($pdo, $currentUserId, $otherUserId);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);
