<?php 
session_start();
include "../DB_connection.php";

if (isset($_POST['user_name']) && isset($_POST['password']) && isset($_POST['full_name']) && isset($_POST['role'])) {

	function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$user_name = validate_input($_POST['user_name']);
	$password = validate_input($_POST['password']);
	$full_name = validate_input($_POST['full_name']);
	$role = validate_input($_POST['role']);

	if (empty($user_name)) {
		header("Location: ../signup.php?error=Username/Email is required");
		exit();
	}else if (empty($password)) {
		header("Location: ../signup.php?error=Password is required");
		exit();
	}else if (empty($full_name)) {
		header("Location: ../signup.php?error=Full Name is required");
		exit();
	}else {
        // Check if username/email already exists
        $sql = "SELECT username FROM users WHERE username=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_name]);

        if($stmt->rowCount() > 0){
             header("Location: ../signup.php?error=The username/email is already taken");
             exit();
        }else {
            // Hash password
            $password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into DB
            $sql = "INSERT INTO users (full_name, username, password, role) VALUES (?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute([$full_name, $user_name, $password, $role]);

            if ($res) {
            	header("Location: ../login.php?success=Account created successfully. Please login.");
                exit();
            }else {
               header("Location: ../signup.php?error=Unknown error occurred during registration");
               exit();
            }
        }
	}
}else {
	header("Location: ../signup.php?error=error");
	exit();
}
