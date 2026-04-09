<?php

require_once __DIR__ . '/../../inc/tenant.php';
require_once __DIR__ . '/../../inc/attendance_pause.php';

function user_model_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

if (!function_exists('user_google_auth_ensure_schema')) {
    function user_google_auth_ensure_schema($pdo)
    {
        static $cache = [];

        $cacheKey = is_object($pdo) ? spl_object_hash($pdo) : 'default';
        if (array_key_exists($cacheKey, $cache)) {
            return (bool)$cache[$cacheKey];
        }

        $columnsToEnsure = [
            'google_sub' => [
                'mysql' => "ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL AFTER username",
                'pgsql' => "ALTER TABLE users ADD COLUMN google_sub VARCHAR(255)",
            ],
            'google_email_verified' => [
                'mysql' => "ALTER TABLE users ADD COLUMN google_email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER google_sub",
                'pgsql' => "ALTER TABLE users ADD COLUMN google_email_verified BOOLEAN DEFAULT FALSE",
            ],
            'google_picture' => [
                'mysql' => "ALTER TABLE users ADD COLUMN google_picture VARCHAR(2048) NULL AFTER profile_image",
                'pgsql' => "ALTER TABLE users ADD COLUMN google_picture VARCHAR(2048)",
            ],
        ];

        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        foreach ($columnsToEnsure as $column => $queries) {
            if (tenant_column_exists($pdo, 'users', $column)) {
                continue;
            }

            try {
                $sql = $driver === 'mysql' ? $queries['mysql'] : $queries['pgsql'];
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Another request may have added the column already.
            }
        }

        $cache[$cacheKey] = tenant_column_exists($pdo, 'users', 'google_sub');
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('user_compensation_ensure_schema')) {
    function user_compensation_ensure_schema($pdo)
    {
        static $cache = [];

        $cacheKey = is_object($pdo) ? spl_object_hash($pdo) : 'default';
        if (array_key_exists($cacheKey, $cache)) {
            return (bool)$cache[$cacheKey];
        }

        if (tenant_column_exists($pdo, 'users', 'hourly_rate')) {
            $cache[$cacheKey] = true;
            return true;
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE users ADD COLUMN hourly_rate DECIMAL(10,2) NULL DEFAULT NULL AFTER role");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN hourly_rate NUMERIC(10,2) DEFAULT NULL");
            }
        } catch (Throwable $e) {
            // Another request may have added the column already.
        }

        $cache[$cacheKey] = tenant_column_exists($pdo, 'users', 'hourly_rate');
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('user_get_by_google_sub_unscoped')) {
    function user_get_by_google_sub_unscoped($pdo, $googleSub)
    {
        if (!user_google_auth_ensure_schema($pdo)) {
            return 0;
        }

        $googleSub = trim((string)$googleSub);
        if ($googleSub === '') {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_sub = ? LIMIT 1");
        $stmt->execute([$googleSub]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: 0;
    }
}

if (!function_exists('user_get_by_email_unscoped')) {
    function user_get_by_email_unscoped($pdo, $email)
    {
        $email = strtolower(trim((string)$email));
        if ($email === '') {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: 0;
    }
}

if (!function_exists('user_link_google_account_unscoped')) {
    function user_link_google_account_unscoped($pdo, $userId, $googleSub, $emailVerified = false, $pictureUrl = null)
    {
        if (!user_google_auth_ensure_schema($pdo)) {
            return false;
        }

        $userId = (int)$userId;
        $googleSub = trim((string)$googleSub);
        $pictureUrl = trim((string)$pictureUrl);
        $emailVerified = filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $emailVerified = $emailVerified === null ? !empty($emailVerified) : $emailVerified;

        if ($userId <= 0 || $googleSub === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE users
             SET google_sub = ?, google_email_verified = ?, google_picture = ?
             WHERE id = ?"
        );
        return $stmt->execute([$googleSub, $emailVerified ? 1 : 0, $pictureUrl !== '' ? $pictureUrl : null, $userId]);
    }
}

function get_all_users($pdo, $role = 'all')
{
    if ($role === 'all') {
        [$sql, $params] = user_model_append_scope($pdo, "SELECT * FROM users WHERE 1=1", [], 'users');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        [$sql, $params] = user_model_append_scope($pdo, "SELECT * FROM users WHERE role = ?", [$role], 'users');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $users = $stmt->fetchAll();
    return $users ?: [];
}

if (!function_exists('user_profile_image_url')) {
    function user_profile_image_url($profileImage)
    {
        $profileImage = trim((string)$profileImage);
        if ($profileImage === '' || strtolower($profileImage) === 'default.png') {
            return '';
        }

        $safeName = basename($profileImage);
        if ($safeName === '') {
            return '';
        }

        $absolutePath = __DIR__ . '/../../uploads/' . $safeName;
        if (!is_file($absolutePath)) {
            return '';
        }

        $mtime = @filemtime($absolutePath);
        return 'uploads/' . rawurlencode($safeName) . '?t=' . ($mtime ? $mtime : time());
    }
}

if (!function_exists('user_display_initials')) {
    function user_display_initials($fullName, $limit = 2)
    {
        $name = trim((string)$fullName);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name);
        $initials = '';
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }

            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($initials) >= (int)$limit) {
                break;
            }
        }

        if ($initials === '') {
            $initials = mb_strtoupper(mb_substr($name, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }
}

if (!function_exists('user_presence_ensure_schema')) {
    function user_presence_ensure_schema($pdo)
    {
        static $cache = [];

        $cacheKey = is_object($pdo) ? spl_object_hash($pdo) : 'default';
        if (array_key_exists($cacheKey, $cache)) {
            return (bool)$cache[$cacheKey];
        }

        if (tenant_column_exists($pdo, 'users', 'last_active_at')) {
            $cache[$cacheKey] = true;
            return true;
        }

        try {
            $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE users ADD COLUMN last_active_at DATETIME NULL AFTER created_at");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN last_active_at TIMESTAMP NULL");
            }
        } catch (Throwable $e) {
            // Another request may have added the column already, or the DB may not allow schema changes here.
        }

        $cache[$cacheKey] = tenant_column_exists($pdo, 'users', 'last_active_at');
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('user_presence_touch')) {
    function user_presence_touch($pdo, $userId, $organizationId = null, $activityAt = null)
    {
        $userId = (int)$userId;
        if ($userId <= 0 || !user_presence_ensure_schema($pdo)) {
            return false;
        }

        date_default_timezone_set('Asia/Manila');
        $activityAt = $activityAt ? (string)$activityAt : date('Y-m-d H:i:s');

        $sql = "UPDATE users SET last_active_at = ? WHERE id = ?";
        $params = [$activityAt, $userId];
        $scope = tenant_get_scope($pdo, 'users', '', 'AND', 'organization_id', $organizationId);
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}

if (!function_exists('user_presence_mark_offline')) {
    function user_presence_mark_offline($pdo, $userId, $organizationId = null)
    {
        $userId = (int)$userId;
        if ($userId <= 0 || !user_presence_ensure_schema($pdo)) {
            return false;
        }

        $sql = "UPDATE users SET last_active_at = NULL WHERE id = ?";
        $params = [$userId];
        $scope = tenant_get_scope($pdo, 'users', '', 'AND', 'organization_id', $organizationId);
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}

if (!function_exists('get_users_attendance_online_map')) {
    function get_users_attendance_online_map($pdo, array $userIds)
    {
        $statusMap = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0) {
                $statusMap[$userId] = false;
            }
        }

        if (empty($statusMap)) {
            return [];
        }

        date_default_timezone_set('Asia/Manila');
        $today = date('Y-m-d');
        $ids = array_keys($statusMap);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT DISTINCT user_id
                FROM attendance
                WHERE user_id IN ($placeholders)
                  AND att_date = ?
                  AND time_in IS NOT NULL
                  AND (time_out IS NULL OR time_out = '00:00:00')";
        $params = array_merge($ids, [$today]);
        [$sql, $params] = user_model_append_scope($pdo, $sql, $params, 'attendance');

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $activeUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($activeUserIds as $activeUserId) {
            $statusMap[(int)$activeUserId] = true;
        }

        return $statusMap;
    }
}

if (!function_exists('get_users_online_map')) {
    function get_users_online_map($pdo, array $userIds, $freshWindowSeconds = 75)
    {
        $statusMap = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0) {
                $statusMap[$userId] = false;
            }
        }

        if (empty($statusMap)) {
            return [];
        }

        if (user_presence_ensure_schema($pdo)) {
            date_default_timezone_set('Asia/Manila');
            $freshWindowSeconds = max(15, (int)$freshWindowSeconds);
            $cutoff = date('Y-m-d H:i:s', time() - $freshWindowSeconds);
            $ids = array_keys($statusMap);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $sql = "SELECT id
                    FROM users
                    WHERE id IN ($placeholders)
                      AND last_active_at IS NOT NULL
                      AND last_active_at >= ?";
            $params = array_merge($ids, [$cutoff]);
            [$sql, $params] = user_model_append_scope($pdo, $sql, $params, 'users');

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $activeUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($activeUserIds as $activeUserId) {
                $statusMap[(int)$activeUserId] = true;
            }
        }

        // Keep attendance as a fallback so existing employee presence behavior does not regress.
        $attendanceMap = get_users_attendance_online_map($pdo, array_keys($statusMap));
        foreach ($attendanceMap as $userId => $isAttendanceOnline) {
            if ($isAttendanceOnline) {
                $statusMap[(int)$userId] = true;
            }
        }

        return $statusMap;
    }
}

