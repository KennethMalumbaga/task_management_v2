<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['subtask_id']) && isset($_POST['action']) && isset($_POST['feedback'])) {
        include "../DB_connection.php";
        require_once "../inc/csrf.php";
        include "model/Subtask.php";
        include "model/Notification.php";
        require_once __DIR__ . "/helpers/input.php";

        if (!csrf_verify('review_subtask_form', $_POST['csrf_token'] ?? null, true)) {
            $em = "Invalid or expired request. Please refresh and try again.";
            header("Location: ../my_task.php?error=" . urlencode($em));
            exit();
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
            if (!$is_self_review && ($score === null || $score < 1 || $score > 5)) {
                $em = "Please provide a performance score between 1 and 5 before accepting.";
                $openTaskId = (int)($subtask['task_id'] ?? 0);
                if ($openTaskId > 0) {
                    header("Location: ../my_task.php?error=$em&open_task=$openTaskId");
                } else {
                    header("Location: ../my_task.php?error=$em");
                }
                exit();
            }
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

