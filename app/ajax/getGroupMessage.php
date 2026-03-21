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
        $groupMembers = get_group_members($pdo, $group_id);
        $mentionNames = build_group_member_mention_names($groupMembers);

        $messages = get_group_messages($pdo, $group_id);
        $readStates = get_group_message_read_states($pdo, $group_id, $user_id);
        $seenReceiptMap = build_group_seen_receipt_map($messages, $readStates, $user_id);

        if (!empty($messages)) {
            $lastDateKey = null;
            foreach ($messages as $msg) {
                $currentDateKey = chat_message_day_key($msg['created_at']);
                if ($currentDateKey !== '' && $currentDateKey !== $lastDateKey) {
                    echo render_chat_date_separator($msg['created_at']);
                    $lastDateKey = $currentDateKey;
                }

                $attachments = get_group_attachments($pdo, $msg['id']);
                $isMine = ((int)$msg['sender_id'] === (int)$user_id);
                $formattedMessage = format_group_message_mentions($msg['message'], $mentionNames);
                $seenReaders = $seenReceiptMap[(int)$msg['id']] ?? [];
                $deletePreview = trim((string)($msg['message'] ?? ''));
                $deletePreview = preg_replace('/\s+/', ' ', $deletePreview ?? '');
                if ($deletePreview === '' && !empty($attachments)) {
                    $deletePreview = count($attachments) > 1 ? 'Attachments' : 'Attachment';
                }
                
                // Prepare Avatar
                $avatarHtml = '';
                if (!$isMine) {
                    if (!empty($msg['profile_image']) && $msg['profile_image'] != 'default.png' && file_exists('../../uploads/' . $msg['profile_image'])) {
                        $avatarHtml = '<div class="message-avatar"><img src="uploads/'.$msg['profile_image'].'" alt="Profile"></div>';
                    } else {
                        $avatarHtml = '<div class="message-avatar" style="display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--primary-dark);">' . strtoupper(substr($msg['full_name'], 0, 1)) . '</div>';
                    }
                }
?>
        <div class="group-chat-message-block <?= $isMine ? 'is-outgoing' : 'is-incoming' ?>">
        <?php if ($isMine) { ?>
            <div class="message-outgoing" data-delete-message-type="group" data-delete-message-id="<?= (int)$msg['id'] ?>">
                 <button
                    type="button"
                    class="message-delete-btn"
                    aria-label="Delete message"
                    title="Delete message"
                    data-delete-message-type="group"
                    data-delete-message-id="<?= (int)$msg['id'] ?>"
                    data-delete-message-preview="<?= htmlspecialchars($deletePreview, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa fa-trash-o"></i>
                 </button>
                 <div class="message-bubble-outgoing">
                    <?=$formattedMessage?>
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
                 <div class="message-time"><?=format_chat_message_time($msg['created_at'])?></div>
            </div>
        <?php } else { ?>
            <div class="message-incoming">
                 <?=$avatarHtml?>
                 <div class="message-structure">
                     <span class="message-user-name">
                        <?=htmlspecialchars($msg['full_name'])?>
                     </span>
                     <div class="message-bubble-incoming">
                        <?=$formattedMessage?>
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
                     <div class="message-time"><?=format_chat_message_time($msg['created_at'])?></div>
                 </div>
            </div>
        <?php } ?>
        <?php if (!empty($seenReaders)) { ?>
            <div class="group-message-seen-anchor">
                <?=render_group_seen_receipts($seenReaders)?>
            </div>
        <?php } ?>
        </div>
<?php
            }
        }
    }
}
