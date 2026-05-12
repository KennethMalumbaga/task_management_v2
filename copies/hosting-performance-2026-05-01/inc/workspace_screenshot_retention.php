<?php

if (!function_exists('workspace_screenshot_retention_default_days')) {
    function workspace_screenshot_retention_default_days()
    {
        return 7;
    }
}

if (!function_exists('workspace_screenshot_retention_min_days')) {
    function workspace_screenshot_retention_min_days()
    {
        return 1;
    }
}

if (!function_exists('workspace_screenshot_retention_max_days')) {
    function workspace_screenshot_retention_max_days()
    {
        return 365;
    }
}

if (!function_exists('workspace_screenshot_retention_schema_ready')) {
    function workspace_screenshot_retention_schema_ready($pdo)
    {
        return tenant_table_exists($pdo, 'organizations')
            && tenant_column_exists($pdo, 'organizations', 'screenshot_retention_days');
    }
}

if (!function_exists('workspace_screenshot_retention_normalize_days')) {
    function workspace_screenshot_retention_normalize_days($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        if (is_string($value) && !preg_match('/^\d+$/', $value)) {
            return null;
        }

        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $days = (int)$value;
        $min = workspace_screenshot_retention_min_days();
        $max = workspace_screenshot_retention_max_days();

        if ($days < $min || $days > $max) {
            return null;
        }

        return $days;
    }
}

if (!function_exists('workspace_screenshot_retention_fetch_days')) {
    function workspace_screenshot_retention_fetch_days($pdo, $orgId = null)
    {
        $default = workspace_screenshot_retention_default_days();
        $orgId = $orgId !== null ? (int)$orgId : tenant_get_current_org_id();

        if ($orgId <= 0 || !workspace_screenshot_retention_schema_ready($pdo)) {
            return $default;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT screenshot_retention_days
                 FROM organizations
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmt->execute([$orgId]);
            $value = $stmt->fetchColumn();
        } catch (Throwable $e) {
            return $default;
        }

        $days = workspace_screenshot_retention_normalize_days($value);
        return $days !== null ? $days : $default;
    }
}

if (!function_exists('workspace_screenshot_retention_resolve_file_path')) {
    function workspace_screenshot_retention_resolve_file_path($imagePath)
    {
        $imagePath = trim((string)$imagePath);
        if ($imagePath === '') {
            return null;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);
        $normalized = ltrim($normalized, DIRECTORY_SEPARATOR);
        $allowedPrefix = 'screenshots' . DIRECTORY_SEPARATOR;

        if (strpos($normalized, $allowedPrefix) !== 0) {
            return null;
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalized;
    }
}

if (!function_exists('workspace_screenshot_retention_cleanup')) {
    function workspace_screenshot_retention_cleanup($pdo, $orgId = null)
    {
        static $cache = [];

        $resolvedOrgId = $orgId !== null ? (int)$orgId : tenant_get_current_org_id();
        $cacheKey = (is_object($pdo) ? spl_object_hash($pdo) : 'default') . ':' . (string)$resolvedOrgId;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $retentionDays = workspace_screenshot_retention_fetch_days($pdo, $resolvedOrgId);
        $cutoffTs = strtotime('-' . $retentionDays . ' days');
        $retentionCutoff = $cutoffTs !== false ? date('Y-m-d H:i:s', $cutoffTs) : null;
        $result = [
            'deleted_count' => 0,
            'retention_days' => (int)$retentionDays,
            'cutoff' => $retentionCutoff,
        ];

        if ($retentionCutoff === null) {
            $cache[$cacheKey] = $result;
            return $result;
        }

        if (tenant_column_exists($pdo, 'screenshots', 'organization_id') && $resolvedOrgId <= 0) {
            $cache[$cacheKey] = $result;
            return $result;
        }

        try {
            $sql = "SELECT id, image_path FROM screenshots WHERE taken_at < ?";
            $params = [$retentionCutoff];
            $scope = tenant_get_scope($pdo, 'screenshots', '', 'AND', 'organization_id', $resolvedOrgId > 0 ? $resolvedOrgId : null);
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $oldRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($oldRecords)) {
                foreach ($oldRecords as $record) {
                    $filePath = workspace_screenshot_retention_resolve_file_path($record['image_path'] ?? '');
                    if ($filePath && is_file($filePath)) {
                        @unlink($filePath);
                    }
                }

                $deleteSql = "DELETE FROM screenshots WHERE taken_at < ?";
                $deleteScope = tenant_get_scope($pdo, 'screenshots', '', 'AND', 'organization_id', $resolvedOrgId > 0 ? $resolvedOrgId : null);
                $deleteSql .= $deleteScope['sql'];

                $stmtDelete = $pdo->prepare($deleteSql);
                $stmtDelete->execute(array_merge([$retentionCutoff], $deleteScope['params']));
                $result['deleted_count'] = count($oldRecords);
            }
        } catch (Throwable $e) {
            $result['error'] = true;
        }

        $cache[$cacheKey] = $result;
        return $result;
    }
}

