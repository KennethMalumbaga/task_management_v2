<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "../DB_connection.php";
    include "model/user.php";

    $is_super_admin = is_super_admin($_SESSION['id'], $pdo);

    if ($is_super_admin) {
        $em = "Access Denied: Super Admin cannot add users.";
        header("Location: ../add-user.php?error=$em");
        exit();
    }

    if (isset($_POST['user_name']) && isset($_POST['password']) && isset($_POST['full_name'])) {
        
        function validate_input($data) {
          $data = trim($data);
          $data = stripslashes($data);
          $data = htmlspecialchars($data);
          return $data;
        }

        $user_name = validate_input($_POST['user_name']);
        $password = validate_input($_POST['password']);
        $full_name = validate_input($_POST['full_name']);
        
        // Role handling
        $role = "employee"; // Default
        if ($is_super_admin && isset($_POST['role'])) {
            $role = validate_input($_POST['role']);
        }

        if (empty($user_name)) {
            $em = "User name is required";
            header("Location: ../add-user.php?error=$em");
            exit();
        }else if (empty($password)) {
            $em = "Password is required";
            header("Location: ../add-user.php?error=$em");
            exit();
        }else if (empty($full_name)) {
            $em = "Full name is required";
            header("Location: ../add-user.php?error=$em");
            exit();
        }else {
            // Check if username already exists
            $sql = "SELECT username FROM users WHERE username=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_name]);

            if($stmt->rowCount() > 0){
                 $em = "The username is already taken";
                 header("Location: ../add-user.php?error=$em");
                 exit();
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $data = array($full_name, $user_name, $password_hash, $role);
            insert_user($pdo, $data);

            $em = "User created successfully";
            header("Location: ../add-user.php?success=$em");
            exit();
        }
    }else {
       $em = "Unknown error occurred";
       header("Location: ../add-user.php?error=$em");
       exit();
    }

}else{ 
   $em = "First login";
   header("Location: ../login.php?error=$em");
   exit();
}

