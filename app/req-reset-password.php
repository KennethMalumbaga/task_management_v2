<?php 
session_start();
date_default_timezone_set('Asia/Manila');
include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
include "send_email.php";
require_once __DIR__ . "/helpers/input.php";

if (isset($_POST['email'])) {
    if (!csrf_verify('req_reset_password_form', $_POST['csrf_token'] ?? null, true)) {
        header("Location: ../forgot-password.php?error=" . urlencode("Invalid or expired request. Please refresh and try again."));
        exit();
    }
    $email = validate_input($_POST['email']);

    if (empty($email)) {
        header("Location: ../forgot-password.php?error=Email is required");
        exit();
    } else {
        // Check if email exists
        $sql = "SELECT * FROM users WHERE username=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            $full_name = $user['full_name'];
            
            // Generate Token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Insert request into DB
            if (tenant_column_exists($pdo, 'password_resets', 'organization_id') && !empty($user['organization_id'])) {
                $sql = "INSERT INTO password_resets (email, token, expires_at, organization_id) VALUES (?,?,?,?)";
                $stmt = $pdo->prepare($sql);
                $res = $stmt->execute([$email, $token, $expires_at, (int)$user['organization_id']]);
            } else {
                $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)";
                $stmt = $pdo->prepare($sql);
                $res = $stmt->execute([$email, $token, $expires_at]);
            }

            if ($res) {
                // Send Email
                if (send_password_reset_email($email, $full_name, $token)) {
                    $msg = "A password reset link has been sent to your email.";
                    header("Location: ../forgot-password.php?success=".urlencode($msg));
                    exit();
                } else {
                    $msg = "Failed to send email. Please try again later.";
                    header("Location: ../forgot-password.php?error=".urlencode($msg));
                    exit();
                }
            } else {
                header("Location: ../forgot-password.php?error=Unknown error occurred");
                exit();
            }

        } else {
            // Email not found - For security, we might want to say "If your email exists..." 
            // but for this internal app, "Email not found" is clearer for debugging/users.
            // Or stick to generic to avoid enumeration.
            // User likely prefers clear error.
            header("Location: ../forgot-password.php?error=Email not found");
            exit();
        }
    }
} else {
    header("Location: ../forgot-password.php");
    exit();
}
