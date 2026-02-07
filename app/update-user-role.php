<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "../DB_connection.php";
    include "model/user.php";

    $is_super_admin = is_super_admin($_SESSION['id'], $pdo);

    if (!$is_super_admin) {
        header("Location: ../user.php?error=Access Denied");
        exit();
    }

    if (isset($_POST['user_id']) && isset($_POST['role'])) {
        $user_id = $_POST['user_id'];
        $role = $_POST['role'];

        // Prevent super admin from changing their own role (optional but safe)
        $target_user = get_user_by_id($pdo, $user_id);
        if ($target_user['username'] == 'admin') {
            header("Location: ../user.php?error=Cannot change Super Admin role");
            exit();
        }

        if ($role == 'admin' || $role == 'employee') {
            $sql = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute([$role, $user_id]);

            if ($res) {
                header("Location: ../user.php?success=Role updated successfully");
            } else {
                header("Location: ../user.php?error=Failed to update role");
            }
        } else {
            header("Location: ../user.php?error=Invalid role");
        }
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}

