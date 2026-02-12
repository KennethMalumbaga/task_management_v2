<?php

function smooth_peer_rating($peer_raw, $n, $prior_mean = 3.5, $prior_weight = 3)
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

function leader_feedback_table_exists($pdo)
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $sql = "SELECT 1 FROM information_schema.tables WHERE table_name = 'leader_feedback' LIMIT 1";
    $stmt = $pdo->query($sql);
    $exists = (bool)$stmt->fetchColumn();
    return $exists;
}

function get_member_leader_feedback($pdo, $task_id, $leader_id, $member_id)
{
    if (!leader_feedback_table_exists($pdo)) {
        return null;
    }

    $sql = "SELECT rating, comment, created_at, updated_at
            FROM leader_feedback
            WHERE task_id = ? AND leader_id = ? AND member_id = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$task_id, (int)$leader_id, (int)$member_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_leader_peer_feedback_stats($pdo, $leader_id)
{
    if (!leader_feedback_table_exists($pdo)) {
        return ['count' => 0, 'avg' => '0.0', 'raw_avg' => '0.0'];
    }

    $sql = "SELECT COUNT(*) AS count, AVG(rating) AS avg
            FROM leader_feedback
            WHERE leader_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$leader_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    $count = (int)($res['count'] ?? 0);
    $raw_avg = ($count > 0 && !empty($res['avg'])) ? (float)$res['avg'] : null;
    $adj_avg = smooth_peer_rating($raw_avg, $count);

    return [
        'count' => $count,
        'avg' => $adj_avg !== null ? number_format($adj_avg, 1) : '0.0',
        'raw_avg' => $raw_avg !== null ? number_format($raw_avg, 1) : '0.0'
    ];
}

function get_leader_feedback_for_task($pdo, $task_id, $leader_id)
{
    if (!leader_feedback_table_exists($pdo)) {
        return [];
    }

    $sql = "SELECT lf.member_id,
                   lf.rating,
                   lf.comment,
                   lf.created_at,
                   lf.updated_at,
                   u.full_name AS member_name,
                   u.profile_image AS member_profile_image
            FROM leader_feedback lf
            JOIN users u ON u.id = lf.member_id
            WHERE lf.task_id = ? AND lf.leader_id = ?
            ORDER BY lf.updated_at DESC NULLS LAST, lf.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$task_id, (int)$leader_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function can_member_rate_leader($pdo, $task_id, $member_id)
{
    $sql = "SELECT leader.user_id AS leader_id
            FROM tasks t
            JOIN task_assignees member
              ON member.task_id = t.id
             AND member.user_id = ?
             AND member.role = 'member'
            JOIN task_assignees leader
              ON leader.task_id = t.id
             AND leader.role = 'leader'
            WHERE t.id = ?
              AND t.status = 'completed'
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$member_id, (int)$task_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function upsert_leader_feedback($pdo, $task_id, $leader_id, $member_id, $rating, $comment = null)
{
    if (!leader_feedback_table_exists($pdo)) {
        return false;
    }

    $sql = "INSERT INTO leader_feedback (task_id, leader_id, member_id, rating, comment, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON CONFLICT (task_id, leader_id, member_id)
            DO UPDATE SET rating = EXCLUDED.rating,
                          comment = EXCLUDED.comment,
                          updated_at = NOW()";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([(int)$task_id, (int)$leader_id, (int)$member_id, (int)$rating, $comment]);
}

function clear_leader_feedback_for_task($pdo, $task_id)
{
    if (!leader_feedback_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare("DELETE FROM leader_feedback WHERE task_id = ?");
    return $stmt->execute([(int)$task_id]);
}
