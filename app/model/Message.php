<?php

require_once __DIR__ . '/../../inc/tenant.php';

function message_scope($pdo, $sql, $params, $joinWord = 'AND', $alias = '')
{
    $scope = tenant_get_scope($pdo, 'chats', $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

function message_apply_not_deleted_filter($pdo, $sql, $alias = '')
{
    if (!tenant_column_exists($pdo, 'chats', 'deleted_at')) {
        return $sql;
    }

    $qualified = $alias !== '' ? ($alias . '.deleted_at') : 'deleted_at';
    return $sql . " AND {$qualified} IS NULL";
}

function message_delete_ensure_schema($pdo)
{
    if (tenant_column_exists($pdo, 'chats', 'deleted_at')) {
        return true;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $columnType = $driver === 'pgsql' ? 'TIMESTAMP NULL' : 'DATETIME NULL';

    try {
        $pdo->exec("ALTER TABLE chats ADD COLUMN deleted_at {$columnType}");
    } catch (Throwable $e) {
        // Ignore duplicate-column or unsupported-schema errors and verify below.
    }

    return tenant_column_exists($pdo, 'chats', 'deleted_at');
}

function getChats($sender_id, $receiver_id, $conn)
{
    $sql = "SELECT * FROM chats
            WHERE ((sender_id = ? AND receiver_id = ?)
               OR (receiver_id = ? AND sender_id = ?))";
    $sql = message_apply_not_deleted_filter($conn, $sql);
    [$sql, $params] = message_scope($conn, $sql, [$sender_id, $receiver_id, $sender_id, $receiver_id], 'AND');
    $sql .= " ORDER BY chat_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetchAll();
    }
    return [];
}

function insertChat($sender_id, $receiver_id, $message, $conn)
{
    $orgId = tenant_get_current_org_id();
    if (tenant_column_exists($conn, 'chats', 'organization_id') && $orgId) {
        $sql = "INSERT INTO chats (sender_id, receiver_id, message, organization_id)
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sender_id, $receiver_id, $message, $orgId]);
    } else {
        $sql = "INSERT INTO chats (sender_id, receiver_id, message)
                VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sender_id, $receiver_id, $message]);
    }

    return $conn->lastInsertId();
}

function insertAttachment($chat_id, $attachment_name, $conn)
{
    if (!table_exists($conn, 'chat_attachments')) {
        return;
    }
    $sql = "INSERT INTO chat_attachments (chat_id, attachment_name)
            VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$chat_id, $attachment_name]);
}

function getAttachments($chat_id, $conn)
{
    if (!table_exists($conn, 'chat_attachments')) {
        return [];
    }
    $sql = "SELECT attachment_name FROM chat_attachments WHERE chat_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$chat_id]);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return [];
}

