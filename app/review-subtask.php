<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['subtask_id']) && isset($_POST['action']) && isset($_POST['feedback'])) {
        include "../DB_connection.php";
        include "model/Subtask.php";
        include "model/Notification.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $subtask_id = validate_input($_POST['subtask_id']);
        $action = validate_input($_POST['action']);
        $feedback = validate_input($_POST['feedback']);
        $parent_id = isset($_POST['parent_id']) ? validate_input($_POST['parent_id']) : null;
        $score = isset($_POST['score']) && is_numeric($_POST['score']) ? (int)$_POST['score'] : null;

        $subtask = get_subtask_by_id($pdo, $subtask_id);
        if (!$subtask) {
            header("Location: ../my_task.php?error=Subtask not found");
            exit();
        }

        $is_self_review = ((int)$subtask['member_id'] === (int)$_SESSION['id']);
        if ($is_self_review) {
            // Prevent leaders from inflating collaborative score on their own subtasks.
            $score = null;
        }

        if ($action == 'accept') {
            $status = 'completed';
            $score_msg = ($score !== null) ? " Score: $score/5." : "";
            if ($is_self_review) {
                $msg = "Your subtask submission has been ACCEPTED. Self-rating is disabled.";
            } else {
                $msg = "Your subtask submission has been ACCEPTED.$score_msg";
            }
            // Only allow score on accept
        } else if ($action == 'revise') {
            $status = 'revise';
            $score = null; // No score on revision
            $msg = "Your subtask submission requires REVISION. Feedback: $feedback";
        } else {
             header("Location: ../my_task.php?error=Invalid action");
             exit();
        }

        update_subtask_status($pdo, $subtask_id, $status, $feedback, $score);
        
        // Notify the member
        insert_notification($pdo, [$msg, $subtask['member_id'], 'Subtask Review', $subtask['task_id']]);

        $em = "Subtask updated successfully";
        header("Location: ../my_task.php?success=$em");
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

