<?php

if (!function_exists('workspace_theme_default_palette')) {
    function workspace_theme_default_palette(): array
    {
        return [
            'primary' => '#6c3ce1',
            'secondary' => '#8b5cf6',
            'accent' => '#6c3ef4',
        ];
    }
}

if (!function_exists('workspace_theme_default_mode')) {
    function workspace_theme_default_mode(): string
    {
        return 'light';
    }
}

if (!function_exists('workspace_theme_resolve_mode')) {
    function workspace_theme_resolve_mode($value): string
    {
        return strtolower(trim((string)$value)) === 'dark'
            ? 'dark'
            : workspace_theme_default_mode();
    }
}

if (!function_exists('workspace_theme_preset_palettes')) {
    function workspace_theme_preset_palettes(): array
    {
        return [
            [
                'name' => 'Classic Purple',
                'primary' => '#6c3ce1',
                'secondary' => '#8b5cf6',
                'accent' => '#6c3ef4',
                'mode' => 'light',
            ],
            [
                'name' => 'Indigo Night',
                'primary' => '#3730a3',
                'secondary' => '#4f46e5',
                'accent' => '#6366f1',
                'mode' => 'light',
            ],
            [
                'name' => 'Ocean Blue',
                'primary' => '#2563eb',
                'secondary' => '#3b82f6',
                'accent' => '#0ea5e9',
                'mode' => 'light',
            ],
            [
                'name' => 'Teal Harbor',
                'primary' => '#0f766e',
                'secondary' => '#14b8a6',
                'accent' => '#06b6d4',
                'mode' => 'light',
            ],
            [
                'name' => 'Emerald',
                'primary' => '#059669',
                'secondary' => '#10b981',
                'accent' => '#14b8a6',
                'mode' => 'light',
            ],
            [
                'name' => 'Forest Gold',
                'primary' => '#3f6212',
                'secondary' => '#65a30d',
                'accent' => '#eab308',
                'mode' => 'light',
            ],
            [
                'name' => 'Sunset',
                'primary' => '#ea580c',
                'secondary' => '#f97316',
                'accent' => '#f59e0b',
                'mode' => 'light',
            ],
            [
                'name' => 'Copper Clay',
                'primary' => '#9a3412',
                'secondary' => '#c2410c',
                'accent' => '#fb923c',
                'mode' => 'light',
            ],
            [
                'name' => 'Rose',
                'primary' => '#e11d48',
                'secondary' => '#f43f5e',
                'accent' => '#fb7185',
                'mode' => 'light',
            ],
            [
                'name' => 'Berry Punch',
                'primary' => '#be185d',
                'secondary' => '#db2777',
                'accent' => '#f472b6',
                'mode' => 'light',
            ],
            [
                'name' => 'Slate',
                'primary' => '#334155',
                'secondary' => '#64748b',
                'accent' => '#475569',
                'mode' => 'light',
            ],
            [
                'name' => 'Graphite Blue Dark',
                'primary' => '#2563eb',
                'secondary' => '#3b82f6',
                'accent' => '#60a5fa',
                'mode' => 'dark',
            ],
            [
                'name' => 'Midnight Mint Dark',
                'primary' => '#0f766e',
                'secondary' => '#14b8a6',
                'accent' => '#2dd4bf',
                'mode' => 'dark',
            ],
            [
                'name' => 'Ember Night Dark',
                'primary' => '#c2410c',
                'secondary' => '#ea580c',
                'accent' => '#fb923c',
                'mode' => 'dark',
            ],
        ];
    }
}

if (!function_exists('workspace_theme_normalize_hex')) {
    function workspace_theme_normalize_hex($value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        $raw = ltrim($raw, '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $raw)) {
            $raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
        } elseif (!preg_match('/^[0-9a-fA-F]{6}$/', $raw)) {
            return null;
        }
        return '#' . strtolower($raw);
    }
}

if (!function_exists('workspace_theme_mix_hex')) {
    function workspace_theme_mix_hex($baseHex, $mixHex, $ratio): ?string
    {
        $base = workspace_theme_normalize_hex($baseHex);
        $mix = workspace_theme_normalize_hex($mixHex);
        if ($base === null || $mix === null) {
            return null;
        }

        $ratio = is_numeric($ratio) ? (float)$ratio : 0.0;
        $ratio = max(0.0, min(1.0, $ratio));

        $baseRgb = [
            hexdec(substr($base, 1, 2)),
            hexdec(substr($base, 3, 2)),
            hexdec(substr($base, 5, 2)),
        ];
        $mixRgb = [
            hexdec(substr($mix, 1, 2)),
            hexdec(substr($mix, 3, 2)),
            hexdec(substr($mix, 5, 2)),
        ];

        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $out[$i] = (int)round(($baseRgb[$i] * (1 - $ratio)) + ($mixRgb[$i] * $ratio));
        }

        return sprintf('#%02x%02x%02x', $out[0], $out[1], $out[2]);
    }
}

if (!function_exists('workspace_theme_hex_to_rgb')) {
    function workspace_theme_hex_to_rgb($value): ?string
    {
        $hex = workspace_theme_normalize_hex($value);
        if ($hex === null) {
            return null;
        }
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        return $r . ', ' . $g . ', ' . $b;
    }
}

if (!function_exists('workspace_theme_schema_ready')) {
    function workspace_theme_schema_ready(PDO $pdo): bool
    {
        if (!function_exists('tenant_table_exists')) {
            require_once __DIR__ . '/tenant.php';
        }

        if (!tenant_table_exists($pdo, 'organizations')) {
            return false;
        }

        foreach (['theme_primary', 'theme_secondary', 'theme_accent'] as $col) {
            if (!tenant_column_exists($pdo, 'organizations', $col)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('workspace_theme_mode_schema_ready')) {
    function workspace_theme_mode_schema_ready(PDO $pdo): bool
    {
        if (!function_exists('tenant_table_exists')) {
            require_once __DIR__ . '/tenant.php';
        }

        return tenant_table_exists($pdo, 'organizations')
            && tenant_column_exists($pdo, 'organizations', 'theme_mode');
    }
}

if (!function_exists('workspace_theme_fetch')) {
    function workspace_theme_fetch(PDO $pdo, $orgId): ?array
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0 || !workspace_theme_schema_ready($pdo)) {
            return null;
        }

        $modeReady = workspace_theme_mode_schema_ready($pdo);
        $stmt = $pdo->prepare(
            "SELECT theme_primary, theme_secondary, theme_accent"
            . ($modeReady ? ", theme_mode" : "") .
            "
             FROM organizations
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'primary' => workspace_theme_normalize_hex($row['theme_primary'] ?? ''),
            'secondary' => workspace_theme_normalize_hex($row['theme_secondary'] ?? ''),
            'accent' => workspace_theme_normalize_hex($row['theme_accent'] ?? ''),
            'mode' => workspace_theme_resolve_mode($row['theme_mode'] ?? workspace_theme_default_mode()),
        ];
    }
}

if (!function_exists('workspace_theme_has_custom')) {
    function workspace_theme_has_custom(?array $theme): bool
    {
        if (!$theme) {
            return false;
        }
        return !empty($theme['primary'])
            || !empty($theme['secondary'])
            || !empty($theme['accent'])
            || workspace_theme_resolve_mode($theme['mode'] ?? '') !== workspace_theme_default_mode();
    }
}

if (!function_exists('workspace_theme_build_dark_css')) {
    function workspace_theme_build_dark_css(array $context): string
    {
        $primary = $context['primary'] ?? '#6c3ce1';
        $secondary = $context['secondary'] ?? '#8b5cf6';
        $primaryStrong = $context['primaryStrong'] ?? $secondary;
        $primaryMuted = $context['primaryMuted'] ?? $secondary;
        $primaryBorder = $context['primaryBorder'] ?? $secondary;
        $primaryRgb = $context['primaryRgb'] ?? '108, 60, 225';
        $accentRgb = $context['accentRgb'] ?? $primaryRgb;

        $darkBody = workspace_theme_mix_hex($primary, '#020617', 0.9) ?: '#0b1120';
        $darkBodyAlt = workspace_theme_mix_hex($primary, '#0f172a', 0.84) ?: '#111827';
        $darkSurface = workspace_theme_mix_hex($primary, '#111827', 0.84) ?: '#131b2a';
        $darkSurface2 = workspace_theme_mix_hex($primary, '#1f2937', 0.78) ?: '#1b2535';
        $darkSurface3 = workspace_theme_mix_hex($primary, '#0b1220', 0.72) ?: '#0f172a';
        $darkBorder = workspace_theme_mix_hex($primary, '#64748b', 0.72) ?: '#334155';
        $darkBorderSoft = workspace_theme_mix_hex($primary, '#475569', 0.8) ?: '#273449';
        $darkText = '#e5e7eb';
        $darkMuted = '#94a3b8';
        $darkSubtle = '#cbd5e1';

        $template = <<<'CSS'
body,
body.dashboard-page,
body.tasks-page,
body.timeline-page,
body.reports-page,
body.create-task-page,
body.invites-page,
body.billing-page {
    background:
        radial-gradient(860px 460px at 100% -120px, rgba({{primaryRgb}}, 0.24), transparent 62%),
        radial-gradient(760px 420px at -8% -120px, rgba({{accentRgb}}, 0.12), transparent 72%),
        {{darkBody}};
    color: {{darkText}};
    color-scheme: dark;
}

body::-webkit-scrollbar-track {
    background: {{darkSurface2}};
}

body::-webkit-scrollbar-thumb {
    background: {{darkBorder}};
}

.dash-sidebar {
    background: var(--sidebar-bg);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

.dash-brand h2 {
    color: #ffffff;
}

.dash-brand span {
    color: rgba(255, 255, 255, 0.72);
}

.dash-nav-section-label {
    color: rgba(255, 255, 255, 0.62);
}

.dash-nav-item {
    color: rgba(255, 255, 255, 0.82);
}

.dash-nav-item i {
    color: inherit;
}

.dash-nav-item:hover,
.dash-nav-item.active {
    background: var(--sidebar-active);
    color: #ffffff;
}

.dash-logout {
    color: #fda4af;
}

.dash-logout:hover {
    color: #fecdd3;
}

.dash-content-topbar,
.mobile-navbar {
    background: {{darkSurface}};
    border-bottom: 1px solid {{darkBorder}};
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.24);
}

.dash-content-topbar-title,
.dash-top-notif-title,
.dash-top-profile-name,
.mobile-brand-text h2,
.mobile-brand-text span {
    color: {{darkText}};
}

.dash-top-bell,
.dash-top-profile-trigger,
.mobile-icon-btn,
.mobile-msg-icon,
.mobile-profile-trigger,
.mobile-toggle-btn,
.mobile-close-btn {
    background: {{darkSurface2}};
    border-color: {{darkBorder}};
    color: {{darkText}};
}

.dash-top-bell:hover,
.mobile-icon-btn:hover,
.mobile-msg-icon:hover,
.mobile-toggle-btn:hover {
    background: {{darkSurface3}};
}

.dash-top-notif-dropdown,
.dash-top-profile-dropdown,
.mobile-top-notif-dropdown,
.mobile-top-profile-dropdown {
    background: {{darkSurface}};
    border: 1px solid {{darkBorder}};
    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.34);
}

.dash-top-notif-head,
.dash-top-notif-foot,
.dash-top-profile-head {
    border-color: {{darkBorder}};
}

.dash-top-notif-item {
    background: {{darkSurface}};
    border-bottom-color: {{darkBorderSoft}};
}

.dash-top-notif-item:hover,
.dash-top-profile-link:hover {
    background: {{darkSurface2}};
}

.dash-top-notif-item.unread,
.dash-top-profile-head {
    background: rgba({{primaryRgb}}, 0.14);
}

.dash-top-notif-type,
.dash-top-profile-link,
.dash-top-profile-name,
.dash-top-profile-head-link,
.dash-top-notif-foot a {
    color: {{darkText}};
}

.dash-top-notif-sub,
.dash-top-notif-msg,
.dash-top-notif-meta,
.dash-top-notif-empty,
.dash-top-profile-email {
    color: {{darkMuted}};
}

.dash-top-profile-role {
    background: rgba({{primaryRgb}}, 0.16);
    color: {{darkSubtle}};
}

#logoutConfirmModal > div,
#sharedIdleCheckModal > div {
    background: {{darkSurface}} !important;
    border: 1px solid {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.32) !important;
}

#logoutConfirmModal h3,
#sharedIdleCheckModal h3 {
    color: {{darkText}} !important;
}

