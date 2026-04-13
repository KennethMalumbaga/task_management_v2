<?php

require_once __DIR__ . '/CalendarMeeting.php';

if (!function_exists('calendar_meeting_email_reminders_ensure_schema')) {
    function calendar_meeting_email_reminders_ensure_schema($pdo)
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
                    "CREATE TABLE IF NOT EXISTS calendar_meeting_email_reminders (
                        id INT NOT NULL AUTO_INCREMENT,
                        meeting_id INT NOT NULL,
                        user_id INT NOT NULL,
                        scheduled_for DATETIME NOT NULL,
                        sent_at DATETIME DEFAULT NULL,
                        error_message TEXT DEFAULT NULL,
                        organization_id INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uniq_calendar_meeting_email_reminders_meeting_user (meeting_id, user_id),
                        KEY idx_calendar_meeting_email_reminders_due (scheduled_for, sent_at),
                        KEY idx_calendar_meeting_email_reminders_org (organization_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } else {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS calendar_meeting_email_reminders (
                        id SERIAL PRIMARY KEY,
                        meeting_id INT NOT NULL,
                        user_id INT NOT NULL,
                        scheduled_for TIMESTAMP NOT NULL,
                        sent_at TIMESTAMP NULL,
                        error_message TEXT NULL,
                        organization_id INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )"
                );
                $pdo->exec(
                    "CREATE UNIQUE INDEX IF NOT EXISTS uniq_calendar_meeting_email_reminders_meeting_user
                     ON calendar_meeting_email_reminders (meeting_id, user_id)"
                );
                $pdo->exec(
                    "CREATE INDEX IF NOT EXISTS idx_calendar_meeting_email_reminders_due
                     ON calendar_meeting_email_reminders (scheduled_for, sent_at)"
                );
                $pdo->exec(
                    "CREATE INDEX IF NOT EXISTS idx_calendar_meeting_email_reminders_org
                     ON calendar_meeting_email_reminders (organization_id)"
                );
            }

            if (!tenant_column_exists($pdo, 'calendar_meeting_email_reminders', 'organization_id')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE calendar_meeting_email_reminders ADD COLUMN organization_id INT NULL AFTER error_message");
                } else {
                    $pdo->exec("ALTER TABLE calendar_meeting_email_reminders ADD COLUMN organization_id INT NULL");
                }
            }

            if (!tenant_column_exists($pdo, 'calendar_meeting_email_reminders', 'error_message')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE calendar_meeting_email_reminders ADD COLUMN error_message TEXT NULL AFTER sent_at");
                } else {
                    $pdo->exec("ALTER TABLE calendar_meeting_email_reminders ADD COLUMN error_message TEXT NULL");
                }
            }
        } catch (Throwable $e) {
            // Keep the app usable even if the DB cannot auto-upgrade here.
        }

        $cache[$cacheKey] = tenant_table_exists($pdo, 'calendar_meeting_email_reminders');
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('calendar_meeting_reminder_meeting_start_at')) {
    function calendar_meeting_reminder_meeting_start_at(array $meeting)
    {
        $meetingDate = trim((string)($meeting['meeting_date'] ?? ''));
        $startTime = trim((string)($meeting['start_time'] ?? ''));
        $timezone = trim((string)($meeting['timezone'] ?? '')) ?: 'Asia/Manila';

        if ($meetingDate === '' || $startTime === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($meetingDate . ' ' . $startTime . ':00', new DateTimeZone($timezone));
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('calendar_meeting_reminder_scheduled_for')) {
    function calendar_meeting_reminder_scheduled_for(array $meeting)
    {
        $startAt = calendar_meeting_reminder_meeting_start_at($meeting);
        if (!$startAt) {
            return null;
        }

        return $startAt->modify('-1 hour');
    }
}

if (!function_exists('calendar_meeting_reminder_fetch_upcoming_meetings')) {
    function calendar_meeting_reminder_fetch_upcoming_meetings($pdo, DateTimeImmutable $now, $daysAhead = 14)
    {
        if (!calendar_meetings_ensure_schema($pdo)) {
            return [];
        }

        $daysAhead = max(1, (int)$daysAhead);
        $startDate = $now->modify('-1 day')->format('Y-m-d');
        $endDate = $now->modify('+' . $daysAhead . ' days')->format('Y-m-d');

        $sql = "SELECT *
                FROM calendar_meetings
                WHERE meeting_date BETWEEN ? AND ?
                ORDER BY meeting_date ASC, start_time ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('calendar_meeting_reminder_fetch_recipient_users')) {
    function calendar_meeting_reminder_fetch_recipient_users($pdo, array $meeting)
    {
        $users = [];
        $appendUsers = static function (array $rows) use (&$users) {
            foreach ($rows as $row) {
                $userId = (int)($row['id'] ?? 0);
                $email = strtolower(trim((string)($row['username'] ?? '')));
                if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $users[$userId] = [
                    'id' => $userId,
                    'full_name' => trim((string)($row['full_name'] ?? '')) ?: 'Workspace member',
                    'username' => $email,
                ];
            }
        };

        $audienceType = calendar_meetings_normalize_audience_type($meeting['audience_type'] ?? 'everyone');
        $organizationId = !empty($meeting['organization_id']) ? (int)$meeting['organization_id'] : null;

        if ($audienceType === 'group' && !empty($meeting['group_id'])) {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT u.id, u.full_name, u.username
                 FROM users u
                 JOIN group_members gm ON gm.user_id = u.id
                 WHERE gm.group_id = ?"
            );
            $stmt->execute([(int)$meeting['group_id']]);
            $appendUsers($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } elseif ($audienceType === 'task' && !empty($meeting['task_id'])) {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT u.id, u.full_name, u.username
                 FROM users u
                 JOIN task_assignees ta ON ta.user_id = u.id
                 WHERE ta.task_id = ?"
            );
            $stmt->execute([(int)$meeting['task_id']]);
            $appendUsers($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } else {
            if (tenant_table_exists($pdo, 'organization_members') && $organizationId) {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT u.id, u.full_name, u.username
                     FROM users u
                     JOIN organization_members om ON om.user_id = u.id
                     WHERE om.organization_id = ?"
                );
                $stmt->execute([$organizationId]);
                $appendUsers($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            } elseif (tenant_column_exists($pdo, 'users', 'organization_id') && $organizationId) {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT id, full_name, username
                     FROM users
                     WHERE organization_id = ?"
                );
                $stmt->execute([$organizationId]);
                $appendUsers($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            } else {
                $stmt = $pdo->query("SELECT DISTINCT id, full_name, username FROM users");
                $appendUsers($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : []);
            }
        }

        $createdBy = (int)($meeting['created_by'] ?? 0);
        if ($createdBy > 0 && !isset($users[$createdBy])) {
            $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$createdBy]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $appendUsers([$row]);
            }
        }

        return array_values($users);
    }
}

if (!function_exists('calendar_meeting_reminder_sync_queue_for_meeting')) {
    function calendar_meeting_reminder_sync_queue_for_meeting($pdo, array $meeting, DateTimeImmutable $now = null)
    {
        if (!calendar_meeting_email_reminders_ensure_schema($pdo)) {
            return 0;
        }

        $meetingId = (int)($meeting['id'] ?? 0);
        if ($meetingId <= 0) {
            return 0;
        }

        $scheduledFor = calendar_meeting_reminder_scheduled_for($meeting);
        if (!$scheduledFor) {
            return 0;
        }
        if ($now instanceof DateTimeImmutable && $scheduledFor < $now) {
            $scheduledFor = $now;
        }

        $recipients = calendar_meeting_reminder_fetch_recipient_users($pdo, $meeting);
        if (empty($recipients)) {
            return 0;
        }

        $existingStmt = $pdo->prepare(
            "SELECT user_id
             FROM calendar_meeting_email_reminders
             WHERE meeting_id = ?"
        );
        $existingStmt->execute([$meetingId]);
        $existingIds = [];
        foreach (($existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $existingIds[(int)($row['user_id'] ?? 0)] = true;
        }

        $hasOrgColumn = tenant_column_exists($pdo, 'calendar_meeting_email_reminders', 'organization_id');
        $inserted = 0;
        foreach ($recipients as $recipient) {
            $userId = (int)($recipient['id'] ?? 0);
            if ($userId <= 0 || isset($existingIds[$userId])) {
                continue;
            }

            if ($hasOrgColumn) {
                $stmt = $pdo->prepare(
                    "INSERT INTO calendar_meeting_email_reminders (meeting_id, user_id, scheduled_for, organization_id)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $meetingId,
                    $userId,
                    $scheduledFor->format('Y-m-d H:i:s'),
                    !empty($meeting['organization_id']) ? (int)$meeting['organization_id'] : null,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO calendar_meeting_email_reminders (meeting_id, user_id, scheduled_for)
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([
                    $meetingId,
                    $userId,
                    $scheduledFor->format('Y-m-d H:i:s'),
                ]);
            }

            $existingIds[$userId] = true;
            $inserted++;
        }

        return $inserted;
    }
}

if (!function_exists('calendar_meeting_reminder_sync_upcoming_queue')) {
    function calendar_meeting_reminder_sync_upcoming_queue($pdo, DateTimeImmutable $now, $daysAhead = 14)
    {
        $meetings = calendar_meeting_reminder_fetch_upcoming_meetings($pdo, $now, $daysAhead);
        $inserted = 0;

        foreach ($meetings as $meeting) {
            $startAt = calendar_meeting_reminder_meeting_start_at($meeting);
            if (!$startAt || $startAt <= $now->modify('-15 minutes')) {
                continue;
            }

            $inserted += calendar_meeting_reminder_sync_queue_for_meeting($pdo, $meeting, $now);
        }

        return $inserted;
    }
}

if (!function_exists('calendar_meeting_reminder_get_due_rows')) {
    function calendar_meeting_reminder_get_due_rows($pdo, DateTimeImmutable $now, $lookbackMinutes = 15)
    {
        if (!calendar_meeting_email_reminders_ensure_schema($pdo) || !calendar_meetings_ensure_schema($pdo)) {
            return [];
        }

        $lookbackMinutes = max(1, (int)$lookbackMinutes);
        $windowStart = $now->modify('-' . $lookbackMinutes . ' minutes')->format('Y-m-d H:i:s');
        $windowEnd = $now->format('Y-m-d H:i:s');

        $sql = "SELECT r.id AS reminder_id,
                       r.meeting_id,
                       r.user_id,
                       r.scheduled_for,
                       r.error_message,
                       cm.title,
                       cm.description,
                       cm.meeting_date,
                       cm.start_time,
                       cm.end_time,
                       cm.timezone,
                       cm.google_meet_url,
                       cm.google_calendar_url,
                       u.full_name,
                       u.username
                FROM calendar_meeting_email_reminders r
                JOIN calendar_meetings cm ON cm.id = r.meeting_id
                JOIN users u ON u.id = r.user_id
                WHERE r.sent_at IS NULL
                  AND r.scheduled_for BETWEEN ? AND ?
                ORDER BY r.scheduled_for ASC, r.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$windowStart, $windowEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('calendar_meeting_reminder_mark_sent')) {
    function calendar_meeting_reminder_mark_sent($pdo, $reminderId, $errorMessage = null, $sentAt = null)
    {
        if (!calendar_meeting_email_reminders_ensure_schema($pdo)) {
            return false;
        }

        $sentAt = $sentAt instanceof DateTimeInterface ? $sentAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE calendar_meeting_email_reminders
             SET sent_at = ?, error_message = ?
             WHERE id = ?"
        );
        return $stmt->execute([$sentAt, $errorMessage !== null ? mb_substr((string)$errorMessage, 0, 4000) : null, (int)$reminderId]);
    }
}

if (!function_exists('calendar_meeting_reminder_mark_error')) {
    function calendar_meeting_reminder_mark_error($pdo, $reminderId, $errorMessage)
    {
        if (!calendar_meeting_email_reminders_ensure_schema($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE calendar_meeting_email_reminders
             SET error_message = ?
             WHERE id = ?"
        );
        return $stmt->execute([mb_substr(trim((string)$errorMessage), 0, 4000), (int)$reminderId]);
    }
}

if (!function_exists('calendar_meeting_reminder_delete_for_meeting')) {
    function calendar_meeting_reminder_delete_for_meeting($pdo, $meetingId)
    {
        if (!calendar_meeting_email_reminders_ensure_schema($pdo)) {
            return false;
        }

        $meetingId = (int)$meetingId;
        if ($meetingId <= 0) {
            return false;
        }

        $sql = "DELETE FROM calendar_meeting_email_reminders WHERE meeting_id = ?";
        $params = [$meetingId];
        $scope = tenant_get_scope($pdo, 'calendar_meeting_email_reminders');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}

if (!function_exists('calendar_meeting_reminder_reset_for_meeting')) {
    function calendar_meeting_reminder_reset_for_meeting($pdo, array $meeting, DateTimeImmutable $now = null)
    {
        $meetingId = (int)($meeting['id'] ?? 0);
        if ($meetingId <= 0) {
            return 0;
        }

        calendar_meeting_reminder_delete_for_meeting($pdo, $meetingId);
        return calendar_meeting_reminder_sync_queue_for_meeting($pdo, $meeting, $now);
    }
}
