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
include "../model/Message.php";
include "../model/GroupMessage.php";

$currentUserId = (int)$_SESSION['id'];
$messageType = trim((string)($_POST['message_type'] ?? ''));
$messageId = (int)($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if ($messageType === 'group') {
    $deleted = delete_group_message_for_sender($pdo, $messageId, $currentUserId);
    echo json_encode(['ok' => $deleted]);
    exit;
}

if ($messageType === 'user') {
    $deleted = delete_chat_message_for_sender($messageId, $currentUserId, $pdo);
    echo json_encode(['ok' => $deleted]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);