#logoutConfirmModal p,
#sharedIdleCheckModal p {
    color: {{darkMuted}} !important;
}

#sharedIdleStayBtn {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
}

body.reports-page {
    --bg: {{darkBody}};
    --surface: {{darkSurface}};
    --surface-2: {{darkSurface2}};
    --border: {{darkBorder}};
    --border-subtle: {{darkBorderSoft}};
    --text-primary: {{darkText}};
    --text-secondary: {{darkMuted}};
    --text-muted: {{darkSubtle}};
}

body.reports-page .report-field input:focus,
body.reports-page .report-field select:focus {
    background: {{darkSurface3}};
}

body.reports-page .btn-primary:hover {
    background: {{primaryStrong}};
}

.captures-v2-page {
    --surface: {{darkSurface}};
    --s2: {{darkSurface2}};
    --s3: {{darkSurface3}};
    --border: {{darkBorder}};
    --tp: {{darkText}};
    --ts: {{darkMuted}};
    --tm: {{darkSubtle}};
    --green-bg: rgba(34, 197, 94, 0.14);
    --blue-bg: rgba(96, 165, 250, 0.16);
}

.captures-v2-page .sc,
.captures-v2-page .fbar,
.captures-v2-page .capture-folder-card,
.captures-v2-page .capture-folder-view,
.captures-v2-page .capture-card,
.captures-v2-page .capture-folder-grid.is-list,
.captures-v2-page .capture-folder-list.is-list {
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
}

.captures-v2-page .dash-content-topbar-title .captures-topbar-main {
    color: {{darkText}} !important;
}

.captures-v2-page .dash-content-topbar-title .captures-topbar-sep {
    color: {{darkMuted}} !important;
}

.captures-v2-page .dash-content-topbar-title .captures-topbar-crumb {
    color: {{primaryMuted}} !important;
}

.workspace-panel,
.workspace-theme-palette,
.workspace-table-wrap,
.invites-page .recent-invites-card,
.invites-page .invite-method-card,
.billing-page .billing-v2-card,
.billing-page .billing-v2-plan-card,
.billing-page .billing-v2-checkout-box,
.workspace-dummy-billing .workspace-panel,
.tasks-board,
body.tasks-page .task-card,
.tasks-empty-state,
body.create-task-page .create-task-form,
.messages-page-main .chat-layout,
.messages-page-main .chat-sidebar,
.messages-page-main .chat-main,
.messages-page-main .chat-list,
body.dashboard-page.role-admin .admin-card,
body.dashboard-page .dash-card,
body.dashboard-page .stat-card,
body.dashboard-page .bulletin-card,
body.dashboard-page .leaderboard-pane,
body.dashboard-page .welcome-card,
body.dashboard-page .employee-overview-card,
body.dashboard-page .dashboard-recent-board,
body.dashboard-page .employee-time-tracker-card,
body.dashboard-page .bulletin-modal,
body.timeline-page .tlp-loading,
body.timeline-page .tlp-stat-card,
body.timeline-page .tlp-filter-tabs,
body.timeline-page .tlp-empty,
body.timeline-page .tlp-tile,
body.timeline-page .tlp-workspace,
body.timeline-page .tlp-project-card,
body.timeline-page .tlp-member-card,
body.timeline-page .tlp-modal,
body.timeline-page .tlp-phase-guide,
body.timeline-page .tlp-project-switch {
    background: {{darkSurface}};
    border-color: {{darkBorder}};
    color: {{darkText}};
}

.workspace-theme-palette:hover,
.workspace-input,
.workspace-btn.ghost,
.workspace-inline-code,
body .workspace-panel-sub code,
body.create-task-page .optional-tag,
body.create-task-page .toggle-label,
body.create-task-page input[type="text"],
body.create-task-page input[type="date"],
body.create-task-page textarea,
body.create-task-page select,
body.reports-page .report-field input,
body.reports-page .report-field select,
body.timeline-page .tlp-search-input,
body.timeline-page .tlp-input,
body.timeline-page .tlp-select,
body.timeline-page .tlp-textarea,
.messages-page-main .chat-search input,
.messages-page-main .chat-input-wrapper {
    background: {{darkSurface2}};
    border-color: {{darkBorder}};
    color: {{darkText}};
}

.workspace-btn.ghost:hover,
body .workspace-input:focus,
body.create-task-page input[type="text"]:focus,
body.create-task-page input[type="date"]:focus,
body.create-task-page textarea:focus,
body.create-task-page select:focus,
body.create-task-page .member-picker.open .search-input-wrap,
.messages-page-main .chat-search input:focus,
body.timeline-page .tlp-search-input:focus,
body.timeline-page .tlp-input:focus,
body.timeline-page .tlp-select:focus,
body.timeline-page .tlp-textarea:focus {
    background: {{darkSurface3}};
}

.workspace-panel-title,
.workspace-theme-palette-name,
.tasks-board-head h3,
body.tasks-page .task-title,
.task-leader-name,
.tasks-empty-state h3,
body.create-task-page .field-label,
body.dashboard-page.role-admin .admin-card-head h3,
body.dashboard-page.role-admin .admin-stat-value,
body.dashboard-page.role-admin .leaderboard-name,
body.dashboard-page.role-admin .admin-task-name,
body.dashboard-page .welcome-card h3,
body.dashboard-page .employee-overview-card h3,
body.dashboard-page .employee-time-title,
body.dashboard-page .stat-info h4,
body.dashboard-page .dashboard-recent-board .task-title,
body.dashboard-page .btitle,
.messages-page-main .chat-user-name,
.messages-page-main .chat-header-info h3,
.messages-page-main .message-user-name,
.messages-page-main .message-bubble-incoming,
.messages-page-main .chat-input-wrapper input,
body.timeline-page .tlp-stat-value,
body.timeline-page .tlp-empty strong,
body.timeline-page .tlp-tile-title,
body.timeline-page .tlp-project-name,
body.timeline-page .tlp-member-name,
body.timeline-page .tlp-modal,
body.reports-page .reports-title {
    color: {{darkText}};
}

.workspace-panel-sub,
.workspace-theme-note,
.workspace-empty,
.workspace-field label,
.tasks-board-head p,
.task-preview-text,
.task-member-count,
.task-due-meta,
.tasks-empty-state p,
body.create-task-page .field-help,
body.create-task-page .optional-tag,
body.create-task-page input[type="text"]::placeholder,
body.create-task-page textarea::placeholder,
body.dashboard-page.role-admin .admin-card-subtitle,
body.dashboard-page.role-admin .admin-stat-label,
body.dashboard-page.role-admin .leaderboard-meta,
body.dashboard-page.role-admin .admin-task-meta,
body.dashboard-page .employee-attendance-note,
body.dashboard-page .stat-info span,
body.dashboard-page .dashboard-task-desc,
body.dashboard-page .bbody,
.messages-page-main .chat-user-role,
.messages-page-main .chat-item-last-msg,
.messages-page-main .chat-time,
.messages-page-main .chat-header-info span,
.messages-page-main .message-time,
.messages-page-main .no-chat-state p,
.messages-page-main .chat-search input::placeholder,
.messages-page-main .chat-search-icon,
.messages-page-main .btn-attach,
body.timeline-page .tlp-stat-label,
body.timeline-page .tlp-tile-sub,
body.timeline-page .tlp-meta-sub,
body.timeline-page .tlp-task-assignee,
body.timeline-page .tlp-phase-desc,
body.timeline-page .tlp-member-role,
body.reports-page .reports-subtitle {
    color: {{darkMuted}};
}

.workspace-table th,
.invites-page .recent-invites-table thead th {
    background: {{darkSurface2}};
    border-color: {{darkBorder}};
    color: {{darkSubtle}};
}

.workspace-table td,
.invites-page .recent-invites-table td,
.invites-page .recent-invites-table tbody tr,
body.dashboard-page.role-admin .leaderboard-item,
body.dashboard-page.role-admin .admin-task-item,
body.dashboard-page .admin-user-row,
body.dashboard-page .bpost,
body.tasks-page .task-footer,
body.timeline-page .tlp-leaderboard-item {
    border-color: {{darkBorderSoft}};
}

.workspace-table td,
.invites-page .recent-invites-table td,
body.dashboard-page .bpost,
body.timeline-page .tlp-phase-row,
body.timeline-page .tlp-day,
body.timeline-page .tlp-task-row {
    color: {{darkText}};
}

.workspace-table tbody tr:hover td,
.invites-page .recent-invites-table tbody tr:hover,
body.dashboard-page.role-admin .leaderboard-item:hover,
body.dashboard-page.role-admin .admin-task-item:hover,
body.dashboard-page .admin-user-row:hover,
body.dashboard-page .bpost:hover,
body.tasks-page .task-card:hover,
.messages-page-main .chat-item:hover,
body.timeline-page .tlp-tile:hover,
body.timeline-page .tlp-phase-row-main:hover {
    background: rgba({{primaryRgb}}, 0.08);
}

.task-leader-box,
body.dashboard-page .dashboard-leader-box,
body.dashboard-page .employee-attendance-note.is-active,
body.dashboard-page .employee-attendance-note.is-paused {
    background: rgba({{primaryRgb}}, 0.12);
    border-color: rgba({{primaryRgb}}, 0.2);
}

.task-leader-avatar,
.task-member-avatar,
body.dashboard-page .dashboard-team-avatar,
body.dashboard-page .dashboard-leader-avatar {
    border-color: {{darkSurface}};
    background: {{darkSurface2}};
}

body.create-task-page .progress-bar,
body.create-task-page .divider {
    background: {{darkSurface2}};
}

body.create-task-page .toggle-option input:checked + .toggle-label {
    background: rgba({{primaryRgb}}, 0.14);
}

.messages-page-main .chat-header,
.messages-page-main .chat-input-area,
.messages-page-main .attachment-preview {
    background: {{darkSurface}};
    border-color: {{darkBorder}};
}

.messages-page-main .message-bubble-incoming {
    background: {{darkSurface2}};
}

.messages-page-main .chat-item.active {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%);
    box-shadow: 0 12px 24px rgba({{primaryRgb}}, 0.32);
}

.messages-page-main .chat-item.active .message-badge {
    background: {{darkSurface}};
    color: {{primary}};
}

.messages-page-main .chat-list::-webkit-scrollbar-track,
.messages-page-main .chat-messages::-webkit-scrollbar-track,
.bulk-invite-hover-guide-scroll::-webkit-scrollbar-track {
    background: {{darkSurface2}};
}

.messages-page-main .chat-list::-webkit-scrollbar-thumb,
.messages-page-main .chat-messages::-webkit-scrollbar-thumb,
.bulk-invite-hover-guide-scroll::-webkit-scrollbar-thumb {
    background: {{primaryMuted}};
}

.messages-page-main .chat-list::-webkit-scrollbar-thumb:hover,
.messages-page-main .chat-messages::-webkit-scrollbar-thumb:hover,
.bulk-invite-hover-guide-scroll::-webkit-scrollbar-thumb:hover {
    background: {{secondary}};
}

body.dashboard-page.role-admin .admin-dashboard {
    --admin-border: {{darkBorder}};
    --admin-text: {{darkText}};
    --admin-muted: {{darkMuted}};
}

