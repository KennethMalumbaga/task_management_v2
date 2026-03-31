<?php
session_start();

unset($_SESSION['pending_google_signup']);

$params = [];
$planCode = strtolower(trim((string)($_GET['plan'] ?? '')));
if ($planCode !== '') {
    $params[] = 'plan=' . urlencode($planCode);
}

$signupMode = strtolower(trim((string)($_GET['signup_mode'] ?? '')));
if ($signupMode !== '') {
    $params[] = 'signup_mode=' . urlencode($signupMode);
}

$target = "../signup.php";
if (!empty($params)) {
    $target .= '?' . implode('&', $params);
}

header("Location: " . $target);
exit();
