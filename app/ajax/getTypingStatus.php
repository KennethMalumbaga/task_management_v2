<?php

session_start();
header('Content-Type: application/json');
require_once "../../inc/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'typing' => false]);
    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'typing' => false]);
    exit;
}

if (!csrf_verify('chat_ajax_actions', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'typing' => false]);
    exit;
}

$userId = (int)$_SESSION['id'];
session_write_close();

include "../../DB_connection.php";
include "../model/Typing.php";
include "../model/Group.php";
include "../model/user.php";

if (!function_exists('typing_avatar_payload_from_user')) {
    function typing_avatar_payload_from_user($user)
    {
        if (empty($user) || !is_array($user)) {
            return null;
        }

        $name = trim((string)($user['full_name'] ?? ''));
        if ($name === '') {
            $name = 'User';
        }

        return [
            'name' => $name,
            'image_url' => user_profile_image_url($user['profile_image'] ?? ''),
            'initials' => user_display_initials($name),
        ];
    }
}

if (isset($_POST['group_id'])) {
    $groupId = (int)$_POST['group_id'];
    if ($groupId <= 0 || !is_user_in_group($pdo, $groupId, $userId)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'typing' => false]);
        exit;
    }

    $typingUsers = typing_status_get_group_users($pdo, $groupId, $userId);
    $count = count($typingUsers);
    $label = '';
    $avatars = [];

    if ($count === 1) {
        $name = trim((string)($typingUsers[0]['full_name'] ?? ''));
        $label = $name !== '' ? ($name . ' is typing') : 'Someone is typing';
    } elseif ($count === 2) {
        $first = trim((string)($typingUsers[0]['full_name'] ?? ''));
        $second = trim((string)($typingUsers[1]['full_name'] ?? ''));
        if ($first !== '' && $second !== '') {
            $label = $first . ' and ' . $second . ' are typing';
        } else {
            $label = '2 people are typing';
        }
    } elseif ($count > 2) {
        $label = $count . ' people are typing';
    }

    foreach (array_slice($typingUsers, 0, 2) as $typingUser) {
        $avatar = typing_avatar_payload_from_user($typingUser);
        if ($avatar !== null) {
            $avatars[] = $avatar;
        }
    }

    echo json_encode([
        'ok' => true,
        'typing' => $count > 0,
        'label' => $label,
        'avatars' => $avatars,
    ]);
    exit;
}

if (isset($_POST['user_id'])) {
    $otherUserId = (int)$_POST['user_id'];
    if ($otherUserId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'typing' => false]);
        exit;
    }

    $isTyping = typing_status_get_direct($pdo, $userId, $otherUserId);
    $otherUser = $isTyping ? get_user_by_id($pdo, $otherUserId) : 0;
    $otherName = is_array($otherUser) ? trim((string)($otherUser['full_name'] ?? '')) : '';
    if ($otherName === '') {
        $otherName = 'User';
    }

    echo json_encode([
        'ok' => true,
        'typing' => $isTyping,
        'label' => $isTyping ? ($otherName . ' is typing') : '',
        'avatars' => $isTyping ? array_values(array_filter([typing_avatar_payload_from_user($otherUser)])) : [],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'typing' => false]);
