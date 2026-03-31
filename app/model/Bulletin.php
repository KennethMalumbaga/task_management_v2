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
                    source_type VARCHAR(30) NULL,
                    source_id INT NULL,
                    audience_type VARCHAR(20) NOT NULL DEFAULT 'everyone',
                    group_id INT NULL,
                    task_id INT NULL,
                    created_by INT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    organization_id INT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_bulletin_posts_created_at (created_at),
                    INDEX idx_bulletin_posts_org (organization_id),
                    INDEX idx_bulletin_posts_source (source_type, source_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            if (!tenant_column_exists($pdo, 'bulletin_posts', 'source_type')) {
                $pdo->exec("ALTER TABLE bulletin_posts ADD COLUMN source_type VARCHAR(30) NULL AFTER body");
            }
            if (!tenant_column_exists($pdo, 'bulletin_posts', 'source_id')) {
                $pdo->exec("ALTER TABLE bulletin_posts ADD COLUMN source_id INT NULL AFTER source_type");
            }
            if (!tenant_column_exists($pdo, 'bulletin_posts', 'audience_type')) {
                $pdo->exec("ALTER TABLE bulletin_posts ADD COLUMN audience_type VARCHAR(20) NOT NULL DEFAULT 'everyone' AFTER source_id");
            }
            if (!tenant_column_exists($pdo, 'bulletin_posts', 'group_id')) {
                $pdo->exec("ALTER TABLE bulletin_posts ADD COLUMN group_id INT NULL AFTER audience_type");
            }
            if (!tenant_column_exists($pdo, 'bulletin_posts', 'task_id')) {
                $pdo->exec("ALTER TABLE bulletin_posts ADD COLUMN task_id INT NULL AFTER group_id");
            }
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

if (!function_exists('bulletin_normalize_audience_type')) {
    function bulletin_normalize_audience_type($audienceType)
    {
        $audienceType = strtolower(trim((string)$audienceType));
        if ($audienceType === 'group') {
            return 'group';
        }
        if ($audienceType === 'task') {
            return 'task';
        }
        return 'everyone';
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
    function get_recent_bulletin_posts($pdo, $limit = 20, $viewerUserId = 0, $sessionRole = 'employee')
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

        $sessionRole = strtolower(trim((string)$sessionRole));
        $viewerUserId = (int)$viewerUserId;
        if ($sessionRole !== 'admin') {
            $groupScope = tenant_get_scope($pdo, 'group_members', 'gm');
            $taskScope = tenant_get_scope($pdo, 'task_assignees', 'ta');
            $sql .= " AND (
                        COALESCE(audience_type, 'everyone') = 'everyone'
                        OR (
                            COALESCE(audience_type, 'everyone') = 'group'
                            AND group_id IS NOT NULL
                            AND EXISTS (
                                SELECT 1
                                FROM group_members gm
                                WHERE gm.group_id = bulletin_posts.group_id
                                  AND gm.user_id = ?" . $groupScope['sql'] . "
                            )
                        )
                        OR (
                            COALESCE(audience_type, 'everyone') = 'task'
                            AND task_id IS NOT NULL
                            AND EXISTS (
                                SELECT 1
                                FROM task_assignees ta
                                WHERE ta.task_id = bulletin_posts.task_id
                                  AND ta.user_id = ?" . $taskScope['sql'] . "
                            )
                        )
                    )";
            $params[] = $viewerUserId;
            $params = array_merge($params, $groupScope['params']);
            $params[] = $viewerUserId;
            $params = array_merge($params, $taskScope['params']);
        }

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
    function create_bulletin_post($pdo, $type, $title, $body, $createdBy = null, array $meta = [])
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
        $sourceType = trim((string)($meta['source_type'] ?? '')) ?: null;
        $sourceId = !empty($meta['source_id']) ? (int)$meta['source_id'] : null;
        $audienceType = bulletin_normalize_audience_type($meta['audience_type'] ?? 'everyone');
        $groupId = !empty($meta['group_id']) ? (int)$meta['group_id'] : null;
        $taskId = !empty($meta['task_id']) ? (int)$meta['task_id'] : null;

        $orgId = tenant_get_current_org_id();
        if ((!$orgId || (int)$orgId <= 0) && $createdBy) {
            $orgId = tenant_resolve_user_org($pdo, (int)$createdBy);
        }
        $hasOrgColumn = tenant_column_exists($pdo, 'bulletin_posts', 'organization_id');

        if ($hasOrgColumn && $orgId) {
            $sql = "INSERT INTO bulletin_posts (type, title, body, source_type, source_id, audience_type, group_id, task_id, created_by, organization_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$type, $title, $body, $sourceType, $sourceId, $audienceType, $groupId, $taskId, $createdBy, (int)$orgId];
        } else {
            $sql = "INSERT INTO bulletin_posts (type, title, body, source_type, source_id, audience_type, group_id, task_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$type, $title, $body, $sourceType, $sourceId, $audienceType, $groupId, $taskId, $createdBy];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $id = (int)$pdo->lastInsertId();

        return get_bulletin_post_by_id($pdo, $id);
    }
}

if (!function_exists('create_meeting_bulletin_reminder')) {
    function create_meeting_bulletin_reminder($pdo, array $meetingData, $createdBy = null)
    {
        if (!bulletin_ensure_table($pdo)) {
            return null;
        }

        $meetingTitle = trim((string)($meetingData['title'] ?? ''));
        if ($meetingTitle === '') {
            $meetingTitle = 'Workspace Meeting';
        }

        $meetingDate = trim((string)($meetingData['meeting_date'] ?? ''));
        $startTime = trim((string)($meetingData['start_time'] ?? ''));
        $endTime = trim((string)($meetingData['end_time'] ?? ''));
        $audienceType = bulletin_normalize_audience_type($meetingData['audience_type'] ?? 'everyone');
        $groupName = trim((string)($meetingData['group_name'] ?? ''));
        $taskName = trim((string)($meetingData['task_name'] ?? ''));

        $dateLabel = $meetingDate;
        if ($meetingDate !== '') {
            $timestamp = strtotime($meetingDate);
            if ($timestamp !== false) {
                $dateLabel = date('F j, Y', $timestamp);
            }
        }

        $timeLabel = '';
        if ($startTime !== '' && $endTime !== '') {
            $startTs = strtotime($startTime);
            $endTs = strtotime($endTime);
            if ($startTs !== false && $endTs !== false) {
                $timeLabel = date('g:i A', $startTs) . ' - ' . date('g:i A', $endTs);
            }
        }

        $audienceLabel = 'Everyone';
        if ($audienceType === 'group' && $groupName !== '') {
            $audienceLabel = 'Group: ' . $groupName;
        } elseif ($audienceType === 'task' && $taskName !== '') {
            $audienceLabel = 'Task: ' . $taskName;
        } elseif ($audienceType === 'group') {
            $audienceLabel = 'Selected group';
        } elseif ($audienceType === 'task') {
            $audienceLabel = 'Selected task';
        }

        $bodyParts = [];
        if ($dateLabel !== '') {
            $bodyParts[] = 'Scheduled for ' . $dateLabel . ($timeLabel !== '' ? ' from ' . $timeLabel : '') . '.';
        } elseif ($timeLabel !== '') {
            $bodyParts[] = 'Scheduled at ' . $timeLabel . '.';
        }
        $bodyParts[] = 'Audience: ' . $audienceLabel . '.';
        $bodyParts[] = 'Open the calendar in TaskFlow to view the Google Meet link.';

        return create_bulletin_post(
            $pdo,
            'rem',
            'Meeting Reminder: ' . $meetingTitle,
            implode(' ', $bodyParts),
            $createdBy,
            [
                'source_type' => 'calendar_meeting',
                'source_id' => !empty($meetingData['source_id']) ? (int)$meetingData['source_id'] : null,
                'audience_type' => $audienceType,
                'group_id' => !empty($meetingData['group_id']) ? (int)$meetingData['group_id'] : null,
                'task_id' => !empty($meetingData['task_id']) ? (int)$meetingData['task_id'] : null,
            ]
        );
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
