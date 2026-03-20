<?php

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

require_once __DIR__ . '/workspace_theme.php';

if (!function_exists('tenant_get_current_org_id')) {
    require_once __DIR__ . '/tenant.php';
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    return;
}

$theme = workspace_theme_fetch($pdo, $orgId);
$css = workspace_theme_build_css($theme);
if ($css === '') {
    return;
}

echo "<style>\n" . $css . "</style>\n";
