<?php

require_once dirname(__DIR__) . '/mail_config.php';
require_once __DIR__ . '/google_auth.php';

if (!function_exists('google_workspace_client_secret')) {
    function google_workspace_client_secret()
    {
        return trim((string)(getenv('GOOGLE_CLIENT_SECRET') ?: ''));
    }
}

if (!function_exists('google_workspace_is_enabled')) {
    function google_workspace_is_enabled()
    {
        return google_auth_client_id() !== '' && google_workspace_client_secret() !== '';
    }
}

if (!function_exists('google_workspace_redirect_uri')) {
    function google_workspace_redirect_uri()
    {
        return APP_URL . '/app/google-workspace-callback.php';
    }
}

if (!function_exists('google_workspace_scopes')) {
    function google_workspace_scopes()
    {
        return [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/drive.file',
        ];
    }
}

if (!function_exists('google_workspace_supported_file_types')) {
    function google_workspace_supported_file_types()
    {
        return [
            'document' => [
                'label' => 'Google Docs',
                'item_label' => 'Google Doc',
                'mime_type' => 'application/vnd.google-apps.document',
                'fallback_url' => 'https://docs.google.com/document/d/%s/edit',
            ],
            'sheet' => [
                'label' => 'Google Sheets',
                'item_label' => 'Google Sheet',
                'mime_type' => 'application/vnd.google-apps.spreadsheet',
                'fallback_url' => 'https://docs.google.com/spreadsheets/d/%s/edit',
            ],
            'slides' => [
                'label' => 'Google Slides',
                'item_label' => 'Google Slides deck',
                'mime_type' => 'application/vnd.google-apps.presentation',
                'fallback_url' => 'https://docs.google.com/presentation/d/%s/edit',
            ],
        ];
    }
}

if (!function_exists('google_workspace_normalize_file_type')) {
    function google_workspace_normalize_file_type($fileType)
    {
        $fileType = strtolower(trim((string)$fileType));
        $supported = google_workspace_supported_file_types();
        return array_key_exists($fileType, $supported) ? $fileType : 'document';
    }
}

if (!function_exists('google_workspace_file_type_meta')) {
    function google_workspace_file_type_meta($fileType)
    {
        $supported = google_workspace_supported_file_types();
        $normalized = google_workspace_normalize_file_type($fileType);
        return $supported[$normalized];
    }
}

