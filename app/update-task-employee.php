<?php
session_start();

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php?error=First login");
    exit();
}

if (!isset($_POST['id'])) {
    header("Location: ../edit-task-employee.php?error=Invalid request");
    exit();
}

require_once "../DB_connection.php";
require_once "Model/Task.php";
require_once "Model/Notification.php";
require_once "Model/User.php";

function clean($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$id = clean($_POST['id']);

/* ---- CHECK LEADER ---- */
$assignees = get_task_assignees($pdo, $id);
$is_leader = false;

if ($assignees != 0) {
    foreach ($assignees as $a) {
        if ($a['user_id'] == $_SESSION['id'] && $a['role'] === 'leader') {
            $is_leader = true;
            break;
        }
    }
}

if (!$is_leader) {
    header("Location: ../edit-task-employee.php?error=Only the task leader can submit&id=$id");
    exit();
}

/* ---- FILE UPLOAD ---- */
if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../edit-task-employee.php?error=Please attach a file&id=$id");
    exit();
}

$allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip'];
$ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    header("Location: ../edit-task-employee.php?error=Invalid file type&id=$id");
    exit();
}

if ($_FILES['submission_file']['size'] > 100 * 1024 * 1024) {
    header("Location: ../edit-task-employee.php?error=File too large (Max 100MB)&id=$id");
    exit();
}

$upload_dir = "../uploads";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = "task_{$id}_" . time() . ".$ext";
move_uploaded_file($_FILES['submission_file']['tmp_name'], "$upload_dir/$filename");

/* ---- SAVE SUBMISSION ---- */
update_task_submission($pdo, ["uploads/$filename", $id]);

/* ---- NOTIFY ADMINS ---- */
$task = get_task_by_id($pdo, $id);
$user = get_user_by_id($pdo, $_SESSION['id']);

$stmt = $pdo->prepare("SELECT id FROM users WHERE role='admin'");
$stmt->execute();

foreach ($stmt->fetchAll() as $admin) {
    insert_notification($pdo, [
        "{$task['title']} submitted by {$user['full_name']}",
        $admin['id'],
        'Task Submitted',
        $id
    ]);
}

header("Location: ../edit-task-employee.php?success=Submitted successfully&id=$id");
exit();