body.dashboard-page.role-admin .admin-user-detail-dialog {
    --admin-border: {{darkBorder}};
    --admin-text: {{darkText}};
    --admin-muted: {{darkMuted}};
    background: {{darkSurface}} !important;
    border: 1px solid {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-stat {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-capture-shell {
    background: linear-gradient(135deg, {{darkSurface2}}, {{darkSurface3}}) !important;
    border-color: {{darkBorder}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-capture-preview,
body.dashboard-page.role-admin .admin-user-detail-capture-preview.is-empty {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-label,
body.dashboard-page.role-admin .admin-user-detail-capture-empty,
body.dashboard-page.role-admin .admin-user-detail-capture-copy p {
    color: {{darkMuted}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-capture-empty i {
    color: {{darkSubtle}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-actions .admin-btn-ghost,
body.dashboard-page.role-admin .admin-clockout-actions .admin-btn-ghost,
body.dashboard-page.role-admin .admin-clockout-close {
    background: {{darkSurface3}} !important;
    border: 1px solid {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.dashboard-page.role-admin .admin-user-detail-actions .admin-btn-ghost:hover,
body.dashboard-page.role-admin .admin-clockout-actions .admin-btn-ghost:hover,
body.dashboard-page.role-admin .admin-clockout-close:hover {
    background: {{darkSurface2}} !important;
}

body.timeline-page .tlp-day-row,
body.timeline-page .tlp-label-col,
body.timeline-page .tlp-gantt-canvas,
body.timeline-page .tlp-progress-strip,
body.timeline-page .tlp-role-banner {
    background: {{darkSurface}};
    border-color: {{darkBorder}};
}

body.timeline-page .tlp-search-input,
body.timeline-page .tlp-input,
body.timeline-page .tlp-select,
body.timeline-page .tlp-textarea,
body.timeline-page .tlp-icon-choice,
body.timeline-page .tlp-color-choice {
    color: {{darkText}};
}

body.timeline-page .tlp-filter-btn,
body.timeline-page .tlp-view-link,
body.timeline-page .tlp-back-btn,
body.timeline-page .tlp-meta-name,
body.timeline-page .tlp-task-title,
body.timeline-page .tlp-phase-name,
body.timeline-page .tlp-phase-meta,
body.timeline-page .tlp-project-pill {
    color: {{darkText}};
}

body.timeline-page .tlp-icon-btn,
body.timeline-page .tlp-add-phase,
body.timeline-page .tlp-add-task-btn,
body.timeline-page .tlp-btn {
    background: {{darkSurface2}};
    border-color: {{darkBorder}};
    color: {{darkText}};
}

body.timeline-page .tlp-icon-btn:hover,
body.timeline-page .tlp-add-phase:hover,
body.timeline-page .tlp-add-task-btn:hover,
body.timeline-page .tlp-btn:hover {
    background: {{darkSurface3}};
}

body.billing-page .billing-v2-card,
body.billing-page .billing-v2-stat-card,
body.billing-page .billing-v2-plan-card,
body.billing-page .billing-v2-checkout-box,
body.billing-page .billing-v2-checkout-summary,
body.billing-page .billing-v2-how-note,
body.billing-page .billing-v2-meta-chip,
body.billing-page .billing-v2-readonly-pill {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.billing-page .billing-v2-stat-card.is-alert,
body.billing-page .billing-v2-checkout-box.is-urgent,
body.billing-page .billing-v2-how-note.is-warn,
body.billing-page .billing-v2-meta-chip.is-warn {
    background: rgba(127, 29, 29, 0.18) !important;
    border-color: rgba(248, 113, 113, 0.34) !important;
}

body.billing-page .billing-v2-how-note.is-ok,
body.billing-page .billing-v2-status-badge.is-active {
    background: rgba(21, 128, 61, 0.18) !important;
    border-color: rgba(74, 222, 128, 0.26) !important;
    color: #bbf7d0 !important;
}

body.billing-page .billing-v2-status-badge.is-trial,
body.billing-page .billing-v2-current-tag,
body.billing-page .billing-v2-checkout-badge,
body.billing-page .billing-v2-readonly-pill {
    background: rgba({{primaryRgb}}, 0.16) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkSubtle}} !important;
}

body.billing-page .billing-v2-checkout-badge.is-urgent,
body.billing-page .billing-v2-status-badge.is-expired {
    background: rgba(127, 29, 29, 0.2) !important;
    border-color: rgba(248, 113, 113, 0.34) !important;
    color: #fca5a5 !important;
}

body.billing-page .billing-v2-card .workspace-panel-title,
body.billing-page .billing-v2-stat-value,
body.billing-page .billing-v2-stat-value.compact,
body.billing-page .billing-v2-plan-head strong,
body.billing-page .billing-v2-plan-seats,
body.billing-page .billing-v2-plan-price,
body.billing-page .billing-v2-checkout-title,
body.billing-page .billing-v2-checkout-summary strong,
body.billing-page .billing-v2-meta-chip strong,
body.billing-page .billing-v2-how-list li,
body.billing-page .billing-v2-blocked-note,
body.billing-page .billing-v2-expired-title,
body.billing-page .billing-v2-expired-sub {
    color: {{darkText}} !important;
}

body.billing-page .billing-v2-card .workspace-panel-sub,
body.billing-page .billing-v2-stat-sub,
body.billing-page .billing-v2-plan-desc,
body.billing-page .billing-v2-plan-price span,
body.billing-page .billing-v2-checkout-sub,
body.billing-page .billing-v2-checkout-summary small,
body.billing-page .billing-v2-checkout-summary-label,
body.billing-page .billing-v2-checkout-form .workspace-field label,
body.billing-page .billing-v2-checkout-form .workspace-field label span,
body.billing-page .billing-v2-meta-chip,
body.billing-page .billing-v2-meta-label,
body.billing-page .billing-v2-how-note,
body.billing-page .billing-v2-readonly-pill,
body.billing-page .billing-v2-expired-sub {
    color: {{darkMuted}} !important;
}

body.billing-page .billing-v2-stat-label,
body.billing-page .billing-v2-hero-stat span,
body.billing-page .billing-v2-meta-icon {
    color: {{darkSubtle}} !important;
}

body.billing-page .billing-v2-stat-value span,
body.billing-page .billing-v2-value-muted,
body.billing-page .billing-v2-value-muted span,
body.billing-page .billing-v2-stat-icon.is-muted {
    color: {{darkMuted}} !important;
}

body.billing-page .billing-v2-stat-icon,
body.billing-page .billing-v2-meta-chip,
body.billing-page .billing-v2-checkout-summary,
body.billing-page .billing-v2-progress-track,
body.billing-page .billing-v2-plan-card.is-current {
    background: {{darkSurface2}} !important;
}

body.billing-page .billing-v2-stat-icon,
body.billing-page .billing-v2-meta-icon {
    color: {{darkSubtle}} !important;
}

body.billing-page .billing-v2-stat-icon.is-alert,
body.billing-page .billing-v2-stat-label.is-alert,
body.billing-page .billing-v2-stat-value.is-alert-date {
    color: #fca5a5 !important;
}

body.billing-page .billing-v2-progress-track.is-muted {
    background: {{darkBorderSoft}} !important;
}

body.billing-page .billing-v2-progress-bar.is-muted {
    background: {{darkBorder}} !important;
}

body.billing-page .billing-v2-checkout-form .workspace-input {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.billing-page .billing-v2-checkout-form .workspace-input:focus {
    background: {{darkSurface3}} !important;
    border-color: rgba({{primaryRgb}}, 0.38) !important;
    box-shadow: 0 0 0 3px rgba({{primaryRgb}}, 0.14) !important;
}

body.billing-page .billing-v2-plan-btn {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    border-color: transparent !important;
    color: #ffffff !important;
    box-shadow: 0 10px 24px rgba({{primaryRgb}}, 0.24) !important;
}

body.billing-page .billing-v2-plan-btn.is-current,
body.billing-page .billing-v2-plan-btn[disabled] {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
    box-shadow: none !important;
}

body.billing-page .billing-v2-plan-card:hover,
body.billing-page .billing-v2-stat-card:hover {
    background: {{darkSurface2}} !important;
    border-color: rgba({{primaryRgb}}, 0.32) !important;
}

body.groups-page .dash-main.page-wrap .card,
body.groups-page .dash-main.page-wrap .group-card,
body.groups-page .dash-main.page-wrap .member-dropdown-list,
body.groups-page .dash-main.page-wrap .custom-modal {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.2) !important;
}

body.groups-page .dash-main.page-wrap .page-title,
body.groups-page .dash-main.page-wrap .section-title,
body.groups-page .dash-main.page-wrap .group-name,
body.groups-page .dash-main.page-wrap .form-label,
body.groups-page .dash-main.page-wrap .custom-modal-header,
body.groups-page .dash-main.page-wrap .member-dropdown-list .user-item div[style*="color: #111827"] {
    color: {{darkText}} !important;
}

body.groups-page .dash-main.page-wrap .page-subtitle,
body.groups-page .dash-main.page-wrap .section-meta,
body.groups-page .dash-main.page-wrap .group-leader,
body.groups-page .dash-main.page-wrap .members-label,
body.groups-page .dash-main.page-wrap .created-at,
body.groups-page .dash-main.page-wrap .member-chip.more,
body.groups-page .dash-main.page-wrap .custom-modal-body,
body.groups-page .dash-main.page-wrap .search-groups i,
body.groups-page .dash-main.page-wrap .search-member-box i,
body.groups-page .dash-main.page-wrap .action-icon {
    color: {{darkMuted}} !important;
}

body.groups-page .dash-main.page-wrap .form-control,
body.groups-page .dash-main.page-wrap .select-leader-box,
body.groups-page .dash-main.page-wrap .search-groups input,
body.groups-page .dash-main.page-wrap .member-dropdown-list .form-control {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.groups-page .dash-main.page-wrap .select-leader-box.placeholder {
    color: {{darkMuted}} !important;
}

body.groups-page .dash-main.page-wrap .form-control::placeholder,
body.groups-page .dash-main.page-wrap .search-groups input::placeholder {
    color: {{darkMuted}} !important;
}

body.groups-page .dash-main.page-wrap .view-toggle {
    background: {{darkSurface2}} !important;
    border: 1px solid {{darkBorder}} !important;
}

body.groups-page .dash-main.page-wrap .toggle-btn {
    color: {{darkMuted}} !important;
}

body.groups-page .dash-main.page-wrap .toggle-btn.active {
    background: {{darkSurface}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18) !important;
}

body.groups-page .dash-main.page-wrap .group-card:hover,
body.groups-page .dash-main.page-wrap .user-item:hover {
    background: rgba({{primaryRgb}}, 0.08) !important;
    border-color: rgba({{primaryRgb}}, 0.26) !important;
}

body.groups-page .dash-main.page-wrap .view-grid .divider,
body.groups-page .dash-main.page-wrap .view-grid .card-bottom,
body.groups-page .dash-main.page-wrap .user-item {
    border-color: {{darkBorderSoft}} !important;
}

body.groups-page .dash-main.page-wrap .member-chip {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorderSoft}} !important;
    color: {{darkText}} !important;
}

body.groups-page .dash-main.page-wrap .member-chip.more {
    background: {{darkSurface3}} !important;
    color: {{darkSubtle}} !important;
}

body.groups-page .dash-main.page-wrap .member-avatar-xs,
body.groups-page .dash-main.page-wrap .member-avatar,
body.groups-page .dash-main.page-wrap .leader-avatar-sm,
body.groups-page .dash-main.page-wrap .user-avatar-sm {
    background: {{darkSurface3}} !important;
    border-color: {{darkSurface}} !important;
    color: {{darkSubtle}} !important;
}

body.groups-page .dash-main.page-wrap .user-avatar-sm span,
body.groups-page .dash-main.page-wrap .member-avatar-xs span {
    color: {{darkSubtle}} !important;
}

body.groups-page .dash-main.page-wrap .user-item.selected,
body.groups-page .dash-main.page-wrap .chip {
    background: rgba({{primaryRgb}}, 0.16) !important;
    color: {{darkSubtle}} !important;
}

body.groups-page .dash-main.page-wrap .chip span.close {
    color: {{darkSubtle}} !important;
}

body.groups-page .dash-main.page-wrap .groups-container::-webkit-scrollbar-track,
body.groups-page .dash-main.page-wrap .member-dropdown-list::-webkit-scrollbar-track {
    background: {{darkSurface2}};
}

body.groups-page .dash-main.page-wrap .groups-container::-webkit-scrollbar-thumb,
body.groups-page .dash-main.page-wrap .member-dropdown-list::-webkit-scrollbar-thumb {
    background: {{darkBorder}};
}

body.groups-page .dash-main.page-wrap .groups-container::-webkit-scrollbar-thumb:hover,
body.groups-page .dash-main.page-wrap .member-dropdown-list::-webkit-scrollbar-thumb:hover {
    background: {{primaryMuted}};
}

body.user-details-page .profile-header-section,
body.user-details-page .profile-content {
    color: {{darkText}};
}

body.user-details-page .profile-content > div:first-child > div {
    border: 1px solid {{darkBorder}} !important;
    box-shadow: none !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(1) {
    background: rgba(16, 185, 129, 0.16) !important;
    border-color: rgba(52, 211, 153, 0.28) !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(2),
body.user-details-page .profile-content > div:first-child > div:nth-child(3) {
    background: rgba(245, 158, 11, 0.14) !important;
    border-color: rgba(251, 191, 36, 0.26) !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(4) {
    background: rgba({{primaryRgb}}, 0.16) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(5) {
    background: rgba(59, 130, 246, 0.16) !important;
    border-color: rgba(96, 165, 250, 0.28) !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(1) i,
body.user-details-page .profile-content > div:first-child > div:nth-child(1) h3,
body.user-details-page .profile-content > div:first-child > div:nth-child(1) span {
    color: #a7f3d0 !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(2) i,
body.user-details-page .profile-content > div:first-child > div:nth-child(2) h3,
body.user-details-page .profile-content > div:first-child > div:nth-child(2) span,
body.user-details-page .profile-content > div:first-child > div:nth-child(3) i,
body.user-details-page .profile-content > div:first-child > div:nth-child(3) h3,
body.user-details-page .profile-content > div:first-child > div:nth-child(3) span {
    color: #fcd34d !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(4) i,
body.user-details-page .profile-content > div:first-child > div:nth-child(4) h3,
body.user-details-page .profile-content > div:first-child > div:nth-child(4) span {
    color: #ddd6fe !important;
}

body.user-details-page .profile-content > div:first-child > div:nth-child(5) i,
body.user-details-page .profile-content > div:first-child > div:nth-child(5) h3,
body.user-details-page .profile-content > div:first-child > div:nth-child(5) span {
    color: #bfdbfe !important;
}

body.user-details-page .profile-field-group label,
body.user-details-page .profile-name-text,
body.user-details-page .task-title,
body.user-details-page h3[style*="color: var(--text-dark)"],
body.user-details-page div[style*="color: var(--text-dark)"],
body.user-details-page div[style*="color: #111827"] {
    color: {{darkText}} !important;
}

body.user-details-page .profile-role-text,
body.user-details-page .profile-field-group label i,
body.user-details-page .task-item p,
body.user-details-page .task-item > div:last-child,
body.user-details-page .profile-content p[style*="color: var(--text-gray)"],
body.user-details-page .profile-content div[style*="color: var(--text-gray)"],
body.user-details-page .profile-content span[style*="color: #6B7280"],
body.user-details-page .profile-content div[style*="color: #4B5563"] {
    color: {{darkMuted}} !important;
}

body.user-details-page .profile-field-group .field-value,
body.user-details-page .profile-content > div[style*="grid-template-columns: repeat(auto-fill"] > div,
body.user-details-page .task-item {
    background: {{darkSurface2}} !important;
    border: 1px solid {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.user-details-page .profile-field-group .field-value {
    padding: 12px 15px !important;
}

body.user-details-page .profile-content hr {
    border-top-color: {{darkBorder}} !important;
}

body.calendar-page .dash-main,
body.edit-profile-page .dash-main,
body.messages-page .dash-main,
body.profile-page .dash-main,
body.users-page .dash-main,
body.timeline-page .dash-main {
    background: transparent !important;
}

body.dashboard-page .stat-card,
body.dashboard-page .dashboard-recent-board,
body.dashboard-page .leaderboard-pane,
body.dashboard-page .employee-leaderboard-card,
body.dashboard-page .welcome-card,
body.dashboard-page .employee-overview-card {
    background: linear-gradient(160deg, {{darkSurface}} 0%, {{darkSurface2}} 100%) !important;
    border-color: {{darkBorder}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18) !important;
}

body.dashboard-page .leaderboard-item,
body.dashboard-page .employee-leaderboard-card .leaderboard-item,
body.dashboard-page .dashboard-recent-board .task-card,
body.dashboard-page .bpost,
body.dashboard-page .dashboard-empty-state,
body.dashboard-page .leaderboard-empty {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorderSoft}} !important;
    color: {{darkText}} !important;
}

body.dashboard-page .leaderboard-item:hover,
body.dashboard-page .employee-leaderboard-card .leaderboard-item:hover,
body.dashboard-page .dashboard-recent-board .task-card:hover,
body.dashboard-page .bpost:hover {
    background: rgba({{primaryRgb}}, 0.08) !important;
}

body.dashboard-page .bulletin-head,
body.dashboard-page .leaderboard-pane .leaderboard-header,
body.dashboard-page .employee-leaderboard-card .leaderboard-header,
body.dashboard-page .overview-divider {
    border-color: {{darkBorderSoft}} !important;
}

body.dashboard-page .bulletin-empty,
body.dashboard-page .dashboard-empty-state,
body.dashboard-page .leaderboard-empty {
    border: 1px dashed {{darkBorder}} !important;
    color: {{darkMuted}} !important;
}

body.dashboard-page .bulletin-title,
body.dashboard-page .btitle,
body.dashboard-page .leaderboard-name,
body.dashboard-page .leaderboard-rating,
body.dashboard-page .tasks-section-header h3,
body.dashboard-page .dashboard-empty-state h3,
body.dashboard-page .employee-time-title {
    color: {{darkText}} !important;
}

body.dashboard-page .stat-info h4,
body.dashboard-page .leaderboard-meta,
body.dashboard-page .bbody,
body.dashboard-page .btime,
body.dashboard-page .welcome-role,
body.dashboard-page .employee-attendance-note,
body.dashboard-page .dashboard-task-desc {
    color: {{darkMuted}} !important;
}

body.dashboard-page .leader-box-preview {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
}

body.dashboard-page .dashboard-leader-label {
    color: {{darkSubtle}} !important;
}

body.dashboard-page .dashboard-leader-name {
    color: {{darkText}} !important;
}

body.dashboard-page .dashboard-team-count {
    color: {{darkMuted}} !important;
}

body.dashboard-page .ctt-title,
body.dashboard-page .ctt-title-text,
body.dashboard-page .ctt-value,
body.dashboard-page .ctt-time-in-value {
    color: {{darkText}} !important;
}

body.dashboard-page .ctt-label,
body.dashboard-page .ctt-time-out,
body.dashboard-page .ctt-session-icon {
    color: {{darkMuted}} !important;
}

body.dashboard-page .ctt-camera,
body.dashboard-page .ctt-stat,
body.dashboard-page .ctt-box {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
    box-shadow: none !important;
}

body.dashboard-page .ctt-stat-alltime {
    background: linear-gradient(135deg, rgba({{primaryRgb}}, 0.14), {{darkSurface3}}) !important;
    border-color: rgba({{primaryRgb}}, 0.22) !important;
}

body.dashboard-page .ctt-stat-alltime .ctt-label {
    color: {{darkSubtle}} !important;
}

body.dashboard-page .ctt-stat-alltime .ctt-value {
    color: {{primaryMuted}} !important;
}

body.dashboard-page .employee-time-tracker-card.is-running .ctt-camera,
body.dashboard-page .employee-time-tracker-card.is-running .ctt-stat-today {
    background: rgba(34, 197, 94, 0.14) !important;
    border-color: rgba(74, 222, 128, 0.24) !important;
    color: #bbf7d0 !important;
}

body.dashboard-page .employee-time-tracker-card.is-paused .ctt-camera,
body.dashboard-page .employee-time-tracker-card.is-paused .ctt-stat-today {
    background: rgba(245, 158, 11, 0.14) !important;
    border-color: rgba(251, 191, 36, 0.24) !important;
    color: #fcd34d !important;
}

body.dashboard-page .employee-time-tracker-card.is-running .ctt-stat-today .ctt-label,
body.dashboard-page .employee-time-tracker-card.is-running .ctt-stat-today .ctt-value,
body.dashboard-page .employee-time-tracker-card.is-paused .ctt-stat-today .ctt-label,
body.dashboard-page .employee-time-tracker-card.is-paused .ctt-stat-today .ctt-value {
    color: inherit !important;
}

body.dashboard-page .ctt-time-label {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.22) !important;
    color: {{primaryMuted}} !important;
}

body.dashboard-page .dashboard-view-all-link {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.dashboard-page .dashboard-view-all-link:hover {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.24) !important;
    color: {{darkText}} !important;
}

body.dashboard-page .employee-attendance-box,
body.dashboard-page .employee-attendance-note {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.dashboard-page.role-admin .admin-stat-item {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    box-shadow: none !important;
}

body.dashboard-page.role-admin .admin-stat-item:hover {
    background: rgba({{primaryRgb}}, 0.12) !important;
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18) !important;
}

body.dashboard-page.role-admin .admin-stat-value {
    color: {{darkText}} !important;
}

body.dashboard-page.role-admin .admin-stat-label {
    color: {{darkMuted}} !important;
}

body.dashboard-page.role-admin .admin-stat-icon.is-purple,
body.dashboard-page.role-admin .admin-stat-icon.is-green {
    background: rgba({{primaryRgb}}, 0.16) !important;
    color: {{darkText}} !important;
}

body.dashboard-page .bulletin-modal,
body.dashboard-page .bulletin-delete-modal,
body.dashboard-page .clockin-setup-modal-dialog,
body.dashboard-page .pause-session-dialog,
body.dashboard-page .admin-clockout-dialog,
body.dashboard-page #adminClockOutNoticeModal > div,
body.dashboard-page #confirmModal > div,
body.dashboard-page #navClockInModal > div,
body.dashboard-page #autoClockOutModal > div,
body.dashboard-page #idleCheckModal > div {
    background: {{darkSurface}} !important;
    border: 1px solid {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.dashboard-page .bulletin-modal h3,
body.dashboard-page .bulletin-delete-text,
body.dashboard-page .clockin-setup-modal-title,
body.dashboard-page .pause-session-dialog h3,
body.dashboard-page .admin-clockout-dialog h3 {
    color: {{darkText}} !important;
}

body.dashboard-page #confirmModal h3[style*="color:#111827"],
body.dashboard-page #confirmModal h3[style*="color: #111827"],
body.dashboard-page #navClockInModal h3[style*="color:#111827"],
body.dashboard-page #navClockInModal h3[style*="color: #111827"],
body.dashboard-page #autoClockOutModal h3[style*="color:#111827"],
body.dashboard-page #autoClockOutModal h3[style*="color: #111827"],
body.dashboard-page #idleCheckModal h3[style*="color:#111827"],
body.dashboard-page #idleCheckModal h3[style*="color: #111827"],
body.dashboard-page #adminClockOutNoticeModal h3[style*="color:#111827"],
body.dashboard-page #adminClockOutNoticeModal h3[style*="color: #111827"],
body.dashboard-page #adminClockOutNoticeModal #adminClockOutNoticeRemark[style*="color:#111827"],
body.dashboard-page #adminClockOutNoticeModal #adminClockOutNoticeRemark[style*="color: #111827"] {
    color: {{darkText}} !important;
}

body.dashboard-page .bulletin-modal label,
body.dashboard-page .clockin-setup-modal-copy,
body.dashboard-page .pause-session-dialog p,
body.dashboard-page .admin-clockout-dialog p {
    color: {{darkMuted}} !important;
}

body.dashboard-page .admin-clockout-label,
body.dashboard-page .admin-clockout-kicker {
    color: {{darkSubtle}} !important;
}

body.dashboard-page .admin-clockout-help {
    color: {{darkMuted}} !important;
}

body.dashboard-page .admin-clockout-textarea {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    caret-color: {{darkText}} !important;
    box-shadow: none !important;
}

body.dashboard-page .admin-clockout-textarea::placeholder {
    color: {{darkMuted}} !important;
}

body.dashboard-page .admin-clockout-textarea:focus {
    background: {{darkSurface2}} !important;
    border-color: rgba({{primaryRgb}}, 0.42) !important;
    box-shadow: 0 0 0 3px rgba({{primaryRgb}}, 0.16) !important;
}

body.dashboard-page #confirmModal p[style*="color:#6B7280"],
body.dashboard-page #confirmModal p[style*="color: #6B7280"],
body.dashboard-page #navClockInModal p[style*="color:#6B7280"],
body.dashboard-page #navClockInModal p[style*="color: #6B7280"],
body.dashboard-page #autoClockOutModal p[style*="color:#6B7280"],
body.dashboard-page #autoClockOutModal p[style*="color: #6B7280"],
body.dashboard-page #idleCheckModal p[style*="color:#6B7280"],
body.dashboard-page #idleCheckModal p[style*="color: #6B7280"],
body.dashboard-page #adminClockOutNoticeModal p[style*="color:#6B7280"],
body.dashboard-page #adminClockOutNoticeModal p[style*="color: #6B7280"],
body.dashboard-page #adminClockOutNoticeModal div[style*="color:#6B7280"],
body.dashboard-page #adminClockOutNoticeModal div[style*="color: #6B7280"] {
    color: {{darkMuted}} !important;
}

body.dashboard-page #adminClockOutNoticeModal > div > div[style*="background:#FEE2E2"],
body.dashboard-page #adminClockOutNoticeModal > div > div[style*="background: #FEE2E2"] {
    background: rgba(127, 29, 29, 0.24) !important;
    color: #fca5a5 !important;
}

body.dashboard-page #adminClockOutNoticeModal > div > div[style*="background:#F9FAFB"],
body.dashboard-page #adminClockOutNoticeModal > div > div[style*="background: #F9FAFB"] {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
}

body.dashboard-page #adminClockOutNoticeModal > div > div[style*="text-transform:uppercase"],
body.dashboard-page #adminClockOutNoticeModal > div > div[style*="text-transform: uppercase"] {
    color: {{darkSubtle}} !important;
}

body.dashboard-page #adminClockOutNoticeModal button {
    background: #ef4444 !important;
    color: #ffffff !important;
    border: none !important;
    box-shadow: none !important;
}

body.dashboard-page #adminClockOutNoticeModal button:hover {
    background: #dc2626 !important;
}

body.dashboard-page #confirmModal button[style*="background:#F3F4F6"],
body.dashboard-page #confirmModal button[style*="background: #F3F4F6"],
body.dashboard-page #navClockInModal button[style*="background:#F3F4F6"],
body.dashboard-page #navClockInModal button[style*="background: #F3F4F6"] {
    background: {{darkSurface3}} !important;
    color: {{darkSubtle}} !important;
    border: 1px solid {{darkBorder}} !important;
}

body.dashboard-page .bulletin-modal select,
body.dashboard-page .bulletin-modal input,
body.dashboard-page .bulletin-modal textarea {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.dashboard-page .bulletin-btn-cancel {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.dashboard-page .pause-session-quick-btn,
body.dashboard-page .pause-session-textarea,
body.dashboard-page .pause-session-cancel,
body.dashboard-page .pause-session-quick-check {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: none !important;
}

body.dashboard-page .pause-session-quick-btn.is-active {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.18) !important;
}

body.dashboard-page .pause-session-quick-icon {
    background: rgba({{primaryRgb}}, 0.18) !important;
    color: {{darkSubtle}} !important;
}

body.dashboard-page .pause-session-quick-copy strong {
    color: {{darkText}} !important;
}

body.dashboard-page .pause-session-quick-copy small,
body.dashboard-page .pause-session-section-label,
body.dashboard-page .pause-session-divider strong {
    color: {{darkMuted}} !important;
}

body.dashboard-page .pause-session-quick-btn.is-active .pause-session-quick-check {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: #ffffff !important;
}

body.dashboard-page .pause-session-divider span {
    background: {{darkBorderSoft}} !important;
}

body.dashboard-page .pause-session-textarea:focus {
    background: {{darkSurface3}} !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
}

body.dashboard-page .pause-session-textarea:disabled {
    background: {{darkSurface3}} !important;
    color: {{darkMuted}} !important;
}

body.dashboard-page .pause-session-cancel:hover {
    background: {{darkSurface3}} !important;
}

body.dashboard-page .pause-session-confirm {
    background: {{darkSurface3}} !important;
    color: {{darkMuted}} !important;
}

body.dashboard-page .pause-session-confirm.is-enabled {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 10px 20px rgba({{primaryRgb}}, 0.24) !important;
}

body.tasks-page .task-card,
body.tasks-page .modal-content,
body.tasks-page .modal-box,
body.tasks-page .info-box,
body.tasks-page .leader-box,
body.tasks-page .subtask-card {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.22) !important;
}

body.tasks-page .info-box,
body.tasks-page .subtask-card,
body.tasks-page .modal-content [style*="background: #F0FDFA"],
body.tasks-page .modal-content [style*="background: #EFF6FF"],
body.tasks-page .modal-content [style*="background: white"],
body.tasks-page .modal-content [style*="background: #F8FAFC"] {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.tasks-page .modal-header-section,
body.tasks-page .task-footer,
body.tasks-page .modal-content [style*="border-top: 1px solid #E5E7EB"],
body.tasks-page .modal-content [style*="border-bottom: 1px solid #BFDBFE"] {
    border-color: {{darkBorderSoft}} !important;
}

body.tasks-page .section-label {
    color: {{darkSubtle}} !important;
}

body.tasks-page .modal-content h2[style*="color: #111827"],
body.tasks-page .modal-box h3[style*="color: #111827"],
body.tasks-page .modal-content div[style*="color: #1F2937"],
body.tasks-page .modal-content span[style*="color: #1F2937"],
body.tasks-page .subtask-card span[style*="color: #1F2937"] {
    color: {{darkText}} !important;
}

body.tasks-page .modal-content div[style*="color: #374151"],
body.tasks-page .modal-content div[style*="color: #6B7280"],
body.tasks-page .modal-content span[style*="color:#374151"],
body.tasks-page .modal-content span[style*="color:#6B7280"],
body.tasks-page .task-footer,
body.tasks-page .close-modal {
    color: {{darkMuted}} !important;
}

body.tasks-page .admin-review-section {
    background: rgba(59, 130, 246, 0.14) !important;
    border-color: rgba(96, 165, 250, 0.28) !important;
}

body.tasks-page .admin-review-icon,
body.tasks-page .admin-review-title,
body.tasks-page .admin-review-text {
    color: #bfdbfe !important;
}

body.tasks-page .awaiting-review-section {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
}

body.tasks-page .leader-notes-box {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkMuted}} !important;
}

body.tasks-page .rating-feedback-box {
    background: rgba(16, 185, 129, 0.16) !important;
    border-color: rgba(52, 211, 153, 0.28) !important;
}

body.tasks-page .rating-header,
body.tasks-page .rating-feedback-text {
    color: #a7f3d0 !important;
}

body.tasks-page .btn-v2.btn-white {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.tasks-page .task-leader-box {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.tasks-page .task-leader-name {
    color: {{darkText}} !important;
}

body.tasks-page .task-leader-avatar,
body.tasks-page .task-member-avatar {
    border-color: {{darkSurface}} !important;
    background: {{darkSurface3}} !important;
}

body.tasks-page .task-member-count,
body.tasks-page .task-preview-text {
    color: {{darkMuted}} !important;
}

body.calendar-page .calendar-shell,
body.calendar-page .cal-board,
body.calendar-page .calendar-tasks,
body.calendar-page .cal-today-indicator {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.2) !important;
}

body.calendar-page .cal-month-title,
body.calendar-page .cal-task-name,
body.calendar-page .cal-task-side-label,
body.calendar-page .cal-tasks-head h3 {
    color: {{darkText}} !important;
}

body.calendar-page .cal-today-indicator {
    background: {{darkSurface2}} !important;
    color: {{darkSubtle}} !important;
}

body.calendar-page .cal-day {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorderSoft}} !important;
    color: {{darkText}} !important;
}

body.calendar-page .cal-day.is-outside {
    background: {{darkSurface3}} !important;
    color: {{darkMuted}} !important;
}

body.calendar-page .cal-day.is-outside:hover,
body.calendar-page .cal-day:not(.is-outside):hover {
    background: rgba({{primaryRgb}}, 0.08) !important;
}

body.calendar-page .cal-day.active {
    background: rgba({{primaryRgb}}, 0.14) !important;
    outline-color: {{primary}} !important;
}

body.calendar-page .cal-task-item {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    box-shadow: none !important;
}

body.calendar-page .cal-task-item.tone-success {
    background: rgba(16, 185, 129, 0.16) !important;
    border-color: rgba(52, 211, 153, 0.28) !important;
}

body.calendar-page .cal-task-item.tone-danger {
    background: rgba(239, 68, 68, 0.14) !important;
    border-color: rgba(248, 113, 113, 0.28) !important;
}

body.calendar-page .cal-task-item.tone-info {
    background: rgba(59, 130, 246, 0.14) !important;
    border-color: rgba(96, 165, 250, 0.28) !important;
}

body.calendar-page .cal-task-item.tone-review {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
}

body.calendar-page .cal-task-item.tone-neutral,
body.calendar-page .cal-empty-state {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.calendar-page .cal-task-assignee,
body.calendar-page .cal-task-desc,
body.calendar-page .cal-task-member-names,
body.calendar-page .cal-task-side-value,
body.calendar-page .cal-empty-state {
    color: {{darkMuted}} !important;
}

body.calendar-page .cal-task-member-avatar {
    background: {{darkSurface3}} !important;
    border-color: {{darkSurface}} !important;
    color: {{darkSubtle}} !important;
}

body.calendar-page .cal-task-member-more,
body.calendar-page .cal-chip.more {
    background: {{darkSurface3}} !important;
    color: {{darkSubtle}} !important;
}

body.reports-page .dtr-section,
body.reports-page .dtr-user-bar,
body.reports-page .dtr-stats,
body.reports-page .dtr-form-header,
body.reports-page .dtr-footer,
body.reports-page .user-tabs {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.reports-page .user-tab,
body.reports-page .month-nav-btn,
body.reports-page .dtr-pill {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.reports-page .user-tab.active {
    background: rgba({{primaryRgb}}, 0.16) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkText}} !important;
}

body.reports-page .month-nav-btn:hover {
    background: {{darkSurface3}} !important;
}

body.reports-page .dtr-company-name,
body.reports-page .dtr-form-title,
body.reports-page .dtr-meta-row span:first-child,
body.reports-page .dtr-meta-value,
body.reports-page .dtr-user-name,
body.reports-page .dtr-stat-value,
body.reports-page .dtr-total-value {
    color: {{darkText}} !important;
}

body.reports-page .dtr-company-address,
body.reports-page .dtr-user-dept,
body.reports-page .dtr-stat-label,
body.reports-page .dtr-total-label,
body.reports-page .dtr-date .day,
body.reports-page .time-cell.empty,
body.reports-page .dtr-editable:empty:before {
    color: {{darkMuted}} !important;
}

body.reports-page .dtr-editable {
    border-bottom-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.reports-page .dtr-table-wrap::-webkit-scrollbar-track,
body.reports-page .user-tabs::-webkit-scrollbar-track {
    background: {{darkSurface2}} !important;
}

body.reports-page .dtr-table-wrap::-webkit-scrollbar-thumb,
body.reports-page .user-tabs::-webkit-scrollbar-thumb {
    background: {{darkBorder}} !important;
}

body.reports-page table.dtr-table,
body.reports-page .report-dtr-table {
    border-color: {{darkBorderSoft}} !important;
}

body.reports-page table.dtr-table thead th,
body.reports-page .report-dtr-table th {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorderSoft}} !important;
    color: {{darkSubtle}} !important;
}

body.reports-page table.dtr-table td,
body.reports-page .report-dtr-table td {
    background: {{darkSurface}} !important;
    border-color: {{darkBorderSoft}} !important;
    color: {{darkText}} !important;
}

body.reports-page table.dtr-table tbody tr.weekend-row td {
    background: {{darkSurface2}} !important;
    color: {{darkMuted}} !important;
}

body.reports-page table.dtr-table tbody tr.active-row td {
    background: rgba({{primaryRgb}}, 0.08) !important;
}

body.reports-page table.dtr-table tbody tr.active-row td:first-child {
    border-left-color: {{primary}} !important;
}

body.reports-page .deduct-input,
body.reports-page .reason-input,
body.reports-page .dtr-adjust-input,
body.reports-page .dtr-reason-input {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.reports-page .deduct-input:focus,
body.reports-page .reason-input:focus,
body.reports-page .dtr-adjust-input:focus,
body.reports-page .dtr-reason-input:focus {
    background: {{darkSurface3}} !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
}

body.reports-page .deduct-input::placeholder,
body.reports-page .reason-input::placeholder,
body.reports-page .dtr-reason-input::placeholder {
    color: {{darkMuted}} !important;
}

body.reports-page .dtr-reason-cell {
    color: {{darkMuted}} !important;
}

body.invites-page .invite-method-card,
body.invites-page .recent-invites-card {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18) !important;
}

body.invites-page .invite-method-divider,
body.invites-page .recent-invites-head,
body.invites-page .recent-invites-footer,
body.invites-page .recent-invites-table thead th,
body.invites-page .recent-invites-table tbody tr,
body.invites-page .bulk-invite-hover-guide-table td {
    border-color: {{darkBorderSoft}} !important;
}

body.invites-page .recent-invites-filters {
    background: {{darkSurface2}} !important;
}

body.invites-page .invite-filter-btn {
    background: transparent !important;
    color: {{darkMuted}} !important;
}

body.invites-page .invite-filter-btn:hover {
    background: rgba({{primaryRgb}}, 0.08) !important;
    color: {{darkText}} !important;
}

body.invites-page .invite-filter-btn.is-active {
    background: {{darkSurface3}} !important;
    color: {{darkText}} !important;
    box-shadow: none !important;
}

body.invites-page .recent-invites-records {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkText}} !important;
}

body.invites-page .invite-method-card .workspace-panel-title,
body.invites-page .recent-invites-head .workspace-panel-title,
body.invites-page .invite-recipient-name,
body.invites-page .invite-expires-date,
body.invites-page .invite-link-text,
body.invites-page .invite-file-name,
body.invites-page .bulk-invite-hover-guide-step-title,
body.invites-page .bulk-invite-hover-guide-table td:first-child {
    color: {{darkText}} !important;
}

body.invites-page .invite-method-card .workspace-panel-sub,
body.invites-page .recent-invites-head .workspace-panel-sub,
body.invites-page .invite-recipient-email,
body.invites-page .invite-expires,
body.invites-page .invite-expires-time,
body.invites-page .recent-invites-range,
body.invites-page .invite-bulk-note,
body.invites-page .invite-link-placeholder-text,
body.invites-page .invite-dropzone-text,
body.invites-page .invite-dropzone-types,
body.invites-page .invite-file-status,
body.invites-page .bulk-invite-hover-guide-step-text,
body.invites-page .bulk-invite-hover-guide-table td:last-child,
body.invites-page .bulk-invite-hover-guide-divider span {
    color: {{darkMuted}} !important;
}

body.invites-page .invite-method-card .workspace-field label,
body.invites-page .recent-invites-table thead th {
    color: {{darkSubtle}} !important;
}

body.invites-page .invite-method-card .workspace-input,
body.invites-page .invite-link-box,
body.invites-page .invite-link-placeholder,
body.invites-page .invite-dropzone,
body.invites-page .invite-file-icon-wrap,
body.invites-page .invite-link-chip,
body.invites-page .invite-action-btn,
body.invites-page .invite-copy-link-btn,
body.invites-page .invite-template-btn,
body.invites-page .invite-page-btn,
body.invites-page .bulk-invite-hover-guide,
body.invites-page .bulk-invite-hover-guide-step-card,
body.invites-page .bulk-invite-hover-guide-sample,
body.invites-page .bulk-invite-hover-guide-table tbody tr:nth-child(odd) td,
body.invites-page .bulk-invite-hover-guide-table tbody tr:nth-child(even) td {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.invites-page .invite-method-card .workspace-input:focus {
    background: {{darkSurface3}} !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    box-shadow: 0 0 0 3px rgba({{primaryRgb}}, 0.12) !important;
}

body.invites-page .invite-method-card .workspace-input::placeholder {
    color: {{darkMuted}} !important;
}

body.invites-page .invite-submit-btn,
body.invites-page .invite-generate-btn {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    color: #ffffff !important;
}

body.invites-page .invite-copy-link-btn:hover,
body.invites-page .invite-template-btn:hover,
body.invites-page .invite-action-btn:hover,
body.invites-page .invite-page-btn:hover:not(.is-disabled):not(.is-active) {
    background: {{darkSurface3}} !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkText}} !important;
    box-shadow: none !important;
}

body.invites-page .invite-action-btn.revoke {
    background: rgba(239, 68, 68, 0.14) !important;
    border-color: rgba(248, 113, 113, 0.28) !important;
    color: #fecaca !important;
}

body.invites-page .invite-action-btn.revoke:hover {
    background: rgba(239, 68, 68, 0.18) !important;
    border-color: rgba(248, 113, 113, 0.34) !important;
    color: #fee2e2 !important;
}

body.invites-page .invite-page-btn.is-active {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: #ffffff !important;
    box-shadow: none !important;
}

body.invites-page .invite-page-btn.is-disabled {
    background: {{darkSurface3}} !important;
    color: {{darkMuted}} !important;
}

body.invites-page .invite-status-pill.invite-status-pending {
    background: rgba({{primaryRgb}}, 0.16) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkSubtle}} !important;
}

body.invites-page .invite-status-pill.invite-status-accepted {
    background: rgba(16, 185, 129, 0.16) !important;
    border-color: rgba(52, 211, 153, 0.28) !important;
    color: #a7f3d0 !important;
}

body.invites-page .invite-status-pill.invite-status-expired,
body.invites-page .invite-status-pill.invite-status-revoked {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkMuted}} !important;
}

