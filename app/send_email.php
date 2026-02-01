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
        $mail->Body    = "
            <h1>Welcome, $full_name!</h1>
            <p>Thank you for registering with the Task Management System.</p>
            <p>Your account has been successfully created.</p>
            <p><strong>Your Password is:</strong> <span style='font-size: 1.2em; color: #333;'>$password</span></p>
            <p>Please keep this password secure. You can change it after logging in.</p>
            <p>You can now <a href='http://localhost/Task_Management/login.php'>login</a> to your account.</p>
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
?>
