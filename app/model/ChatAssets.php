<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('chat_asset_extension')) {
    function chat_asset_extension($filename)
    {
        $filename = trim((string)$filename);
        if ($filename === '') {
            return '';
        }

        return strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
    }
}

if (!function_exists('chat_asset_is_media')) {
    function chat_asset_is_media($filename)
    {
        return in_array(chat_asset_extension($filename), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }
}

if (!function_exists('chat_asset_safe_name')) {
    function chat_asset_safe_name($filename)
    {
        return basename(trim((string)$filename));
    }
}

if (!function_exists('chat_asset_disk_path')) {
    function chat_asset_disk_path($filename)
    {
        $safeName = chat_asset_safe_name($filename);
        if ($safeName === '') {
            return '';
        }

        return __DIR__ . '/../../uploads/' . $safeName;
    }
}

if (!function_exists('chat_asset_public_url')) {
    function chat_asset_public_url($filename)
    {
        $safeName = chat_asset_safe_name($filename);
        if ($safeName === '') {
            return '';
        }

        $path = chat_asset_disk_path($safeName);
        $mtime = is_file($path) ? @filemtime($path) : 0;
        return 'uploads/' . rawurlencode($safeName) . ($mtime ? ('?t=' . $mtime) : '');
    }
}

if (!function_exists('chat_asset_human_size')) {
    function chat_asset_human_size($bytes)
    {
        $bytes = (float)$bytes;
        if ($bytes <= 0) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / pow(1024, $power);
        $precision = $power === 0 ? 0 : 2;

        return number_format($value, $precision) . ' ' . $units[$power];
    }
}

if (!function_exists('chat_asset_size_label')) {
    function chat_asset_size_label($filename)
    {
        $path = chat_asset_disk_path($filename);
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $size = @filesize($path);
        if ($size === false) {
            return '';
        }

        return chat_asset_human_size($size);
    }
}

if (!function_exists('chat_asset_month_key')) {
    function chat_asset_month_key($timestamp)
    {
        $time = strtotime((string)$timestamp);
        if ($time === false) {
            return 'unknown';
        }

        return date('Y-m', $time);
    }
}

if (!function_exists('chat_asset_month_label')) {
    function chat_asset_month_label($timestamp)
    {
        $time = strtotime((string)$timestamp);
        if ($time === false) {
            return 'Older';
        }

        return date('F Y', $time);
    }
}

if (!function_exists('chat_asset_date_label')) {
    function chat_asset_date_label($timestamp)
    {
        $time = strtotime((string)$timestamp);
        if ($time === false) {
            return '';
        }

        return date('M j, Y', $time);
    }
}

if (!function_exists('build_chat_asset_record')) {
    function build_chat_asset_record($attachmentName, $createdAt, $senderId = 0, $sourceId = 0)
    {
        $safeName = chat_asset_safe_name($attachmentName);
        if ($safeName === '') {
            return null;
        }

        return [
            'name' => $safeName,
            'url' => chat_asset_public_url($safeName),
            'is_media' => chat_asset_is_media($safeName),
            'size_label' => chat_asset_size_label($safeName),
            'created_at' => (string)$createdAt,
            'date_label' => chat_asset_date_label($createdAt),
            'month_key' => chat_asset_month_key($createdAt),
            'month_label' => chat_asset_month_label($createdAt),
            'sender_id' => (int)$senderId,
            'source_id' => (int)$sourceId,
        ];
    }
}

if (!function_exists('get_direct_chat_assets')) {
    function get_direct_chat_assets($pdo, $userId, $otherUserId)
    {
        if (!tenant_table_exists($pdo, 'chat_attachments')) {
            return [];
        }

        $userId = (int)$userId;
        $otherUserId = (int)$otherUserId;
        if ($userId <= 0 || $otherUserId <= 0) {
            return [];
        }

        $sql = "SELECT c.chat_id AS source_id, c.sender_id, c.created_at, ca.attachment_name
                FROM chats c
                JOIN chat_attachments ca ON ca.chat_id = c.chat_id
                WHERE ((c.sender_id = ? AND c.receiver_id = ?)
                   OR (c.receiver_id = ? AND c.sender_id = ?))";
        if (tenant_column_exists($pdo, 'chats', 'deleted_at')) {
            $sql .= " AND c.deleted_at IS NULL";
        }
        $params = [$userId, $otherUserId, $userId, $otherUserId];
        $scope = tenant_get_scope($pdo, 'chats', 'c');
        $sql .= $scope['sql'] . " ORDER BY c.created_at DESC, c.chat_id DESC";
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $assets = [];
        foreach ($rows as $row) {
            $asset = build_chat_asset_record(
                $row['attachment_name'] ?? '',
                $row['created_at'] ?? '',
                $row['sender_id'] ?? 0,
                $row['source_id'] ?? 0
            );
            if ($asset !== null) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }
}

if (!function_exists('get_group_chat_assets')) {
    function get_group_chat_assets($pdo, $groupId)
    {
        if (!tenant_table_exists($pdo, 'group_message_attachments')) {
            return [];
        }

        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            return [];
        }

        $sql = "SELECT gm.id AS source_id, gm.sender_id, gm.created_at, gma.attachment_name
                FROM group_messages gm
                JOIN group_message_attachments gma ON gma.message_id = gm.id
                WHERE gm.group_id = ?";
        if (tenant_column_exists($pdo, 'group_messages', 'deleted_at')) {
            $sql .= " AND gm.deleted_at IS NULL";
        }
        $params = [$groupId];
        $scope = tenant_get_scope($pdo, 'group_messages', 'gm');
        $sql .= $scope['sql'] . " ORDER BY gm.created_at DESC, gm.id DESC";
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $assets = [];
        foreach ($rows as $row) {
            $asset = build_chat_asset_record(
                $row['attachment_name'] ?? '',
                $row['created_at'] ?? '',
                $row['sender_id'] ?? 0,
                $row['source_id'] ?? 0
            );
            if ($asset !== null) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }
}

if (!function_exists('split_chat_assets_by_type')) {
    function split_chat_assets_by_type($assets)
    {
        $split = ['media' => [], 'files' => []];

        foreach ((array)$assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            if (!empty($asset['is_media'])) {
                $split['media'][] = $asset;
            } else {
                $split['files'][] = $asset;
            }
        }

        return $split;
    }
}

if (!function_exists('group_chat_media_by_month')) {
    function group_chat_media_by_month($mediaAssets)
    {
        $groups = [];

        foreach ((array)$mediaAssets as $asset) {
            $monthKey = (string)($asset['month_key'] ?? 'unknown');
            if (!isset($groups[$monthKey])) {
                $groups[$monthKey] = [
                    'label' => (string)($asset['month_label'] ?? 'Older'),
                    'items' => [],
                ];
            }

            $groups[$monthKey]['items'][] = $asset;
        }

        return $groups;
    }
}

if (!function_exists('render_chat_media_files_browser')) {
    function render_chat_media_files_browser($assets, $emptyMediaLabel = 'No media sent yet.', $emptyFilesLabel = 'No files sent yet.')
    {
        $split = split_chat_assets_by_type($assets);
        $mediaGroups = group_chat_media_by_month($split['media']);
        $fileAssets = $split['files'];

        ob_start();
        ?>
        <div class="chat-assets-shell">
            <div class="chat-assets-tabs" role="tablist" aria-label="Media and Files">
                <button type="button" class="chat-assets-tab active" data-target="media" role="tab" aria-selected="true">Media</button>
                <button type="button" class="chat-assets-tab" data-target="files" role="tab" aria-selected="false">Files</button>
            </div>

            <div class="chat-assets-panel active" data-panel="media">
                <?php if (!empty($mediaGroups)) { ?>
                    <?php foreach ($mediaGroups as $group) { ?>
                        <div class="chat-assets-month-block">
                            <div class="chat-assets-month-label"><?= htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="chat-assets-media-grid">
                                <?php foreach ($group['items'] as $asset) { ?>
                                    <a class="chat-assets-media-item" href="<?= htmlspecialchars((string)$asset['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars((string)$asset['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <img src="<?= htmlspecialchars((string)$asset['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$asset['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="chat-assets-empty"><?= htmlspecialchars($emptyMediaLabel, ENT_QUOTES, 'UTF-8') ?></div>
                <?php } ?>
            </div>

            <div class="chat-assets-panel" data-panel="files">
                <?php if (!empty($fileAssets)) { ?>
                    <div class="chat-assets-file-list">
                        <?php foreach ($fileAssets as $asset) { ?>
                            <?php
                            $subParts = [];
                            if (!empty($asset['size_label'])) {
                                $subParts[] = (string)$asset['size_label'];
                            }
                            if (!empty($asset['date_label'])) {
                                $subParts[] = (string)$asset['date_label'];
                            }
                            $subText = implode(' • ', $subParts);
                            ?>
                            <a class="chat-assets-file-item" href="<?= htmlspecialchars((string)$asset['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars((string)$asset['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="chat-assets-file-icon"><i class="fa fa-file-text-o"></i></span>
                                <span class="chat-assets-file-meta">
                                    <span class="chat-assets-file-name"><?= htmlspecialchars((string)$asset['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($subText !== '') { ?>
                                        <span class="chat-assets-file-subtext"><?= htmlspecialchars($subText, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php } ?>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="chat-assets-empty"><?= htmlspecialchars($emptyFilesLabel, ENT_QUOTES, 'UTF-8') ?></div>
                <?php } ?>
            </div>
        </div>
        <?php

        return trim(ob_get_clean());
    }
}
