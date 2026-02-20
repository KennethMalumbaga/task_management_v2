<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    
    if (isset($_POST['task_id']) && isset($_POST['action']) && isset($_POST['feedback'])) {
        include "../DB_connection.php";
        require_once "../inc/tenant.php";
        require_once "../inc/csrf.php";
        include "model/Task.php";
        include "model/Notification.php";
        require_once __DIR__ . "/helpers/input.php";

        $task_id = validate_input($_POST['task_id']);
        $submittedToken = $_POST['csrf_token'] ?? null;
        $validReviewToken = csrf_verify('review_task_form', $submittedToken, false);
        $validAdminToken = csrf_verify('admin_review_task_form', $submittedToken, false);
        if (!$validReviewToken && !$validAdminToken) {
            $em = "Invalid or expired request. Please refresh and try again.";
            header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$task_id));
            exit();
        }
        csrf_verify($validReviewToken ? 'review_task_form' : 'admin_review_task_form', $submittedToken, true);

        $action = validate_input($_POST['action']);
        $feedback = validate_input($_POST['feedback']);

        $task = get_task_by_id($pdo, $task_id);
        if ($task == 0) {
            header("Location: ../tasks.php?error=" . urlencode("Task not found") . "&open_task=" . urlencode((string)$task_id));
            exit();
        }

        if ($action == 'accept') {
            $status = 'completed';
            $msg = "'{$task['title']}' has been ACCEPTED.";
        } else if ($action == 'revise') {
            $status = 'revise';
            $msg = "'{$task['title']}' requires REVISION. feedback: $feedback";
        } else {
             header("Location: ../tasks.php?error=" . urlencode("Invalid action") . "&open_task=" . urlencode((string)$task_id));
             exit();
        }

        // Update task status and comment
        $sql = "UPDATE tasks SET status = ?, review_comment = ? WHERE id = ?";
        $params = [$status, $feedback, $task_id];
        $scope = tenant_get_scope($pdo, 'tasks');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Notify the assignee
        if ($task['assigned_to']) {
            insert_notification($pdo, [$msg, $task['assigned_to'], 'Task Review', $task_id]);
        }

        $em = "Task updated successfully";
        header("Location: ../tasks.php?success=" . urlencode($em) . "&open_task=" . urlencode((string)$task_id));
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

