<?php 

session_start();
require_once "../../inc/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (isset($_SESSION['id'])) {

	if (!csrf_verify('chat_ajax_actions', $_POST['csrf_token'] ?? null, false)) {
		http_response_code(403);
		exit;
	}

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
    $lastSeenAnchorId = find_last_seen_direct_chat_anchor_id($chats, $id_1, $id_2);
    $otherUser = get_user_by_id($pdo, $id_2);
    $seenAvatarHtml = '';
    if (!empty($otherUser) && $lastSeenAnchorId > 0) {
        $seenName = trim((string)($otherUser['full_name'] ?? 'User'));
        if ($seenName === '') {
            $seenName = 'User';
        }
        $seenProfileUrl = user_profile_image_url($otherUser['profile_image'] ?? '');
        $seenInitials = user_display_initials($seenName);
        ob_start();
        ?>
        <span class="direct-message-seen-avatar" title="<?= htmlspecialchars($seenName, ENT_QUOTES, 'UTF-8') ?>" aria-label="Seen by <?= htmlspecialchars($seenName, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($seenProfileUrl !== '') { ?>
                <img src="<?= htmlspecialchars($seenProfileUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($seenName, ENT_QUOTES, 'UTF-8') ?>">
            <?php } else { ?>
                <span class="direct-message-seen-avatar-fallback"><?= htmlspecialchars($seenInitials, ENT_QUOTES, 'UTF-8') ?></span>
            <?php } ?>
        </span>
        <?php
        $seenAvatarHtml = trim(ob_get_clean());
    }
    $lastDateKey = null;
    foreach ($chats as $chat) {
        $currentDateKey = chat_message_day_key($chat['created_at']);
        if ($currentDateKey !== '' && $currentDateKey !== $lastDateKey) {
            echo render_chat_date_separator($chat['created_at']);
            $lastDateKey = $currentDateKey;
        }

        $attachments = getAttachments($chat['chat_id'], $pdo);
        if ($chat['sender_id'] == $id_1) { // My message (Outgoing)
            $deletePreview = trim(strip_tags((string)($chat['message'] ?? '')));
            $deletePreview = preg_replace('/\s+/', ' ', $deletePreview ?? '');
            if ($deletePreview === '' && !empty($attachments)) {
                $deletePreview = count($attachments) > 1 ? 'Attachments' : 'Attachment';
            }
    ?>
        <div class="message-outgoing" data-delete-message-type="user" data-delete-message-id="<?= (int)$chat['chat_id'] ?>">
             <button
                type="button"
                class="message-delete-btn"
                aria-label="Delete message"
                title="Delete message"
                data-delete-message-type="user"
                data-delete-message-id="<?= (int)$chat['chat_id'] ?>"
                data-delete-message-preview="<?= htmlspecialchars($deletePreview, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa fa-trash-o"></i>
             </button>
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
             <div class="message-time">
                <span><?=format_chat_message_time($chat['created_at'])?></span>
                <?=render_chat_read_receipt($chat['opened'] ?? false)?>
             </div>
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
                 <div class="message-time"><?=format_chat_message_time($chat['created_at'])?></div>
             </div>
        </div>
    <?php } ?>
        <?php if ($seenAvatarHtml !== '' && (int)$chat['chat_id'] === (int)$lastSeenAnchorId) { ?>
            <div class="direct-message-seen-anchor">
                <?=$seenAvatarHtml?>
            </div>
        <?php } ?>
    <?php } }
	}
}