if (!function_exists('workspace_screenshot_retention_list_org_ids')) {
    function workspace_screenshot_retention_list_org_ids($pdo)
    {
        if (!tenant_column_exists($pdo, 'screenshots', 'organization_id')) {
            return [null];
        }

        try {
            if (tenant_table_exists($pdo, 'organizations')) {
                $stmt = $pdo->query("SELECT id FROM organizations ORDER BY id ASC");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $orgIds = [];
                foreach ($rows as $row) {
                    $orgId = (int)$row;
                    if ($orgId > 0) {
                        $orgIds[] = $orgId;
                    }
                }
                return $orgIds;
            }

            $stmt = $pdo->query(
                "SELECT DISTINCT organization_id
                 FROM screenshots
                 WHERE organization_id IS NOT NULL
                 ORDER BY organization_id ASC"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $orgIds = [];
            foreach ($rows as $row) {
                $orgId = (int)$row;
                if ($orgId > 0) {
                    $orgIds[] = $orgId;
                }
            }
            return $orgIds;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('workspace_screenshot_retention_cleanup_all')) {
    function workspace_screenshot_retention_cleanup_all($pdo)
    {
        $orgIds = workspace_screenshot_retention_list_org_ids($pdo);
        $results = [];
        $totalDeleted = 0;

        if ($orgIds === [null]) {
            $result = workspace_screenshot_retention_cleanup($pdo, null);
            $results[] = [
                'scope' => 'GLOBAL',
                'org_id' => null,
                'deleted_count' => (int)($result['deleted_count'] ?? 0),
                'retention_days' => (int)($result['retention_days'] ?? workspace_screenshot_retention_default_days()),
            ];
            return [
                'total_deleted_count' => (int)($result['deleted_count'] ?? 0),
                'results' => $results,
            ];
        }

        foreach ($orgIds as $orgId) {
            $orgId = (int)$orgId;
            if ($orgId <= 0) {
                continue;
            }

            $result = workspace_screenshot_retention_cleanup($pdo, $orgId);
            $deletedCount = (int)($result['deleted_count'] ?? 0);
            $totalDeleted += $deletedCount;
            $results[] = [
                'scope' => 'org_id=' . $orgId,
                'org_id' => $orgId,
                'deleted_count' => $deletedCount,
                'retention_days' => (int)($result['retention_days'] ?? workspace_screenshot_retention_default_days()),
            ];
        }

        return [
            'total_deleted_count' => $totalDeleted,
            'results' => $results,
        ];
    }
}

if (!function_exists('workspace_screenshot_retention_self_cleanup_interval_seconds')) {
    function workspace_screenshot_retention_self_cleanup_interval_seconds()
    {
        return 3600;
    }
}

if (!function_exists('workspace_screenshot_retention_self_cleanup_state_path')) {
    function workspace_screenshot_retention_self_cleanup_state_path($orgId = null)
    {
        $scope = ((int)$orgId > 0) ? ('org_' . (int)$orgId) : 'global';
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'screenshot_retention_self_cleanup_' . $scope . '.json';
    }
}

if (!function_exists('workspace_screenshot_retention_maybe_cleanup')) {
    function workspace_screenshot_retention_maybe_cleanup($pdo, $orgId = null)
    {
        $resolvedOrgId = $orgId !== null ? (int)$orgId : tenant_get_current_org_id();
        $result = [
            'ran' => false,
            'deleted_count' => 0,
            'retention_days' => workspace_screenshot_retention_default_days(),
            'reason' => 'skipped',
        ];

        if (tenant_column_exists($pdo, 'screenshots', 'organization_id') && $resolvedOrgId <= 0) {
            $result['reason'] = 'missing_workspace';
            return $result;
        }

        $statePath = workspace_screenshot_retention_self_cleanup_state_path($resolvedOrgId > 0 ? $resolvedOrgId : null);
        $stateDir = dirname($statePath);
        if (!is_dir($stateDir) && !@mkdir($stateDir, 0777, true) && !is_dir($stateDir)) {
            $result['reason'] = 'state_dir_unavailable';
            return $result;
        }

        $handle = @fopen($statePath, 'c+');
        if (!$handle) {
            $result['reason'] = 'state_file_unavailable';
            return $result;
        }

        try {
            if (!@flock($handle, LOCK_EX | LOCK_NB)) {
                $result['reason'] = 'locked';
                return $result;
            }

            $rawState = stream_get_contents($handle);
            $state = is_string($rawState) && $rawState !== ''
                ? json_decode($rawState, true)
                : [];
            if (!is_array($state)) {
                $state = [];
            }

            $lastRunAt = isset($state['last_run_at']) ? strtotime((string)$state['last_run_at']) : false;
            $interval = workspace_screenshot_retention_self_cleanup_interval_seconds();
            if ($lastRunAt !== false && (time() - $lastRunAt) < $interval) {
                $result['reason'] = 'throttled';
                if (isset($state['retention_days'])) {
                    $result['retention_days'] = (int)$state['retention_days'];
                }
                return $result;
            }

            $cleanup = workspace_screenshot_retention_cleanup($pdo, $resolvedOrgId > 0 ? $resolvedOrgId : null);
            $result['ran'] = true;
            $result['deleted_count'] = (int)($cleanup['deleted_count'] ?? 0);
            $result['retention_days'] = (int)($cleanup['retention_days'] ?? workspace_screenshot_retention_default_days());
            $result['reason'] = !empty($cleanup['error']) ? 'error' : 'completed';

            $state = [
                'last_run_at' => date('c'),
                'deleted_count' => $result['deleted_count'],
                'retention_days' => $result['retention_days'],
                'status' => $result['reason'],
            ];
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state));
            fflush($handle);

            return $result;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
