<?php

require_once __DIR__ . '/../../inc/tenant.php';

function report_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

function report_map_rows_by_user($rows)
{
    $map = [];
    if (!is_array($rows)) {
        return $map;
    }
    foreach ($rows as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $map[$userId] = $row;
    }
    return $map;
}

function report_get_task_metrics($pdo, $userIds, $startDate, $endDate, $startTs, $endTs)
{
    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT
                ta.user_id,
                SUM(CASE WHEN t.status = 'pending' AND t.due_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN t.status IN ('in_progress', 'revise', 'rejected') AND t.due_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN t.status = 'completed' AND t.reviewed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN t.status = 'completed' AND t.reviewed_at BETWEEN ? AND ? AND DATE(t.reviewed_at) <= t.due_date THEN 1 ELSE 0 END) AS completed_on_time,
                SUM(CASE WHEN t.status <> 'completed' AND t.due_date < ? THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN t.status = 'completed' AND t.reviewed_at BETWEEN ? AND ? AND (t.rating IS NULL OR t.rating <= 0) THEN 1 ELSE 0 END) AS unrated_completed,
                SUM(CASE WHEN t.status = 'completed' AND t.reviewed_at BETWEEN ? AND ? AND t.rating > 0 THEN t.rating ELSE 0 END) AS task_rating_sum,
                SUM(CASE WHEN t.status = 'completed' AND t.reviewed_at BETWEEN ? AND ? AND t.rating > 0 THEN 1 ELSE 0 END) AS task_rating_count
            FROM task_assignees ta
            JOIN tasks t ON t.id = ta.task_id
            WHERE ta.user_id IN ($placeholders)";

    $params = [
        $startDate, $endDate,
        $startDate, $endDate,
        $startTs, $endTs,
        $startTs, $endTs,
        $endDate,
        $startTs, $endTs,
        $startTs, $endTs,
        $startTs, $endTs,
    ];
    $params = array_merge($params, $userIds);

    $scope = tenant_get_scope($pdo, 'task_assignees', 'ta', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $scope = tenant_get_scope($pdo, 'tasks', 't', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $sql .= " GROUP BY ta.user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return report_map_rows_by_user($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function report_get_assignee_rating_metrics($pdo, $userIds, $startTs, $endTs)
{
    if (empty($userIds)) {
        return [];
    }

    if (!tenant_column_exists($pdo, 'task_assignees', 'performance_rating')
        || !tenant_column_exists($pdo, 'task_assignees', 'rated_at')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT
                ta.user_id,
                SUM(ta.performance_rating) AS rating_sum,
                COUNT(*) AS rating_count
            FROM task_assignees ta
            WHERE ta.user_id IN ($placeholders)
              AND ta.performance_rating IS NOT NULL
              AND ta.performance_rating > 0
              AND ta.rated_at BETWEEN ? AND ?";

    $params = array_merge($userIds, [$startTs, $endTs]);
    $scope = tenant_get_scope($pdo, 'task_assignees', 'ta', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $sql .= " GROUP BY ta.user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return report_map_rows_by_user($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function report_get_subtask_score_metrics($pdo, $userIds, $startTs, $endTs)
{
    if (empty($userIds)) {
        return [];
    }

    $dateColumn = 'updated_at';
    if (tenant_column_exists($pdo, 'subtasks', 'reviewed_at')) {
        $dateColumn = 'reviewed_at';
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT
                s.member_id AS user_id,
                SUM(s.score) AS score_sum,
                COUNT(*) AS score_count
            FROM subtasks s
            WHERE s.member_id IN ($placeholders)
              AND s.score IS NOT NULL
              AND s.score > 0
              AND s.$dateColumn BETWEEN ? AND ?";

    $params = array_merge($userIds, [$startTs, $endTs]);
    $scope = tenant_get_scope($pdo, 'subtasks', 's', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $sql .= " GROUP BY s.member_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return report_map_rows_by_user($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function report_get_attendance_metrics($pdo, $userIds, $startDate, $endDate)
{
    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT
                a.user_id,
                SUM(a.total_hours) AS total_hours,
                COUNT(DISTINCT a.att_date) AS days_count
            FROM attendance a
            WHERE a.user_id IN ($placeholders)
              AND a.att_date BETWEEN ? AND ?";

    $params = array_merge($userIds, [$startDate, $endDate]);
    $scope = tenant_get_scope($pdo, 'attendance', 'a', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $sql .= " GROUP BY a.user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return report_map_rows_by_user($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function report_get_capture_metrics($pdo, $userIds, $startDate, $endDate)
{
    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT
                s.user_id,
                COUNT(*) AS capture_count
            FROM screenshots s
            WHERE s.user_id IN ($placeholders)
              AND DATE(s.taken_at) BETWEEN ? AND ?";

    $params = array_merge($userIds, [$startDate, $endDate]);
    $scope = tenant_get_scope($pdo, 'screenshots', 's', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $sql .= " GROUP BY s.user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return report_map_rows_by_user($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function report_get_attendance_days($pdo, $userIds, $startDate, $endDate)
{
    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT
                a.user_id,
                a.att_date
            FROM attendance a
            WHERE a.user_id IN ($placeholders)
              AND a.att_date BETWEEN ? AND ?
              AND a.total_hours > 0";

    $params = array_merge($userIds, [$startDate, $endDate]);
    $scope = tenant_get_scope($pdo, 'attendance', 'a', 'AND');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);
    $sql .= " GROUP BY a.user_id, a.att_date";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (int)($row['user_id'] ?? 0);
        $dateKey = (string)($row['att_date'] ?? '');
        if ($uid <= 0 || $dateKey === '') {
            continue;
        }
        if (!isset($map[$uid])) {
            $map[$uid] = [];
        }
        $map[$uid][$dateKey] = true;
    }
    return $map;
}
