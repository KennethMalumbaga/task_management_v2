<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('timeline_column_exists')) {
    function timeline_column_exists($pdo, $table, $column)
    {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('timeline_append_scope')) {
    function timeline_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
    {
        $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
        return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
    }
}

if (!function_exists('timeline_ensure_schema')) {
    function timeline_ensure_schema($pdo)
    {
        static $alreadyEnsured = false;
        if ($alreadyEnsured) {
            return;
        }

        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'mysql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS project_timeline_tasks (
                    id INT NOT NULL AUTO_INCREMENT,
                    project_id INT NOT NULL,
                    title VARCHAR(150) NOT NULL,
                    assignee_user_id INT DEFAULT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_by INT DEFAULT NULL,
                    organization_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT project_timeline_tasks_pkey PRIMARY KEY (id),
                    INDEX idx_project_timeline_tasks_project (project_id),
                    INDEX idx_project_timeline_tasks_org_project (organization_id, project_id),
                    CONSTRAINT fk_project_timeline_tasks_project FOREIGN KEY (project_id) REFERENCES tasks(id) ON DELETE CASCADE,
                    CONSTRAINT fk_project_timeline_tasks_assignee FOREIGN KEY (assignee_user_id) REFERENCES users(id) ON DELETE SET NULL,
                    CONSTRAINT fk_project_timeline_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS project_timeline_phases (
                    id INT NOT NULL AUTO_INCREMENT,
                    timeline_task_id INT NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    description TEXT DEFAULT NULL,
                    icon VARCHAR(40) NOT NULL DEFAULT 'fa-circle',
                    color VARCHAR(7) NOT NULL DEFAULT '#6C3CE1',
                    start_day INT NOT NULL DEFAULT 1,
                    duration_days INT NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_by INT DEFAULT NULL,
                    organization_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT project_timeline_phases_pkey PRIMARY KEY (id),
                    INDEX idx_project_timeline_phases_task (timeline_task_id),
                    INDEX idx_project_timeline_phases_org_task (organization_id, timeline_task_id),
                    CONSTRAINT fk_project_timeline_phases_task FOREIGN KEY (timeline_task_id) REFERENCES project_timeline_tasks(id) ON DELETE CASCADE,
                    CONSTRAINT fk_project_timeline_phases_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS project_timeline_tasks (
                    id SERIAL PRIMARY KEY,
                    project_id INT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
                    title VARCHAR(150) NOT NULL,
                    assignee_user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
                    organization_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            );

            $pdo->exec(
                "CREATE INDEX IF NOT EXISTS idx_project_timeline_tasks_project
                 ON project_timeline_tasks (project_id)"
            );
            $pdo->exec(
                "CREATE INDEX IF NOT EXISTS idx_project_timeline_tasks_org_project
                 ON project_timeline_tasks (organization_id, project_id)"
            );

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS project_timeline_phases (
                    id SERIAL PRIMARY KEY,
                    timeline_task_id INT NOT NULL REFERENCES project_timeline_tasks(id) ON DELETE CASCADE,
                    name VARCHAR(150) NOT NULL,
                    description TEXT NULL,
                    icon VARCHAR(40) NOT NULL DEFAULT 'fa-circle',
                    color VARCHAR(7) NOT NULL DEFAULT '#6C3CE1',
                    start_day INT NOT NULL DEFAULT 1,
                    duration_days INT NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
                    organization_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $pdo->exec(
                "CREATE INDEX IF NOT EXISTS idx_project_timeline_phases_task
                 ON project_timeline_phases (timeline_task_id)"
            );
            $pdo->exec(
                "CREATE INDEX IF NOT EXISTS idx_project_timeline_phases_org_task
                 ON project_timeline_phases (organization_id, timeline_task_id)"
            );
        }

        $alreadyEnsured = true;
    }
}

if (!function_exists('timeline_status_from_task_status')) {
    function timeline_status_from_task_status($taskStatus)
    {
        $normalized = strtolower(trim((string)$taskStatus));
        if ($normalized === 'completed') {
            return 'completed';
        }
        if ($normalized === 'pending') {
            return 'planning';
        }
        return 'active';
    }
}

if (!function_exists('timeline_default_progress')) {
    function timeline_default_progress($taskStatus)
    {
        $normalized = strtolower(trim((string)$taskStatus));
        if ($normalized === 'completed') {
            return 100;
        }
        if ($normalized === 'pending') {
            return 12;
        }
        if ($normalized === 'rejected') {
            return 35;
        }
        if ($normalized === 'revise') {
            return 64;
        }
        return 52;
    }
}

if (!function_exists('timeline_sanitize_icon_class')) {
    function timeline_sanitize_icon_class($iconClass)
    {
        $iconClass = trim((string)$iconClass);
        if ($iconClass === '') {
            return 'fa-circle';
        }
        if (!preg_match('/^[a-z0-9\\-\\s]{2,40}$/i', $iconClass)) {
            return 'fa-circle';
        }
        return $iconClass;
    }
}

if (!function_exists('timeline_sanitize_color_hex')) {
    function timeline_sanitize_color_hex($color)
    {
        $color = trim((string)$color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtoupper($color);
        }
        return '#6C3CE1';
    }
}

if (!function_exists('timeline_deadline_days_from_values')) {
    function timeline_deadline_days_from_values($createdAt, $dueDate)
    {
        $createdAt = trim((string)$createdAt);
        $dueDate = trim((string)$dueDate);
        if ($createdAt === '' || $dueDate === '') {
            return 0;
        }

        try {
            $startDate = new DateTime((new DateTime($createdAt))->format('Y-m-d'));
            $endDate = new DateTime((new DateTime($dueDate))->format('Y-m-d'));
        } catch (Throwable $e) {
            return 0;
        }

        if ($endDate < $startDate) {
            return 1;
        }

        $span = ((int)$startDate->diff($endDate)->days) + 1;
        return max(1, $span);
    }
}

if (!function_exists('timeline_deadline_days_from_project_row')) {
    function timeline_deadline_days_from_project_row($projectRow)
    {
        return timeline_deadline_days_from_values(
            (string)($projectRow['created_at'] ?? ''),
            (string)($projectRow['due_date'] ?? '')
        );
    }
}

if (!function_exists('timeline_get_project_deadline_days')) {
    function timeline_get_project_deadline_days($pdo, $projectId)
    {
        $projectId = (int)$projectId;
        if ($projectId <= 0) {
            return 0;
        }

        $sql = "SELECT created_at, due_date FROM tasks WHERE id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$projectId], 'tasks');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0;
        }

        return timeline_deadline_days_from_project_row($row);
    }
}

if (!function_exists('timeline_fetch_project_members')) {
    function timeline_fetch_project_members($pdo, $projectId)
    {
        $sql = "SELECT ta.user_id, ta.role, u.full_name
                FROM task_assignees ta
                JOIN users u ON u.id = ta.user_id
                WHERE ta.task_id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$projectId], 'task_assignees', 'ta');
        $userScope = tenant_get_scope($pdo, 'users', 'u');
        $sql .= $userScope['sql'] . " ORDER BY ta.role DESC, u.full_name ASC";
        $params = array_merge($params, $userScope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('timeline_fetch_project_group_name')) {
    function timeline_fetch_project_group_name($pdo, $leaderId)
    {
        $leaderId = (int)$leaderId;
        if ($leaderId <= 0) {
            return 'Project Team';
        }

        $sql = "SELECT g.name
                FROM groups g
                JOIN group_members gm ON gm.group_id = g.id
                WHERE g.type = 'group' AND gm.user_id = ? AND gm.role = 'leader'";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$leaderId], 'groups', 'g');
        $memberScope = tenant_get_scope($pdo, 'group_members', 'gm');
        $sql .= $memberScope['sql'] . " ORDER BY g.id DESC LIMIT 1";
        $params = array_merge($params, $memberScope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $name = $stmt->fetchColumn();
        if (!$name) {
            return 'Project Team';
        }
        return (string)$name;
    }
}

if (!function_exists('timeline_fetch_timeline_task_rows')) {
    function timeline_fetch_timeline_task_rows($pdo, $projectId)
    {
        $sql = "SELECT tt.id, tt.title, tt.assignee_user_id, tt.sort_order, u.full_name AS assignee_name
                FROM project_timeline_tasks tt
                LEFT JOIN users u ON u.id = tt.assignee_user_id
                WHERE tt.project_id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$projectId], 'project_timeline_tasks', 'tt');
        $userScope = tenant_get_scope($pdo, 'users', 'u');
        if (!empty($userScope['params'])) {
            $sql .= " AND (u.organization_id = ? OR u.id IS NULL)";
            $params = array_merge($params, $userScope['params']);
        }
        $sql .= " ORDER BY tt.sort_order ASC, tt.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$tasks) {
            return [];
        }

        $taskIds = [];
        foreach ($tasks as $taskRow) {
            $taskIds[] = (int)$taskRow['id'];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $sql = "SELECT pp.id, pp.timeline_task_id, pp.name, pp.description, pp.icon, pp.color,
                       pp.start_day, pp.duration_days, pp.sort_order
                FROM project_timeline_phases pp
                WHERE pp.timeline_task_id IN ($placeholders)";
        [$sql, $phaseParams] = timeline_append_scope(
            $pdo,
            $sql,
            $taskIds,
            'project_timeline_phases',
            'pp'
        );
        $sql .= " ORDER BY pp.sort_order ASC, pp.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($phaseParams);
        $phaseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $phaseMap = [];
        foreach ($phaseRows as $phaseRow) {
            $timelineTaskId = (int)$phaseRow['timeline_task_id'];
            if (!isset($phaseMap[$timelineTaskId])) {
                $phaseMap[$timelineTaskId] = [];
            }
            $phaseMap[$timelineTaskId][] = [
                'id' => (int)$phaseRow['id'],
                'name' => (string)$phaseRow['name'],
                'description' => trim((string)($phaseRow['description'] ?? '')),
                'icon' => timeline_sanitize_icon_class($phaseRow['icon'] ?? 'fa-circle'),
                'color' => timeline_sanitize_color_hex($phaseRow['color'] ?? '#6C3CE1'),
                'start_day' => max(1, (int)$phaseRow['start_day']),
                'duration_days' => max(1, (int)$phaseRow['duration_days']),
            ];
        }

        $result = [];
        foreach ($tasks as $taskRow) {
            $taskId = (int)$taskRow['id'];
            $result[] = [
                'id' => $taskId,
                'title' => (string)$taskRow['title'],
                'assignee_user_id' => isset($taskRow['assignee_user_id']) ? (int)$taskRow['assignee_user_id'] : null,
                'assignee_name' => (string)($taskRow['assignee_name'] ?? ''),
                'phases' => $phaseMap[$taskId] ?? [],
            ];
        }

        return $result;
    }
}

