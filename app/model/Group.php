<?php

require_once __DIR__ . '/../../inc/tenant.php';

function group_column_exists($pdo, $table, $column)
{
    $sql = "SELECT 1 FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function group_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

function create_group($pdo, $name, $leader_id, $member_ids = [], $created_by = null, $type = 'group', $task_id = null)
{
    if ($created_by === null) {
        $created_by = $leader_id;
    }

    $orgId = tenant_get_current_org_id();
    $hasOrgOnGroups = group_column_exists($pdo, 'groups', 'organization_id') && $orgId;
    $hasOrgOnMembers = group_column_exists($pdo, 'group_members', 'organization_id') && $orgId;
    $has_task_id = group_column_exists($pdo, 'groups', 'task_id');

    if ($has_task_id) {
        if ($hasOrgOnGroups) {
            $stmt = $pdo->prepare(
                "INSERT INTO `groups` (name, created_by, type, task_id, organization_id) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $created_by, $type, $task_id, $orgId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `groups` (name, created_by, type, task_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $created_by, $type, $task_id]);
        }
    } else {
        if ($hasOrgOnGroups) {
            $stmt = $pdo->prepare(
                "INSERT INTO `groups` (name, created_by, type, organization_id) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $created_by, $type, $orgId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `groups` (name, created_by, type) VALUES (?, ?, ?)");
            $stmt->execute([$name, $created_by, $type]);
        }
    }
    $group_id = $pdo->lastInsertId();

    if ($hasOrgOnMembers) {
        $stmt = $pdo->prepare(
            "INSERT INTO group_members (group_id, user_id, role, organization_id) VALUES (?, ?, 'leader', ?)"
        );
        $stmt->execute([$group_id, $leader_id, $orgId]);

        $stmt = $pdo->prepare(
            "INSERT INTO group_members (group_id, user_id, role, organization_id) VALUES (?, ?, 'member', ?)"
        );
    } else {
        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'leader')");
        $stmt->execute([$group_id, $leader_id]);

        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
    }

    $unique_members = [];
    foreach ($member_ids as $id) {
        $id = (int)$id;
        if ($id <= 0 || $id === (int)$leader_id) {
            continue;
        }
        $unique_members[$id] = true;
    }
    foreach (array_keys($unique_members) as $id) {
        if ($hasOrgOnMembers) {
            $stmt->execute([$group_id, $id, $orgId]);
        } else {
            $stmt->execute([$group_id, $id]);
        }
    }

    return $group_id;
}

function get_all_groups($pdo)
{
    [$sql, $params] = group_append_scope(
        $pdo,
        "SELECT * FROM `groups` WHERE type = 'group'",
        [],
        'groups'
    );
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_by_id($pdo, $group_id)
{
    $sql = "SELECT * FROM `groups` WHERE id = ?";
    [$sql, $params] = group_append_scope($pdo, $sql, [$group_id], 'groups');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_groups_for_user($pdo, $user_id)
{
    $sql = "SELECT g.*
            FROM `groups` g
            JOIN group_members gm ON gm.group_id = g.id
            WHERE gm.user_id = ?";
    $params = [$user_id];
    $scope = tenant_get_scope($pdo, 'groups', 'g');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);
    $scope = tenant_get_scope($pdo, 'group_members', 'gm');
    $sql .= $scope['sql'] . " ORDER BY g.name ASC";
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_members($pdo, $group_id)
{
    $sql = "SELECT gm.user_id, gm.role, u.full_name, u.profile_image, u.role AS user_role
            FROM group_members gm
            JOIN users u ON u.id = gm.user_id
            WHERE gm.group_id = ?";
    [$sql, $params] = group_append_scope($pdo, $sql, [$group_id], 'group_members', 'gm');
    $sql .= " ORDER BY gm.role DESC, u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_leader_id($pdo, $group_id)
{
    $sql = "SELECT user_id FROM group_members WHERE group_id = ? AND role = 'leader'";
    [$sql, $params] = group_append_scope($pdo, $sql, [$group_id], 'group_members');
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function is_user_in_group($pdo, $group_id, $user_id)
{
    $sql = "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?";
    [$sql, $params] = group_append_scope($pdo, $sql, [$group_id, $user_id], 'group_members');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function delete_group($pdo, $group_id)
{
    $sql = "DELETE FROM `groups` WHERE id = ?";
    [$sql, $params] = group_append_scope($pdo, $sql, [$group_id], 'groups');
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function delete_task_chat_groups_by_title($pdo, $task_title)
{
    $sql = "DELETE FROM `groups` WHERE name = ? AND type = 'task_chat'";
    [$sql, $params] = group_append_scope($pdo, $sql, [$task_title], 'groups');
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function delete_task_chat_groups_by_task_id($pdo, $task_id)
{
    if (!group_column_exists($pdo, 'groups', 'task_id')) {
        return null;
    }

    $sql = "DELETE FROM `groups` WHERE task_id = ? AND type = 'task_chat'";
    [$sql, $params] = group_append_scope($pdo, $sql, [$task_id], 'groups');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function sync_task_chat_group_link_and_name($pdo, $task_id, $old_title, $new_title)
{
    $has_task_id = group_column_exists($pdo, 'groups', 'task_id');

    if ($has_task_id) {
        $sql = "UPDATE `groups`
                SET name = ?, task_id = ?
                WHERE type = 'task_chat' AND task_id = ?";
        [$sql, $params] = group_append_scope($pdo, $sql, [$new_title, $task_id, $task_id], 'groups');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $updated = $stmt->rowCount();

        if ($updated === 0 && !empty($old_title)) {
            $sql = "UPDATE `groups`
                    SET name = ?, task_id = ?
                    WHERE type = 'task_chat' AND name = ?";
            [$sql, $params] = group_append_scope($pdo, $sql, [$new_title, $task_id, $old_title], 'groups');
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $updated = $stmt->rowCount();
        }

        return $updated > 0;
    }

    if (!empty($old_title) && $old_title !== $new_title) {
        $sql = "UPDATE `groups` SET name = ? WHERE type = 'task_chat' AND name = ?";
        [$sql, $params] = group_append_scope($pdo, $sql, [$new_title, $old_title], 'groups');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    return false;
}

function delete_orphan_task_chat_groups($pdo)
{
    if (group_column_exists($pdo, 'groups', 'task_id')) {
        $sql = "DELETE FROM `groups`
                WHERE type = 'task_chat'
                  AND (
                        (task_id IS NOT NULL AND NOT EXISTS (
                            SELECT 1 FROM tasks t WHERE t.id = `groups`.task_id
                        ))
                        OR
                        (task_id IS NULL AND NOT EXISTS (
                            SELECT 1 FROM tasks t WHERE t.title = `groups`.name
                        ))
                      )";
        [$sql, $params] = group_append_scope($pdo, $sql, [], 'groups');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    $sql = "DELETE FROM `groups`
            WHERE type = 'task_chat'
              AND NOT EXISTS (
                    SELECT 1 FROM tasks t WHERE t.title = `groups`.name
              )";
    [$sql, $params] = group_append_scope($pdo, $sql, [], 'groups');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function delete_legacy_duplicate_group_chats_by_title($pdo, $title)
{
    if (trim((string)$title) === '') {
        return 0;
    }

    $has_task_id = group_column_exists($pdo, 'groups', 'task_id');
    $taskIdClause = $has_task_id ? "AND `groups`.task_id IS NULL" : "";
    $taskIdClause2 = $has_task_id ? "AND g2.task_id IS NULL" : "";

    $sql = "DELETE FROM `groups`
            WHERE type = 'group'
              {$taskIdClause}
              AND LOWER(`groups`.name) = LOWER(?)
              AND NOT EXISTS (
                    SELECT 1 FROM tasks t WHERE LOWER(t.title) = LOWER(?)
              )
              AND (
                    SELECT COUNT(*)
                    FROM `groups` g2
                    WHERE g2.type = 'group'
                      {$taskIdClause2}
                      AND LOWER(g2.name) = LOWER(?)
              ) > 1";
    $params = [$title, $title, $title];
    $scope = tenant_get_scope($pdo, 'groups');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function check_group_exists($pdo, $name)
{
    $sql = "SELECT 1
            FROM `groups`
            WHERE type = 'group' AND LOWER(TRIM(name)) = LOWER(TRIM(?))";
    [$sql, $params] = group_append_scope($pdo, $sql, [$name], 'groups');
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function get_top_rated_groups($pdo, $limit = 5)
{
    $limit = max(1, (int)$limit);

    $sql = "SELECT g.id, g.name as group_name,
                   COUNT(DISTINCT gm.user_id) as member_count,
                   ROUND(AVG(t.rating), 1) as avg_rating,
                   COUNT(DISTINCT t.id) as rated_task_count
            FROM `groups` g
            JOIN group_members gm ON gm.group_id = g.id
            JOIN task_assignees ta ON ta.user_id = gm.user_id
            JOIN tasks t ON t.id = ta.task_id
            WHERE g.type = 'group'
              AND t.status = 'completed'
              AND t.rating > 0";
    $params = [];
    $scope = tenant_get_scope($pdo, 'groups', 'g');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);
    $scope = tenant_get_scope($pdo, 'group_members', 'gm');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);
    $scope = tenant_get_scope($pdo, 'task_assignees', 'ta');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);
    $scope = tenant_get_scope($pdo, 'tasks', 't');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    $sql .= " GROUP BY g.id, g.name
              HAVING COUNT(DISTINCT t.id) > 0
              ORDER BY avg_rating DESC, rated_task_count DESC
              LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $idx => $value) {
        $paramIndex = $idx + 1;
        if ($paramIndex === count($params)) {
            $stmt->bindValue($paramIndex, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($paramIndex, $value);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

