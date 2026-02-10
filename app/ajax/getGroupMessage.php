<?php

session_start();

if (isset($_SESSION['id'])) {
    if (isset($_POST['group_id'])) {
        include "../../DB_connection.php";
        include "../model/GroupMessage.php";
        include "../model/Message.php";
        include "../model/Group.php";

        $user_id = $_SESSION['id'];
        $group_id = (int)$_POST['group_id'];

        if (!is_user_in_group($pdo, $group_id, $user_id)) {
            exit();
        }

        // Mark as read
        mark_group_as_read($pdo, $group_id, $user_id);

        $messages = get_group_messages($pdo, $group_id);

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                $attachments = get_group_attachments($pdo, $msg['id']);
                $isMine = ((int)$msg['sender_id'] === (int)$user_id);
                
                // Prepare Avatar
                $avatarHtml = '';
                if (!$isMine) {
                    if (!empty($msg['profile_image']) && $msg['profile_image'] != 'default.png' && file_exists('../../uploads/' . $msg['profile_image'])) {
                        $avatarHtml = '<div class="message-avatar"><img src="uploads/'.$msg['profile_image'].'" alt="Profile"></div>';
                    } else {
                        $avatarHtml = '<div class="message-avatar" style="display:flex; align-items:center; justify-content:center; font-weight:700; color:#6366F1;">' . strtoupper(substr($msg['full_name'], 0, 1)) . '</div>';
                    }
                }
?>
        <?php if ($isMine) { ?>
            <div class="message-outgoing">
                 <div class="message-bubble-outgoing">
                    <?=$msg['message']?>
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
                 <div class="message-time"><?=formatChatTime($msg['created_at'])?></div>
            </div>
        <?php } else { ?>
            <div class="message-incoming">
                 <?=$avatarHtml?>
                 <div class="message-structure">
                     <span class="message-user-name">
                        <?=htmlspecialchars($msg['full_name'])?>
                     </span>
                     <div class="message-bubble-incoming">
                        <?=$msg['message']?>
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
                     <div class="message-time"><?=formatChatTime($msg['created_at'])?></div>
                 </div>
            </div>
        <?php } ?>
<?php
            }
        }
    }
}
