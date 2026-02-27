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

if (!csrf_verify('bulletin_post_action', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired request']);
    exit;
}

$type = $_POST['type'] ?? 'ann';
$title = $_POST['title'] ?? '';
$body = $_POST['body'] ?? '';

if (trim((string)$title) === '' || trim((string)$body) === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Title and message are required.']);
    exit;
}

try {
    $post = create_bulletin_post($pdo, $type, $title, $body, (int)$_SESSION['id']);
    if (!$post) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to save bulletin post.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Post published successfully.',
        'post' => $post,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to save bulletin post.']);
}

