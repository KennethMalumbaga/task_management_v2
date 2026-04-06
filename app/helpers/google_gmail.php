<?php

require_once __DIR__ . '/google_workspace.php';

if (!function_exists('google_gmail_required_scope')) {
    function google_gmail_required_scope()
    {
        return 'https://www.googleapis.com/auth/gmail.send';
    }
}

if (!function_exists('google_gmail_is_enabled')) {
    function google_gmail_is_enabled()
    {
        return google_workspace_is_enabled();
    }
}

if (!function_exists('google_gmail_redirect_uri')) {
    function google_gmail_redirect_uri()
    {
        return APP_URL . '/app/google-gmail-callback.php';
    }
}

if (!function_exists('google_gmail_scopes')) {
    function google_gmail_scopes()
    {
        return [
            'openid',
            'email',
            'profile',
            google_gmail_required_scope(),
        ];
    }
}

if (!function_exists('google_gmail_build_auth_url')) {
    function google_gmail_build_auth_url($state, $forceConsent = false)
    {
        $params = [
            'client_id' => google_auth_client_id(),
            'redirect_uri' => google_gmail_redirect_uri(),
            'response_type' => 'code',
            'scope' => implode(' ', google_gmail_scopes()),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'state' => (string)$state,
            'prompt' => $forceConsent ? 'consent select_account' : 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('google_gmail_api_error_message')) {
    function google_gmail_api_error_message(array $response, $defaultMessage)
    {
        $message = google_workspace_api_error_message($response, $defaultMessage);
        $rawBody = is_array($response['body'] ?? null) ? json_encode($response['body']) : (string)($response['raw'] ?? '');
        $rawLower = strtolower((string)$rawBody);

        if (
            strpos($rawLower, 'insufficient') !== false
            || strpos($rawLower, 'permission') !== false
            || strpos($rawLower, 'scope') !== false
        ) {
            if (stripos($message, 'gmail scope') === false) {
                $message .= ' Make sure the Google OAuth consent screen includes the Gmail send scope.';
            }
        }

        if (
            strpos($rawLower, 'mail service not enabled') !== false
            || strpos($rawLower, 'gmail') !== false && strpos($rawLower, 'not been used in project') !== false
        ) {
            $message .= ' Enable the Gmail API in Google Cloud, then try again.';
        }

        return trim($message);
    }
}

if (!function_exists('google_gmail_exchange_code_for_tokens')) {
    function google_gmail_exchange_code_for_tokens($code)
    {
        $payload = http_build_query([
            'code' => trim((string)$code),
            'client_id' => google_auth_client_id(),
            'client_secret' => google_workspace_client_secret(),
            'redirect_uri' => google_gmail_redirect_uri(),
            'grant_type' => 'authorization_code',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = google_workspace_http_request(
            'https://oauth2.googleapis.com/token',
            'POST',
            ['Content-Type: application/x-www-form-urlencoded'],
            $payload,
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'tokens' => null,
                'error' => google_gmail_api_error_message($response, 'Unable to complete Gmail authorization.'),
            ];
        }

        return [
            'ok' => true,
            'tokens' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}

if (!function_exists('google_gmail_base64url_encode')) {
    function google_gmail_base64url_encode($value)
    {
        return rtrim(strtr(base64_encode((string)$value), '+/', '-_'), '=');
    }
}

if (!function_exists('google_gmail_sanitize_header_value')) {
    function google_gmail_sanitize_header_value($value)
    {
        $value = str_replace(["\r", "\n"], ' ', (string)$value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}

if (!function_exists('google_gmail_encode_header')) {
    function google_gmail_encode_header($value)
    {
        $value = google_gmail_sanitize_header_value($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return $value;
    }
}

if (!function_exists('google_gmail_format_address')) {
    function google_gmail_format_address($email, $name = '')
    {
        $email = strtolower(trim((string)$email));
        $name = google_gmail_sanitize_header_value($name);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        if ($name === '') {
            return $email;
        }

        return google_gmail_encode_header($name) . ' <' . $email . '>';
    }
}

if (!function_exists('google_gmail_normalize_recipients')) {
    function google_gmail_normalize_recipients(array $recipients)
    {
        $normalized = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            if (!is_array($recipient)) {
                continue;
            }

            $email = strtolower(trim((string)($recipient['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $normalized[] = [
                'email' => $email,
                'name' => trim((string)($recipient['name'] ?? '')),
            ];
        }

        return $normalized;
    }
}

if (!function_exists('google_gmail_sanitize_filename')) {
    function google_gmail_sanitize_filename($filename)
    {
        $filename = trim((string)$filename);
        $filename = str_replace(["\r", "\n", "\0"], '', $filename);
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = trim($filename, " \t\n\r\0\x0B\"'");

        if ($filename === '') {
            return 'attachment';
        }

        return $filename;
    }
}

if (!function_exists('google_gmail_normalize_mime_type')) {
    function google_gmail_normalize_mime_type($mimeType)
    {
        $mimeType = strtolower(trim((string)$mimeType));
        if ($mimeType === '' || !preg_match('/^[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*$/i', $mimeType)) {
            return 'application/octet-stream';
        }

        return $mimeType;
    }
}

if (!function_exists('google_gmail_normalize_attachments')) {
    function google_gmail_normalize_attachments(array $attachments)
    {
        $normalized = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $content = $attachment['content'] ?? null;
            if (!is_string($content)) {
                continue;
            }

            $normalized[] = [
                'filename' => google_gmail_sanitize_filename((string)($attachment['filename'] ?? 'attachment')),
                'mime_type' => google_gmail_normalize_mime_type((string)($attachment['mime_type'] ?? 'application/octet-stream')),
                'content' => $content,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('google_gmail_quote_header_parameter')) {
    function google_gmail_quote_header_parameter($value)
    {
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$value);
        return '"' . $value . '"';
    }
}

if (!function_exists('google_gmail_generate_boundary')) {
    function google_gmail_generate_boundary($prefix = 'taskflow')
    {
        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $suffix = substr(hash('sha256', uniqid((string)$prefix, true) . microtime(true)), 0, 24);
        }

        return preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$prefix) . '_' . $suffix;
    }
}

if (!function_exists('google_gmail_build_raw_message')) {
    function google_gmail_build_raw_message(array $payload)
    {
        $fromEmail = strtolower(trim((string)($payload['from_email'] ?? '')));
        $fromName = trim((string)($payload['from_name'] ?? ''));
        $replyToEmail = strtolower(trim((string)($payload['reply_to_email'] ?? $fromEmail)));
        $replyToName = trim((string)($payload['reply_to_name'] ?? $fromName));
        $subject = trim((string)($payload['subject'] ?? ''));
        $body = trim((string)($payload['body'] ?? ''));
        $recipients = google_gmail_normalize_recipients((array)($payload['to'] ?? []));
        $attachments = google_gmail_normalize_attachments((array)($payload['attachments'] ?? []));

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || empty($recipients)) {
            return null;
        }

        if ($subject === '') {
            $subject = '(No subject)';
        }

        if ($body === '') {
            $body = ' ';
        }

        $toHeaderParts = [];
        foreach ($recipients as $recipient) {
            $formatted = google_gmail_format_address($recipient['email'], (string)($recipient['name'] ?? ''));
            if ($formatted !== '') {
                $toHeaderParts[] = $formatted;
            }
        }

        if (empty($toHeaderParts)) {
            return null;
        }

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . google_gmail_format_address($fromEmail, $fromName),
            'To: ' . implode(', ', $toHeaderParts),
            'Subject: ' . google_gmail_encode_header($subject),
        ];

        $replyToHeader = google_gmail_format_address($replyToEmail, $replyToName);
        if ($replyToHeader !== '') {
            $headers[] = 'Reply-To: ' . $replyToHeader;
        }

        $encodedBody = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        if (empty($attachments)) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            return implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
        }

        $boundary = google_gmail_generate_boundary('taskflow_mixed');
        $headers[] = 'Content-Type: multipart/mixed; boundary=' . google_gmail_quote_header_parameter($boundary);

        $parts = [];
        $parts[] =
            '--' . $boundary . "\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            $encodedBody;

        foreach ($attachments as $attachment) {
            $filename = google_gmail_sanitize_filename((string)($attachment['filename'] ?? 'attachment'));
            $mimeType = google_gmail_normalize_mime_type((string)($attachment['mime_type'] ?? 'application/octet-stream'));
            $content = (string)($attachment['content'] ?? '');

            $parts[] =
                '--' . $boundary . "\r\n" .
                'Content-Type: ' . $mimeType . '; name=' . google_gmail_quote_header_parameter($filename) . "\r\n" .
                "Content-Transfer-Encoding: base64\r\n" .
                'Content-Disposition: attachment; filename=' . google_gmail_quote_header_parameter($filename) . "\r\n\r\n" .
                rtrim(chunk_split(base64_encode($content), 76, "\r\n"));
        }

        $parts[] = '--' . $boundary . '--';
        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }
}

if (!function_exists('google_gmail_send_message')) {
    function google_gmail_send_message($accessToken, array $payload)
    {
        $rawMessage = google_gmail_build_raw_message($payload);
        if ($rawMessage === null) {
            return [
                'ok' => false,
                'message' => null,
                'error' => 'Email payload is incomplete.',
            ];
        }

        $response = google_workspace_http_request(
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
            'POST',
            [
                'Authorization: Bearer ' . trim((string)$accessToken),
                'Content-Type: application/json',
            ],
            json_encode([
                'raw' => google_gmail_base64url_encode($rawMessage),
            ]),
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'message' => null,
                'error' => google_gmail_api_error_message($response, 'Unable to send the Gmail message.'),
            ];
        }

        return [
            'ok' => true,
            'message' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}
