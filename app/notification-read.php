<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "../DB_connection.php";
    require_once "../inc/csrf.php";
    include "model/Notification.php";

    if (!csrf_verify('notification_read_action', $_GET['csrf_token'] ?? null, false)) {
       $em = urlencode("Invalid or expired request");
       header("Location: ../notifications.php?error=$em");
       exit();
    }

   if (isset($_GET['notification_id'])) {
       $notification_id = $_GET['notification_id'];
       $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : null;
       
       // Mark notification as read
       notification_make_read($pdo, $_SESSION['id'], $notification_id);
       
       // Redirect to task if task_id is provided
       if ($task_id) {
           $user_role = $_SESSION['role'];
           if ($user_role === 'admin') {
               // Admin: open the task in the current task management page
               header("Location: ../tasks.php?open_task=" . $task_id);
           } else if ($user_role === 'employee') {
               // Employee: open task modal in My Tasks page
               header("Location: ../my_task.php?open_task=" . $task_id);
           } else {
               header("Location: ../notifications.php");
           }
       } else {
           // No task_id, just go to notifications page
           header("Location: ../notifications.php");
       }
       exit();

     }else {
       header("Location: index.php");
       exit();
     }
}else{ 
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
}
 ?>
