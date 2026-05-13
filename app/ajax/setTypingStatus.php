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

$userId = (int)$_SESSION['id'];
$isTyping = !empty($_POST['is_typing']);
session_write_close();

include "../../DB_connection.php";
include "../model/Typing.php";
include "../model/Group.php";
include "../model/user.php";

if (isset($_POST['group_id'])) {
    $groupId = (int)$_POST['group_id'];
    if ($groupId <= 0 || !is_user_in_group($pdo, $groupId, $userId)) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }

    if ($isTyping) {
        typing_status_upsert_group($pdo, $userId, $groupId);
    } else {
        typing_status_clear_group($pdo, $userId, $groupId);
    }

    echo json_encode(['ok' => true]);
    exit;
}

if (isset($_POST['user_id'])) {
    $otherUserId = (int)$_POST['user_id'];
    if (
        $otherUserId <= 0
        || $otherUserId === $userId
        || !user_is_workspace_member($pdo, $userId)
        || !user_is_workspace_member($pdo, $otherUserId)
    ) {
        http_response_code($otherUserId <= 0 ? 400 : 403);
        echo json_encode(['ok' => false]);
        exit;
    }

    if ($isTyping) {
        typing_status_upsert_direct($pdo, $userId, $otherUserId);
    } else {
        typing_status_clear_direct($pdo, $userId, $otherUserId);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);
