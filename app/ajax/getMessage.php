<?php 

session_start();

if (isset($_SESSION['id'])) {

	if (isset($_POST['id_2'])) {
	
	include "../../DB_connection.php";
    include "../model/Message.php";
    include "../model/user.php";

	$id_1 = $_SESSION['id'];
	$id_2 = $_POST['id_2'];
	$opend = 0;

	$chats = getChats($id_1, $id_2, $pdo); 
    
    // Mark as read
    opend($id_1, $pdo, $chats);   
    
    if (!empty($chats)) {
    foreach ($chats as $chat) {
        $attachments = getAttachments($chat['chat_id'], $pdo);
        if ($chat['sender_id'] == $id_1) { // My message (Outgoing)
    ?>
        <div class="message-outgoing">
             <div class="message-bubble-outgoing">
                <?=$chat['message']?>
                <?php 
                if (!empty($attachments)) { 
                    foreach($attachments as $attachment) {
                        $fileParts = explode('.', $attachment);
                        $ext = strtolower(end($fileParts));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                ?>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2);">
                        <?php if ($isImage) { ?>
                            <a href="uploads/<?=$attachment?>" target="_blank">
                                <img src="uploads/<?=$attachment?>" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                            </a>
                        <?php } else { ?>
                            <a href="uploads/<?=$attachment?>" target="_blank" style="color: white; text-decoration: underline; display: flex; align-items: center; gap: 5px;">
                                <i class="fa fa-paperclip"></i> <?=$attachment?>
                            </a>
                        <?php } ?>
                    </div>
                <?php 
                    }
                } 
                ?>
             </div>
             <div class="message-time"><?=formatChatTime($chat['created_at'])?></div>
        </div>
    <?php } else { // Received message (Incoming) ?>
        <div class="message-incoming">
             <div class="message-structure">
                 <div class="message-bubble-incoming">
                    <?=$chat['message']?>
                    <?php 
                    if (!empty($attachments)) { 
                        foreach($attachments as $attachment) {
                            $fileParts = explode('.', $attachment);
                            $ext = strtolower(end($fileParts));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                    ?>
                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1);">
                            <?php if ($isImage) { ?>
                                <a href="uploads/<?=$attachment?>" target="_blank">
                                    <img src="uploads/<?=$attachment?>" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                                </a>
                            <?php } else { ?>
                                <a href="uploads/<?=$attachment?>" target="_blank" style="color: #4B5563; text-decoration: underline; display: flex; align-items: center; gap: 5px;">
                                    <i class="fa fa-paperclip"></i> <?=$attachment?>
                                </a>
                            <?php } ?>
                        </div>
                    <?php 
                        }
                    } 
                    ?>
                 </div>
                 <div class="message-time"><?=formatChatTime($chat['created_at'])?></div>
             </div>
        </div>
    <?php } } }
	}
}
