<?php
session_start();

include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once "invite_helpers.php";
require_once __DIR__ . "/helpers/input.php";
require_once __DIR__ . "/helpers/password_policy.php";

function generate_temporary_password($length = 10)
{
    $length = max(8, (int)$length);
    $upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    $lower = "abcdefghijkmnopqrstuvwxyz";
    $digits = "23456789";
    $symbols = "!@#$%&*";
    $all = $upper . $lower . $digits . $symbols;

    do {
        $out = '';
        $out .= $upper[random_int(0, strlen($upper) - 1)];
        $out .= $lower[random_int(0, strlen($lower) - 1)];
        $out .= $digits[random_int(0, strlen($digits) - 1)];
        $out .= $symbols[random_int(0, strlen($symbols) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $out .= $all[random_int(0, strlen($all) - 1)];
        }

        $chars = str_split($out);
        shuffle($chars);
        $out = implode('', $chars);
    } while (!password_meets_policy($out));

    return $out;
}

if (!isset($_POST['token']) || !isset($_POST['full_name'])) {
    header("Location: ../login.php?error=Invalid invitation request.");
    exit();
}

$requestToken = trim((string)($_POST['token'] ?? ''));
$submittedEmail = strtolower(validate_input($_POST['email'] ?? ''));
$redirectEmailParam = $submittedEmail !== '' ? "&email=" . urlencode($submittedEmail) : "";
if (!csrf_verify('accept_workspace_invite_form', $_POST['csrf_token'] ?? null, true)) {
    header("Location: ../join-workspace.php?token=" . urlencode($requestToken) . $redirectEmailParam . "&error=" . urlencode("Invalid or expired request. Please try again."));
    exit();
}

$token = validate_input($_POST['token']);
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
$fullName = validate_input($_POST['full_name']);

if ($token === '') {
    header("Location: ../join-workspace.php?error=Invitation token is missing.");
    exit();
}

if ($fullName === '') {
    header("Location: ../join-workspace.php?token=" . urlencode($token) . $redirectEmailParam . "&error=" . urlencode("Full name is required."));
    exit();
}

if (!tenant_table_exists($pdo, 'workspace_invites')) {
    header("Location: ../join-workspace.php?error=Invitation system is not available.");
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT wi.*, o.status AS organization_status
         FROM workspace_invites wi
         JOIN organizations o ON o.id = wi.organization_id
         WHERE wi.token = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$token]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invite) {
        throw new RuntimeException("Invalid invitation link.");
    }

    $inviteStatus = strtolower((string)$invite['status']);
    if ($inviteStatus !== 'pending') {
        throw new RuntimeException("This invitation is no longer active.");
    }

    if (strtotime((string)$invite['expires_at']) <= time()) {
        $upd = $pdo->prepare("UPDATE workspace_invites SET status = 'expired' WHERE id = ?");
        $upd->execute([(int)$invite['id']]);
        throw new RuntimeException("This invitation has expired.");
    }

    $orgStatus = strtolower((string)($invite['organization_status'] ?? 'active'));
    if (in_array($orgStatus, ['suspended', 'canceled'], true)) {
        throw new RuntimeException("This workspace is currently unavailable.");
    }

    $isOpenLink = invite_is_open_link_email((string)$invite['email']);
    $email = strtolower((string)$invite['email']);
    if ($isOpenLink) {
        if ($submittedEmail === '' || !filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Valid work email is required.");
        }
        $email = $submittedEmail;
    }

    $inviteRole = strtolower((string)($invite['role'] ?? 'employee'));
    $role = ($inviteRole === 'admin') ? 'admin' : 'employee';

    // Current auth expects username/email to be globally unique.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException("This email already has an account. Ask your admin to use password reset.");
    }

    $generatedPassword = null;
    $mustChangePassword = false;
    if ($isOpenLink) {
        $generatedPassword = generate_temporary_password(10);
        $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);
        $mustChangePassword = true;
    } else {
        if ($password === '' || $confirmPassword === '') {
            throw new RuntimeException("Password fields are required.");
        }
        if (!password_meets_policy($password)) {
            throw new RuntimeException(password_policy_error());
        }
        if ($password !== $confirmPassword) {
            throw new RuntimeException("Passwords do not match.");
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    $organizationId = (int)$invite['organization_id'];

    $capacity = tenant_check_workspace_capacity($pdo, $organizationId);
    if (!$capacity['ok']) {
        throw new RuntimeException((string)$capacity['reason']);
    }

    if (tenant_column_exists($pdo, 'users', 'organization_id')) {
        $sql = "INSERT INTO users (full_name, username, password, role, must_change_password, organization_id)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullName, $email, $passwordHash, $role, $mustChangePassword ? "true" : "false", $organizationId]);
    } else {
        $sql = "INSERT INTO users (full_name, username, password, role, must_change_password)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullName, $email, $passwordHash, $role, $mustChangePassword ? "true" : "false"]);
    }

    $newUserId = (int)$pdo->lastInsertId();

    if (tenant_table_exists($pdo, 'organization_members')) {
        $memberRole = $role === 'admin' ? 'admin' : 'member';
        $stmt = $pdo->prepare(
            "INSERT INTO organization_members (organization_id, user_id, role)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$organizationId, $newUserId, $memberRole]);
    }

    $stmt = $pdo->prepare(
        "UPDATE workspace_invites
         SET status = 'accepted',
             accepted_at = NOW(),
             accepted_user_id = ?,
             email = ?,
             full_name = ?
         WHERE id = ?"
    );
    $stmt->execute([$newUserId, $email, $fullName, (int)$invite['id']]);

    if ($isOpenLink) {
        include_once "send_email.php";
        if (!send_confirmation_email($email, $fullName, (string)$generatedPassword)) {
            throw new RuntimeException("Temporary password email could not be sent. Please try again.");
        }
    }

    $pdo->commit();

    if ($isOpenLink) {
        $msg = "Account created. A temporary password was sent to your email.";
    } else {
        $msg = "Account created successfully. You can now log in.";
    }
    header("Location: ../login.php?success=" . urlencode($msg));
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $err = $e->getMessage() ?: "Unable to accept invitation.";
    header("Location: ../join-workspace.php?token=" . urlencode($token) . $redirectEmailParam . "&error=" . urlencode($err));
    exit();
}
