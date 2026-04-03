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
