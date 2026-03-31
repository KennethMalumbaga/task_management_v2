<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('google_workspace_tokens_ensure_schema')) {
    function google_workspace_tokens_ensure_schema($pdo)
    {
        static $cache = [];

        $cacheKey = is_object($pdo) ? spl_object_hash($pdo) : 'default';
        if (array_key_exists($cacheKey, $cache)) {
            return (bool)$cache[$cacheKey];
        }

        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        try {
            if ($driver === 'mysql') {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS user_google_oauth_tokens (
                        id INT NOT NULL AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        google_sub VARCHAR(255) DEFAULT NULL,
                        google_email VARCHAR(255) DEFAULT NULL,
                        refresh_token TEXT NOT NULL,
                        scope TEXT DEFAULT NULL,
                        organization_id INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT user_google_oauth_tokens_pkey PRIMARY KEY (id),
                        UNIQUE KEY uniq_user_google_oauth_tokens_user (user_id),
                        KEY idx_user_google_oauth_tokens_org_user (organization_id, user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } else {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS user_google_oauth_tokens (
                        id SERIAL PRIMARY KEY,
                        user_id INT NOT NULL UNIQUE,
                        google_sub VARCHAR(255) NULL,
                        google_email VARCHAR(255) NULL,
                        refresh_token TEXT NOT NULL,
                        scope TEXT NULL,
                        organization_id INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )"
                );
                $pdo->exec(
                    "CREATE INDEX IF NOT EXISTS idx_user_google_oauth_tokens_org_user
                     ON user_google_oauth_tokens (organization_id, user_id)"
                );
            }
        } catch (Throwable $e) {
            // Keep the rest of the app working even if schema auto-upgrade is blocked.
        }

        $cache[$cacheKey] = tenant_table_exists($pdo, 'user_google_oauth_tokens');
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('google_workspace_get_token_record')) {
    function google_workspace_get_token_record($pdo, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0 || !google_workspace_tokens_ensure_schema($pdo)) {
            return null;
        }

        $sql = "SELECT * FROM user_google_oauth_tokens WHERE user_id = ?";
        $params = [$userId];
        $scope = tenant_get_scope($pdo, 'user_google_oauth_tokens');
        $sql .= $scope['sql'] . " ORDER BY id DESC LIMIT 1";
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('google_workspace_save_refresh_token')) {
    function google_workspace_save_refresh_token($pdo, $userId, $googleSub, $googleEmail, $refreshToken, $scope = '')
    {
        $userId = (int)$userId;
        $googleSub = trim((string)$googleSub);
        $googleEmail = strtolower(trim((string)$googleEmail));
        $refreshToken = trim((string)$refreshToken);
        $scope = trim((string)$scope);

        if ($userId <= 0 || $refreshToken === '' || !google_workspace_tokens_ensure_schema($pdo)) {
            return false;
        }

        $orgId = tenant_get_current_org_id();
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare(
                "INSERT INTO user_google_oauth_tokens
                 (user_id, google_sub, google_email, refresh_token, scope, organization_id)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    google_sub = VALUES(google_sub),
                    google_email = VALUES(google_email),
                    refresh_token = VALUES(refresh_token),
                    scope = VALUES(scope),
                    organization_id = VALUES(organization_id)"
            );

            return $stmt->execute([
                $userId,
                $googleSub !== '' ? $googleSub : null,
                $googleEmail !== '' ? $googleEmail : null,
                $refreshToken,
                $scope !== '' ? $scope : null,
                $orgId ? (int)$orgId : null,
            ]);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO user_google_oauth_tokens
             (user_id, google_sub, google_email, refresh_token, scope, organization_id)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT (user_id) DO UPDATE SET
                google_sub = EXCLUDED.google_sub,
                google_email = EXCLUDED.google_email,
                refresh_token = EXCLUDED.refresh_token,
                scope = EXCLUDED.scope,
                organization_id = EXCLUDED.organization_id,
                updated_at = CURRENT_TIMESTAMP"
        );

        return $stmt->execute([
            $userId,
            $googleSub !== '' ? $googleSub : null,
            $googleEmail !== '' ? $googleEmail : null,
            $refreshToken,
            $scope !== '' ? $scope : null,
            $orgId ? (int)$orgId : null,
        ]);
    }
}

if (!function_exists('google_workspace_delete_token_record')) {
    function google_workspace_delete_token_record($pdo, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0 || !google_workspace_tokens_ensure_schema($pdo)) {
            return false;
        }

        $sql = "DELETE FROM user_google_oauth_tokens WHERE user_id = ?";
        $params = [$userId];
        $scope = tenant_get_scope($pdo, 'user_google_oauth_tokens');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
