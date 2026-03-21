<?php

require_once __DIR__ . '/../../inc/tenant.php';

function typing_status_normalize_org_id($organizationId = null)
{
    $resolvedOrgId = $organizationId !== null ? (int)$organizationId : (int)tenant_get_current_org_id();
    return $resolvedOrgId > 0 ? $resolvedOrgId : 0;
}

function typing_status_schema_ready($pdo)
{
    static $ready = false;

    if ($ready) {
        return true;
    }

    if (tenant_table_exists($pdo, 'chat_typing_statuses')) {
        $ready = true;
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS chat_typing_statuses (
                id INT NOT NULL AUTO_INCREMENT,
                chat_type VARCHAR(10) NOT NULL,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL DEFAULT 0,
                group_id INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                organization_id INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_chat_typing_direct (chat_type, sender_id, receiver_id, group_id, organization_id),
                KEY idx_chat_typing_direct_lookup (chat_type, receiver_id, updated_at),
                KEY idx_chat_typing_group_lookup (chat_type, group_id, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $pdo->exec($sql);
        $ready = tenant_table_exists($pdo, 'chat_typing_statuses');
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function typing_status_touch($pdo, $chatType, $senderId, $receiverId, $groupId, $organizationId = null, $updatedAt = null)
{
    $senderId = (int)$senderId;
    $receiverId = max(0, (int)$receiverId);
    $groupId = max(0, (int)$groupId);
    $organizationId = typing_status_normalize_org_id($organizationId);
    $updatedAt = $updatedAt ?: date('Y-m-d H:i:s');

    if ($senderId <= 0 || !typing_status_schema_ready($pdo)) {
        return false;
    }

    $update = $pdo->prepare(
        "UPDATE chat_typing_statuses
         SET updated_at = ?
         WHERE chat_type = ? AND sender_id = ? AND receiver_id = ? AND group_id = ? AND organization_id = ?"
    );
    $update->execute([$updatedAt, $chatType, $senderId, $receiverId, $groupId, $organizationId]);
    if ($update->rowCount() > 0) {
        return true;
    }

    try {
        $insert = $pdo->prepare(
            "INSERT INTO chat_typing_statuses (chat_type, sender_id, receiver_id, group_id, updated_at, organization_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([$chatType, $senderId, $receiverId, $groupId, $updatedAt, $organizationId]);
        return true;
    } catch (Throwable $e) {
        $update->execute([$updatedAt, $chatType, $senderId, $receiverId, $groupId, $organizationId]);
        return $update->rowCount() > 0;
    }
}

function typing_status_clear($pdo, $chatType, $senderId, $receiverId, $groupId, $organizationId = null)
{
    $senderId = (int)$senderId;
    $receiverId = max(0, (int)$receiverId);
    $groupId = max(0, (int)$groupId);
    $organizationId = typing_status_normalize_org_id($organizationId);

    if ($senderId <= 0 || !tenant_table_exists($pdo, 'chat_typing_statuses')) {
        return false;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM chat_typing_statuses
         WHERE chat_type = ? AND sender_id = ? AND receiver_id = ? AND group_id = ? AND organization_id = ?"
    );
    return $stmt->execute([$chatType, $senderId, $receiverId, $groupId, $organizationId]);
}

function typing_status_upsert_direct($pdo, $senderId, $receiverId, $organizationId = null, $updatedAt = null)
{
    $receiverId = (int)$receiverId;
    if ($receiverId <= 0) {
        return false;
    }

    return typing_status_touch($pdo, 'user', $senderId, $receiverId, 0, $organizationId, $updatedAt);
}

function typing_status_upsert_group($pdo, $senderId, $groupId, $organizationId = null, $updatedAt = null)
{
    $groupId = (int)$groupId;
    if ($groupId <= 0) {
        return false;
    }

    return typing_status_touch($pdo, 'group', $senderId, 0, $groupId, $organizationId, $updatedAt);
}

function typing_status_clear_direct($pdo, $senderId, $receiverId, $organizationId = null)
{
    $receiverId = (int)$receiverId;
    if ($receiverId <= 0) {
        return false;
    }

    return typing_status_clear($pdo, 'user', $senderId, $receiverId, 0, $organizationId);
}

function typing_status_clear_group($pdo, $senderId, $groupId, $organizationId = null)
{
    $groupId = (int)$groupId;
    if ($groupId <= 0) {
        return false;
    }

    return typing_status_clear($pdo, 'group', $senderId, 0, $groupId, $organizationId);
}

function typing_status_get_direct($pdo, $viewerId, $otherUserId, $organizationId = null, $freshWindowSeconds = 6)
{
    $viewerId = (int)$viewerId;
    $otherUserId = (int)$otherUserId;
    $organizationId = typing_status_normalize_org_id($organizationId);
    $cutoff = date('Y-m-d H:i:s', time() - max(1, (int)$freshWindowSeconds));

    if ($viewerId <= 0 || $otherUserId <= 0 || !tenant_table_exists($pdo, 'chat_typing_statuses')) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM chat_typing_statuses
         WHERE chat_type = 'user'
           AND sender_id = ?
           AND receiver_id = ?
           AND group_id = 0
           AND organization_id = ?
           AND updated_at >= ?
         LIMIT 1"
    );
    $stmt->execute([$otherUserId, $viewerId, $organizationId, $cutoff]);
    return (bool)$stmt->fetchColumn();
}

function typing_status_get_group_users($pdo, $groupId, $excludeUserId, $organizationId = null, $freshWindowSeconds = 6)
{
    $groupId = (int)$groupId;
    $excludeUserId = (int)$excludeUserId;
    $organizationId = typing_status_normalize_org_id($organizationId);
    $cutoff = date('Y-m-d H:i:s', time() - max(1, (int)$freshWindowSeconds));

    if ($groupId <= 0 || !tenant_table_exists($pdo, 'chat_typing_statuses')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT ts.sender_id AS id, u.full_name, u.profile_image
         FROM chat_typing_statuses ts
         JOIN users u ON u.id = ts.sender_id
         WHERE ts.chat_type = 'group'
           AND ts.group_id = ?
           AND ts.receiver_id = 0
           AND ts.organization_id = ?
           AND ts.updated_at >= ?
           AND ts.sender_id <> ?
         ORDER BY ts.updated_at DESC, u.full_name ASC"
    );
    $stmt->execute([$groupId, $organizationId, $cutoff, $excludeUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
