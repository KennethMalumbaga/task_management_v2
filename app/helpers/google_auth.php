<?php

require_once dirname(__DIR__) . '/mail_config.php';

if (!function_exists('google_auth_client_id')) {
    function google_auth_client_id()
    {
        return trim((string)(getenv('GOOGLE_CLIENT_ID') ?: ''));
    }
}

if (!function_exists('google_auth_is_enabled')) {
    function google_auth_is_enabled()
    {
        return google_auth_client_id() !== '';
    }
}

if (!function_exists('google_auth_verify_gsi_csrf')) {
    function google_auth_verify_gsi_csrf(array $post, array $cookies)
    {
        $cookieToken = trim((string)($cookies['g_csrf_token'] ?? ''));
        $bodyToken = trim((string)($post['g_csrf_token'] ?? ''));

        if ($cookieToken === '' || $bodyToken === '') {
            return false;
        }

        return hash_equals($cookieToken, $bodyToken);
    }
}

if (!function_exists('google_auth_email_is_authoritative')) {
    function google_auth_email_is_authoritative($email, $emailVerified, $hostedDomain = '')
    {
        $email = strtolower(trim((string)$email));
        $hostedDomain = trim((string)$hostedDomain);
        $emailVerified = filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $emailVerified = $emailVerified === null ? !empty($emailVerified) : $emailVerified;

        if ($email === '' || !$emailVerified) {
            return false;
        }

        if (str_ends_with($email, '@gmail.com')) {
            return true;
        }

        return $hostedDomain !== '';
    }
}

if (!function_exists('google_auth_base64url_decode')) {
    function google_auth_base64url_decode($input)
    {
        $input = strtr((string)$input, '-_', '+/');
        $padding = strlen($input) % 4;
        if ($padding > 0) {
            $input .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($input, true);
        return $decoded === false ? null : $decoded;
    }
}

if (!function_exists('google_auth_http_get')) {
    function google_auth_http_get($url, $timeoutSeconds = 10)
    {
        $ch = curl_init((string)$url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => max(1, (int)$timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => max(1, (int)$timeoutSeconds),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'ok' => false,
                'status' => 0,
                'headers' => [],
                'body' => '',
                'error' => $error !== '' ? $error : 'Unable to contact Google.',
            ];
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);
        $headers = [];

        foreach (preg_split("/\r\n|\n|\r/", (string)$rawHeaders) as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim((string)$name));
            $value = trim((string)$value);
            if ($name === '') {
                continue;
            }
            $headers[$name] = $value;
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'headers' => $headers,
            'body' => (string)$body,
            'error' => '',
        ];
    }
}

if (!function_exists('google_auth_cert_cache_path')) {
    function google_auth_cert_cache_path()
    {
        return dirname(__DIR__, 2) . '/tmp/google_identity_certs_cache.json';
    }
}

if (!function_exists('google_auth_cache_ttl_from_headers')) {
    function google_auth_cache_ttl_from_headers(array $headers)
    {
        $cacheControl = (string)($headers['cache-control'] ?? '');
        if (preg_match('/max-age=(\d+)/i', $cacheControl, $matches)) {
            return max(60, (int)$matches[1]);
        }

        return 3600;
    }
}

if (!function_exists('google_auth_fetch_certificates')) {
    function google_auth_fetch_certificates($forceRefresh = false)
    {
        static $memoryCache = null;
        static $memoryExpiry = 0;

        $now = time();
        if (!$forceRefresh && is_array($memoryCache) && $memoryExpiry > $now) {
            return ['ok' => true, 'certs' => $memoryCache];
        }

        $cachePath = google_auth_cert_cache_path();
        if (!$forceRefresh && is_file($cachePath)) {
            $cachedRaw = @file_get_contents($cachePath);
            $cached = json_decode((string)$cachedRaw, true);
            if (is_array($cached) && !empty($cached['expires_at']) && (int)$cached['expires_at'] > $now && !empty($cached['certs']) && is_array($cached['certs'])) {
                $memoryCache = $cached['certs'];
                $memoryExpiry = (int)$cached['expires_at'];
                return ['ok' => true, 'certs' => $memoryCache];
            }
        }

        $response = google_auth_http_get('https://www.googleapis.com/oauth2/v1/certs', 10);
        if (!$response['ok']) {
            return [
                'ok' => false,
                'certs' => [],
                'error' => $response['error'] !== '' ? $response['error'] : 'Unable to fetch Google certificates.',
            ];
        }

        $certs = json_decode((string)$response['body'], true);
        if (!is_array($certs) || empty($certs)) {
            return [
                'ok' => false,
                'certs' => [],
                'error' => 'Google certificates response was invalid.',
            ];
        }

        $ttl = google_auth_cache_ttl_from_headers((array)$response['headers']);
        $expiresAt = $now + $ttl;
        $memoryCache = $certs;
        $memoryExpiry = $expiresAt;

        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        @file_put_contents($cachePath, json_encode([
            'expires_at' => $expiresAt,
            'certs' => $certs,
        ], JSON_PRETTY_PRINT));

        return ['ok' => true, 'certs' => $certs];
    }
}