if (!function_exists('timeline_project_role_for_user')) {
    function timeline_project_role_for_user($pdo, $projectId, $userId)
    {
        $projectId = (int)$projectId;
        $userId = (int)$userId;
        if ($projectId <= 0 || $userId <= 0) {
            return null;
        }

        $sql = "SELECT role FROM task_assignees WHERE task_id = ? AND user_id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$projectId, $userId], 'task_assignees');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $role = $stmt->fetchColumn();
        return $role ? (string)$role : null;
    }
}

if (!function_exists('timeline_is_project_member')) {
    function timeline_is_project_member($pdo, $projectId, $userId)
    {
        return timeline_project_role_for_user($pdo, $projectId, $userId) !== null;
    }
}

if (!function_exists('timeline_can_modify_project')) {
    function timeline_can_modify_project($pdo, $projectId, $sessionRole, $userId)
    {
        return timeline_project_role_for_user($pdo, $projectId, $userId) === 'leader';
    }
}

if (!function_exists('timeline_fetch_project_rows')) {
    function timeline_fetch_project_rows($pdo, $sessionRole, $userId, $projectId = null)
    {
        $projectId = $projectId !== null ? (int)$projectId : 0;
        if ((string)$sessionRole === 'admin') {
            $sql = "SELECT t.id, t.title, t.description, t.status, t.created_at, t.due_date
                    FROM tasks t
                    WHERE 1=1";
            $params = [];
            if ($projectId > 0) {
                $sql .= " AND t.id = ?";
                $params[] = $projectId;
            }
            [$sql, $params] = timeline_append_scope($pdo, $sql, $params, 'tasks', 't');
            $sql .= " ORDER BY t.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql = "SELECT DISTINCT t.id, t.title, t.description, t.status, t.created_at, t.due_date, ta_self.role AS user_project_role
                FROM tasks t
                JOIN task_assignees ta_self
                  ON ta_self.task_id = t.id
                 AND ta_self.user_id = ?
                WHERE 1=1";
        $params = [(int)$userId];
        if ($projectId > 0) {
            $sql .= " AND t.id = ?";
            $params[] = $projectId;
        }
        [$sql, $params] = timeline_append_scope($pdo, $sql, $params, 'tasks', 't');
        $selfScope = tenant_get_scope($pdo, 'task_assignees', 'ta_self');
        $sql .= $selfScope['sql'] . " ORDER BY t.id DESC";
        $params = array_merge($params, $selfScope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('timeline_estimate_total_days')) {
    function timeline_estimate_total_days($projectRow, $timelineTasks)
    {
        $maxEndDay = 0;
        foreach ($timelineTasks as $taskRow) {
            foreach (($taskRow['phases'] ?? []) as $phaseRow) {
                $start = max(1, (int)($phaseRow['start_day'] ?? 1));
                $duration = max(1, (int)($phaseRow['duration_days'] ?? 1));
                $maxEndDay = max($maxEndDay, $start + $duration - 1);
            }
        }

        $totalDays = max(20, $maxEndDay);
        $deadlineDays = timeline_deadline_days_from_project_row($projectRow);
        if ($deadlineDays > 0) {
            $totalDays = max($totalDays, $deadlineDays);
        }

        return max(14, min(90, (int)$totalDays));
    }
}

if (!function_exists('timeline_estimate_progress')) {
    function timeline_estimate_progress($taskStatus, $timelineTasks, $todayDay)
    {
        $todayDay = max(1, (int)$todayDay);
        $normalizedStatus = strtolower(trim((string)$taskStatus));
        if ($normalizedStatus === 'completed') {
            return 100;
        }

        $totalPhaseDays = 0;
        $elapsedPhaseDays = 0;

        foreach ($timelineTasks as $taskRow) {
            foreach (($taskRow['phases'] ?? []) as $phaseRow) {
                $start = max(1, (int)($phaseRow['start_day'] ?? 1));
                $duration = max(1, (int)($phaseRow['duration_days'] ?? 1));
                $totalPhaseDays += $duration;

                $elapsed = $todayDay - $start + 1;
                if ($elapsed < 0) {
                    $elapsed = 0;
                }
                if ($elapsed > $duration) {
                    $elapsed = $duration;
                }
                $elapsedPhaseDays += $elapsed;
            }
        }

        if ($totalPhaseDays > 0) {
            $pct = (int)round(($elapsedPhaseDays / $totalPhaseDays) * 100);
            return max(0, min(99, $pct));
        }

        return timeline_default_progress($taskStatus);
    }
}

if (!function_exists('timeline_build_project_payload')) {
    function timeline_build_project_payload($pdo, $projectRow, $sessionRole, $userId, $todayDay)
    {
        $projectId = (int)$projectRow['id'];
        $membersRaw = timeline_fetch_project_members($pdo, $projectId);
        $members = [];
        $leaderId = 0;
        $leaderName = '';

        foreach ($membersRaw as $memberRow) {
            $memberRole = (string)($memberRow['role'] ?? 'member');
            $memberId = (int)($memberRow['user_id'] ?? 0);
            $memberName = (string)($memberRow['full_name'] ?? 'User');
            if ($memberRole === 'leader' && $leaderId <= 0) {
                $leaderId = $memberId;
                $leaderName = $memberName;
            }
            $members[] = [
                'id' => $memberId,
                'name' => $memberName,
                'role' => $memberRole,
            ];
        }

        $timelineTasks = timeline_fetch_timeline_task_rows($pdo, $projectId);
        $deadlineDays = timeline_deadline_days_from_project_row($projectRow);
        $totalDays = timeline_estimate_total_days($projectRow, $timelineTasks);
        $progress = timeline_estimate_progress($projectRow['status'] ?? '', $timelineTasks, $todayDay);
        $status = timeline_status_from_task_status($projectRow['status'] ?? 'pending');
        $userProjectRole = (string)(
            ($sessionRole === 'admin')
                ? 'admin'
                : ($projectRow['user_project_role'] ?? timeline_project_role_for_user($pdo, $projectId, (int)$userId) ?? 'member')
        );

        return [
            'id' => $projectId,
            'name' => (string)$projectRow['title'],
            'description' => trim((string)($projectRow['description'] ?? '')),
            'group' => timeline_fetch_project_group_name($pdo, $leaderId),
            'status' => $status,
            'progress' => max(0, min(100, (int)$progress)),
            'total_days' => $totalDays,
            'deadline_days' => $deadlineDays > 0 ? $deadlineDays : null,
            'leader' => [
                'id' => $leaderId,
                'name' => $leaderName !== '' ? $leaderName : 'Unassigned Leader',
            ],
            'members' => $members,
            'tasks' => $timelineTasks,
            'permissions' => [
                'can_edit' => timeline_can_modify_project($pdo, $projectId, $sessionRole, $userId),
                'user_project_role' => $userProjectRole,
            ],
        ];
    }
}

if (!function_exists('timeline_get_projects_payload')) {
    function timeline_get_projects_payload($pdo, $sessionRole, $userId, $projectId = null)
    {
        timeline_ensure_schema($pdo);

        $todayDay = (int)date('j');
        $projectRows = timeline_fetch_project_rows($pdo, $sessionRole, $userId, $projectId);
        $projects = [];
        foreach ($projectRows as $projectRow) {
            $projects[] = timeline_build_project_payload(
                $pdo,
                $projectRow,
                $sessionRole,
                (int)$userId,
                $todayDay
            );
        }

        return [
            'today_day' => $todayDay,
            'projects' => $projects,
        ];
    }
}

if (!function_exists('timeline_get_timeline_task_by_id')) {
    function timeline_get_timeline_task_by_id($pdo, $timelineTaskId)
    {
        $sql = "SELECT * FROM project_timeline_tasks WHERE id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [(int)$timelineTaskId], 'project_timeline_tasks');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('timeline_get_phase_by_id')) {
    function timeline_get_phase_by_id($pdo, $phaseId)
    {
        $sql = "SELECT * FROM project_timeline_phases WHERE id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [(int)$phaseId], 'project_timeline_phases');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('timeline_validate_assignee_for_project')) {
    function timeline_validate_assignee_for_project($pdo, $projectId, $assigneeUserId)
    {
        $assigneeUserId = (int)$assigneeUserId;
        if ($assigneeUserId <= 0) {
            return null;
        }
        return timeline_is_project_member($pdo, (int)$projectId, $assigneeUserId) ? $assigneeUserId : null;
    }
}

if (!function_exists('timeline_create_task_lane')) {
    function timeline_create_task_lane($pdo, $projectId, $title, $assigneeUserId, $actorUserId)
    {
        $projectId = (int)$projectId;
        $actorUserId = (int)$actorUserId;
        $title = trim((string)$title);
        if ($projectId <= 0 || $actorUserId <= 0 || $title === '') {
            return null;
        }

        $assigneeUserId = timeline_validate_assignee_for_project($pdo, $projectId, (int)$assigneeUserId);

        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 1
                FROM project_timeline_tasks
                WHERE project_id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$projectId], 'project_timeline_tasks');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $nextOrder = (int)$stmt->fetchColumn();
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        $orgId = tenant_get_current_org_id();
        $stmt = $pdo->prepare(
            "INSERT INTO project_timeline_tasks
             (project_id, title, assignee_user_id, sort_order, created_by, organization_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $projectId,
            substr($title, 0, 150),
            $assigneeUserId,
            $nextOrder,
            $actorUserId,
            $orgId ? (int)$orgId : null,
        ]);

        return timeline_get_timeline_task_by_id($pdo, (int)$pdo->lastInsertId());
    }
}

if (!function_exists('timeline_save_task_lane')) {
    function timeline_save_task_lane($pdo, $projectId, $taskId, $title, $assigneeUserId, $actorUserId)
    {
        $projectId = (int)$projectId;
        $taskId = (int)$taskId;
        $actorUserId = (int)$actorUserId;
        $title = trim((string)$title);

        if ($actorUserId <= 0 || $title === '') {
            return null;
        }

        if ($taskId > 0) {
            $existing = timeline_get_timeline_task_by_id($pdo, $taskId);
            if (!$existing) {
                return null;
            }

            $existingProjectId = (int)($existing['project_id'] ?? 0);
            if ($projectId <= 0) {
                $projectId = $existingProjectId;
            } elseif ($existingProjectId !== $projectId) {
                return null;
            }

            if ($projectId <= 0) {
                return null;
            }

            $assigneeUserId = timeline_validate_assignee_for_project($pdo, $projectId, (int)$assigneeUserId);
            $sql = "UPDATE project_timeline_tasks
                    SET title = ?, assignee_user_id = ?
                    WHERE id = ?";
            [$sql, $params] = timeline_append_scope(
                $pdo,
                $sql,
                [substr($title, 0, 150), $assigneeUserId, $taskId],
                'project_timeline_tasks'
            );
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return timeline_get_timeline_task_by_id($pdo, $taskId);
        }

        if ($projectId <= 0) {
            return null;
        }

        return timeline_create_task_lane($pdo, $projectId, $title, $assigneeUserId, $actorUserId);
    }
}

if (!function_exists('timeline_delete_task_lane')) {
    function timeline_delete_task_lane($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return false;
        }

        $sql = "DELETE FROM project_timeline_tasks WHERE id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$taskId], 'project_timeline_tasks');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('timeline_save_phase')) {
    function timeline_save_phase($pdo, $timelineTaskId, $phaseId, $name, $description, $icon, $color, $startDay, $durationDays, $actorUserId)
    {
        $timelineTaskId = (int)$timelineTaskId;
        $phaseId = (int)$phaseId;
        $actorUserId = (int)$actorUserId;
        $name = trim((string)$name);
        $description = trim((string)$description);
        $icon = timeline_sanitize_icon_class($icon);
        $color = timeline_sanitize_color_hex($color);
        $startDay = max(1, min(365, (int)$startDay));
        $durationDays = max(1, min(180, (int)$durationDays));

        if ($timelineTaskId <= 0 || $actorUserId <= 0 || $name === '') {
            return null;
        }

        $taskRow = timeline_get_timeline_task_by_id($pdo, $timelineTaskId);
        if (!$taskRow) {
            return null;
        }

        $projectId = (int)($taskRow['project_id'] ?? 0);
        $deadlineDays = timeline_get_project_deadline_days($pdo, $projectId);
        if ($deadlineDays > 0) {
            if ($startDay > $deadlineDays) {
                $startDay = $deadlineDays;
            }
            $maxDuration = max(1, ($deadlineDays - $startDay) + 1);
            if ($durationDays > $maxDuration) {
                $durationDays = $maxDuration;
            }
        }

        $orgId = tenant_get_current_org_id();
        if ($phaseId > 0) {
            $existingPhase = timeline_get_phase_by_id($pdo, $phaseId);
            if (!$existingPhase || (int)$existingPhase['timeline_task_id'] !== $timelineTaskId) {
                return null;
            }

            $sql = "UPDATE project_timeline_phases
                    SET name = ?, description = ?, icon = ?, color = ?, start_day = ?, duration_days = ?
                    WHERE id = ?";
            [$sql, $params] = timeline_append_scope(
                $pdo,
                $sql,
                [substr($name, 0, 150), $description, $icon, $color, $startDay, $durationDays, $phaseId],
                'project_timeline_phases'
            );
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return timeline_get_phase_by_id($pdo, $phaseId);
        }

        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 1
                FROM project_timeline_phases
                WHERE timeline_task_id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$timelineTaskId], 'project_timeline_phases');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $nextOrder = (int)$stmt->fetchColumn();
        if ($nextOrder <= 0) {
            $nextOrder = 1;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO project_timeline_phases
             (timeline_task_id, name, description, icon, color, start_day, duration_days, sort_order, created_by, organization_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $timelineTaskId,
            substr($name, 0, 150),
            $description,
            $icon,
            $color,
            $startDay,
            $durationDays,
            $nextOrder,
            $actorUserId,
            $orgId ? (int)$orgId : null,
        ]);

        return timeline_get_phase_by_id($pdo, (int)$pdo->lastInsertId());
    }
}

if (!function_exists('timeline_delete_phase')) {
    function timeline_delete_phase($pdo, $phaseId)
    {
        $phaseId = (int)$phaseId;
        if ($phaseId <= 0) {
            return false;
        }
        $sql = "DELETE FROM project_timeline_phases WHERE id = ?";
        [$sql, $params] = timeline_append_scope($pdo, $sql, [$phaseId], 'project_timeline_phases');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
