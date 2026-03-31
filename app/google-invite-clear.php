<?php
session_start();

unset($_SESSION['pending_google_invite_accept']);

$token = trim((string)($_GET['token'] ?? ''));
$params = [];
if ($token !== '') {
    $params[] = 'token=' . urlencode($token);
}

$target = "../join-workspace.php";
if (!empty($params)) {
    $target .= '?' . implode('&', $params);
}

header("Location: " . $target);
exit();
