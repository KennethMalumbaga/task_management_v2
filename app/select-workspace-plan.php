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

function plan_redirect_error($message)
{
    header("Location: ../workspace-billing.php?error=" . urlencode((string)$message));
    exit();
}

function plan_redirect_success($message)
{
    header("Location: ../workspace-billing.php?success=" . urlencode((string)$message));
    exit();
}

if (!isset($_POST['plan_code'])) {
    plan_redirect_error("Plan selection is required.");
}

if (!csrf_verify('workspace_plan_select_form', $_POST['csrf_token'] ?? null, true)) {
    plan_redirect_error("Invalid or expired request. Please refresh and try again.");
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
if ($isSuperAdmin) {
    plan_redirect_error("Super Admin cannot update workspace plan from this page.");
}

$orgId = tenant_get_current_org_id();
if (!$orgId) {
    plan_redirect_error("Workspace context is missing.");
}

$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
if ($organizationRole !== '' && !in_array($organizationRole, ['owner', 'admin'], true)) {
    plan_redirect_error("You do not have permission to update workspace plan.");
}

$planCode = trim((string)$_POST['plan_code']);
if ($planCode === '') {
    plan_redirect_error("Plan selection is required.");
}

$result = tenant_apply_workspace_plan($pdo, (int)$orgId, $planCode);
if (empty($result['ok'])) {
    plan_redirect_error((string)($result['reason'] ?? 'Failed to update workspace plan right now.'));
}

$selectedPlan = $result['plan'] ?? tenant_resolve_workspace_plan($planCode, 'starter');
$selectedPlanName = (string)($selectedPlan['name'] ?? 'Plan');
$selectedSeatLimit = (int)($selectedPlan['seat_limit'] ?? 0);

plan_redirect_success("Workspace plan updated to {$selectedPlanName} ({$selectedSeatLimit} seats).");
