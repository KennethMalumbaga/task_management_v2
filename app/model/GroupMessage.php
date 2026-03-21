<?php

require_once __DIR__ . '/../../inc/tenant.php';
require_once __DIR__ . '/user.php';

function group_message_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

function group_message_apply_not_deleted_filter($pdo, $sql, $alias = '')
{
    if (!tenant_column_exists($pdo, 'group_messages', 'deleted_at')) {
        return $sql;
    }

    $qualified = $alias !== '' ? ($alias . '.deleted_at') : 'deleted_at';
    return $sql . " AND {$qualified} IS NULL";
}

function group_message_delete_ensure_schema($pdo)
{
    if (tenant_column_exists($pdo, 'group_messages', 'deleted_at')) {
        return true;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $columnType = $driver === 'pgsql' ? 'TIMESTAMP NULL' : 'DATETIME NULL';

    try {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN deleted_at {$columnType}");
    } catch (Throwable $e) {
        // Ignore duplicate-column or unsupported-schema errors and verify below.
    }

    return tenant_column_exists($pdo, 'group_messages', 'deleted_at');
}

function insert_group_message($pdo, $group_id, $sender_id, $message)
{
    $orgId = tenant_get_current_org_id();
    if (tenant_column_exists($pdo, 'group_messages', 'organization_id') && $orgId) {
        $stmt = $pdo->prepare(
            "INSERT INTO group_messages (group_id, sender_id, message, organization_id) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$group_id, $sender_id, $message, $orgId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$group_id, $sender_id, $message]);
    }
    return $pdo->lastInsertId();
}

function get_group_messages($pdo, $group_id)
{
    $sql = "SELECT gm.*, u.full_name, u.profile_image, u.role AS user_role
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = ?";
    $sql = group_message_apply_not_deleted_filter($pdo, $sql, 'gm');
    [$sql, $params] = group_message_scope($pdo, $sql, [$group_id], 'group_messages', 'gm');
    $sql .= " ORDER BY gm.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_last_group_message($pdo, $group_id)
{
    $sql = "SELECT gm.*, u.full_name
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = ?";
    $sql = group_message_apply_not_deleted_filter($pdo, $sql, 'gm');
    [$sql, $params] = group_message_scope($pdo, $sql, [$group_id], 'group_messages', 'gm');
    $sql .= " ORDER BY gm.id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_group_unread_count($pdo, $group_id, $user_id)
{
    $sql = "SELECT last_message_id FROM group_message_reads WHERE group_id = ? AND user_id = ?";
    [$sql, $params] = group_message_scope($pdo, $sql, [$group_id, $user_id], 'group_message_reads');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last_read_id = $stmt->fetchColumn() ?: 0;

    $sql = "SELECT COUNT(*) FROM group_messages WHERE group_id = ? AND id > ?";
    $sql = group_message_apply_not_deleted_filter($pdo, $sql);
    [$sql, $params] = group_message_scope($pdo, $sql, [$group_id, $last_read_id], 'group_messages');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_all_group_unread($pdo, $user_id)
{
    $sql = "SELECT g.id
            FROM groups g
            JOIN group_members gm ON gm.group_id = g.id
            WHERE gm.user_id = ?";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'groups', 'g');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalUnread = 0;
    foreach ($groups as $group_id) {
        $totalUnread += get_group_unread_count($pdo, $group_id, $user_id);
    }
    return $totalUnread;
}

function mark_group_as_read($pdo, $group_id, $user_id)
{
    $sql = "SELECT MAX(id) FROM group_messages WHERE group_id = ?";
    $sql = group_message_apply_not_deleted_filter($pdo, $sql);
    [$sql, $params] = group_message_scope($pdo, $sql, [$group_id], 'group_messages');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last_msg_id = $stmt->fetchColumn();

    if ($last_msg_id) {
        $sql = "SELECT id FROM group_message_reads WHERE group_id = ? AND user_id = ?";
        [$sql, $params] = group_message_scope($pdo, $sql, [$group_id, $user_id], 'group_message_reads');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $sql = "UPDATE group_message_reads SET last_message_id = ? WHERE group_id = ? AND user_id = ?";
            [$sql, $params] = group_message_scope($pdo, $sql, [$last_msg_id, $group_id, $user_id], 'group_message_reads');
            $update = $pdo->prepare($sql);
            $update->execute($params);
        } else {
            $orgId = tenant_get_current_org_id();
            if (tenant_column_exists($pdo, 'group_message_reads', 'organization_id') && $orgId) {
                $insert = $pdo->prepare(
                    "INSERT INTO group_message_reads (group_id, user_id, last_message_id, organization_id) VALUES (?, ?, ?, ?)"
                );
                $insert->execute([$group_id, $user_id, $last_msg_id, $orgId]);
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO group_message_reads (group_id, user_id, last_message_id) VALUES (?, ?, ?)"
                );
                $insert->execute([$group_id, $user_id, $last_msg_id]);
            }
        }
    }
}

function delete_group_message_for_sender($pdo, $messageId, $senderId)
{
    $messageId = (int)$messageId;
    $senderId = (int)$senderId;

    if ($messageId <= 0 || $senderId <= 0 || !group_message_delete_ensure_schema($pdo)) {
        return false;
    }

    $deletedAt = date('Y-m-d H:i:s');
    $sql = "UPDATE group_messages
            SET deleted_at = ?
            WHERE id = ? AND sender_id = ?";
    $sql = group_message_apply_not_deleted_filter($pdo, $sql);
    [$sql, $params] = group_message_scope($pdo, $sql, [$deletedAt, $messageId, $senderId], 'group_messages');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function insert_group_attachment($pdo, $message_id, $attachment_name)
{
    if (!table_exists($pdo, 'group_message_attachments')) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO group_message_attachments (message_id, attachment_name) VALUES (?, ?)");
    $stmt->execute([$message_id, $attachment_name]);
}

function get_group_attachments($pdo, $message_id)
{
    if (!table_exists($pdo, 'group_message_attachments')) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT attachment_name FROM group_message_attachments WHERE message_id = ?");
    $stmt->execute([$message_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

if (!function_exists('format_group_list_preview')) {
    function format_group_list_preview($pdo, $lastGroupMsg, $currentUserId)
    {
        if (empty($lastGroupMsg) || !is_array($lastGroupMsg)) {
            return 'Group Chat';
        }

        $senderId = (int)($lastGroupMsg['sender_id'] ?? 0);
        $senderName = trim((string)($lastGroupMsg['full_name'] ?? ''));
        $messageText = trim((string)($lastGroupMsg['message'] ?? ''));
        $messageId = (int)($lastGroupMsg['id'] ?? 0);

        $prefix = '';
        if ($senderId > 0) {
            if ($senderId === (int)$currentUserId) {
                $prefix = 'You: ';
            } elseif ($senderName !== '') {
                $prefix = $senderName . ': ';
            }
        }

        if ($messageText !== '') {
            return htmlspecialchars($prefix . $messageText, ENT_QUOTES, 'UTF-8');
        }

        if ($messageId > 0) {
            $attachments = get_group_attachments($pdo, $messageId);
            if (!empty($attachments)) {
                return htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . "<i class='fa fa-paperclip'></i> Attachment";
            }
        }

        return 'Group Chat';
    }
}

function get_group_message_read_states($pdo, $group_id, $exclude_user_id = 0)
{
    if (!table_exists($pdo, 'group_message_reads')) {
        return [];
    }

    $group_id = (int)$group_id;
    $exclude_user_id = (int)$exclude_user_id;
    if ($group_id <= 0) {
        return [];
    }

    $sql = "SELECT gmr.user_id, gmr.last_message_id, u.full_name, u.profile_image
            FROM group_message_reads gmr
            JOIN users u ON u.id = gmr.user_id
            WHERE gmr.group_id = ?
              AND gmr.last_message_id IS NOT NULL
              AND gmr.last_message_id > 0";
    $params = [$group_id];

    if ($exclude_user_id > 0) {
        $sql .= " AND gmr.user_id <> ?";
        $params[] = $exclude_user_id;
    }

    [$sql, $params] = group_message_scope($pdo, $sql, $params, 'group_message_reads', 'gmr');
    $sql .= " ORDER BY gmr.last_message_id DESC, gmr.user_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function build_group_seen_receipt_map($messages, $readStates, $currentUserId)
{
    $currentUserId = (int)$currentUserId;
    $messageIds = [];

    foreach ((array)$messages as $message) {
        $messageId = (int)($message['id'] ?? 0);
        if ($messageId > 0) {
            $messageIds[] = $messageId;
        }
    }

    if (empty($messageIds)) {
        return [];
    }

    sort($messageIds, SORT_NUMERIC);
    $receiptMap = [];

    foreach ((array)$readStates as $readState) {
        $lastReadId = (int)($readState['last_message_id'] ?? 0);
        if ($lastReadId <= 0) {
            continue;
        }

        $anchorMessageId = 0;
        foreach ($messageIds as $messageId) {
            if ($messageId > $lastReadId) {
                break;
            }
            $anchorMessageId = $messageId;
        }

        if ($anchorMessageId <= 0) {
            continue;
        }

        if (!isset($receiptMap[$anchorMessageId])) {
            $receiptMap[$anchorMessageId] = [];
        }

        $receiptMap[$anchorMessageId][] = $readState;
    }

    return $receiptMap;
}

function render_group_seen_receipts($readers, $maxVisible = 4)
{
    $readers = is_array($readers) ? array_values($readers) : [];
    if (empty($readers)) {
        return '';
    }

    $maxVisible = max(1, (int)$maxVisible);
    $visibleReaders = array_slice($readers, 0, $maxVisible);
    $remainingCount = max(0, count($readers) - count($visibleReaders));

    ob_start();
    ?>
    <div class="group-message-seen-row" aria-label="Seen by group members">
        <?php foreach ($visibleReaders as $reader) { ?>
            <?php
            $name = trim((string)($reader['full_name'] ?? 'User'));
            if ($name === '') {
                $name = 'User';
            }
            $profileUrl = user_profile_image_url($reader['profile_image'] ?? '');
            $initials = user_display_initials($name);
            ?>
            <span class="group-message-seen-avatar" title="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($profileUrl !== '') { ?>
                    <img src="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                <?php } else { ?>
                    <span class="group-message-seen-avatar-fallback"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                <?php } ?>
            </span>
        <?php } ?>
        <?php if ($remainingCount > 0) { ?>
            <span class="group-message-seen-avatar group-message-seen-avatar-more" title="<?= htmlspecialchars($remainingCount . ' more', ENT_QUOTES, 'UTF-8') ?>">
                <span class="group-message-seen-avatar-fallback">+<?= (int)$remainingCount ?></span>
            </span>
        <?php } ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function build_group_member_mention_names($members)
{
    $names = [];
    if (!is_array($members)) {
        return $names;
    }

    foreach ($members as $member) {
        $name = trim((string)($member['full_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $names[$name] = true;
    }

    $result = array_keys($names);
    usort($result, function ($a, $b) {
        $lenA = function_exists('mb_strlen') ? mb_strlen($a) : strlen($a);
        $lenB = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
        return $lenB <=> $lenA;
    });

    return $result;
}

function format_group_message_mentions($message, $memberNames)
{
    $working = (string)$message;
    if ($working === '') {
        return '';
    }

    $tokens = [];
    $tokenIndex = 0;
    foreach ((array)$memberNames as $name) {
        $name = trim((string)$name);
        if ($name === '') {
            continue;
        }

        $token = "__MENTION_{$tokenIndex}__";
        $pattern = '/(^|[\s\(\[\{>])@' . preg_quote($name, '/') . '(?=$|[\s\)\]\}\,\.\!\?\:\;])/iu';
        $working = preg_replace_callback(
            $pattern,
            function ($m) use ($token) {
                return $m[1] . $token;
            },
            $working
        );
        $tokens[$token] = $name;
        $tokenIndex++;
    }

    $working = preg_replace_callback(
        '/(^|[\s\(\[\{>])@everyone(?=$|[\s\)\]\}\,\.\!\?\:\;])/iu',
        function ($m) {
            return $m[1] . '__MENTION_EVERYONE__';
        },
        $working
    );

    $escaped = nl2br(htmlspecialchars($working, ENT_QUOTES, 'UTF-8'));
    $escaped = str_replace('__MENTION_EVERYONE__', '<span class="chat-mention chat-mention-everyone">@everyone</span>', $escaped);
    foreach ($tokens as $token => $name) {
        $replacement = '<span class="chat-mention">@' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
        $escaped = str_replace($token, $replacement, $escaped);
    }

    return $escaped;
}

if (!function_exists('table_exists')) {
    function table_exists($pdo, $table_name)
    {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = ?");
            $stmt->execute([$table_name]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}
