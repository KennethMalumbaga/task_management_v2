<?php

require_once __DIR__ . '/../../inc/tenant.php';
require_once __DIR__ . '/../helpers/rating.php';

function subtask_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

function insert_subtask($pdo, $data)
{
    $orgId = tenant_get_current_org_id();
    $hasOrg = tenant_column_exists($pdo, 'subtasks', 'organization_id') && $orgId;

    if ($hasOrg) {
        $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date, organization_id) VALUES (?, ?, ?, ?, ?)";
        $params = [$data[0], $data[1], $data[2], $data[3], $orgId];
    } else {
        $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date) VALUES (?, ?, ?, ?)";
        $params = $data;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

function get_subtasks_by_task($pdo, $task_id)
{
    $sql = "SELECT s.*, u.full_name as member_name
            FROM subtasks s
            JOIN users u ON s.member_id = u.id
            WHERE s.task_id = ?";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$task_id], 'subtasks', 's');
    $sql .= " ORDER BY s.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_subtask_by_id($pdo, $subtask_id)
{
    $sql = "SELECT * FROM subtasks WHERE id = ?";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$subtask_id], 'subtasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_subtasks_by_member($pdo, $member_id)
{
    $sql = "SELECT s.*, t.title as task_title
            FROM subtasks s
            JOIN tasks t ON s.task_id = t.id
            WHERE s.member_id = ?";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$member_id], 'subtasks', 's');
    $sql .= " ORDER BY s.due_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_subtask_submission($pdo, $id, $file_path, $note = null)
{
    $sql = "UPDATE subtasks SET submission_file = ?, submission_note = ?, status = 'submitted', updated_at = NOW() WHERE id = ?";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$file_path, $note, $id], 'subtasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function update_subtask_status($pdo, $id, $status, $feedback = null, $score = null)
{
    $sql = "UPDATE subtasks SET status = ?, feedback = ?, score = ?, updated_at = NOW() WHERE id = ?";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$status, $feedback, $score, $id], 'subtasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function delete_subtask($pdo, $id)
{
    $sql = "DELETE FROM subtasks WHERE id = ?";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$id], 'subtasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function subtask_model_column_exists($pdo, $table, $column)
{
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function subtask_model_table_exists($pdo, $table)
{
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_name = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function subtask_apply_peer_smoothing($peer_raw, $n, $prior_mean = 3.5, $prior_weight = 3)
{
    return tm_apply_peer_rating_smoothing($peer_raw, $n, $prior_mean, $prior_weight);
}

function subtask_blend_leader_admin_member_50_50($admin_avg, $member_avg)
{
    $has_admin = ($admin_avg !== null);
    $has_member = ($member_avg !== null);

    if ($has_admin && $has_member) {
        return (((float)$admin_avg) + ((float)$member_avg)) / 2;
    }
    if ($has_admin) {
        return (float)$admin_avg;
    }
    if ($has_member) {
        return (float)$member_avg;
    }
    return null;
}

/**
 * Get collaborative scores breakdown by project/task for a user
 * Returns overall stats and per-project breakdown
 */
function get_collaborative_scores_by_user($pdo, $user_id)
{
    $user_id = (int)$user_id;

    $sql = "SELECT COUNT(*) FROM task_assignees WHERE user_id = ? AND role = 'leader'";
    [$sql, $params] = subtask_append_scope($pdo, $sql, [$user_id], 'task_assignees');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leader_task_count = (int)$stmt->fetchColumn();

    if ($leader_task_count > 0) {
        $has_leader_admin_rating = subtask_model_column_exists($pdo, 'task_assignees', 'performance_rating');
        $has_leader_feedback_table = subtask_model_table_exists($pdo, 'leader_feedback');

        $admin_count = 0;
        $admin_avg = null;
        if ($has_leader_admin_rating) {
            $sql_admin = "SELECT COUNT(*) AS count, AVG(performance_rating) AS avg
                          FROM task_assignees
                          WHERE user_id = ?
                            AND role = 'leader'
                            AND performance_rating IS NOT NULL
                            AND performance_rating > 0";
            [$sql_admin, $params_admin] = subtask_append_scope($pdo, $sql_admin, [$user_id], 'task_assignees');
            $stmt = $pdo->prepare($sql_admin);
            $stmt->execute($params_admin);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $admin_count = (int)($admin['count'] ?? 0);
            $admin_avg = ($admin_count > 0 && $admin['avg'] !== null) ? (float)$admin['avg'] : null;
        }

        $peer_count = 0;
        $peer_avg_raw = null;
        $peer_avg = null;
        if ($has_leader_feedback_table) {
            $sql_peer = "SELECT COUNT(*) AS count, AVG(rating) AS avg
                         FROM leader_feedback
                         WHERE leader_id = ?";
            [$sql_peer, $params_peer] = subtask_append_scope($pdo, $sql_peer, [$user_id], 'leader_feedback');
            $stmt = $pdo->prepare($sql_peer);
            $stmt->execute($params_peer);
            $peer = $stmt->fetch(PDO::FETCH_ASSOC);
            $peer_count = (int)($peer['count'] ?? 0);
            $peer_avg_raw = ($peer_count > 0 && $peer['avg'] !== null) ? (float)$peer['avg'] : null;
            $peer_avg = $peer_avg_raw;
        }

        $total_count = $admin_count + $peer_count;
        $overall_avg = 0.0;
        $blended_overall = subtask_blend_leader_admin_member_50_50($admin_avg, $peer_avg);
        if ($blended_overall !== null) {
            $overall_avg = $blended_overall;
        }

        $breakdown = [];
        $sql_tasks = "SELECT t.id AS task_id, t.title AS task_title
                      FROM tasks t
                      JOIN task_assignees ta ON ta.task_id = t.id
                      WHERE ta.user_id = ? AND ta.role = 'leader'";
        $params_tasks = [$user_id];
        $scopeTasks = tenant_get_scope($pdo, 'tasks', 't');
        $scopeTa = tenant_get_scope($pdo, 'task_assignees', 'ta');
        $sql_tasks .= $scopeTasks['sql'] . $scopeTa['sql'] . " ORDER BY t.title ASC";
        $params_tasks = array_merge($params_tasks, $scopeTasks['params']);
        $params_tasks = array_merge($params_tasks, $scopeTa['params']);
        $stmt = $pdo->prepare($sql_tasks);
        $stmt->execute($params_tasks);
        $leader_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($leader_tasks as $t) {
            $task_id = (int)$t['task_id'];

            $task_admin_count = 0;
            $task_admin_avg = null;
            if ($has_leader_admin_rating) {
                $sql = "SELECT performance_rating
                        FROM task_assignees
                        WHERE task_id = ?
                          AND user_id = ?
                          AND role = 'leader'
                          AND performance_rating IS NOT NULL
                          AND performance_rating > 0";
                [$sql, $params_local] = subtask_append_scope($pdo, $sql, [$task_id, $user_id], 'task_assignees');
                $sql .= " LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params_local);
                $task_admin_rating = $stmt->fetchColumn();
                if ($task_admin_rating !== false) {
                    $task_admin_count = 1;
                    $task_admin_avg = (float)$task_admin_rating;
                }
            }

            $task_peer_count = 0;
            $task_peer_avg_raw = null;
            $task_peer_avg = null;
            if ($has_leader_feedback_table) {
                $sql = "SELECT COUNT(*) AS count, AVG(rating) AS avg
                        FROM leader_feedback
                        WHERE task_id = ? AND leader_id = ?";
                [$sql, $params_local] = subtask_append_scope($pdo, $sql, [$task_id, $user_id], 'leader_feedback');
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params_local);
                $task_peer = $stmt->fetch(PDO::FETCH_ASSOC);
                $task_peer_count = (int)($task_peer['count'] ?? 0);
                $task_peer_avg_raw = ($task_peer_count > 0 && $task_peer['avg'] !== null) ? (float)$task_peer['avg'] : null;
                $task_peer_avg = $task_peer_avg_raw;
            }

            $task_total_count = $task_admin_count + $task_peer_count;
            if ($task_total_count === 0) {
                continue;
            }

            $task_avg = subtask_blend_leader_admin_member_50_50($task_admin_avg, $task_peer_avg);
            if ($task_avg === null) {
                continue;
            }

            $breakdown[] = [
                'task_id' => $task_id,
                'task_title' => $t['task_title'],
                'subtask_count' => $task_total_count,
                'avg_score' => $task_avg
            ];
        }

        return [
            'count' => $total_count,
            'avg' => number_format($overall_avg, 1),
            'projects' => $breakdown
        ];
    }

    $sql_overall = "SELECT COUNT(*) as count, AVG(s.score) as avg
                    FROM subtasks s
                    WHERE s.member_id = ? AND s.score IS NOT NULL";
    [$sql_overall, $params_overall] = subtask_append_scope($pdo, $sql_overall, [$user_id], 'subtasks', 's');
    $stmt = $pdo->prepare($sql_overall);
    $stmt->execute($params_overall);
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql_breakdown = "SELECT t.id as task_id, t.title as task_title,
                             COUNT(s.id) as subtask_count, AVG(s.score) as avg_score
                      FROM subtasks s
                      JOIN tasks t ON s.task_id = t.id
                      WHERE s.member_id = ? AND s.score IS NOT NULL";
    $params_breakdown = [$user_id];
    $scope = tenant_get_scope($pdo, 'subtasks', 's');
    $sql_breakdown .= $scope['sql'] . "
                      GROUP BY t.id, t.title
                      ORDER BY t.title ASC";
    $params_breakdown = array_merge($params_breakdown, $scope['params']);

    $stmt = $pdo->prepare($sql_breakdown);
    $stmt->execute($params_breakdown);
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'count' => $overall['count'] ?? 0,
        'avg' => $overall['avg'] ? number_format($overall['avg'], 1) : "0.0",
        'projects' => $breakdown
    ];
}
