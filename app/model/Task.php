<?php

require_once __DIR__ . '/../../inc/tenant.php';

/* ---------------------------------------------
   HELPER: CHECK COLUMN EXISTS
--------------------------------------------- */
function column_exists($pdo, $table, $column)
{
    $sql = "SELECT 1 FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function task_model_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

/* ---------------------------------------------
   INSERT, UPDATE, DELETE TASKS
--------------------------------------------- */

function insert_task($pdo, $data)
{
    $has_template_file = column_exists($pdo, 'tasks', 'template_file');
    $orgId = tenant_get_current_org_id();
    $has_org = column_exists($pdo, 'tasks', 'organization_id') && $orgId;

    if ($has_template_file && isset($data[4])) {
        if ($has_org) {
            $sql = "INSERT INTO tasks
                    (title, description, assigned_to, due_date, template_file, organization_id)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$data[0], $data[1], $data[2], $data[3], $data[4], $orgId];
        } else {
            $sql = "INSERT INTO tasks
                    (title, description, assigned_to, due_date, template_file)
                    VALUES (?, ?, ?, ?, ?)";
            $params = [$data[0], $data[1], $data[2], $data[3], $data[4]];
        }
    } else {
        if ($has_org) {
            $sql = "INSERT INTO tasks
                    (title, description, assigned_to, due_date, organization_id)
                    VALUES (?, ?, ?, ?, ?)";
            $params = [$data[0], $data[1], $data[2], $data[3], $orgId];
        } else {
            $sql = "INSERT INTO tasks
                    (title, description, assigned_to, due_date)
                    VALUES (?, ?, ?, ?)";
            $params = array_slice($data, 0, 4);
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

function task_title_exists($pdo, $title)
{
    $sql = "SELECT COUNT(*) FROM tasks WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [$title], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function update_task($pdo, $data)
{
    $has_template_file = column_exists($pdo, 'tasks', 'template_file');

    if ($has_template_file && count($data) === 9) {
        $sql = "UPDATE tasks SET
                    title=?,
                    description=?,
                    assigned_to=?,
                    due_date=?,
                    status=?,
                    review_comment=?,
                    reviewed_by=?,
                    reviewed_at=NOW(),
                    template_file=?
                WHERE id=?";
    } else {
        $sql = "UPDATE tasks SET
                    title=?,
                    description=?,
                    assigned_to=?,
                    due_date=?,
                    status=?,
                    review_comment=?,
                    reviewed_by=?,
                    reviewed_at=NOW()
                WHERE id=?";
    }

    [$sql, $params] = task_model_append_scope($pdo, $sql, $data, 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function update_task_status($pdo, $data)
{
    $sql = "UPDATE tasks SET status=? WHERE id=?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, $data, 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function update_task_submission($pdo, $data)
{
    $sql = "UPDATE tasks SET
                submission_file=?,
                status='in_progress',
                review_comment=NULL,
                reviewed_by=NULL,
                reviewed_at=NULL
            WHERE id=?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, $data, 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function delete_task($pdo, $data)
{
    $sql = "DELETE FROM tasks WHERE id=?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, $data, 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function unassign_completed_tasks($pdo, $user_id)
{
    $sql = "UPDATE tasks SET assigned_to=NULL
            WHERE assigned_to=? AND status='completed'";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [$user_id], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/* ---------------------------------------------
   TASK ASSIGNEES
--------------------------------------------- */

function insert_task_assignees($pdo, $task_id, $leader_id, $members = [])
{
    $orgId = tenant_get_current_org_id();
    $has_org = column_exists($pdo, 'task_assignees', 'organization_id') && $orgId;

    if ($has_org) {
        $stmt = $pdo->prepare(
            "INSERT INTO task_assignees (task_id, user_id, role, organization_id) VALUES (?, ?, 'leader', ?)"
        );
        $stmt->execute([$task_id, $leader_id, $orgId]);

        $stmt = $pdo->prepare(
            "INSERT INTO task_assignees (task_id, user_id, role, organization_id) VALUES (?, ?, 'member', ?)"
        );
        foreach ($members as $id) {
            if ($id != $leader_id) {
                $stmt->execute([$task_id, $id, $orgId]);
            }
        }
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO task_assignees (task_id, user_id, role) VALUES (?, ?, 'leader')"
    );
    $stmt->execute([$task_id, $leader_id]);

    $stmt = $pdo->prepare(
        "INSERT INTO task_assignees (task_id, user_id, role) VALUES (?, ?, 'member')"
    );
    foreach ($members as $id) {
        if ($id != $leader_id) {
            $stmt->execute([$task_id, $id]);
        }
    }
}

function update_task_assignees($pdo, $task_id, $leader_id, $members = [])
{
    $sql = "DELETE FROM task_assignees WHERE task_id=?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [$task_id], 'task_assignees');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    insert_task_assignees($pdo, $task_id, $leader_id, $members);
}

/* ---------------------------------------------
   TASK FETCHING
--------------------------------------------- */

function get_task_by_id($pdo, $id)
{
    $sql = "SELECT * FROM tasks WHERE id=?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [$id], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_task_by_title($pdo, $title)
{
    $title = trim((string)$title);
    if ($title === '') {
        return 0;
    }

    $sql = "SELECT * FROM tasks WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [$title], 'tasks');
    $sql .= " ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    return $task ?: 0;
}

function get_all_tasks($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT * FROM tasks WHERE 1=1", [], 'tasks');
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_pending($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT * FROM tasks WHERE status = 'pending'", [], 'tasks');
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_in_progress($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT * FROM tasks WHERE status = 'in_progress'", [], 'tasks');
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_completed($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT * FROM tasks WHERE status = 'completed'", [], 'tasks');
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_task_assignees($pdo, $task_id)
{
    $has_rating = column_exists($pdo, 'task_assignees', 'performance_rating');
    $has_comment = column_exists($pdo, 'task_assignees', 'rating_comment');

    if ($has_rating && $has_comment) {
        $sql = "SELECT u.full_name, ta.role, ta.user_id, u.profile_image, ta.performance_rating, ta.rating_comment
                FROM users u
                JOIN task_assignees ta ON u.id = ta.user_id
                WHERE ta.task_id = ?";
    } else {
        $sql = "SELECT u.full_name, ta.role, ta.user_id, u.profile_image, NULL AS performance_rating, NULL AS rating_comment
                FROM users u
                JOIN task_assignees ta ON u.id = ta.user_id
                WHERE ta.task_id = ?";
    }

    [$sql, $params] = task_model_append_scope($pdo, $sql, [$task_id], 'task_assignees', 'ta');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return 0;
}

function task_assignee_rating_columns_exist($pdo)
{
    return column_exists($pdo, 'task_assignees', 'performance_rating')
        && column_exists($pdo, 'task_assignees', 'rated_by')
        && column_exists($pdo, 'task_assignees', 'rated_at')
        && column_exists($pdo, 'task_assignees', 'rating_comment');
}

function update_task_assignee_ratings($pdo, $task_id, $ratings, $rated_by, $comments = [])
{
    if (!task_assignee_rating_columns_exist($pdo)) {
        return false;
    }

    $sql = "UPDATE task_assignees
            SET performance_rating = ?, rating_comment = ?, rated_by = ?, rated_at = NOW()
            WHERE task_id = ? AND user_id = ?";
    $baseScope = tenant_get_scope($pdo, 'task_assignees');
    $sql .= $baseScope['sql'];

    $stmt = $pdo->prepare($sql);
    foreach ($ratings as $user_id => $rating) {
        $comment = isset($comments[$user_id]) ? $comments[$user_id] : null;
        $params = [(int)$rating, $comment, (int)$rated_by, (int)$task_id, (int)$user_id];
        $params = array_merge($params, $baseScope['params']);
        $stmt->execute($params);
    }

    return true;
}

function clear_task_assignee_ratings($pdo, $task_id)
{
    if (!task_assignee_rating_columns_exist($pdo)) {
        return false;
    }

    $sql = "UPDATE task_assignees
            SET performance_rating = NULL, rating_comment = NULL, rated_by = NULL, rated_at = NULL
            WHERE task_id = ?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [(int)$task_id], 'task_assignees');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return true;
}

/* ---------------------------------------------
   DUE / OVERDUE
--------------------------------------------- */

function get_all_tasks_due_today($pdo)
{
    $sql = "SELECT * FROM tasks
            WHERE due_date = CURRENT_DATE AND status != 'completed'";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_overdue($pdo)
{
    $sql = "SELECT * FROM tasks
            WHERE due_date < CURRENT_DATE AND status != 'completed'";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_NoDeadline($pdo)
{
    $sql = "SELECT * FROM tasks
            WHERE due_date IS NULL AND status != 'completed'";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------------------------------------
   STATUS COUNTS
--------------------------------------------- */

function count_tasks($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE 1=1", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_tasks_due_today($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE due_date = CURRENT_DATE AND status != 'completed'", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_tasks_overdue($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE due_date < CURRENT_DATE AND status != 'completed'", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_tasks_NoDeadline($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE due_date IS NULL AND status != 'completed'", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_pending_tasks($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE status='pending'", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_in_progress_tasks($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE status='in_progress'", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_completed_tasks($pdo)
{
    [$sql, $params] = task_model_append_scope($pdo, "SELECT COUNT(*) FROM tasks WHERE status='completed'", [], 'tasks');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_tasks($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM task_assignees WHERE user_id=?";
    [$sql, $params] = task_model_append_scope($pdo, $sql, [$user_id], 'task_assignees');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_tasks_overdue($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.due_date < CURRENT_DATE AND t.status != 'completed'";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_tasks_NoDeadline($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.due_date IS NULL AND t.status != 'completed'";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_pending_tasks($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status = 'pending'";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_in_progress_tasks($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status = 'in_progress'";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_completed_tasks($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status = 'completed'";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function count_my_active_tasks($pdo, $user_id)
{
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status != 'completed'";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/* ---------------------------------------------
   USER TASKS (task_assignees)
--------------------------------------------- */

function get_all_tasks_by_user($pdo, $user_id)
{
    $sql = "SELECT DISTINCT t.* FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=?";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'] . " ORDER BY t.id DESC";
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_employee_task_progress($pdo, $user_id)
{
    $sqlTotal = "SELECT COUNT(DISTINCT task_id) FROM task_assignees WHERE user_id=?";
    [$sqlTotal, $paramsTotal] = task_model_append_scope($pdo, $sqlTotal, [$user_id], 'task_assignees');
    $totalStmt = $pdo->prepare($sqlTotal);
    $totalStmt->execute($paramsTotal);

    $sqlCompleted = "SELECT COUNT(DISTINCT ta.task_id)
                     FROM task_assignees ta
                     JOIN tasks t ON t.id = ta.task_id
                     WHERE ta.user_id=? AND t.status='completed'";
    $paramsCompleted = [$user_id];
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sqlCompleted .= $scope['sql'];
    $paramsCompleted = array_merge($paramsCompleted, $scope['params']);

    $completedStmt = $pdo->prepare($sqlCompleted);
    $completedStmt->execute($paramsCompleted);

    $total = (int)$totalStmt->fetchColumn();
    $completed = (int)$completedStmt->fetchColumn();

    return [
        'total' => $total,
        'completed' => $completed,
        'percentage' => $total ? round(($completed / $total) * 100) : 0
    ];
}
