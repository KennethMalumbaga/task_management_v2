<?php

require_once __DIR__ . '/tenant.php';

if (!function_exists('attendance_pause_ensure_schema')) {
    function attendance_pause_ensure_schema($pdo)
    {
        static $schemaReady = null;

        if ($schemaReady !== null) {
            return $schemaReady;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS attendance_pauses (
                    id INT NOT NULL AUTO_INCREMENT,
                    attendance_id INT NOT NULL,
                    user_id INT NOT NULL,
                    organization_id INT DEFAULT NULL,
                    pause_reason VARCHAR(255) NOT NULL,
                    paused_at DATETIME NOT NULL,
                    resumed_at DATETIME DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_attendance_pauses_attendance_id (attendance_id),
                    KEY idx_attendance_pauses_user_id (user_id),
                    KEY idx_attendance_pauses_open (attendance_id, resumed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            if (tenant_table_exists($pdo, 'attendance_pauses') && !tenant_column_exists($pdo, 'attendance_pauses', 'organization_id')) {
                $pdo->exec("ALTER TABLE attendance_pauses ADD COLUMN organization_id INT DEFAULT NULL AFTER user_id");
            }
        } catch (Throwable $e) {
            // Best effort. The feature will degrade gracefully if DDL is blocked.
        }

        $schemaReady = tenant_table_exists($pdo, 'attendance_pauses');
        return $schemaReady;
    }
}

if (!function_exists('attendance_pause_build_datetime')) {
    function attendance_pause_build_datetime($attDate, $value, $fallbackDate = null)
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return str_replace('T', ' ', $raw);
        }

        $datePart = trim((string)$attDate);
        if ($datePart === '') {
            $datePart = $fallbackDate ? trim((string)$fallbackDate) : date('Y-m-d');
        }

        return $datePart . ' ' . $raw;
    }
}

if (!function_exists('attendance_pause_get_summary_map')) {
    function attendance_pause_get_summary_map($pdo, array $attendanceIds, $organizationId = null, $asOfDateTime = null)
    {
        $summaryMap = [];
        $attendanceIds = array_values(array_filter(array_map('intval', $attendanceIds)));

        if (!$attendanceIds || !attendance_pause_ensure_schema($pdo)) {
            return $summaryMap;
        }

        $placeholders = implode(',', array_fill(0, count($attendanceIds), '?'));
        $sql = "SELECT attendance_id, pause_reason, paused_at, resumed_at
                FROM attendance_pauses
                WHERE attendance_id IN ($placeholders)";
        $params = $attendanceIds;
        $scope = tenant_get_scope($pdo, 'attendance_pauses', '', 'AND', 'organization_id', $organizationId);
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resolvedAsOf = $asOfDateTime ? str_replace('T', ' ', (string)$asOfDateTime) : date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $attendanceId = (int)($row['attendance_id'] ?? 0);
            if ($attendanceId <= 0) {
                continue;
            }

            if (!isset($summaryMap[$attendanceId])) {
                $summaryMap[$attendanceId] = [
                    'paused_seconds' => 0,
                    'is_paused' => false,
                    'pause_reason' => null,
                    'paused_at' => null,
                ];
            }

            $pausedAt = trim((string)($row['paused_at'] ?? ''));
            if ($pausedAt === '') {
                continue;
            }

            $pausedTs = strtotime($pausedAt);
            if ($pausedTs === false) {
                continue;
            }

            $resumeValue = trim((string)($row['resumed_at'] ?? ''));
            if ($resumeValue === '') {
                $resumeValue = $resolvedAsOf;
                $summaryMap[$attendanceId]['is_paused'] = true;
                $summaryMap[$attendanceId]['pause_reason'] = trim((string)($row['pause_reason'] ?? '')) ?: null;
                $summaryMap[$attendanceId]['paused_at'] = $pausedAt;
            }

            $resumeTs = strtotime(str_replace('T', ' ', $resumeValue));
            if ($resumeTs === false || $resumeTs <= $pausedTs) {
                continue;
            }

            $summaryMap[$attendanceId]['paused_seconds'] += ($resumeTs - $pausedTs);
        }

        return $summaryMap;
    }
}

