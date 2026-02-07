<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['task_id'])) {
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
        $note = isset($_POST['submission_note']) ? validate_input($_POST['submission_note']) : null;

        // Handle File Upload
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
             $allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip'];
             $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
             
             if (in_array($ext, $allowed) && $_FILES['submission_file']['size'] <= 100 * 1024 * 1024) {
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
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$note, $file_path, $task_id]);
        } else {
            $sql = "UPDATE tasks SET status = 'completed', submission_note = ?, reviewed_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$note, $task_id]);
        }

        // Notify Admin(s)
        $stmt2 = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $stmt2->execute();
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

