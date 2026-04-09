<?php

require_once __DIR__ . '/../../inc/tenant.php';

if (!function_exists('payroll_deduction_types')) {
    function payroll_deduction_types()
    {
        return [
            'cash_advance' => 'Cash Advance',
            'loan' => 'Loan',
            'laptop' => 'Laptop',
            'smartphone' => 'Smartphone',
            'uniform' => 'Uniform',
            'other' => 'Other',
        ];
    }
}

if (!function_exists('payroll_deduction_normalize_type')) {
    function payroll_deduction_normalize_type($value)
    {
        $types = payroll_deduction_types();
        $key = strtolower(trim((string)$value));
        return isset($types[$key]) ? $key : 'other';
    }
}

if (!function_exists('payroll_deduction_amount_modes')) {
    function payroll_deduction_amount_modes()
    {
        return [
            'fixed' => 'Fixed amount',
            'percent' => 'Percent of gross',
        ];
    }
}

if (!function_exists('payroll_deduction_normalize_amount_mode')) {
    function payroll_deduction_normalize_amount_mode($value)
    {
        $modes = payroll_deduction_amount_modes();
        $key = strtolower(trim((string)$value));
        return isset($modes[$key]) ? $key : 'fixed';
    }
}

if (!function_exists('payroll_deduction_period_labels')) {
    function payroll_deduction_period_labels()
    {
        return [
            'once' => 'One-time',
            'monthly' => 'Every month',
        ];
    }
}

if (!function_exists('payroll_deduction_normalize_period')) {
    function payroll_deduction_normalize_period($value)
    {
        $periods = payroll_deduction_period_labels();
        $key = strtolower(trim((string)$value));
        return isset($periods[$key]) ? $key : 'once';
    }
}

if (!function_exists('payroll_government_deduction_catalog')) {
    function payroll_government_deduction_catalog()
    {
        return [
            'sss' => [
                'setting_key' => 'sss_enabled',
                'label' => 'SSS',
                'description' => '4.5% of gross',
                'rate' => 0.045,
                'cap' => 1350,
            ],
            'philhealth' => [
                'setting_key' => 'philhealth_enabled',
                'label' => 'PhilHealth',
                'description' => '2% of gross',
                'rate' => 0.02,
                'cap' => 1800,
            ],
            'pagibig' => [
                'setting_key' => 'pagibig_enabled',
                'label' => 'Pag-IBIG',
                'description' => '2% of gross',
                'rate' => 0.02,
                'cap' => 200,
            ],
            'withholding_tax' => [
                'setting_key' => 'withholding_tax_enabled',
                'label' => 'Withholding tax',
                'description' => 'BIR progressive',
                'rate' => null,
                'cap' => null,
            ],
        ];
    }
}

if (!function_exists('payroll_government_settings_defaults')) {
    function payroll_government_settings_defaults()
    {
        return [
            'sss_enabled' => 1,
            'philhealth_enabled' => 1,
            'pagibig_enabled' => 1,
            'withholding_tax_enabled' => 1,
        ];
    }
}

if (!function_exists('payroll_deduction_ensure_schema')) {
    function payroll_deduction_ensure_schema($pdo)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS payroll_deductions (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    deduction_date DATE NOT NULL,
                    deduction_type VARCHAR(30) NOT NULL DEFAULT 'other',
                    title VARCHAR(150) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    amount_mode VARCHAR(20) NOT NULL DEFAULT 'fixed',
                    apply_period VARCHAR(20) NOT NULL DEFAULT 'once',
                    notes TEXT DEFAULT NULL,
                    created_by INT NOT NULL,
                    organization_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_payroll_deductions_user_date (user_id, deduction_date),
                    KEY idx_payroll_deductions_org (organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            // Best effort. Payroll deductions remain unavailable if DDL fails.
        }

        if (tenant_table_exists($pdo, 'payroll_deductions')) {
            try {
                if (!tenant_column_exists($pdo, 'payroll_deductions', 'amount_mode')) {
                    $pdo->exec("ALTER TABLE payroll_deductions ADD COLUMN amount_mode VARCHAR(20) NOT NULL DEFAULT 'fixed' AFTER amount");
                }
                if (!tenant_column_exists($pdo, 'payroll_deductions', 'apply_period')) {
                    $pdo->exec("ALTER TABLE payroll_deductions ADD COLUMN apply_period VARCHAR(20) NOT NULL DEFAULT 'once' AFTER amount_mode");
                }
            } catch (Throwable $e) {
                // Best effort. Older rows default to fixed / once semantics.
            }
        }

        $ready = tenant_table_exists($pdo, 'payroll_deductions');
        return $ready;
    }
}

