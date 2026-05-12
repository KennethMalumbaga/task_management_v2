<?php
require_once __DIR__ . '/DB_connection.php';

function dashboard_perf_column_exists(PDO $pdo, string $table, string $column): bool
{
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
}

function dashboard_perf_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?
         LIMIT 1"
    );
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function dashboard_perf_add_index(PDO $pdo, string $table, string $index, array $columns): string
{
    foreach ($columns as $column) {
        if (!dashboard_perf_column_exists($pdo, $table, $column)) {
            return "Skipped {$index}: missing {$table}.{$column}";
        }
    }

    if (dashboard_perf_index_exists($pdo, $table, $index)) {
        return "Skipped {$index}: already exists";
    }

    $quotedColumns = array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', $columns);
    $sql = "ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD INDEX `"
        . str_replace('`', '``', $index) . "` (" . implode(', ', $quotedColumns) . ")";
    $pdo->exec($sql);

    return "Added {$index}";
}

$indexes = [
    ['attendance', 'idx_attendance_user_date_active', ['user_id', 'att_date', 'time_out']],
    ['notifications', 'idx_notifications_recipient_read_date', ['recipient', 'is_read', 'date', 'id']],
    ['task_assignees', 'idx_task_assignees_user_role_task', ['user_id', 'role', 'task_id']],
    ['task_assignees', 'idx_task_assignees_task_user_role', ['task_id', 'user_id', 'role']],
    ['group_members', 'idx_group_members_user_group', ['user_id', 'group_id']],
    ['group_message_reads', 'idx_group_message_reads_user_group_last', ['user_id', 'group_id', 'last_message_id']],
    ['group_messages', 'idx_group_messages_group_id_id', ['group_id', 'id']],
    ['leader_feedback', 'idx_leader_feedback_leader_task_rating', ['leader_id', 'task_id', 'rating']],
    ['screenshots', 'idx_screenshots_user_attendance_taken', ['user_id', 'attendance_id', 'taken_at']],
    ['bulletin_posts', 'idx_bulletin_posts_org_id_id', ['organization_id', 'id']],
    ['chats', 'idx_chats_receiver_opened_org', ['receiver_id', 'opened', 'organization_id', 'chat_id']],
    ['chats', 'idx_chats_conversation_org', ['sender_id', 'receiver_id', 'organization_id', 'chat_id']],
    ['attendance_pauses', 'idx_attendance_pauses_attendance_org_resumed', ['attendance_id', 'organization_id', 'resumed_at']],
    ['users', 'idx_users_org_role_id', ['organization_id', 'role', 'id']],
    ['organization_members', 'idx_org_members_org_role_user', ['organization_id', 'role', 'user_id']],
    ['subscriptions', 'idx_subscriptions_org_id', ['organization_id']],
];

header('Content-Type: text/plain; charset=utf-8');

foreach ($indexes as [$table, $index, $columns]) {
    try {
        echo dashboard_perf_add_index($pdo, $table, $index, $columns) . PHP_EOL;
    } catch (Throwable $e) {
        echo "Failed {$index}: " . $e->getMessage() . PHP_EOL;
    }
}
