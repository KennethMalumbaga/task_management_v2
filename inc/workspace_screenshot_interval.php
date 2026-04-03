<?php

if (!function_exists('workspace_screenshot_interval_default_min_minutes')) {
    function workspace_screenshot_interval_default_min_minutes()
    {
        return 20;
    }
}

if (!function_exists('workspace_screenshot_interval_default_max_minutes')) {
    function workspace_screenshot_interval_default_max_minutes()
    {
        return 30;
    }
}

if (!function_exists('workspace_screenshot_interval_min_allowed_minutes')) {
    function workspace_screenshot_interval_min_allowed_minutes()
    {
        return 5;
    }
}

if (!function_exists('workspace_screenshot_interval_max_allowed_minutes')) {
    function workspace_screenshot_interval_max_allowed_minutes()
    {
        return 180;
    }
}

if (!function_exists('workspace_screenshot_interval_schema_ready')) {
    function workspace_screenshot_interval_schema_ready($pdo)
    {
        return tenant_table_exists($pdo, 'organizations')
            && tenant_column_exists($pdo, 'organizations', 'screenshot_interval_min_minutes')
            && tenant_column_exists($pdo, 'organizations', 'screenshot_interval_max_minutes');
    }
}

if (!function_exists('workspace_screenshot_interval_normalize_minutes')) {
    function workspace_screenshot_interval_normalize_minutes($value)
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

        $minutes = (int)$value;
        $minAllowed = workspace_screenshot_interval_min_allowed_minutes();
        $maxAllowed = workspace_screenshot_interval_max_allowed_minutes();

        if ($minutes < $minAllowed || $minutes > $maxAllowed) {
            return null;
        }

        return $minutes;
    }
}

if (!function_exists('workspace_screenshot_interval_default_config')) {
    function workspace_screenshot_interval_default_config()
    {
        return [
            'min_minutes' => workspace_screenshot_interval_default_min_minutes(),
            'max_minutes' => workspace_screenshot_interval_default_max_minutes(),
        ];
    }
}

if (!function_exists('workspace_screenshot_interval_resolve_config')) {
    function workspace_screenshot_interval_resolve_config($minValue, $maxValue)
    {
        $minMinutes = workspace_screenshot_interval_normalize_minutes($minValue);
        $maxMinutes = workspace_screenshot_interval_normalize_minutes($maxValue);

        if ($minMinutes === null || $maxMinutes === null) {
            return null;
        }

        if ($minMinutes > $maxMinutes) {
            [$minMinutes, $maxMinutes] = [$maxMinutes, $minMinutes];
        }

        return [
            'min_minutes' => $minMinutes,
            'max_minutes' => $maxMinutes,
        ];
    }
}

if (!function_exists('workspace_screenshot_interval_fetch_minutes')) {
    function workspace_screenshot_interval_fetch_minutes($pdo, $orgId = null)
    {
        $default = workspace_screenshot_interval_default_config();
        $orgId = $orgId !== null ? (int)$orgId : tenant_get_current_org_id();

        if ($orgId <= 0 || !workspace_screenshot_interval_schema_ready($pdo)) {
            return $default;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT screenshot_interval_min_minutes, screenshot_interval_max_minutes
                 FROM organizations
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmt->execute([$orgId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return $default;
        }

        if (!$row) {
            return $default;
        }

        $resolved = workspace_screenshot_interval_resolve_config(
            $row['screenshot_interval_min_minutes'] ?? null,
            $row['screenshot_interval_max_minutes'] ?? null
        );

        return $resolved ?: $default;
    }
}
