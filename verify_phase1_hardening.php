<?php
require_once __DIR__ . '/maintenance_guard.php';
enforce_maintenance_script_access();

require_once __DIR__ . '/DB_connection.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$hasFailures = false;
$hasWarnings = false;

$printLine = static function (string $status, string $message) use (&$hasFailures, &$hasWarnings): void {
    if ($status === 'FAIL') {
        $hasFailures = true;
    } elseif ($status === 'WARN') {
        $hasWarnings = true;
    }

    echo '[' . $status . '] ' . $message . PHP_EOL;
};

$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1"
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
};

$indexExists = static function (PDO $pdo, string $index): ?string {
    $stmt = $pdo->prepare(
        "SELECT table_name
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND index_name = ?
         LIMIT 1"
    );
    $stmt->execute([$index]);
    $table = $stmt->fetchColumn();
    return $table !== false ? (string)$table : null;
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
};

echo 'Phase 1 SaaS Hardening Verification' . PHP_EOL;
echo 'Database: ' . (string)$pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'Checked at: ' . date('c') . PHP_EOL . PHP_EOL;

$criticalTables = [
    'attendance',
    'notifications',
    'task_assignees',
    'group_members',
    'group_message_reads',
    'group_messages',
    'screenshots',
    'chats',
    'chat_attachments',
    'group_message_attachments',
    'users',
    'tasks',
];

$optionalTables = [
    'organizations',
    'organization_members',
    'subscriptions',
    'bulletin_posts',
    'leader_feedback',
    'attendance_pauses',
    'chat_hidden_threads',
    'chat_typing_statuses',
];

echo 'Tables' . PHP_EOL;
foreach ($criticalTables as $table) {
    $printLine($tableExists($pdo, $table) ? 'OK' : 'FAIL', "critical table {$table}");
}
foreach ($optionalTables as $table) {
    $printLine($tableExists($pdo, $table) ? 'OK' : 'WARN', "optional/runtime table {$table}");
}

echo PHP_EOL . 'Performance Indexes' . PHP_EOL;
$indexes = [
    'idx_attendance_user_date_active' => ['table' => 'attendance', 'critical' => true],
    'idx_notifications_recipient_read_date' => ['table' => 'notifications', 'critical' => true],
    'idx_task_assignees_user_role_task' => ['table' => 'task_assignees', 'critical' => true],
    'idx_task_assignees_task_user_role' => ['table' => 'task_assignees', 'critical' => true],
    'idx_group_members_user_group' => ['table' => 'group_members', 'critical' => true],
    'idx_group_message_reads_user_group_last' => ['table' => 'group_message_reads', 'critical' => true],
    'idx_group_messages_group_id_id' => ['table' => 'group_messages', 'critical' => true],
    'idx_screenshots_user_attendance_taken' => ['table' => 'screenshots', 'critical' => true],
    'idx_chats_receiver_opened_org' => ['table' => 'chats', 'critical' => true],
    'idx_chats_conversation_org' => ['table' => 'chats', 'critical' => true],
    'idx_chats_receiver_sender_org' => ['table' => 'chats', 'critical' => true],
    'idx_chat_attachments_chat_id' => ['table' => 'chat_attachments', 'critical' => true],
    'idx_group_msg_attachments_message' => ['table' => 'group_message_attachments', 'critical' => true],
    'idx_group_message_reads_group_user_last' => ['table' => 'group_message_reads', 'critical' => true],
    'idx_bulletin_posts_org_id_id' => ['table' => 'bulletin_posts', 'critical' => false],
    'idx_leader_feedback_leader_task_rating' => ['table' => 'leader_feedback', 'critical' => false],
    'idx_attendance_pauses_attendance_org_resumed' => ['table' => 'attendance_pauses', 'critical' => false],
    'idx_users_org_role_id' => ['table' => 'users', 'critical' => false],
    'idx_org_members_org_role_user' => ['table' => 'organization_members', 'critical' => false],
    'idx_subscriptions_org_id' => ['table' => 'subscriptions', 'critical' => false],
    'idx_chat_hidden_user_org_type' => ['table' => 'chat_hidden_threads', 'critical' => false],
    'idx_chat_typing_direct_status' => ['table' => 'chat_typing_statuses', 'critical' => false],
    'idx_chat_typing_group_status' => ['table' => 'chat_typing_statuses', 'critical' => false],
];

foreach ($indexes as $index => $meta) {
    $table = $meta['table'];
    $tableReady = $tableExists($pdo, $table);
    if (!$tableReady) {
        $printLine(!empty($meta['critical']) ? 'FAIL' : 'WARN', "index {$index} skipped because table {$table} is missing");
        continue;
    }

    $actualTable = $indexExists($pdo, $index);
    $printLine($actualTable ? 'OK' : (!empty($meta['critical']) ? 'FAIL' : 'WARN'), "index {$index}" . ($actualTable ? " on {$actualTable}" : ' missing'));
}

echo PHP_EOL . 'Key Columns' . PHP_EOL;
$columns = [
    ['attendance', 'organization_id', false],
    ['attendance', 'last_heartbeat_at', false],
    ['chats', 'organization_id', false],
    ['chats', 'deleted_at', false],
    ['group_messages', 'organization_id', false],
    ['group_messages', 'deleted_at', false],
    ['users', 'organization_id', false],
    ['users', 'last_active_at', false],
    ['tasks', 'organization_id', false],
    ['task_assignees', 'organization_id', false],
];

foreach ($columns as [$table, $column, $critical]) {
    if (!$tableExists($pdo, $table)) {
        $printLine($critical ? 'FAIL' : 'WARN', "column {$table}.{$column} skipped because table is missing");
        continue;
    }
    $printLine($columnExists($pdo, $table, $column) ? 'OK' : ($critical ? 'FAIL' : 'WARN'), "column {$table}.{$column}");
}

echo PHP_EOL . 'Runtime Config' . PHP_EOL;
$htaccess = __DIR__ . '/.htaccess';
$htaccessBody = is_readable($htaccess) ? (string)file_get_contents($htaccess) : '';
$printLine(strpos($htaccessBody, 'mod_expires.c') !== false ? 'OK' : 'WARN', '.htaccess has guarded expires rules');
$printLine(strpos($htaccessBody, 'mod_headers.c') !== false ? 'OK' : 'WARN', '.htaccess has guarded header rules');
$printLine(is_dir(__DIR__ . '/tmp') && is_writable(__DIR__ . '/tmp') ? 'OK' : 'WARN', 'tmp directory is writable for performance.log');

$opcacheEnabled = filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if (PHP_SAPI === 'cli') {
    $printLine($opcacheEnabled ? 'OK' : 'WARN', 'OPcache enabled for this PHP context; web PHP may differ');
} else {
    $printLine($opcacheEnabled ? 'OK' : 'WARN', 'OPcache enabled for web PHP');
}

echo PHP_EOL;
if ($hasFailures) {
    echo "Result: FAIL. Run run_migration_dashboard_performance_indexes.php and review missing critical items." . PHP_EOL;
    exit(1);
}

if ($hasWarnings) {
    echo "Result: WARN. Core checks passed, but review optional/runtime items for SaaS readiness." . PHP_EOL;
    exit(0);
}

echo "Result: OK. Phase 1 hardening checks passed." . PHP_EOL;
