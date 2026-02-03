<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['id']) && isset($_FILES['submission_file'])) {
        require_once "../DB_connection.php";
        require_once "Model/Subtask.php";
        require_once "Model/Notification.php";
        require_once "Model/Task.php"; // Include this at the top

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $id = validate_input($_POST['id']);
        
        $subtask = get_subtask_by_id($pdo, $id);
        if (!$subtask) {
             header("Location: ../my_subtasks.php?error=Subtask not found");
             exit();
        }

        /* ---- FILE UPLOAD ---- */
        if ($_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $_FILES['submission_file']['error'];
            header("Location: ../my_task.php?error=Upload failed with error code $errorCode&open_task=" . $subtask['task_id']);
            exit();
        }

        $allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip'];
        $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            header("Location: ../my_task.php?error=Invalid file type&open_task=" . $subtask['task_id']);
            exit();
        }

        if ($_FILES['submission_file']['size'] > 100 * 1024 * 1024) {
            header("Location: ../my_task.php?error=File too large (Max 100MB)&open_task=" . $subtask['task_id']);
            exit();
        }

        $upload_dir = "../uploads"; // Relative to app/ folder
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = "subtask_{$id}_" . time() . ".$ext";
        $destination = "$upload_dir/$filename";
        
        if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $destination)) {
            header("Location: ../my_task.php?error=Failed to move uploaded file&open_task=" . $subtask['task_id']);
            exit();
        }

        // Get submission note
        $note = isset($_POST['submission_note']) ? validate_input($_POST['submission_note']) : null;

        // Save relative path for database (so it works from root)
        update_subtask_submission($pdo, $id, "uploads/$filename", $note);

        // Notify Leader
        $assignees = get_task_assignees($pdo, $subtask['task_id']);
        if ($assignees != 0) {
            foreach($assignees as $a) {
                if ($a['role'] == 'leader') {
                    insert_notification($pdo, ["Subtask submitted by User " . $_SESSION['id'], $a['user_id'], 'Subtask Submitted', $subtask['task_id']]);
                }
            }
        }

        $em = "Subtask submitted successfully";
        header("Location: ../my_task.php?success=$em&open_task=" . $subtask['task_id']);
        exit();

    }else {
        $em = "Unknown error occurred";
        header("Location: ../my_subtasks.php?error=$em");
        exit();
    }
}else{ 
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}
