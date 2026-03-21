<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('chat_visibility_normalize_org_id')) {
    function chat_visibility_normalize_org_id($organizationId = null)
    {
        $resolvedOrgId = $organizationId !== null ? (int)$organizationId : (int)tenant_get_current_org_id();
        return $resolvedOrgId > 0 ? $resolvedOrgId : 0;
    }
}

if (!function_exists('chat_visibility_schema_ready')) {
    function chat_visibility_schema_ready($pdo)
    {
        static $ready = false;

        if ($ready) {
            return true;
        }

        if (tenant_table_exists($pdo, 'chat_hidden_threads')) {
            $ready = true;
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS chat_hidden_threads (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    thread_type VARCHAR(10) NOT NULL,
                    other_user_id INT NOT NULL DEFAULT 0,
                    group_id INT NOT NULL DEFAULT 0,
                    hidden_at DATETIME NOT NULL,
                    organization_id INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_chat_hidden_thread (user_id, thread_type, other_user_id, group_id, organization_id),
                    KEY idx_chat_hidden_lookup (user_id, thread_type, hidden_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $pdo->exec($sql);
            $ready = tenant_table_exists($pdo, 'chat_hidden_threads');
        } catch (Throwable $e) {
            $ready = false;
        }

        return $ready;
    }
}

if (!function_exists('chat_visibility_hide_thread')) {
    function chat_visibility_hide_thread($pdo, $userId, $threadType, $otherUserId = 0, $groupId = 0, $organizationId = null, $hiddenAt = null)
    {
        $userId = (int)$userId;
        $otherUserId = max(0, (int)$otherUserId);
        $groupId = max(0, (int)$groupId);
        $organizationId = chat_visibility_normalize_org_id($organizationId);
        $hiddenAt = $hiddenAt ?: date('Y-m-d H:i:s');

        if ($userId <= 0 || !chat_visibility_schema_ready($pdo)) {
            return false;
        }

        $update = $pdo->prepare(
            "UPDATE chat_hidden_threads
             SET hidden_at = ?
             WHERE user_id = ? AND thread_type = ? AND other_user_id = ? AND group_id = ? AND organization_id = ?"
        );
        $update->execute([$hiddenAt, $userId, $threadType, $otherUserId, $groupId, $organizationId]);
        if ($update->rowCount() > 0) {
            return true;
        }

        try {
            $insert = $pdo->prepare(
                "INSERT INTO chat_hidden_threads (user_id, thread_type, other_user_id, group_id, hidden_at, organization_id)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([$userId, $threadType, $otherUserId, $groupId, $hiddenAt, $organizationId]);
            return true;
        } catch (Throwable $e) {
            $update->execute([$hiddenAt, $userId, $threadType, $otherUserId, $groupId, $organizationId]);
            return $update->rowCount() > 0;
        }
    }
}

if (!function_exists('chat_visibility_hide_user_thread')) {
    function chat_visibility_hide_user_thread($pdo, $userId, $otherUserId, $organizationId = null, $hiddenAt = null)
    {
        $otherUserId = (int)$otherUserId;
        if ($otherUserId <= 0) {
            return false;
        }

        return chat_visibility_hide_thread($pdo, $userId, 'user', $otherUserId, 0, $organizationId, $hiddenAt);
    }
}

if (!function_exists('chat_visibility_hide_group_thread')) {
    function chat_visibility_hide_group_thread($pdo, $userId, $groupId, $organizationId = null, $hiddenAt = null)
    {
        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            return false;
        }

        return chat_visibility_hide_thread($pdo, $userId, 'group', 0, $groupId, $organizationId, $hiddenAt);
    }
}

if (!function_exists('get_hidden_threads_map')) {
    function get_hidden_threads_map($pdo, $userId, $organizationId = null)
    {
        $userId = (int)$userId;
        $organizationId = chat_visibility_normalize_org_id($organizationId);

        $result = [
            'users' => [],
            'groups' => [],
        ];

        if ($userId <= 0 || !tenant_table_exists($pdo, 'chat_hidden_threads')) {
            return $result;
        }

        $stmt = $pdo->prepare(
            "SELECT thread_type, other_user_id, group_id, hidden_at
             FROM chat_hidden_threads
             WHERE user_id = ? AND organization_id = ?"
        );
        $stmt->execute([$userId, $organizationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $threadType = (string)($row['thread_type'] ?? '');
            $hiddenAt = (string)($row['hidden_at'] ?? '');
            if ($hiddenAt === '') {
                continue;
            }

            if ($threadType === 'group') {
                $groupId = (int)($row['group_id'] ?? 0);
                if ($groupId > 0) {
                    $result['groups'][$groupId] = $hiddenAt;
                }
            } else {
                $otherUserId = (int)($row['other_user_id'] ?? 0);
                if ($otherUserId > 0) {
                    $result['users'][$otherUserId] = $hiddenAt;
                }
            }
        }

        return $result;
    }
}

if (!function_exists('chat_thread_should_be_hidden')) {
    function chat_thread_should_be_hidden($hiddenAt, $lastActivityAt = null)
    {
        $hiddenAt = trim((string)$hiddenAt);
        if ($hiddenAt === '') {
            return false;
        }

        $hiddenTs = strtotime($hiddenAt);
        if ($hiddenTs === false) {
            return false;
        }

        $lastActivityAt = trim((string)$lastActivityAt);
        if ($lastActivityAt === '' || $lastActivityAt === '0000-00-00 00:00:00') {
            return true;
        }

        $activityTs = strtotime($lastActivityAt);
        if ($activityTs === false) {
            return true;
        }

        return $activityTs <= $hiddenTs;
    }
}
