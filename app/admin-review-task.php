<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    
    if (isset($_POST['task_id']) && isset($_POST['action'])) {
        include "../DB_connection.php";
        include "model/Notification.php";
        include "model/Task.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $task_id = validate_input($_POST['task_id']);
        $action = validate_input($_POST['action']);
        
        // Get Task Info for notification
        $task = get_task_by_id($pdo, $task_id);
        if (!$task) {
             header("Location: ../tasks.php?error=Task not found");
             exit();
        }
        
        $assigned_to_ids = [];
        $assignees = get_task_assignees($pdo, $task_id);
        if ($assignees != 0) {
            foreach ($assignees as $a) {
                // Determine who to notify. Usually Leader and maybe members.
                // For simplicity, notify everyone.
                $assigned_to_ids[] = $a['user_id'];
            }
        }

        if ($action == 'accept') {
            $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            $feedback = isset($_POST['feedback']) ? validate_input($_POST['feedback']) : '';

            $sql = "UPDATE tasks SET status = 'completed', rating = ?, review_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$rating, $feedback, $_SESSION['id'], $task_id]);

            // Notify
            foreach($assigned_to_ids as $uid) {
                insert_notification($pdo, ["Task Accepted & Rated ($rating/5): " . $task['title'], $uid, 'Task Verified', $task_id]);
            }
            
            $em = "Task accepted and rated successfully";
            header("Location: ../tasks.php?success=$em");
            exit();

        } else if ($action == 'revise') {
            $feedback = isset($_POST['feedback']) ? validate_input($_POST['feedback']) : '';

            // Set status to 'in_progress' for revision
            $sql = "UPDATE tasks SET status = 'in_progress', review_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$feedback, $_SESSION['id'], $task_id]);

            // Notify
            foreach($assigned_to_ids as $uid) {
                insert_notification($pdo, ["Task Revision Requested: " . $task['title'], $uid, 'Task Revision', $task_id]);
            }

            $em = "Revision requested successfully";
            header("Location: ../tasks.php?success=$em");
            exit();
        } else {
            header("Location: ../tasks.php?error=Invalid action");
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

