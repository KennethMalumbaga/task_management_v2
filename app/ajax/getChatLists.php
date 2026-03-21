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
    $userPresenceMap = get_users_clocked_in_map($pdo, array_column($users, 'id'));

    ob_start();
    if ($users != 0) {
        foreach ($users as $user) {
            $lastMessage = $user['last_message_data'];
            $unreadCount = countUnreadChat($user['id'], $_SESSION['id'], $pdo);
            $user['is_online'] = !empty($userPresenceMap[(int)$user['id']]);
    ?>
    <?= render_chat_user_list_item($user, $lastMessage, $unreadCount, (int)$_SESSION['id']) ?>
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
            $group['last_message_data'] = $lastGroupMsg;
            $group['last_msg_sort_time'] = (!empty($lastGroupMsg) && !empty($lastGroupMsg['created_at']))
                ? $lastGroupMsg['created_at']
                : null;
            if (!empty($lastGroupMsg) && !empty($lastGroupMsg['created_at'])) {
                $group['last_msg_time'] = $lastGroupMsg['created_at'];
            } elseif (!empty($group['created_at'])) {
                $group['last_msg_time'] = $group['created_at'];
            } else {
                $group['last_msg_time'] = null;
            }
            $groups[] = $group;
        }
        usort($groups, function($a, $b) {
            return strtotime($b['last_msg_time'] ?? '1970-01-01 00:00:00') - strtotime($a['last_msg_time'] ?? '1970-01-01 00:00:00');
        });
    }

    ob_start();
    if (!empty($groups)) { 
        foreach ($groups as $group) { 
            $lastGroupMsg = $group['last_message_data'] ?? [];
            $groupLastTimestamp = !empty($group['last_msg_sort_time']) ? strtotime($group['last_msg_sort_time']) : 0;
            if ($groupLastTimestamp === false) $groupLastTimestamp = 0;
            $groupPreview = format_group_list_preview($pdo, $lastGroupMsg, (int)$_SESSION['id']);
    ?>
        <div class="chat-item group-item" data-group-id="<?=$group['id']?>" data-group-name="<?=htmlspecialchars($group['name'])?>" data-last-ts="<?=$groupLastTimestamp?>">
            <div class="avatar-md" style="background:var(--primary-soft-3); color:var(--primary);">
                <i class="fa fa-users"></i>
            </div>
            <div class="chat-item-content">
                <div class="chat-item-header">
                    <span class="chat-user-name"><?=htmlspecialchars($group['name'])?></span>
                </div>
                <div class="chat-item-sub-row">
                    <div class="chat-item-last-msg"><?=$groupPreview?></div>
                    <?php 
                        $grpUnread = get_group_unread_count($pdo, $group['id'], $_SESSION['id']);
                        if($grpUnread > 0){
                    ?>
                        <span class="message-badge"><?=$grpUnread?></span>
                    <?php } ?>
                </div>
                <?php if(!empty($group['last_msg_time'])) { ?>
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

    echo json_encode([
        'users' => $usersHtml, 
        'groups' => $groupsHtml,
        'totalUnread' => countAllUnread($_SESSION['id'], $pdo) + count_all_group_unread($pdo, $_SESSION['id'])
    ]);
}
?>
