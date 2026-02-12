<?php

/* ---------------------------------------------
   HELPER: CHECK COLUMN EXISTS (PostgreSQL)
--------------------------------------------- */
function column_exists($pdo, $table, $column){
    $sql = "SELECT 1 FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

/* ---------------------------------------------
   INSERT, UPDATE, DELETE TASKS
--------------------------------------------- */

function insert_task($pdo, $data){
    $has_template_file = column_exists($pdo, 'tasks', 'template_file');

    if ($has_template_file && isset($data[4])) {
        $sql = "INSERT INTO tasks 
                (title, description, assigned_to, due_date, template_file) 
                VALUES (?, ?, ?, ?, ?)";
    } else {
        $sql = "INSERT INTO tasks 
                (title, description, assigned_to, due_date) 
                VALUES (?, ?, ?, ?)";
        $data = array_slice($data, 0, 4);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return $pdo->lastInsertId();
}

function task_title_exists($pdo, $title){
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))"
    );
    $stmt->execute([$title]);
    return (int)$stmt->fetchColumn() > 0;
}

function update_task($pdo, $data){
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

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function update_task_status($pdo, $data){
    $stmt = $pdo->prepare("UPDATE tasks SET status=? WHERE id=?");
    $stmt->execute($data);
}

function update_task_submission($pdo, $data){
    $sql = "UPDATE tasks SET
                submission_file=?,
                status='in_progress',
                review_comment=NULL,
                reviewed_by=NULL,
                reviewed_at=NULL
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function delete_task($pdo, $data){
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->execute($data);
}

function unassign_completed_tasks($pdo, $user_id){
    $stmt = $pdo->prepare(
        "UPDATE tasks SET assigned_to=NULL 
         WHERE assigned_to=? AND status='completed'"
    );
    $stmt->execute([$user_id]);
}

/* ---------------------------------------------
   TASK ASSIGNEES
--------------------------------------------- */

function insert_task_assignees($pdo, $task_id, $leader_id, $members=[]){
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

function update_task_assignees($pdo, $task_id, $leader_id, $members=[]){
    // 1. Delete all existing assignees for this task
    $stmt = $pdo->prepare("DELETE FROM task_assignees WHERE task_id=?");
    $stmt->execute([$task_id]);

    // 2. Re-insert Leader
    $stmt = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role) VALUES (?, ?, 'leader')");
    $stmt->execute([$task_id, $leader_id]);

    // 3. Re-insert Members
    if (!empty($members)) {
        $stmtMember = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, role) VALUES (?, ?, 'member')");
        foreach ($members as $mem_id) {
            if ($mem_id != $leader_id) {
                 $stmtMember->execute([$task_id, $mem_id]);
            }
        }
    }
}

/* ---------------------------------------------
   TASK FETCHING
--------------------------------------------- */

/* ---------------------------------------------
   TASK FETCHING
--------------------------------------------- */

function get_task_by_id($pdo, $id){
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_all_tasks($pdo){
    return $pdo->query("SELECT * FROM tasks ORDER BY id DESC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_pending($pdo){
    return $pdo->query("SELECT * FROM tasks WHERE status = 'pending' ORDER BY id DESC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_in_progress($pdo){
    return $pdo->query("SELECT * FROM tasks WHERE status = 'in_progress' ORDER BY id DESC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_completed($pdo){
    return $pdo->query("SELECT * FROM tasks WHERE status = 'completed' ORDER BY id DESC")
               ->fetchAll(PDO::FETCH_ASSOC);
}

function get_task_assignees($pdo, $task_id){
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
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$task_id]);
    
    if($stmt->rowCount() > 0){
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }else{
        return 0; // Keeping 0 to match tasks.php check: if ($assignees != 0)
    }
}

function task_assignee_rating_columns_exist($pdo){
    return column_exists($pdo, 'task_assignees', 'performance_rating')
        && column_exists($pdo, 'task_assignees', 'rated_by')
        && column_exists($pdo, 'task_assignees', 'rated_at')
        && column_exists($pdo, 'task_assignees', 'rating_comment');
}

function update_task_assignee_ratings($pdo, $task_id, $ratings, $rated_by, $comments = []){
    if (!task_assignee_rating_columns_exist($pdo)) {
        return false;
    }

    $sql = "UPDATE task_assignees 
            SET performance_rating = ?, rating_comment = ?, rated_by = ?, rated_at = NOW()
            WHERE task_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);

    foreach ($ratings as $user_id => $rating) {
        $comment = isset($comments[$user_id]) ? $comments[$user_id] : null;
        $stmt->execute([(int)$rating, $comment, (int)$rated_by, (int)$task_id, (int)$user_id]);
    }

    return true;
}

function clear_task_assignee_ratings($pdo, $task_id){
    if (!task_assignee_rating_columns_exist($pdo)) {
        return false;
    }

    $sql = "UPDATE task_assignees
            SET performance_rating = NULL, rating_comment = NULL, rated_by = NULL, rated_at = NULL
            WHERE task_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$task_id]);
    return true;
}

