<?php

if (!function_exists('login_verification_truthy')) {
    function login_verification_truthy($value)
    {
        if ($value === false || $value === null) {
            return false;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('login_verification_env')) {
    function login_verification_env($name, $default = '')
    {
        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($name, $_ENV)) {
            return $_ENV[$name];
        }

        return $default;
    }
}

if (!function_exists('login_verification_is_temporarily_disabled')) {
    function login_verification_is_temporarily_disabled()
    {
        if (login_verification_truthy(login_verification_env('DISABLE_LOGIN_VERIFICATION'))) {
            return true;
        }

        $railwayEnv = login_verification_env('RAILWAY_ENVIRONMENT');
        if ($railwayEnv === false || trim((string)$railwayEnv) === '') {
            return false;
        }

        return login_verification_truthy(login_verification_env('DISABLE_LOGIN_VERIFICATION_ON_RAILWAY'));
    }
}

if (!function_exists('login_verification_ensure_table')) {
    function login_verification_ensure_table($pdo)
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        try {
            $sql = "CREATE TABLE IF NOT EXISTS user_login_verifications (
                        user_id INT NOT NULL,
                        code_hash VARCHAR(255) DEFAULT NULL,
                        code_expires_at DATETIME DEFAULT NULL,
                        last_code_sent_at DATETIME DEFAULT NULL,
                        verify_attempts INT NOT NULL DEFAULT 0,
                        verified_at DATETIME DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT user_login_verifications_pkey PRIMARY KEY (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
            $ready = true;
            return true;
        } catch (Throwable $e) {
            error_log("login_verification_ensure_table failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('login_verification_generate_code')) {
    function login_verification_generate_code($length = 4)
    {
        $length = max(4, (int)$length);
        $digits = '0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $digits[random_int(0, 9)];
        }
        return $code;
    }
}

if (!function_exists('login_verification_mask_email')) {
    function login_verification_mask_email($email)
    {
        $email = trim((string)$email);
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '') {
            return '***@' . $domain;
        }
        if (strlen($local) <= 2) {
            return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
        }

        return substr($local, 0, 1) . str_repeat('*', max(2, strlen($local) - 2)) . substr($local, -1) . '@' . $domain;
    }
}

if (!function_exists('login_verification_mark_required')) {
    function login_verification_mark_required($pdo, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }
        // Avoid running CREATE TABLE while a transaction is open.
        // MySQL DDL causes implicit commits and can break signup flow.
        if (!$pdo->inTransaction()) {
            if (!login_verification_ensure_table($pdo)) {
                return false;
            }
        }

        try {
            $sql = "INSERT INTO user_login_verifications
                        (user_id, code_hash, code_expires_at, last_code_sent_at, verify_attempts, verified_at)
                    VALUES (?, NULL, NULL, NULL, 0, NULL)
                    ON DUPLICATE KEY UPDATE
                        code_hash = NULL,
                        code_expires_at = NULL,
                        last_code_sent_at = NULL,
                        verify_attempts = 0,
                        verified_at = NULL";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$userId]);
        } catch (Throwable $e) {
            error_log("login_verification_mark_required failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('login_verification_is_required')) {
    function login_verification_is_required($pdo, $userId)
    {
        if (login_verification_is_temporarily_disabled()) {
            return false;
        }

        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }
        if (!login_verification_ensure_table($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT verified_at FROM user_login_verifications WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        return empty($row['verified_at']);
    }
}

if (!function_exists('login_verification_issue_code')) {
    function login_verification_issue_code($pdo, $userId, $ttlMinutes = 10)
    {
        $userId = (int)$userId;
        $ttlMinutes = max(1, (int)$ttlMinutes);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid user.'];
        }
        if (!login_verification_ensure_table($pdo)) {
            return ['ok' => false, 'error' => 'Verification system is not available.'];
        }

        $code = login_verification_generate_code(4);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $ttlMinutes . ' minutes'));

        $sql = "INSERT INTO user_login_verifications
                    (user_id, code_hash, code_expires_at, last_code_sent_at, verify_attempts, verified_at)
                VALUES (?, ?, ?, NOW(), 0, NULL)
                ON DUPLICATE KEY UPDATE
                    code_hash = VALUES(code_hash),
                    code_expires_at = VALUES(code_expires_at),
                    last_code_sent_at = VALUES(last_code_sent_at),
                    verify_attempts = 0";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([$userId, $codeHash, $expiresAt]);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Unable to save verification code.'];
        }

        return ['ok' => true, 'code' => $code, 'expires_at' => $expiresAt];
    }
}

if (!function_exists('login_verification_verify_code')) {
    function login_verification_verify_code($pdo, $userId, $code, $maxAttempts = 5)
    {
        $userId = (int)$userId;
        $code = trim((string)$code);
        $maxAttempts = max(1, (int)$maxAttempts);

        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid verification request.'];
        }
        if (!login_verification_ensure_table($pdo)) {
            return ['ok' => false, 'error' => 'Verification system is not available.'];
        }

        $stmt = $pdo->prepare(
            "SELECT code_hash, code_expires_at, verify_attempts, verified_at
             FROM user_login_verifications
             WHERE user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'Verification session expired. Please login again.'];
        }
        if (!empty($row['verified_at'])) {
            return ['ok' => true];
        }

        $attempts = (int)($row['verify_attempts'] ?? 0);
        if ($attempts >= $maxAttempts) {
            return ['ok' => false, 'error' => 'Too many attempts. Please resend a new code.'];
        }

        $hash = (string)($row['code_hash'] ?? '');
        $expiresAt = (string)($row['code_expires_at'] ?? '');
        if ($hash === '' || $expiresAt === '') {
            return ['ok' => false, 'error' => 'No active code. Please resend verification code.'];
        }

        $expiryTs = strtotime($expiresAt);
        if ($expiryTs === false || $expiryTs < time()) {
            return ['ok' => false, 'error' => 'Verification code expired. Please resend a new code.'];
        }

        if (!password_verify($code, $hash)) {
            $upd = $pdo->prepare("UPDATE user_login_verifications SET verify_attempts = verify_attempts + 1 WHERE user_id = ?");
            $upd->execute([$userId]);
            return ['ok' => false, 'error' => 'Incorrect verification code.'];
        }

        $upd = $pdo->prepare(
            "UPDATE user_login_verifications
             SET verified_at = NOW(),
                 code_hash = NULL,
                 code_expires_at = NULL,
                 verify_attempts = 0
             WHERE user_id = ?"
        );
        $upd->execute([$userId]);
        return ['ok' => true];
    }
}