if (!function_exists('google_workspace_build_auth_url')) {
    function google_workspace_build_auth_url($state, $forceConsent = false)
    {
        $params = [
            'client_id' => google_auth_client_id(),
            'redirect_uri' => google_workspace_redirect_uri(),
            'response_type' => 'code',
            'scope' => implode(' ', google_workspace_scopes()),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'state' => (string)$state,
            'prompt' => $forceConsent ? 'consent select_account' : 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('google_workspace_http_request')) {
    function google_workspace_http_request($url, $method = 'GET', array $headers = [], $body = null, $timeoutSeconds = 20)
    {
        $ch = curl_init((string)$url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => max(5, (int)$timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => max(5, (int)$timeoutSeconds),
            CURLOPT_CUSTOMREQUEST => strtoupper((string)$method),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'error' => $error !== '' ? $error : 'Unable to contact Google.',
                'raw' => '',
            ];
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => json_decode((string)$raw, true),
            'error' => '',
            'raw' => (string)$raw,
        ];
    }
}

if (!function_exists('google_workspace_api_error_message')) {
    function google_workspace_api_error_message(array $response, $defaultMessage)
    {
        $defaultMessage = trim((string)$defaultMessage);
        if ($defaultMessage === '') {
            $defaultMessage = 'Google request failed.';
        }

        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $status = (int)($response['status'] ?? 0);
        $rawError = '';

        if (isset($body['error']) && is_array($body['error'])) {
            $errorBlock = $body['error'];
            $rawError = trim((string)($errorBlock['message'] ?? ''));
            if ($rawError === '' && isset($errorBlock['errors'][0]) && is_array($errorBlock['errors'][0])) {
                $rawError = trim((string)($errorBlock['errors'][0]['message'] ?? ''));
            }
            if ($rawError === '' && trim((string)($errorBlock['status'] ?? '')) !== '') {
                $rawError = trim((string)$errorBlock['status']);
            }
        }

        if ($rawError === '') {
            $rawError = trim((string)($body['error_description'] ?? ''));
        }

        if ($rawError === '') {
            $rawError = trim((string)($response['error'] ?? ''));
        }

        $message = $defaultMessage;
        if ($rawError !== '') {
            $message .= ' ' . $rawError;
        }

        $rawLower = strtolower($rawError);
        if (
            strpos($rawLower, 'api has not been used') !== false
            || strpos($rawLower, 'has not been used in project') !== false
            || strpos($rawLower, 'accessnotconfigured') !== false
            || strpos($rawLower, 'service_disabled') !== false
        ) {
            if (strpos($rawLower, 'docs') !== false) {
                $message .= ' Enable the Google Docs API in Google Cloud, then try again.';
            } else {
                $message .= ' Enable the Google Drive API in Google Cloud, then try again.';
            }
        } elseif ($status === 403 && strpos($rawLower, 'insufficient') !== false) {
            $message .= ' Check that the OAuth consent screen includes the requested Google Drive scope.';
        }

        return trim($message);
    }
}

if (!function_exists('google_workspace_exchange_code_for_tokens')) {
    function google_workspace_exchange_code_for_tokens($code)
    {
        $payload = http_build_query([
            'code' => trim((string)$code),
            'client_id' => google_auth_client_id(),
            'client_secret' => google_workspace_client_secret(),
            'redirect_uri' => google_workspace_redirect_uri(),
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
            $body = is_array($response['body']) ? $response['body'] : [];
            return [
                'ok' => false,
                'tokens' => null,
                'error' => google_workspace_api_error_message($response, 'Unable to complete Google authorization.'),
            ];
        }

        return [
            'ok' => true,
            'tokens' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}

if (!function_exists('google_workspace_refresh_access_token')) {
    function google_workspace_refresh_access_token($refreshToken)
    {
        $payload = http_build_query([
            'refresh_token' => trim((string)$refreshToken),
            'client_id' => google_auth_client_id(),
            'client_secret' => google_workspace_client_secret(),
            'grant_type' => 'refresh_token',
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
                'error' => google_workspace_api_error_message($response, 'Unable to refresh Google access.'),
            ];
        }

        return [
            'ok' => true,
            'tokens' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}

if (!function_exists('google_workspace_fetch_userinfo')) {
    function google_workspace_fetch_userinfo($accessToken)
    {
        $response = google_workspace_http_request(
            'https://openidconnect.googleapis.com/v1/userinfo',
            'GET',
            ['Authorization: Bearer ' . trim((string)$accessToken)],
            null,
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'profile' => null,
                'error' => google_workspace_api_error_message($response, 'Unable to read Google account details.'),
            ];
        }

        return [
            'ok' => true,
            'profile' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}

if (!function_exists('google_workspace_create_file')) {
    function google_workspace_create_file($accessToken, $title, $fileType = 'document')
    {
        $typeMeta = google_workspace_file_type_meta($fileType);
        $title = trim((string)$title);
        if ($title === '') {
            $title = 'TaskFlow ' . $typeMeta['item_label'];
        }

        $response = google_workspace_http_request(
            'https://www.googleapis.com/drive/v3/files?fields=id,webViewLink,name',
            'POST',
            [
                'Authorization: Bearer ' . trim((string)$accessToken),
                'Content-Type: application/json',
            ],
            json_encode([
                'name' => $title,
                'mimeType' => $typeMeta['mime_type'],
            ]),
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'file' => null,
                'error' => google_workspace_api_error_message($response, 'Unable to create ' . $typeMeta['item_label'] . '.'),
            ];
        }

        $file = is_array($response['body']) ? $response['body'] : [];
        if (empty($file['id'])) {
            return [
                'ok' => false,
                'file' => null,
                'error' => 'Google did not return a file id.',
            ];
        }

        if (empty($file['webViewLink'])) {
            $file['webViewLink'] = sprintf($typeMeta['fallback_url'], rawurlencode((string)$file['id']));
        }

        return [
            'ok' => true,
            'file' => $file,
            'error' => '',
        ];
    }
}

if (!function_exists('google_workspace_create_document')) {
    function google_workspace_create_document($accessToken, $title)
    {
        $result = google_workspace_create_file($accessToken, $title, 'document');
        return [
            'ok' => (bool)($result['ok'] ?? false),
            'document' => (array)($result['file'] ?? []),
            'error' => (string)($result['error'] ?? ''),
        ];
    }
}

if (!function_exists('google_workspace_seed_document_content')) {
    function google_workspace_seed_document_content($accessToken, $documentId, array $lines)
    {
        $documentId = trim((string)$documentId);
        $lines = array_values(array_filter(array_map(static function ($line) {
            return trim((string)$line);
        }, $lines), static function ($line) {
            return $line !== '';
        }));

        if ($documentId === '' || empty($lines)) {
            return true;
        }

        $response = google_workspace_http_request(
            'https://docs.googleapis.com/v1/documents/' . rawurlencode($documentId) . ':batchUpdate',
            'POST',
            [
                'Authorization: Bearer ' . trim((string)$accessToken),
                'Content-Type: application/json',
            ],
            json_encode([
                'requests' => [
                    [
                        'insertText' => [
                            'location' => ['index' => 1],
                            'text' => implode("\n", $lines) . "\n",
                        ],
                    ],
                ],
            ]),
            20
        );

        return (bool)$response['ok'];
    }
}

if (!function_exists('google_workspace_share_document_with_emails')) {
    function google_workspace_share_document_with_emails($accessToken, $documentId, array $emails)
    {
        $documentId = trim((string)$documentId);
        if ($documentId === '') {
            return [];
        }

        $uniqueEmails = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string)$email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $uniqueEmails[$email] = true;
            }
        }

        $shared = [];
        foreach (array_keys($uniqueEmails) as $email) {
            $response = google_workspace_http_request(
                'https://www.googleapis.com/drive/v3/files/' . rawurlencode($documentId) . '/permissions?sendNotificationEmail=false',
                'POST',
                [
                    'Authorization: Bearer ' . trim((string)$accessToken),
                    'Content-Type: application/json',
                ],
                json_encode([
                    'type' => 'user',
                    'role' => 'writer',
                    'emailAddress' => $email,
                ]),
                20
            );

            if ($response['ok']) {
                $shared[] = $email;
            }
        }

        return $shared;
    }
}

if (!function_exists('google_workspace_share_file_with_emails')) {
    function google_workspace_share_file_with_emails($accessToken, $fileId, array $emails)
    {
        return google_workspace_share_document_with_emails($accessToken, $fileId, $emails);
    }
}

if (!function_exists('google_workspace_build_subtask_doc_title')) {
    function google_workspace_build_subtask_doc_title($taskTitle, $phaseName, $memberName = '')
    {
        $parts = array_values(array_filter([
            trim((string)$taskTitle),
            trim((string)$phaseName),
            trim((string)$memberName),
        ], static function ($value) {
            return $value !== '';
        }));

        $title = implode(' - ', $parts);
        if ($title === '') {
            $title = 'TaskFlow Document';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 180) : substr($title, 0, 180);
    }
}
