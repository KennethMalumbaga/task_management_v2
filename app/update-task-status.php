<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "../DB_connection.php";
    include "model/Task.php";

    if (isset($_GET['id']) && isset($_GET['status'])) {
        $id = $_GET['id'];
        $status = $_GET['status'];

        // Validate status
        $allowed_statuses = ['pending', 'in_progress', 'completed'];
        if (!in_array($status, $allowed_statuses)) {
             $em = "Invalid status";
             header("Location: ../tasks.php?error=$em");
             exit();
        }

        $data = [$status, $id];
        update_task_status($pdo, $data);

        $sm = "Task status updated to " . str_replace('_', ' ', $status);
        header("Location: ../tasks.php?success=$sm");
        exit();
    } else {
        header("Location: ../tasks.php");
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}

