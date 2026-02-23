<?php
session_start();
include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once __DIR__ . "/helpers/input.php";

if (!isset($_POST['user_name']) || !isset($_POST['full_name'])) {
    header("Location: ../signup.php?error=error");
    exit();
}

if (!csrf_verify('signup_form', $_POST['csrf_token'] ?? null, true)) {
    header("Location: ../signup.php?error=" . urlencode("Invalid or expired request. Please refresh and try again."));
    exit();
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

function generate_temporary_password($length = 10)
{
    $length = max(8, (int)$length);
    $upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    $lower = "abcdefghijkmnopqrstuvwxyz";
    $digits = "23456789";
    $symbols = "!@#$%&*";
    $all = $upper . $lower . $digits . $symbols;

    $password = '';
    $password .= $upper[random_int(0, strlen($upper) - 1)];
    $password .= $lower[random_int(0, strlen($lower) - 1)];
    $password .= $digits[random_int(0, strlen($digits) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];

    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    $chars = str_split($password);
    shuffle($chars);
    return implode('', $chars);
}

$user_name = validate_input($_POST['user_name']);
$full_name = validate_input($_POST['full_name']);
$organization_name = validate_input($_POST['organization_name'] ?? '');

if (empty($user_name)) {
    header("Location: ../signup.php?error=Username/Email is required");
    exit();
}
if (!filter_var($user_name, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../signup.php?error=Invalid email address");
    exit();
}
if (empty($full_name)) {
    header("Location: ../signup.php?error=Full Name is required");
    exit();
}
if (empty($organization_name)) {
    header("Location: ../signup.php?error=Workspace name is required");
    exit();
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE username=?");
$stmt->execute([$user_name]);
if ($stmt->rowCount() > 0) {
    header("Location: ../signup.php?error=The username/email is already taken");
    exit();
}

$generated_password = generate_temporary_password(10);
$password_hash = password_hash($generated_password, PASSWORD_DEFAULT);

$hasTenantTables = tenant_table_exists($pdo, 'organizations')
    && tenant_table_exists($pdo, 'organization_members')
    && tenant_column_exists($pdo, 'users', 'organization_id');

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
             VALUES (?, ?, ?, 'active', 'trial')"
        );
        $orgStmt->execute([$organization_name, $slug, $user_name]);
        $newOrgId = (int)$pdo->lastInsertId();

        if (tenant_table_exists($pdo, 'subscriptions')) {
            $subscription = tenant_ensure_subscription($pdo, $newOrgId);
            if (!$subscription) {
                throw new RuntimeException('Failed to initialize workspace subscription.');
            }
        }

        $userStmt = $pdo->prepare(
            "INSERT INTO users (full_name, username, password, role, must_change_password, organization_id)
             VALUES (?, ?, ?, 'admin', ?, ?)"
        );
        $userStmt->execute([$full_name, $user_name, $password_hash, "true", $newOrgId]);
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
        $userStmt->execute([$full_name, $user_name, $password_hash, "true"]);
        $newUserId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: ../signup.php?error=Unknown error occurred during registration");
    exit();
}

include_once "send_email.php";
if (send_confirmation_email($user_name, $full_name, $generated_password)) {
    if ($hasTenantTables) {
        $msg = "Workspace created. A confirmation email with your temporary password has been sent to $user_name.";
    } else {
        $msg = "Account created successfully. A confirmation email with your temporary password has been sent to $user_name.";
    }
    header("Location: ../login.php?success=" . urlencode($msg));
    exit();
}

try {
    $pdo->beginTransaction();

    if ($hasTenantTables && $newOrgId && $newUserId) {
        $stmt = $pdo->prepare("DELETE FROM organization_members WHERE organization_id = ? AND user_id = ?");
        $stmt->execute([$newOrgId, $newUserId]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$newUserId]);
        $stmt = $pdo->prepare("DELETE FROM organizations WHERE id = ?");
        $stmt->execute([$newOrgId]);
    } elseif ($newUserId) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$newUserId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$msg = "Registration failed: Could not send confirmation email to $user_name. Please ensure your email is valid.";
header("Location: ../signup.php?error=" . urlencode($msg));
exit();
