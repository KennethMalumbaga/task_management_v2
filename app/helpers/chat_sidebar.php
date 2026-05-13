<?php

if (!function_exists('chat_sidebar_timestamp')) {
    function chat_sidebar_timestamp($value)
    {
        $timestamp = strtotime((string)$value);
        return $timestamp === false ? 0 : $timestamp;
    }
}

if (!function_exists('chat_sidebar_build_data')) {
    function chat_sidebar_build_data($pdo, $currentUserId, $allUsers = null, $allGroups = null)
    {
        $currentUserId = (int)$currentUserId;
        $allUsers = is_array($allUsers) ? $allUsers : get_all_users($pdo);
        $hiddenThreadsMap = get_hidden_threads_map($pdo, $currentUserId);

        $otherUserIds = [];
        foreach ($allUsers as $user) {
            $userId = (int)($user['id'] ?? 0);
            if ($userId > 0 && $userId !== $currentUserId) {
                $otherUserIds[] = $userId;
            }
        }

        $lastMessagesMap = get_last_chats_map($currentUserId, $otherUserIds, $pdo);
        $directUnreadMap = count_unread_chats_map($currentUserId, $otherUserIds, $pdo);

        $users = [];
        foreach ($allUsers as $user) {
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0 || $userId === $currentUserId) {
                continue;
            }

            $lastMessage = $lastMessagesMap[$userId] ?? [];
            $lastMsgTime = !empty($lastMessage['created_at']) ? (string)$lastMessage['created_at'] : '0000-00-00 00:00:00';
            if (chat_thread_should_be_hidden($hiddenThreadsMap['users'][$userId] ?? null, $lastMsgTime)) {
                continue;
            }

            $user['last_msg_time'] = $lastMsgTime;
            $user['last_message_data'] = $lastMessage;
            $user['unread_count'] = (int)($directUnreadMap[$userId] ?? 0);
            $users[] = $user;
        }

        usort($users, function ($a, $b) {
            return chat_sidebar_timestamp($b['last_msg_time'] ?? '') <=> chat_sidebar_timestamp($a['last_msg_time'] ?? '');
        });

        $userPresenceMap = get_users_clocked_in_map($pdo, array_column($users, 'id'));
        foreach ($users as &$user) {
            $user['is_online'] = !empty($userPresenceMap[(int)($user['id'] ?? 0)]);
        }
        unset($user);

        $allGroups = is_array($allGroups) ? $allGroups : get_groups_for_user($pdo, $currentUserId);
        $groupIds = array_values(array_filter(array_map(function ($group) {
            return (int)($group['id'] ?? 0);
        }, $allGroups), function ($groupId) {
            return $groupId > 0;
        }));

        $lastGroupMessagesMap = get_last_group_messages_map($pdo, $groupIds);
        $groupUnreadMap = get_group_unread_counts_map($pdo, $groupIds, $currentUserId);

        $groups = [];
        foreach ($allGroups as $group) {
            $groupId = (int)($group['id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            $lastGroupMsg = $lastGroupMessagesMap[$groupId] ?? [];
            $lastMsgTime = null;
            if (!empty($lastGroupMsg['created_at'])) {
                $lastMsgTime = (string)$lastGroupMsg['created_at'];
            } elseif (!empty($group['created_at'])) {
                $lastMsgTime = (string)$group['created_at'];
            }

            if (chat_thread_should_be_hidden($hiddenThreadsMap['groups'][$groupId] ?? null, (string)$lastMsgTime)) {
                continue;
            }

            $group['last_message_data'] = $lastGroupMsg;
            $group['last_msg_sort_time'] = !empty($lastGroupMsg['created_at']) ? (string)$lastGroupMsg['created_at'] : null;
            $group['last_msg_time'] = $lastMsgTime;
            $group['unread_count'] = (int)($groupUnreadMap[$groupId] ?? 0);
            $groups[] = $group;
        }

        usort($groups, function ($a, $b) {
            return chat_sidebar_timestamp($b['last_msg_time'] ?? '1970-01-01 00:00:00')
                <=> chat_sidebar_timestamp($a['last_msg_time'] ?? '1970-01-01 00:00:00');
        });

        $totalUnread = 0;
        foreach ($users as $user) {
            $totalUnread += (int)($user['unread_count'] ?? 0);
        }
        foreach ($groups as $group) {
            $totalUnread += (int)($group['unread_count'] ?? 0);
        }

        return [
            'all_users' => $allUsers,
            'users' => $users,
            'groups' => $groups,
            'total_unread' => $totalUnread,
        ];
    }
}

if (!function_exists('chat_sidebar_render_users')) {
    function chat_sidebar_render_users(array $users, $currentUserId, $emptyHtml = '')
    {
        ob_start();
        foreach ($users as $user) {
            echo render_chat_user_list_item(
                $user,
                $user['last_message_data'] ?? [],
                (int)($user['unread_count'] ?? 0),
                (int)$currentUserId
            );
        }

        $html = trim(ob_get_clean());
        return $html !== '' ? $html : $emptyHtml;
    }
}

if (!function_exists('chat_sidebar_render_group_item')) {
    function chat_sidebar_render_group_item($pdo, $group, $currentUserId)
    {
        $groupId = (int)($group['id'] ?? 0);
        $groupName = (string)($group['name'] ?? 'Group');
        $lastGroupMsg = $group['last_message_data'] ?? [];
        $groupLastTimestamp = !empty($group['last_msg_sort_time'])
            ? chat_sidebar_timestamp($group['last_msg_sort_time'])
            : 0;
        $groupPreview = format_group_list_preview($pdo, $lastGroupMsg, (int)$currentUserId);
        $unreadCount = (int)($group['unread_count'] ?? 0);

        ob_start();
        ?>
        <div class="chat-item group-item" data-group-id="<?= $groupId ?>" data-group-name="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>" data-last-ts="<?= $groupLastTimestamp ?>">
            <div class="avatar-md" style="background:var(--primary-soft-3); color:var(--primary);">
                <i class="fa fa-users"></i>
            </div>
            <div class="chat-item-content">
                <div class="chat-item-header">
                    <span class="chat-user-name"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="chat-item-sub-row">
                    <div class="chat-item-last-msg"><?= $groupPreview ?></div>
                    <?php if ($unreadCount > 0) { ?>
                        <span class="message-badge"><?= $unreadCount ?></span>
                    <?php } ?>
                </div>
                <?php if (!empty($group['last_msg_time'])) { ?>
                    <div class="chat-time"><?= htmlspecialchars((string)formatChatTime($group['last_msg_time']), ENT_QUOTES, 'UTF-8') ?></div>
                <?php } ?>
            </div>
            <button
                type="button"
                class="chat-item-delete-btn"
                aria-label="Delete chat <?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>"
                title="Delete chat"
                data-delete-type="group"
                data-delete-id="<?= $groupId ?>"
                data-delete-name="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa fa-trash-o"></i>
            </button>
        </div>
        <?php
        return trim(ob_get_clean());
    }
}

if (!function_exists('chat_sidebar_render_groups')) {
    function chat_sidebar_render_groups($pdo, array $groups, $currentUserId, $emptyHtml = '<div style="padding: 12px; color:#9CA3AF; font-size:13px;">No groups yet.</div>')
    {
        ob_start();
        foreach ($groups as $group) {
            echo chat_sidebar_render_group_item($pdo, $group, $currentUserId);
        }

        $html = trim(ob_get_clean());
        return $html !== '' ? $html : $emptyHtml;
    }
}

if (!function_exists('chat_sidebar_build_payload')) {
    function chat_sidebar_build_payload($pdo, $currentUserId, $allUsers = null, $allGroups = null)
    {
        $data = chat_sidebar_build_data($pdo, $currentUserId, $allUsers, $allGroups);

        return [
            'users' => chat_sidebar_render_users($data['users'], $currentUserId),
            'groups' => chat_sidebar_render_groups($pdo, $data['groups'], $currentUserId),
            'totalUnread' => (int)$data['total_unread'],
        ];
    }
}
