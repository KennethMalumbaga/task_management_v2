<?php 
function getChats($sender_id, $receiver_id, $conn){
    $sql = "SELECT * FROM chats
            WHERE (sender_id = ? AND receiver_id = ?)
            OR (receiver_id = ? AND sender_id = ?)
            ORDER BY chat_id ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sender_id, $receiver_id, $sender_id, $receiver_id]);

    if($stmt->rowCount() > 0){
        $chats = $stmt->fetchAll();
        return $chats;
    }else{
        $chats = [];
        return $chats;
    }
}

function insertChat($sender_id, $receiver_id, $message, $conn){
    $sql = "INSERT INTO chats (sender_id, receiver_id, message)
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $res = $stmt->execute([$sender_id, $receiver_id, $message]);

    return $res;
}
