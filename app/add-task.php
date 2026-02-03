<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

if (isset($_POST['title']) && isset($_POST['description']) && isset($_POST['leader_id']) && $_SESSION['role'] == 'admin' && isset($_POST['due_date'])) {
	include "../DB_connection.php";

    function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$title = validate_input($_POST['title']);
	$description = validate_input($_POST['description']);
	$leader_id = validate_input($_POST['leader_id']);
	$due_date = validate_input($_POST['due_date']);
	$member_ids = isset($_POST['member_ids']) ? $_POST['member_ids'] : [];
	// Clean member_ids array
	$member_ids = array_filter(array_map('intval', $member_ids), function($id) { return $id > 0; });

	if (empty($title)) {
		$em = "Title is required";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else if (empty($description)) {
		$em = "Description is required";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else if ($leader_id == 0) {
		$em = "Select Leader";
	    header("Location: ../create_task.php?error=$em");
	    exit();
	}else {
    
       include_once "model/Task.php";
       include_once "model/Notification.php";

       // Handle template file upload (optional)
       $template_file_path = null;
       if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] == UPLOAD_ERR_OK) {
           $file = $_FILES['template_file'];
           
           // Validate file type
           $allowed_extensions = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip','txt'];
           $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
           
           if (!in_array($file_ext, $allowed_extensions)) {
               $em = "Invalid template file type. Allowed: pdf, doc, docx, xls, xlsx, png, jpg, jpeg, zip, txt.";
               header("Location: ../create_task.php?error=$em");
               exit();
           }
           
           // Max 10MB
           if ($file['size'] > 10 * 1024 * 1024) {
               $em = "Template file is too large. Maximum allowed size is 10MB.";
               header("Location: ../create_task.php?error=$em");
               exit();
           }
           
           // Ensure uploads directory exists
           $upload_dir = "../uploads";
           if (!is_dir($upload_dir)) {
               mkdir($upload_dir, 0777, true);
           }
           
           // Generate unique filename
           $new_filename = "template_" . time() . "_" . basename($file['name']);
           $destination = $upload_dir . "/" . $new_filename;
           
           if (move_uploaded_file($file['tmp_name'], $destination)) {
               $template_file_path = "uploads/" . $new_filename;
           } else {
               $em = "Failed to upload template file. Please try again.";
               header("Location: ../create_task.php?error=$em");
               exit();
           }
       }

       // Insert task (using leader_id for assigned_to for backward compatibility, but we'll use task_assignees)
       $data = array($title, $description, $leader_id, $due_date, $template_file_path);
       $task_id = insert_task($pdo, $data);

       // Insert task assignees (leader + members)
       insert_task_assignees($pdo, $task_id, $leader_id, $member_ids);

       // Notify leader
       $notif_data = array("'$title' has been assigned to you as leader. Please review and start working on it", $leader_id, 'New Task Assigned', $task_id);
       insert_notification($pdo, $notif_data);

       // Notify all members
       foreach ($member_ids as $member_id) {
           $notif_data = array("'$title' has been assigned to you. Please review and start working on it", $member_id, 'New Task Assigned', $task_id);
           insert_notification($pdo, $notif_data);
       }

       $em = "Task created successfully";
	    header("Location: ../tasks.php?success=$em");
	    exit();

    
	}
}else {
   $em = "Unknown error occurred";
   header("Location: ../create_task.php?error=$em");
   exit();
}

}else{ 
   $em = "First login";
   header("Location: ../create_task.php?error=$em");
   exit();
}