if (!function_exists('get_users_clocked_in_map')) {
    function get_users_clocked_in_map($pdo, array $userIds)
    {
        return get_users_online_map($pdo, $userIds);
    }
}

if (!function_exists('chat_user_presence_label')) {
    function chat_user_presence_label($isOnline)
    {
        return $isOnline ? 'Online' : 'Offline';
    }
}

if (!function_exists('chat_user_avatar_html')) {
    function chat_user_avatar_html($user, $isOnline = false)
    {
        $profileUrl = user_profile_image_url($user['profile_image'] ?? '');
        $initials = user_display_initials($user['full_name'] ?? 'User');

        ob_start();
        ?>
        <div class="avatar-md">
            <?php if ($profileUrl !== '') { ?>
                <img src="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Profile">
            <?php } else { ?>
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            <?php } ?>
            <?php if ($isOnline) { ?>
                <span class="chat-avatar-status-dot is-online" aria-hidden="true"></span>
            <?php } ?>
        </div>
        <?php
        return trim(ob_get_clean());
    }
}

if (!function_exists('chat_user_presence_html')) {
    function chat_user_presence_html($isOnline = false)
    {
        $statusClass = $isOnline ? 'is-online' : 'is-offline';
        $label = chat_user_presence_label($isOnline);

        ob_start();
        ?>
        <span class="chat-user-presence <?= $statusClass ?>">
            <span class="chat-user-presence-dot <?= $statusClass ?>" aria-hidden="true"></span>
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php
        return trim(ob_get_clean());
    }
}

