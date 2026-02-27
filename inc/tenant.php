<?php

if (!function_exists('tenant_column_exists')) {
    function tenant_column_exists($pdo, $table, $column)
    {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('tenant_table_exists')) {
    function tenant_table_exists($pdo, $table)
    {
        $sql = "SELECT 1
                FROM information_schema.tables
                WHERE table_name = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('tenant_get_current_org_id')) {
    function tenant_get_current_org_id()
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['organization_id'])) {
            $orgId = (int)$_SESSION['organization_id'];
            return $orgId > 0 ? $orgId : null;
        }
        return null;
    }
}

if (!function_exists('tenant_trial_days')) {
    function tenant_trial_days()
    {
        return 2;
    }
}

if (!function_exists('tenant_workspace_plan_catalog')) {
    function tenant_workspace_plan_catalog()
    {
        return [
            'starter' => [
                'code' => 'starter',
                'name' => 'Starter',
                'seat_limit' => 10,
                'summary' => 'Up to 10 team members',
            ],
            'professional' => [
                'code' => 'professional',
                'name' => 'Professional',
                'seat_limit' => 20,
                'summary' => 'Up to 20 team members',
            ],
            'enterprise' => [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'seat_limit' => 40,
                'summary' => 'Up to 40 team members',
            ],
        ];
    }
}

if (!function_exists('tenant_resolve_workspace_plan')) {
    function tenant_resolve_workspace_plan($planCode, $fallbackCode = 'starter')
    {
        $catalog = tenant_workspace_plan_catalog();
        $aliases = [
            'trial' => 'starter',
            'legacy' => 'starter',
            'pro' => 'professional',
            'team' => 'professional',
            'business' => 'enterprise',
        ];

        $code = strtolower(trim((string)$planCode));
        if (isset($aliases[$code])) {
            $code = $aliases[$code];
        }

        if (isset($catalog[$code])) {
            return $catalog[$code];
        }

        $fallback = strtolower(trim((string)$fallbackCode));
        if (isset($aliases[$fallback])) {
            $fallback = $aliases[$fallback];
        }

        return $catalog[$fallback] ?? reset($catalog);
    }
}

if (!function_exists('tenant_apply_workspace_plan')) {
    function tenant_apply_workspace_plan($pdo, $orgId, $planCode)
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0) {
            return [
                'ok' => false,
                'reason' => 'Workspace context is missing.',
                'plan' => null,
            ];
        }

        if (!tenant_table_exists($pdo, 'organizations') || !tenant_table_exists($pdo, 'subscriptions')) {
            return [
                'ok' => false,
                'reason' => 'Workspace billing tables are not available.',
                'plan' => null,
            ];
        }

        $plan = tenant_resolve_workspace_plan($planCode, 'starter');
        $seatLimit = max(1, (int)($plan['seat_limit'] ?? 0));
        $activeMembers = tenant_count_workspace_members($pdo, $orgId);

        if ($seatLimit < $activeMembers) {
            return [
                'ok' => false,
                'reason' => "Plan seat limit ({$seatLimit}) is below active members ({$activeMembers}).",
                'plan' => $plan,
            ];
        }

        try {
            $pdo->beginTransaction();

            $subscription = tenant_ensure_subscription($pdo, $orgId);
            if (!$subscription) {
                throw new RuntimeException('Unable to initialize workspace subscription.');
            }

            $stmtOrg = $pdo->prepare("UPDATE organizations SET plan_code = ? WHERE id = ?");
            $stmtOrg->execute([(string)$plan['code'], $orgId]);

            $stmtSub = $pdo->prepare(
                "UPDATE subscriptions
                 SET seat_limit = ?
                 WHERE organization_id = ?"
            );
            $stmtSub->execute([$seatLimit, $orgId]);

            $pdo->commit();

            return [
                'ok' => true,
                'reason' => null,
                'plan' => $plan,
                'seat_limit' => $seatLimit,
                'active_members' => $activeMembers,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'reason' => 'Failed to update workspace plan right now.',
                'plan' => $plan,
            ];
        }
    }
}
if (!function_exists('tenant_get_scope')) {
    function tenant_get_scope($pdo, $table, $alias = '', $joinWord = 'AND', $column = 'organization_id', $orgId = null)
    {
        if (!tenant_column_exists($pdo, $table, $column)) {
            return ['sql' => '', 'params' => []];
        }

        $resolvedOrgId = $orgId !== null ? (int)$orgId : tenant_get_current_org_id();
        if ($resolvedOrgId <= 0) {
            return ['sql' => '', 'params' => []];
        }

        $qualified = $alias !== '' ? "{$alias}.{$column}" : $column;
        $joinWord = strtoupper(trim($joinWord)) === 'WHERE' ? 'WHERE' : 'AND';

        return [
            'sql' => " {$joinWord} {$qualified} = ?",
            'params' => [$resolvedOrgId]
        ];
    }
}

