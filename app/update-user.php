<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

if (isset($_POST['user_name']) && isset($_POST['password']) && isset($_POST['full_name']) && $_SESSION['role'] == 'admin') {
	include "../DB_connection.php";

    function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$user_name = validate_input($_POST['user_name']);
	$password = validate_input($_POST['password']);
	$full_name = validate_input($_POST['full_name']);
	$id = validate_input($_POST['id']);


	if (empty($user_name)) {
		$em = "User name is required";
	    header("Location: ../edit-user.php?error=$em&id=$id");
	    exit();
	}else if (empty($password)) {
		$em = "Password is required";
	    header("Location: ../edit-user.php?error=$em&id=$id");
	    exit();
	}else if (empty($full_name)) {
		$em = "Full name is required";
	    header("Location: ../edit-user.php?error=$em&id=$id");
	    exit();
	}else {
    
       include "Model/User.php";
       $is_super_admin = is_super_admin($_SESSION['id'], $pdo);

       // Check if trying to change role
       $target_user = get_user_by_id($pdo, $id);
       $role = $target_user['role'];

       if (isset($_POST['role']) && $is_super_admin) {
           $role = $_POST['role'];
       }

       // Security: Non-super-admin cannot edit an admin
       if ($target_user['role'] == 'admin' && !$is_super_admin && $target_user['id'] != $_SESSION['id']) {
           $em = "Access denied";
           header("Location: ../edit-user.php?error=$em&id=$id");
           exit();
       }

       if ($password == "**********") {
           // Not changing password
           $sql = "UPDATE users SET full_name=?, username=?, role=? WHERE id=?";
           $stmt = $pdo->prepare($sql);
           $stmt->execute([$full_name, $user_name, $role, $id]);
       }else {
           $password = password_hash($password, PASSWORD_DEFAULT);
           $sql = "UPDATE users SET full_name=?, username=?, password=?, role=? WHERE id=?";
           $stmt = $pdo->prepare($sql);
           $stmt->execute([$full_name, $user_name, $password, $role, $id]);
       }

       $em = "User updated successfully";
       header("Location: ../edit-user.php?success=$em&id=$id");
       exit();

    
	}
}else {
   $em = "Unknown error occurred";
   header("Location: ../edit-user.php?error=$em");
   exit();
}

}else{ 
   $em = "First login";
   header("Location: ../edit-user.php?error=$em");
   exit();
}