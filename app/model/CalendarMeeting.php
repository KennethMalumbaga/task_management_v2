<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('calendar_meetings_ensure_schema')) {
    function calendar_meetings_ensure_schema($pdo)
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
                    "CREATE TABLE IF NOT EXISTS calendar_meetings (
                        id INT NOT NULL AUTO_INCREMENT,
                        title VARCHAR(255) NOT NULL,
                        description TEXT DEFAULT NULL,
                        meeting_date DATE NOT NULL,
                        start_time TIME NOT NULL,
                        end_time TIME NOT NULL,
                        timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Manila',
                        audience_type VARCHAR(20) NOT NULL DEFAULT 'everyone',
                        group_id INT DEFAULT NULL,
                        task_id INT DEFAULT NULL,
                        google_event_id VARCHAR(255) DEFAULT NULL,
                        google_calendar_url VARCHAR(2048) DEFAULT NULL,
                        google_meet_url VARCHAR(2048) DEFAULT NULL,
                        google_conference_id VARCHAR(255) DEFAULT NULL,
                        created_by INT NOT NULL,
                        organization_id INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT calendar_meetings_pkey PRIMARY KEY (id),
                        UNIQUE KEY uniq_calendar_meetings_google_event (google_event_id),
                        KEY idx_calendar_meetings_org_date (organization_id, meeting_date),
                        KEY idx_calendar_meetings_creator_date (created_by, meeting_date)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } else {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS calendar_meetings (
                        id SERIAL PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        description TEXT NULL,
                        meeting_date DATE NOT NULL,
                        start_time TIME NOT NULL,
                        end_time TIME NOT NULL,
                        timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Manila',
                        audience_type VARCHAR(20) NOT NULL DEFAULT 'everyone',
                        group_id INT NULL,
                        task_id INT NULL,
                        google_event_id VARCHAR(255) NULL UNIQUE,
                        google_calendar_url VARCHAR(2048) NULL,
                        google_meet_url VARCHAR(2048) NULL,
                        google_conference_id VARCHAR(255) NULL,
                        created_by INT NOT NULL,
                        organization_id INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )"
                );
                $pdo->exec(
                    "CREATE INDEX IF NOT EXISTS idx_calendar_meetings_org_date
                     ON calendar_meetings (organization_id, meeting_date)"
                );
                $pdo->exec(
                    "CREATE INDEX IF NOT EXISTS idx_calendar_meetings_creator_date
                     ON calendar_meetings (created_by, meeting_date)"
                );
            }

            if (!tenant_column_exists($pdo, 'calendar_meetings', 'audience_type')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE calendar_meetings ADD COLUMN audience_type VARCHAR(20) NOT NULL DEFAULT 'everyone' AFTER timezone");
                } else {
                    $pdo->exec("ALTER TABLE calendar_meetings ADD COLUMN audience_type VARCHAR(20) NOT NULL DEFAULT 'everyone'");
                }
            }

            if (!tenant_column_exists($pdo, 'calendar_meetings', 'group_id')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE calendar_meetings ADD COLUMN group_id INT NULL AFTER audience_type");
                } else {
                    $pdo->exec("ALTER TABLE calendar_meetings ADD COLUMN group_id INT NULL");
                }
            }

            if (!tenant_column_exists($pdo, 'calendar_meetings', 'task_id')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE calendar_meetings ADD COLUMN task_id INT NULL AFTER group_id");
                } else {
                    $pdo->exec("ALTER TABLE calendar_meetings ADD COLUMN task_id INT NULL");
                }
            }
        } catch (Throwable $e) {
            // Keep the rest of the app working even if schema auto-upgrade is blocked.
        }

        $cache[$cacheKey] = tenant_table_exists($pdo, 'calendar_meetings');
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('calendar_meetings_normalize_audience_type')) {
    function calendar_meetings_normalize_audience_type($audienceType)
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

if (!function_exists('calendar_meetings_append_scope')) {
    function calendar_meetings_append_scope($pdo, $sql, array $params, $alias = '', $joinWord = 'AND')
    {
        $scope = tenant_get_scope($pdo, 'calendar_meetings', $alias, $joinWord);
        return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
    }
}

if (!function_exists('calendar_meetings_insert')) {
    function calendar_meetings_insert($pdo, array $data)
    {
        if (!calendar_meetings_ensure_schema($pdo)) {
            return 0;
        }

        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $orgId = tenant_get_current_org_id();
        $hasOrg = tenant_column_exists($pdo, 'calendar_meetings', 'organization_id');
        $columns = [
            'title',
            'description',
            'meeting_date',
            'start_time',
            'end_time',
            'timezone',
            'audience_type',
            'group_id',
            'task_id',
            'google_event_id',
            'google_calendar_url',
            'google_meet_url',
            'google_conference_id',
            'created_by',
        ];
        $values = [
            trim((string)($data['title'] ?? '')),
            trim((string)($data['description'] ?? '')) ?: null,
            trim((string)($data['meeting_date'] ?? '')),
            trim((string)($data['start_time'] ?? '')),
            trim((string)($data['end_time'] ?? '')),
            trim((string)($data['timezone'] ?? '')) ?: 'Asia/Manila',
            calendar_meetings_normalize_audience_type($data['audience_type'] ?? 'everyone'),
            !empty($data['group_id']) ? (int)$data['group_id'] : null,
            !empty($data['task_id']) ? (int)$data['task_id'] : null,
            trim((string)($data['google_event_id'] ?? '')) ?: null,
            trim((string)($data['google_calendar_url'] ?? '')) ?: null,
            trim((string)($data['google_meet_url'] ?? '')) ?: null,
            trim((string)($data['google_conference_id'] ?? '')) ?: null,
            (int)($data['created_by'] ?? 0),
        ];

        if ($hasOrg) {
            $columns[] = 'organization_id';
            $values[] = $orgId ? (int)$orgId : null;
        }

        $sql = "INSERT INTO calendar_meetings (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
        if ($driver === 'pgsql') {
            $sql .= " RETURNING id";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($driver === 'pgsql') {
            return (int)$stmt->fetchColumn();
        }

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('calendar_meetings_get_between')) {
    function calendar_meetings_get_between($pdo, $startDate, $endDate, $viewerUserId = 0, $sessionRole = 'employee')
    {
        if (!calendar_meetings_ensure_schema($pdo)) {
            return [];
        }

        $startDate = trim((string)$startDate);
        $endDate = trim((string)$endDate);
        if ($startDate === '' || $endDate === '') {
            return [];
        }

        $sql = "SELECT cm.*,
                       COALESCE(u.full_name, 'Workspace member') AS creator_name,
                       u.profile_image AS creator_profile_image,
                       g.name AS group_name,
                       t.title AS task_name
                FROM calendar_meetings cm
                LEFT JOIN users u ON u.id = cm.created_by
                LEFT JOIN groups g ON g.id = cm.group_id
                LEFT JOIN tasks t ON t.id = cm.task_id
                WHERE cm.meeting_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];

        $sessionRole = strtolower(trim((string)$sessionRole));
        $viewerUserId = (int)$viewerUserId;
        if ($sessionRole === 'admin') {
            $sql .= " AND EXISTS (
                        SELECT 1
                        FROM users creator
                        WHERE creator.id = cm.created_by
                          AND creator.role = 'admin'
                    )";
        } else {
            $groupScope = tenant_get_scope($pdo, 'group_members', 'gm');
            $taskScope = tenant_get_scope($pdo, 'task_assignees', 'ta');
            $sql .= " AND (
                        COALESCE(cm.audience_type, 'everyone') = 'everyone'
                        OR (
                            COALESCE(cm.audience_type, 'everyone') = 'group'
                            AND cm.group_id IS NOT NULL
                            AND EXISTS (
                                SELECT 1
                                FROM group_members gm
                                WHERE gm.group_id = cm.group_id
                                  AND gm.user_id = ?" . $groupScope['sql'] . "
                            )
                        )
                        OR (
                            COALESCE(cm.audience_type, 'everyone') = 'task'
                            AND cm.task_id IS NOT NULL
                            AND EXISTS (
                                SELECT 1
                                FROM task_assignees ta
                                WHERE ta.task_id = cm.task_id
                                  AND ta.user_id = ?" . $taskScope['sql'] . "
                            )
                        )
                    )";
            $params[] = $viewerUserId;
            $params = array_merge($params, $groupScope['params']);
            $params[] = $viewerUserId;
            $params = array_merge($params, $taskScope['params']);
        }

        [$sql, $params] = calendar_meetings_append_scope($pdo, $sql, $params, 'cm');
        $sql .= " ORDER BY cm.meeting_date ASC, cm.start_time ASC, cm.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('calendar_meetings_get_for_date')) {
    function calendar_meetings_get_for_date($pdo, $meetingDate, $viewerUserId = 0, $sessionRole = 'employee')
    {
        return calendar_meetings_get_between($pdo, $meetingDate, $meetingDate, $viewerUserId, $sessionRole);
    }
}
