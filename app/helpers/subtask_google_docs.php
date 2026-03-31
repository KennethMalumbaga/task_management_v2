<?php

require_once __DIR__ . '/google_workspace.php';
require_once dirname(__DIR__) . '/model/Subtask.php';
require_once dirname(__DIR__) . '/model/Task.php';
require_once dirname(__DIR__) . '/model/user.php';
require_once dirname(__DIR__) . '/model/Timeline.php';

if (!function_exists('subtask_google_doc_fetch_context')) {
    function subtask_google_doc_fetch_context($pdo, $subtaskId)
    {
        $subtaskId = (int)$subtaskId;
        if ($subtaskId <= 0) {
            return null;
        }

        subtask_ensure_schema($pdo);
        if (function_exists('timeline_ensure_schema')) {
            timeline_ensure_schema($pdo);
        }

        $sql = "SELECT s.*, t.title AS task_title,
                       COALESCE(pp.name, '') AS timeline_phase_name,
                       COALESCE(pp.phase_type, 'standard') AS timeline_phase_type,
                       COALESCE(mu.full_name, 'Assigned Member') AS member_name
                FROM subtasks s
                JOIN tasks t ON t.id = s.task_id
                LEFT JOIN project_timeline_phases pp ON pp.id = s.timeline_phase_id
                LEFT JOIN users mu ON mu.id = s.member_id
                WHERE s.id = ?";
        [$sql, $params] = subtask_append_scope($pdo, $sql, [$subtaskId], 'subtasks', 's');
        $taskScope = tenant_get_scope($pdo, 'tasks', 't');
        $sql .= $taskScope['sql'];
        $params = array_merge($params, $taskScope['params']);
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('subtask_google_doc_collect_share_emails')) {
    function subtask_google_doc_collect_share_emails($pdo, $taskId)
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return [];
        }

        $emails = [];

        $admins = get_all_users($pdo, 'admin');
        foreach ((array)$admins as $admin) {
            $email = strtolower(trim((string)($admin['username'] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }

        $assignees = get_task_assignees($pdo, $taskId);
        foreach ((array)$assignees as $assignee) {
            $userId = (int)($assignee['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $user = get_user_by_id($pdo, $userId);
            if (!$user) {
                continue;
            }
            $email = strtolower(trim((string)($user['username'] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }
}

if (!function_exists('subtask_google_doc_seed_lines')) {
    function subtask_google_doc_seed_lines(array $context)
    {
        $lines = [
            trim((string)($context['task_title'] ?? 'TaskFlow Document')),
        ];

        $phaseName = trim((string)($context['timeline_phase_name'] ?? ''));
        if ($phaseName !== '') {
            $lines[] = 'Phase: ' . $phaseName;
        }

        $memberName = trim((string)($context['member_name'] ?? ''));
        if ($memberName !== '') {
            $lines[] = 'Assignee: ' . $memberName;
        }

        $dueDate = trim((string)($context['due_date'] ?? ''));
        if ($dueDate !== '') {
            $lines[] = 'Due Date: ' . $dueDate;
        }

        $lines[] = '';
        $lines[] = 'Created from TaskFlow.';

        return $lines;
    }
}

if (!function_exists('subtask_google_doc_create_and_store')) {
    function subtask_google_doc_create_and_store($pdo, array $context, $accessToken)
    {
        $existingUrl = trim((string)($context['google_doc_url'] ?? ''));
        if ($existingUrl !== '') {
            return [
                'ok' => true,
                'url' => $existingUrl,
                'doc_id' => trim((string)($context['google_doc_id'] ?? '')),
                'created' => false,
            ];
        }

        $document = google_workspace_create_document(
            $accessToken,
            google_workspace_build_subtask_doc_title(
                $context['task_title'] ?? '',
                $context['timeline_phase_name'] ?? '',
                $context['member_name'] ?? ''
            )
        );

        if (!$document['ok']) {
            return [
                'ok' => false,
                'error' => (string)($document['error'] ?? 'Unable to create Google Doc.'),
            ];
        }

        $doc = (array)($document['document'] ?? []);
        $docId = trim((string)($doc['id'] ?? ''));
        $docUrl = trim((string)($doc['webViewLink'] ?? ''));
        if ($docId === '' || $docUrl === '') {
            return [
                'ok' => false,
                'error' => 'Google Doc was created but the returned link was incomplete.',
            ];
        }

        google_workspace_seed_document_content($accessToken, $docId, subtask_google_doc_seed_lines($context));
        google_workspace_share_document_with_emails(
            $accessToken,
            $docId,
            subtask_google_doc_collect_share_emails($pdo, (int)($context['task_id'] ?? 0))
        );

        if (!subtask_attach_google_doc($pdo, (int)($context['id'] ?? 0), $docId, $docUrl)) {
            return [
                'ok' => false,
                'error' => 'Google Doc was created, but TaskFlow could not save the link.',
            ];
        }

        return [
            'ok' => true,
            'url' => $docUrl,
            'doc_id' => $docId,
            'created' => true,
        ];
    }
}
