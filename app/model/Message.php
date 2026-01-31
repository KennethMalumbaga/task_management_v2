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

function lastChat($id_1, $id_2, $conn){
    $sql = "SELECT * FROM chats
            WHERE (sender_id = ? AND receiver_id = ?)
            OR (receiver_id = ? AND sender_id = ?)
            ORDER BY chat_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_1, $id_2, $id_1, $id_2]);

    if($stmt->rowCount() > 0){
        $chat = $stmt->fetch();
        return $chat;
    }else{
        $chat = [];
        return $chat;
    }
}

function countUnreadChat($sender_id, $receiver_id, $conn){
    $sql = "SELECT * FROM chats
            WHERE sender_id = ? AND receiver_id = ? AND opened = false";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sender_id, $receiver_id]);

    if($stmt->rowCount() > 0){
        $chats = $stmt->fetchAll();
        return count($chats);
    }else{
        return 0; 
    }
}

function countAllUnread($receiver_id, $conn){
    $sql = "SELECT * FROM chats
            WHERE receiver_id = ? AND opened = false";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$receiver_id]);

    if($stmt->rowCount() > 0){
        $chats = $stmt->fetchAll();
        return count($chats);
    }else{
        return 0;
    }
}

function opend($id_1, $conn, $chats){
    foreach ($chats as $chat) {
        if ($chat['opened'] == false && $chat['receiver_id'] == $id_1) {
            $opened = true;
            $chat_id = $chat['chat_id'];

            $sql = "UPDATE chats
                    SET opened = ?
                    WHERE chat_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$opened ? 'true' : 'false', $chat_id]);
        }
    }
}

function formatChatTime($timestamp){
    $time = strtotime($timestamp);
    $currentDate = date('Y-m-d');
    $msgDate = date('Y-m-d', $time);

    if($currentDate == $msgDate){
        // Recent (Today) -> 10:30 pm
        return date('g:i a', $time);
    }else{
        // Older -> January 31, 2026
        return date('F j, Y', $time);
    }
}
