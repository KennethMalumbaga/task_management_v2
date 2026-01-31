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

       if($stmt->rowCount() > 0){ 
         $users = $stmt->fetchAll();
         foreach ($users as $user) {
         	if ($user['id'] == $_SESSION['id']) continue;
       ?>
       <div class="chat-item" data-id="<?=$user['id']?>">
            <div class="user-avatar-sm" style="width: 40px; height: 40px; background: #E0E7FF; color: var(--primary);">
                 <?php 
                 if (!empty($user['profile_image']) && file_exists('../../uploads/' . $user['profile_image'])) {
                     echo '<img src="uploads/'.$user['profile_image'].'" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">';
                 } else {
                     echo strtoupper(substr($user['full_name'], 0, 1));
                 }
                 ?>
            </div>
            <div>
                <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($user['full_name']) ?></div>
                <div style="font-size: 12px; color: var(--text-gray);"><?= ucfirst($user['role']) ?></div>
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
