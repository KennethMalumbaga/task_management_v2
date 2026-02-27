<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once "inc/csrf.php";
require_once "inc/tenant.php";

function checkout_redirect_login_error($message)
{
    header("Location: login.php?error=" . urlencode((string)$message));
    exit();
}

$pending = $_SESSION['post_signup_checkout'] ?? null;
$state = trim((string)($_GET['state'] ?? ''));

if (!is_array($pending) || empty($pending['state']) || $state === '' || !hash_equals((string)$pending['state'], $state)) {
    checkout_redirect_login_error("Your payment session is invalid or expired. Please sign up again.");
}

$createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
if ($createdAt > 0 && (time() - $createdAt) > 1800) {
    unset($_SESSION['post_signup_checkout']);
    checkout_redirect_login_error("Your payment session expired. Please sign up again.");
}

$planCatalog = tenant_workspace_plan_catalog();
$planCode = strtolower(trim((string)($pending['plan_code'] ?? 'starter')));
$selectedPlan = $planCatalog[$planCode] ?? tenant_resolve_workspace_plan($planCode, 'starter');
$planName = (string)($selectedPlan['name'] ?? ($pending['plan_name'] ?? 'Starter'));
$seatLimit = max(1, (int)($selectedPlan['seat_limit'] ?? ($pending['seat_limit'] ?? 10)));

$planPrices = [
    'starter' => 9,
    'professional' => 17,
    'enterprise' => 29,
];
$planPrice = (int)($planPrices[$planCode] ?? $planPrices['starter']);
$workspaceName = (string)($pending['workspace_name'] ?? 'Workspace');
$billingEmail = (string)($pending['billing_email'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Signup Payment | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-body">
<?php include "inc/toast.php"; ?>

<div class="auth-container">
    <div class="auth-left">
        <div class="auth-left-content">
            <h2>One more step to activate your workspace.</h2>
            <p>Finish this dummy checkout so your selected plan is activated before first login.</p>

            <div class="auth-feature-list">
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="fa fa-check-circle"></i></div>
                    <div class="auth-feature-text">
                        <h4>Workspace</h4>
                        <p><?= htmlspecialchars($workspaceName) ?></p>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="fa fa-cubes"></i></div>
                    <div class="auth-feature-text">
                        <h4>Selected Plan</h4>
                        <p><?= htmlspecialchars($planName) ?> (<?= $seatLimit ?> seats)</p>
                    </div>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i class="fa fa-credit-card"></i></div>
                    <div class="auth-feature-text">
                        <h4>Dummy Billing</h4>
                        <p>This is a temporary simulated checkout (no real charge).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-logos">
            <img src="img/logo.png" alt="Logo 1" class="auth-logo-img">
            <img src="img/logo2.png" alt="Logo 2" class="auth-logo-img">
        </div>
        <h3 class="auth-title">Complete Payment</h3>
        <p class="auth-subtitle">Activate your <?= htmlspecialchars($planName) ?> subscription</p>

        <?php if (isset($_GET['error'])) { ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars((string)$_GET['error']) ?>
            </div>
        <?php } ?>

        <div class="auth-info-box">
            <strong>Plan:</strong> <?= htmlspecialchars($planName) ?><br>
            <strong>Price:</strong> $<?= $planPrice ?>/month (dummy)<br>
            <strong>Seats:</strong> Up to <?= $seatLimit ?> members
            <?php if ($billingEmail !== '') { ?>
                <br><strong>Billing Email:</strong> <?= htmlspecialchars($billingEmail) ?>
            <?php } ?>
        </div>

        <form method="POST" action="app/process-post-signup-payment.php">
            <?= csrf_field('post_signup_dummy_payment_form') ?>
            <input type="hidden" name="state" value="<?= htmlspecialchars($state) ?>">

            <div class="form-group">
                <label class="form-label">Payment Method (Dummy)</label>
                <select class="form-control" name="payment_method" required>
                    <option value="card">Credit/Debit Card</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Reference (Optional)</label>
                <input type="text" class="form-control" name="reference_note" maxlength="80" placeholder="Example: OR-2026-001">
            </div>

            <button type="submit" class="btn-primary">Pay Now (Dummy)</button>
        </form>
    </div>
</div>
</body>
</html>

