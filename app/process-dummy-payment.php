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
require_once "../inc/paymongo.php";

function workspace_payment_redirect_error($message)
{
    header("Location: ../workspace-billing.php?error=" . urlencode((string)$message));
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    workspace_payment_redirect_error("Invalid request method.");
}

if (!csrf_verify('workspace_dummy_payment_form', $_POST['csrf_token'] ?? null, true)) {
    workspace_payment_redirect_error("Invalid or expired request. Please refresh and try again.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
if ($isSuperAdmin) {
    workspace_payment_redirect_error("Super Admin cannot process workspace billing from this page.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    workspace_payment_redirect_error("Workspace context is missing.");
}

$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
if ($organizationRole !== '' && !in_array($organizationRole, ['owner', 'admin'], true)) {
    workspace_payment_redirect_error("You do not have permission to process workspace billing.");
}

if (!tenant_table_exists($pdo, 'subscriptions')) {
    workspace_payment_redirect_error("Workspace billing tables are not available.");
}

if (!paymongo_is_configured()) {
    workspace_payment_redirect_error("PayMongo test mode is not configured. Add PAYMONGO_SECRET_KEY with your sk_test_ key.");
}

$requestedPlanCode = strtolower(trim((string)($_POST['plan_code'] ?? '')));
$planCatalog = tenant_workspace_plan_catalog();
if ($requestedPlanCode === '' || !isset($planCatalog[$requestedPlanCode])) {
    workspace_payment_redirect_error("Please choose a valid workspace plan.");
}

$selectedPlan = $planCatalog[$requestedPlanCode];
$planCode = (string)($selectedPlan['code'] ?? $requestedPlanCode);
$planName = (string)($selectedPlan['name'] ?? 'Workspace Plan');
$planSeatLimit = max(1, (int)($selectedPlan['seat_limit'] ?? 0));
$activeMembers = tenant_count_workspace_members($pdo, (int)$orgId);
if ($planSeatLimit < $activeMembers) {
    workspace_payment_redirect_error("The selected plan only includes {$planSeatLimit} seats, but this workspace already has {$activeMembers} active members.");
}

$method = strtolower(trim((string)($_POST['payment_method'] ?? '')));
$paymentMethodKey = 'paymongo_checkout';
$paymentMethodTypes = [];
if ($method !== '') {
    $methodConfig = paymongo_resolve_checkout_method($method);
    if ($methodConfig === null) {
        workspace_payment_redirect_error("Please choose a valid PayMongo payment method.");
    }
    $paymentMethodKey = (string)($methodConfig['key'] ?? $method);
    $paymentMethodTypes = (array)($methodConfig['types'] ?? []);
} else {
    foreach (paymongo_checkout_method_catalog() as $methodConfig) {
        foreach ((array)($methodConfig['types'] ?? []) as $type) {
            $type = strtolower(trim((string)$type));
            if ($type !== '') {
                $paymentMethodTypes[] = $type;
            }
        }
    }
    $paymentMethodTypes = array_values(array_unique($paymentMethodTypes));
}

if (empty($paymentMethodTypes)) {
    workspace_payment_redirect_error("No PayMongo payment methods are available right now.");
}

try {
    $stmtOrg = $pdo->prepare(
        "SELECT id, name, plan_code, billing_email
         FROM organizations
         WHERE id = ?
         LIMIT 1"
    );
    $stmtOrg->execute([(int)$orgId]);
    $org = $stmtOrg->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$org) {
        workspace_payment_redirect_error("Workspace was not found.");
    }

    $subscription = tenant_ensure_subscription($pdo, (int)$orgId);
    if (!$subscription) {
        workspace_payment_redirect_error("Unable to initialize workspace subscription.");
    }

    $workspaceName = trim((string)($org['name'] ?? ($_SESSION['organization_name'] ?? 'Workspace')));
    $amountCentavos = paymongo_plan_price_centavos($planCode, 'workspace');
    $state = paymongo_create_state_token();
    $referenceNumber = paymongo_reference_number('TFWS', (int)$orgId);
    $billingName = trim((string)($_SESSION['full_name'] ?? $workspaceName));
    $billingEmail = trim((string)($org['billing_email'] ?? ($_SESSION['username'] ?? '')));

    $checkoutResult = paymongo_create_checkout_session([
        'amount_centavos' => $amountCentavos,
        'billing_email' => $billingEmail,
        'billing_name' => $billingName,
        'cancel_url' => paymongo_build_app_url('/app/paymongo-return.php', [
            'flow' => 'workspace',
            'result' => 'cancel',
            'state' => $state,
        ]),
        'description' => "TaskFlow workspace subscription for {$workspaceName}",
        'item_description' => "{$planName} plan for {$workspaceName}",
        'item_name' => "{$planName} Plan",
        'metadata' => [
            'flow' => 'workspace',
            'organization_id' => (string)$orgId,
            'plan_code' => $planCode,
            'payment_method' => $paymentMethodKey,
            'workspace_name' => $workspaceName,
        ],
        'payment_method_types' => $paymentMethodTypes,
        'reference_number' => $referenceNumber,
        'success_url' => paymongo_build_app_url('/app/paymongo-return.php', [
            'flow' => 'workspace',
            'result' => 'success',
            'state' => $state,
        ]),
    ]);

    if (empty($checkoutResult['ok'])) {
        workspace_payment_redirect_error((string)($checkoutResult['error'] ?? 'Unable to start PayMongo checkout right now.'));
    }

    $_SESSION['paymongo_workspace_checkout'] = [
        'amount_php' => paymongo_plan_price_php($planCode, 'workspace'),
        'checkout_session_id' => (string)($checkoutResult['checkout_session_id'] ?? ''),
        'created_at' => time(),
        'organization_id' => (int)$orgId,
        'payment_method' => $paymentMethodKey,
        'plan_code' => $planCode,
        'plan_name' => $planName,
        'reference_number' => $referenceNumber,
        'state' => $state,
    ];

    header("Location: " . (string)$checkoutResult['checkout_url']);
    exit();
} catch (Throwable $e) {
    workspace_payment_redirect_error("Unable to start PayMongo checkout right now. Please try again.");
}
