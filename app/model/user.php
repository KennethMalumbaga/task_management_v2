<?php


function get_all_users($pdo, $role = 'all')
{
    if ($role === 'all') {
        $sql = "SELECT * FROM users";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    else {
        $sql = "SELECT * FROM users WHERE role = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$role]);
    }
    $users = $stmt->fetchAll();
    return $users ?: [];
}


function insert_user($pdo, $data)
{
    $sql = "INSERT INTO users (full_name, username, password, role) VALUES(?,?,?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function update_user($pdo, $data)
{
    $sql = "UPDATE users SET full_name=?, username=?, password=?, role=? WHERE id=? AND role=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function user_has_tasks($pdo, $user_id)
{
    // Only check for active tasks (not completed)
    // Users with only completed tasks can be deleted
    $sql = "SELECT 1 FROM tasks WHERE assigned_to=? AND status != 'completed' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return (bool)$stmt->fetchColumn();
}

function delete_user($pdo, $data)
{
    try {
        $sql = "DELETE FROM users WHERE id=? AND role=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        return true;
    }
    catch (PDOException $e) {
        return false;
    }
}


function get_user_by_id($pdo, $id)
{
    $sql = "SELECT * FROM users WHERE id = ? ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: 0;
}

function update_profile($pdo, $data)
{
    $sql = "UPDATE users SET full_name=?, password=?, bio=?, phone=?, address=?, skills=?, profile_image=?, must_change_password=FALSE WHERE id=? ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function update_profile_info($pdo, $data)
{
    $sql = "UPDATE users SET full_name=?, bio=?, phone=?, address=?, skills=?, profile_image=? WHERE id=? ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function count_users($pdo)
{
    $sql = "SELECT COUNT(*) FROM users WHERE role='employee'";
    $stmt = $pdo->query($sql);
    return $stmt->fetchColumn();
}

function get_user_rating_stats($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) as count, AVG(t.rating) as avg 
            FROM tasks t 
            JOIN task_assignees ta ON t.id = ta.task_id 
            WHERE ta.user_id = ? AND t.status = 'completed' AND t.rating > 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'count' => $res['count'],
        'avg' => $res['avg'] ? number_format($res['avg'], 1) : "0.0"
    ];
}

function is_super_admin($user_id, $pdo)
{
    $sql = "SELECT username FROM users WHERE id = ? AND role = 'admin'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $username = $stmt->fetchColumn();
    return $username === 'admin';
}

function get_todays_attendance_stats($pdo, $user_id)
{
    date_default_timezone_set('Asia/Manila'); // Ensure correct timezone

    // 1. Get ALL records for total duration
    $sql = "SELECT * FROM attendance 
            WHERE user_id = ? 
            ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_seconds_all = 0;
    $total_seconds_today = 0;
    $latest_in = null;
    $latest_out = null;
    $today_date = date('Y-m-d');

    foreach ($records as $row) {
        $in = strtotime($row['time_in']);
        // Active session check
        if (!empty($row['time_out']) && $row['time_out'] != '00:00:00') {
            $out = strtotime($row['time_out']);
        }
        else {
            // If active and it's TODAY, count up to now (realtime)
            if (isset($row['att_date']) && $row['att_date'] == $today_date) {
                $out = time();
            }
            else {
                // Old unclosed records -> ignore duration (or assume closed at end of day?)
                // Simplest is to ignore duration for invalid old sessions
                $out = $in;
            }
        }

        $duration = max(0, $out - $in);
        $total_seconds_all += $duration;

        if (isset($row['att_date']) && $row['att_date'] == $today_date) {
            $total_seconds_today += $duration;
        // Update latest (only if today, or maybe just last record?)
        // Actually usually we want the Very Last Record's info
        }

        // Track latest record regardless of date for "Last Action"
        $latest_in = $row['time_in'];
        $latest_out = $row['time_out'];
    }

    // Format totals
    $all_h = floor($total_seconds_all / 3600);
    $all_m = floor(($total_seconds_all % 3600) / 60);

    $day_h = floor($total_seconds_today / 3600);
    $day_m = floor(($total_seconds_today % 3600) / 60);

    return [
        'time_in' => $latest_in ? date("h:i A", strtotime($latest_in)) : '--:--',
        'time_out' => (!empty($latest_out) && $latest_out != '00:00:00') ? date("h:i A", strtotime($latest_out)) : '--:--',
        'total_duration' => "{$all_h}h {$all_m}m", // Keeping this as Overall check
        'overall_duration' => "{$all_h}h {$all_m}m",
        'daily_duration' => "{$day_h}h {$day_m}m"
    ];
}

/**
 * Check if a user is currently clocked in (has active attendance session today)
 */
function is_user_clocked_in($pdo, $user_id)
{
    date_default_timezone_set('Asia/Manila');
    $today = date('Y-m-d');

    $sql = "SELECT id FROM attendance 
            WHERE user_id = ? 
            AND att_date = ? 
            AND time_in IS NOT NULL 
            AND (time_out IS NULL OR time_out = '00:00:00')
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $today]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
}

function get_top_rated_users($pdo, $limit = 5)
{
    $stmt = $pdo->prepare("SELECT id, full_name, profile_image FROM users WHERE role = 'employee'");
    $stmt->execute();
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
