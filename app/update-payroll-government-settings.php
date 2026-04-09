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

if (!csrf_verify('payroll_government_settings_form', $_POST['csrf_token'] ?? null, true)) {
    header('Location: ../payroll.php?error=' . urlencode('Invalid or expired request. Please refresh and try again.'));
    exit;
}

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

$result = payroll_government_settings_save($pdo, [
    'sss_enabled' => !empty($_POST['sss_enabled']),
    'philhealth_enabled' => !empty($_POST['philhealth_enabled']),
    'pagibig_enabled' => !empty($_POST['pagibig_enabled']),
    'withholding_tax_enabled' => !empty($_POST['withholding_tax_enabled']),
], (int)$_SESSION['id']);

if (!empty($result['ok'])) {
    header('Location: ' . $redirectBase . '&success=' . urlencode('Government deductions updated.'));
    exit;
}

$error = $result['error'] ?? 'Unable to update government deductions.';
header('Location: ' . $redirectBase . '&error=' . urlencode($error));
exit;
