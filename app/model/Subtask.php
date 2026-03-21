<?php

require_once __DIR__ . '/../../inc/tenant.php';
require_once __DIR__ . '/../helpers/rating.php';

function subtask_append_scope($pdo, $sql, $params, $table, $alias = '', $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, $table, $alias, $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

if (!function_exists('subtask_get_task_org_id')) {
    function subtask_get_task_org_id($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0 || !tenant_table_exists($pdo, 'tasks')) {
            return null;
        }

        if (!tenant_column_exists($pdo, 'tasks', 'organization_id')) {
            return null;
        }

        $sql = "SELECT organization_id FROM tasks WHERE id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$taskId], 'tasks');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orgId = (int)$stmt->fetchColumn();
        return $orgId > 0 ? $orgId : null;
    }
}

if (!function_exists('subtask_repair_org_scope_for_task')) {
    function subtask_repair_org_scope_for_task($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0 || !tenant_column_exists($pdo, 'subtasks', 'organization_id')) {
            return;
        }

        $targetOrgId = subtask_get_task_org_id($pdo, $taskId);
        if (!$targetOrgId) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE subtasks
                 SET organization_id = ?
                 WHERE task_id = ?
                   AND (organization_id IS NULL OR organization_id = 0)"
            );
            $stmt->execute([(int)$targetOrgId, $taskId]);
        } catch (Throwable $e) {
            // Keep fetch flow stable even if repair update is blocked.
        }
    }
}

if (!function_exists('subtask_index_exists')) {
    function subtask_index_exists($pdo, $table, $indexName)
    {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'mysql') {
            $sql = "SELECT 1
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                      AND index_name = ?
                    LIMIT 1";
        } else {
            $sql = "SELECT 1
                    FROM pg_indexes
                    WHERE schemaname = 'public'
                      AND tablename = ?
                      AND indexname = ?
                    LIMIT 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $indexName]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('subtask_ensure_schema')) {
    function subtask_ensure_schema($pdo)
    {
        static $alreadyEnsured = false;
        if ($alreadyEnsured) {
            return;
        }
        if (!tenant_table_exists($pdo, 'subtasks')) {
            $alreadyEnsured = true;
            return;
        }

        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        try {
            if (!tenant_column_exists($pdo, 'subtasks', 'timeline_phase_id')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE subtasks ADD COLUMN timeline_phase_id INT NULL");
                } else {
                    $pdo->exec("ALTER TABLE subtasks ADD COLUMN timeline_phase_id INTEGER NULL");
                }
            }

            if (!tenant_column_exists($pdo, 'subtasks', 'reviewed_by')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE subtasks ADD COLUMN reviewed_by INT NULL");
                } else {
                    $pdo->exec("ALTER TABLE subtasks ADD COLUMN reviewed_by INTEGER NULL");
                }
            }

            if (!tenant_column_exists($pdo, 'subtasks', 'reviewed_at')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE subtasks ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL");
                } else {
                    $pdo->exec("ALTER TABLE subtasks ADD COLUMN reviewed_at TIMESTAMP NULL");
                }
            }

            if (!subtask_index_exists($pdo, 'subtasks', 'idx_subtasks_timeline_phase_id')) {
                if ($driver === 'mysql') {
                    $pdo->exec("CREATE INDEX idx_subtasks_timeline_phase_id ON subtasks (timeline_phase_id)");
                } else {
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_subtasks_timeline_phase_id ON subtasks (timeline_phase_id)");
                }
            }
        } catch (Throwable $e) {
            // Keep legacy subtask flows working even if schema auto-upgrade is blocked.
        }

        $alreadyEnsured = true;
    }
}

