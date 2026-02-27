<?php
session_start();
include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/input.php";
require_once __DIR__ . "/helpers/password_policy.php";
require_once __DIR__ . "/helpers/login_verification.php";

function signup_redirect_error($message, $planCode = 'starter', $signupMode = '')
{
    $query = "error=" . urlencode((string)$message);
    $safePlan = strtolower(trim((string)$planCode));
    if ($safePlan !== '') {
        $query .= "&plan=" . urlencode($safePlan);
    }
    $safeMode = strtolower(trim((string)$signupMode));
    if (in_array($safeMode, ['trial', 'paid'], true)) {
        $query .= "&signup_mode=" . urlencode($safeMode);
    }
    header("Location: ../signup.php?" . $query);
    exit();
}

function signup_checkout_state_token()
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return hash('sha256', uniqid('checkout_', true) . microtime(true));
    }
}

function signup_normalize_mode($modeRaw, $planRaw = '')
{
    $mode = strtolower(trim((string)$modeRaw));
    $plan = strtolower(trim((string)$planRaw));

    if (in_array($mode, ['free-trial', 'free_trial'], true)) {
        return 'trial';
    }
    if (in_array($mode, ['trial', 'paid'], true)) {
        return $mode;
    }
    if ($plan === '' || in_array($plan, ['trial', 'free-trial', 'free_trial'], true)) {
        return 'trial';
    }
    return 'paid';
}

$incomingPlanCode = (string)($_POST['plan_code'] ?? 'starter');
$signupMode = signup_normalize_mode($_POST['signup_mode'] ?? '', $incomingPlanCode);

if (!csrf_verify('signup_form', $_POST['csrf_token'] ?? null, true)) {
    signup_redirect_error("Invalid or expired request. Please refresh and try again.", $incomingPlanCode, $signupMode);
}

if (!isset($_POST['user_name']) || !isset($_POST['full_name']) || !isset($_POST['password'])) {
    signup_redirect_error("Invalid signup request.", $incomingPlanCode, $signupMode);
}

