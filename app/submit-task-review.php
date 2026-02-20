<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['task_id'])) {
        include "../DB_connection.php";
        require_once "../inc/tenant.php";
        require_once "../inc/csrf.php";
        include "model/Notification.php";
        include "model/Task.php";
        require_once __DIR__ . "/helpers/input.php";

        if (!csrf_verify('submit_task_review_form', $_POST['csrf_token'] ?? null, true)) {
            $em = "Invalid or expired request. Please refresh and try again.";
            header("Location: ../my_task.php?error=" . urlencode($em));
            exit();
        }

        $task_id = validate_input($_POST['task_id']);
        $note = isset($_POST['submission_note']) ? validate_input($_POST['submission_note']) : null;

        // Handle File Upload
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
             $allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip','json'];
             $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
             
             if (in_array($ext, $allowed) && $_FILES['submission_file']['size'] <= 50 * 1024 * 1024) {
                 $upload_dir = "../uploads";
                 if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                 }
                 $filename = "task_{$task_id}_submit_" . time() . ".$ext";
                 if(move_uploaded_file($_FILES['submission_file']['tmp_name'], "$upload_dir/$filename")) {
                     $file_path = "uploads/$filename";
                 }
             }
        }

        // Update task status to 'completed' and save submission info
        if ($file_path) {
            $sql = "UPDATE tasks SET status = 'completed', submission_note = ?, submission_file = ?, reviewed_at = NOW() WHERE id = ?";
            $params = [$note, $file_path, $task_id];
            $scope = tenant_get_scope($pdo, 'tasks');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "UPDATE tasks SET status = 'completed', submission_note = ?, reviewed_at = NOW() WHERE id = ?";
            $params = [$note, $task_id];
            $scope = tenant_get_scope($pdo, 'tasks');
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

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
             insert_notification($pdo, ["Task Submitted by Leader (" . $_SESSION['full_name'] . ")", $admin['id'], 'Task Submitted', $task_id]);
        }

        $em = "Task submitted for review successfully";
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