body.invites-page .invite-one-time-pill,
body.invites-page .invite-ext-chip {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.24) !important;
    color: {{darkSubtle}} !important;
}

body.invites-page .invite-link-chip {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
}

body.invites-page .invite-link-dot {
    background: {{darkBorder}} !important;
}

body.invites-page .invite-dropzone:hover,
body.invites-page .invite-dropzone.drag-over,
body.invites-page .invite-dropzone.has-file {
    background: {{darkSurface3}} !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    box-shadow: none !important;
}

body.invites-page .invite-dropzone-text strong,
body.invites-page .invite-file-meta,
body.invites-page .invite-link-chip,
body.invites-page .invite-expires i {
    color: {{darkSubtle}} !important;
}

body.invites-page .invite-bulk-hover-indicator {
    background: {{darkSurface3}} !important;
    color: {{darkSubtle}} !important;
}

body.invites-page .invite-bulk-panel.is-hover-guide-visible .invite-bulk-hover-indicator {
    background: rgba({{primaryRgb}}, 0.24) !important;
    color: #ffffff !important;
}

body.invites-page .bulk-invite-hover-guide {
    border: 1px solid {{darkBorder}} !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
}

body.invites-page .bulk-invite-hover-guide-divider::before,
body.invites-page .bulk-invite-hover-guide-divider::after {
    background: {{darkBorderSoft}} !important;
}

