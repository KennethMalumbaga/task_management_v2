<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "../DB_connection.php";
    require_once "../inc/csrf.php";
    require_once __DIR__ . "/helpers/notification.php";
    include "model/Notification.php";
    include "model/Task.php";
    $notificationReadCsrfToken = csrf_token('notification_read_action');

    $notifications = get_all_my_notifications($pdo, $_SESSION['id']);
    $notificationNowTs = tm_notification_reference_now($pdo);
    $user_role = $_SESSION['role'];

    if ($notifications == 0) { ?>
        <li>
        <a href="#">
            You have zero notification
        </a>
        </li>
       
    <?php }else{
    foreach ($notifications as $notification) {
        $task_id = tm_get_notification_task_id($pdo, $notification);
        $notificationWhen = tm_notification_time_ago($notification, $notificationNowTs);
 ?>
    <li>
    <a href="app/notification-read.php?notification_id=<?=$notification['id']?><?=$task_id ? '&task_id=' . $task_id : ''?>&csrf_token=<?=urlencode($notificationReadCsrfToken)?>">
        
        <?php if (tm_notification_is_unread($notification)) {
            echo "<mark>".$notification['type']."</mark>: ";
        }else echo $notification['type'].": " ?>
        <?=$notification['message']?>
        &nbsp;&nbsp;<small><?= htmlspecialchars($notificationWhen) ?></small>
    </a>
    </li>
 <?php
 }
 }
}else{ 
  echo "";
}
 ?>
