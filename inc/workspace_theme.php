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

if (!function_exists('workspace_theme_fetch')) {
    function workspace_theme_fetch(PDO $pdo, $orgId): ?array
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0 || !workspace_theme_schema_ready($pdo)) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT theme_primary, theme_secondary, theme_accent
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
        ];
    }
}

if (!function_exists('workspace_theme_has_custom')) {
    function workspace_theme_has_custom(?array $theme): bool
    {
        if (!$theme) {
            return false;
        }
        return !empty($theme['primary']) || !empty($theme['secondary']) || !empty($theme['accent']);
    }
}

if (!function_exists('workspace_theme_build_css')) {
    function workspace_theme_build_css(?array $theme): string
    {
        if (!workspace_theme_has_custom($theme)) {
            return '';
        }

        $defaults = workspace_theme_default_palette();
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
        ];

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

        return $css;
    }
}
