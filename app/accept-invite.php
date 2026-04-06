<?php
session_start();

include "../DB_connection.php";
require_once "../inc/tenant.php";
require_once "../inc/csrf.php";
require_once "invite_helpers.php";
require_once __DIR__ . "/helpers/input.php";
require_once __DIR__ . "/helpers/password_policy.php";
require_once __DIR__ . "/model/user.php";

function accept_invite_random_password()
{
    try {
        return bin2hex(random_bytes(24)) . '!G';
    } catch (Throwable $e) {
        return hash('sha256', uniqid('google_invite_', true) . microtime(true)) . '!G';
    }
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

$pendingGoogleInvite = isset($_SESSION['pending_google_invite_accept']) && is_array($_SESSION['pending_google_invite_accept'])
    ? $_SESSION['pending_google_invite_accept']
    : null;
$googleInviteActive = false;

if (is_array($pendingGoogleInvite)) {
    $pendingCreatedAt = isset($pendingGoogleInvite['created_at']) ? (int)$pendingGoogleInvite['created_at'] : 0;
    $pendingToken = trim((string)($pendingGoogleInvite['token'] ?? ''));

    if ($pendingCreatedAt > 0 && (time() - $pendingCreatedAt) <= 1800 && $pendingToken !== '' && hash_equals($pendingToken, $token)) {
        $googleInviteActive = true;
    } else {
        unset($_SESSION['pending_google_invite_accept']);
        $pendingGoogleInvite = null;
    }
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

    $workspaceAccess = tenant_workspace_access_state($pdo, (int)$invite['organization_id'], false);
    if (empty($workspaceAccess['can_access_workspace'])) {
        throw new RuntimeException((string)($workspaceAccess['message'] ?? "This workspace is currently unavailable."));
    }

    $isOpenLink = invite_is_open_link_email((string)$invite['email']);
    $email = strtolower((string)$invite['email']);
    $googleSub = '';
    $googlePicture = '';
    $googleEmailVerified = 0;

    if ($googleInviteActive) {
        $pendingEmail = strtolower(trim((string)($pendingGoogleInvite['email'] ?? '')));
        if ($pendingEmail === '' || !filter_var($pendingEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Google account email is missing. Please try the invite again.");
        }

        if (!$isOpenLink && !hash_equals(strtolower((string)$invite['email']), $pendingEmail)) {
            throw new RuntimeException("This Google account does not match the invited email.");
        }

        $email = $pendingEmail;
        $googleSub = trim((string)($pendingGoogleInvite['google_sub'] ?? ''));
        $googlePicture = trim((string)($pendingGoogleInvite['picture'] ?? ''));
        $googleEmailVerified = !empty($pendingGoogleInvite['email_verified']) ? 1 : 0;

        if ($googleSub === '') {
            throw new RuntimeException("Google account information is incomplete. Please try the invite again.");
        }

        $password = accept_invite_random_password();
        $confirmPassword = $password;
    } elseif ($isOpenLink) {
        if ($submittedEmail === '' || !filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Valid work email is required.");
        }
        $email = $submittedEmail;
    }

    $inviteRole = strtolower((string)($invite['role'] ?? 'employee'));
    $role = ($inviteRole === 'admin') ? 'admin' : 'employee';

    if ($googleInviteActive) {
        $existingGoogleUser = user_get_by_google_sub_unscoped($pdo, $googleSub);
        if ($existingGoogleUser !== 0) {
            throw new RuntimeException("This Google account already has a TaskFlow account.");
        }
    }

    // Current auth expects username/email to be globally unique.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException("This email already has an account. Ask your admin to use password reset.");
    }

    if (!$googleInviteActive) {
        if ($password === '' || $confirmPassword === '') {
            throw new RuntimeException("Password fields are required.");
        }
        if (!password_meets_policy($password)) {
            throw new RuntimeException(password_policy_error());
        }
        if ($password !== $confirmPassword) {
            throw new RuntimeException("Passwords do not match.");
        }
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException("Unable to process the account password right now.");
    }

    $organizationId = (int)$invite['organization_id'];

    $capacity = tenant_check_workspace_capacity($pdo, $organizationId);
    if (!$capacity['ok']) {
        throw new RuntimeException((string)$capacity['reason']);
    }

    user_google_auth_ensure_schema($pdo);
    $hasGoogleSubColumn = tenant_column_exists($pdo, 'users', 'google_sub');
    $hasGooglePictureColumn = tenant_column_exists($pdo, 'users', 'google_picture');
    $hasGoogleVerifiedColumn = tenant_column_exists($pdo, 'users', 'google_email_verified');

    $userColumns = ['full_name', 'username', 'password', 'role', 'must_change_password'];
    $userValues = [$fullName, $email, $passwordHash, $role, 0];

    if (tenant_column_exists($pdo, 'users', 'organization_id')) {
        $userColumns[] = 'organization_id';
        $userValues[] = $organizationId;
    }
    if ($googleInviteActive && $hasGoogleSubColumn) {
        $userColumns[] = 'google_sub';
        $userValues[] = $googleSub;
    }
    if ($googleInviteActive && $hasGooglePictureColumn) {
        $userColumns[] = 'google_picture';
        $userValues[] = $googlePicture !== '' ? $googlePicture : null;
    }
    if ($googleInviteActive && $hasGoogleVerifiedColumn) {
        $userColumns[] = 'google_email_verified';
        $userValues[] = $googleEmailVerified;
    }

    $sql = "INSERT INTO users (" . implode(', ', $userColumns) . ")
            VALUES (" . implode(', ', array_fill(0, count($userColumns), '?')) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($userValues);

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

    $pdo->commit();

    unset($_SESSION['pending_google_invite_accept']);

    $msg = $googleInviteActive
        ? "Workspace joined successfully. You can now sign in with Google."
        : "Account created successfully. You can now log in.";
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
