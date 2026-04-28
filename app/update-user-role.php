<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "../DB_connection.php";
    require_once "../inc/tenant.php";
    require_once "../inc/csrf.php";
    include "model/user.php";

    if (isset($_POST['user_id']) && isset($_POST['role'])) {
        if (!csrf_verify('update_user_role_form', $_POST['csrf_token'] ?? null, true)) {
            header("Location: ../user.php?error=" . urlencode("Invalid or expired request. Please refresh and try again."));
            exit();
        }

        $user_id = $_POST['user_id'];
        $role = $_POST['role'];
        $result = update_user_role_as_admin($pdo, $_SESSION['id'], $user_id, $role);
        $key = $result['ok'] ? 'success' : 'error';
        header("Location: ../user.php?$key=" . urlencode($result['message']));
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}
