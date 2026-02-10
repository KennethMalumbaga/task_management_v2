<?php 
session_start();

if (isset($_SESSION['id'])) {
    include "../../DB_connection.php";
    include "../model/user.php";
    include "../model/Message.php";
    include "../model/Group.php";
    include "../model/GroupMessage.php";

    // --- Users List ---
    $all_users = get_all_users($pdo);
    $users = [];
    foreach ($all_users as $user) {
        if ($user['id'] == $_SESSION['id']) continue;
        
        $lastMessage = lastChat($_SESSION['id'], $user['id'], $pdo);
        $user['last_msg_time'] = !empty($lastMessage) ? $lastMessage['created_at'] : '0000-00-00 00:00:00';
        $user['last_message_data'] = $lastMessage;
        $users[] = $user;
    }

    // Sort users by last message time desc
    usort($users, function($a, $b) {
        return strtotime($b['last_msg_time']) - strtotime($a['last_msg_time']);
    });

    ob_start();
    if ($users != 0) {
        foreach ($users as $user) {
            $lastMessage = $user['last_message_data'];
            $unreadCount = countUnreadChat($user['id'], $_SESSION['id'], $pdo);
            $unreadClass = ($unreadCount > 0) ? "unread" : "";
    ?>
    <div class="chat-item <?=$unreadClass?>" data-id="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>" data-role="<?=ucfirst($user['role'])?>">
        <div class="avatar-md">
             <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('../../uploads/' . $user['profile_image'])): ?>
                <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
             <?php else: ?>
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
             <?php endif; ?>
        </div>
        <div class="chat-item-content">
            <div class="chat-item-header">
                <span class="chat-user-name"><?= htmlspecialchars($user['full_name']) ?></span>
            </div>
            
            <div class="chat-item-sub-row">
                <?php if(!empty($lastMessage)) { ?>
                    <div class="chat-item-last-msg">
                        <?php 
                            if($lastMessage['sender_id'] == $_SESSION['id']) echo "You: ";
                            if(!empty($lastMessage['attachment']) && empty($lastMessage['message'])) echo "<i class='fa fa-paperclip'></i> Attachment"; 
                            else echo htmlspecialchars($lastMessage['message']);
                        ?>
                    </div>
                <?php } else { ?>
                    <div class="chat-user-role"><?= ucfirst($user['role']) ?></div>
                <?php } ?>
                
                <?php if($unreadCount > 0) { ?>
                    <span class="message-badge"><?=$unreadCount?></span>
                <?php } ?>
            </div>

            <?php if(!empty($lastMessage)) { ?>
                <span class="chat-time"><?=formatChatTime($lastMessage['created_at'])?></span>
            <?php } ?>
        </div>
    </div>
    <?php 
        }
    }
    $usersHtml = ob_get_clean();

    // --- Groups List ---
    $all_groups = get_groups_for_user($pdo, $_SESSION['id']);
    $groups = [];
    if (!empty($all_groups)) {
        foreach ($all_groups as $group) {
            $lastGroupMsg = get_last_group_message($pdo, $group['id']);
            $group['last_msg_time'] = !empty($lastGroupMsg) ? $lastGroupMsg['created_at'] : '0000-00-00 00:00:00';
            $groups[] = $group;
        }
        usort($groups, function($a, $b) {
            return strtotime($b['last_msg_time']) - strtotime($a['last_msg_time']);
        });
    }

    ob_start();
    if (!empty($groups)) { 
        foreach ($groups as $group) { 
    ?>
        <div class="chat-item group-item" data-group-id="<?=$group['id']?>" data-group-name="<?=htmlspecialchars($group['name'])?>">
            <div class="avatar-md" style="background:#EEF2FF; color:#4F46E5;">
                <i class="fa fa-users"></i>
            </div>
            <div class="chat-item-content">
                <div class="chat-item-header">
                    <span class="chat-user-name"><?=htmlspecialchars($group['name'])?></span>
                </div>
                <div class="chat-item-sub-row">
                    <div class="chat-user-role">Group Chat</div>
                    <?php 
                        $grpUnread = get_group_unread_count($pdo, $group['id'], $_SESSION['id']);
                        if($grpUnread > 0){
                    ?>
                        <span class="message-badge"><?=$grpUnread?></span>
                    <?php } ?>
                </div>
                <?php if(!empty($group['created_at'])) { ?>
                     <div class="chat-time"><?=formatChatTime($group['last_msg_time'])?></div>
                <?php } ?>
            </div>
        </div>
    <?php 
        } 
    } else { 
    ?>
        <div style="padding: 12px; color:#9CA3AF; font-size:13px;">No groups yet.</div>
    <?php 
    } 
    $groupsHtml = ob_get_clean();

    echo json_encode(['users' => $usersHtml, 'groups' => $groupsHtml]);
}
?>
