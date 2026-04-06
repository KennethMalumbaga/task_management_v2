<?php
date_default_timezone_set('Asia/Manila');

include "maintenance_guard.php";
include "DB_connection.php";
require_once __DIR__ . '/app/mail_config.php';
require_once __DIR__ . '/app/helpers/subscription_reminder.php';

enforce_maintenance_script_access();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

$warningDays = 15;
$warningDaysCli = maintenance_get_cli_option('warning-days');
if ($warningDaysCli !== null && $warningDaysCli !== '') {
    $warningDays = max(1, (int)$warningDaysCli);
} elseif (isset($_GET['warning_days'])) {
    $warningDays = max(1, (int)$_GET['warning_days']);
}

$ignoreWindow = maintenance_truthy(maintenance_get_cli_option('ignore-window'))
    || maintenance_truthy($_GET['ignore_window'] ?? null);

echo "TaskFlow subscription reminder run\n";
echo "Now: " . date('Y-m-d H:i:s T') . "\n";
echo "Reminder window: {$warningDays} days\n";
echo "Ignore window: " . ($ignoreWindow ? "yes" : "no") . "\n";

if (!maintenance_is_tenant_enabled($pdo) || !tenant_table_exists($pdo, 'organizations')) {
    echo "Tenant workspace billing is not enabled. Nothing to process.\n";
    exit();
}

$context = maintenance_require_org_context($pdo);
$orgIds = [];

if (!empty($context['global'])) {
    $stmt = $pdo->query("SELECT id FROM organizations ORDER BY id ASC");
    $orgIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    echo "Scope: global\n";
} else {
    $orgIds = [(int)$context['org_id']];
    echo "Scope: org_id=" . (int)$context['org_id'] . "\n";
}

if (empty($orgIds)) {
    echo "No workspaces found.\n";
    exit();
}

$checked = 0;
$eligible = 0;
$created = 0;
$emailsSent = 0;
$duplicates = 0;
$eligibleButSkipped = 0;
$statusUpdates = 0;
$details = [];

foreach ($orgIds as $rawOrgId) {
    $orgId = (int)$rawOrgId;
    if ($orgId <= 0) {
        continue;
    }

    $checked++;
    $syncResult = tenant_sync_workspace_subscription_status($pdo, $orgId);
    if (!empty($syncResult['changed'])) {
        $statusUpdates++;
    }

    $result = tm_dispatch_workspace_subscription_reminder($pdo, $orgId, null, $warningDays, $ignoreWindow);
    $notice = is_array($result['notice'] ?? null) ? $result['notice'] : [];
    $ownerContact = is_array($result['owner_contact'] ?? null) ? $result['owner_contact'] : [];
    $workspaceName = trim((string)($ownerContact['workspace_name'] ?? ''));
    if ($workspaceName === '') {
        $workspaceName = 'Workspace #' . $orgId;
    }

    if (!empty($notice['show'])) {
        $eligible++;
    }

    if (!empty($result['created'])) {
        $created++;
        if (!empty($result['email_sent'])) {
            $emailsSent++;
        }

        $detail = "[ORG {$orgId}] {$workspaceName}: reminder created";
        if ($ignoreWindow) {
            $detail .= " with manual override";
        }
        if (!empty($ownerContact['email'])) {
            $detail .= " and emailed to " . $ownerContact['email'];
        }
        if (!empty($result['reason'])) {
            $detail .= " (" . $result['reason'] . ")";
        }
        $details[] = $detail;
        continue;
    }

    if (!empty($result['duplicate'])) {
        $duplicates++;
        $details[] = "[ORG {$orgId}] {$workspaceName}: reminder already exists for this billing period.";
        continue;
    }

    if (!empty($notice['show'])) {
        $eligibleButSkipped++;
        $details[] = "[ORG {$orgId}] {$workspaceName}: eligible but not sent"
            . (!empty($result['reason']) ? " ({$result['reason']})" : '.');
    }
}

echo "Checked workspaces: {$checked}\n";
echo "Statuses updated: {$statusUpdates}\n";
echo "Within reminder window: {$eligible}\n";
echo "Notifications created: {$created}\n";
echo "Emails sent: {$emailsSent}\n";
echo "Duplicates skipped: {$duplicates}\n";
echo "Eligible but not sent: {$eligibleButSkipped}\n";

if (!empty($details)) {
    echo "\nDetails:\n";
    foreach ($details as $line) {
        echo "- {$line}\n";
    }
}
