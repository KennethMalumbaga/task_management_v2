<?php 
session_start();

if (isset($_SESSION['id'])) {
    
    if (isset($_POST['key'])) {

       include "../../DB_connection.php";
       include "../model/user.php";
       include "../model/Message.php";
       include "../model/Group.php";
       include "../model/GroupMessage.php";

       $key = "%{$_POST['key']}%";
     
       $sql = "SELECT * FROM users
               WHERE full_name ILIKE ? OR username ILIKE ?";
       $stmt = $pdo->prepare($sql);
       $stmt->execute([$key, $key]);

       ob_start();
       if($stmt->rowCount() > 0){ 
           $users = $stmt->fetchAll();
           $hasUser = false;
           foreach ($users as $user) {
               if ($user['id'] == $_SESSION['id']) continue;
               $hasUser = true;
               
               $lastMessage = lastChat($_SESSION['id'], $user['id'], $pdo);
               $unreadCount = countUnreadChat($user['id'], $_SESSION['id'], $pdo);
               $unreadClass = ($unreadCount > 0) ? "unread" : "";
       ?>
       <div class="chat-item <?=$unreadClass?>" data-id="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>" data-role="<?=ucfirst($user['role'])?>">
            <div class="avatar-md">
                 <?php 
                 if (!empty($user['profile_image']) && file_exists('../../uploads/' . $user['profile_image'])) {
                     echo '<img src="uploads/'.$user['profile_image'].'" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">';
                 } else {
                     echo strtoupper(substr($user['full_name'], 0, 1));
                 }
                 ?>
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
                    FROM groups g
                    INNER JOIN group_members gm ON g.id = gm.group_id
                    WHERE gm.user_id = ?
                      AND g.name ILIKE ?
                    ORDER BY g.id DESC";
       $groupStmt = $pdo->prepare($groupSql);
       $groupStmt->execute([$_SESSION['id'], $key]);
       $groups = $groupStmt->fetchAll();

       ob_start();
       if(!empty($groups)) {
           foreach ($groups as $group) {
               $grpUnread = get_group_unread_count($pdo, $group['id'], $_SESSION['id']);
               $lastGroupMsg = get_last_group_message($pdo, $group['id']);
               $lastMsgTime = !empty($lastGroupMsg['created_at'])
                   ? $lastGroupMsg['created_at']
                   : (!empty($group['created_at']) ? $group['created_at'] : null);
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
                    <?php if($grpUnread > 0){ ?>
                        <span class="message-badge"><?=$grpUnread?></span>
                    <?php } ?>
                </div>
                <?php if(!empty($lastMsgTime)) { ?>
                    <div class="chat-time"><?=formatChatTime($lastMsgTime)?></div>
                <?php } ?>
            </div>
       </div>
       <?php
           }
       } else {
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
