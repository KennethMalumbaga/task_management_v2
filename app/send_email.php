<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Adjust path as needed based on where it was extracted
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

require_once __DIR__ . "/mail_config.php";

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

function send_workspace_invite_email($to_email, $full_name, $workspace_name, $token, $inviter_name, $role = 'employee') {
    if (MAIL_USERNAME === '' || MAIL_PASSWORD === '') {
        error_log('Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.');
        return false;
    }

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
        $mail->addAddress($to_email, $full_name);

        $mail->isHTML(true);
        $join_url = APP_URL . "/join-workspace.php?token=$token";
        $safe_role = ($role === 'admin') ? 'Admin' : 'Employee';

        $mail->Subject = "You're invited to join {$workspace_name} on TaskFlow";
        $mail->Body    = "
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
        $mail->AltBody = "You were invited to join {$workspace_name}. Open this link: {$join_url}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Workspace invite email failed: {$mail->ErrorInfo}");
        return false;
    }
}

function send_login_verification_code_email($to_email, $full_name, $code) {
    if (MAIL_USERNAME === '' || MAIL_PASSWORD === '') {
        error_log('Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.');
        return false;
    }

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
        $mail->addAddress($to_email, $full_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your TaskFlow verification code';
        $mail->Body    = "
            <h2>Login Verification</h2>
            <p>Hello {$full_name},</p>
            <p>Use this 4-digit code to finish your login:</p>
            <p style='font-size: 28px; letter-spacing: 8px; font-weight: 700; margin: 16px 0;'>{$code}</p>
            <p>This code expires in 10 minutes.</p>
            <p>If you did not request this login, you can ignore this email.</p>
            <br>
            <p>Regards,<br>The Team</p>
        ";
        $mail->AltBody = "Hello {$full_name}. Your TaskFlow verification code is {$code}. It expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Verification email failed: {$mail->ErrorInfo}");
        return false;
    }
}

function send_meeting_reminder_email($to_email, $full_name, array $meetingData) {
    if (MAIL_USERNAME === '' || MAIL_PASSWORD === '') {
        error_log('Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.');
        return false;
    }

    $title = trim((string)($meetingData['title'] ?? ''));
    if ($title === '') {
        $title = 'TaskFlow Meeting';
    }

    $meetingDate = trim((string)($meetingData['meeting_date'] ?? ''));
    $startTime = trim((string)($meetingData['start_time'] ?? ''));
    $endTime = trim((string)($meetingData['end_time'] ?? ''));
    $timezone = trim((string)($meetingData['timezone'] ?? 'Asia/Manila'));
    $description = trim((string)($meetingData['description'] ?? ''));
    $meetUrl = trim((string)($meetingData['google_meet_url'] ?? ''));
    $calendarUrl = trim((string)($meetingData['google_calendar_url'] ?? ''));

    $dateLabel = $meetingDate;
    if ($meetingDate !== '') {
        $dateTs = strtotime($meetingDate);
        if ($dateTs !== false) {
            $dateLabel = date('F j, Y', $dateTs);
        }
    }

    $timeLabel = trim($startTime . ($endTime !== '' ? ' - ' . $endTime : ''));
    if ($startTime !== '' && $endTime !== '') {
        $startTs = strtotime($startTime);
        $endTs = strtotime($endTime);
        if ($startTs !== false && $endTs !== false) {
            $timeLabel = date('g:i A', $startTs) . ' - ' . date('g:i A', $endTs);
        }
    }

    $safeName = htmlspecialchars(trim((string)$full_name) !== '' ? (string)$full_name : 'there', ENT_QUOTES);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES);
    $safeDate = htmlspecialchars($dateLabel, ENT_QUOTES);
    $safeTime = htmlspecialchars($timeLabel, ENT_QUOTES);
    $safeTimezone = htmlspecialchars($timezone, ENT_QUOTES);
    $safeDescription = nl2br(htmlspecialchars($description, ENT_QUOTES));
    $safeMeetUrl = htmlspecialchars($meetUrl, ENT_QUOTES);
    $safeCalendarUrl = htmlspecialchars($calendarUrl, ENT_QUOTES);

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
        $mail->addAddress($to_email, trim((string)$full_name));

        $mail->isHTML(true);
        $mail->Subject = 'Meeting Reminder: ' . $title;
        $mail->Body = "
            <h2>Meeting Reminder</h2>
            <p>Hello {$safeName},</p>
            <p>This is your TaskFlow reminder that <strong>{$safeTitle}</strong> starts in about 1 hour.</p>
            <p><strong>Date:</strong> {$safeDate}<br>
            <strong>Time:</strong> {$safeTime}<br>
            <strong>Timezone:</strong> {$safeTimezone}</p>
            " . ($description !== '' ? "<p><strong>Notes:</strong><br>{$safeDescription}</p>" : "") . "
            " . ($meetUrl !== '' ? "<p><a href=\"{$safeMeetUrl}\">Join Google Meet</a></p>" : "") . "
            " . ($calendarUrl !== '' ? "<p><a href=\"{$safeCalendarUrl}\">Open in Google Calendar</a></p>" : "") . "
            <br>
            <p>Regards,<br>The Team</p>
        ";
        $mail->AltBody =
            "Hello {$full_name},\n\n" .
            "Reminder: {$title} starts in about 1 hour.\n" .
            ($dateLabel !== '' ? "Date: {$dateLabel}\n" : '') .
            ($timeLabel !== '' ? "Time: {$timeLabel}\n" : '') .
            "Timezone: {$timezone}\n" .
            ($description !== '' ? "Notes: {$description}\n" : '') .
            ($meetUrl !== '' ? "Join Google Meet: {$meetUrl}\n" : '') .
            ($calendarUrl !== '' ? "Open in Google Calendar: {$calendarUrl}\n" : '');

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Meeting reminder email failed: {$mail->ErrorInfo}");
        return false;
    }
}
?>