/* ---------------------------------------------
   DUE / OVERDUE
--------------------------------------------- */

function get_all_tasks_due_today($pdo){
    $stmt = $pdo->prepare(
        "SELECT * FROM tasks 
         WHERE due_date = CURRENT_DATE AND status != 'completed'"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_overdue($pdo){
    $stmt = $pdo->prepare(
        "SELECT * FROM tasks 
         WHERE due_date < CURRENT_DATE AND status != 'completed'"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_tasks_NoDeadline($pdo){
    $stmt = $pdo->prepare(
        "SELECT * FROM tasks 
         WHERE due_date IS NULL AND status != 'completed'"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------------------------------------
   STATUS COUNTS
--------------------------------------------- */

/* ---------------------------------------------
   STATUS COUNTS
--------------------------------------------- */

function count_tasks($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
}

function count_tasks_due_today($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date = CURRENT_DATE AND status != 'completed'")->fetchColumn();
}

function count_tasks_overdue($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURRENT_DATE AND status != 'completed'")->fetchColumn();
}

function count_tasks_NoDeadline($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date IS NULL AND status != 'completed'")->fetchColumn();
}

function count_pending_tasks($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='pending'")->fetchColumn();
}

function count_in_progress_tasks($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='in_progress'")->fetchColumn();
}

function count_completed_tasks($pdo){
    return $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn();
}

function count_my_tasks($pdo, $user_id){
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM task_assignees WHERE user_id=?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function count_my_tasks_overdue($pdo, $user_id){
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.due_date < CURRENT_DATE AND t.status != 'completed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function count_my_tasks_NoDeadline($pdo, $user_id){
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.due_date IS NULL AND t.status != 'completed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function count_my_pending_tasks($pdo, $user_id){
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status = 'pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function count_my_in_progress_tasks($pdo, $user_id){
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status = 'in_progress'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function count_my_completed_tasks($pdo, $user_id){
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status = 'completed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function count_my_active_tasks($pdo, $user_id){
    $sql = "SELECT COUNT(*) FROM tasks t
            JOIN task_assignees ta ON t.id = ta.task_id
            WHERE ta.user_id=? AND t.status != 'completed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/* ---------------------------------------------
   USER TASKS (task_assignees)
--------------------------------------------- */

function get_all_tasks_by_user($pdo, $user_id){
    $stmt = $pdo->prepare(
        "SELECT DISTINCT t.* FROM tasks t
         JOIN task_assignees ta ON t.id = ta.task_id
         WHERE ta.user_id=?
         ORDER BY t.id DESC"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_employee_task_progress($pdo, $user_id){
    $total = $pdo->prepare(
        "SELECT COUNT(DISTINCT task_id) FROM task_assignees WHERE user_id=?"
    );
    $total->execute([$user_id]);

    $completed = $pdo->prepare(
        "SELECT COUNT(DISTINCT ta.task_id)
         FROM task_assignees ta
         JOIN tasks t ON t.id = ta.task_id
         WHERE ta.user_id=? AND t.status='completed'"
    );
    $completed->execute([$user_id]);

    $total = (int)$total->fetchColumn();
    $completed = (int)$completed->fetchColumn();

    return [
        'total' => $total,
        'completed' => $completed,
        'percentage' => $total ? round(($completed/$total)*100) : 0
    ];
}
