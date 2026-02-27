<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

function delete_workspace_should_return_to_dashboard(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    return isset($_GET['return_to']) && $_GET['return_to'] === 'maintenance_dashboard';
}

function delete_workspace_redirect_to_dashboard(string $message, bool $isError = false): void
{
    $param = $isError ? 'error' : 'success';
    header("Location: maintenance_dashboard.php?{$param}=" . urlencode($message));
    exit();
}

function delete_workspace_is_safe_identifier(string $value): bool
{
    return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
}

function delete_workspace_existing_columns(PDO $pdo, string $table, array $columns): array
{
    if (!delete_workspace_is_safe_identifier($table) || !tenant_table_exists($pdo, $table)) {
        return [];
    }

    $validColumns = [];
    foreach ($columns as $column) {
        if (!is_string($column) || !delete_workspace_is_safe_identifier($column)) {
            continue;
        }
        if (tenant_column_exists($pdo, $table, $column)) {
            $validColumns[] = $column;
        }
    }

    return array_values(array_unique($validColumns));
}

function delete_workspace_fetch_workspace_user_data(PDO $pdo, int $orgId): array
{
    if (!tenant_table_exists($pdo, 'users') || !tenant_column_exists($pdo, 'users', 'organization_id')) {
        return ['ids' => [], 'usernames' => []];
    }

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE organization_id = ?");
    $stmt->execute([$orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ids = [];
    $usernames = [];
    foreach ($rows as $row) {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        if ($id > 0) {
            $ids[] = $id;
        }

        $username = trim((string)($row['username'] ?? ''));
        if ($username !== '') {
            $usernames[] = $username;
        }
    }

    return [
        'ids' => array_values(array_unique($ids)),
        'usernames' => array_values(array_unique($usernames)),
    ];
}

function delete_workspace_fetch_workspace_task_ids(PDO $pdo, int $orgId): array
{
    if (!tenant_table_exists($pdo, 'tasks') || !tenant_column_exists($pdo, 'tasks', 'organization_id')) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE organization_id = ?");
    $stmt->execute([$orgId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $taskIds = [];
    foreach ($rows as $row) {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        if ($id > 0) {
            $taskIds[] = $id;
        }
    }

    return array_values(array_unique($taskIds));
}

function delete_workspace_delete_by_ids(PDO $pdo, string $table, array $columns, array $ids): int
{
    $cleanIds = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $cleanIds[] = $id;
        }
    }
    $cleanIds = array_values(array_unique($cleanIds));
    if (empty($cleanIds)) {
        return 0;
    }

    $columns = delete_workspace_existing_columns($pdo, $table, $columns);
    if (empty($columns)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $clauses = [];
    $params = [];
    foreach ($columns as $column) {
        $clauses[] = "{$column} IN ({$placeholders})";
        $params = array_merge($params, $cleanIds);
    }

    $sql = "DELETE FROM {$table} WHERE " . implode(' OR ', $clauses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

function delete_workspace_delete_by_values(PDO $pdo, string $table, string $column, array $values): int
{
    $columns = delete_workspace_existing_columns($pdo, $table, [$column]);
    if (empty($columns)) {
        return 0;
    }

    $cleanValues = [];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $cleanValues[] = $value;
        }
    }
    $cleanValues = array_values(array_unique($cleanValues));
    if (empty($cleanValues)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($cleanValues), '?'));
    $sql = "DELETE FROM {$table} WHERE {$columns[0]} IN ({$placeholders})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cleanValues);

    return $stmt->rowCount();
}

function delete_workspace_fetch_fk_children(PDO $pdo, string $referencedTable, string $referencedColumn = 'id'): array
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'pgsql') {
        $sql = "SELECT kcu.table_name, kcu.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_name = kcu.constraint_name
                   AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage ccu
                    ON ccu.constraint_name = tc.constraint_name
                   AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND tc.table_schema = current_schema()
                  AND ccu.table_name = ?
                  AND ccu.column_name = ?";
    } else {
        $sql = "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME = ?
                  AND REFERENCED_COLUMN_NAME = ?";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$referencedTable, $referencedColumn]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $children = [];
    foreach ($rows as $row) {
        $table = (string)($row['table_name'] ?? '');
        $column = (string)($row['column_name'] ?? '');

        if (!delete_workspace_is_safe_identifier($table) || !delete_workspace_is_safe_identifier($column)) {
            continue;
        }
        if (!tenant_table_exists($pdo, $table) || !tenant_column_exists($pdo, $table, $column)) {
            continue;
        }

        if (!isset($children[$table])) {
            $children[$table] = [];
        }
        if (!in_array($column, $children[$table], true)) {
            $children[$table][] = $column;
        }
    }

    return $children;
}

$returnToDashboard = delete_workspace_should_return_to_dashboard();

try {
    if (!tenant_table_exists($pdo, 'organizations')) {
        throw new RuntimeException("Organizations table is not available.");
    }

    $orgId = maintenance_get_requested_org_id();
    if ($orgId === null || $orgId <= 0) {
        throw new RuntimeException("org_id is required.");
    }
    if (!maintenance_org_exists($pdo, $orgId)) {
        throw new RuntimeException("Workspace not found.");
    }

    $orgStmt = $pdo->prepare("SELECT name FROM organizations WHERE id = ? LIMIT 1");
    $orgStmt->execute([$orgId]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
    if (!$org) {
        throw new RuntimeException("Workspace not found.");
    }
    $workspaceName = trim((string)($org['name'] ?? ''));
    if ($workspaceName === '') {
        $workspaceName = "Workspace #{$orgId}";
    }

    $workspaceUserData = delete_workspace_fetch_workspace_user_data($pdo, $orgId);
    $workspaceUserIds = $workspaceUserData['ids'];
    $workspaceUsernames = $workspaceUserData['usernames'];
    $workspaceTaskIds = delete_workspace_fetch_workspace_task_ids($pdo, $orgId);

    $pdo->beginTransaction();

    $tenantDataTables = [
        'group_message_reads',
        'group_messages',
        'chat_attachments',
        'chats',
        'screenshots',
        'attendance',
        'notifications',
        'leader_feedback',
        'subtasks',
        'task_assignees',
        'group_members',
        'groups',
        'tasks',
        'password_resets',
        'workspace_invites',
        'bulletin_posts',
    ];

    foreach ($tenantDataTables as $table) {
        if (!tenant_table_exists($pdo, $table)) {
            continue;
        }
        if (!tenant_column_exists($pdo, $table, 'organization_id')) {
            continue;
        }
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE organization_id = ?");
        $stmt->execute([$orgId]);
    }

    $userLinkedCleanup = [
        ['table' => 'workspace_invites', 'columns' => ['invited_by', 'accepted_user_id']],
        ['table' => 'notifications', 'columns' => ['recipient']],
        ['table' => 'attendance', 'columns' => ['user_id']],
        ['table' => 'screenshots', 'columns' => ['user_id']],
        ['table' => 'group_members', 'columns' => ['user_id']],
        ['table' => 'group_messages', 'columns' => ['sender_id']],
        ['table' => 'group_message_reads', 'columns' => ['user_id']],
        ['table' => 'subtasks', 'columns' => ['member_id']],
        ['table' => 'task_assignees', 'columns' => ['user_id']],
        ['table' => 'tasks', 'columns' => ['assigned_to', 'reviewed_by']],
        ['table' => 'organization_members', 'columns' => ['user_id']],
        ['table' => 'chats', 'columns' => ['sender_id', 'receiver_id']],
        ['table' => 'leader_feedback', 'columns' => ['leader_id', 'member_id']],
        ['table' => 'bulletin_posts', 'columns' => ['created_by']],
        ['table' => 'user_login_verifications', 'columns' => ['user_id']],
    ];
    foreach ($userLinkedCleanup as $cleanup) {
        delete_workspace_delete_by_ids(
            $pdo,
            (string)$cleanup['table'],
            (array)$cleanup['columns'],
            $workspaceUserIds
        );
    }

    $taskLinkedCleanup = [
        ['table' => 'notifications', 'columns' => ['task_id']],
        ['table' => 'leader_feedback', 'columns' => ['task_id']],
        ['table' => 'subtasks', 'columns' => ['task_id']],
        ['table' => 'task_assignees', 'columns' => ['task_id']],
        ['table' => 'groups', 'columns' => ['task_id']],
    ];
    foreach ($taskLinkedCleanup as $cleanup) {
        delete_workspace_delete_by_ids(
            $pdo,
            (string)$cleanup['table'],
            (array)$cleanup['columns'],
            $workspaceTaskIds
        );
    }

    if (!empty($workspaceUsernames)) {
        delete_workspace_delete_by_values($pdo, 'password_resets', 'email', $workspaceUsernames);
    }

    $userFkChildren = delete_workspace_fetch_fk_children($pdo, 'users', 'id');
    foreach ($userFkChildren as $table => $columns) {
        if (strtolower($table) === 'users') {
            continue;
        }
        delete_workspace_delete_by_ids($pdo, $table, $columns, $workspaceUserIds);
    }

    $orgFkChildren = delete_workspace_fetch_fk_children($pdo, 'organizations', 'id');
    foreach ($orgFkChildren as $table => $columns) {
        if (strtolower($table) === 'organizations') {
            continue;
        }
        delete_workspace_delete_by_ids($pdo, $table, $columns, [$orgId]);
    }

    if (tenant_table_exists($pdo, 'organization_members')) {
        $stmt = $pdo->prepare("DELETE FROM organization_members WHERE organization_id = ?");
        $stmt->execute([$orgId]);
    }

    if (tenant_table_exists($pdo, 'subscriptions')) {
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE organization_id = ?");
        $stmt->execute([$orgId]);
    }

    if (tenant_table_exists($pdo, 'users') && tenant_column_exists($pdo, 'users', 'organization_id')) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE organization_id = ?");
        $stmt->execute([$orgId]);
    }

    $deleteOrg = $pdo->prepare("DELETE FROM organizations WHERE id = ?");
    $deleteOrg->execute([$orgId]);
    if ($deleteOrg->rowCount() < 1) {
        throw new RuntimeException("Workspace delete failed.");
    }

    $pdo->commit();

    $message = "{$workspaceName} deleted successfully.";
    if ($returnToDashboard) {
        delete_workspace_redirect_to_dashboard($message);
    }

    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
    } else {
        echo "<h2 style='color: #166534;'>Success</h2>";
        echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<p><a href='maintenance_dashboard.php'>Back to dashboard</a></p>";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $errorMessage = $e->getMessage() ?: "Workspace delete failed.";
    if ($returnToDashboard) {
        delete_workspace_redirect_to_dashboard($errorMessage, true);
    }

    if (PHP_SAPI === 'cli') {
        echo "Error: {$errorMessage}" . PHP_EOL;
    } else {
        http_response_code(400);
        echo "<h2 style='color: #b91c1c;'>Error</h2>";
        echo "<p>" . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<p><a href='maintenance_dashboard.php'>Back to dashboard</a></p>";
    }
}
