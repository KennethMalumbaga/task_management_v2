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
