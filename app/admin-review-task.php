<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    
    if (isset($_POST['task_id']) && isset($_POST['action'])) {
        include "../DB_connection.php";
        require_once "../inc/tenant.php";
        require_once "../inc/csrf.php";
        include "model/Notification.php";
        include "model/Task.php";
        include "model/LeaderFeedback.php";
        require_once __DIR__ . "/helpers/input.php";

        if (!csrf_verify('admin_review_task_form', $_POST['csrf_token'] ?? null, true)) {
            $em = "Invalid or expired request. Please refresh and try again.";
            header("Location: ../tasks.php?error=" . urlencode($em));
            exit();
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
        $leader_id = 0;
        $assignees = get_task_assignees($pdo, $task_id);
        if ($assignees != 0) {
            foreach ($assignees as $a) {
                // Determine who to notify. Usually Leader and maybe members.
                // For simplicity, notify everyone.
                $assigned_to_ids[] = $a['user_id'];
                if (($a['role'] ?? '') === 'leader') {
                    $leader_id = (int)$a['user_id'];
                }
            }
        }

        if ($action == 'accept') {
            $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            $leader_rating = isset($_POST['leader_rating']) ? (int)$_POST['leader_rating'] : 0;
            $feedback = isset($_POST['feedback']) ? validate_input($_POST['feedback']) : '';

            if ($rating < 1 || $rating > 5) {
                $em = "Task rating must be between 1 and 5.";
                header("Location: ../tasks.php?error=$em&open_task=$task_id");
                exit();
            }

            if ($leader_id > 0 && ($leader_rating < 1 || $leader_rating > 5)) {
                $em = "Leader rating must be between 1 and 5.";
                header("Location: ../tasks.php?error=$em&open_task=$task_id");
                exit();
            }

            $sql = "UPDATE tasks SET status = 'completed', rating = ?, review_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
            $params = [$rating, $feedback, $_SESSION['id'], $task_id];
            $scope = tenant_get_scope($pdo, 'tasks');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() <= 0) {
                $em = "Task review failed: task not found in your workspace.";
                header("Location: ../tasks.php?error=$em&open_task=$task_id");
                exit();
            }

            if ($leader_id > 0 && $leader_rating > 0) {
                update_task_assignee_ratings($pdo, $task_id, [$leader_id => $leader_rating], $_SESSION['id']);
            }

            // Notify
            foreach($assigned_to_ids as $uid) {
                insert_notification($pdo, ["Task is rated ($rating/5): " . $task['title'], $uid, 'Task Rated', $task_id]);
            }
            
            $em = "Task accepted and rated successfully";
            header("Location: ../tasks.php?success=$em");
            exit();

        } else if ($action == 'revise') {
            $feedback = isset($_POST['feedback']) ? validate_input($_POST['feedback']) : '';

            // Set status to 'in_progress' for revision
            $sql = "UPDATE tasks SET status = 'in_progress', review_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
            $params = [$feedback, $_SESSION['id'], $task_id];
            $scope = tenant_get_scope($pdo, 'tasks');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() <= 0) {
                $em = "Revision request failed: task not found in your workspace.";
                header("Location: ../tasks.php?error=$em&open_task=$task_id");
                exit();
            }

            // Clear ratings tied to previous acceptance cycle.
            clear_task_assignee_ratings($pdo, $task_id);
            clear_leader_feedback_for_task($pdo, $task_id);

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

