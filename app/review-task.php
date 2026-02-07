<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    
    if (isset($_POST['task_id']) && isset($_POST['action']) && isset($_POST['feedback'])) {
        include "../DB_connection.php";
        include "model/Task.php";
        include "model/Notification.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $task_id = validate_input($_POST['task_id']);
        $action = validate_input($_POST['action']);
        $feedback = validate_input($_POST['feedback']);

        $task = get_task_by_id($pdo, $task_id);
        if ($task == 0) {
            header("Location: ../edit-task.php?error=Task not found&id=$task_id");
            exit();
        }

        if ($action == 'accept') {
            $status = 'completed';
            $msg = "'{$task['title']}' has been ACCEPTED.";
        } else if ($action == 'revise') {
            $status = 'revise';
            $msg = "'{$task['title']}' requires REVISION. feedback: $feedback";
        } else {
             header("Location: ../edit-task.php?error=Invalid action&id=$task_id");
             exit();
        }

        // Update task status and comment
        $sql = "UPDATE tasks SET status = ?, review_comment = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $feedback, $task_id]);
        
        // Notify the assignee
        if ($task['assigned_to']) {
            insert_notification($pdo, [$msg, $task['assigned_to'], 'Task Review', $task_id]);
        }

        $em = "Task updated successfully";
        header("Location: ../edit-task.php?success=$em&id=$task_id");
        exit();

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

