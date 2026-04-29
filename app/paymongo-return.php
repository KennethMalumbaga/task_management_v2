<?php
session_start();

include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/paymongo.php";

function paymongo_return_redirect($target, array $query = [])
{
    $location = $target;
    if (!empty($query)) {
        $location .= '?' . http_build_query($query);
    }

    header("Location: " . $location);
    exit();
}

function paymongo_return_period_display($value)
{
    if (empty($value)) {
        return 'your current billing period';
    }

    $ts = strtotime((string)$value);
    if ($ts === false) {
        return 'your current billing period';
    }

    return date("M j, Y g:i A", $ts);
}

$flow = strtolower(trim((string)($_GET['flow'] ?? '')));
$result = strtolower(trim((string)($_GET['result'] ?? '')));
$state = trim((string)($_GET['state'] ?? ''));

if (!in_array($flow, ['workspace', 'post_signup'], true) || !in_array($result, ['success', 'cancel'], true)) {
    paymongo_return_redirect("../login.php", [
        'error' => 'Invalid PayMongo return request.',
    ]);
}

if ($flow === 'workspace') {
    $pending = $_SESSION['paymongo_workspace_checkout'] ?? null;
    if (!is_array($pending) || empty($pending['state']) || $state === '' || !hash_equals((string)$pending['state'], $state)) {
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => 'Your PayMongo checkout session is invalid or expired. Please try again.',
        ]);
    }

    $createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
    if ($createdAt > 0 && (time() - $createdAt) > 3600) {
        unset($_SESSION['paymongo_workspace_checkout']);
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => 'Your PayMongo checkout session expired. Please start a new payment.',
        ]);
    }

    if ($result === 'cancel') {
        unset($_SESSION['paymongo_workspace_checkout']);
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => 'PayMongo checkout was cancelled. No payment was recorded.',
        ]);
    }

    $checkoutSessionId = trim((string)($pending['checkout_session_id'] ?? ''));
    if ($checkoutSessionId === '') {
        unset($_SESSION['paymongo_workspace_checkout']);
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => 'Checkout session details are missing. Please start a new payment.',
        ]);
    }

    $checkoutResult = paymongo_retrieve_checkout_session($checkoutSessionId);
    if (empty($checkoutResult['ok'])) {
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => (string)($checkoutResult['error'] ?? 'Unable to verify your PayMongo payment right now.'),
        ]);
    }

    $checkout = $checkoutResult['checkout'] ?? null;
    if (!paymongo_checkout_is_paid($checkout)) {
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => 'PayMongo has not marked this checkout as paid yet. Please try again in a moment.',
        ]);
    }

    $activation = paymongo_activate_workspace_subscription($pdo, (int)($pending['organization_id'] ?? 0), $checkoutSessionId);
    if (empty($activation['ok'])) {
        paymongo_return_redirect("../workspace-billing.php", [
            'error' => (string)($activation['reason'] ?? 'Unable to activate the workspace subscription right now.'),
        ]);
    }

    $summary = paymongo_checkout_payment_summary($checkout);
    unset($_SESSION['paymongo_workspace_checkout']);

    $message = "PayMongo test payment successful via " . (string)($summary['method_label'] ?? 'PayMongo') . ".";
    $message .= " Subscription active until " . paymongo_return_period_display($activation['current_period_end'] ?? null) . ".";

    paymongo_return_redirect("../workspace-billing.php", [
        'success' => $message,
    ]);
}

$pending = $_SESSION['post_signup_checkout'] ?? null;
if (!is_array($pending) || empty($pending['state']) || $state === '' || !hash_equals((string)$pending['state'], $state)) {
    unset($_SESSION['post_signup_checkout']);
    paymongo_return_redirect("../signup.php", [
        'error' => 'Your payment session is invalid or expired. Please create your workspace again.',
    ]);
}

$createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
if ($createdAt > 0 && (time() - $createdAt) > 1800) {
    unset($_SESSION['post_signup_checkout']);
    paymongo_return_redirect("../signup.php", [
        'error' => 'Your payment session expired. Please create your workspace again.',
    ]);
}

if ($result === 'cancel') {
    unset($_SESSION['post_signup_checkout']['paymongo']);
    paymongo_return_redirect("../post-signup-checkout.php", [
        'state' => $state,
        'error' => 'PayMongo checkout was cancelled. No payment was recorded.',
    ]);
}

$paymongoState = is_array($pending['paymongo'] ?? null) ? $pending['paymongo'] : [];
$checkoutSessionId = trim((string)($paymongoState['checkout_session_id'] ?? ''));
if ($checkoutSessionId === '') {
    unset($_SESSION['post_signup_checkout']);
    paymongo_return_redirect("../signup.php", [
        'error' => 'Checkout session details are missing. Please create your workspace again.',
    ]);
}

$checkoutResult = paymongo_retrieve_checkout_session($checkoutSessionId);
if (empty($checkoutResult['ok'])) {
    paymongo_return_redirect("../post-signup-checkout.php", [
        'state' => $state,
        'error' => (string)($checkoutResult['error'] ?? 'Unable to verify your PayMongo payment right now.'),
    ]);
}

$checkout = $checkoutResult['checkout'] ?? null;
if (!paymongo_checkout_is_paid($checkout)) {
    paymongo_return_redirect("../post-signup-checkout.php", [
        'state' => $state,
        'error' => 'PayMongo has not marked this checkout as paid yet. Please try again in a moment.',
    ]);
}

$activation = paymongo_activate_workspace_subscription($pdo, (int)($pending['organization_id'] ?? 0), $checkoutSessionId);
if (empty($activation['ok'])) {
    paymongo_return_redirect("../post-signup-checkout.php", [
        'state' => $state,
        'error' => (string)($activation['reason'] ?? 'Unable to activate your workspace subscription right now.'),
    ]);
}

$planName = trim((string)($pending['plan_name'] ?? 'selected'));
$planCode = strtolower(trim((string)($pending['plan_code'] ?? '')));
$enterpriseRequestedCapacity = (int)($pending['enterprise_requested_capacity'] ?? 0);
if ($planCode === 'enterprise' && $enterpriseRequestedCapacity >= 40) {
    tenant_create_enterprise_capacity_request(
        $pdo,
        (int)($pending['organization_id'] ?? 0),
        (int)($pending['user_id'] ?? 0),
        $enterpriseRequestedCapacity
    );
}
unset($_SESSION['post_signup_checkout']);

$message = "PayMongo test payment successful";
if ($planName !== '') {
    $message .= " for {$planName}";
}
$message .= ($planCode === 'enterprise' && $enterpriseRequestedCapacity >= 40)
    ? ". Your requested {$enterpriseRequestedCapacity}-member Enterprise capacity was sent to Super Admin for review."
    : "";
$message .= ". You can now log in.";

paymongo_return_redirect("../login.php", [
    'success' => $message,
]);
