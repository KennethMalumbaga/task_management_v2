<?php
session_start();

if (!isset($_SESSION['role']) || !isset($_SESSION['id']) || $_SESSION['role'] !== "admin") {
    $em = "First login";
    header("Location: ../login.php?error=$em");
    exit();
}

include "../DB_connection.php";
include "model/user.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";

function seat_limit_redirect_error($message)
{
    header("Location: ../workspace-billing.php?error=" . urlencode((string)$message));
    exit();
}

function seat_limit_redirect_success($message)
{
    header("Location: ../workspace-billing.php?success=" . urlencode((string)$message));
    exit();
}
seat_limit_redirect_error("Manual seat updates are disabled. Select a workspace plan from Billing to change seat capacity.");
