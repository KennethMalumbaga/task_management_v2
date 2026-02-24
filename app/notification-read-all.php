<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "../DB_connection.php";
    require_once "../inc/csrf.php";
    include "model/Notification.php";

    if (!csrf_verify('notification_read_all_action', $_GET['csrf_token'] ?? null, false)) {
        $em = urlencode("Invalid or expired request");
        header("Location: ../notifications.php?error=$em");
        exit();
    }

    notification_make_all_read($pdo, $_SESSION['id']);

    $redirect = trim((string)($_GET['redirect'] ?? ''));
    if ($redirect !== '') {
        $isSafe = preg_match('/^[A-Za-z0-9._\/-]+(\?[A-Za-z0-9._~=&%-]*)?$/', $redirect)
            && strpos($redirect, '..') === false
            && strpos($redirect, '://') === false;

        if ($isSafe) {
            header("Location: ../" . ltrim($redirect, '/'));
            exit();
        }
    }

    $sm = urlencode("All notifications marked as read");
    header("Location: ../notifications.php?success=$sm");
    exit();
}

$em = urlencode("First login");
header("Location: ../login.php?error=$em");
exit();
?>
