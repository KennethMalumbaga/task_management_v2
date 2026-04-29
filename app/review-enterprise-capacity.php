<?php
session_start();

include "../maintenance_guard.php";
include "../DB_connection.php";
require_once "../inc/csrf.php";
require_once "../inc/tenant.php";
require_once "send_email.php";

enforce_maintenance_script_access();

function enterprise_capacity_review_redirect($message, $isError = false)
{
    $key = $isError ? 'error' : 'success';
    header("Location: ../maintenance_dashboard.php?{$key}=" . urlencode((string)$message) . "#enterpriseCapacityRequests");
    exit();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    enterprise_capacity_review_redirect("Invalid request method.", true);
}

$requestId = (int)($_POST['request_id'] ?? 0);
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
$reviewerNote = trim((string)($_POST['reviewer_note'] ?? ''));

if ($requestId <= 0 || !in_array($decision, ['approved', 'declined'], true)) {
    enterprise_capacity_review_redirect("Review request is incomplete.", true);
}

$csrfKey = trim((string)($_POST['csrf_key'] ?? ('enterprise_capacity_review_form_' . $requestId)));
$csrfKeyIsAllowed = preg_match('/^enterprise_capacity_review_form(_\d+)?$/', $csrfKey);
$csrfValid = $csrfKeyIsAllowed && csrf_verify($csrfKey, $_POST['csrf_token'] ?? null, true);

if (!$csrfValid) {
    $legacyValid = csrf_verify('enterprise_capacity_review_form', $_POST['csrf_token'] ?? null, true);
    if (!$legacyValid && !is_maintenance_script_allowed()) {
        enterprise_capacity_review_redirect("Invalid or expired request. Please refresh and try again.", true);
    }
}

if (!tenant_ensure_enterprise_capacity_requests_table($pdo)) {
    enterprise_capacity_review_redirect("Enterprise capacity request table is unavailable.", true);
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            ecr.id,
            ecr.organization_id,
            ecr.user_id,
            ecr.requested_seat_limit,
            ecr.status,
            o.name AS workspace_name,
            u.full_name AS owner_name,
            u.username AS owner_email
         FROM enterprise_capacity_requests ecr
         JOIN organizations o ON o.id = ecr.organization_id
         LEFT JOIN users u ON u.id = ecr.user_id
         WHERE ecr.id = ?
         LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$request) {
        enterprise_capacity_review_redirect("Enterprise capacity request was not found.", true);
    }

    if (strtolower((string)($request['status'] ?? '')) !== 'pending') {
        enterprise_capacity_review_redirect("This Enterprise capacity request has already been reviewed.", true);
    }

    $requestedSeatLimit = max(40, (int)($request['requested_seat_limit'] ?? 40));
    $orgId = (int)($request['organization_id'] ?? 0);

    $pdo->beginTransaction();

    if ($decision === 'approved') {
        $subStmt = $pdo->prepare(
            "UPDATE subscriptions
             SET seat_limit = ?
             WHERE organization_id = ?"
        );
        $subStmt->execute([$requestedSeatLimit, $orgId]);
    }

    $reviewStmt = $pdo->prepare(
        "UPDATE enterprise_capacity_requests
         SET status = ?, reviewer_note = ?, reviewed_at = NOW(), updated_at = NOW()
         WHERE id = ?"
    );
    $reviewStmt->execute([$decision, $reviewerNote !== '' ? $reviewerNote : null, $requestId]);

    $pdo->commit();

    $ownerEmail = trim((string)($request['owner_email'] ?? ''));
    if ($ownerEmail === '') {
        $contact = tenant_get_workspace_owner_contact($pdo, $orgId);
        $ownerEmail = trim((string)($contact['email'] ?? ''));
        $request['owner_name'] = trim((string)($contact['full_name'] ?? ($request['owner_name'] ?? '')));
    }

    $emailSent = false;
    if ($ownerEmail !== '') {
        $emailSent = send_enterprise_capacity_decision_email(
            $ownerEmail,
            (string)($request['owner_name'] ?? 'Workspace Owner'),
            (string)($request['workspace_name'] ?? 'Workspace'),
            $requestedSeatLimit,
            $decision,
            $reviewerNote
        );
    }

    $label = $decision === 'approved' ? 'approved' : 'declined';
    $emailSuffix = $emailSent ? " Owner email sent." : " Owner email was not sent; check mail configuration.";
    enterprise_capacity_review_redirect("Enterprise capacity request {$label}.{$emailSuffix}");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    enterprise_capacity_review_redirect("Unable to review Enterprise capacity request right now.", true);
}
