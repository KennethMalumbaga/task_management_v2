<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    if (isset($_POST['group_name']) && isset($_POST['leader_id'])) {
        include "../DB_connection.php";
        include "model/Group.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $group_name = validate_input($_POST['group_name']);
        $leader_id = (int)validate_input($_POST['leader_id']);
        $member_ids = isset($_POST['member_ids']) ? $_POST['member_ids'] : [];
        $member_ids = array_filter(array_map('intval', $member_ids), function($id) { return $id > 0; });

        if ($group_name === '') {
            $em = "Group name is required";
            header("Location: ../groups.php?error=$em");
            exit();
        }
        if (check_group_exists($pdo, $group_name)) {
            $em = "Group name already exists";
            header("Location: ../groups.php?error=$em");
            exit();
        }
        if ($leader_id <= 0) {
            $em = "Select a leader";
            header("Location: ../groups.php?error=$em");
            exit();
        }

        $created_by = (int)$_SESSION['id'];
        create_group($pdo, $group_name, $leader_id, $member_ids, $created_by);
        $em = "Group created successfully";
        header("Location: ../groups.php?success=$em");
        exit();
    } else {
        $em = "Unknown error occurred";
        header("Location: ../groups.php?error=$em");
        exit();
    }
} else {
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}
