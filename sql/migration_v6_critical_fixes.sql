-- ============================================================
-- Aleltu Farm EIFMS -- Migration v6: Critical Schema Fixes
-- Run ONCE after migration_v2 through migration_v5.
-- Safe: all ALTER TABLE use IF NOT EXISTS.
-- Back up your database before running this.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- FIX 1: Rename audit_logs to audit_log
-- log_activity() writes to audit_log but schema created audit_logs
DROP TABLE IF EXISTS audit_log;
RENAME TABLE audit_logs TO audit_log;

-- FIX 2: dist_dispatches - align with dispatch_create()
ALTER TABLE dist_dispatches
    MODIFY COLUMN driver_id INT NULL,
    ADD COLUMN IF NOT EXISTS driver_name VARCHAR(150) NULL AFTER driver_id,
    ADD COLUMN IF NOT EXISTS unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER quantity_dispatched,
    ADD COLUMN IF NOT EXISTS total_price DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER unit_price,
    ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_price,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid' AFTER amount_paid,
    ADD COLUMN IF NOT EXISTS payment_notes TEXT NULL AFTER payment_status;

-- FIX 3: dist_vehicles - driver columns
ALTER TABLE dist_vehicles
    ADD COLUMN IF NOT EXISTS driver_name VARCHAR(150) NULL AFTER vehicle_type,
    ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(30) NULL AFTER driver_name;

-- FIX 4: dist_dispatch_payments table (was never created)
CREATE TABLE IF NOT EXISTS dist_dispatch_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    reference_no VARCHAR(100) NULL,
    notes TEXT NULL,
    received_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispatch_id) REFERENCES dist_dispatches(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_dispatch (dispatch_id)
) ENGINE=InnoDB;

-- FIX 5: fin_purchase_orders - amount_paid / payment_status
ALTER TABLE fin_purchase_orders
    ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_amount,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid' AFTER amount_paid,
    MODIFY COLUMN status ENUM('draft','pending_approval','approved','sent','pending_receipt','received','cancelled') NOT NULL DEFAULT 'draft';

-- FIX 6: fin_purchase_requests - material_request_id
ALTER TABLE fin_purchase_requests
    ADD COLUMN IF NOT EXISTS material_request_id INT NULL AFTER stock_item_id;

-- FIX 7: wh_stock_items - partition_code
ALTER TABLE wh_stock_items
    ADD COLUMN IF NOT EXISTS partition_code ENUM('finished_products','feed_nutrition','materials_supplies') NOT NULL DEFAULT 'materials_supplies' AFTER product_class;

UPDATE wh_stock_items SET partition_code = 'finished_products' WHERE product_class = 'milk';
UPDATE wh_stock_items SET partition_code = 'feed_nutrition'    WHERE product_class = 'feed';
UPDATE wh_stock_items SET partition_code = 'finished_products' WHERE sku = 'MILK-RAW';
UPDATE wh_stock_items SET partition_code = 'feed_nutrition'    WHERE sku IN ('FEED-LAYER','FEED-DAIRY-MIX');
UPDATE wh_stock_items SET partition_code = 'finished_products' WHERE sku = 'FISH-TILAPIA';

-- FIX 8: wh_stock_batches - unit_cost for inventory valuation
ALTER TABLE wh_stock_batches
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER quantity_on_hand;

-- FIX 9: fin_bank_accounts - account_type
ALTER TABLE fin_bank_accounts
    ADD COLUMN IF NOT EXISTS account_type ENUM('bank','cash','mobile') NOT NULL DEFAULT 'bank' AFTER bank_name;

UPDATE fin_bank_accounts SET account_type = 'bank' WHERE bank_name = 'Oromia Bank';
UPDATE fin_bank_accounts SET account_type = 'cash' WHERE bank_name = 'Cash';

INSERT INTO fin_bank_accounts (bank_name, account_type, account_name, account_number, opening_balance)
SELECT 'Cash', 'cash', 'Petty Cash (On Hand)', 'CASH-001', 0.00
WHERE NOT EXISTS (SELECT 1 FROM fin_bank_accounts WHERE account_type = 'cash');

-- FIX 10: notifications - link_url + per-user read table
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NULL AFTER message;

CREATE TABLE IF NOT EXISTS notification_reads (
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- FIX 11: dept_material_requests - missing columns
ALTER TABLE dept_material_requests
    ADD COLUMN IF NOT EXISTS custom_item_name VARCHAR(150) NULL AFTER stock_item_id,
    ADD COLUMN IF NOT EXISTS note TEXT NULL AFTER requested_quantity,
    ADD COLUMN IF NOT EXISTS received_confirmed_at DATETIME NULL AFTER fulfilled_by,
    ADD COLUMN IF NOT EXISTS received_confirmed_by INT NULL AFTER received_confirmed_at,
    MODIFY COLUMN status ENUM(
        'pending','partially_issued','fulfilled',
        'shortage_purchase_raised','sent_to_department',
        'completed','rejected'
    ) NOT NULL DEFAULT 'pending';

-- FIX 12: wh_store_receiving - material_request_id + stored status
ALTER TABLE wh_store_receiving
    ADD COLUMN IF NOT EXISTS material_request_id INT NULL AFTER source_reference,
    MODIFY COLUMN status ENUM('pending_receipt','stored','sent_to_department','received_full','received_variance','rejected') NOT NULL DEFAULT 'pending_receipt';

-- FIX 13: vet_vaccination_records - missing columns
ALTER TABLE vet_vaccination_records
    ADD COLUMN IF NOT EXISTS scheduled_time TIME NULL DEFAULT '09:00:00' AFTER scheduled_date,
    ADD COLUMN IF NOT EXISTS batch_number VARCHAR(100) NULL AFTER vaccine_name,
    ADD COLUMN IF NOT EXISTS dosage VARCHAR(100) NULL AFTER batch_number,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER administered_date,
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS reminder_1day_sent TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS reminder_2hr_sent TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS reminder_1hr_sent TINYINT(1) NOT NULL DEFAULT 0;

-- FIX 14: vet_disease_incidents - photo_path
ALTER TABLE vet_disease_incidents
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER notes;

-- FIX 15: vet_health_records - photo_path
ALTER TABLE vet_health_records
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER treatment;

-- FIX 16: wh_stock_adjustments table (was never created)
CREATE TABLE IF NOT EXISTS wh_stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    adjustment_type ENUM('increase','decrease') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    reason TEXT NOT NULL,
    adjusted_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (adjusted_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- FIX 17: employees - photo_path
ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER phone;

-- FIX 18: users - api_token
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS api_token VARCHAR(64) NULL UNIQUE AFTER password;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Migration v6 complete. All critical schema gaps fixed.' AS result;
