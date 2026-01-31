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
    
    // Mark as read
    opend($id_1, $pdo, $chats);   
    
    if (!empty($chats)) {
    foreach ($chats as $chat) {
        if ($chat['sender_id'] == $id_1) { // My message (Outgoing)
    ?>
        <div class="message-outgoing">
             <div class="message-bubble-outgoing">
                <?=$chat['message']?>
             </div>
             <div class="message-time"><?=formatChatTime($chat['created_at'])?></div>
        </div>
    <?php } else { // Received message (Incoming) ?>
        <div class="message-incoming">
             <div class="message-bubble-incoming">
                <?=$chat['message']?>
             </div>
             <div class="message-time"><?=formatChatTime($chat['created_at'])?></div>
        </div>
    <?php } } }
	}
}
