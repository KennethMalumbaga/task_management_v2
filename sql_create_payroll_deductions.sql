CREATE TABLE payroll_deductions (
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
);

CREATE TABLE payroll_government_settings (
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
);
