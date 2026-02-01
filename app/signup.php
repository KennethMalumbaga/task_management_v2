<?php 
session_start();
include "../DB_connection.php";

if (isset($_POST['user_name']) && isset($_POST['full_name'])) {
	function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$user_name = validate_input($_POST['user_name']);
	$full_name = validate_input($_POST['full_name']);
    $role = "employee"; // Force role to Employee

    // Generate Random Password
    $generated_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%"), 0, 10);

	if (empty($user_name)) {
		header("Location: ../signup.php?error=Username/Email is required");
		exit();
	}else if (!filter_var($user_name, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../signup.php?error=Invalid email address");
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
            $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);

            // Insert into DB
            $sql = "INSERT INTO users (full_name, username, password, role, must_change_password) VALUES (?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute([$full_name, $user_name, $password_hash, $role, "true"]);

            if ($res) {
                // Send Confirmation Email
                include_once "send_email.php";
                if(send_confirmation_email($user_name, $full_name, $generated_password)) {
                    $msg = "Account created successfully. A confirmation email with your password has been sent to $user_name.";
                    header("Location: ../login.php?success=".urlencode($msg));
                    exit();
                } else {
                    // Email failed to send. internal rollback.
                    $sql = "DELETE FROM users WHERE username=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_name]);

                    $msg = "Registration failed: Could not send confirmation email to $user_name. Please ensure your email is valid.";
                    header("Location: ../signup.php?error=".urlencode($msg));
                    exit();
                }
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
