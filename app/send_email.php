<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Adjust path as needed based on where it was extracted
require '../lib/PHPMailer/src/Exception.php';
require '../lib/PHPMailer/src/PHPMailer.php';
require '../lib/PHPMailer/src/SMTP.php';

include "mail_config.php";

if (!function_exists('tm_mail_is_railway_runtime')) {
    function tm_mail_is_railway_runtime()
    {
        $env = getenv('RAILWAY_ENVIRONMENT');
        return $env !== false && trim((string)$env) !== '';
    }
}

if (!function_exists('tm_mail_should_use_resend')) {
    function tm_mail_should_use_resend()
    {
        $driver = strtolower(trim((string)MAIL_DRIVER));
        if ($driver === 'smtp') {
            return false;
        }
        if ($driver === 'resend') {
            return RESEND_API_KEY !== '';
        }

        return tm_mail_is_railway_runtime() && RESEND_API_KEY !== '';
    }
}

if (!function_exists('tm_mail_missing_config_message')) {
    function tm_mail_missing_config_message()
    {
        if (tm_mail_should_use_resend()) {
            return 'Mail not configured: set RESEND_API_KEY environment variable for Railway email delivery.';
        }

        return 'Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.';
    }
}

if (!function_exists('tm_mail_is_configured')) {
    function tm_mail_is_configured()
    {
        if (tm_mail_should_use_resend()) {
            return RESEND_API_KEY !== '';
        }

        return MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
    }
}

if (!function_exists('tm_mail_build_from_header')) {
    function tm_mail_build_from_header()
    {
        $custom = trim((string)RESEND_FROM_ADDRESS);
        if ($custom !== '') {
            return $custom;
        }

        $name = trim((string)MAIL_FROM_NAME);
        if ($name === '') {
            return 'onboarding@resend.dev';
        }

        return sprintf('%s <%s>', $name, 'onboarding@resend.dev');
    }
}

if (!function_exists('tm_mail_extract_error_message')) {
    function tm_mail_extract_error_message($responseBody, $statusCode = 0)
    {
        $responseBody = trim((string)$responseBody);
        if ($responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                foreach (['message', 'error'] as $key) {
                    if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                        return $decoded[$key];
                    }
                }
            }
        }

        if ($responseBody !== '') {
            return $responseBody;
        }

        if ($statusCode > 0) {
            return 'HTTP ' . $statusCode;
        }

        return 'Unknown email transport error.';
    }
}

if (!function_exists('tm_mail_send_via_resend')) {
    function tm_mail_send_via_resend($toEmail, $subject, $htmlBody, $textBody, $errorPrefix)
    {
        $payload = [
            'from' => tm_mail_build_from_header(),
            'to' => [trim((string)$toEmail)],
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            error_log($errorPrefix . ': Failed to encode Resend payload.');
            return false;
        }

        $responseBody = '';
        $statusCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . RESEND_API_KEY,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 30,
            ]);

            $responseBody = (string)curl_exec($ch);
            if ($responseBody === '' && curl_errno($ch)) {
                error_log($errorPrefix . ': ' . curl_error($ch));
                curl_close($ch);
                return false;
            }

            $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", [
                        'Authorization: Bearer ' . RESEND_API_KEY,
                        'Content-Type: application/json',
                    ]),
                    'content' => $body,
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ]);

            $responseBody = (string)file_get_contents('https://api.resend.com/emails', false, $context);
            $headers = $http_response_header ?? [];
            if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', (string)$headers[0], $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return true;
        }

        error_log($errorPrefix . ': ' . tm_mail_extract_error_message($responseBody, $statusCode));
        return false;
    }
}

if (!function_exists('tm_mail_send_via_smtp')) {
    function tm_mail_send_via_smtp($toEmail, $toName, $subject, $htmlBody, $textBody, $errorPrefix)
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log($errorPrefix . ": {$mail->ErrorInfo}");
            return false;
        }
    }
}

