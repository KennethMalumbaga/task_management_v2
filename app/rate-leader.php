<?php
session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $em = "Invalid request";
    header("Location: ../my_task.php?error=$em");
    exit();
}

if (!isset($_POST['task_id']) || !isset($_POST['rating'])) {
    $em = "Missing required fields";
    header("Location: ../my_task.php?error=$em");
    exit();
}

include "../DB_connection.php";
include "model/LeaderFeedback.php";

function validate_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

$task_id = (int)validate_input($_POST['task_id']);
$rating = (int)validate_input($_POST['rating']);
$comment = isset($_POST['comment']) ? validate_input($_POST['comment']) : null;
$member_id = (int)$_SESSION['id'];

if ($task_id <= 0) {
    $em = "Invalid task";
    header("Location: ../my_task.php?error=$em");
    exit();
}

if ($rating < 1 || $rating > 5) {
    $em = "Rating must be between 1 and 5";
    header("Location: ../my_task.php?error=$em&open_task=$task_id");
    exit();
}

$eligibility = can_member_rate_leader($pdo, $task_id, $member_id);
if (!$eligibility || empty($eligibility['leader_id'])) {
    $em = "You are not allowed to rate the leader for this task";
    header("Location: ../my_task.php?error=$em&open_task=$task_id");
    exit();
}

$leader_id = (int)$eligibility['leader_id'];
$ok = upsert_leader_feedback($pdo, $task_id, $leader_id, $member_id, $rating, $comment);

if (!$ok) {
    $em = "Leader rating is unavailable. Please run the migration first.";
    header("Location: ../my_task.php?error=$em&open_task=$task_id");
    exit();
}

$em = "Leader rating submitted successfully";
header("Location: ../my_task.php?success=$em&open_task=$task_id");
exit();
?>
