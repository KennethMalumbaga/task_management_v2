<?php 
session_start();
require_once "../../inc/performance.php";
performance_monitor_request('messages.chat_lists');

if (isset($_SESSION['id'])) {
    $currentUserId = (int)$_SESSION['id'];
    session_write_close();

    include "../../DB_connection.php";
    include "../model/user.php";
    include "../model/Message.php";
    include "../model/Group.php";
    include "../model/GroupMessage.php";
    include "../model/ChatVisibility.php";
    include "../helpers/chat_sidebar.php";

    echo json_encode(chat_sidebar_build_payload($pdo, $currentUserId));
}
?>
