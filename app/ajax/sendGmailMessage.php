<?php

session_start();

require_once __DIR__ . '/../../inc/csrf.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid request method.',
    ]);
    exit;
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

if ((string)$_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Only admins can send formal emails.',
    ]);
    exit;
}

if (!csrf_verify('compose_email_action', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid or expired request.',
    ]);
    exit;
}

require_once __DIR__ . '/../../DB_connection.php';
require_once __DIR__ . '/../helpers/google_gmail.php';
require_once __DIR__ . '/../model/user.php';
require_once __DIR__ . '/../model/GoogleWorkspace.php';

$connectUrl = 'app/google-gmail-init.php';

if (!google_gmail_is_enabled()) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Google Gmail integration is not configured yet.',
        'needs_gmail_auth' => false,
    ]);
    exit;
}

$currentUserId = (int)$_SESSION['id'];
$currentUser = get_user_by_id($pdo, $currentUserId);
if (!$currentUser) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Your TaskFlow account could not be found.',
    ]);
    exit;
}

$maxAttachmentCount = 10;
$maxAttachmentBytes = 18 * 1024 * 1024;

$tokenRecord = google_workspace_get_token_record($pdo, $currentUserId);
$refreshToken = trim((string)($tokenRecord['refresh_token'] ?? ''));
$grantedScopes = trim((string)($tokenRecord['scope'] ?? ''));
$hasGmailScope = google_workspace_scope_contains($grantedScopes, google_gmail_required_scope());

if ($refreshToken === '' || !$hasGmailScope) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'Connect your Gmail account before sending formal emails.',
        'needs_gmail_auth' => true,
        'connect_url' => $connectUrl,
    ]);
    exit;
}

$subject = trim((string)($_POST['subject'] ?? ''));
$body = str_replace(["\r\n", "\r"], "\n", (string)($_POST['body'] ?? ''));
$body = trim($body);
$recipientIdsRaw = $_POST['recipient_ids'] ?? [];
$attachments = [];

if (!is_array($recipientIdsRaw)) {
    $recipientIdsRaw = [$recipientIdsRaw];
}

$recipientIds = [];
foreach ($recipientIdsRaw as $rawId) {
    $userId = (int)$rawId;
    if ($userId > 0 && $userId !== $currentUserId) {
        $recipientIds[$userId] = $userId;
    }
}
$recipientIds = array_values($recipientIds);

if (empty($recipientIds)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Select at least one recipient.',
    ]);
    exit;
}

if (
    isset($_FILES['attachments'])
    && is_array($_FILES['attachments']['name'] ?? null)
) {
    $attachmentNames = (array)($_FILES['attachments']['name'] ?? []);
    $attachmentTypes = (array)($_FILES['attachments']['type'] ?? []);
    $attachmentTmpNames = (array)($_FILES['attachments']['tmp_name'] ?? []);
    $attachmentErrors = (array)($_FILES['attachments']['error'] ?? []);
    $attachmentSizes = (array)($_FILES['attachments']['size'] ?? []);
    $attachmentTotalBytes = 0;
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $hasFinfo = is_object($finfo) || is_resource($finfo);

    foreach ($attachmentNames as $index => $originalName) {
        $errorCode = isset($attachmentErrors[$index]) ? (int)$attachmentErrors[$index] : UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            if ($hasFinfo) {
                finfo_close($finfo);
            }

            $uploadMessage = 'Unable to upload one of the attachments.';
            if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                $uploadMessage = 'One of the attachments is too large for the server upload limit.';
            }

            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $uploadMessage,
            ]);
            exit;
        }

        if (count($attachments) >= $maxAttachmentCount) {
            if ($hasFinfo) {
                finfo_close($finfo);
            }

            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'You can attach up to ' . $maxAttachmentCount . ' files per email.',
            ]);
            exit;
        }

        $tmpName = (string)($attachmentTmpNames[$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            if ($hasFinfo) {
                finfo_close($finfo);
            }

            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'One of the uploaded attachments could not be verified.',
            ]);
            exit;
        }

        $size = isset($attachmentSizes[$index]) ? (int)$attachmentSizes[$index] : 0;
        $attachmentTotalBytes += max(0, $size);
        if ($attachmentTotalBytes > $maxAttachmentBytes) {
            if ($hasFinfo) {
                finfo_close($finfo);
            }

            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Attachments must stay under 18 MB total.',
            ]);
            exit;
        }

        $content = @file_get_contents($tmpName);
        if (!is_string($content)) {
            if ($hasFinfo) {
                finfo_close($finfo);
            }

            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'One of the attachments could not be read.',
            ]);
            exit;
        }

        $mimeType = 'application/octet-stream';
        if ($hasFinfo) {
            $detectedMimeType = finfo_file($finfo, $tmpName);
            if (is_string($detectedMimeType) && trim($detectedMimeType) !== '') {
                $mimeType = trim($detectedMimeType);
            }
        } elseif (!empty($attachmentTypes[$index])) {
            $mimeType = trim((string)$attachmentTypes[$index]);
        }

        $attachments[] = [
            'filename' => (string)$originalName,
            'mime_type' => $mimeType,
            'content' => $content,
        ];
    }

    if ($hasFinfo) {
        finfo_close($finfo);
    }
}

if ($subject === '' && $body === '' && empty($attachments)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Add a subject, message, or attachment before sending.',
    ]);
    exit;
}

$recipients = [];
foreach ($recipientIds as $recipientId) {
    $user = get_user_by_id($pdo, $recipientId);
    if (!$user) {
        continue;
    }

    if ((string)($user['role'] ?? '') === 'admin') {
        continue;
    }

    $email = strtolower(trim((string)($user['username'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }

    $recipients[] = [
        'email' => $email,
        'name' => trim((string)($user['full_name'] ?? '')),
    ];
}

if (empty($recipients)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'The selected members do not have valid email addresses.',
    ]);
    exit;
}

$refresh = google_workspace_refresh_access_token($refreshToken);
if (!$refresh['ok']) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'Reconnect Gmail to continue sending formal emails.',
        'needs_gmail_auth' => true,
        'connect_url' => $connectUrl,
    ]);
    exit;
}

$tokens = (array)($refresh['tokens'] ?? []);
$accessToken = trim((string)($tokens['access_token'] ?? ''));
if ($accessToken === '') {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'Reconnect Gmail to continue sending formal emails.',
        'needs_gmail_auth' => true,
        'connect_url' => $connectUrl,
    ]);
    exit;
}

$fromEmail = strtolower(trim((string)($tokenRecord['google_email'] ?? $currentUser['username'] ?? '')));
$fromName = trim((string)($currentUser['full_name'] ?? ''));

$send = google_gmail_send_message($accessToken, [
    'from_email' => $fromEmail,
    'from_name' => $fromName,
    'reply_to_email' => $fromEmail,
    'reply_to_name' => $fromName,
    'to' => $recipients,
    'subject' => $subject,
    'body' => $body,
    'attachments' => $attachments,
]);

if (!$send['ok']) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => (string)($send['error'] ?? 'Unable to send the Gmail message right now.'),
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Formal email sent successfully.',
    'sent_count' => count($recipients),
    'attachment_count' => count($attachments),
]);