if (!function_exists('payroll_government_settings_ensure_schema')) {
    function payroll_government_settings_ensure_schema($pdo)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS payroll_government_settings (
                    id INT NOT NULL AUTO_INCREMENT,
                    organization_id INT DEFAULT NULL,
                    sss_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    philhealth_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    pagibig_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    withholding_tax_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    updated_by INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_payroll_gov_settings_org (organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            // Best effort. Defaults will be used if the table is unavailable.
        }

        $ready = tenant_table_exists($pdo, 'payroll_government_settings');
        return $ready;
    }
}

if (!function_exists('payroll_government_settings_get')) {
    function payroll_government_settings_get($pdo)
    {
        $defaults = payroll_government_settings_defaults();
        if (!payroll_government_settings_ensure_schema($pdo)) {
            return $defaults;
        }

        $orgId = tenant_get_current_org_id();
        if (tenant_column_exists($pdo, 'payroll_government_settings', 'organization_id') && $orgId) {
            $stmt = $pdo->prepare(
                "SELECT sss_enabled, philhealth_enabled, pagibig_enabled, withholding_tax_enabled
                 FROM payroll_government_settings
                 WHERE organization_id = ?
                 LIMIT 1"
            );
            $stmt->execute([(int)$orgId]);
        } else {
            $stmt = $pdo->query(
                "SELECT sss_enabled, philhealth_enabled, pagibig_enabled, withholding_tax_enabled
                 FROM payroll_government_settings
                 ORDER BY id ASC
                 LIMIT 1"
            );
        }

        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return $defaults;
        }

        foreach ($defaults as $key => $defaultValue) {
            $defaults[$key] = !empty($row[$key]) ? 1 : 0;
        }
        return $defaults;
    }
}

if (!function_exists('payroll_government_settings_save')) {
    function payroll_government_settings_save($pdo, array $values, $adminId)
    {
        if (!payroll_government_settings_ensure_schema($pdo)) {
            return ['ok' => false, 'error' => 'Government deduction settings table is unavailable.'];
        }

        $adminId = (int)$adminId;
        if ($adminId <= 0) {
            return ['ok' => false, 'error' => 'Invalid admin account.'];
        }

        $defaults = payroll_government_settings_defaults();
        $payload = [];
        foreach ($defaults as $key => $defaultValue) {
            $payload[$key] = !empty($values[$key]) ? 1 : 0;
        }

        $orgId = tenant_get_current_org_id();
        $existingId = 0;

        if (tenant_column_exists($pdo, 'payroll_government_settings', 'organization_id') && $orgId) {
            $stmt = $pdo->prepare("SELECT id FROM payroll_government_settings WHERE organization_id = ? LIMIT 1");
            $stmt->execute([(int)$orgId]);
            $existingId = (int)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("SELECT id FROM payroll_government_settings ORDER BY id ASC LIMIT 1");
            $existingId = $stmt ? (int)$stmt->fetchColumn() : 0;
        }

        if ($existingId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE payroll_government_settings
                 SET sss_enabled = ?, philhealth_enabled = ?, pagibig_enabled = ?, withholding_tax_enabled = ?, updated_by = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $payload['sss_enabled'],
                $payload['philhealth_enabled'],
                $payload['pagibig_enabled'],
                $payload['withholding_tax_enabled'],
                $adminId,
                $existingId,
            ]);
            return ['ok' => true, 'updated' => true];
        }

        if (tenant_column_exists($pdo, 'payroll_government_settings', 'organization_id') && $orgId) {
            $stmt = $pdo->prepare(
                "INSERT INTO payroll_government_settings
                 (organization_id, sss_enabled, philhealth_enabled, pagibig_enabled, withholding_tax_enabled, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                (int)$orgId,
                $payload['sss_enabled'],
                $payload['philhealth_enabled'],
                $payload['pagibig_enabled'],
                $payload['withholding_tax_enabled'],
                $adminId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO payroll_government_settings
                 (sss_enabled, philhealth_enabled, pagibig_enabled, withholding_tax_enabled, updated_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $payload['sss_enabled'],
                $payload['philhealth_enabled'],
                $payload['pagibig_enabled'],
                $payload['withholding_tax_enabled'],
                $adminId,
            ]);
        }

        return ['ok' => true, 'created' => true];
    }
}

