<?php

function insert_group_message($pdo, $group_id, $sender_id, $message){
    $stmt = $pdo->prepare("INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$group_id, $sender_id, $message]);
    return $pdo->lastInsertId();
}

function get_group_messages($pdo, $group_id){
    $sql = "SELECT gm.*, u.full_name, u.profile_image, u.role AS user_role
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = ?
            ORDER BY gm.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$group_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_last_group_message($pdo, $group_id){
    $sql = "SELECT gm.*, u.full_name
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = ?
            ORDER BY gm.id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$group_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_group_unread_count($pdo, $group_id, $user_id){
    // Get last read message id
    $stmt = $pdo->prepare("SELECT last_message_id FROM group_message_reads WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    $last_read_id = $stmt->fetchColumn() ?: 0;

    // Count messages after that id
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_messages WHERE group_id = ? AND id > ?");
    $stmt->execute([$group_id, $last_read_id]);
    return $stmt->fetchColumn();
}

function count_all_group_unread($pdo, $user_id){
    // Get all groups for user
    $sql = "SELECT g.id 
            FROM groups g
            JOIN group_members gm ON gm.group_id = g.id
            WHERE gm.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalUnread = 0;
    foreach($groups as $group_id){
        $totalUnread += get_group_unread_count($pdo, $group_id, $user_id);
    }
    return $totalUnread;
}

function mark_group_as_read($pdo, $group_id, $user_id){
    // Get last message id in group
    $stmt = $pdo->prepare("SELECT MAX(id) FROM group_messages WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $last_msg_id = $stmt->fetchColumn();

    if ($last_msg_id) {
        // Upsert logic for stats
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM group_message_reads WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $update = $pdo->prepare("UPDATE group_message_reads SET last_message_id = ? WHERE group_id = ? AND user_id = ?");
            $update->execute([$last_msg_id, $group_id, $user_id]);
        } else {
            $insert = $pdo->prepare("INSERT INTO group_message_reads (group_id, user_id, last_message_id) VALUES (?, ?, ?)");
            $insert->execute([$group_id, $user_id, $last_msg_id]);
        }
    }
}

function insert_group_attachment($pdo, $message_id, $attachment_name){
    if (!table_exists($pdo, 'group_message_attachments')) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO group_message_attachments (message_id, attachment_name) VALUES (?, ?)");
    $stmt->execute([$message_id, $attachment_name]);
}

function get_group_attachments($pdo, $message_id){
    if (!table_exists($pdo, 'group_message_attachments')) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT attachment_name FROM group_message_attachments WHERE message_id = ?");
    $stmt->execute([$message_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

if (!function_exists('table_exists')) {
    function table_exists($pdo, $table_name){
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = ?");
            $stmt->execute([$table_name]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}
