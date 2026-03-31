<?php
session_start();

if ((isset($_SESSION['role']) && $_SESSION['role'] === "employee") || (isset($_SESSION['role']) && $_SESSION['role'] === "admin")) {
    require_once "../DB_connection.php";
    require_once "../inc/csrf.php";
    require_once "model/Subtask.php";
    require_once "model/Notification.php";
    require_once "model/Task.php";
    require_once "model/user.php";
    require_once __DIR__ . "/helpers/input.php";
    require_once __DIR__ . "/helpers/subtask_google_docs.php";

    if (!csrf_verify('submit_subtask_google_doc_form', $_POST['csrf_token'] ?? null, true)) {
        header("Location: ../my_task.php?error=" . urlencode("Invalid or expired request. Please refresh and try again."));
        exit();
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $subtask = subtask_google_doc_fetch_context($pdo, $id);
    if (!$subtask) {
        header("Location: ../my_task.php?error=Subtask not found");
        exit();
    }

    if (!subtask_is_google_workspace_phase($subtask)) {
        header("Location: ../my_task.php?error=" . urlencode("This subtask is not configured as a Google Workspace phase.") . "&open_task=" . $subtask['task_id']);
        exit();
    }

    $workspaceMeta = subtask_google_workspace_meta($subtask);

    if ((int)($subtask['member_id'] ?? 0) !== (int)$_SESSION['id']) {
        header("Location: ../my_task.php?error=" . urlencode("Only the assigned member can submit this " . $workspaceMeta['item_label'] . ".") . "&open_task=" . $subtask['task_id']);
        exit();
    }

    $googleDocUrl = trim((string)($subtask['google_doc_url'] ?? ''));
    if ($googleDocUrl === '') {
        header("Location: ../my_task.php?error=" . urlencode("Create the " . $workspaceMeta['item_label'] . " first before submitting this phase.") . "&open_task=" . $subtask['task_id']);
        exit();
    }

    $note = isset($_POST['submission_note']) ? validate_input($_POST['submission_note']) : null;
    update_subtask_submission($pdo, $id, $googleDocUrl, $note);

    $submitterName = trim((string)($_SESSION['full_name'] ?? ''));
    if ($submitterName === '') {
        $submitter = get_user_by_id($pdo, (int)$_SESSION['id']);
        if ($submitter && isset($submitter['full_name'])) {
            $submitterName = trim((string)$submitter['full_name']);
        }
    }
    if ($submitterName === '') {
        $submitterName = "User " . (int)$_SESSION['id'];
    }

    $assignees = get_task_assignees($pdo, $subtask['task_id']);
    if ($assignees != 0) {
        foreach ($assignees as $a) {
            if ($a['role'] == 'leader' && (int)$a['user_id'] !== (int)$_SESSION['id']) {
                insert_notification($pdo, [$workspaceMeta['submitted_notification_label'] . " submitted by " . $submitterName, $a['user_id'], 'Subtask Submitted', $subtask['task_id']]);
            }
        }
    }

    header("Location: ../my_task.php?success=" . urlencode($workspaceMeta['submitted_notice']) . "&open_task=" . $subtask['task_id']);
    exit();
}

$em = "First login";
header("Location: ../login.php?error=$em");
exit();
