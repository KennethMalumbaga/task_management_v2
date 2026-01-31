<?php 

session_start();

if (isset($_SESSION['id'])) {

	if (isset($_POST['id_2'])) {
	
	include "../../DB_connection.php";
    include "../Model/Message.php";
    include "../Model/User.php";

	$id_1 = $_SESSION['id'];
	$id_2 = $_POST['id_2'];
	$opend = 0;

	$chats = getChats($id_1, $id_2, $pdo);    

    if (!empty($chats)) {
    foreach ($chats as $chat) {
        if ($chat['sender_id'] == $id_1) { // My message
    ?>
        <div style="align-self: flex-end; max-width: 70%; background: var(--primary); color: white; padding: 10px 15px; border-radius: 12px 12px 0 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 5px;">
             <div style="font-size: 14px;"><?=$chat['message']?></div>
             <div style="font-size: 10px; opacity: 0.8; margin-top: 5px; text-align: right;"><?=$chat['created_at']?></div>
        </div>
    <?php } else { // Received message ?>
        <div style="align-self: flex-start; max-width: 70%; background: white; padding: 10px 15px; border-radius: 0 12px 12px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 5px;">
            <div style="font-size: 14px;"><?=$chat['message']?></div>
            <div style="font-size: 10px; color: var(--text-gray); margin-top: 5px;"><?=$chat['created_at']?></div>
        </div>
    <?php } } }
	}
}