body.invites-page .bulk-invite-hover-guide-scroll::-webkit-scrollbar-track {
    background: {{darkSurface2}} !important;
}

body.invites-page .bulk-invite-hover-guide-scroll::-webkit-scrollbar-thumb {
    background: {{primaryMuted}} !important;
}

body.invites-page .bulk-invite-hover-guide-scroll::-webkit-scrollbar-thumb:hover {
    background: {{secondary}} !important;
}

body.edit-profile-page .dash-card,
body.profile-page .dash-card {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.2) !important;
}

body.edit-profile-page .profile-content,
body.profile-page .profile-content,
body.edit-profile-page .profile-header-section,
body.profile-page .profile-header-section {
    color: {{darkText}} !important;
}

body.edit-profile-page .profile-name-text,
body.profile-page .profile-name-text,
body.edit-profile-page .profile-field-group label,
body.profile-page .profile-field-group label,
body.edit-profile-page .profile-content h4,
body.profile-page .profile-content h4,
body.profile-page .profile-content h3[style*="color: var(--text-dark)"],
body.profile-page .profile-content div[style*="color: var(--text-dark)"],
body.profile-page .profile-content div[style*="color: #111827"] {
    color: {{darkText}} !important;
}

body.edit-profile-page .profile-role-text,
body.profile-page .profile-role-text,
body.edit-profile-page .profile-field-group label i,
body.profile-page .profile-field-group label i,
body.edit-profile-page .profile-content span[style*="color: #6B7280"],
body.profile-page .profile-content span[style*="color: #6B7280"],
body.profile-page .profile-content div[style*="color: #4B5563"],
body.profile-page .profile-content div[style*="color: var(--text-gray)"],
body.profile-page .profile-content p[style*="color: var(--text-gray)"] {
    color: {{darkMuted}} !important;
}