if (!function_exists('render_chat_user_list_item')) {
    function render_chat_user_list_item($user, $lastMessage, $unreadCount, $currentUserId)
    {
        $userId = (int)($user['id'] ?? 0);
        $fullName = trim((string)($user['full_name'] ?? 'User'));
        if ($fullName === '') {
            $fullName = 'User';
        }

        $roleLabel = ucfirst((string)($user['role'] ?? 'user'));
        $lastTimestamp = !empty($lastMessage['created_at']) ? strtotime((string)$lastMessage['created_at']) : 0;
        if ($lastTimestamp === false) {
            $lastTimestamp = 0;
        }

        $isOnline = !empty($user['is_online']);
        $unreadCount = (int)$unreadCount;
        $unreadClass = $unreadCount > 0 ? 'unread' : '';

        ob_start();
        ?>
        <div class="chat-item <?= $unreadClass ?>"
             data-id="<?= $userId ?>"
             data-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
             data-role="<?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>"
             data-online="<?= $isOnline ? '1' : '0' ?>"
             data-last-ts="<?= (int)$lastTimestamp ?>">
            <?= chat_user_avatar_html($user, $isOnline) ?>
            <div class="chat-item-content">
                <div class="chat-item-header">
                    <div class="chat-item-identity">
                        <span class="chat-user-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <div class="chat-item-sub-row">
                    <?php if (!empty($lastMessage)) { ?>
                        <div class="chat-item-last-msg">
                            <?php
                            if ((int)($lastMessage['sender_id'] ?? 0) === (int)$currentUserId) {
                                echo "You: ";
                            }
                            if (!empty($lastMessage['attachment']) && empty($lastMessage['message'])) {
                                echo "<i class='fa fa-paperclip'></i> Attachment";
                            } else {
                                echo htmlspecialchars((string)($lastMessage['message'] ?? ''), ENT_QUOTES, 'UTF-8');
                            }
                            ?>
                        </div>
                    <?php } else { ?>
                        <div class="chat-user-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>

                    <?php if ($unreadCount > 0) { ?>
                        <span class="message-badge"><?= $unreadCount ?></span>
                    <?php } ?>
                </div>

                <?php if (!empty($lastMessage) && !empty($lastMessage['created_at']) && function_exists('formatChatTime')) { ?>
                    <span class="chat-time"><?= htmlspecialchars((string)formatChatTime($lastMessage['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
                <?php } ?>
            </div>
            <button
                type="button"
                class="chat-item-delete-btn"
                aria-label="Delete chat with <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                title="Delete chat"
                data-delete-type="user"
                data-delete-id="<?= $userId ?>"
                data-delete-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa fa-trash-o"></i>
            </button>
        </div>
        <?php
        return trim(ob_get_clean());
    }
}

function insert_user($pdo, $data)
{
    $orgId = tenant_get_current_org_id();
    if (tenant_column_exists($pdo, 'users', 'organization_id') && $orgId) {
        $sql = "INSERT INTO users (full_name, username, password, role, organization_id) VALUES(?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data[0], $data[1], $data[2], $data[3], $orgId]);

        if (tenant_table_exists($pdo, 'organization_members')) {
            $userId = (int)$pdo->lastInsertId();
            $memberRole = $data[3] === 'admin' ? 'admin' : 'member';
            $stmt = $pdo->prepare(
                "INSERT INTO organization_members (organization_id, user_id, role)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$orgId, $userId, $memberRole]);
        }
        return;
    }

    $sql = "INSERT INTO users (full_name, username, password, role) VALUES(?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function update_user($pdo, $data)
{
    $sql = "UPDATE users SET full_name=?, username=?, password=?, role=? WHERE id=? AND role=?";
    [$sql, $params] = user_model_append_scope($pdo, $sql, $data, 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function user_has_tasks($pdo, $user_id)
{
    $sql = "SELECT 1 FROM tasks WHERE assigned_to=? AND status != 'completed'";
    [$sql, $params] = user_model_append_scope($pdo, $sql, [$user_id], 'tasks');
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function delete_user($pdo, $data)
{
    try {
        $sql = "DELETE FROM users WHERE id=? AND role=?";
        [$sql, $params] = user_model_append_scope($pdo, $sql, $data, 'users');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function get_user_by_id($pdo, $id)
{
    $sql = "SELECT * FROM users WHERE id = ?";
    [$sql, $params] = user_model_append_scope($pdo, $sql, [$id], 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch();
    return $user ?: 0;
}

function update_profile($pdo, $data)
{
    $sql = "UPDATE users
            SET full_name=?, password=?, bio=?, phone=?, address=?, skills=?, profile_image=?, must_change_password=FALSE
            WHERE id=?";
    [$sql, $params] = user_model_append_scope($pdo, $sql, $data, 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function update_profile_info($pdo, $data)
{
    $sql = "UPDATE users
            SET full_name=?, bio=?, phone=?, address=?, skills=?, profile_image=?
            WHERE id=?";
    [$sql, $params] = user_model_append_scope($pdo, $sql, $data, 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function count_users($pdo)
{
    [$sql, $params] = user_model_append_scope($pdo, "SELECT COUNT(*) FROM users WHERE role='employee'", [], 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function get_user_rating_stats($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) as count, AVG(t.rating) as avg
            FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id = ? AND t.status = 'completed' AND t.rating > 0";
    $params = [$user_id];

    $taskScope = tenant_get_scope($pdo, 'tasks', 't', 'AND');
    $sql .= $taskScope['sql'];
    $params = array_merge($params, $taskScope['params']);
    $assigneeScope = tenant_get_scope($pdo, 'task_assignees', 'ta', 'AND');
    $sql .= $assigneeScope['sql'];
    $params = array_merge($params, $assigneeScope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'count' => $res['count'],
        'avg' => $res['avg'] ? number_format($res['avg'], 1) : "0.0"
    ];
}

function is_super_admin($user_id, $pdo)
{
    $sql = "SELECT username FROM users WHERE id = ? AND role = 'admin'";
    [$sql, $params] = user_model_append_scope($pdo, $sql, [$user_id], 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $username = $stmt->fetchColumn();
    return $username === 'admin';
}

if (!function_exists('user_model_build_public_file_url')) {
    function user_model_build_public_file_url($relativePath)
    {
        $relativePath = trim((string)$relativePath);
        if ($relativePath === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '') {
            return null;
        }

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (!is_file($absolutePath)) {
            return null;
        }

        $mtime = @filemtime($absolutePath);
        return $normalized . '?t=' . ($mtime ? $mtime : time());
    }
}

if (!function_exists('get_active_users_with_pause_state')) {
    function get_active_users_with_pause_state($pdo)
    {
        date_default_timezone_set('Asia/Manila');

        $today = date('Y-m-d');
        $organizationId = tenant_get_current_org_id();
        $sql = "SELECT a.id AS attendance_id, a.user_id, a.time_in, u.full_name, u.username, u.profile_image
                FROM attendance a
                INNER JOIN users u ON a.user_id = u.id
                WHERE a.att_date = ?
                  AND a.time_in IS NOT NULL
                  AND (a.time_out IS NULL OR a.time_out = '00:00:00')
                  AND u.role = 'employee'";
        $params = [$today];

        $scopeAtt = tenant_get_scope($pdo, 'attendance', 'a');
        $sql .= $scopeAtt['sql'];
        $params = array_merge($params, $scopeAtt['params']);

        $scopeUser = tenant_get_scope($pdo, 'users', 'u');
        $sql .= $scopeUser['sql'];
        $params = array_merge($params, $scopeUser['params']);

        $sql .= " ORDER BY a.time_in DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            return [];
        }

        $attendanceIds = [];
        foreach ($rows as $row) {
            $attendanceId = isset($row['attendance_id']) ? (int)$row['attendance_id'] : 0;
            if ($attendanceId > 0) {
                $attendanceIds[] = $attendanceId;
            }
        }

        $pauseSummaries = attendance_pause_get_summary_map($pdo, $attendanceIds, $organizationId, date('Y-m-d H:i:s'));

        foreach ($rows as &$row) {
            $attendanceId = isset($row['attendance_id']) ? (int)$row['attendance_id'] : 0;
            $pauseSummary = $pauseSummaries[$attendanceId] ?? [];
            $row['is_paused'] = !empty($pauseSummary['is_paused']);
            $row['pause_reason'] = $pauseSummary['pause_reason'] ?? null;
            $row['pause_started_at'] = $pauseSummary['paused_at'] ?? null;
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('user_model_get_latest_screenshot_row')) {
    function user_model_get_latest_screenshot_row($pdo, $userId, $organizationId = null, $attendanceId = null)
    {
        $userId = (int)$userId;
        $attendanceId = $attendanceId !== null ? (int)$attendanceId : null;
        if ($userId <= 0) {
            return null;
        }

        $sql = "SELECT attendance_id, image_path, taken_at
                FROM screenshots
                WHERE user_id = ?";
        $params = [$userId];

        if ($attendanceId !== null && $attendanceId > 0) {
            $sql .= " AND attendance_id = ?";
            $params[] = $attendanceId;
        }

        $scope = tenant_get_scope($pdo, 'screenshots', '', 'AND', 'organization_id', $organizationId);
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $sql .= " ORDER BY taken_at DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('get_active_user_dashboard_detail')) {
    function get_active_user_dashboard_detail($pdo, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        date_default_timezone_set('Asia/Manila');

        $today = date('Y-m-d');
        $organizationId = tenant_get_current_org_id();
        $sql = "SELECT a.id AS attendance_id, a.att_date, a.time_in, u.id AS user_id, u.full_name, u.username, u.profile_image
                FROM attendance a
                INNER JOIN users u ON a.user_id = u.id
                WHERE a.user_id = ?
                  AND a.att_date = ?
                  AND a.time_in IS NOT NULL
                  AND (a.time_out IS NULL OR a.time_out = '00:00:00')
                  AND u.role = 'employee'";
        $params = [$userId, $today];

        $scopeAtt = tenant_get_scope($pdo, 'attendance', 'a');
        $sql .= $scopeAtt['sql'];
        $params = array_merge($params, $scopeAtt['params']);

        $scopeUser = tenant_get_scope($pdo, 'users', 'u');
        $sql .= $scopeUser['sql'];
        $params = array_merge($params, $scopeUser['params']);

        $sql .= " ORDER BY a.time_in DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $attendanceId = isset($row['attendance_id']) ? (int)$row['attendance_id'] : 0;
        $attDate = trim((string)($row['att_date'] ?? '')) ?: $today;
        $timeInRaw = trim((string)($row['time_in'] ?? ''));
        $timeInValue = $timeInRaw !== '' ? attendance_pause_build_datetime($attDate, $timeInRaw, $attDate) : null;
        $timeInTs = $timeInValue ? strtotime($timeInValue) : false;

        $activePause = $attendanceId > 0
            ? attendance_pause_get_active($pdo, $attendanceId, $userId, $organizationId)
            : null;
        $isPaused = $activePause ? true : false;
        $pauseReason = $activePause ? trim((string)($activePause['pause_reason'] ?? '')) : '';
        $pauseStartedAt = $activePause ? trim((string)($activePause['paused_at'] ?? '')) : '';

        $latestScreenshot = $attendanceId > 0
            ? user_model_get_latest_screenshot_row($pdo, $userId, $organizationId, $attendanceId)
            : null;
        if (!$latestScreenshot) {
            $latestScreenshot = user_model_get_latest_screenshot_row($pdo, $userId, $organizationId, null);
        }

        $lastScreenshotPath = trim((string)($latestScreenshot['image_path'] ?? ''));
        $lastScreenshotUrl = $lastScreenshotPath !== ''
            ? user_model_build_public_file_url($lastScreenshotPath)
            : null;
        $lastScreenshotTakenAt = trim((string)($latestScreenshot['taken_at'] ?? ''));
        $lastScreenshotTs = $lastScreenshotTakenAt !== '' ? strtotime($lastScreenshotTakenAt) : false;

        $profileImage = trim((string)($row['profile_image'] ?? ''));
        $avatarUrl = $profileImage !== '' ? user_profile_image_url($profileImage) : '';

        return [
            'user_id' => (int)($row['user_id'] ?? $userId),
            'attendance_id' => $attendanceId,
            'full_name' => trim((string)($row['full_name'] ?? '')) ?: 'User',
            'username' => trim((string)($row['username'] ?? '')),
            'initials' => user_display_initials($row['full_name'] ?? ''),
            'avatar_url' => $avatarUrl,
            'status' => $isPaused ? 'paused' : 'active',
            'status_label' => $isPaused ? 'Paused' : 'Active',
            'pause_reason' => $pauseReason !== '' ? $pauseReason : null,
            'pause_started_at' => $pauseStartedAt !== '' ? $pauseStartedAt : null,
            'pause_started_at_label' => $pauseStartedAt !== '' ? date('M d, Y h:i A', strtotime($pauseStartedAt)) : null,
            'last_time_in' => $timeInValue ?: null,
            'last_time_in_label' => $timeInTs ? date('M d, Y h:i A', $timeInTs) : '--',
            'last_screenshot_path' => $lastScreenshotPath !== '' ? $lastScreenshotPath : null,
            'last_screenshot_url' => $lastScreenshotUrl,
            'last_screenshot_taken_at' => $lastScreenshotTakenAt !== '' ? $lastScreenshotTakenAt : null,
            'last_screenshot_label' => $lastScreenshotTs ? date('M d, Y h:i A', $lastScreenshotTs) : 'No screenshots yet',
            'last_screenshot_note' => $lastScreenshotTs
                ? (($attendanceId > 0 && !empty($latestScreenshot['attendance_id']) && (int)$latestScreenshot['attendance_id'] === $attendanceId)
                    ? 'Latest screenshot from this session.'
                    : 'Latest saved screenshot on record.')
                : 'No screenshot available yet.',
            'captures_url' => 'screenshots.php?open_user_id=' . rawurlencode((string)$userId) . '&user_id=' . rawurlencode((string)$userId),
        ];
    }
}

function get_todays_attendance_stats($pdo, $user_id)
{
    date_default_timezone_set('Asia/Manila');

    $sql = "SELECT * FROM attendance WHERE user_id = ?";
    [$sql, $params] = user_model_append_scope($pdo, $sql, [$user_id], 'attendance');
    $sql .= " ORDER BY id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $attendanceIds = [];
    foreach ($records as $record) {
        $recordId = isset($record['id']) ? (int)$record['id'] : 0;
        if ($recordId > 0) {
            $attendanceIds[] = $recordId;
        }
    }
    $pauseSummaries = attendance_pause_get_summary_map($pdo, $attendanceIds, null, date('Y-m-d H:i:s'));

    $total_seconds_all = 0;
    $total_seconds_today = 0;
    $latest_in = null;
    $latest_out = null;
    $latest_attendance_id = null;
    $latest_is_paused = false;
    $latest_pause_reason = null;
    $latest_pause_started_at = null;
    $latest_admin_clock_out_remark = null;
    $today_date = date('Y-m-d');

    foreach ($records as $row) {
        $attendanceId = isset($row['id']) ? (int)$row['id'] : 0;
        $attDate = trim((string)($row['att_date'] ?? ''));
        $inRaw = trim((string)($row['time_in'] ?? ''));
        if ($inRaw === '') {
            continue;
        }

        $inValue = attendance_pause_build_datetime($attDate, $inRaw, $attDate ?: $today_date);
        $in = $inValue ? strtotime($inValue) : false;
        if ($in === false) {
            continue;
        }

        if (!empty($row['time_out']) && $row['time_out'] != '00:00:00') {
            $outValue = attendance_pause_build_datetime($attDate, $row['time_out'], $attDate ?: $today_date);
            $out = $outValue ? strtotime($outValue) : false;
        } else {
            if ($attDate === $today_date) {
                $out = time();
            } else {
                $out = $in;
            }
        }

        if ($out === false) {
            $out = $in;
        }

        $pausedSeconds = (int)($pauseSummaries[$attendanceId]['paused_seconds'] ?? 0);
        $duration = max(0, ($out - $in) - $pausedSeconds);
        $total_seconds_all += $duration;

        if ($attDate === $today_date) {
            $total_seconds_today += $duration;
        }

        $latest_attendance_id = $attendanceId > 0 ? $attendanceId : $latest_attendance_id;
        $latest_in = $row['time_in'];
        $latest_out = $row['time_out'];
        $latest_is_paused = (bool)($pauseSummaries[$attendanceId]['is_paused'] ?? false);
        $latest_pause_reason = $pauseSummaries[$attendanceId]['pause_reason'] ?? null;
        $latest_pause_started_at = $pauseSummaries[$attendanceId]['paused_at'] ?? null;
        $remark = trim((string)($row['admin_clock_out_remark'] ?? ''));
        $latest_admin_clock_out_remark = $remark !== '' ? $remark : null;
    }

    $all_h = floor($total_seconds_all / 3600);
    $all_m = floor(($total_seconds_all % 3600) / 60);
    $day_h = floor($total_seconds_today / 3600);
    $day_m = floor(($total_seconds_today % 3600) / 60);

    return [
        'attendance_id' => (!empty($latest_out) && $latest_out != '00:00:00') ? null : $latest_attendance_id,
        'time_in' => $latest_in ? date("h:i A", strtotime($latest_in)) : '--:--',
        'time_out' => (!empty($latest_out) && $latest_out != '00:00:00') ? date("h:i A", strtotime($latest_out)) : '--:--',
        'total_duration' => "{$all_h}h {$all_m}m",
        'overall_duration' => "{$all_h}h {$all_m}m",
        'daily_duration' => "{$day_h}h {$day_m}m",
        'is_paused' => $latest_is_paused,
        'pause_reason' => $latest_pause_reason,
        'pause_started_at' => $latest_pause_started_at,
        'admin_clock_out_remark' => $latest_admin_clock_out_remark,
        'clocked_out_by_admin' => $latest_admin_clock_out_remark !== null && !empty($latest_out) && $latest_out != '00:00:00',
    ];
}

function is_user_clocked_in($pdo, $user_id)
{
    date_default_timezone_set('Asia/Manila');
    $today = date('Y-m-d');

    $sql = "SELECT id FROM attendance
            WHERE user_id = ?
            AND att_date = ?
            AND time_in IS NOT NULL
            AND (time_out IS NULL OR time_out = '00:00:00')";
    [$sql, $params] = user_model_append_scope($pdo, $sql, [$user_id, $today], 'attendance');
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
}

function get_top_rated_users($pdo, $limit = 5)
{
    $sql = "SELECT id, full_name, profile_image FROM users WHERE role = 'employee'";
    [$sql, $params] = user_model_append_scope($pdo, $sql, [], 'users');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($users as $u) {
        $task_stats = get_user_rating_stats($pdo, $u['id']);
        $rated_task_count = (int)($task_stats['count'] ?? 0);
        $avg_task_rating = $rated_task_count > 0 ? (float)$task_stats['avg'] : null;

        $collab_score_count = 0;
        $avg_collab_rating = null;
        if (function_exists('get_collaborative_scores_by_user')) {
            $collab_stats = get_collaborative_scores_by_user($pdo, $u['id']);
            $collab_score_count = (int)($collab_stats['count'] ?? 0);
            $avg_collab_rating = $collab_score_count > 0 ? (float)$collab_stats['avg'] : null;
        }

        if ($rated_task_count === 0 && $collab_score_count === 0) {
            continue;
        }

        $parts = 0;
        $sum = 0.0;
        if ($avg_task_rating !== null) {
            $sum += $avg_task_rating;
            $parts++;
        }
        if ($avg_collab_rating !== null) {
            $sum += $avg_collab_rating;
            $parts++;
        }
        $avg_rating = $parts > 0 ? $sum / $parts : 0.0;

        $rows[] = [
            'id' => (int)$u['id'],
            'full_name' => $u['full_name'],
            'profile_image' => $u['profile_image'],
            'rated_task_count' => $rated_task_count,
            'collab_score_count' => $collab_score_count,
            'avg_task_rating' => number_format($avg_task_rating ?? 0, 1),
            'avg_collab_rating' => number_format($avg_collab_rating ?? 0, 1),
            'avg_rating' => number_format($avg_rating, 1)
        ];
    }

    usort($rows, function ($a, $b) {
        $a_avg = (float)$a['avg_rating'];
        $b_avg = (float)$b['avg_rating'];
        if ($a_avg !== $b_avg) {
            return $a_avg < $b_avg ? 1 : -1;
        }
        $a_total = (int)$a['rated_task_count'] + (int)$a['collab_score_count'];
        $b_total = (int)$b['rated_task_count'] + (int)$b['collab_score_count'];
        if ($a_total !== $b_total) {
            return $a_total < $b_total ? 1 : -1;
        }
        return strcmp((string)$a['full_name'], (string)$b['full_name']);
    });

    return array_slice($rows, 0, (int)$limit);
}
