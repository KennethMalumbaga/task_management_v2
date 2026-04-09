<?php

session_start();
if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=Unauthorized');
    exit;
}

require_once "../DB_connection.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/model/Payroll.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../payroll.php?error=' . urlencode('Invalid request.'));
    exit;
}

if (!csrf_verify('payroll_delete_deduction_form', $_POST['csrf_token'] ?? null, true)) {
    header('Location: ../payroll.php?error=' . urlencode('Invalid or expired request. Please refresh and try again.'));
    exit;
}

$deductionId = isset($_POST['deduction_id']) ? (int)$_POST['deduction_id'] : 0;
$month = trim((string)($_POST['redirect_month'] ?? date('Y-m')));
$redirectUserId = isset($_POST['redirect_user_id']) ? (int)$_POST['redirect_user_id'] : 0;
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

$result = payroll_deduction_delete($pdo, $deductionId);
if (!empty($result['ok'])) {
    header('Location: ' . $redirectBase . '&success=' . urlencode('Payroll deduction removed.') . $redirectAnchor);
    exit;
}

$error = $result['error'] ?? 'Unable to delete payroll deduction.';
header('Location: ' . $redirectBase . '&error=' . urlencode($error) . $redirectAnchor);
exit;
