<?php

require_once __DIR__ . '/../../inc/tenant.php';
require_once __DIR__ . '/../../inc/attendance_pause.php';

function user_model_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
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
