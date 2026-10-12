-- ============================================================
-- Aleltu Farm EIFMS - Finance module (SAD section 2.7 / 5.7)
-- Import via phpMyAdmin -> aleltu_eifms database -> Import tab,
-- AFTER sql/schema.sql and sql/002_employee_management.sql.
-- Skip if doing a brand-new import of the updated schema.sql,
-- which already includes these tables.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS fin_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL DEFAULT 'Oromia Bank',
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Cash Flow ledger: every inflow/outflow lands here, unit-tagged (BR-FN01).
CREATE TABLE IF NOT EXISTS fin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NULL,
    direction ENUM('inflow','outflow') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    unit_tag ENUM('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead') NOT NULL,
    category VARCHAR(50) NULL,          -- expense | payroll | purchase | sale_settlement | other
    source_table VARCHAR(50) NULL,      -- e.g. fin_expenses, fin_payslips
    source_id INT NULL,
    transaction_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    recorded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_account_id) REFERENCES fin_bank_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_date_unit (transaction_date, unit_tag)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fin_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_tag ENUM('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead') NOT NULL,
    category VARCHAR(80) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    expense_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    requested_by INT NOT NULL,
    approved_by INT NULL,
    status ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fin_purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_item_id INT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    justification VARCHAR(255) NULL,
    requested_by INT NOT NULL,
    status ENUM('pending','approved','rejected','converted_to_po') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fin_purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_request_id INT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    stock_item_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL,
    -- BR-FN02: above a configurable threshold requires approval before "sent".
    status ENUM('draft','pending_approval','approved','sent','received','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_request_id) REFERENCES fin_purchase_requests(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fin_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_tag ENUM('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    planned_amount DECIMAL(14,2) NOT NULL,
    proposed_by INT NOT NULL,
    approved_by INT NULL,
    status ENUM('draft','proposed','approved') NOT NULL DEFAULT 'draft',
    FOREIGN KEY (proposed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fin_payroll_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    -- BR-FN03: cannot finalize until attendance for the period is locked.
    status ENUM('draft','attendance_locked','finalized') NOT NULL DEFAULT 'draft',
    processed_by INT NOT NULL,
    finalized_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fin_payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL,
    days_present INT NOT NULL DEFAULT 0,
    net_pay DECIMAL(12,2) NOT NULL,
    UNIQUE KEY uniq_payslip (payroll_run_id, employee_id),
    FOREIGN KEY (payroll_run_id) REFERENCES fin_payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB;

INSERT INTO fin_bank_accounts (bank_name, account_name, account_number, opening_balance) VALUES
('Oromia Bank', 'Aleltu Farm Company - Main Account', '1000200030004000', 500000.00);

SET FOREIGN_KEY_CHECKS = 1;
