<?php
session_start();

include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once "../inc/paymongo.php";

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

if (!paymongo_is_configured()) {
    post_signup_payment_redirect_error("PayMongo test mode is not configured. Add PAYMONGO_SECRET_KEY with your sk_test_ key.", $state);
}

$method = strtolower(trim((string)($_POST['payment_method'] ?? '')));
$methodConfig = paymongo_resolve_checkout_method($method);
if ($methodConfig === null) {
    post_signup_payment_redirect_error("Please choose a valid PayMongo payment method.", $state);
}

try {
    $subscription = tenant_ensure_subscription($pdo, $orgId);
    if (!$subscription) {
        post_signup_payment_redirect_error("Unable to initialize subscription.", $state);
    }

    $planName = (string)($pending['plan_name'] ?? 'selected');
    $planCode = (string)($pending['plan_code'] ?? 'starter');
    $workspaceName = trim((string)($pending['workspace_name'] ?? 'Workspace'));
    $billingEmail = trim((string)($pending['billing_email'] ?? ''));
    $billingName = '';

    if (tenant_table_exists($pdo, 'users')) {
        $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([(int)($pending['user_id'] ?? 0)]);
        $billingName = trim((string)$stmtUser->fetchColumn());
    }

    if ($billingName === '') {
        $billingName = $workspaceName . ' Owner';
    }

    $referenceNumber = paymongo_reference_number('TFSU', $orgId);
    $checkoutResult = paymongo_create_checkout_session([
        'amount_centavos' => paymongo_plan_price_centavos($planCode, 'post_signup'),
        'billing_email' => $billingEmail,
        'billing_name' => $billingName,
        'cancel_url' => paymongo_build_app_url('/app/paymongo-return.php', [
            'flow' => 'post_signup',
            'result' => 'cancel',
            'state' => $state,
        ]),
        'description' => "TaskFlow signup checkout for {$workspaceName}",
        'item_description' => "{$planName} plan for {$workspaceName}",
        'item_name' => "{$planName} Plan",
        'metadata' => [
            'flow' => 'post_signup',
            'organization_id' => (string)$orgId,
            'plan_code' => $planCode,
            'payment_method' => (string)$methodConfig['key'],
            'workspace_name' => $workspaceName,
        ],
        'payment_method_types' => (array)($methodConfig['types'] ?? []),
        'reference_number' => $referenceNumber,
        'success_url' => paymongo_build_app_url('/app/paymongo-return.php', [
            'flow' => 'post_signup',
            'result' => 'success',
            'state' => $state,
        ]),
    ]);

    if (empty($checkoutResult['ok'])) {
        post_signup_payment_redirect_error((string)($checkoutResult['error'] ?? 'Unable to start PayMongo checkout right now.'), $state);
    }

    $_SESSION['post_signup_checkout']['paymongo'] = [
        'checkout_session_id' => (string)($checkoutResult['checkout_session_id'] ?? ''),
        'payment_method' => (string)($methodConfig['key'] ?? ''),
        'reference_number' => $referenceNumber,
        'started_at' => time(),
    ];

    header("Location: " . (string)$checkoutResult['checkout_url']);
    exit();
} catch (Throwable $e) {
    post_signup_payment_redirect_error("Unable to start PayMongo checkout right now. Please try again.", $state);
}
