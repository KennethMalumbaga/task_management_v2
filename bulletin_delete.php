<?php
session_start();
header('Content-Type: application/json');

require 'DB_connection.php';
require_once 'inc/csrf.php';
require_once 'app/model/Bulletin.php';

if (!isset($_SESSION['id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!csrf_verify('bulletin_delete_action', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request']);
    exit;
}

$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if ($postId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid post ID']);
    exit;
}

try {
    $deleted = delete_bulletin_post($pdo, $postId);
    if (!$deleted) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Post not found or already deleted.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Bulletin post deleted.',
        'post_id' => $postId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to delete bulletin post.']);
}