body.edit-profile-page .field-value,
body.profile-page .field-value {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.edit-profile-page .field-value::placeholder,
body.profile-page .field-value::placeholder {
    color: {{darkMuted}} !important;
}

body.edit-profile-page .btn-outline,
body.profile-page .btn-outline {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.edit-profile-page hr[style*="border-top: 1px solid #E5E7EB"],
body.profile-page hr[style*="border-top: 1px solid #E5E7EB"] {
    border-top-color: {{darkBorderSoft}} !important;
}

body.edit-profile-page div[style*="background: #F9FAFB"],
body.profile-page div[style*="background: #F9FAFB"],
body.profile-page div[style*="background: #FFF7ED"],
body.profile-page div[style*="background: #EFF6FF"] {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.edit-profile-page div[style*="background: #FFFBEB"],
body.edit-profile-page div[style*="background: #FEF2F2"],
body.edit-profile-page div[style*="background: #ECFDF5"] {
    border-color: {{darkBorder}} !important;
}

body.profile-page .profile-content > div:first-child > div {
    border: 1px solid {{darkBorder}} !important;
    box-shadow: none !important;
}

body.profile-page .profile-content > div:first-child > div:nth-child(1) {
    background: rgba(245, 158, 11, 0.14) !important;
    border-color: rgba(251, 191, 36, 0.24) !important;
}

body.profile-page .profile-content > div:first-child > div:nth-child(2),
body.profile-page div[style*="background: var(--primary-soft-2)"] {
    background: rgba({{primaryRgb}}, 0.14) !important;
    border-color: rgba({{primaryRgb}}, 0.24) !important;
}

body.profile-page .profile-content > div:first-child > div:nth-child(3) {
    background: rgba(59, 130, 246, 0.14) !important;
    border-color: rgba(96, 165, 250, 0.24) !important;
}

body.profile-page .profile-content > div:first-child > div:nth-child(1) h3,
body.profile-page .profile-content > div:first-child > div:nth-child(1) span {
    color: #fcd34d !important;
}

body.profile-page .profile-content > div:first-child > div:nth-child(2) h3,
body.profile-page .profile-content > div:first-child > div:nth-child(2) span,
body.profile-page div[style*="background: var(--primary-soft-2)"] h3,
body.profile-page div[style*="background: var(--primary-soft-2)"] span {
    color: #ddd6fe !important;
}

body.profile-page .profile-content > div:first-child > div:nth-child(3) h3,
body.profile-page .profile-content > div:first-child > div:nth-child(3) span {
    color: #bfdbfe !important;
}

body.profile-page div[style*="background: linear-gradient(135deg, var(--primary-soft), var(--primary-soft-5))"] {
    background: linear-gradient(135deg, rgba({{primaryRgb}}, 0.22), {{darkSurface2}}) !important;
    border: 1px solid rgba({{primaryRgb}}, 0.24) !important;
}

body.profile-page div[style*="background: white; width: 60px; height: 60px"] {
    background: {{darkSurface}} !important;
    border: 1px solid {{darkBorder}} !important;
    box-shadow: none !important;
}

body.profile-page div[style*="color: var(--primary-ink)"],
body.profile-page div[style*="color: var(--primary-dark)"],
body.profile-page span[style*="color: #6B21A8"],
body.profile-page h3[style*="color: #6B21A8"],
body.profile-page span[style*="color: #92400E"],
body.profile-page h3[style*="color: #92400E"],
body.profile-page span[style*="color: #1E40AF"],
body.profile-page h3[style*="color: #1E40AF"] {
    color: {{darkText}} !important;
}

body.groups-page .groups-empty-state {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkMuted}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18) !important;
}

body.timeline-page .tlp-stat-card,
body.timeline-page .tlp-filter-tabs,
body.timeline-page .tlp-empty,
body.timeline-page .tlp-tile,
body.timeline-page .tlp-project-card,
body.timeline-page .tlp-member-card,
body.timeline-page .tlp-project-pill {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.18) !important;
}

body.timeline-page .tlp-tile,
body.timeline-page .tlp-project-card,
body.timeline-page .tlp-member-card {
    background: {{darkSurface2}} !important;
}

body.timeline-page .tlp-search-input {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-filter-btn {
    color: {{darkMuted}} !important;
}

body.timeline-page .tlp-filter-btn.active {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    color: #ffffff !important;
}

body.timeline-page .tlp-tile-title,
body.timeline-page .tlp-project-name,
body.timeline-page .tlp-member-name {
    color: {{darkText}} !important;
}

body.timeline-page .tlp-tile-sub,
body.timeline-page .tlp-project-sub,
body.timeline-page .tlp-progress-head span:first-child,
body.timeline-page .tlp-project-pill,
body.timeline-page .tlp-empty,
body.timeline-page .tlp-stat-label {
    color: {{darkMuted}} !important;
}

body.timeline-page .tlp-progress-track,
body.timeline-page .tlp-mini-row,
body.timeline-page .tlp-progress-strip-track {
    background: {{darkSurface3}} !important;
}

body.timeline-page .tlp-project-pill.active {
    background: rgba({{primaryRgb}}, 0.16) !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-detail-head,
body.timeline-page .tlp-workspace,
body.timeline-page .tlp-gantt-head,
body.timeline-page .tlp-gantt-scroll,
body.timeline-page .tlp-phase-list,
body.timeline-page .tlp-progress-strip,
body.timeline-page .tlp-role-banner {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18) !important;
}

body.timeline-page .tlp-phase-guide-list {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.timeline-page .tlp-phase-guide-chip {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorderSoft}} !important;
}

