<?php
include "maintenance_guard.php";
include "DB_connection.php";
require_once "inc/workspace_screenshot_retention.php";

enforce_maintenance_script_access();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

function screenshot_retention_cleanup_write_log(string $message): void
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'screenshot_retention_cleanup.log', $line, FILE_APPEND);
}

$requestedOrgId = maintenance_get_requested_org_id();

if ($requestedOrgId !== null) {
    maintenance_bootstrap_tenant_context($requestedOrgId);
    $result = workspace_screenshot_retention_cleanup($pdo, $requestedOrgId);
    $deletedCount = (int)($result['deleted_count'] ?? 0);
    $retentionDays = (int)($result['retention_days'] ?? workspace_screenshot_retention_default_days());
    $message = "Screenshot retention cleanup done (org_id={$requestedOrgId}). Deleted rows: {$deletedCount}. Retention: {$retentionDays} day(s).";
    screenshot_retention_cleanup_write_log($message);
    echo $message . PHP_EOL;
    exit;
}

if (PHP_SAPI !== 'cli') {
    if (!maintenance_is_global_override_requested() || !maintenance_is_global_override_allowed()) {
        exit("Provide org_id to clean one workspace, or allow global mode and pass global=1.\n");
    }
}

$summary = workspace_screenshot_retention_cleanup_all($pdo);
$workspaceCount = count($summary['results'] ?? []);
$deletedCount = (int)($summary['total_deleted_count'] ?? 0);
$message = "Screenshot retention cleanup done (all workspaces). Deleted rows: {$deletedCount}. Workspaces checked: {$workspaceCount}.";
screenshot_retention_cleanup_write_log($message);
echo $message . PHP_EOL;
