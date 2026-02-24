<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['task_id'])) {
        include "../DB_connection.php";
        require_once "../inc/tenant.php";
        require_once "../inc/csrf.php";
        include "model/Notification.php";
        include "model/Task.php";
        include "model/LeaderFeedback.php";
        require_once __DIR__ . "/helpers/input.php";

        if (!csrf_verify('resubmit_task_form', $_POST['csrf_token'] ?? null, true)) {
            $em = "Invalid or expired request. Please refresh and try again.";
            header("Location: ../my_task.php?error=" . urlencode($em));
            exit();
        }

        $task_id = validate_input($_POST['task_id']);
        $note = isset($_POST['revision_note']) ? validate_input($_POST['revision_note']) : null;
        
        // Handle File Upload
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
             $allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip','json'];
             $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
             
             // Basic validation (can add more robust checks)
             if (in_array($ext, $allowed) && $_FILES['submission_file']['size'] <= 50 * 1024 * 1024) {
                 $upload_dir = "../uploads";
                 if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                 }
                 $filename = "task_{$task_id}_resubmit_" . time() . ".$ext";
                 if(move_uploaded_file($_FILES['submission_file']['tmp_name'], "$upload_dir/$filename")) {
                     $file_path = "uploads/$filename";
                 }
             }
        }

        // Update task status to 'completed' again.
        if ($file_path) {
            $sql = "UPDATE tasks SET status = 'completed', submission_note = ?, submission_file = ?, rating = NULL, review_comment = NULL, reviewed_by = NULL, reviewed_at = NOW() WHERE id = ?";
            $params = [$note, $file_path, $task_id];
            $scope = tenant_get_scope($pdo, 'tasks');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Keep existing file if not replaced
            $sql = "UPDATE tasks SET status = 'completed', submission_note = ?, rating = NULL, review_comment = NULL, reviewed_by = NULL, reviewed_at = NOW() WHERE id = ?";
            $params = [$note, $task_id];
            $scope = tenant_get_scope($pdo, 'tasks');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // Clear any previous per-assignee ratings for the new review cycle.
        clear_task_assignee_ratings($pdo, $task_id);
        clear_leader_feedback_for_task($pdo, $task_id);

        // Notify Admin(s)
        $adminSql = "SELECT id FROM users WHERE role = 'admin'";
        $adminParams = [];
        $scope = tenant_get_scope($pdo, 'users');
        $adminSql .= $scope['sql'];
        $adminParams = array_merge($adminParams, $scope['params']);
        $stmt2 = $pdo->prepare($adminSql);
        $stmt2->execute($adminParams);
        $admins = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $task = get_task_by_id($pdo, $task_id);
        
        foreach($admins as $admin){
             insert_notification($pdo, ["Task Resubmitted by Leader (" . $_SESSION['full_name'] . ")", $admin['id'], 'Task Resubmitted', $task_id]);
        }

        $em = "Task resubmitted for review successfully";
        header("Location: ../my_task.php?success=$em");
        exit();

    }else {
        // Check for post_max_size violation
        if (empty($_POST) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
             $em = "The file is too large! It exceeds the server's post_max_size limit (defined in php.ini).";
             header("Location: ../my_task.php?error=$em");
             exit();
        }

        $em = "Unknown error occurred";
        header("Location: ../my_task.php?error=$em");
        exit();
    }
}else{ 
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}


