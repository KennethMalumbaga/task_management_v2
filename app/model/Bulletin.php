<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('bulletin_ensure_table')) {
    function bulletin_ensure_table($pdo)
    {
        static $ensured = false;
        if ($ensured) {
            return true;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS bulletin_posts (
                    id INT NOT NULL AUTO_INCREMENT,
                    type VARCHAR(10) NOT NULL DEFAULT 'ann',
                    title VARCHAR(255) NOT NULL,
                    body TEXT NOT NULL,
                    created_by INT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    organization_id INT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_bulletin_posts_created_at (created_at),
                    INDEX idx_bulletin_posts_org (organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $ensured = true;
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bulletin_normalize_type')) {
    function bulletin_normalize_type($type)
    {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, ['ann', 'rem', 'alt'], true)) {
            return 'ann';
        }
        return $type;
    }
}

if (!function_exists('bulletin_time_ago')) {
    function bulletin_time_ago($timestamp)
    {
        $ts = strtotime((string)$timestamp);
        if ($ts === false) {
            return '';
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }
        if ($diff < 172800) {
            return 'Yesterday';
        }
        return floor($diff / 86400) . ' days ago';
    }
}

if (!function_exists('bulletin_format_row')) {
    function bulletin_format_row($row)
    {
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => isset($row['id']) ? (int)$row['id'] : 0,
            'type' => bulletin_normalize_type($row['type'] ?? 'ann'),
            'title' => trim((string)($row['title'] ?? '')),
            'body' => trim((string)($row['body'] ?? '')),
            'time' => bulletin_time_ago($row['created_at'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

if (!function_exists('get_recent_bulletin_posts')) {
    function get_recent_bulletin_posts($pdo, $limit = 20)
    {
        if (!bulletin_ensure_table($pdo)) {
            return [];
        }

        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 1;
        } elseif ($limit > 100) {
            $limit = 100;
        }

        $sql = "SELECT id, type, title, body, created_at
                FROM bulletin_posts
                WHERE 1=1";
        $params = [];
        $scope = tenant_get_scope($pdo, 'bulletin_posts');
        $sql .= $scope['sql'] . "
                ORDER BY id DESC
                LIMIT {$limit}";
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $posts = [];
        foreach ($rows as $row) {
            $formatted = bulletin_format_row($row);
            if ($formatted) {
                $posts[] = $formatted;
            }
        }

        return $posts;
    }
}

if (!function_exists('get_bulletin_post_by_id')) {
    function get_bulletin_post_by_id($pdo, $id)
    {
        if (!bulletin_ensure_table($pdo)) {
            return null;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $sql = "SELECT id, type, title, body, created_at
                FROM bulletin_posts
                WHERE id = ?";
        $params = [$id];
        $scope = tenant_get_scope($pdo, 'bulletin_posts');
        $sql .= $scope['sql'] . " LIMIT 1";
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? bulletin_format_row($row) : null;
    }
}

if (!function_exists('create_bulletin_post')) {
    function create_bulletin_post($pdo, $type, $title, $body, $createdBy = null)
    {
        if (!bulletin_ensure_table($pdo)) {
            return null;
        }

        $type = bulletin_normalize_type($type);
        $title = trim((string)$title);
        $body = trim((string)$body);
        $createdBy = $createdBy !== null ? (int)$createdBy : null;

        if ($title === '' || $body === '') {
            return null;
        }

        $title = mb_substr($title, 0, 255);
        $body = mb_substr($body, 0, 4000);

        $orgId = tenant_get_current_org_id();
        if ((!$orgId || (int)$orgId <= 0) && $createdBy) {
            $orgId = tenant_resolve_user_org($pdo, (int)$createdBy);
        }
        $hasOrgColumn = tenant_column_exists($pdo, 'bulletin_posts', 'organization_id');

        if ($hasOrgColumn && $orgId) {
            $sql = "INSERT INTO bulletin_posts (type, title, body, created_by, organization_id)
                    VALUES (?, ?, ?, ?, ?)";
            $params = [$type, $title, $body, $createdBy, (int)$orgId];
        } else {
            $sql = "INSERT INTO bulletin_posts (type, title, body, created_by)
                    VALUES (?, ?, ?, ?)";
            $params = [$type, $title, $body, $createdBy];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $id = (int)$pdo->lastInsertId();

        return get_bulletin_post_by_id($pdo, $id);
    }
}

if (!function_exists('delete_bulletin_post')) {
    function delete_bulletin_post($pdo, $id)
    {
        if (!bulletin_ensure_table($pdo)) {
            return false;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        $sql = "DELETE FROM bulletin_posts WHERE id = ?";
        $params = [$id];
        $scope = tenant_get_scope($pdo, 'bulletin_posts');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