body.timeline-page .tlp-phase-guide-count,
body.timeline-page .tlp-phase-guide-limit,
body.timeline-page .tlp-phase-guide-empty {
    color: {{darkMuted}} !important;
}

body.timeline-page .tlp-icon-choice {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkMuted}} !important;
}

body.timeline-page .tlp-icon-choice:hover {
    background: {{darkSurface3}} !important;
    border-color: {{primaryBorder}} !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-icon-choice.active {
    background: rgba({{primaryRgb}}, 0.16) !important;
    border-color: rgba({{primaryRgb}}, 0.34) !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-modal h3 {
    color: {{darkText}} !important;
}

body.timeline-page #tlpConfirmTitle,
body.timeline-page #tlpConfirmTitle i {
    color: #f87171 !important;
}

body.timeline-page .tlp-confirm-message {
    color: {{darkMuted}} !important;
}

body.timeline-page .tlp-btn.cancel,
body.timeline-page #tlpConfirmCancelBtn {
    background: {{darkSurface3}} !important;
    border: 1px solid {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.timeline-page .tlp-btn.cancel:hover,
body.timeline-page #tlpConfirmCancelBtn:hover {
    background: {{darkSurface2}} !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-btn.danger,
body.timeline-page #tlpConfirmBtn {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%) !important;
    border: 1px solid rgba(248, 113, 113, 0.28) !important;
    color: #ffffff !important;
}

body.timeline-page .tlp-btn.danger:hover,
body.timeline-page #tlpConfirmBtn:hover {
    background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%) !important;
    color: #ffffff !important;
}

body.timeline-page .tlp-label-col,
body.timeline-page .tlp-task-label,
body.timeline-page .tlp-phase-row-main,
body.timeline-page .tlp-empty[style*="background:#F9FAFB"] {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorderSoft}} !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-divider,
body.timeline-page .tlp-gantt-head,
body.timeline-page .tlp-task-label,
body.timeline-page .tlp-phase-row,
body.timeline-page .tlp-gantt-task,
body.timeline-page .tlp-phase-list {
    border-color: {{darkBorderSoft}} !important;
}

body.timeline-page .tlp-task-row,
body.timeline-page .tlp-task-row[style*="background:"],
body.timeline-page .tlp-phase-row {
    background: {{darkSurface}} !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-day {
    border-color: {{darkBorderSoft}} !important;
    background: transparent !important;
    color: {{darkSubtle}} !important;
}

body.timeline-page .tlp-grid-line {
    background: {{darkBorderSoft}} !important;
}

body.timeline-page .tlp-day.today {
    background: rgba({{primaryRgb}}, 0.16) !important;
    color: {{darkText}} !important;
}

body.timeline-page .tlp-task-title,
body.timeline-page .tlp-meta-name,
body.timeline-page .tlp-phase-name,
body.timeline-page .tlp-progress-strip-label {
    color: {{darkText}} !important;
}

body.timeline-page .tlp-meta-sub,
body.timeline-page .tlp-task-assignee,
body.timeline-page .tlp-phase-desc,
body.timeline-page .tlp-phase-row span[style*="color:#6B7280"],
body.timeline-page .tlp-empty span {
    color: {{darkMuted}} !important;
}

body.timeline-page .tlp-gantt-scroll::-webkit-scrollbar-track {
    background: {{darkSurface2}} !important;
}

body.timeline-page .tlp-gantt-scroll::-webkit-scrollbar-thumb {
    background: {{darkBorder}} !important;
}

body.timeline-page .tlp-gantt-scroll::-webkit-scrollbar-thumb:hover {
    background: {{primaryMuted}} !important;
}

body.messages-page .messages-page-main .chat-layout,
body.messages-page .messages-page-main .chat-sidebar,
body.messages-page .messages-page-main .chat-list,
body.messages-page .messages-page-main .chat-main,
body.messages-page .chat-info-sidebar {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.messages-page .messages-page-main .chat-layout {
    background: {{darkSurface}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.2) !important;
}

body.messages-page .messages-page-main .chat-sidebar,
body.messages-page .chat-info-sidebar {
    background: {{darkSurface2}} !important;
}

body.messages-page .messages-page-main .chat-search input,
body.messages-page .messages-page-main .chat-input-wrapper,
body.messages-page .messages-page-main .chat-input-wrapper input {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}

body.messages-page .messages-page-main .chat-input-wrapper input:focus {
    background: {{darkSurface2}} !important;
    border-color: rgba({{primaryRgb}}, 0.28) !important;
    color: {{darkText}} !important;
    box-shadow: 0 0 0 3px rgba({{primaryRgb}}, 0.12) !important;
}

body.messages-page .messages-page-main .chat-search input::placeholder,
body.messages-page .messages-page-main .chat-input-wrapper input::placeholder,
body.messages-page .messages-page-main .chat-search-icon,
body.messages-page .chat-group-heading-label,
body.messages-page .messages-page-main .chat-user-role,
body.messages-page .messages-page-main .chat-item-last-msg,
body.messages-page .messages-page-main .chat-time,
body.messages-page .messages-page-main .message-time,
body.messages-page .messages-page-main .no-chat-state,
body.messages-page .chat-info-header .btn-close-info {
    color: {{darkMuted}} !important;
}

body.messages-page .messages-page-main .chat-item:hover {
    background: rgba({{primaryRgb}}, 0.08) !important;
}

body.messages-page .messages-page-main .chat-header,
body.messages-page .messages-page-main .chat-input-area,
body.messages-page .messages-page-main .attachment-preview,
body.messages-page .chat-info-header {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
}

body.messages-page .messages-page-main .chat-messages,
body.messages-page .chat-info-content {
    background: linear-gradient(180deg, {{darkSurface2}} 0%, {{darkSurface3}} 100%) !important;
}

body.messages-page .messages-page-main .chat-date-separator {
    color: {{darkMuted}} !important;
}

body.messages-page .messages-page-main .chat-date-separator::before,
body.messages-page .messages-page-main .chat-date-separator::after {
    background: {{darkBorderSoft}} !important;
}

body.messages-page .messages-page-main .message-bubble-incoming {
    background: {{darkSurface3}} !important;
    color: {{darkText}} !important;
}

body.messages-page .messages-page-main .chat-typing-bubble {
    background: {{darkSurface2}} !important;
    color: {{darkText}} !important;
    border-color: {{darkBorderSoft}} !important;
}

body.messages-page .messages-page-main .chat-typing-message .chat-typing-avatar {
    border-color: {{darkSurface2}} !important;
}

body.messages-page .messages-page-main .group-message-seen-avatar {
    background: {{darkSurface2}} !important;
    color: {{darkText}} !important;
    border-color: {{darkSurface}} !important;
}

body.messages-page .messages-page-main .direct-message-seen-avatar {
    background: {{darkSurface2}} !important;
    color: {{darkText}} !important;
    border-color: {{darkSurface}} !important;
}

body.messages-page .messages-page-main .group-message-seen-avatar-more {
    background: {{darkBorderSoft}} !important;
}

body.messages-page .messages-page-main .chat-header-info h3,
body.messages-page .messages-page-main .chat-user-name,
body.messages-page .messages-page-main .message-user-name,
body.messages-page .chat-info-header {
    color: {{darkText}} !important;
}

body.messages-page .group-member-name {
    color: {{darkText}} !important;
}

body.messages-page .group-member-role,
body.messages-page .group-member-name span[style*="color:#9CA3AF"],
body.messages-page .group-member-name span[style*="color: #9CA3AF"] {
    color: {{darkMuted}} !important;
}

body.messages-page .chat-info-user-card,
body.messages-page .chat-assets-file-item {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
}

body.messages-page .chat-info-section-label,
body.messages-page .chat-assets-empty,
body.messages-page .chat-assets-file-subtext,
body.messages-page .chat-assets-tab {
    color: {{darkMuted}} !important;
}

body.messages-page .chat-info-user-name,
body.messages-page .chat-assets-month-label,
body.messages-page .chat-assets-file-name {
    color: {{darkText}} !important;
}

body.messages-page .chat-assets-tabs {
    border-color: {{darkBorder}} !important;
}

body.messages-page .chat-assets-tab.active {
    color: {{primary}} !important;
    border-color: {{primary}} !important;
}

body.messages-page .chat-assets-empty {
    background: {{darkSurface}} !important;
    border-color: {{darkBorderSoft}} !important;
}

body.messages-page .chat-assets-media-item,
body.messages-page .chat-assets-file-icon {
    background: {{darkSurface3}} !important;
}

body.messages-page .chat-assets-file-icon {
    color: {{primary}} !important;
}

body.messages-page .messages-page-main .chat-filter-tab {
    background: {{darkSurface3}} !important;
    color: {{darkMuted}} !important;
}

body.messages-page .messages-page-main .chat-filter-tab.active {
    background: linear-gradient(135deg, {{primary}} 0%, {{secondary}} 100%) !important;
    color: #ffffff !important;
}

body.messages-page .messages-page-main .chat-item.show-delete-action {
    background: rgba({{primaryRgb}}, 0.12) !important;
}

body.messages-page .messages-page-main .chat-item-delete-btn {
    background: rgba(248, 113, 113, 0.18) !important;
    color: #FCA5A5 !important;
}

body.messages-page .messages-page-main .message-delete-btn {
    background: rgba(248, 113, 113, 0.18) !important;
    color: #FCA5A5 !important;
}

body.messages-page .messages-page-main .chat-item.active .chat-item-delete-btn {
    background: rgba(255, 255, 255, 0.16) !important;
    color: #ffffff !important;
}

body.messages-page .chat-delete-modal-card {
    background: {{darkSurface}} !important;
}

body.messages-page .chat-delete-modal-card h4 {
    color: {{darkText}} !important;
}

body.messages-page .chat-delete-modal-card p,
body.messages-page .chat-delete-btn-secondary {
    color: {{darkMuted}} !important;
}

body.messages-page .chat-delete-btn-secondary {
    background: {{darkSurface3}} !important;
}

body.messages-page .chat-delete-btn-danger {
    background: #DC2626 !important;
    color: #ffffff !important;
}

body.users-page .header-card,
body.users-page .user-card,
body.users-page .modal-content,
body.users-page #adminConfirmModal > div {
    background: {{darkSurface}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.2) !important;
}

body.users-page .user-card:hover {
    background: {{darkSurface2}} !important;
}

body.users-page .user-name,
body.users-page .modal-header h3,
body.users-page #modalUserName,
body.users-page #adminConfirmModal h3 {
    color: {{darkText}} !important;
}

body.users-page .user-email,
body.users-page .skill-tags,
body.users-page #roleModal p,
body.users-page #adminConfirmModal p,
body.users-page #roleModal label {
    color: {{darkMuted}} !important;
}

body.users-page .stats-row,
body.users-page .status-out,
body.users-page .role-note {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
}

body.users-page .stats-row div[style*="background: #E5E7EB"] {
    background: {{darkBorder}} !important;
}

body.users-page .btn-profile,
body.users-page .btn-cancel,
body.users-page #adminConfirmModal button[style*="background:#F3F4F6"],
body.users-page #adminConfirmModal button[style*="background: #F3F4F6"] {
    background: {{darkSurface3}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkSubtle}} !important;
}

body.users-page .admin-clockout-btn {
    background: rgba(245, 158, 11, 0.14) !important;
    border-color: rgba(251, 191, 36, 0.28) !important;
    color: #fcd34d !important;
}

body.users-page .role-note {
    background: rgba(245, 158, 11, 0.12) !important;
    border-color: rgba(251, 191, 36, 0.24) !important;
    color: #fcd34d !important;
}

