<?php 
session_start();
date_default_timezone_set('Asia/Manila');
include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/input.php";

if (isset($_POST['new_password']) && isset($_POST['confirm_password']) && isset($_POST['token'])) {
    $rawToken = trim((string)($_POST['token'] ?? ''));
    if (!csrf_verify('do_reset_password_form', $_POST['csrf_token'] ?? null, true)) {
        header("Location: ../reset-password.php?token=" . urlencode($rawToken) . "&error=" . urlencode("Invalid or expired request. Please refresh and try again."));
        exit();
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
            $reset_org_id = isset($reset['organization_id']) ? (int)$reset['organization_id'] : 0;

            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update User Password
            $sql = "UPDATE users SET password=?, must_change_password=FALSE WHERE username=?";
            $params = [$hashed_password, $email];
            if (tenant_column_exists($pdo, 'users', 'organization_id') && $reset_org_id > 0) {
                $sql .= " AND organization_id=?";
                $params[] = $reset_org_id;
            }
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute($params);

            if ($res) {
                // Delete Token
                $sql = "DELETE FROM password_resets WHERE email=?";
                $params = [$email];
                if (tenant_column_exists($pdo, 'password_resets', 'organization_id') && $reset_org_id > 0) {
                    $sql .= " AND organization_id=?";
                    $params[] = $reset_org_id;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

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