if (!function_exists('tm_send_app_mail')) {
    function tm_send_app_mail($toEmail, $toName, $subject, $htmlBody, $textBody, $errorPrefix)
    {
        if (!tm_mail_is_configured()) {
            error_log(tm_mail_missing_config_message());
            return false;
        }

        if (tm_mail_should_use_resend()) {
            return tm_mail_send_via_resend($toEmail, $subject, $htmlBody, $textBody, $errorPrefix);
        }

        return tm_mail_send_via_smtp($toEmail, $toName, $subject, $htmlBody, $textBody, $errorPrefix);
    }
}

function send_confirmation_email($to_email, $full_name, $password) {
    $login_url = APP_URL . '/login.php';
    $htmlBody = "
        <h1>Welcome, $full_name!</h1>
        <p>Thank you for registering with the Task Management System.</p>
        <p>Your account has been successfully created.</p>
        <p><strong>Your Password is:</strong> <span style='font-size: 1.2em; color: #333;'>$password</span></p>
        <p>Please keep this password secure. You can change it after logging in.</p>
        <p>You can now <a href='{$login_url}'>login</a> to your account.</p>
        <br>
        <p>Regards,<br>The Team</p>
    ";
    $textBody = "Welcome, $full_name! Your password is: $password. Please login.";

    return tm_send_app_mail(
        $to_email,
        $full_name,
        'Welcome to Task Management System',
        $htmlBody,
        $textBody,
        'Message could not be sent. Mailer Error'
    );
}


function send_password_reset_email($to_email, $full_name, $token) {
    $url = APP_URL . "/reset-password.php?token=$token";
    $htmlBody = "
        <h1>Password Reset</h1>
        <p>Hello $full_name,</p>
        <p>We received a request to reset your password.</p>
        <p>Click the link below to reset it:</p>
        <p><a href='$url'>$url</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you did not request this, please ignore this email.</p>
        <br>
        <p>Regards,<br>The Team</p>
    ";
    $textBody = "Hello $full_name. Reset your password by visiting: $url";

    return tm_send_app_mail(
        $to_email,
        $full_name,
        'Password Reset Request',
        $htmlBody,
        $textBody,
        'Message could not be sent. Mailer Error'
    );
}

function send_workspace_invite_email($to_email, $full_name, $workspace_name, $token, $inviter_name, $role = 'employee') {
    $join_url = APP_URL . "/join-workspace.php?token=$token";
    $safe_role = ($role === 'admin') ? 'Admin' : 'Employee';
    $htmlBody = "
        <h2>Workspace Invitation</h2>
        <p>Hello {$full_name},</p>
        <p><strong>{$inviter_name}</strong> invited you to join <strong>{$workspace_name}</strong> in TaskFlow.</p>
        <p>Your role: <strong>{$safe_role}</strong></p>
        <p>Click this link to set your password and activate your account:</p>
        <p><a href='{$join_url}'>{$join_url}</a></p>
        <p>This invitation link expires in 7 days.</p>
        <br>
        <p>Regards,<br>The Team</p>
    ";
    $textBody = "You were invited to join {$workspace_name}. Open this link: {$join_url}";

    return tm_send_app_mail(
        $to_email,
        $full_name,
        "You're invited to join {$workspace_name} on TaskFlow",
        $htmlBody,
        $textBody,
        'Workspace invite email failed'
    );
}

function send_login_verification_code_email($to_email, $full_name, $code) {
    $htmlBody = "
        <h2>Login Verification</h2>
        <p>Hello {$full_name},</p>
        <p>Use this 4-digit code to finish your login:</p>
        <p style='font-size: 28px; letter-spacing: 8px; font-weight: 700; margin: 16px 0;'>{$code}</p>
        <p>This code expires in 10 minutes.</p>
        <p>If you did not request this login, you can ignore this email.</p>
        <br>
        <p>Regards,<br>The Team</p>
    ";
    $textBody = "Hello {$full_name}. Your TaskFlow verification code is {$code}. It expires in 10 minutes.";

    return tm_send_app_mail(
        $to_email,
        $full_name,
        'Your TaskFlow verification code',
        $htmlBody,
        $textBody,
        'Verification email failed'
    );
}
?>