if (!function_exists('google_auth_verify_id_token')) {
    function google_auth_verify_id_token($idToken, $expectedClientId = null)
    {
        $expectedClientId = trim((string)($expectedClientId ?: google_auth_client_id()));
        if ($expectedClientId === '') {
            return ['ok' => false, 'claims' => null, 'error' => 'Google login is not configured.'];
        }

        $token = trim((string)$idToken);
        if ($token === '') {
            return ['ok' => false, 'claims' => null, 'error' => 'Missing Google credential.'];
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google credential format is invalid.'];
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $decodedHeader = google_auth_base64url_decode($encodedHeader);
        $decodedPayload = google_auth_base64url_decode($encodedPayload);
        $signature = google_auth_base64url_decode($encodedSignature);

        if ($decodedHeader === null || $decodedPayload === null || $signature === null) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google credential could not be decoded.'];
        }

        $header = json_decode($decodedHeader, true);
        $payload = json_decode($decodedPayload, true);
        if (!is_array($header) || !is_array($payload)) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google credential payload is invalid.'];
        }

        if (strtoupper((string)($header['alg'] ?? '')) !== 'RS256') {
            return ['ok' => false, 'claims' => null, 'error' => 'Unsupported Google token algorithm.'];
        }

        $kid = trim((string)($header['kid'] ?? ''));
        if ($kid === '') {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token key identifier is missing.'];
        }

        $certResult = google_auth_fetch_certificates(false);
        if (!$certResult['ok']) {
            return ['ok' => false, 'claims' => null, 'error' => (string)($certResult['error'] ?? 'Unable to verify Google login.')];
        }

        $certs = (array)$certResult['certs'];
        if (!isset($certs[$kid])) {
            $certResult = google_auth_fetch_certificates(true);
            $certs = $certResult['ok'] ? (array)$certResult['certs'] : [];
        }

        $certificate = (string)($certs[$kid] ?? '');
        if ($certificate === '') {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token signing certificate was not found.'];
        }

        $publicKey = openssl_pkey_get_public($certificate);
        if ($publicKey === false) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google signing certificate could not be loaded.'];
        }

        $verified = openssl_verify($encodedHeader . '.' . $encodedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token signature verification failed.'];
        }

        $issuer = trim((string)($payload['iss'] ?? ''));
        if ($issuer !== 'accounts.google.com' && $issuer !== 'https://accounts.google.com') {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token issuer is invalid.'];
        }

        $audience = $payload['aud'] ?? '';
        $audiences = is_array($audience) ? $audience : [$audience];
        $audiences = array_map(static function ($value) {
            return trim((string)$value);
        }, $audiences);
        if (!in_array($expectedClientId, $audiences, true)) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token audience does not match this app.'];
        }

        $now = time();
        $clockSkew = 300;
        $exp = isset($payload['exp']) ? (int)$payload['exp'] : 0;
        $nbf = isset($payload['nbf']) ? (int)$payload['nbf'] : 0;
        if ($exp <= 0 || $exp < ($now - $clockSkew)) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token has expired.'];
        }
        if ($nbf > 0 && $nbf > ($now + $clockSkew)) {
            return ['ok' => false, 'claims' => null, 'error' => 'Google token is not valid yet.'];
        }

        $sub = trim((string)($payload['sub'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        if ($sub === '' || $email === '') {
            return ['ok' => false, 'claims' => null, 'error' => 'Google account information is incomplete.'];
        }

        return ['ok' => true, 'claims' => $payload, 'error' => ''];
    }
}
