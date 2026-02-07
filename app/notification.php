<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "../DB_connection.php";
    include "model/Notification.php";
    include "model/Task.php";

    $notifications = get_all_my_notifications($pdo, $_SESSION['id']);
    $user_role = $_SESSION['role'];

    // Helper function to get task_id from notification
    function get_notification_task_id($pdo, $notification) {
        // First try to get task_id from notification record (if column exists)
        if (isset($notification['task_id']) && $notification['task_id'] !== null) {
            return $notification['task_id'];
        }
        
        // Otherwise, try to extract task title from message and find task
        $message = $notification['message'];
        // Extract task title from messages like: "'Task Title' has been assigned..."
        if (preg_match("/'([^']+)'/", $message, $matches)) {
            $task_title = $matches[1];
            $task = get_task_by_title($pdo, $task_title);
            if ($task != 0) {
                return $task['id'];
            }
        }
        
        return null;
    }

    if ($notifications == 0) { ?>
        <li>
        <a href="#">
            You have zero notification
        </a>
        </li>
       
    <?php }else{
    foreach ($notifications as $notification) {
        $task_id = get_notification_task_id($pdo, $notification);
 ?>
    <li>
    <a href="app/notification-read.php?notification_id=<?=$notification['id']?><?=$task_id ? '&task_id=' . $task_id : ''?>">
        
        <?php if ($notification['is_read'] == 0) {
            echo "<mark>".$notification['type']."</mark>: ";
        }else echo $notification['type'].": " ?>
        <?=$notification['message']?>
        &nbsp;&nbsp;<small><?=$notification['date']?></small>
    </a>
    </li>
 <?php
 }
 }
}else{ 
  echo "";
}
 ?>
