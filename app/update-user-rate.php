<?php

session_start();
if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=Unauthorized');
    exit;
}

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/model/user.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../user.php?error=Invalid request');
    exit;
}

if (!csrf_verify('update_user_hourly_rate_form', $_POST['csrf_token'] ?? null, true)) {
    header('Location: ../user.php?error=' . urlencode('Invalid or expired request. Please refresh and try again.'));
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$rateRaw = trim((string)($_POST['hourly_rate'] ?? ''));
$returnContext = trim((string)($_POST['return_context'] ?? 'user_details'));
$returnMonth = trim((string)($_POST['return_month'] ?? ''));
$returnUserId = isset($_POST['return_user_id']) ? (int)$_POST['return_user_id'] : $userId;
$returnSection = trim((string)($_POST['return_section'] ?? 'rate_manager'));

$redirectBase = '../user_details.php?id=' . max(0, $userId);
$redirectAnchor = '#compensationSection';

if ($returnContext === 'payroll') {
    $query = [];
    if (preg_match('/^\d{4}-\d{2}$/', $returnMonth)) {
        $query['month'] = $returnMonth;
    }
    if ($returnUserId > 0) {
        $query['user_id'] = $returnUserId;
    }
    if ($returnSection !== '') {
        $query['section'] = $returnSection;
    }

    $redirectBase = '../payroll.php';
    if (!empty($query)) {
        $redirectBase .= '?' . http_build_query($query);
    }
    $redirectAnchor = '';
}

if ($userId <= 0) {
    header('Location: ../user.php?error=' . urlencode('Invalid employee selected.'));
    exit;
}

if (!user_compensation_ensure_schema($pdo)) {
    header('Location: ' . $redirectBase . '&error=' . urlencode('Hourly rate column is unavailable.') . $redirectAnchor);
    exit;
}

$targetUser = get_user_by_id($pdo, $userId);
if (!$targetUser) {
    header('Location: ../user.php?error=' . urlencode('Employee not found.'));
    exit;
}

if (($targetUser['role'] ?? '') !== 'employee') {
    header('Location: ' . $redirectBase . '&error=' . urlencode('Rates can only be assigned to employee accounts.') . $redirectAnchor);
    exit;
}

$hourlyRate = null;
if ($rateRaw !== '') {
    if (!is_numeric($rateRaw)) {
        header('Location: ' . $redirectBase . '&error=' . urlencode('Hourly rate must be a valid number.') . $redirectAnchor);
        exit;
    }

    $hourlyRate = round((float)$rateRaw, 2);
    if ($hourlyRate < 0) {
        header('Location: ' . $redirectBase . '&error=' . urlencode('Hourly rate cannot be negative.') . $redirectAnchor);
        exit;
    }
}

$sql = "UPDATE users SET hourly_rate = ? WHERE id = ?";
$params = [$hourlyRate, $userId];
$scope = tenant_get_scope($pdo, 'users');
$sql .= $scope['sql'];
$params = array_merge($params, $scope['params']);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$message = $hourlyRate === null
    ? 'Hourly rate cleared.'
    : 'Hourly rate saved successfully.';

header('Location: ' . $redirectBase . '&success=' . urlencode($message) . $redirectAnchor);
exit;
