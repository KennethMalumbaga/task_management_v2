<?php

session_start();
if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=Unauthorized');
    exit;
}

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/model/user.php";
require_once __DIR__ . "/model/Payroll.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../payroll.php?error=' . urlencode('Invalid request.'));
    exit;
}

if (!csrf_verify('payroll_add_deduction_form', $_POST['csrf_token'] ?? null, true)) {
    header('Location: ../payroll.php?error=' . urlencode('Invalid or expired request. Please refresh and try again.'));
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$deductionDate = trim((string)($_POST['deduction_date'] ?? ''));
$deductionType = trim((string)($_POST['deduction_type'] ?? 'other'));
$title = trim((string)($_POST['title'] ?? ''));
$amountRaw = trim((string)($_POST['amount'] ?? ''));
$amountMode = trim((string)($_POST['amount_mode'] ?? 'fixed'));
$applyPeriod = trim((string)($_POST['apply_period'] ?? 'once'));
$notes = trim((string)($_POST['notes'] ?? ''));
$month = trim((string)($_POST['redirect_month'] ?? date('Y-m')));
$redirectUserId = isset($_POST['redirect_user_id']) ? (int)$_POST['redirect_user_id'] : $userId;
$redirectSection = trim((string)($_POST['redirect_section'] ?? 'deductions'));

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$redirectQuery = ['month' => $month];
if ($redirectUserId > 0) {
    $redirectQuery['user_id'] = $redirectUserId;
}
if ($redirectSection !== '') {
    $redirectQuery['section'] = $redirectSection;
}
$redirectBase = '../payroll.php?' . http_build_query($redirectQuery);
$redirectAnchor = '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deductionDate)) {
    header('Location: ' . $redirectBase . '&error=' . urlencode('Please provide a valid deduction date.') . $redirectAnchor);
    exit;
}

if ($userId <= 0) {
    header('Location: ' . $redirectBase . '&error=' . urlencode('Please select an employee first.') . $redirectAnchor);
    exit;
}

if ($amountRaw === '' || !is_numeric($amountRaw)) {
    header('Location: ' . $redirectBase . '&error=' . urlencode('Deduction amount must be a valid number.') . $redirectAnchor);
    exit;
}

$targetUser = get_user_by_id($pdo, $userId);
if (!$targetUser || ($targetUser['role'] ?? '') !== 'employee') {
    header('Location: ' . $redirectBase . '&error=' . urlencode('Deductions can only be assigned to employee accounts.') . $redirectAnchor);
    exit;
}

$result = payroll_deduction_create(
    $pdo,
    $userId,
    $deductionDate,
    $deductionType,
    $title,
    (float)$amountRaw,
    $notes,
    (int)$_SESSION['id'],
    $amountMode,
    $applyPeriod
);

if (!empty($result['ok'])) {
    header('Location: ' . $redirectBase . '&success=' . urlencode('Payroll deduction added.') . $redirectAnchor);
    exit;
}

$error = $result['error'] ?? 'Unable to save payroll deduction.';
header('Location: ' . $redirectBase . '&error=' . urlencode($error) . $redirectAnchor);
exit;