if (!function_exists('payroll_withholding_tax')) {
    function payroll_withholding_tax($taxablePay)
    {
        $taxablePay = max(0, (float)$taxablePay);

        if ($taxablePay <= 20833) {
            return 0;
        }
        if ($taxablePay <= 33333) {
            return ($taxablePay - 20833) * 0.15;
        }
        if ($taxablePay <= 66667) {
            return 1875 + (($taxablePay - 33333) * 0.20);
        }
        if ($taxablePay <= 166667) {
            return 8541.80 + (($taxablePay - 66667) * 0.25);
        }
        if ($taxablePay <= 666667) {
            return 33541.80 + (($taxablePay - 166667) * 0.30);
        }

        return 183541.80 + (($taxablePay - 666667) * 0.35);
    }
}

if (!function_exists('payroll_compute_government_deductions')) {
    function payroll_compute_government_deductions($grossPay, array $settings = [])
    {
        $grossPay = max(0, round((float)$grossPay, 2));
        $catalog = payroll_government_deduction_catalog();
        $resolvedSettings = array_merge(payroll_government_settings_defaults(), $settings);
        $items = [];

        $sss = 0;
        if (!empty($resolvedSettings['sss_enabled'])) {
            $sss = min($grossPay * 0.045, 1350);
            $items[] = [
                'key' => 'sss',
                'label' => $catalog['sss']['label'],
                'description' => $catalog['sss']['description'],
                'amount' => round($sss, 2),
            ];
        }

        $philhealth = 0;
        if (!empty($resolvedSettings['philhealth_enabled'])) {
            $philhealth = min($grossPay * 0.02, 1800);
            $items[] = [
                'key' => 'philhealth',
                'label' => $catalog['philhealth']['label'],
                'description' => $catalog['philhealth']['description'],
                'amount' => round($philhealth, 2),
            ];
        }

        $pagibig = 0;
        if (!empty($resolvedSettings['pagibig_enabled'])) {
            $pagibig = min($grossPay * 0.02, 200);
            $items[] = [
                'key' => 'pagibig',
                'label' => $catalog['pagibig']['label'],
                'description' => $catalog['pagibig']['description'],
                'amount' => round($pagibig, 2),
            ];
        }

        $taxableBase = max(0, $grossPay - $sss - $philhealth - $pagibig);
        $withholdingTax = 0;
        if (!empty($resolvedSettings['withholding_tax_enabled'])) {
            $withholdingTax = payroll_withholding_tax($taxableBase);
            $items[] = [
                'key' => 'withholding_tax',
                'label' => $catalog['withholding_tax']['label'],
                'description' => $catalog['withholding_tax']['description'],
                'amount' => round($withholdingTax, 2),
            ];
        }

        $total = 0;
        foreach ($items as $item) {
            $total += (float)($item['amount'] ?? 0);
        }

        return [
            'items' => $items,
            'taxable_base' => round($taxableBase, 2),
            'total' => round($total, 2),
        ];
    }
}

if (!function_exists('payroll_deduction_compute_amount')) {
    function payroll_deduction_compute_amount(array $item, $grossPay)
    {
        $grossPay = max(0, round((float)$grossPay, 2));
        $amount = max(0, round((float)($item['amount'] ?? 0), 2));
        $mode = payroll_deduction_normalize_amount_mode($item['amount_mode'] ?? 'fixed');

        if ($mode === 'percent') {
            return round($grossPay * ($amount / 100), 2);
        }

        return $amount;
    }
}

if (!function_exists('payroll_deduction_resolve_items')) {
    function payroll_deduction_resolve_items(array $items, $grossPay)
    {
        $resolved = [];
        foreach ($items as $item) {
            $computedAmount = payroll_deduction_compute_amount($item, $grossPay);
            if ($computedAmount <= 0) {
                continue;
            }

            $item['amount_mode'] = payroll_deduction_normalize_amount_mode($item['amount_mode'] ?? 'fixed');
            $item['apply_period'] = payroll_deduction_normalize_period($item['apply_period'] ?? 'once');
            $item['computed_amount'] = $computedAmount;
            $resolved[] = $item;
        }

        return $resolved;
    }
}

