-- Aleltu Farm EIFMS - Migration v2 Schema Extensions

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Granular Role-Based Access Control (RBAC)
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action_code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    UNIQUE KEY uniq_perm (module, action_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Store Receiving (Pending Receipts with Real Sent vs Received Quantities)
CREATE TABLE IF NOT EXISTS wh_store_receiving (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_code VARCHAR(50) NOT NULL UNIQUE,
    source_type ENUM('dairy','poultry','fish','feed_production','purchase') NOT NULL,
    source_reference VARCHAR(100) NULL,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    sent_quantity DECIMAL(12,2) NOT NULL,
    received_quantity DECIMAL(12,2) NULL,
    variance_quantity DECIMAL(12,2) GENERATED ALWAYS AS (sent_quantity - COALESCE(received_quantity, 0)) STORED,
    variance_reason VARCHAR(255) NULL,
    status ENUM('pending_receipt','received_full','received_variance','rejected') NOT NULL DEFAULT 'pending_receipt',
    sent_by INT NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_by INT NULL,
    received_at DATETIME NULL,
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (sent_by) REFERENCES users(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 3. Department Material Requisitions (Store Stock Check & Shortage Purchase Request)
CREATE TABLE IF NOT EXISTS dept_material_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    requested_quantity DECIMAL(12,2) NOT NULL,
    issued_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    shortage_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','partially_issued','fulfilled','shortage_purchase_raised','rejected') NOT NULL DEFAULT 'pending',
    purchase_request_id INT NULL,
    requested_by INT NOT NULL,
    fulfilled_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (fulfilled_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 4. Target Notification Scoping
ALTER TABLE notifications 
    ADD COLUMN target_user_id INT NULL AFTER priority,
    ADD COLUMN target_role_id INT NULL AFTER target_user_id,
    ADD COLUMN target_department_id INT NULL AFTER target_role_id;

-- 5. Payroll Partial Payments Ledger
ALTER TABLE fin_payslips
    ADD COLUMN daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER base_salary,
    ADD COLUMN total_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER net_pay,
    ADD COLUMN remaining_balance DECIMAL(12,2) GENERATED ALWAYS AS (net_pay - total_paid) STORED AFTER total_paid;

CREATE TABLE IF NOT EXISTS fin_payroll_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payslip_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    notes VARCHAR(255) NULL,
    recorded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payslip_id) REFERENCES fin_payslips(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
