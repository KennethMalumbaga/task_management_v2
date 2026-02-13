<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Adjust path as needed based on where it was extracted
require '../lib/PHPMailer/src/Exception.php';
require '../lib/PHPMailer/src/PHPMailer.php';
require '../lib/PHPMailer/src/SMTP.php';

include "mail_config.php";

function send_confirmation_email($to_email, $full_name, $password) {
    if (MAIL_USERNAME === '' || MAIL_PASSWORD === '') {
        error_log('Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        //Server settings
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = MAIL_HOST;                              //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = MAIL_USERNAME;                          //SMTP username
        $mail->Password   = MAIL_PASSWORD;                          //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable implicit TLS encryption
        $mail->Port       = MAIL_PORT;                              //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        //Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $full_name);     //Add a recipient

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Welcome to Task Management System';
        $login_url = APP_URL . '/login.php';
        $mail->Body    = "
            <h1>Welcome, $full_name!</h1>
            <p>Thank you for registering with the Task Management System.</p>
            <p>Your account has been successfully created.</p>
            <p><strong>Your Password is:</strong> <span style='font-size: 1.2em; color: #333;'>$password</span></p>
            <p>Please keep this password secure. You can change it after logging in.</p>
            <p>You can now <a href='{$login_url}'>login</a> to your account.</p>
            <br>
            <p>Regards,<br>The Team</p>
        ";
        $mail->AltBody = "Welcome, $full_name! Your password is: $password. Please login.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
    }


function send_password_reset_email($to_email, $full_name, $token) {
    if (MAIL_USERNAME === '' || MAIL_PASSWORD === '') {
        error_log('Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        //Server settings
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        //Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $full_name);

        //Content
        $mail->isHTML(true);
        $url = APP_URL . "/reset-password.php?token=$token";
        
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = "
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
        $mail->AltBody = "Hello $full_name. Reset your password by visiting: $url";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
