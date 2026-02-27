<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

function workspace_status_should_return_to_dashboard(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    return isset($_GET['return_to']) && $_GET['return_to'] === 'maintenance_dashboard';
}

function workspace_status_redirect_to_dashboard(string $message, bool $isError = false): void
{
    $param = $isError ? 'error' : 'success';
    header("Location: maintenance_dashboard.php?{$param}=" . urlencode($message));
    exit();
}

$returnToDashboard = workspace_status_should_return_to_dashboard();

try {
    if (!tenant_table_exists($pdo, 'organizations')) {
        throw new RuntimeException("Organizations table is not available.");
    }

    $orgId = maintenance_get_requested_org_id();
    if ($orgId === null || $orgId <= 0) {
        throw new RuntimeException("org_id is required.");
    }
    if (!maintenance_org_exists($pdo, $orgId)) {
        throw new RuntimeException("Workspace not found.");
    }

    $requestedStatus = strtolower(
        trim((string)($_GET['set_status'] ?? maintenance_get_cli_option('set-status') ?? ''))
    );
    $allowedStatuses = ['active', 'inactive'];
    if ($requestedStatus !== '' && !in_array($requestedStatus, $allowedStatuses, true)) {
        throw new RuntimeException("Invalid set_status. Use 'active' or 'inactive'.");
    }

    $stmt = $pdo->prepare("SELECT name, status FROM organizations WHERE id = ? LIMIT 1");
    $stmt->execute([$orgId]);
    $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$workspace) {
        throw new RuntimeException("Workspace not found.");
    }

    $currentStatus = strtolower(trim((string)($workspace['status'] ?? 'inactive')));
    $nextStatus = $requestedStatus !== ''
        ? $requestedStatus
        : ($currentStatus === 'active' ? 'inactive' : 'active');

    if (!in_array($nextStatus, $allowedStatuses, true)) {
        $nextStatus = 'inactive';
    }

    if ($currentStatus !== $nextStatus) {
        $update = $pdo->prepare("UPDATE organizations SET status = ? WHERE id = ?");
        $update->execute([$nextStatus, $orgId]);
    }

    $workspaceName = trim((string)($workspace['name'] ?? ''));
    if ($workspaceName === '') {
        $workspaceName = "Workspace #{$orgId}";
    }
    $stateLabel = $nextStatus === 'active' ? 'ON' : 'OFF';
    $message = "{$workspaceName} is now {$stateLabel}.";

    if ($returnToDashboard) {
        workspace_status_redirect_to_dashboard($message);
    }

    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
    } else {
        echo "<h2 style='color: #166534;'>Success</h2>";
        echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<p><a href='maintenance_dashboard.php'>Back to dashboard</a></p>";
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage() ?: "Unable to update workspace status.";

    if ($returnToDashboard) {
        workspace_status_redirect_to_dashboard($errorMessage, true);
    }

    if (PHP_SAPI === 'cli') {
        echo "Error: {$errorMessage}" . PHP_EOL;
    } else {
        http_response_code(400);
        echo "<h2 style='color: #b91c1c;'>Error</h2>";
        echo "<p>" . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<p><a href='maintenance_dashboard.php'>Back to dashboard</a></p>";
    }
}
