<?php
session_start();

if (!isset($_SESSION['role'], $_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=First login");
    exit();
}

include "DB_connection.php";
include "app/model/user.php";
require_once "inc/tenant.php";
require_once "inc/csrf.php";
require_once "inc/workspace_theme.php";
require_once "inc/workspace_screenshot_retention.php";
require_once "inc/workspace_screenshot_interval.php";

function ws_format_short_date($value)
{
    if (empty($value)) {
        return "N/A";
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return "N/A";
    }
    return date("M j, Y", $ts);
}

$isSuperAdmin = is_super_admin((int)$_SESSION['id'], $pdo);
$tenantEnabled = tenant_column_exists($pdo, 'users', 'organization_id') && tenant_table_exists($pdo, 'organizations');
$orgId = tenant_get_current_org_id();
$organizationRole = strtolower(trim((string)($_SESSION['organization_role'] ?? '')));
$canManageWorkspace = !$isSuperAdmin && ($organizationRole === '' || in_array($organizationRole, ['owner', 'admin'], true));

$error = null;
$org = null;
$capacity = null;
$memberCount = 0;
$seatUsed = 0;
$seatLimit = null;
$flashSuccess = isset($_GET['success']) ? trim((string)$_GET['success']) : null;
$flashError = isset($_GET['error']) ? trim((string)$_GET['error']) : null;

if (!$tenantEnabled) {
    $error = "Workspace settings are unavailable until tenant migration is enabled.";
} elseif (!$orgId) {
    $error = "Workspace context is missing. Please log in again.";
} else {
    try {
        $stmtOrg = $pdo->prepare(
            "SELECT id, name, slug, status, plan_code, billing_email, created_at
             FROM organizations
             WHERE id = ?
             LIMIT 1"
        );
        $stmtOrg->execute([(int)$orgId]);
        $org = $stmtOrg->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$org) {
            $error = "Workspace was not found.";
        } else {
            $capacity = tenant_check_workspace_capacity($pdo, (int)$orgId);
            $seatUsed = (int)($capacity['seat_used'] ?? 0);
            $seatLimit = isset($capacity['seat_limit']) ? (int)$capacity['seat_limit'] : null;

            if (tenant_table_exists($pdo, 'organization_members')) {
                $stmtMembers = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM organization_members
                     WHERE organization_id = ?"
                );
                $stmtMembers->execute([(int)$orgId]);
                $memberCount = (int)$stmtMembers->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        $error = "Unable to load workspace settings right now.";
    }
}

$themeDefaults = workspace_theme_default_palette();
$themeReady = $tenantEnabled && workspace_theme_schema_ready($pdo);
$themeModeReady = $tenantEnabled && workspace_theme_mode_schema_ready($pdo);
$themePrimary = $themeDefaults['primary'];
$themeSecondary = $themeDefaults['secondary'];
$themeAccent = $themeDefaults['accent'];
$themeMode = workspace_theme_default_mode();
$themeHasCustom = false;
$retentionReady = $tenantEnabled && workspace_screenshot_retention_schema_ready($pdo);
$screenshotRetentionDays = workspace_screenshot_retention_default_days();
$intervalReady = $tenantEnabled && workspace_screenshot_interval_schema_ready($pdo);
$screenshotInterval = workspace_screenshot_interval_default_config();

if ($themeReady && $orgId) {
    $themeValues = workspace_theme_fetch($pdo, $orgId);
    if ($themeValues) {
        if (!empty($themeValues['primary'])) {
            $themePrimary = $themeValues['primary'];
            $themeHasCustom = true;
        }
        if (!empty($themeValues['secondary'])) {
            $themeSecondary = $themeValues['secondary'];
            $themeHasCustom = true;
        }
        if (!empty($themeValues['accent'])) {
            $themeAccent = $themeValues['accent'];
            $themeHasCustom = true;
        }
        if (($themeValues['mode'] ?? workspace_theme_default_mode()) !== workspace_theme_default_mode()) {
            $themeMode = workspace_theme_resolve_mode($themeValues['mode'] ?? workspace_theme_default_mode());
            $themeHasCustom = true;
        }
    }
}

if ($orgId) {
    $screenshotRetentionDays = workspace_screenshot_retention_fetch_days($pdo, $orgId);
    $screenshotInterval = workspace_screenshot_interval_fetch_minutes($pdo, $orgId);
}

$themeAccentLight = workspace_theme_mix_hex($themeAccent, '#ffffff', 0.86) ?: $themeDefaults['accent'];
$themePalettes = workspace_theme_preset_palettes();
$workspaceDisplayName = (string)($org['name'] ?? ($_SESSION['organization_name'] ?? 'Workspace'));
$workspaceSlug = (string)($org['slug'] ?? 'N/A');
$workspaceCreatedShort = ws_format_short_date($org['created_at'] ?? null);
$subscriptionStatus = strtoupper((string)($capacity['subscription_status'] ?? 'N/A'));
$seatUsageDisplay = $seatLimit !== null ? ($seatUsed . "/" . $seatLimit) : (string)$seatUsed;
$themeModeLabel = ucfirst($themeMode);
$themeStatusLabel = $themeHasCustom ? 'Custom' : 'Default';
$screenshotRetentionLabel = $screenshotRetentionDays . ' day' . ($screenshotRetentionDays === 1 ? '' : 's');
$defaultRetentionDays = workspace_screenshot_retention_default_days();
$defaultRetentionLabel = $defaultRetentionDays . ' day' . ($defaultRetentionDays === 1 ? '' : 's');
$retentionRangeLabel = workspace_screenshot_retention_min_days() . ' to ' . workspace_screenshot_retention_max_days() . ' days';
$screenshotIntervalMinMinutes = (int)($screenshotInterval['min_minutes'] ?? workspace_screenshot_interval_default_min_minutes());
$screenshotIntervalMaxMinutes = (int)($screenshotInterval['max_minutes'] ?? workspace_screenshot_interval_default_max_minutes());
$screenshotIntervalLabel = $screenshotIntervalMinMinutes === $screenshotIntervalMaxMinutes
    ? ($screenshotIntervalMinMinutes . ' min fixed')
    : ($screenshotIntervalMinMinutes . '-' . $screenshotIntervalMaxMinutes . ' min random');
$defaultIntervalLabel = workspace_screenshot_interval_default_min_minutes() . '-' . workspace_screenshot_interval_default_max_minutes() . ' min random';
$intervalRangeLabel = workspace_screenshot_interval_min_allowed_minutes() . ' to ' . workspace_screenshot_interval_max_allowed_minutes() . ' minutes';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Settings | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/workspace-panels.css">
</head>
<body class="billing-page settings-page">
<?php include "inc/new_sidebar.php"; ?>

<div class="dash-main">
    <div class="workspace-shell workspace-animate">
        <section class="workspace-hero">
            <div>
                <span class="workspace-eyebrow">
                    <i class="fa fa-cog"></i> Workspace Settings
                </span>
                <h2>Manage your workspace identity and look from one place.</h2>
                <p>Update the workspace name shown across your admin screens, tune the workspace theme, and control screenshot retention and capture timing without changing billing or member data.</p>
            </div>
            <div class="workspace-hero-stats">
                <div class="workspace-hero-stat">
                    <span>Workspace</span>
                    <strong><?= htmlspecialchars($workspaceDisplayName) ?></strong>
                </div>
                <div class="workspace-hero-stat">
                    <span>Theme</span>
                    <strong><?= htmlspecialchars($themeStatusLabel) ?></strong>
                    <small><?= htmlspecialchars($themeModeLabel) ?> mode</small>
                </div>
                <div class="workspace-hero-stat">
                    <span>Members</span>
                    <strong><?= (int)$memberCount ?></strong>
                    <small><?= htmlspecialchars($seatUsageDisplay) ?> seats used</small>
                </div>
                <div class="workspace-hero-stat">
                    <span>Subscription</span>
                    <strong><?= htmlspecialchars($subscriptionStatus) ?></strong>
                    <small>Created <?= htmlspecialchars($workspaceCreatedShort) ?></small>
                </div>
            </div>
        </section>

        <div class="workspace-alert-stack">
            <?php if ($flashError) { ?>
                <div class="workspace-alert error">
                    <i class="fa fa-exclamation-circle"></i>
                    <div><?= htmlspecialchars($flashError) ?></div>
                </div>
            <?php } ?>
            <?php if ($flashSuccess) { ?>
                <div class="workspace-alert success">
                    <i class="fa fa-check-circle"></i>
                    <div><?= htmlspecialchars($flashSuccess) ?></div>
                </div>
            <?php } ?>
        </div>

        <?php if ($error) { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <div>
                        <h3 class="workspace-panel-title">Settings Unavailable</h3>
                        <p class="workspace-panel-sub">Workspace settings could not be loaded for the current session.</p>
                    </div>
                </div>
                <div class="workspace-alert error">
                    <i class="fa fa-warning"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            </section>
        <?php } else { ?>
            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <div>
                        <h3 class="workspace-panel-title">Workspace Name</h3>
                        <p class="workspace-panel-sub">Change the name shown across invites and workspace pages. Your workspace slug stays unchanged.</p>
                    </div>
                    <?php if (!$canManageWorkspace) { ?>
                        <span class="workspace-pill soft">Read-only</span>
                    <?php } ?>
                </div>

                <form action="app/update-workspace-name.php" method="POST" class="workspace-form-grid two-col">
                    <?= csrf_field('workspace_name_form') ?>
                    <input type="hidden" name="redirect_to" value="workspace-settings.php">

                    <div class="workspace-field">
                        <label for="workspace_name">Workspace Name</label>
                        <input
                            type="text"
                            id="workspace_name"
                            name="workspace_name"
                            class="workspace-input"
                            value="<?= htmlspecialchars($workspaceDisplayName, ENT_QUOTES) ?>"
                            maxlength="80"
                            required
                            <?= $canManageWorkspace ? '' : 'disabled' ?>
                        >
                    </div>

                    <div class="workspace-field">
                        <label>Workspace Slug</label>
                        <div class="workspace-input"><?= htmlspecialchars($workspaceSlug) ?></div>
                    </div>

                    <div class="workspace-field">
                        <label>Created</label>
                        <div class="workspace-input"><?= htmlspecialchars($workspaceCreatedShort) ?></div>
                    </div>

                    <div class="workspace-field">
                        <label>Note</label>
                        <div class="workspace-input">The new name updates this workspace label only and leaves billing, seat limits, and routes untouched.</div>
                    </div>

                    <div class="workspace-action-row" style="grid-column: 1 / -1;">
                        <button class="workspace-btn primary" type="submit" <?= $canManageWorkspace ? '' : 'disabled' ?>>
                            <i class="fa fa-save"></i>
                            Save Workspace Name
                        </button>
                    </div>
                </form>

                <?php if (!$canManageWorkspace) { ?>
                    <div class="workspace-alert info">
                        <i class="fa fa-lock"></i>
                        <div>You currently have read-only access and cannot update the workspace name.</div>
                    </div>
                <?php } ?>
            </section>

            <section class="workspace-panel workspace-theme-card">
                <div class="workspace-panel-head">
                    <div>
                        <h3 class="workspace-panel-title">Workspace Theme</h3>
                        <p class="workspace-panel-sub">Customize your workspace UI palette and switch between light and dark workspace modes.</p>
                    </div>
                    <?php if (!$canManageWorkspace) { ?>
                        <span class="workspace-pill soft">Read-only</span>
                    <?php } elseif ($themeHasCustom) { ?>
                        <span class="workspace-pill soft">Custom</span>
                    <?php } ?>
                </div>

                <?php if (!$themeReady) { ?>
                    <div class="workspace-alert warn">
                        <i class="fa fa-warning"></i>
                        <div>Theme customization requires the workspace theme columns. Run <span class="workspace-inline-code">sql_add_workspace_theme.sql</span> to enable it.</div>
                    </div>
                <?php } else { ?>
                    <div class="workspace-theme-preview">
                        <div class="workspace-theme-swatch" id="themePreviewPrimary" style="background: <?= htmlspecialchars($themePrimary, ENT_QUOTES) ?>"></div>
                        <div class="workspace-theme-swatch" id="themePreviewSecondary" style="background: <?= htmlspecialchars($themeSecondary, ENT_QUOTES) ?>"></div>
                        <div class="workspace-theme-swatch" id="themePreviewAccent" style="background: <?= htmlspecialchars($themeAccent, ENT_QUOTES) ?>"></div>
                        <div class="workspace-theme-gradient" id="themePreviewGradient" style="background: linear-gradient(135deg, <?= htmlspecialchars($themePrimary, ENT_QUOTES) ?> 0%, <?= htmlspecialchars($themeSecondary, ENT_QUOTES) ?> 100%);"></div>
                        <span class="workspace-theme-note">Applies immediately across this workspace.</span>
                    </div>

                    <?php if (!$themeModeReady) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-moon-o"></i>
                            <div>Dark mode needs the <span class="workspace-inline-code">theme_mode</span> column. Run <span class="workspace-inline-code">sql_add_workspace_theme_mode.sql</span> if your workspace already has the original theme columns.</div>
                        </div>
                    <?php } ?>

                    <div class="workspace-theme-palette-grid" id="workspaceThemePaletteGrid">
                        <?php foreach ($themePalettes as $palette) {
                            $pName = (string)($palette['name'] ?? 'Palette');
                            $pPrimary = (string)($palette['primary'] ?? '');
                            $pSecondary = (string)($palette['secondary'] ?? '');
                            $pAccent = (string)($palette['accent'] ?? '');
                            $pMode = workspace_theme_resolve_mode($palette['mode'] ?? workspace_theme_default_mode());
                        ?>
                            <button
                                type="button"
                                class="workspace-theme-palette"
                                data-primary="<?= htmlspecialchars($pPrimary, ENT_QUOTES) ?>"
                                data-secondary="<?= htmlspecialchars($pSecondary, ENT_QUOTES) ?>"
                                data-accent="<?= htmlspecialchars($pAccent, ENT_QUOTES) ?>"
                                data-mode="<?= htmlspecialchars($pMode, ENT_QUOTES) ?>"
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                                <span class="workspace-theme-palette-name"><?= htmlspecialchars($pName) ?></span>
                                <span class="workspace-theme-palette-swatches">
                                    <span class="workspace-theme-palette-swatch" style="background: <?= htmlspecialchars($pPrimary, ENT_QUOTES) ?>"></span>
                                    <span class="workspace-theme-palette-swatch" style="background: <?= htmlspecialchars($pSecondary, ENT_QUOTES) ?>"></span>
                                    <span class="workspace-theme-palette-swatch" style="background: <?= htmlspecialchars($pAccent, ENT_QUOTES) ?>"></span>
                                </span>
                            </button>
                        <?php } ?>
                    </div>

                    <form action="app/update-workspace-theme.php" method="POST" class="workspace-form-grid two-col">
                        <?= csrf_field('workspace_theme_form') ?>
                        <input type="hidden" name="redirect_to" value="workspace-settings.php">

                        <div class="workspace-field">
                            <label for="theme_primary">Primary Color</label>
                            <input
                                type="color"
                                id="theme_primary"
                                name="theme_primary"
                                class="workspace-input workspace-color-input"
                                value="<?= htmlspecialchars($themePrimary, ENT_QUOTES) ?>"
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="theme_secondary">Secondary Color</label>
                            <input
                                type="color"
                                id="theme_secondary"
                                name="theme_secondary"
                                class="workspace-input workspace-color-input"
                                value="<?= htmlspecialchars($themeSecondary, ENT_QUOTES) ?>"
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="theme_accent">Accent Color</label>
                            <input
                                type="color"
                                id="theme_accent"
                                name="theme_accent"
                                class="workspace-input workspace-color-input"
                                value="<?= htmlspecialchars($themeAccent, ENT_QUOTES) ?>"
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="theme_mode">Workspace Mode</label>
                            <select
                                id="theme_mode"
                                name="theme_mode"
                                class="workspace-input"
                                <?= ($canManageWorkspace && $themeModeReady) ? '' : 'disabled' ?>
                            >
                                <option value="light" <?= $themeMode === 'light' ? 'selected' : '' ?>>Light</option>
                                <option value="dark" <?= $themeMode === 'dark' ? 'selected' : '' ?>>Dark</option>
                            </select>
                        </div>

                        <div class="workspace-field">
                            <label>Accent Preview</label>
                            <div class="workspace-input" id="themePreviewAccentSoft" style="background: <?= htmlspecialchars($themeAccentLight, ENT_QUOTES) ?>; border-color: <?= htmlspecialchars($themeMode === 'dark' ? '#334155' : '#e5e7eb', ENT_QUOTES) ?>; height: 42px; padding: 0;"></div>
                        </div>

                        <div class="workspace-action-row" style="grid-column: 1 / -1;">
                            <button class="workspace-btn primary" type="submit" name="theme_action" value="save" <?= $canManageWorkspace ? '' : 'disabled' ?>>
                                <i class="fa fa-paint-brush"></i>
                                Save Theme
                            </button>
                            <button class="workspace-btn ghost" type="submit" name="theme_action" value="reset" <?= $canManageWorkspace ? '' : 'disabled' ?>>
                                Reset to Default
                            </button>
                        </div>
                    </form>

                    <?php if (!$canManageWorkspace) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-lock"></i>
                            <div>You currently have read-only access and cannot update the workspace theme.</div>
                        </div>
                    <?php } elseif (!$themeModeReady) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-database"></i>
                            <div>Color palettes are ready. Run <span class="workspace-inline-code">sql_add_workspace_theme_mode.sql</span> once to save dark mode selections too.</div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </section>

            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <div>
                        <h3 class="workspace-panel-title">Screenshot Retention</h3>
                        <p class="workspace-panel-sub">Control how long captured screenshots stay available before automatic cleanup removes them from this workspace.</p>
                    </div>
                    <?php if (!$canManageWorkspace) { ?>
                        <span class="workspace-pill soft">Read-only</span>
                    <?php } elseif ($retentionReady) { ?>
                        <span class="workspace-pill soft"><?= htmlspecialchars($screenshotRetentionLabel) ?></span>
                    <?php } ?>
                </div>

                <?php if (!$retentionReady) { ?>
                    <div class="workspace-alert warn">
                        <i class="fa fa-warning"></i>
                        <div>Screenshot retention settings require the workspace retention column. Run <span class="workspace-inline-code">sql_add_workspace_screenshot_retention.sql</span> to enable it.</div>
                    </div>
                <?php } else { ?>
                    <form action="app/update-workspace-screenshot-retention.php" method="POST" class="workspace-form-grid two-col">
                        <?= csrf_field('workspace_screenshot_retention_form') ?>
                        <input type="hidden" name="redirect_to" value="workspace-settings.php">

                        <div class="workspace-field">
                            <label for="screenshot_retention_days">Retention Window</label>
                            <input
                                type="number"
                                id="screenshot_retention_days"
                                name="screenshot_retention_days"
                                class="workspace-input"
                                value="<?= (int)$screenshotRetentionDays ?>"
                                min="<?= (int)workspace_screenshot_retention_min_days() ?>"
                                max="<?= (int)workspace_screenshot_retention_max_days() ?>"
                                required
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label>Automatic Cleanup</label>
                            <div class="workspace-input">Screenshots older than <?= htmlspecialchars($screenshotRetentionLabel) ?> are deleted automatically during normal app use, and they are also purged when new captures are saved or when this workspace's captures are opened.</div>
                        </div>

                        <div class="workspace-field">
                            <label>Allowed Range</label>
                            <div class="workspace-input"><?= htmlspecialchars($retentionRangeLabel) ?></div>
                        </div>

                        <div class="workspace-field">
                            <label>Default Retention</label>
                            <div class="workspace-input"><?= htmlspecialchars($defaultRetentionLabel) ?></div>
                        </div>

                        <div class="workspace-action-row" style="grid-column: 1 / -1;">
                            <button class="workspace-btn primary" type="submit" <?= $canManageWorkspace ? '' : 'disabled' ?>>
                                <i class="fa fa-save"></i>
                                Save Retention
                            </button>
                        </div>
                    </form>

                    <?php if (!$canManageWorkspace) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-lock"></i>
                            <div>You currently have read-only access and cannot update screenshot retention.</div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </section>

            <section class="workspace-panel">
                <div class="workspace-panel-head">
                    <div>
                        <h3 class="workspace-panel-title">Screenshot Timing</h3>
                        <p class="workspace-panel-sub">Choose the random capture window for this workspace. Use the same value in both fields if you want a fixed screenshot cadence.</p>
                    </div>
                    <?php if (!$canManageWorkspace) { ?>
                        <span class="workspace-pill soft">Read-only</span>
                    <?php } elseif ($intervalReady) { ?>
                        <span class="workspace-pill soft"><?= htmlspecialchars($screenshotIntervalLabel) ?></span>
                    <?php } ?>
                </div>

                <?php if (!$intervalReady) { ?>
                    <div class="workspace-alert warn">
                        <i class="fa fa-warning"></i>
                        <div>Screenshot timing settings require the workspace interval columns. Run <span class="workspace-inline-code">sql_add_workspace_screenshot_interval.sql</span> to enable them.</div>
                    </div>
                <?php } else { ?>
                    <form action="app/update-workspace-screenshot-interval.php" method="POST" class="workspace-form-grid two-col">
                        <?= csrf_field('workspace_screenshot_interval_form') ?>
                        <input type="hidden" name="redirect_to" value="workspace-settings.php">

                        <div class="workspace-field">
                            <label for="screenshot_interval_min_minutes">Minimum Interval</label>
                            <input
                                type="number"
                                id="screenshot_interval_min_minutes"
                                name="screenshot_interval_min_minutes"
                                class="workspace-input"
                                value="<?= (int)$screenshotIntervalMinMinutes ?>"
                                min="<?= (int)workspace_screenshot_interval_min_allowed_minutes() ?>"
                                max="<?= (int)workspace_screenshot_interval_max_allowed_minutes() ?>"
                                required
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label for="screenshot_interval_max_minutes">Maximum Interval</label>
                            <input
                                type="number"
                                id="screenshot_interval_max_minutes"
                                name="screenshot_interval_max_minutes"
                                class="workspace-input"
                                value="<?= (int)$screenshotIntervalMaxMinutes ?>"
                                min="<?= (int)workspace_screenshot_interval_min_allowed_minutes() ?>"
                                max="<?= (int)workspace_screenshot_interval_max_allowed_minutes() ?>"
                                required
                                <?= $canManageWorkspace ? '' : 'disabled' ?>
                            >
                        </div>

                        <div class="workspace-field">
                            <label>Capture Pattern</label>
                            <div class="workspace-input">While a user is clocked in, screenshots are captured at random times between <?= (int)$screenshotIntervalMinMinutes ?> and <?= (int)$screenshotIntervalMaxMinutes ?> minutes.</div>
                        </div>

                        <div class="workspace-field">
                            <label>Allowed Range</label>
                            <div class="workspace-input"><?= htmlspecialchars($intervalRangeLabel) ?></div>
                        </div>

                        <div class="workspace-field">
                            <label>Default Timing</label>
                            <div class="workspace-input"><?= htmlspecialchars($defaultIntervalLabel) ?></div>
                        </div>

                        <div class="workspace-field">
                            <label>Tip</label>
                            <div class="workspace-input">Set the minimum and maximum to the same value if you prefer a fixed interval instead of a random range.</div>
                        </div>

                        <div class="workspace-action-row" style="grid-column: 1 / -1;">
                            <button class="workspace-btn primary" type="submit" <?= $canManageWorkspace ? '' : 'disabled' ?>>
                                <i class="fa fa-save"></i>
                                Save Timing
                            </button>
                        </div>
                    </form>

                    <?php if (!$canManageWorkspace) { ?>
                        <div class="workspace-alert info">
                            <i class="fa fa-lock"></i>
                            <div>You currently have read-only access and cannot update screenshot timing.</div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
</div>
<script>
(function () {
    var primaryInput = document.getElementById('theme_primary');
    var secondaryInput = document.getElementById('theme_secondary');
    var accentInput = document.getElementById('theme_accent');
    var modeInput = document.getElementById('theme_mode');
    var previewPrimary = document.getElementById('themePreviewPrimary');
    var previewSecondary = document.getElementById('themePreviewSecondary');
    var previewAccent = document.getElementById('themePreviewAccent');
    var previewAccentSoft = document.getElementById('themePreviewAccentSoft');
    var previewGradient = document.getElementById('themePreviewGradient');
    var paletteButtons = Array.prototype.slice.call(document.querySelectorAll('.workspace-theme-palette'));

    if (!primaryInput || !secondaryInput || !accentInput) return;

    function toLowerHex(value) {
        return String(value || '').trim().toLowerCase();
    }

    function updatePreview() {
        var primary = primaryInput.value || '';
        var secondary = secondaryInput.value || '';
        var accent = accentInput.value || '';
        var mode = modeInput ? String(modeInput.value || 'light').toLowerCase() : 'light';

        if (previewPrimary) previewPrimary.style.background = primary;
        if (previewSecondary) previewSecondary.style.background = secondary;
        if (previewAccent) previewAccent.style.background = accent;
        if (previewGradient) {
            previewGradient.style.background = 'linear-gradient(135deg, ' + primary + ' 0%, ' + secondary + ' 100%)';
        }

        if (previewAccentSoft) {
            previewAccentSoft.style.background = accent ? accent + '22' : '';
            previewAccentSoft.style.borderColor = mode === 'dark' ? '#334155' : '#e5e7eb';
        }
    }

    function setActivePalette(activeBtn) {
        paletteButtons.forEach(function (btn) {
            if (btn === activeBtn) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });
    }

    function syncActivePalette() {
        var current = {
            primary: toLowerHex(primaryInput.value),
            secondary: toLowerHex(secondaryInput.value),
            accent: toLowerHex(accentInput.value),
            mode: modeInput ? String(modeInput.value || 'light').toLowerCase() : 'light'
        };

        var matched = false;
        paletteButtons.forEach(function (btn) {
            var matches = toLowerHex(btn.getAttribute('data-primary')) === current.primary
                && toLowerHex(btn.getAttribute('data-secondary')) === current.secondary
                && toLowerHex(btn.getAttribute('data-accent')) === current.accent
                && (!modeInput || modeInput.disabled || String(btn.getAttribute('data-mode') || 'light').toLowerCase() === current.mode);
            if (matches && !matched) {
                setActivePalette(btn);
                matched = true;
            } else if (!matches) {
                btn.classList.remove('is-active');
            }
        });
        if (!matched) {
            setActivePalette(null);
        }
    }

    paletteButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nextPrimary = btn.getAttribute('data-primary') || '';
            var nextSecondary = btn.getAttribute('data-secondary') || '';
            var nextAccent = btn.getAttribute('data-accent') || '';
            var nextMode = btn.getAttribute('data-mode') || 'light';
            primaryInput.value = nextPrimary;
            secondaryInput.value = nextSecondary;
            accentInput.value = nextAccent;
            if (modeInput && !modeInput.disabled) {
                modeInput.value = nextMode;
            }
            setActivePalette(btn);
            updatePreview();
        });
    });

    [primaryInput, secondaryInput, accentInput, modeInput].forEach(function (input) {
        if (!input) return;
        input.addEventListener('input', function () {
            updatePreview();
            syncActivePalette();
        });
    });

    updatePreview();
    syncActivePalette();
})();
</script>
</body>
</html>
