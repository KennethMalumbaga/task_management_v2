<?php 
session_start();
date_default_timezone_set('Asia/Manila');
include "../DB_connection.php";

if (isset($_POST['new_password']) && isset($_POST['confirm_password']) && isset($_POST['token'])) {
    
    function validate_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $new_password = validate_input($_POST['new_password']);
    $confirm_password = validate_input($_POST['confirm_password']);
    $token = validate_input($_POST['token']);

    if (empty($new_password) || empty($confirm_password)) {
        header("Location: ../reset-password.php?token=$token&error=All fields are required");
        exit();
    } else if ($new_password !== $confirm_password) {
        header("Location: ../reset-password.php?token=$token&error=Passwords do not match");
        exit();
    } else {
        // Validate Token
        $sql = "SELECT * FROM password_resets WHERE token=? AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$token]);

        if ($stmt->rowCount() > 0) {
            $reset = $stmt->fetch();
            $email = $reset['email'];

            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update User Password
            $sql = "UPDATE users SET password=?, must_change_password=FALSE WHERE username=?";
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute([$hashed_password, $email]);

            if ($res) {
                // Delete Token
                $sql = "DELETE FROM password_resets WHERE email=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email]);

                header("Location: ../login.php?success=Password has been reset successfully. Please login.");
                exit();
            } else {
                header("Location: ../reset-password.php?token=$token&error=Unknown error occurred");
                exit();
            }

        } else {
            header("Location: ../reset-password.php?token=$token&error=Invalid or expired token");
            exit();
        }
    }
} else {
    header("Location: ../login.php");
    exit();
}