if (!function_exists('tenant_resolve_user_org')) {
    function tenant_resolve_user_org($pdo, $userId, $fallbackOrgId = null)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return $fallbackOrgId ? (int)$fallbackOrgId : null;
        }

        if (tenant_table_exists($pdo, 'organization_members')) {
            $stmt = $pdo->prepare(
                "SELECT organization_id
                 FROM organization_members
                 WHERE user_id = ?
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([$userId]);
            $orgId = $stmt->fetchColumn();
            if ($orgId) {
                return (int)$orgId;
            }
        }

        if (tenant_column_exists($pdo, 'users', 'organization_id')) {
            $stmt = $pdo->prepare("SELECT organization_id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $orgId = $stmt->fetchColumn();
            if ($orgId) {
                return (int)$orgId;
            }
        }

        return $fallbackOrgId ? (int)$fallbackOrgId : null;
    }
}

if (!function_exists('tenant_resolve_user_membership_role')) {
    function tenant_resolve_user_membership_role($pdo, $userId, $orgId = null, $fallbackRole = 'member')
    {
        $userId = (int)$userId;
        $orgId = $orgId !== null ? (int)$orgId : tenant_get_current_org_id();

        if ($userId <= 0 || $orgId <= 0 || !tenant_table_exists($pdo, 'organization_members')) {
            return $fallbackRole;
        }

        $stmt = $pdo->prepare(
            "SELECT role
             FROM organization_members
             WHERE user_id = ? AND organization_id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId, $orgId]);
        $role = $stmt->fetchColumn();

        return $role ? (string)$role : $fallbackRole;
    }
}

if (!function_exists('tenant_fetch_subscription')) {
    function tenant_fetch_subscription($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0 || !tenant_table_exists($pdo, 'subscriptions')) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT id, organization_id, status, seat_limit, trial_ends_at, current_period_end
             FROM subscriptions
             WHERE organization_id = ?
             LIMIT 1"
        );
        $stmt->execute([$orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('tenant_ensure_subscription')) {
    function tenant_ensure_subscription($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0 || !tenant_table_exists($pdo, 'subscriptions')) {
            return null;
        }

        $existing = tenant_fetch_subscription($pdo, $orgId);
        if ($existing) {
            return $existing;
        }

        $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . tenant_trial_days() . ' days'));
        $periodEndsAt = date('Y-m-d H:i:s', strtotime('+1 month'));

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO subscriptions
                 (organization_id, provider, status, seat_limit, trial_ends_at, current_period_end)
                 VALUES (?, 'manual', 'trialing', 10, ?, ?)"
            );
            $stmt->execute([$orgId, $trialEndsAt, $periodEndsAt]);
        } catch (Throwable $e) {
            // If another request created it first, just fetch the row.
        }

        return tenant_fetch_subscription($pdo, $orgId);
    }
}

if (!function_exists('tenant_workspace_requires_payment')) {
    function tenant_workspace_requires_payment($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0) {
            return [
                'required' => false,
                'reason' => null,
                'subscription_status' => null,
                'trial_ends_at' => null,
            ];
        }

        $subscription = tenant_ensure_subscription($pdo, $orgId);
        $status = strtolower(trim((string)($subscription['status'] ?? 'active')));
        $trialEndsAt = $subscription['trial_ends_at'] ?? null;

        $blockedStatuses = [
            'canceled',
            'cancelled',
            'suspended',
            'inactive',
            'unpaid',
            'incomplete',
            'incomplete_expired',
            'paused',
        ];

        if ($status !== '' && in_array($status, $blockedStatuses, true)) {
            return [
                'required' => true,
                'reason' => "Workspace subscription is '{$status}'. Please complete payment to continue.",
                'subscription_status' => $status,
                'trial_ends_at' => $trialEndsAt,
            ];
        }

        if ($status === 'trialing' && !empty($trialEndsAt)) {
            $trialTs = strtotime((string)$trialEndsAt);
            if ($trialTs !== false && $trialTs <= time()) {
                return [
                    'required' => true,
                    'reason' => 'Your 2-day free trial has ended. Please complete payment to continue.',
                    'subscription_status' => $status,
                    'trial_ends_at' => $trialEndsAt,
                ];
            }
        }

        return [
            'required' => false,
            'reason' => null,
            'subscription_status' => $status !== '' ? $status : null,
            'trial_ends_at' => $trialEndsAt,
        ];
    }
}