if (!function_exists('table_exists')) {
    function table_exists($conn, $table_name)
    {
        try {
            $sql = "SELECT 1 FROM information_schema.tables WHERE table_name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$table_name]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}

function lastChat($id_1, $id_2, $conn)
{
    $sql = "SELECT * FROM chats
            WHERE ((sender_id = ? AND receiver_id = ?)
               OR (receiver_id = ? AND sender_id = ?))";
    $sql = message_apply_not_deleted_filter($conn, $sql);
    [$sql, $params] = message_scope($conn, $sql, [$id_1, $id_2, $id_1, $id_2], 'AND');
    $sql .= " ORDER BY chat_id DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch();
    }
    return [];
}

function countUnreadChat($sender_id, $receiver_id, $conn)
{
    $sql = "SELECT COUNT(*) FROM chats
            WHERE sender_id = ? AND receiver_id = ? AND opened = false";
    $sql = message_apply_not_deleted_filter($conn, $sql);
    [$sql, $params] = message_scope($conn, $sql, [$sender_id, $receiver_id], 'AND');
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function countAllUnread($receiver_id, $conn)
{
    $sql = "SELECT COUNT(*) FROM chats
            WHERE receiver_id = ? AND opened = false";
    $sql = message_apply_not_deleted_filter($conn, $sql);
    [$sql, $params] = message_scope($conn, $sql, [$receiver_id], 'AND');
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function mark_chat_conversation_as_read($viewer_id, $other_user_id, $conn)
{
    $viewer_id = (int)$viewer_id;
    $other_user_id = (int)$other_user_id;
    if ($viewer_id <= 0 || $other_user_id <= 0) {
        return false;
    }

    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    $openedValue = ($driver === 'pgsql') ? true : 1;

    $sql = "UPDATE chats
            SET opened = ?
            WHERE sender_id = ? AND receiver_id = ? AND opened = false";
    $sql = message_apply_not_deleted_filter($conn, $sql);
    [$sql, $params] = message_scope($conn, $sql, [$openedValue, $other_user_id, $viewer_id], 'AND');
    $stmt = $conn->prepare($sql);
    return $stmt->execute($params);
}

function opend($id_1, $conn, $chats)
{
    foreach ($chats as $chat) {
        if ($chat['opened'] == false && $chat['receiver_id'] == $id_1) {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            $openedValue = ($driver === 'pgsql') ? true : 1;
            $chat_id = $chat['chat_id'];

            $sql = "UPDATE chats SET opened = ? WHERE chat_id = ?";
            $sql = message_apply_not_deleted_filter($conn, $sql);
            [$sql, $params] = message_scope($conn, $sql, [$openedValue, $chat_id], 'AND');
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
    }
}

function delete_chat_message_for_sender($chatId, $senderId, $conn)
{
    $chatId = (int)$chatId;
    $senderId = (int)$senderId;

    if ($chatId <= 0 || $senderId <= 0 || !message_delete_ensure_schema($conn)) {
        return false;
    }

    $deletedAt = date('Y-m-d H:i:s');
    $sql = "UPDATE chats
            SET deleted_at = ?
            WHERE chat_id = ? AND sender_id = ?";
    $sql = message_apply_not_deleted_filter($conn, $sql);
    [$sql, $params] = message_scope($conn, $sql, [$deletedAt, $chatId, $senderId], 'AND');

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

if (!function_exists('chat_message_is_opened')) {
    function chat_message_is_opened($openedValue)
    {
        if (is_bool($openedValue)) {
            return $openedValue;
        }

        if (is_numeric($openedValue)) {
            return ((int)$openedValue) === 1;
        }

        $normalized = strtolower(trim((string)$openedValue));
        return in_array($normalized, ['1', 'true', 't', 'yes'], true);
    }
}

function formatChatTime($timestamp)
{
    $time = strtotime($timestamp);
    $currentDate = date('Y-m-d');
    $msgDate = date('Y-m-d', $time);

    if ($currentDate == $msgDate) {
        return date('g:i a', $time);
    }
    return date('F j, Y', $time);
}

if (!function_exists('chat_message_day_key')) {
    function chat_message_day_key($timestamp)
    {
        $time = strtotime((string)$timestamp);
        if ($time === false) {
            return '';
        }

        return date('Y-m-d', $time);
    }
}

if (!function_exists('format_chat_message_time')) {
    function format_chat_message_time($timestamp)
    {
        $time = strtotime((string)$timestamp);
        if ($time === false) {
            return '';
        }

        $currentDate = date('Y-m-d');
        $msgDate = date('Y-m-d', $time);

        if ($currentDate === $msgDate) {
            return date('g:i A', $time);
        }

        return date('M j g:i A', $time);
    }
}

if (!function_exists('format_chat_day_label')) {
    function format_chat_day_label($timestamp)
    {
        $time = strtotime((string)$timestamp);
        if ($time === false) {
            return '';
        }

        $todayStart = strtotime(date('Y-m-d'));
        $messageDayStart = strtotime(date('Y-m-d', $time));
        $dayDiff = (int)(($todayStart - $messageDayStart) / 86400);

        if ($dayDiff === 0) {
            return 'Today';
        }

        if ($dayDiff === 1) {
            return 'Yesterday';
        }

        return date('F j, Y', $time);
    }
}

if (!function_exists('render_chat_date_separator')) {
    function render_chat_date_separator($timestamp)
    {
        $label = format_chat_day_label($timestamp);
        if ($label === '') {
            return '';
        }

        return '<div class="chat-date-separator" role="separator" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '"><span>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</span></div>';
    }
}

if (!function_exists('render_chat_read_receipt')) {
    function render_chat_read_receipt($openedValue)
    {
        $isOpened = chat_message_is_opened($openedValue);
        $label = $isOpened ? 'Seen' : 'Sent';
        $statusClass = $isOpened ? 'message-status-seen' : 'message-status-sent';
        $marks = $isOpened ? '&#10003;&#10003;' : '&#10003;';

        return '<span class="message-status ' . $statusClass . '" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '" title="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '">' . $marks . '</span>';
    }
}
