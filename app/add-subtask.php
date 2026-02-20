<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {

    if (isset($_POST['task_id']) && isset($_POST['member_id']) && isset($_POST['description']) && isset($_POST['due_date'])) {
        include "../DB_connection.php";
        require_once "../inc/tenant.php";
        require_once "../inc/csrf.php";
        include "model/Subtask.php";
        include "model/Notification.php";
        include "model/Task.php";
        require_once __DIR__ . "/helpers/input.php";

        if (!csrf_verify('add_subtask_form', $_POST['csrf_token'] ?? null, true)) {
            $em = "Invalid or expired request. Please refresh and try again.";
            header("Location: ../my_task.php?error=" . urlencode($em));
            exit();
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
        }
        else if (empty($due_date)) {
            $em = "Due Date is required";
            header("Location: ../my_task.php?error=$em");
            exit();
        }
        else {
            // Check for duplicate subtask (same description in the same task)
            $checkSql = "SELECT id FROM subtasks WHERE task_id = ? AND description = ?";
            $checkParams = [$task_id, $description];
            $scope = tenant_get_scope($pdo, 'subtasks');
            $checkSql .= $scope['sql'];
            $checkParams = array_merge($checkParams, $scope['params']);
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute($checkParams);
            if ($checkStmt->rowCount() > 0) {
                $em = "A subtask with this description already exists in this task.";
                header("Location: ../my_task.php?error=$em");
                exit();
            }

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
    }
    else {
        $em = "Unknown error occurred";
        header("Location: ../tasks.php?error=$em");
        exit();
    }
}
else {
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}
