<?php
session_start();

include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";

function post_signup_payment_redirect_error($message, $state = '')
{
    $target = "../post-signup-checkout.php";
    $query = [];
    if ($state !== '') {
        $query[] = "state=" . urlencode($state);
    }
    if ((string)$message !== '') {
        $query[] = "error=" . urlencode((string)$message);
    }
    if (!empty($query)) {
        $target .= "?" . implode("&", $query);
    }
    header("Location: " . $target);
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    post_signup_payment_redirect_error("Invalid request method.");
}

if (!csrf_verify('post_signup_dummy_payment_form', $_POST['csrf_token'] ?? null, true)) {
    post_signup_payment_redirect_error("Invalid or expired request. Please refresh and try again.");
}

$pending = $_SESSION['post_signup_checkout'] ?? null;
$state = trim((string)($_POST['state'] ?? ''));
if (!is_array($pending) || empty($pending['state']) || $state === '' || !hash_equals((string)$pending['state'], $state)) {
    unset($_SESSION['post_signup_checkout']);
    header("Location: ../signup.php?error=" . urlencode("Payment session expired. Please create your workspace again."));
    exit();
}

$createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
if ($createdAt > 0 && (time() - $createdAt) > 1800) {
    unset($_SESSION['post_signup_checkout']);
    header("Location: ../signup.php?error=" . urlencode("Payment session expired. Please create your workspace again."));
    exit();
}

$orgId = (int)($pending['organization_id'] ?? 0);
if ($orgId <= 0) {
    unset($_SESSION['post_signup_checkout']);
    header("Location: ../signup.php?error=" . urlencode("Workspace context is missing. Please sign up again."));
    exit();
}

if (!tenant_table_exists($pdo, 'subscriptions')) {
    post_signup_payment_redirect_error("Subscription table is missing.", $state);
}

$method = strtolower(trim((string)($_POST['payment_method'] ?? '')));
$referenceNote = trim((string)($_POST['reference_note'] ?? ''));
$referenceNote = substr($referenceNote, 0, 80);
$methodLabels = [
    'card' => 'Card',
    'gcash' => 'GCash',
    'bank_transfer' => 'Bank Transfer',
];
if (!isset($methodLabels[$method])) {
    post_signup_payment_redirect_error("Please choose a valid payment method.", $state);
}

try {
    $subscription = tenant_ensure_subscription($pdo, $orgId);
    if (!$subscription) {
        post_signup_payment_redirect_error("Unable to initialize subscription.", $state);
    }

    $currentPeriodTs = !empty($subscription['current_period_end'])
        ? strtotime((string)$subscription['current_period_end'])
        : false;
    $nowTs = time();
    $baseTs = ($currentPeriodTs !== false && $currentPeriodTs > $nowTs) ? $currentPeriodTs : $nowTs;
    $newPeriodEnd = date('Y-m-d H:i:s', strtotime('+1 month', $baseTs));
    $providerSubscriptionId = 'dummy-signup-' . $orgId . '-' . time();

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

    $params[] = $orgId;
    $sql = "UPDATE subscriptions SET " . implode(", ", $setParts) . " WHERE organization_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $planName = (string)($pending['plan_name'] ?? 'selected');
    $methodLabel = (string)$methodLabels[$method];
    unset($_SESSION['post_signup_checkout']);

    $message = "Dummy payment successful via {$methodLabel} for {$planName} plan. You can now log in.";
    if ($referenceNote !== '') {
        $message .= " Ref: {$referenceNote}.";
    }
    header("Location: ../login.php?success=" . urlencode($message));
    exit();
} catch (Throwable $e) {
    post_signup_payment_redirect_error("Payment failed right now. Please try again.", $state);
}

