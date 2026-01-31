<?php 
session_start();

if (isset($_SESSION['id'])) {
    
    if (isset($_POST['key'])) {

       include "../../DB_connection.php";
       include "../Model/User.php";

       $key = "%{$_POST['key']}%";
     
       $sql = "SELECT * FROM users
               WHERE full_name ILIKE ? OR username ILIKE ?";
       $stmt = $pdo->prepare($sql);
       $stmt->execute([$key, $key]);

       include "../Model/Message.php";
       
       if($stmt->rowCount() > 0){ 
         $users = $stmt->fetchAll();
         foreach ($users as $user) {
         	if ($user['id'] == $_SESSION['id']) continue;
            
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
                    <?php if($unreadCount > 0) { ?>
                        <span class="message-badge"><?=$unreadCount?></span>
                    <?php } ?>
                </div>
                
                 <?php if(!empty($lastMessage)) { ?>
                    <div class="chat-item-last-msg">
                        <?php 
                            if($lastMessage['sender_id'] == $_SESSION['id']) echo "You: ";
                            echo htmlspecialchars($lastMessage['message']);
                        ?>
                    </div>
                    <div class="chat-time" style="text-align: right; margin-top: -15px; font-size: 10px;">
                        <?=formatChatTime($lastMessage['created_at'])?>
                    </div>
                <?php } else { ?>
                    <div class="chat-user-role"><?= ucfirst($user['role']) ?></div>
                <?php } ?>
            </div>
       </div>
       <?php 
         }
       }else{ 
       ?>
       <div style="padding: 20px; text-align: center; color: var(--text-gray); font-size: 13px;">
           <i class="fa fa-user-times"></i> No user found
       </div>
       <?php } 
    }
}