if (!function_exists('subtask_timeline_phase_is_valid_for_task')) {
    function subtask_timeline_phase_is_valid_for_task($pdo, $timelinePhaseId, $taskId)
    {
        $timelinePhaseId = (int)$timelinePhaseId;
        $taskId = (int)$taskId;

        if ($timelinePhaseId <= 0 || $taskId <= 0) {
            return false;
        }

        subtask_ensure_schema($pdo);

        if (
            !tenant_table_exists($pdo, 'project_timeline_tasks')
            || !tenant_table_exists($pdo, 'project_timeline_phases')
            || !tenant_column_exists($pdo, 'subtasks', 'timeline_phase_id')
        ) {
            return false;
        }

        $sql = "SELECT 1
                FROM project_timeline_phases pp
                JOIN project_timeline_tasks tt ON tt.id = pp.timeline_task_id
                WHERE pp.id = ? AND tt.project_id = ?";

        $params = [$timelinePhaseId, $taskId];
        $phaseScope = tenant_get_scope($pdo, 'project_timeline_phases', 'pp');
        $taskScope = tenant_get_scope($pdo, 'project_timeline_tasks', 'tt');
        $sql .= $phaseScope['sql'] . $taskScope['sql'];
        $sql .= " LIMIT 1";
        $params = array_merge($params, $phaseScope['params'], $taskScope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('subtask_normalize_date_ymd')) {
    function subtask_normalize_date_ymd($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTime($value))->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('subtask_compute_phase_date_window')) {
    function subtask_compute_phase_date_window($taskCreatedAt, $startDay, $durationDays)
    {
        $createdDate = subtask_normalize_date_ymd($taskCreatedAt);
        if ($createdDate === null) {
            return [null, null];
        }

        $startDay = max(1, (int)$startDay);
        $durationDays = max(1, (int)$durationDays);

        try {
            $startDate = new DateTime($createdDate);
            $startDate->modify('+' . ($startDay - 1) . ' days');

            $endDate = clone $startDate;
            $endDate->modify('+' . ($durationDays - 1) . ' days');

            return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
        } catch (Throwable $e) {
            return [null, null];
        }
    }
}

if (!function_exists('subtask_task_has_timeline_phases')) {
    function subtask_task_has_timeline_phases($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return false;
        }

        subtask_ensure_schema($pdo);

        if (
            !tenant_table_exists($pdo, 'project_timeline_tasks')
            || !tenant_table_exists($pdo, 'project_timeline_phases')
        ) {
            return false;
        }

        $sql = "SELECT 1
                FROM project_timeline_phases pp
                JOIN project_timeline_tasks tt ON tt.id = pp.timeline_task_id
                WHERE tt.project_id = ?";
        $params = [$taskId];
        $phaseScope = tenant_get_scope($pdo, 'project_timeline_phases', 'pp');
        $taskScope = tenant_get_scope($pdo, 'project_timeline_tasks', 'tt');
        $sql .= $phaseScope['sql'] . $taskScope['sql'];
        $sql .= " LIMIT 1";
        $params = array_merge($params, $phaseScope['params'], $taskScope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('subtask_get_phase_due_window')) {
    function subtask_get_phase_due_window($pdo, $taskId, $timelinePhaseId)
    {
        $taskId = (int)$taskId;
        $timelinePhaseId = (int)$timelinePhaseId;

        if ($taskId <= 0 || $timelinePhaseId <= 0) {
            return null;
        }

        subtask_ensure_schema($pdo);

        if (
            !tenant_table_exists($pdo, 'project_timeline_tasks')
            || !tenant_table_exists($pdo, 'project_timeline_phases')
            || !tenant_table_exists($pdo, 'tasks')
        ) {
            return null;
        }

        $sql = "SELECT pp.start_day, pp.duration_days, t.created_at AS project_created_at
                FROM project_timeline_phases pp
                JOIN project_timeline_tasks tt ON tt.id = pp.timeline_task_id
                JOIN tasks t ON t.id = tt.project_id
                WHERE tt.project_id = ? AND pp.id = ?";
        $params = [$taskId, $timelinePhaseId];
        $phaseScope = tenant_get_scope($pdo, 'project_timeline_phases', 'pp');
        $taskScope = tenant_get_scope($pdo, 'project_timeline_tasks', 'tt');
        $projectScope = tenant_get_scope($pdo, 'tasks', 't');
        $sql .= $phaseScope['sql'] . $taskScope['sql'] . $projectScope['sql'];
        $sql .= " LIMIT 1";
        $params = array_merge($params, $phaseScope['params'], $taskScope['params'], $projectScope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        [$startDate, $endDate] = subtask_compute_phase_date_window(
            (string)($row['project_created_at'] ?? ''),
            (int)($row['start_day'] ?? 1),
            (int)($row['duration_days'] ?? 1)
        );

        if ($startDate === null || $endDate === null) {
            return null;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}

if (!function_exists('get_timeline_phases_for_task')) {
    function get_timeline_phases_for_task($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return [];
        }

        subtask_ensure_schema($pdo);

        if (
            !tenant_table_exists($pdo, 'project_timeline_tasks')
            || !tenant_table_exists($pdo, 'project_timeline_phases')
            || !tenant_table_exists($pdo, 'tasks')
        ) {
            return [];
        }

        $sql = "SELECT pp.id, pp.name, pp.timeline_task_id, tt.title AS timeline_task_title,
                       pp.start_day, pp.duration_days, t.created_at AS project_created_at
                FROM project_timeline_phases pp
                JOIN project_timeline_tasks tt ON tt.id = pp.timeline_task_id
                JOIN tasks t ON t.id = tt.project_id
                WHERE tt.project_id = ?";
        $params = [$taskId];
        $taskScope = tenant_get_scope($pdo, 'project_timeline_tasks', 'tt');
        $phaseScope = tenant_get_scope($pdo, 'project_timeline_phases', 'pp');
        $projectScope = tenant_get_scope($pdo, 'tasks', 't');
        $sql .= $taskScope['sql'] . $phaseScope['sql'] . $projectScope['sql'];
        $params = array_merge($params, $taskScope['params'], $phaseScope['params'], $projectScope['params']);
        $sql .= " ORDER BY tt.sort_order ASC, tt.id ASC, pp.sort_order ASC, pp.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            [$startDate, $endDate] = subtask_compute_phase_date_window(
                (string)($row['project_created_at'] ?? ''),
                (int)($row['start_day'] ?? 1),
                (int)($row['duration_days'] ?? 1)
            );
            $row['phase_start_date'] = $startDate;
            $row['phase_end_date'] = $endDate;
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('subtask_get_by_timeline_phase_id')) {
    function subtask_get_by_timeline_phase_id($pdo, $timelinePhaseId)
    {
        $timelinePhaseId = (int)$timelinePhaseId;
        if ($timelinePhaseId <= 0 || !tenant_column_exists($pdo, 'subtasks', 'timeline_phase_id')) {
            return null;
        }

        $sql = "SELECT * FROM subtasks WHERE timeline_phase_id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$timelinePhaseId], 'subtasks');
        $sql .= " ORDER BY id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        if (tenant_column_exists($pdo, 'subtasks', 'organization_id')) {
            $stmt = $pdo->prepare(
                "SELECT * FROM subtasks
                 WHERE timeline_phase_id = ?
                   AND (organization_id IS NULL OR organization_id = 0)
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([$timelinePhaseId]);
            $legacy = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($legacy) {
                $targetOrgId = tenant_get_current_org_id();
                if (!$targetOrgId) {
                    $targetOrgId = subtask_get_task_org_id($pdo, (int)($legacy['task_id'] ?? 0));
                }
                if ($targetOrgId) {
                    $upd = $pdo->prepare("UPDATE subtasks SET organization_id = ? WHERE id = ?");
                    $upd->execute([(int)$targetOrgId, (int)$legacy['id']]);
                    $legacy['organization_id'] = (int)$targetOrgId;
                }
                return $legacy;
            }
        }

        return null;
    }
}

if (!function_exists('subtask_is_user_assigned_to_task')) {
    function subtask_is_user_assigned_to_task($pdo, $taskId, $userId)
    {
        $taskId = (int)$taskId;
        $userId = (int)$userId;
        if ($taskId <= 0 || $userId <= 0) {
            return false;
        }

        $sql = "SELECT 1 FROM task_assignees WHERE task_id = ? AND user_id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$taskId, $userId], 'task_assignees');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('subtask_get_task_leader_user_id')) {
    function subtask_get_task_leader_user_id($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return 0;
        }

        $sql = "SELECT user_id
                FROM task_assignees
                WHERE task_id = ? AND role = 'leader'";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$taskId], 'task_assignees');
        $sql .= " ORDER BY id ASC";
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('subtask_get_timeline_task_row')) {
    function subtask_get_timeline_task_row($pdo, $timelineTaskId)
    {
        $timelineTaskId = (int)$timelineTaskId;
        if ($timelineTaskId <= 0 || !tenant_table_exists($pdo, 'project_timeline_tasks')) {
            return null;
        }

        $sql = "SELECT id, project_id, title, assignee_user_id
                FROM project_timeline_tasks
                WHERE id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$timelineTaskId], 'project_timeline_tasks');
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('subtask_fetch_timeline_phases_for_task_lane')) {
    function subtask_fetch_timeline_phases_for_task_lane($pdo, $timelineTaskId)
    {
        $timelineTaskId = (int)$timelineTaskId;
        if ($timelineTaskId <= 0 || !tenant_table_exists($pdo, 'project_timeline_phases')) {
            return [];
        }

        $sql = "SELECT id, timeline_task_id, name, start_day, duration_days
                FROM project_timeline_phases
                WHERE timeline_task_id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$timelineTaskId], 'project_timeline_phases');
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('subtask_resolve_autosync_member_id')) {
    function subtask_resolve_autosync_member_id($pdo, $projectId, $timelineTaskRow)
    {
        $projectId = (int)$projectId;
        $timelineTaskRow = is_array($timelineTaskRow) ? $timelineTaskRow : [];
        if ($projectId <= 0) {
            return 0;
        }

        $assigneeUserId = (int)($timelineTaskRow['assignee_user_id'] ?? 0);
        if ($assigneeUserId > 0 && subtask_is_user_assigned_to_task($pdo, $projectId, $assigneeUserId)) {
            return $assigneeUserId;
        }

        $leaderId = subtask_get_task_leader_user_id($pdo, $projectId);
        if ($leaderId > 0) {
            return $leaderId;
        }

        $sql = "SELECT user_id
                FROM task_assignees
                WHERE task_id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$projectId], 'task_assignees');
        $sql .= " ORDER BY CASE WHEN role = 'leader' THEN 0 ELSE 1 END, id ASC";
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('subtask_sync_from_timeline_phase')) {
    function subtask_sync_from_timeline_phase($pdo, $projectId, $timelineTaskRow, $phaseRow, $actorUserId = 0)
    {
        subtask_ensure_schema($pdo);

        $projectId = (int)$projectId;
        $timelineTaskRow = is_array($timelineTaskRow) ? $timelineTaskRow : [];
        $phaseRow = is_array($phaseRow) ? $phaseRow : [];
        $phaseId = (int)($phaseRow['id'] ?? 0);

        if ($projectId <= 0 || $phaseId <= 0 || !tenant_column_exists($pdo, 'subtasks', 'timeline_phase_id')) {
            return null;
        }

        $memberId = subtask_resolve_autosync_member_id($pdo, $projectId, $timelineTaskRow);
        if ($memberId <= 0) {
            return null;
        }

        $phaseWindow = subtask_get_phase_due_window($pdo, $projectId, $phaseId);
        $dueDate = is_array($phaseWindow) ? (string)($phaseWindow['end_date'] ?? '') : '';
        if ($dueDate === '') {
            $dueDate = subtask_normalize_date_ymd(date('Y-m-d'));
        }
        if ($dueDate === null || $dueDate === '') {
            return null;
        }

        $description = trim((string)($phaseRow['name'] ?? ''));
        if ($description === '') {
            $description = 'Timeline Phase #' . $phaseId;
        }

        $existing = subtask_get_by_timeline_phase_id($pdo, $phaseId);
        if ($existing) {
            $existingStatus = strtolower(trim((string)($existing['status'] ?? 'pending')));
            $isLocked = in_array($existingStatus, ['submitted', 'completed'], true);

            $nextDescription = $isLocked ? (string)($existing['description'] ?? $description) : $description;
            $nextDueDate = $isLocked
                ? (subtask_normalize_date_ymd((string)($existing['due_date'] ?? '')) ?: $dueDate)
                : $dueDate;
            $nextMemberId = $isLocked ? (int)($existing['member_id'] ?? 0) : $memberId;
            if ($nextMemberId <= 0) {
                $nextMemberId = $memberId;
            }

            $sql = "UPDATE subtasks
                    SET description = ?, due_date = ?, member_id = ?, updated_at = NOW()
                    WHERE id = ?";
            [$sql, $params] = subtask_append_scope(
                $pdo,
                $sql,
                [$nextDescription, $nextDueDate, $nextMemberId, (int)$existing['id']],
                'subtasks'
            );
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return get_subtask_by_id($pdo, (int)$existing['id']);
        }

        $newId = insert_subtask($pdo, [
            $projectId,
            $memberId,
            $description,
            $dueDate,
            $phaseId,
        ]);

        return $newId ? get_subtask_by_id($pdo, (int)$newId) : null;
    }
}

if (!function_exists('subtask_sync_all_phases_for_timeline_task')) {
    function subtask_sync_all_phases_for_timeline_task($pdo, $timelineTaskId, $actorUserId = 0)
    {
        $timelineTaskId = (int)$timelineTaskId;
        if ($timelineTaskId <= 0) {
            return ['total' => 0, 'synced' => 0];
        }

        $timelineTask = subtask_get_timeline_task_row($pdo, $timelineTaskId);
        if (!$timelineTask) {
            return ['total' => 0, 'synced' => 0];
        }

        $projectId = (int)($timelineTask['project_id'] ?? 0);
        if ($projectId <= 0) {
            return ['total' => 0, 'synced' => 0];
        }

        $phaseRows = subtask_fetch_timeline_phases_for_task_lane($pdo, $timelineTaskId);
        $synced = 0;

        foreach ($phaseRows as $phaseRow) {
            $result = subtask_sync_from_timeline_phase($pdo, $projectId, $timelineTask, $phaseRow, (int)$actorUserId);
            if ($result) {
                $synced += 1;
            }
        }

        return [
            'total' => count($phaseRows),
            'synced' => $synced,
        ];
    }
}

if (!function_exists('subtask_sync_all_phases_for_project_task')) {
    function subtask_sync_all_phases_for_project_task($pdo, $projectId, $actorUserId = 0)
    {
        $projectId = (int)$projectId;
        if ($projectId <= 0 || !tenant_table_exists($pdo, 'project_timeline_tasks')) {
            return ['total' => 0, 'synced' => 0];
        }

        $sql = "SELECT id FROM project_timeline_tasks WHERE project_id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$projectId], 'project_timeline_tasks');
        $sql .= " ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $timelineTaskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $total = 0;
        $synced = 0;
        foreach ($timelineTaskIds as $timelineTaskId) {
            $result = subtask_sync_all_phases_for_timeline_task($pdo, (int)$timelineTaskId, (int)$actorUserId);
            $total += (int)($result['total'] ?? 0);
            $synced += (int)($result['synced'] ?? 0);
        }

        return [
            'total' => $total,
            'synced' => $synced,
        ];
    }
}

function insert_subtask($pdo, $data)
{
    subtask_ensure_schema($pdo);

    $taskId = isset($data[0]) ? (int)$data[0] : 0;
    $orgId = tenant_get_current_org_id();
    if (!$orgId && $taskId > 0) {
        $orgId = subtask_get_task_org_id($pdo, $taskId);
    }
    $hasOrg = tenant_column_exists($pdo, 'subtasks', 'organization_id') && $orgId;
    $hasTimelinePhase = tenant_column_exists($pdo, 'subtasks', 'timeline_phase_id');
    $timelinePhaseId = isset($data[4]) ? (int)$data[4] : 0;
    if ($timelinePhaseId <= 0) {
        $timelinePhaseId = null;
    }

    if ($hasOrg && $hasTimelinePhase) {
        $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date, timeline_phase_id, organization_id) VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$data[0], $data[1], $data[2], $data[3], $timelinePhaseId, $orgId];
    } elseif ($hasOrg) {
        $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date, organization_id) VALUES (?, ?, ?, ?, ?)";
        $params = [$data[0], $data[1], $data[2], $data[3], $orgId];
    } elseif ($hasTimelinePhase) {
        $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date, timeline_phase_id) VALUES (?, ?, ?, ?, ?)";
        $params = [$data[0], $data[1], $data[2], $data[3], $timelinePhaseId];
    } else {
        $sql = "INSERT INTO subtasks (task_id, member_id, description, due_date) VALUES (?, ?, ?, ?)";
        $params = array_slice($data, 0, 4);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

function get_subtasks_by_task($pdo, $task_id)
{
    subtask_ensure_schema($pdo);
    subtask_repair_org_scope_for_task($pdo, $task_id);

    $hasTimelinePhase = tenant_column_exists($pdo, 'subtasks', 'timeline_phase_id');
    $hasTimelinePhaseTable = tenant_table_exists($pdo, 'project_timeline_phases');

    if ($hasTimelinePhase && $hasTimelinePhaseTable) {
        $sql = "SELECT s.*, COALESCE(u.full_name, 'Unassigned member') as member_name, pp.name AS timeline_phase_name
                FROM subtasks s
                LEFT JOIN users u ON s.member_id = u.id
                LEFT JOIN project_timeline_phases pp ON pp.id = s.timeline_phase_id
                WHERE s.task_id = ?";
    } elseif ($hasTimelinePhase) {
        $sql = "SELECT s.*, COALESCE(u.full_name, 'Unassigned member') as member_name, NULL AS timeline_phase_name
                FROM subtasks s
                LEFT JOIN users u ON s.member_id = u.id
                WHERE s.task_id = ?";
    } else {
        $sql = "SELECT s.*, COALESCE(u.full_name, 'Unassigned member') as member_name, NULL AS timeline_phase_id, NULL AS timeline_phase_name
                FROM subtasks s
                LEFT JOIN users u ON s.member_id = u.id
                WHERE s.task_id = ?";
    }

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

function update_subtask_status($pdo, $id, $status, $feedback = null, $score = null, $reviewedBy = null)
{
    subtask_ensure_schema($pdo);

    $hasReviewerFields = tenant_column_exists($pdo, 'subtasks', 'reviewed_by')
        && tenant_column_exists($pdo, 'subtasks', 'reviewed_at');
    $reviewedBy = (int)$reviewedBy;

    if ($hasReviewerFields && $reviewedBy > 0) {
        $sql = "UPDATE subtasks
                SET status = ?, feedback = ?, score = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
                WHERE id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$status, $feedback, $score, $reviewedBy, $id], 'subtasks');
    } else {
        $sql = "UPDATE subtasks SET status = ?, feedback = ?, score = ?, updated_at = NOW() WHERE id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$status, $feedback, $score, $id], 'subtasks');
    }

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
