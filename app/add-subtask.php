<?php
session_start();
if ((isset($_SESSION['role']) && $_SESSION['role'] == "employee") || (isset($_SESSION['role']) && $_SESSION['role'] == "admin")) {
    $em = "Manual subtask creation is disabled. Subtasks are generated from timeline phases.";
    header("Location: ../my_task.php?error=" . urlencode($em));
    exit();
}
else {
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}
