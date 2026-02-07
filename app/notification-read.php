<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "../DB_connection.php";
    include "model/Notification.php";

   if (isset($_GET['notification_id'])) {
       $notification_id = $_GET['notification_id'];
       $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : null;
       
       // Mark notification as read
       notification_make_read($pdo, $_SESSION['id'], $notification_id);
       
       // Redirect to task if task_id is provided
       if ($task_id) {
           $user_role = $_SESSION['role'];
           if ($user_role === 'admin') {
               // Admin: redirect to edit-task.php to see submitted file
               header("Location: ../edit-task.php?id=" . $task_id);
           } else if ($user_role === 'employee') {
               // Employee: redirect to edit-task-employee.php to see task details
               header("Location: ../edit-task-employee.php?id=" . $task_id);
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
