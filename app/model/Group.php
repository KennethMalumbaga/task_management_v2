<?php

function group_column_exists($pdo, $table, $column)
{
    $sql = "SELECT 1 FROM information_schema.columns 
            WHERE table_name = ? AND column_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function create_group($pdo, $name, $leader_id, $member_ids = [], $created_by = null, $type = 'group', $task_id = null)
{
    if ($created_by === null) {
        $created_by = $leader_id;
    }

    $has_task_id = group_column_exists($pdo, 'groups', 'task_id');
    if ($has_task_id) {
        $stmt = $pdo->prepare("INSERT INTO groups (name, created_by, type, task_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $created_by, $type, $task_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO groups (name, created_by, type) VALUES (?, ?, ?)");
        $stmt->execute([$name, $created_by, $type]);
    }
    $group_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'leader')");
    $stmt->execute([$group_id, $leader_id]);

    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
    $unique_members = [];
    foreach ($member_ids as $id) {
        $id = (int)$id;
        if ($id <= 0 || $id === (int)$leader_id) {
            continue;
        }
        $unique_members[$id] = true;
    }
    foreach (array_keys($unique_members) as $id) {
        $stmt->execute([$group_id, $id]);
    }

    return $group_id;
}

function get_all_groups($pdo)
{
    $stmt = $pdo->query("SELECT * FROM groups WHERE type = 'group' ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_by_id($pdo, $group_id)
{
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_groups_for_user($pdo, $user_id)
{
    $sql = "SELECT g.*
            FROM groups g
            JOIN group_members gm ON gm.group_id = g.id
            WHERE gm.user_id = ?
            ORDER BY g.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_members($pdo, $group_id)
{
    $sql = "SELECT gm.user_id, gm.role, u.full_name, u.profile_image, u.role AS user_role
            FROM group_members gm
            JOIN users u ON u.id = gm.user_id
            WHERE gm.group_id = ?
            ORDER BY gm.role DESC, u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$group_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_leader_id($pdo, $group_id)
{
    $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND role = 'leader' LIMIT 1");
    $stmt->execute([$group_id]);
    return $stmt->fetchColumn();
}

function is_user_in_group($pdo, $group_id, $user_id)
{
    $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    return (bool)$stmt->fetchColumn();
}

function delete_group($pdo, $group_id)
{
    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    return $stmt->execute([$group_id]);
}

function delete_task_chat_groups_by_title($pdo, $task_title)
{
    $stmt = $pdo->prepare("DELETE FROM groups WHERE name = ? AND type = 'task_chat'");
    return $stmt->execute([$task_title]);
}

function delete_task_chat_groups_by_task_id($pdo, $task_id)
{
    if (!group_column_exists($pdo, 'groups', 'task_id')) {
        return null;
    }
    $stmt = $pdo->prepare("DELETE FROM groups WHERE task_id = ? AND type = 'task_chat'");
    $stmt->execute([$task_id]);
    return $stmt->rowCount() > 0;
}

function sync_task_chat_group_link_and_name($pdo, $task_id, $old_title, $new_title)
{
    $has_task_id = group_column_exists($pdo, 'groups', 'task_id');

    if ($has_task_id) {
        $stmt = $pdo->prepare(
            "UPDATE groups
             SET name = ?, task_id = ?
             WHERE type = 'task_chat' AND task_id = ?"
        );
        $stmt->execute([$new_title, $task_id, $task_id]);
        $updated = $stmt->rowCount();

        // Backfill legacy rows that were created before task_id existed.
        if ($updated === 0 && !empty($old_title)) {
            $stmt = $pdo->prepare(
                "UPDATE groups
                 SET name = ?, task_id = ?
                 WHERE type = 'task_chat' AND name = ?"
            );
            $stmt->execute([$new_title, $task_id, $old_title]);
            $updated = $stmt->rowCount();
        }

        return $updated > 0;
    }

    // Legacy DB fallback when task_id column does not exist
    if (!empty($old_title) && $old_title !== $new_title) {
        $stmt = $pdo->prepare("UPDATE groups SET name = ? WHERE type = 'task_chat' AND name = ?");
        $stmt->execute([$new_title, $old_title]);
        return $stmt->rowCount() > 0;
    }

    return false;
}

function delete_orphan_task_chat_groups($pdo)
{
    if (group_column_exists($pdo, 'groups', 'task_id')) {
        $sql = "DELETE FROM groups g
                WHERE g.type = 'task_chat'
                  AND (
                        (g.task_id IS NOT NULL AND NOT EXISTS (
                            SELECT 1 FROM tasks t WHERE t.id = g.task_id
                        ))
                        OR
                        (g.task_id IS NULL AND NOT EXISTS (
                            SELECT 1 FROM tasks t WHERE t.title = g.name
                        ))
                      )";
        return $pdo->exec($sql);
    }

    $sql = "DELETE FROM groups g
            WHERE g.type = 'task_chat'
              AND NOT EXISTS (
                    SELECT 1 FROM tasks t WHERE t.title = g.name
              )";
    return $pdo->exec($sql);
}

function delete_legacy_duplicate_group_chats_by_title($pdo, $title)
{
    if (trim((string)$title) === '') {
        return 0;
    }

    $has_task_id = group_column_exists($pdo, 'groups', 'task_id');
    $taskIdClause = $has_task_id ? "AND g.task_id IS NULL" : "";
    $taskIdClause2 = $has_task_id ? "AND g2.task_id IS NULL" : "";

    $sql = "DELETE FROM groups g
            WHERE g.type = 'group'
              {$taskIdClause}
              AND LOWER(g.name) = LOWER(?)
              AND NOT EXISTS (
                    SELECT 1 FROM tasks t WHERE LOWER(t.title) = LOWER(?)
              )
              AND (
                    SELECT COUNT(*)
                    FROM groups g2
                    WHERE g2.type = 'group'
                      {$taskIdClause2}
                      AND LOWER(g2.name) = LOWER(?)
              ) > 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $title, $title]);
    return $stmt->rowCount();
}

function check_group_exists($pdo, $name)
{
    $stmt = $pdo->prepare(
        "SELECT 1 
         FROM groups 
         WHERE type = 'group' AND LOWER(TRIM(name)) = LOWER(TRIM(?))
         LIMIT 1"
    );
    $stmt->execute([$name]);
    return (bool)$stmt->fetchColumn();
}

function get_top_rated_groups($pdo, $limit = 5)
{
    $sql = "SELECT g.id, g.name as group_name,
                   COUNT(DISTINCT gm.user_id) as member_count,
                   ROUND(AVG(t.rating)::numeric, 1) as avg_rating,
                   COUNT(DISTINCT t.id) as rated_task_count
            FROM groups g
            JOIN group_members gm ON gm.group_id = g.id
            JOIN task_assignees ta ON ta.user_id = gm.user_id
            JOIN tasks t ON t.id = ta.task_id
            WHERE g.type = 'group'
              AND t.status = 'completed'
              AND t.rating > 0
            GROUP BY g.id, g.name
            HAVING COUNT(DISTINCT t.id) > 0
            ORDER BY avg_rating DESC, rated_task_count DESC
            LIMIT ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