if (!function_exists('payroll_deduction_create')) {
    function payroll_deduction_create($pdo, $userId, $deductionDate, $deductionType, $title, $amount, $notes, $adminId, $amountMode = 'fixed', $applyPeriod = 'once')
    {
        if (!payroll_deduction_ensure_schema($pdo)) {
            return ['ok' => false, 'error' => 'Payroll deductions table is unavailable.'];
        }

        $userId = (int)$userId;
        $adminId = (int)$adminId;
        $deductionDate = trim((string)$deductionDate);
        $deductionType = payroll_deduction_normalize_type($deductionType);
        $title = trim((string)$title);
        $notes = trim((string)$notes);
        $amount = round((float)$amount, 2);
        $amountMode = payroll_deduction_normalize_amount_mode($amountMode);
        $applyPeriod = payroll_deduction_normalize_period($applyPeriod);
        $orgId = tenant_get_current_org_id();

        if ($userId <= 0 || $adminId <= 0 || $deductionDate === '') {
            return ['ok' => false, 'error' => 'Invalid payroll deduction request.'];
        }

        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Deduction amount must be greater than zero.'];
        }

        if ($title === '') {
            $types = payroll_deduction_types();
            $title = $types[$deductionType] ?? 'Deduction';
        }

        $notes = $notes !== '' ? substr(strip_tags($notes), 0, 500) : null;

        if (tenant_column_exists($pdo, 'payroll_deductions', 'organization_id') && $orgId) {
            $sql = "INSERT INTO payroll_deductions
                    (user_id, deduction_date, deduction_type, title, amount, amount_mode, apply_period, notes, created_by, organization_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$userId, $deductionDate, $deductionType, $title, $amount, $amountMode, $applyPeriod, $notes, $adminId, $orgId];
        } else {
            $sql = "INSERT INTO payroll_deductions
                    (user_id, deduction_date, deduction_type, title, amount, amount_mode, apply_period, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$userId, $deductionDate, $deductionType, $title, $amount, $amountMode, $applyPeriod, $notes, $adminId];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true];
    }
}

if (!function_exists('payroll_deduction_delete')) {
    function payroll_deduction_delete($pdo, $deductionId)
    {
        if (!payroll_deduction_ensure_schema($pdo)) {
            return ['ok' => false, 'error' => 'Payroll deductions table is unavailable.'];
        }

        $deductionId = (int)$deductionId;
        if ($deductionId <= 0) {
            return ['ok' => false, 'error' => 'Invalid payroll deduction selected.'];
        }

        $sql = "DELETE FROM payroll_deductions WHERE id = ?";
        $params = [$deductionId];
        $scope = tenant_get_scope($pdo, 'payroll_deductions');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true];
    }
}

if (!function_exists('payroll_deduction_get_range_map')) {
    function payroll_deduction_get_range_map($pdo, array $userIds, $startDate, $endDate, array $grossPayByUser = [])
    {
        $map = [];
        $items = payroll_deduction_get_range_items($pdo, $userIds, $startDate, $endDate);
        foreach ($items as $item) {
            $uid = (int)($item['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $computedAmount = payroll_deduction_compute_amount($item, $grossPayByUser[$uid] ?? 0);
            if (!isset($map[$uid])) {
                $map[$uid] = ['user_id' => $uid, 'amount_total' => 0];
            }
            $map[$uid]['amount_total'] += $computedAmount;
        }

        return $map;
    }
}

if (!function_exists('payroll_deduction_get_items')) {
    function payroll_deduction_get_items($pdo, $userId, $startDate, $endDate, $grossPay = null)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return [];
        }

        $items = payroll_deduction_get_range_items($pdo, [$userId], $startDate, $endDate);
        if ($grossPay === null) {
            return $items;
        }

        return payroll_deduction_resolve_items($items, $grossPay);
    }
}

if (!function_exists('payroll_deduction_get_range_items')) {
    function payroll_deduction_get_range_items($pdo, array $userIds, $startDate, $endDate)
    {
        $items = [];
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (!$userIds || !payroll_deduction_ensure_schema($pdo)) {
            return $items;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT id, user_id, deduction_date, deduction_type, title, amount, amount_mode, apply_period, notes, created_at
                FROM payroll_deductions
                WHERE user_id IN ($placeholders)
                  AND (
                    (apply_period = 'monthly' AND deduction_date <= ?)
                    OR
                    (apply_period <> 'monthly' AND deduction_date BETWEEN ? AND ?)
                  )";

        $params = array_merge($userIds, [$endDate, $startDate, $endDate]);
        $scope = tenant_get_scope($pdo, 'payroll_deductions', '', 'AND', 'organization_id');
        $sql .= $scope['sql'];
        $params = array_merge($params, $scope['params']);
        $sql .= " ORDER BY deduction_date DESC, id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
