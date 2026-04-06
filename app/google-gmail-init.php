<?php
session_start();

require_once "../DB_connection.php";
require_once "helpers/google_gmail.php";
require_once "model/GoogleWorkspace.php";

function google_gmail_init_redirect($message = '', $isError = false, $openComposer = true)
{
    $params = [];
    if ($openComposer) {
        $params[] = 'open_formal_email=1';
    }

    if (trim((string)$message) !== '') {
        $params[] = ($isError ? 'gmail_error=' : 'gmail_status=') . urlencode((string)$message);
    }

    $target = "../messages.php";
    if (!empty($params)) {
        $target .= '?' . implode('&', $params);
    }

    header("Location: " . $target);
    exit();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: ../login.php?error=" . urlencode("First login"));
    exit();
}

if ((string)$_SESSION['role'] !== 'admin') {
    google_gmail_init_redirect('Only admins can send formal emails.', true, false);
}

if (!google_gmail_is_enabled()) {
    google_gmail_init_redirect('Google Gmail integration is not configured yet.', true);
}

$currentUserId = (int)$_SESSION['id'];
$existingToken = google_workspace_get_token_record($pdo, $currentUserId);
$hasGmailScope = google_workspace_scope_contains((string)($existingToken['scope'] ?? ''), google_gmail_required_scope());
$forceConsent = !$existingToken || !$hasGmailScope;

if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
    $authUrl = google_gmail_build_auth_url('debug-state', $forceConsent);
    $query = [];
    parse_str((string)parse_url($authUrl, PHP_URL_QUERY), $query);

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'app_url' => APP_URL,
        'redirect_uri' => (string)($query['redirect_uri'] ?? ''),
        'client_id' => (string)($query['client_id'] ?? ''),
        'scope' => (string)($query['scope'] ?? ''),
        'prompt' => (string)($query['prompt'] ?? ''),
        'gmail_enabled' => true,
        'has_existing_token' => !empty($existingToken),
        'has_gmail_scope' => $hasGmailScope,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    $state = bin2hex(random_bytes(16));
} catch (Throwable $e) {
    $state = hash('sha256', uniqid('google_gmail_', true) . microtime(true));
}

$_SESSION['pending_google_gmail'] = [
    'user_id' => $currentUserId,
    'state' => $state,
    'created_at' => time(),
    'action' => 'formal_email',
];

header("Location: " . google_gmail_build_auth_url($state, $forceConsent));
exit();
