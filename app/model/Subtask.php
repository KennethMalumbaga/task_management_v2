<?php

function insert_subtask($pdo, $data){
    $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return $pdo->lastInsertId();
}

function get_subtasks_by_task($pdo, $task_id){
    $sql = "SELECT s.*, u.full_name as member_name 
            FROM subtasks s
            JOIN users u ON s.member_id = u.id
            WHERE s.task_id = ?
            ORDER BY s.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$task_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_subtask_by_id($pdo, $subtask_id){
    $sql = "SELECT * FROM subtasks WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subtask_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_subtasks_by_member($pdo, $member_id){
    $sql = "SELECT s.*, t.title as task_title 
            FROM subtasks s
            JOIN tasks t ON s.task_id = t.id
            WHERE s.member_id = ?
            ORDER BY s.due_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_subtask_submission($pdo, $id, $file_path, $note = null){
    $sql = "UPDATE subtasks SET submission_file = ?, submission_note = ?, status = 'submitted', updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$file_path, $note, $id]);
}

function update_subtask_status($pdo, $id, $status, $feedback = null, $score = null){
    $sql = "UPDATE subtasks SET status = ?, feedback = ?, score = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $feedback, $score, $id]);
}

function delete_subtask($pdo, $id){
    $stmt = $pdo->prepare("DELETE FROM subtasks WHERE id = ?");
    $stmt->execute([$id]);
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
    $n = (int)$n;
    if ($n <= 0 || $peer_raw === null) {
        return null;
    }

    $peer_raw = (float)$peer_raw;
    $prior_mean = (float)$prior_mean;
    $prior_weight = (float)$prior_weight;

    return (($n / ($n + $prior_weight)) * $peer_raw)
         + (($prior_weight / ($n + $prior_weight)) * $prior_mean);
}

/**
 * Get collaborative scores breakdown by project/task for a user
 * Returns overall stats and per-project breakdown
 */
function get_collaborative_scores_by_user($pdo, $user_id) {
    $user_id = (int)$user_id;

    // If user is a leader in at least one task, collaborative score is based on:
    // 1) Admin's leader rating (task_assignees.performance_rating for leader row)
    // 2) Team members' leader feedback (leader_feedback.rating)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE user_id = ? AND role = 'leader'");
    $stmt->execute([$user_id]);
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
            $stmt = $pdo->prepare($sql_admin);
            $stmt->execute([$user_id]);
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
            $stmt = $pdo->prepare($sql_peer);
            $stmt->execute([$user_id]);
            $peer = $stmt->fetch(PDO::FETCH_ASSOC);
            $peer_count = (int)($peer['count'] ?? 0);
            $peer_avg_raw = ($peer_count > 0 && $peer['avg'] !== null) ? (float)$peer['avg'] : null;
            $peer_avg = subtask_apply_peer_smoothing($peer_avg_raw, $peer_count);
        }

        $total_count = $admin_count + $peer_count;
        $overall_avg = 0.0;
        if ($total_count > 0) {
            $weighted_sum = ($admin_avg !== null ? $admin_avg * $admin_count : 0)
                          + ($peer_avg !== null ? $peer_avg * $peer_count : 0);
            $overall_avg = $weighted_sum / $total_count;
        }

        $breakdown = [];
        $sql_tasks = "SELECT t.id AS task_id, t.title AS task_title
                      FROM tasks t
                      JOIN task_assignees ta ON ta.task_id = t.id
                      WHERE ta.user_id = ? AND ta.role = 'leader'
                      ORDER BY t.title ASC";
        $stmt = $pdo->prepare($sql_tasks);
        $stmt->execute([$user_id]);
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
                          AND performance_rating > 0
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$task_id, $user_id]);
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
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$task_id, $user_id]);
                $task_peer = $stmt->fetch(PDO::FETCH_ASSOC);
                $task_peer_count = (int)($task_peer['count'] ?? 0);
                $task_peer_avg_raw = ($task_peer_count > 0 && $task_peer['avg'] !== null) ? (float)$task_peer['avg'] : null;
                $task_peer_avg = subtask_apply_peer_smoothing($task_peer_avg_raw, $task_peer_count);
            }

            $task_total_count = $task_admin_count + $task_peer_count;
            if ($task_total_count === 0) {
                continue;
            }

            $task_weighted_sum = ($task_admin_avg !== null ? $task_admin_avg * $task_admin_count : 0)
                               + ($task_peer_avg !== null ? $task_peer_avg * $task_peer_count : 0);
            $task_avg = $task_weighted_sum / $task_total_count;

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

    // Member collaborative score: based on scored subtasks.
    $sql_overall = "SELECT COUNT(*) as count, AVG(s.score) as avg 
                    FROM subtasks s 
                    WHERE s.member_id = ? AND s.score IS NOT NULL";
    $stmt = $pdo->prepare($sql_overall);
    $stmt->execute([$user_id]);
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql_breakdown = "SELECT t.id as task_id, t.title as task_title, 
                             COUNT(s.id) as subtask_count, AVG(s.score) as avg_score
                      FROM subtasks s
                      JOIN tasks t ON s.task_id = t.id
                      WHERE s.member_id = ? AND s.score IS NOT NULL
                      GROUP BY t.id, t.title
                      ORDER BY t.title ASC";
    $stmt = $pdo->prepare($sql_breakdown);
    $stmt->execute([$user_id]);
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'count' => $overall['count'] ?? 0,
        'avg' => $overall['avg'] ? number_format($overall['avg'], 1) : "0.0",
        'projects' => $breakdown
    ];
}