body.users-page #roleModal select {
    background: {{darkSurface2}} !important;
    border-color: {{darkBorder}} !important;
    color: {{darkText}} !important;
}
CSS;

        return strtr($template, [
            '{{primary}}' => $primary,
            '{{secondary}}' => $secondary,
            '{{primaryStrong}}' => $primaryStrong,
            '{{primaryMuted}}' => $primaryMuted,
            '{{primaryBorder}}' => $primaryBorder,
            '{{primaryRgb}}' => $primaryRgb,
            '{{accentRgb}}' => $accentRgb,
            '{{darkBody}}' => $darkBody,
            '{{darkBodyAlt}}' => $darkBodyAlt,
            '{{darkSurface}}' => $darkSurface,
            '{{darkSurface2}}' => $darkSurface2,
            '{{darkSurface3}}' => $darkSurface3,
            '{{darkBorder}}' => $darkBorder,
            '{{darkBorderSoft}}' => $darkBorderSoft,
            '{{darkText}}' => $darkText,
            '{{darkMuted}}' => $darkMuted,
            '{{darkSubtle}}' => $darkSubtle,
        ]);
    }
}

if (!function_exists('workspace_theme_build_css')) {
    function workspace_theme_build_css(?array $theme): string
    {
        if (!workspace_theme_has_custom($theme)) {
            return '';
        }

        $defaults = workspace_theme_default_palette();
        $mode = workspace_theme_resolve_mode($theme['mode'] ?? workspace_theme_default_mode());
        $primary = $theme['primary'] ?: $defaults['primary'];
        $secondary = $theme['secondary'] ?: workspace_theme_mix_hex($primary, '#ffffff', 0.2);
        if (!$secondary) {
            $secondary = $defaults['secondary'];
        }
        $accent = $theme['accent'] ?: $primary;

        $accentLight = workspace_theme_mix_hex($accent, '#ffffff', 0.86) ?: $defaults['accent'];
        $accentMid = workspace_theme_mix_hex($accent, '#ffffff', 0.45) ?: $secondary;
        $adminDark = workspace_theme_mix_hex($primary, '#000000', 0.2) ?: $secondary;
        $adminLight = workspace_theme_mix_hex($primary, '#ffffff', 0.86) ?: $accentLight;

        $primaryStrong = workspace_theme_mix_hex($primary, '#000000', 0.12) ?: $secondary;
        $primaryDeep = workspace_theme_mix_hex($primary, '#000000', 0.22) ?: $primaryStrong;
        $primaryInk = workspace_theme_mix_hex($primary, '#000000', 0.32) ?: $primaryDeep;
        $primaryInk2 = workspace_theme_mix_hex($primary, '#000000', 0.45) ?: $primaryInk;
        $primaryMuted = workspace_theme_mix_hex($primary, '#ffffff', 0.35) ?: $secondary;
        $primaryMuted2 = workspace_theme_mix_hex($primary, '#ffffff', 0.55) ?: $primaryMuted;
        $primaryMuted3 = workspace_theme_mix_hex($primary, '#ffffff', 0.65) ?: $primaryMuted2;
        $primaryBorder = workspace_theme_mix_hex($primary, '#ffffff', 0.72) ?: $primaryMuted3;
        $primarySoft = workspace_theme_mix_hex($primary, '#ffffff', 0.82) ?: $primaryBorder;
        $primarySoft2 = workspace_theme_mix_hex($primary, '#ffffff', 0.9) ?: $primarySoft;
        $primarySoft3 = workspace_theme_mix_hex($primary, '#ffffff', 0.86) ?: $primarySoft2;
        $primarySoft4 = workspace_theme_mix_hex($primary, '#ffffff', 0.76) ?: $primarySoft3;
        $primarySoft5 = workspace_theme_mix_hex($primary, '#ffffff', 0.88) ?: $primarySoft2;
        $primarySoft6 = workspace_theme_mix_hex($primary, '#ffffff', 0.94) ?: $primarySoft2;
        $primarySoft7 = workspace_theme_mix_hex($primary, '#ffffff', 0.92) ?: $primarySoft2;
        $primarySoft8 = workspace_theme_mix_hex($primary, '#ffffff', 0.9) ?: $primarySoft2;

        $primaryRgb = workspace_theme_hex_to_rgb($primary);
        $secondaryRgb = workspace_theme_hex_to_rgb($secondary);
        $accentRgb = workspace_theme_hex_to_rgb($accent);
        $primaryStrongRgb = workspace_theme_hex_to_rgb($primaryStrong);
        $primaryDeepRgb = workspace_theme_hex_to_rgb($primaryDeep);
        $primaryInkRgb = workspace_theme_hex_to_rgb($primaryInk);
        $primaryInk2Rgb = workspace_theme_hex_to_rgb($primaryInk2);

        $vars = [
            '--workspace-theme-mode' => $mode,
            '--primary-color' => $primary,
            '--primary-hover' => $secondary,
            '--secondary-color' => $secondary,
            '--primary' => $primary,
            '--primary-dark' => $secondary,
            '--sidebar-bg' => $primary,
            '--sidebar-active' => $secondary,
            '--accent' => $accent,
            '--accent-mid' => $accentMid,
            '--accent-light' => $accentLight,
            '--tlp-accent' => $accent,
            '--primary-strong' => $primaryStrong,
            '--primary-deep' => $primaryDeep,
            '--primary-ink' => $primaryInk,
            '--primary-ink-2' => $primaryInk2,
            '--primary-muted' => $primaryMuted,
            '--primary-muted-2' => $primaryMuted2,
            '--primary-muted-3' => $primaryMuted3,
            '--primary-border' => $primaryBorder,
            '--primary-soft' => $primarySoft,
            '--primary-soft-2' => $primarySoft2,
            '--primary-soft-3' => $primarySoft3,
            '--primary-soft-4' => $primarySoft4,
            '--primary-soft-5' => $primarySoft5,
            '--primary-soft-6' => $primarySoft6,
            '--primary-soft-7' => $primarySoft7,
            '--primary-soft-8' => $primarySoft8,
            '--primary-rgb' => $primaryRgb ?: '108, 60, 225',
            '--primary-dark-rgb' => $secondaryRgb ?: '139, 92, 246',
            '--accent-rgb' => $accentRgb ?: '108, 62, 244',
            '--primary-strong-rgb' => $primaryStrongRgb ?: '124, 58, 237',
            '--primary-deep-rgb' => $primaryDeepRgb ?: '109, 40, 217',
            '--primary-ink-rgb' => $primaryInkRgb ?: '91, 33, 182',
            '--primary-ink-2-rgb' => $primaryInk2Rgb ?: '76, 29, 149',
            '--scrollbar-track' => $primarySoft6,
            '--scrollbar-thumb' => $primaryMuted2,
            '--scrollbar-thumb-hover' => $primaryStrong,
            '--scrollbar-corner' => $primarySoft6,
        ];

        if ($mode === 'dark') {
            $darkBody = workspace_theme_mix_hex($primary, '#020617', 0.9) ?: '#0b1120';
            $darkSurface = workspace_theme_mix_hex($primary, '#111827', 0.84) ?: '#131b2a';
            $darkSurface2 = workspace_theme_mix_hex($primary, '#1f2937', 0.78) ?: '#1b2535';
            $darkBorder = workspace_theme_mix_hex($primary, '#64748b', 0.72) ?: '#334155';
            $darkBorderSoft = workspace_theme_mix_hex($primary, '#475569', 0.8) ?: '#273449';

            $vars['--sidebar-bg'] = workspace_theme_mix_hex($primary, '#020617', 0.48) ?: $darkSurface;
            $vars['--sidebar-active'] = workspace_theme_mix_hex($primary, '#ffffff', 0.12) ?: $primary;
            $vars['--primary-muted'] = workspace_theme_mix_hex($primary, '#cbd5e1', 0.72) ?: '#9fb2c7';
            $vars['--primary-muted-2'] = workspace_theme_mix_hex($primary, '#94a3b8', 0.76) ?: '#7d92a8';
            $vars['--primary-muted-3'] = workspace_theme_mix_hex($primary, '#64748b', 0.8) ?: $darkBorderSoft;
            $vars['--primary-border'] = workspace_theme_mix_hex($primary, '#475569', 0.82) ?: $darkBorder;
            $vars['--primary-soft'] = workspace_theme_mix_hex($primary, '#1f2937', 0.86) ?: $darkSurface2;
            $vars['--primary-soft-2'] = workspace_theme_mix_hex($primary, '#111827', 0.88) ?: $darkSurface;
            $vars['--primary-soft-3'] = workspace_theme_mix_hex($primary, '#0f172a', 0.84) ?: $darkSurface;
            $vars['--primary-soft-4'] = workspace_theme_mix_hex($primary, '#334155', 0.8) ?: $darkBorder;
            $vars['--primary-soft-5'] = workspace_theme_mix_hex($primary, '#0f172a', 0.9) ?: $darkSurface;
            $vars['--primary-soft-6'] = workspace_theme_mix_hex($primary, '#020617', 0.92) ?: $darkBody;
            $vars['--primary-soft-7'] = workspace_theme_mix_hex($primary, '#172033', 0.88) ?: $darkSurface2;
            $vars['--primary-soft-8'] = workspace_theme_mix_hex($primary, '#111827', 0.9) ?: $darkSurface;
            $vars['--bg-light'] = $darkBody;
            $vars['--white'] = $darkSurface;
            $vars['--text-dark'] = '#e5e7eb';
            $vars['--text-gray'] = '#94a3b8';
            $vars['--border-color'] = $darkBorder;
            $vars['--bg'] = $darkBody;
            $vars['--surface'] = $darkSurface;
            $vars['--surface-2'] = $darkSurface2;
            $vars['--border'] = $darkBorder;
            $vars['--border-subtle'] = $darkBorderSoft;
            $vars['--text-primary'] = '#e5e7eb';
            $vars['--text-secondary'] = '#94a3b8';
            $vars['--text-muted'] = '#cbd5e1';
            $vars['--scrollbar-track'] = $darkSurface2;
            $vars['--scrollbar-thumb'] = workspace_theme_mix_hex($primary, '#94a3b8', 0.76) ?: $darkBorder;
            $vars['--scrollbar-thumb-hover'] = workspace_theme_mix_hex($primary, '#cbd5e1', 0.62) ?: $primaryMuted;
            $vars['--scrollbar-corner'] = $darkSurface2;
        }

        $css = ":root {\n";
        foreach ($vars as $name => $value) {
            $css .= "    {$name}: {$value};\n";
        }
        $css .= "}\n";
        $css .= "body.dashboard-page.role-admin {\n";
        $css .= "    --admin-purple: {$primary};\n";
        $css .= "    --admin-purple-mid: {$secondary};\n";
        $css .= "    --admin-purple-dark: {$adminDark};\n";
        $css .= "    --admin-purple-light: {$adminLight};\n";
        $css .= "}\n";
        $css .= "html, body, body * {\n";
        $css .= "    scrollbar-width: thin;\n";
        $css .= "    scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);\n";
        $css .= "}\n";
        $css .= "body::-webkit-scrollbar,\n";
        $css .= "body *::-webkit-scrollbar {\n";
        $css .= "    width: 10px;\n";
        $css .= "    height: 10px;\n";
        $css .= "}\n";
        $css .= "body::-webkit-scrollbar-track,\n";
        $css .= "body *::-webkit-scrollbar-track {\n";
        $css .= "    background: var(--scrollbar-track);\n";
        $css .= "    border-radius: 999px;\n";
        $css .= "}\n";
        $css .= "body::-webkit-scrollbar-thumb,\n";
        $css .= "body *::-webkit-scrollbar-thumb {\n";
        $css .= "    background: var(--scrollbar-thumb);\n";
        $css .= "    border: 2px solid var(--scrollbar-track);\n";
        $css .= "    border-radius: 999px;\n";
        $css .= "}\n";
        $css .= "body::-webkit-scrollbar-thumb:hover,\n";
        $css .= "body *::-webkit-scrollbar-thumb:hover {\n";
        $css .= "    background: var(--scrollbar-thumb-hover);\n";
        $css .= "}\n";
        $css .= "body::-webkit-scrollbar-corner,\n";
        $css .= "body *::-webkit-scrollbar-corner {\n";
        $css .= "    background: var(--scrollbar-corner);\n";
        $css .= "}\n";

        if ($mode === 'dark') {
            $css .= workspace_theme_build_dark_css([
                'primary' => $primary,
                'secondary' => $secondary,
                'primaryStrong' => $primaryStrong,
                'primaryMuted' => $primaryMuted,
                'primaryBorder' => $primaryBorder,
                'primaryRgb' => $primaryRgb ?: '108, 60, 225',
                'accentRgb' => $accentRgb ?: ($primaryRgb ?: '108, 60, 225'),
            ]);
        }

        return $css;
    }
}
