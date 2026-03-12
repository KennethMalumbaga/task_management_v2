<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('attendance_adjustment_ensure_schema')) {
    function attendance_adjustment_ensure_schema($pdo)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS attendance_adjustments (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    att_date DATE NOT NULL,
                    hours_deducted DECIMAL(6,2) NOT NULL DEFAULT 0,
                    reason VARCHAR(255) DEFAULT NULL,
                    created_by INT NOT NULL,
                    updated_by INT DEFAULT NULL,
                    organization_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_att_adj_user_date (user_id, att_date),
                    KEY idx_att_adj_org (organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            // Best effort. If DDL fails, adjustments will be unavailable.
        }

        $ready = tenant_table_exists($pdo, 'attendance_adjustments');
        return $ready;
    }
}

if (!function_exists('attendance_adjustment_get_range_map')) {
    function attendance_adjustment_get_range_map($pdo, array $userIds, $startDate, $endDate)
    {
        $map = [];
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (!$userIds || !attendance_adjustment_ensure_schema($pdo)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT user_id, SUM(hours_deducted) AS hours_deducted
                FROM attendance_adjustments
                WHERE user_id IN ($placeholders)
                  AND att_date BETWEEN ? AND ?";
        $params = array_merge($userIds, [$startDate, $endDate]);
        $scope = tenant_get_scope($pdo, 'attendance_adjustments', '', 'AND', 'organization_id');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);
        $sql .= " GROUP BY user_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $uid = (int)($row['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $map[$uid] = $row;
        }
        return $map;
    }
}

if (!function_exists('attendance_adjustment_get_daily_map')) {
    function attendance_adjustment_get_daily_map($pdo, $userId, $startDate, $endDate)
    {
        $map = [];
        $userId = (int)$userId;
        if ($userId <= 0 || !attendance_adjustment_ensure_schema($pdo)) {
            return $map;
        }

        $sql = "SELECT att_date, hours_deducted, reason, updated_at
                FROM attendance_adjustments
                WHERE user_id = ?
                  AND att_date BETWEEN ? AND ?";
        $params = [$userId, $startDate, $endDate];
        $scope = tenant_get_scope($pdo, 'attendance_adjustments', '', 'AND', 'organization_id');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);
        $sql .= " ORDER BY att_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $dateKey = (string)($row['att_date'] ?? '');
            if ($dateKey === '') {
                continue;
            }
            $map[$dateKey] = $row;
        }
        return $map;
    }
}

if (!function_exists('attendance_adjustment_upsert')) {
    function attendance_adjustment_upsert($pdo, $userId, $attDate, $hours, $reason, $adminId)
    {
        if (!attendance_adjustment_ensure_schema($pdo)) {
            return ['ok' => false, 'error' => 'Adjustments table is unavailable.'];
        }

        $userId = (int)$userId;
        $adminId = (int)$adminId;
        $attDate = trim((string)$attDate);
        $hours = round((float)$hours, 2);
        $reason = trim((string)$reason);
        $orgId = tenant_get_current_org_id();

        if ($userId <= 0 || $adminId <= 0 || $attDate === '') {
            return ['ok' => false, 'error' => 'Invalid adjustment request.'];
        }

        if ($hours < 0) {
            $hours = 0;
        }

        $scope = tenant_get_scope($pdo, 'attendance_adjustments', '', 'AND', 'organization_id', $orgId);

        if ($hours <= 0) {
            $sql = "DELETE FROM attendance_adjustments WHERE user_id = ? AND att_date = ?";
            $params = [$userId, $attDate];
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ['ok' => true, 'deleted' => true];
        }

        $sql = "SELECT id FROM attendance_adjustments WHERE user_id = ? AND att_date = ?";
        $params = [$userId, $attDate];
        $sql .= $scope['sql'] . " LIMIT 1";
        $params = array_merge($params, $scope['params']);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $updateSql = "UPDATE attendance_adjustments
                          SET hours_deducted = ?, reason = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?";
            $updateParams = [$hours, $reason !== '' ? $reason : null, $adminId, (int)$existingId];
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);
            return ['ok' => true, 'updated' => true];
        }

        if (tenant_column_exists($pdo, 'attendance_adjustments', 'organization_id') && $orgId) {
            $insertSql = "INSERT INTO attendance_adjustments
                          (user_id, att_date, hours_deducted, reason, created_by, updated_by, organization_id)
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertParams = [$userId, $attDate, $hours, $reason !== '' ? $reason : null, $adminId, $adminId, $orgId];
        } else {
            $insertSql = "INSERT INTO attendance_adjustments
                          (user_id, att_date, hours_deducted, reason, created_by, updated_by)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $insertParams = [$userId, $attDate, $hours, $reason !== '' ? $reason : null, $adminId, $adminId];
        }

        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute($insertParams);
        return ['ok' => true, 'created' => true];
    }
}

