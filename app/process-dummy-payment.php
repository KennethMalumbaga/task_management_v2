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

function dummy_payment_redirect_error($message)
{
    header("Location: ../workspace-billing.php?error=" . urlencode((string)$message));
    exit();
}

function dummy_payment_redirect_success($message)
{
    header("Location: ../workspace-billing.php?success=" . urlencode((string)$message));
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    dummy_payment_redirect_error("Invalid request method.");
}

if (!csrf_verify('workspace_dummy_payment_form', $_POST['csrf_token'] ?? null, true)) {
    dummy_payment_redirect_error("Invalid or expired request. Please refresh and try again.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
if ($isSuperAdmin) {
    dummy_payment_redirect_error("Super Admin cannot run workspace dummy checkout from this page.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    dummy_payment_redirect_error("Workspace context is missing.");
}

$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
if ($organizationRole !== '' && !in_array($organizationRole, ['owner', 'admin'], true)) {
    dummy_payment_redirect_error("You do not have permission to process workspace billing.");
}

if (!tenant_table_exists($pdo, 'subscriptions')) {
    dummy_payment_redirect_error("Workspace billing tables are not available.");
}

$method = strtolower(trim((string)($_POST['payment_method'] ?? '')));
$referenceNote = trim((string)($_POST['reference_note'] ?? ''));
$referenceNote = substr($referenceNote, 0, 80);

$methodLabels = [
    'gcash' => 'GCash',
    'card' => 'Card',
    'bank_transfer' => 'Bank Transfer',
    'over_the_counter' => 'Over the Counter',
];

if (!isset($methodLabels[$method])) {
    dummy_payment_redirect_error("Please choose a valid demo payment method.");
}

try {
    $subscription = tenant_ensure_subscription($pdo, (int)$orgId);
    if (!$subscription) {
        dummy_payment_redirect_error("Unable to initialize workspace subscription.");
    }

    $nowTs = time();
    $currentPeriodTs = !empty($subscription['current_period_end'])
        ? strtotime((string)$subscription['current_period_end'])
        : false;
    $baseTs = ($currentPeriodTs !== false && $currentPeriodTs > $nowTs) ? $currentPeriodTs : $nowTs;
    $newPeriodEnd = date('Y-m-d H:i:s', strtotime('+1 month', $baseTs));
    $providerSubscriptionId = 'dummy-' . (int)$orgId . '-' . time();
    $setParts = [
        "status = 'active'",
        "current_period_end = ?",
    ];
    $params = [$newPeriodEnd];

    if (tenant_column_exists($pdo, 'subscriptions', 'provider')) {
        $setParts[] = "provider = ?";
        $params[] = 'dummy';
    }
    if (tenant_column_exists($pdo, 'subscriptions', 'provider_subscription_id')) {
        $setParts[] = "provider_subscription_id = ?";
        $params[] = $providerSubscriptionId;
    }

    $params[] = (int)$orgId;
    $sql = "UPDATE subscriptions SET " . implode(", ", $setParts) . " WHERE organization_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $periodDisplay = date("M j, Y g:i A", strtotime($newPeriodEnd));
    $methodDisplay = $methodLabels[$method];

    $message = "Dummy payment successful via {$methodDisplay}. Subscription active until {$periodDisplay}.";
    if ($referenceNote !== '') {
        $message .= " Ref: {$referenceNote}.";
    }

    dummy_payment_redirect_success($message);
} catch (Throwable $e) {
    dummy_payment_redirect_error("Dummy payment failed right now. Please try again.");
}