if (!function_exists('tenant_count_workspace_members')) {
    function tenant_count_workspace_members($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0) {
            return 0;
        }

        if (tenant_table_exists($pdo, 'organization_members')) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT user_id)
                 FROM organization_members
                 WHERE organization_id = ?"
            );
            $stmt->execute([$orgId]);
            return (int)$stmt->fetchColumn();
        }

        if (tenant_column_exists($pdo, 'users', 'organization_id')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ?");
            $stmt->execute([$orgId]);
            return (int)$stmt->fetchColumn();
        }

        return 0;
    }
}

if (!function_exists('tenant_check_workspace_capacity')) {
    function tenant_check_workspace_capacity($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        if ($orgId <= 0) {
            return [
                'ok' => false,
                'reason' => 'Workspace context is missing.',
                'subscription_status' => null,
                'seat_limit' => null,
                'seat_used' => 0,
                'seats_left' => null,
                'trial_ends_at' => null,
                'current_period_end' => null,
            ];
        }

        $subscription = tenant_ensure_subscription($pdo, $orgId);
        $status = strtolower(trim((string)($subscription['status'] ?? 'active')));
        $trialEndsAt = $subscription['trial_ends_at'] ?? null;
        $periodEndsAt = $subscription['current_period_end'] ?? null;

        $seatUsed = tenant_count_workspace_members($pdo, $orgId);
        $seatLimit = isset($subscription['seat_limit']) ? (int)$subscription['seat_limit'] : null;
        $seatsLeft = $seatLimit === null ? null : ($seatLimit - $seatUsed);

        $blockedStatuses = [
            'canceled',
            'cancelled',
            'suspended',
            'inactive',
            'unpaid',
            'incomplete',
            'incomplete_expired',
            'paused',
        ];
        if ($status !== '' && in_array($status, $blockedStatuses, true)) {
            return [
                'ok' => false,
                'reason' => "Workspace subscription is '{$status}'. Please update billing before adding members.",
                'subscription_status' => $status,
                'seat_limit' => $seatLimit,
                'seat_used' => $seatUsed,
                'seats_left' => $seatsLeft,
                'trial_ends_at' => $trialEndsAt,
                'current_period_end' => $periodEndsAt,
            ];
        }

        if ($status === 'trialing' && !empty($trialEndsAt)) {
            $trialTs = strtotime((string)$trialEndsAt);
            if ($trialTs !== false && $trialTs <= time()) {
                return [
                    'ok' => false,
                    'reason' => 'Workspace trial has ended. Please activate a paid plan before adding members.',
                    'subscription_status' => $status,
                    'seat_limit' => $seatLimit,
                    'seat_used' => $seatUsed,
                    'seats_left' => $seatsLeft,
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_end' => $periodEndsAt,
                ];
            }
        }

        if ($seatLimit !== null) {
            if ($seatLimit <= 0) {
                return [
                    'ok' => false,
                    'reason' => 'No seats are configured for this workspace subscription.',
                    'subscription_status' => $status,
                    'seat_limit' => $seatLimit,
                    'seat_used' => $seatUsed,
                    'seats_left' => $seatsLeft,
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_end' => $periodEndsAt,
                ];
            }

            if ($seatsLeft !== null && $seatsLeft <= 0) {
                return [
                    'ok' => false,
                    'reason' => "Seat limit reached ({$seatUsed}/{$seatLimit}). Remove a user or upgrade your plan.",
                    'subscription_status' => $status,
                    'seat_limit' => $seatLimit,
                    'seat_used' => $seatUsed,
                    'seats_left' => $seatsLeft,
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_end' => $periodEndsAt,
                ];
            }
        }

        return [
            'ok' => true,
            'reason' => null,
            'subscription_status' => $status !== '' ? $status : null,
            'seat_limit' => $seatLimit,
            'seat_used' => $seatUsed,
            'seats_left' => $seatsLeft,
            'trial_ends_at' => $trialEndsAt,
            'current_period_end' => $periodEndsAt,
        ];
    }
}
