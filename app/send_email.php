<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Adjust path as needed based on where it was extracted
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

require_once __DIR__ . "/mail_config.php";

if (!function_exists('tm_mail_missing_config_message')) {
    function tm_mail_missing_config_message()
    {
        return 'Mail not configured: set MAIL_USERNAME and MAIL_PASSWORD environment variables.';
    }
}

if (!function_exists('tm_mail_is_configured')) {
    function tm_mail_is_configured()
    {
        return MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
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

function send_workspace_subscription_reminder_email($to_email, $full_name, $workspace_name, $ends_at_display, $is_trial = false) {
    $safeName = trim((string)$full_name) !== '' ? (string)$full_name : 'Workspace Owner';
    $safeWorkspace = trim((string)$workspace_name) !== '' ? (string)$workspace_name : 'your workspace';
    $safeEndsAt = trim((string)$ends_at_display) !== '' ? (string)$ends_at_display : 'the scheduled billing end date';
    $billingUrl = APP_URL . '/workspace-billing.php';
    $subjectLabel = $is_trial ? 'trial' : 'subscription';
    $safeNameHtml = htmlspecialchars($safeName, ENT_QUOTES);
    $safeWorkspaceHtml = htmlspecialchars($safeWorkspace, ENT_QUOTES);
    $safeEndsAtHtml = htmlspecialchars($safeEndsAt, ENT_QUOTES);
    $billingUrlHtml = htmlspecialchars($billingUrl, ENT_QUOTES);

    $htmlBody = "
        <h2>Workspace Billing Reminder</h2>
        <p>Hello {$safeNameHtml},</p>
        <p>Your <strong>{$safeWorkspaceHtml}</strong> {$subjectLabel} is nearing its end.</p>
        <p><strong>Ends on:</strong> {$safeEndsAtHtml}</p>
        <p>Please renew before the deadline to avoid interrupted workspace access for your team.</p>
        <p><a href='{$billingUrlHtml}'>Open Workspace Billing</a></p>
        <br>
        <p>Regards,<br>The Team</p>
    ";
    $textBody =
        "Hello {$safeName},\n\n" .
        "Your {$safeWorkspace} {$subjectLabel} is nearing its end.\n" .
        "Ends on: {$safeEndsAt}\n" .
        "Renew here: {$billingUrl}\n\n" .
        "Regards,\nThe Team";

    return tm_send_app_mail(
        $to_email,
        $safeName,
        "TaskFlow {$subjectLabel} reminder for {$safeWorkspace}",
        $htmlBody,
        $textBody,
        'Workspace subscription reminder email failed'
    );
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
