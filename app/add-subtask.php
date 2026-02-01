<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['task_id']) && isset($_POST['member_id']) && isset($_POST['description']) && isset($_POST['due_date'])) {
        include "../DB_connection.php";
        include "Model/Subtask.php";
        include "Model/Notification.php";
        include "Model/Task.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $task_id = validate_input($_POST['task_id']);
        $member_id = validate_input($_POST['member_id']);
        $description = validate_input($_POST['description']);
        $due_date = validate_input($_POST['due_date']);
        $parent_id = isset($_POST['parent_id']) ? validate_input($_POST['parent_id']) : $task_id; // For redirect

        if (empty($description)) {
            $em = "Description is required";
            header("Location: ../my_task.php?error=$em");
            exit();
        }else if (empty($due_date)) {
            $em = "Due Date is required";
            header("Location: ../my_task.php?error=$em");
            exit();
        }else {
            $data = array($task_id, $member_id, $description, $due_date);
            insert_subtask($pdo, $data);

            // Notify the member
            $task = get_task_by_id($pdo, $task_id);
            $notif_msg = "You have been assigned a subtask for: " . $task['title'];
            insert_notification($pdo, [$notif_msg, $member_id, 'New Subtask', $task_id]);

            $em = "Subtask created successfully";
            header("Location: ../my_task.php?success=$em");
            exit();
        }
    }else {
        $em = "Unknown error occurred";
        header("Location: ../tasks.php?error=$em");
        exit();
    }
}else{ 
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}