function build_org_slug($name)
{
    $slug = strtolower(trim((string)$name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');
    if ($slug === '') {
        $slug = 'workspace';
    }
    return substr($slug, 0, 80);
}

$selectedPlan = tenant_resolve_workspace_plan($incomingPlanCode !== '' ? $incomingPlanCode : 'starter', 'starter');
$selectedPlanCode = (string)($selectedPlan['code'] ?? 'starter');
$selectedPlanName = (string)($selectedPlan['name'] ?? 'Starter');
$selectedPlanSeatLimit = max(1, (int)($selectedPlan['seat_limit'] ?? 10));

$user_name = validate_input($_POST['user_name']);
$full_name = validate_input($_POST['full_name']);
$organization_name = validate_input($_POST['organization_name'] ?? '');
$password = (string)($_POST['password'] ?? '');

if (empty($user_name)) {
    signup_redirect_error("Username/Email is required", $selectedPlanCode, $signupMode);
}
if (!filter_var($user_name, FILTER_VALIDATE_EMAIL)) {
    signup_redirect_error("Invalid email address", $selectedPlanCode, $signupMode);
}
if (empty($full_name)) {
    signup_redirect_error("Full Name is required", $selectedPlanCode, $signupMode);
}
if (empty($organization_name)) {
    signup_redirect_error("Workspace name is required", $selectedPlanCode, $signupMode);
}
if ($password === '') {
    signup_redirect_error("Password is required", $selectedPlanCode, $signupMode);
}
if (!password_meets_policy($password)) {
    signup_redirect_error(password_policy_error(), $selectedPlanCode, $signupMode);
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE username=?");
$stmt->execute([$user_name]);
if ($stmt->rowCount() > 0) {
    signup_redirect_error("The username/email is already taken", $selectedPlanCode, $signupMode);
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
if ($password_hash === false) {
    signup_redirect_error("Unable to process password right now. Please try again.", $selectedPlanCode, $signupMode);
}

$hasTenantTables = tenant_table_exists($pdo, 'organizations')
    && tenant_table_exists($pdo, 'organization_members')
    && tenant_column_exists($pdo, 'users', 'organization_id');

if (!login_verification_ensure_table($pdo)) {
    signup_redirect_error("Unable to initialize account verification. Please try again.", $selectedPlanCode, $signupMode);
}

$newUserId = null;
$newOrgId = null;

try {
    $pdo->beginTransaction();

    if ($hasTenantTables) {
        $baseSlug = build_org_slug($organization_name);
        $slug = $baseSlug;
        $counter = 1;
        while (true) {
            $check = $pdo->prepare("SELECT id FROM organizations WHERE slug = ? LIMIT 1");
            $check->execute([$slug]);
            if (!$check->fetchColumn()) {
                break;
            }
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $orgStmt = $pdo->prepare(
            "INSERT INTO organizations (name, slug, billing_email, status, plan_code)
             VALUES (?, ?, ?, 'active', ?)"
        );
        $orgStmt->execute([$organization_name, $slug, $user_name, $selectedPlanCode]);
        $newOrgId = (int)$pdo->lastInsertId();

        if (tenant_table_exists($pdo, 'subscriptions')) {
            $subscription = tenant_ensure_subscription($pdo, $newOrgId);
            if (!$subscription) {
                throw new RuntimeException('Failed to initialize workspace subscription.');
            }

            $subStmt = $pdo->prepare(
                "UPDATE subscriptions
                 SET seat_limit = ?
                 WHERE organization_id = ?"
            );
            $subStmt->execute([$selectedPlanSeatLimit, $newOrgId]);

            if ($signupMode === 'paid') {
                $subStatusStmt = $pdo->prepare(
                    "UPDATE subscriptions
                     SET status = 'incomplete'
                     WHERE organization_id = ?"
                );
                $subStatusStmt->execute([$newOrgId]);
            }
        }

        $userStmt = $pdo->prepare(
            "INSERT INTO users (full_name, username, password, role, must_change_password, organization_id)
             VALUES (?, ?, ?, 'admin', ?, ?)"
        );
        $userStmt->execute([$full_name, $user_name, $password_hash, 0, $newOrgId]);
        $newUserId = (int)$pdo->lastInsertId();

        $memberStmt = $pdo->prepare(
            "INSERT INTO organization_members (organization_id, user_id, role)
             VALUES (?, ?, 'owner')"
        );
        $memberStmt->execute([$newOrgId, $newUserId]);
    } else {
        $userStmt = $pdo->prepare(
            "INSERT INTO users (full_name, username, password, role, must_change_password)
             VALUES (?, ?, ?, 'employee', ?)"
        );
        $userStmt->execute([$full_name, $user_name, $password_hash, 0]);
        $newUserId = (int)$pdo->lastInsertId();
    }

    if (!login_verification_mark_required($pdo, (int)$newUserId)) {
        throw new RuntimeException('Failed to initialize login verification.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("signup failed: " . $e->getMessage());
    signup_redirect_error("Unknown error occurred during registration", $selectedPlanCode, $signupMode);
}

if ($hasTenantTables) {
    if ($signupMode === 'paid') {
        $checkoutState = signup_checkout_state_token();
        $_SESSION['post_signup_checkout'] = [
            'state' => $checkoutState,
            'organization_id' => (int)$newOrgId,
            'user_id' => (int)$newUserId,
            'workspace_name' => (string)$organization_name,
            'billing_email' => (string)$user_name,
            'plan_code' => (string)$selectedPlanCode,
            'plan_name' => (string)$selectedPlanName,
            'seat_limit' => (int)$selectedPlanSeatLimit,
            'created_at' => time(),
        ];

        header("Location: ../post-signup-checkout.php?state=" . urlencode($checkoutState));
        exit();
    }

    $msg = "Workspace created with a 2-day free trial. Log in to receive your one-time verification code.";
} else {
    $msg = "Account created successfully. Log in to receive your one-time verification code.";
}
header("Location: ../login.php?success=" . urlencode($msg));
exit();