if (!function_exists('attendance_pause_get_active')) {
    function attendance_pause_get_active($pdo, $attendanceId, $userId = null, $organizationId = null)
    {
        $attendanceId = (int)$attendanceId;
        $userId = $userId !== null ? (int)$userId : null;

        if ($attendanceId <= 0 || !attendance_pause_ensure_schema($pdo)) {
            return null;
        }

        $sql = "SELECT id, attendance_id, user_id, pause_reason, paused_at
                FROM attendance_pauses
                WHERE attendance_id = ?
                  AND resumed_at IS NULL";
        $params = [$attendanceId];

        if ($userId !== null && $userId > 0) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $scope = tenant_get_scope($pdo, 'attendance_pauses', '', 'AND', 'organization_id', $organizationId);
        $sql .= $scope['sql'] . "
                ORDER BY id DESC
                LIMIT 1";
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('attendance_pause_close_active')) {
    function attendance_pause_close_active($pdo, $attendanceId, $organizationId = null, $resumeDateTime = null)
    {
        $attendanceId = (int)$attendanceId;
        if ($attendanceId <= 0 || !attendance_pause_ensure_schema($pdo)) {
            return false;
        }

        $resolvedResume = trim((string)$resumeDateTime);
        if ($resolvedResume === '') {
            $resolvedResume = date('Y-m-d H:i:s');
        }

        $sql = "UPDATE attendance_pauses
                SET resumed_at = ?
                WHERE attendance_id = ?
                  AND resumed_at IS NULL";
        $params = [$resolvedResume, $attendanceId];
        $scope = tenant_get_scope($pdo, 'attendance_pauses', '', 'AND', 'organization_id', $organizationId);
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('attendance_pause_calculate_effective_seconds')) {
    function attendance_pause_calculate_effective_seconds($pdo, array $attendanceRow, $organizationId = null, $endDateTime = null)
    {
        $attendanceId = isset($attendanceRow['id']) ? (int)$attendanceRow['id'] : 0;
        $attDate = trim((string)($attendanceRow['att_date'] ?? ''));
        $timeInRaw = trim((string)($attendanceRow['time_in'] ?? ''));
        $timeOutRaw = trim((string)($attendanceRow['time_out'] ?? ''));

        if ($attendanceId <= 0 || $timeInRaw === '') {
            return 0;
        }

        $timeInValue = attendance_pause_build_datetime($attDate, $timeInRaw, $attDate);
        $timeInTs = $timeInValue ? strtotime($timeInValue) : false;
        if ($timeInTs === false) {
            return 0;
        }

        $resolvedEnd = trim((string)$endDateTime);
        if ($resolvedEnd === '') {
            if ($timeOutRaw !== '' && $timeOutRaw !== '00:00:00') {
                $resolvedEnd = attendance_pause_build_datetime($attDate, $timeOutRaw, $attDate);
            } elseif ($attDate !== '' && $attDate !== date('Y-m-d')) {
                $resolvedEnd = $timeInValue;
            } else {
                $resolvedEnd = date('Y-m-d H:i:s');
            }
        } else {
            $resolvedEnd = attendance_pause_build_datetime($attDate, $resolvedEnd, $attDate);
        }

        $timeOutTs = $resolvedEnd ? strtotime($resolvedEnd) : false;
        if ($timeOutTs === false || $timeOutTs <= $timeInTs) {
            return 0;
        }

        $summaryMap = attendance_pause_get_summary_map($pdo, [$attendanceId], $organizationId, $resolvedEnd);
        $pausedSeconds = (int)($summaryMap[$attendanceId]['paused_seconds'] ?? 0);

        return max(0, ($timeOutTs - $timeInTs) - $pausedSeconds);
    }
}
