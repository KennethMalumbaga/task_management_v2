<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    
    if (isset($_POST['task_id'])) {
        include "../DB_connection.php";
        include "Model/Notification.php";
        include "Model/Task.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $task_id = validate_input($_POST['task_id']);
        $note = isset($_POST['revision_note']) ? validate_input($_POST['revision_note']) : null;
        
        // Update task status to 'completed' again.
        // Clear rating, reviewed_by, but maybe keep review_comment for history? 
        // User wants "Resubmit", implying a new attempt.
        // Usually we want to clear the 'revision requested' state.
        // The admin view checks for (status=completed && rating=0).
        // So we MUST clear rating.
        // We should PROBABLY clear review_comment too so it doesn't look like it has feedback already, 
        // OR we prepend/append old feedback?
        // Let's clear review_comment so it looks fresh, OR keep it but maybe the UI handles it?
        // If I clear review_comment, the "Revision Requested" badge in my_task.php (if based on comment) might disappear?
        // Wait, if status is 'completed', the "Revision Requested" badge won't show anyway (it shows on 'in_progress').
        // So setting status to 'completed' is enough to hide the revision warning.
        
        // We also want to update submission_note with the new note.
        
        $sql = "UPDATE tasks SET status = 'completed', submission_note = ?, rating = NULL, review_comment = NULL, reviewed_by = NULL, reviewed_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$note, $task_id]);

        // Notify Admin(s)
        $stmt2 = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $stmt2->execute();
        $admins = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $task = get_task_by_id($pdo, $task_id);
        
        foreach($admins as $admin){
             insert_notification($pdo, ["Task Resubmitted by Leader (" . $_SESSION['full_name'] . ")", $admin['id'], 'Task Resubmitted', $task_id]);
        }

        $em = "Task resubmitted for review successfully";
        header("Location: ../my_task.php?success=$em");
        exit();

    }else {
        $em = "Unknown error occurred";
        header("Location: ../my_task.php?error=$em");
        exit();
    }
}else{ 
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}
