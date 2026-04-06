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

if (!function_exists('tenant_is_valid_email')) {
    function tenant_is_valid_email($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('tenant_get_workspace_owner_contact')) {
    function tenant_get_workspace_owner_contact($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        $contact = [
            'organization_id' => $orgId,
            'workspace_name' => 'Workspace',
            'billing_email' => null,
            'user_id' => null,
            'full_name' => 'Workspace Owner',
            'email' => null,
        ];

        if ($orgId <= 0 || !tenant_table_exists($pdo, 'organizations')) {
            return $contact;
        }

        $orgStmt = $pdo->prepare(
            "SELECT name, billing_email
             FROM organizations
             WHERE id = ?
             LIMIT 1"
        );
        $orgStmt->execute([$orgId]);
        $org = $orgStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $workspaceName = trim((string)($org['name'] ?? ''));
        if ($workspaceName !== '') {
            $contact['workspace_name'] = $workspaceName;
        }

        $billingEmail = trim((string)($org['billing_email'] ?? ''));
        if (tenant_is_valid_email($billingEmail)) {
            $contact['billing_email'] = $billingEmail;
        }

        if (tenant_table_exists($pdo, 'organization_members') && tenant_table_exists($pdo, 'users')) {
            $ownerStmt = $pdo->prepare(
                "SELECT u.id, u.full_name, u.username
                 FROM organization_members om
                 JOIN users u ON u.id = om.user_id
                 WHERE om.organization_id = ?
                   AND om.role = 'owner'
                 ORDER BY om.id ASC
                 LIMIT 1"
            );
            $ownerStmt->execute([$orgId]);
            $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($owner) {
                $contact['user_id'] = isset($owner['id']) ? (int)$owner['id'] : null;

                $ownerName = trim((string)($owner['full_name'] ?? ''));
                if ($ownerName !== '') {
                    $contact['full_name'] = $ownerName;
                }

                $ownerEmail = trim((string)($owner['username'] ?? ''));
                if (tenant_is_valid_email($ownerEmail)) {
                    $contact['email'] = $ownerEmail;
                }
            }
        }

        if ($contact['email'] === null && $contact['billing_email'] !== null) {
            $contact['email'] = $contact['billing_email'];
        }

        if ($contact['user_id'] === null && tenant_column_exists($pdo, 'users', 'organization_id')) {
            $adminStmt = $pdo->prepare(
                "SELECT id, full_name, username
                 FROM users
                 WHERE organization_id = ?
                   AND role = 'admin'
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $adminStmt->execute([$orgId]);
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($admin) {
                $contact['user_id'] = isset($admin['id']) ? (int)$admin['id'] : null;

                $adminName = trim((string)($admin['full_name'] ?? ''));
                if ($adminName !== '') {
                    $contact['full_name'] = $adminName;
                }

                if ($contact['email'] === null) {
                    $adminEmail = trim((string)($admin['username'] ?? ''));
                    if (tenant_is_valid_email($adminEmail)) {
                        $contact['email'] = $adminEmail;
                    }
                }
            }
        }

        return $contact;
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

if (!function_exists('tenant_subscription_blocked_statuses')) {
    function tenant_subscription_blocked_statuses()
    {
        return [
            'canceled',
            'cancelled',
            'suspended',
            'inactive',
            'unpaid',
            'incomplete',
            'incomplete_expired',
            'paused',
        ];
    }
}

if (!function_exists('tenant_evaluate_workspace_subscription')) {
    function tenant_evaluate_workspace_subscription($subscription, $nowTs = null)
    {
        $nowTs = is_numeric($nowTs) ? (int)$nowTs : time();
        $status = strtolower(trim((string)($subscription['status'] ?? 'active')));
        $trialEndsAt = $subscription['trial_ends_at'] ?? null;
        $periodEndsAt = $subscription['current_period_end'] ?? null;
        $isTrial = in_array($status, ['trialing', 'trial'], true);

        $summary = [
            'status' => $status !== '' ? $status : null,
            'effective_status' => $status !== '' ? $status : null,
            'trial_ends_at' => $trialEndsAt,
            'current_period_end' => $periodEndsAt,
            'is_trial' => $isTrial,
            'requires_payment' => false,
            'payment_reason' => null,
            'capacity_reason' => null,
            'expired_by_trial' => false,
            'expired_by_period' => false,
        ];

        if ($status !== '' && in_array($status, tenant_subscription_blocked_statuses(), true)) {
            $summary['requires_payment'] = true;
            $summary['payment_reason'] = "Workspace subscription is '{$status}'. Please complete payment to continue.";
            $summary['capacity_reason'] = "Workspace subscription is '{$status}'. Please update billing before adding members.";
            return $summary;
        }

        if ($isTrial && !empty($trialEndsAt)) {
            $trialTs = strtotime((string)$trialEndsAt);
            if ($trialTs !== false && $trialTs <= $nowTs) {
                $summary['requires_payment'] = true;
                $summary['payment_reason'] = 'Your 2-day free trial has ended. Please complete payment to continue.';
                $summary['capacity_reason'] = 'Workspace trial has ended. Please activate a paid plan before adding members.';
                $summary['expired_by_trial'] = true;
                $summary['effective_status'] = 'inactive';
                return $summary;
            }
        }

        if (!$isTrial && !empty($periodEndsAt)) {
            $periodTs = strtotime((string)$periodEndsAt);
            if ($periodTs !== false && $periodTs <= $nowTs) {
                $summary['requires_payment'] = true;
                $summary['payment_reason'] = 'Your workspace subscription has expired. Please renew to continue.';
                $summary['capacity_reason'] = 'Workspace subscription has expired. Please renew before adding members.';
                $summary['expired_by_period'] = true;
                $summary['effective_status'] = 'inactive';
                return $summary;
            }
        }

        return $summary;
    }
}

if (!function_exists('tenant_sync_workspace_subscription_status')) {
    function tenant_sync_workspace_subscription_status($pdo, $orgId)
    {
        $orgId = (int)$orgId;
        if (
            $orgId <= 0
            || !tenant_table_exists($pdo, 'organizations')
            || !tenant_table_exists($pdo, 'subscriptions')
        ) {
            return [
                'ok' => false,
                'changed' => false,
                'reason' => 'Workspace billing tables are unavailable.',
            ];
        }

        $subscription = tenant_ensure_subscription($pdo, $orgId);
        if (!$subscription) {
            return [
                'ok' => false,
                'changed' => false,
                'reason' => 'Subscription record is unavailable.',
            ];
        }

        $summary = tenant_evaluate_workspace_subscription($subscription);

        $orgStmt = $pdo->prepare("SELECT status FROM organizations WHERE id = ? LIMIT 1");
        $orgStmt->execute([$orgId]);
        $org = $orgStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$org) {
            return [
                'ok' => false,
                'changed' => false,
                'reason' => 'Workspace not found.',
            ];
        }

        $currentOrgStatus = strtolower(trim((string)($org['status'] ?? 'active')));
        $currentSubscriptionStatus = strtolower(trim((string)($subscription['status'] ?? 'active')));
        $nextOrgStatus = null;
        $nextSubscriptionStatus = null;

        if (!empty($summary['requires_payment'])) {
            if (in_array($currentOrgStatus, ['active', 'suspended'], true)) {
                $nextOrgStatus = 'inactive';
            }

            if (
                ($summary['expired_by_trial'] || $summary['expired_by_period'])
                && in_array($currentSubscriptionStatus, ['active', 'trialing', 'trial', 'suspended'], true)
            ) {
                $nextSubscriptionStatus = 'inactive';
            }
        } else {
            if ($currentOrgStatus === 'suspended') {
                $nextOrgStatus = 'active';
            }
        }

        if ($nextOrgStatus === null && $nextSubscriptionStatus === null) {
            return [
                'ok' => true,
                'changed' => false,
                'organization_status' => $currentOrgStatus,
                'subscription_status' => $currentSubscriptionStatus,
                'summary' => $summary,
            ];
        }

        try {
            $startedTransaction = !$pdo->inTransaction();
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            if ($nextSubscriptionStatus !== null && $nextSubscriptionStatus !== $currentSubscriptionStatus) {
                $subStmt = $pdo->prepare("UPDATE subscriptions SET status = ? WHERE organization_id = ?");
                $subStmt->execute([$nextSubscriptionStatus, $orgId]);
                $currentSubscriptionStatus = $nextSubscriptionStatus;
            }

            if ($nextOrgStatus !== null && $nextOrgStatus !== $currentOrgStatus) {
                $updateOrg = $pdo->prepare("UPDATE organizations SET status = ? WHERE id = ?");
                $updateOrg->execute([$nextOrgStatus, $orgId]);
                $currentOrgStatus = $nextOrgStatus;
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'ok' => true,
                'changed' => true,
                'organization_status' => $currentOrgStatus,
                'subscription_status' => $currentSubscriptionStatus,
                'summary' => $summary,
            ];
        } catch (Throwable $e) {
            if (!empty($startedTransaction) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'changed' => false,
                'reason' => 'Unable to sync workspace subscription status right now.',
                'summary' => $summary,
            ];
        }
    }
}

if (!function_exists('tenant_workspace_expired_message')) {
    function tenant_workspace_expired_message($canManageBilling = false)
    {
        return $canManageBilling
            ? 'Workspace subscription has expired. Please pay to use it again.'
            : 'Workspace subscription has expired. Please ask your workspace owner to pay to use it again.';
    }
}

if (!function_exists('tenant_workspace_inactive_message')) {
    function tenant_workspace_inactive_message()
    {
        return 'Workspace is currently turned off. Please contact your workspace admin.';
    }
}

if (!function_exists('tenant_workspace_access_state')) {
    function tenant_workspace_access_state($pdo, $orgId, $canManageBilling = false)
    {
        $orgId = (int)$orgId;
        $canManageBilling = (bool)$canManageBilling;
        $result = [
            'org_id' => $orgId,
            'organization' => null,
            'org_status' => null,
            'billing_gate' => [
                'required' => false,
                'reason' => null,
                'subscription_status' => null,
                'trial_ends_at' => null,
                'current_period_end' => null,
            ],
            'billing_required' => false,
            'can_access_workspace' => false,
            'should_route_to_billing' => false,
            'message' => null,
            'sync' => null,
        ];

        if ($orgId <= 0 || !tenant_table_exists($pdo, 'organizations')) {
            $result['message'] = 'Workspace context is missing.';
            return $result;
        }

        $result['sync'] = tenant_sync_workspace_subscription_status($pdo, $orgId);

        $orgStmt = $pdo->prepare(
            "SELECT id, name, status
             FROM organizations
             WHERE id = ?
             LIMIT 1"
        );
        $orgStmt->execute([$orgId]);
        $org = $orgStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$org) {
            $result['message'] = 'Workspace was not found.';
            return $result;
        }

        $orgStatus = strtolower(trim((string)($org['status'] ?? 'active')));
        $billingGate = tenant_workspace_requires_payment($pdo, $orgId);
        $billingRequired = !empty($billingGate['required']);
        $shouldRouteToBilling = $canManageBilling && $billingRequired;
        $canAccessWorkspace = $orgStatus === 'active' && !$billingRequired;

        $message = null;
        if ($shouldRouteToBilling) {
            $message = tenant_workspace_expired_message(true);
        } elseif (!$canAccessWorkspace) {
            $message = $billingRequired
                ? tenant_workspace_expired_message(false)
                : tenant_workspace_inactive_message();
        }

        $result['organization'] = $org;
        $result['org_status'] = $orgStatus;
        $result['billing_gate'] = $billingGate;
        $result['billing_required'] = $billingRequired;
        $result['can_access_workspace'] = $canAccessWorkspace;
        $result['should_route_to_billing'] = $shouldRouteToBilling;
        $result['message'] = $message;

        return $result;
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
        $summary = tenant_evaluate_workspace_subscription($subscription);

        return [
            'required' => !empty($summary['requires_payment']),
            'reason' => $summary['payment_reason'],
            'subscription_status' => $summary['effective_status'],
            'trial_ends_at' => $summary['trial_ends_at'],
            'current_period_end' => $summary['current_period_end'],
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
        $summary = tenant_evaluate_workspace_subscription($subscription);
        $status = $summary['effective_status'];
        $trialEndsAt = $summary['trial_ends_at'] ?? null;
        $periodEndsAt = $summary['current_period_end'] ?? null;

        $seatUsed = tenant_count_workspace_members($pdo, $orgId);
        $seatLimit = isset($subscription['seat_limit']) ? (int)$subscription['seat_limit'] : null;
        $seatsLeft = $seatLimit === null ? null : ($seatLimit - $seatUsed);

        if (!empty($summary['requires_payment'])) {
            return [
                'ok' => false,
                'reason' => (string)$summary['capacity_reason'],
                'subscription_status' => $status,
                'seat_limit' => $seatLimit,
                'seat_used' => $seatUsed,
                'seats_left' => $seatsLeft,
                'trial_ends_at' => $trialEndsAt,
                'current_period_end' => $periodEndsAt,
            ];
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

if (!function_exists('tenant_subscription_time_left_text')) {
    function tenant_subscription_time_left_text($secondsLeft)
    {
        $secondsLeft = (int)$secondsLeft;
        if ($secondsLeft <= 0) {
            return 'expired';
        }

        $days = (int)floor($secondsLeft / 86400);
        $hours = (int)floor(($secondsLeft % 86400) / 3600);
        $minutes = (int)floor(($secondsLeft % 3600) / 60);

        if ($days > 0) {
            return $days . ' day' . ($days === 1 ? '' : 's')
                . ' and '
                . $hours . ' hour' . ($hours === 1 ? '' : 's');
        }

        if ($hours > 0) {
            return $hours . ' hour' . ($hours === 1 ? '' : 's')
                . ' and '
                . $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        $minutes = max(1, $minutes);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
}

if (!function_exists('tenant_workspace_subscription_notice')) {
    function tenant_workspace_subscription_notice($pdo, $orgId, $warningDays = 15, $ignoreWindow = false)
    {
        $orgId = (int)$orgId;
        $warningDays = max(1, (int)$warningDays);
        $ignoreWindow = (bool)$ignoreWindow;

        if ($orgId <= 0) {
            return [
                'show' => false,
                'reason' => 'Workspace context is missing.',
            ];
        }

        $subscription = tenant_ensure_subscription($pdo, $orgId);
        if (!$subscription) {
            return [
                'show' => false,
                'reason' => 'Subscription record is unavailable.',
            ];
        }

        $status = strtolower(trim((string)($subscription['status'] ?? 'active')));
        $trialEndsAt = trim((string)($subscription['trial_ends_at'] ?? ''));
        $periodEndsAt = trim((string)($subscription['current_period_end'] ?? ''));
        $isTrial = in_array($status, ['trialing', 'trial'], true);

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
                'show' => false,
                'reason' => 'Subscription is already blocked.',
                'status' => $status,
            ];
        }

        $endsAt = '';
        $referenceLabel = $isTrial ? 'trial' : 'subscription';
        if ($isTrial && $trialEndsAt !== '') {
            $endsAt = $trialEndsAt;
        } elseif ($periodEndsAt !== '') {
            $endsAt = $periodEndsAt;
        } elseif ($trialEndsAt !== '') {
            $endsAt = $trialEndsAt;
            $referenceLabel = 'trial';
        }

        if ($endsAt === '') {
            return [
                'show' => false,
                'reason' => 'No billing deadline is set.',
                'status' => $status,
            ];
        }

        $endsAtTs = strtotime($endsAt);
        if ($endsAtTs === false) {
            return [
                'show' => false,
                'reason' => 'Billing deadline is invalid.',
                'status' => $status,
            ];
        }

        $secondsLeft = $endsAtTs - time();
        if ($secondsLeft <= 0) {
            return [
                'show' => false,
                'reason' => 'Subscription is already expired.',
                'status' => $status,
                'ends_at' => $endsAt,
                'is_trial' => $isTrial,
                'reference_label' => $referenceLabel,
            ];
        }

        $warningWindowSeconds = $warningDays * 86400;
        if (!$ignoreWindow && $secondsLeft > $warningWindowSeconds) {
            return [
                'show' => false,
                'reason' => 'Subscription is not yet within the reminder window.',
                'status' => $status,
                'ends_at' => $endsAt,
                'is_trial' => $isTrial,
                'reference_label' => $referenceLabel,
            ];
        }

        return [
            'show' => true,
            'reason' => null,
            'status' => $status,
            'ends_at' => $endsAt,
            'ends_at_display' => date('M j, Y g:i A', $endsAtTs),
            'seconds_left' => $secondsLeft,
            'time_left_text' => tenant_subscription_time_left_text($secondsLeft),
            'warning_days' => $warningDays,
            'ignore_window' => $ignoreWindow,
            'is_trial' => $isTrial,
            'reference_label' => $referenceLabel,
        ];
    }
}
