<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

if (isset($_POST['id']) && isset($_POST['title']) && isset($_POST['description']) && isset($_POST['assigned_to']) && $_SESSION['role'] == 'admin'&& isset($_POST['due_date']) && isset($_POST['status'])) {
	include "../DB_connection.php";
    require_once "../inc/csrf.php";
    require_once __DIR__ . "/helpers/input.php";
    require_once __DIR__ . "/helpers/task_links.php";

    $id = validate_input($_POST['id']);
    $submittedToken = $_POST['csrf_token'] ?? null;
    $validUpdateToken = csrf_verify('update_task_form', $submittedToken, false);
    $validAdminToken = csrf_verify('admin_review_task_form', $submittedToken, false);
    if (!$validUpdateToken && !$validAdminToken) {
        $em = "Invalid or expired request. Please refresh and try again.";
        header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
        exit();
    }
    csrf_verify($validUpdateToken ? 'update_task_form' : 'admin_review_task_form', $submittedToken, true);

	$title = validate_input($_POST['title']);
	$description = validate_input($_POST['description']);
	$assigned_to = validate_input($_POST['assigned_to']);
	$due_date = validate_input($_POST['due_date']);
	$status = validate_input($_POST['status']);
	$review_comment = isset($_POST['review_comment']) ? validate_input($_POST['review_comment']) : "";
    $google_doc_url_raw = isset($_POST['google_doc_url']) ? trim((string)$_POST['google_doc_url']) : "";

	if (empty($title)) {
		$em = "Title is required";
	    header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
	    exit();
	}else if (empty($description)) {
		$em = "Description is required";
	    header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
	    exit();
	}else if ($assigned_to == 0) {
		$em = "Select User";
	    header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
	    exit();
	}else {
       $google_doc_url = task_link_normalize_google_doc_url($google_doc_url_raw);
       if ($google_doc_url === null) {
           $em = "Google Docs link must be a valid docs.google.com/document URL.";
           header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
           exit();
       }
    
       include "model/Task.php";
       include "model/Notification.php";
       include "model/Group.php";

       // Handle template file upload (optional)
       $template_file_path = null;
       if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] == UPLOAD_ERR_OK) {
           $file = $_FILES['template_file'];
           
           // Validate file type
           $allowed_extensions = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip','txt'];
           $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
           
           if (!in_array($file_ext, $allowed_extensions)) {
               $em = "Invalid template file type. Allowed: pdf, doc, docx, xls, xlsx, png, jpg, jpeg, zip, txt.";
               header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
               exit();
           }
           
           // Max 50MB
           if ($file['size'] > 50 * 1024 * 1024) {
               $em = "Template file is too large. Maximum allowed size is 50MB.";
               header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
               exit();
           }
           
           // Ensure uploads directory exists
           $upload_dir = "../uploads";
           if (!is_dir($upload_dir)) {
               mkdir($upload_dir, 0777, true);
           }
           
           // Get current task to delete old template file if exists
           $current_task = get_task_by_id($pdo, $id);
           if ($current_task != 0 && !empty($current_task['template_file'])) {
               $old_file_path = "../" . $current_task['template_file'];
               if (file_exists($old_file_path)) {
                   @unlink($old_file_path);
               }
           }
           
           // Generate unique filename
           $new_filename = "template_" . $id . "_" . time() . "_" . basename($file['name']);
           $destination = $upload_dir . "/" . $new_filename;
           
           if (move_uploaded_file($file['tmp_name'], $destination)) {
               $template_file_path = "uploads/" . $new_filename;
           } else {
               $em = "Failed to upload template file. Please try again.";
               header("Location: ../tasks.php?error=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
               exit();
           }
       } else {
           // Keep existing template file if no new file uploaded
           $current_task = get_task_by_id($pdo, $id);
           if ($current_task != 0 && !empty($current_task['template_file'])) {
               $template_file_path = $current_task['template_file'];
           }
       }

       $old_title = $current_task != 0 && isset($current_task['title']) ? $current_task['title'] : $title;

       // Persist task changes + review info
       $admin_id = $_SESSION['id'];
       $data = [
           'title' => $title,
           'description' => $description,
           'assigned_to' => $assigned_to,
           'due_date' => $due_date,
           'status' => $status,
           'review_comment' => $review_comment,
           'reviewed_by' => $admin_id,
           'template_file' => $template_file_path,
           'google_doc_url' => $google_doc_url,
           'id' => $id,
       ];
       update_task($pdo, $data);

       // Keep related task_chat group linked and renamed when task title changes.
       sync_task_chat_group_link_and_name($pdo, (int)$id, $old_title, $title);

       // Update Assignees (Leader + Members)
       $team_members = isset($_POST['team_members']) ? $_POST['team_members'] : [];
       update_task_assignees($pdo, $id, $assigned_to, $team_members);

       // Send notification to employee about the review result if there is an assignee
       if (!empty($assigned_to) && $assigned_to != 0) {
       	  if ($status === 'completed') {
       	  	  $message = "'$title' has been approved and marked as completed. " . (!empty($review_comment) ? "Comment: $review_comment" : '');
       	  	  $type = 'Task Completed';
       	  } else if ($status === 'rejected') {
       	  	  $message = "'$title' submission was rejected. " . (!empty($review_comment) ? "Comment: $review_comment" : 'Please review and resubmit.');
       	  	  $type = 'Task Rejected';
       	  } else {
       	  	  $message = "'$title' has been updated. " . (!empty($review_comment) ? "Comment: $review_comment" : '');
       	  	  $type = 'Task Updated';
       	  }

       	  $notif_data = array($message, $assigned_to, $type, $id);
       	  insert_notification($pdo, $notif_data);
       }

       $em = "Task updated successfully";
	    header("Location: ../tasks.php?success=" . urlencode($em) . "&open_task=" . urlencode((string)$id));
	    exit();

    
	}
}else {
   $em = "Unknown error occurred";
   header("Location: ../tasks.php?error=" . urlencode($em));
   exit();
}

}else{ 
   $em = "First login";
   header("Location: ../login.php?error=$em");
   exit();
}
