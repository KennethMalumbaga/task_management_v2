<?php

session_start();
if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=Unauthorized');
    exit;
}

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/model/AttendanceAdjustment.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../reports.php?error=Invalid request');
    exit;
}

if (!csrf_verify('attendance_deduction_form', $_POST['csrf_token'] ?? null, true)) {
    header('Location: ../reports.php?error=Invalid or expired request');
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$attDate = trim((string)($_POST['att_date'] ?? ''));
$hoursRaw = $_POST['deduct_hours'] ?? '';
$reason = trim((string)($_POST['reason'] ?? ''));
$reason = strip_tags($reason);
$reason = substr($reason, 0, 255);
$redirect = trim((string)($_POST['redirect'] ?? 'reports.php'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attDate)) {
    $attDate = '';
}

$hours = 0;
if ($hoursRaw !== '') {
    $hours = (float)$hoursRaw;
}

if ($userId <= 0 || $attDate === '') {
    header('Location: ../reports.php?error=Invalid adjustment data');
    exit;
}

if ($hours < 0) {
    $hours = 0;
}
if ($hours > 24) {
    $hours = 24;
}

$result = attendance_adjustment_upsert($pdo, $userId, $attDate, $hours, $reason, (int)$_SESSION['id']);

if (!empty($result['ok'])) {
    $msg = $hours <= 0 ? 'Deduction cleared.' : 'Deduction saved.';
    $redirectUrl = $redirect !== '' ? $redirect : 'reports.php';
    $separator = strpos($redirectUrl, '?') === false ? '?' : '&';
    header('Location: ../' . $redirectUrl . $separator . 'success=' . urlencode($msg));
    exit;
}

$error = $result['error'] ?? 'Unable to save deduction.';
$redirectUrl = $redirect !== '' ? $redirect : 'reports.php';
$separator = strpos($redirectUrl, '?') === false ? '?' : '&';
header('Location: ../' . $redirectUrl . $separator . 'error=' . urlencode($error));
exit;
