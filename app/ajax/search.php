<?php 
session_start();

if (isset($_SESSION['id'])) {
    
    if (isset($_POST['key'])) {

       include "../../DB_connection.php";
       require_once "../../inc/tenant.php";
       include "../model/user.php";
       include "../model/Message.php";
       include "../model/Group.php";
       include "../model/GroupMessage.php";
       include "../model/ChatVisibility.php";

       $key = "%{$_POST['key']}%";
       $hiddenThreadsMap = get_hidden_threads_map($pdo, (int)$_SESSION['id']);
     
       $sql = "SELECT * FROM users
               WHERE (LOWER(full_name) LIKE LOWER(?) OR LOWER(username) LIKE LOWER(?))";
       $params = [$key, $key];
       $scope = tenant_get_scope($pdo, 'users');
       $sql .= $scope['sql'];
       $params = array_merge($params, $scope['params']);
       $stmt = $pdo->prepare($sql);
       $stmt->execute($params);

       ob_start();
       if($stmt->rowCount() > 0){ 
           $users = $stmt->fetchAll();
           $userPresenceMap = get_users_clocked_in_map($pdo, array_column($users, 'id'));
           $hasUser = false;
           foreach ($users as $user) {
                if ($user['id'] == $_SESSION['id']) continue;
                $lastMessage = lastChat($_SESSION['id'], $user['id'], $pdo);
                if (chat_thread_should_be_hidden($hiddenThreadsMap['users'][(int)$user['id']] ?? null, (string)($lastMessage['created_at'] ?? ''))) {
                    continue;
                }
                $hasUser = true;
                $unreadCount = countUnreadChat($user['id'], $_SESSION['id'], $pdo);
                $user['is_online'] = !empty($userPresenceMap[(int)$user['id']]);
        ?>
       <?= render_chat_user_list_item($user, $lastMessage, $unreadCount, (int)$_SESSION['id']) ?>
       <?php 
           }
           
           if (!$hasUser) {
       ?>
       <div style="padding: 20px; text-align: center; color: var(--text-gray); font-size: 13px;">
           <i class="fa fa-user-times"></i> No user found
       </div>
       <?php
           }
       } else { 
       ?>
       <div style="padding: 20px; text-align: center; color: var(--text-gray); font-size: 13px;">
           <i class="fa fa-user-times"></i> No user found
       </div>
       <?php 
       }
       $usersHtml = ob_get_clean();

       $groupSql = "SELECT g.*
                    FROM `groups` g
                    INNER JOIN group_members gm ON g.id = gm.group_id
                     WHERE gm.user_id = ?
                       AND LOWER(g.name) LIKE LOWER(?)";
       $groupParams = [$_SESSION['id'], $key];
       $scope = tenant_get_scope($pdo, 'groups', 'g');
       $groupSql .= $scope['sql'] . "
                     ORDER BY g.id DESC";
       $groupParams = array_merge($groupParams, $scope['params']);
       $groupStmt = $pdo->prepare($groupSql);
       $groupStmt->execute($groupParams);
       $groups = $groupStmt->fetchAll();

       ob_start();
       $hasGroup = false;
       if(!empty($groups)) {
           foreach ($groups as $group) {
               $grpUnread = get_group_unread_count($pdo, $group['id'], $_SESSION['id']);
               $lastGroupMsg = get_last_group_message($pdo, $group['id']);
               $lastMsgTime = !empty($lastGroupMsg['created_at'])
                   ? $lastGroupMsg['created_at']
                   : (!empty($group['created_at']) ? $group['created_at'] : null);
               if (chat_thread_should_be_hidden($hiddenThreadsMap['groups'][(int)$group['id']] ?? null, (string)$lastMsgTime)) {
                   continue;
               }
               $hasGroup = true;
               $groupLastTimestamp = !empty($lastGroupMsg['created_at']) ? strtotime($lastGroupMsg['created_at']) : 0;
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
                    <?php if($grpUnread > 0){ ?>
                        <span class="message-badge"><?=$grpUnread?></span>
                    <?php } ?>
                </div>
                <?php if(!empty($lastMsgTime)) { ?>
                    <div class="chat-time"><?=formatChatTime($lastMsgTime)?></div>
                <?php } ?>
            </div>
            <button
                type="button"
                class="chat-item-delete-btn"
                aria-label="Delete chat <?= htmlspecialchars($group['name']) ?>"
                title="Delete chat"
                data-delete-type="group"
                data-delete-id="<?=$group['id']?>"
                data-delete-name="<?=htmlspecialchars($group['name'])?>">
                <i class="fa fa-trash-o"></i>
            </button>
       </div>
       <?php
           }
       }

       if (!$hasGroup) {
       ?>
       <div style="padding: 12px; color:#9CA3AF; font-size:13px;">No groups found</div>
       <?php
       }
       $groupsHtml = ob_get_clean();

       echo json_encode([
           'users' => $usersHtml,
           'groups' => $groupsHtml
       ]);
    }
}

