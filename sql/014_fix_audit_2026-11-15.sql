-- 014_fix_audit_2026-11-15.sql
-- Aleltu Farm EIMS — Batch 1 (Critical) schema fixes from the 2026-11-15
-- live audit and code reviews (Parts 1 + 2).
--
-- Apply ONCE via phpMyAdmin (Import tab) on the LIVE database, AFTER sql/013.
-- Every statement is idempotent, so re-running this file is safe:
--   * ADD COLUMN ... IF NOT EXISTS is a no-op on re-run.
--   * CREATE TABLE IF NOT EXISTS is a no-op on re-run.
--   * Every backfill below ASSIGNS an absolute value (never adds to an
--     existing value), so it cannot double-count on re-run.
--
-- What it fixes (live symptom in brackets):
--   * fin_bank_accounts.current_balance .......... [expense save SQL error,
--     blank finance dashboard + transfer pages, bank-account add failure]
--   * wh_stock_movements (new table) .............. [blank income statement,
--     HTTP 500 admin analytics, EVERY stock receive/issue fails]
--   * wh_stock_items.quantity_on_hand ............. [every stock receive/issue
--     fails even after wh_stock_movements exists]
--   * poultry_flocks.current_count ................ [blank poultry dashboard]
--   * vet_treatments (new) + vet_health_records.cost
--     + vet_health_records.photo_path ............. [blank vet health page,
--     treatment save fails]
--   * feed_formulas / feed_formula_ingredients /
--     feed_production_batches / feed_batch_ingredients
--     (new tables) ................................ [HTTP 500 feed formulas
--     + feed batches pages]
--   * poultry_egg_collections.transferred_at ...... [blank egg entry page;
--     same change as sql/013, repeated here so this file alone is enough]
--   * dept_material_requests.unit_cost / total_cost [warehouse fulfill and
--     direct-issue fatal]
--   * attendance.notes / recorded_by .............. [API attendance POST fails]
--   * fin_cheques (new table) ..................... [cheques on a fresh
--     install; the live DB already has this table, so this is a no-op there]
--
-- Batch 2 (2026-11-15) adds: users lockout/token-expiry columns,
-- wh_stock_items partition_code/department_scope,
-- wh_stock_batches partition_code/unit_cost, the dept_material_requests
-- status enum values, and the fin_payslips columns (including converting
-- the GENERATED remaining_balance to a plain column the code can write).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Bank account running balance (code reads/writes it everywhere).
ALTER TABLE `fin_bank_accounts`
    ADD COLUMN IF NOT EXISTS `current_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `opening_balance`;

-- Backfill (idempotent absolute assignment, mirrors
-- finance_balance_by_method(): opening balance + inflows - outflows,
-- excluding the opening-balance seed transaction itself).
UPDATE `fin_bank_accounts` a
SET a.`current_balance` = a.`opening_balance` + COALESCE((
    SELECT SUM(CASE WHEN t.`direction` = 'inflow' THEN t.`amount` ELSE -t.`amount` END)
    FROM `fin_transactions` t
    WHERE t.`bank_account_id` = a.`id` AND t.`category` <> 'opening_balance'
), 0);

-- 2. Unified stock movements ledger (written by stock_receive(),
-- store_issue_stock() and dispatch returns; read by the income statement
-- COGS query and admin analytics).
CREATE TABLE IF NOT EXISTS `wh_stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_item_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL,
    `stock_batch_id` INT NULL,
    `movement_type` VARCHAR(20) NOT NULL,
    `quantity` DECIMAL(14,2) NOT NULL,
    `unit_cost` DECIMAL(14,4) NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` INT NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_item` (`stock_item_id`),
    INDEX `idx_batch` (`stock_batch_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB;

-- 3. Per-item stock total, maintained by warehouse_sync_item_total().
ALTER TABLE `wh_stock_items`
    ADD COLUMN IF NOT EXISTS `quantity_on_hand` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `is_perishable`;

-- Backfill (idempotent absolute assignment, same formula as
-- warehouse_sync_item_total()).
UPDATE `wh_stock_items` i
SET i.`quantity_on_hand` = COALESCE((
    SELECT SUM(b.`quantity_on_hand`)
    FROM `wh_stock_batches` b
    WHERE b.`stock_item_id` = i.`id` AND b.`quantity_on_hand` > 0
), 0);

-- 4. Live flock count (read by the poultry dashboard; written by flock
-- create / mortality flows).
ALTER TABLE `poultry_flocks`
    ADD COLUMN IF NOT EXISTS `current_count` INT NOT NULL DEFAULT 0 AFTER `initial_population`;

-- Backfill (idempotent absolute assignment, same formula the code uses for
-- live_population).
UPDATE `poultry_flocks`
SET `current_count` = GREATEST(0, `initial_population` - `cumulative_mortality` - `cumulative_culled_sold` + `cumulative_transfers_in`);

-- 5. Veterinary treatments (written by vet_treatment_administer(); joined
-- by modules/veterinary/health.php).
CREATE TABLE IF NOT EXISTS `vet_treatments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_type` VARCHAR(30) NOT NULL,
    `subject_id` INT NOT NULL,
    `record_date` DATE NOT NULL,
    `condition_name` VARCHAR(150) NULL,
    `treatment` TEXT NULL,
    `medication_stock_item_id` INT NULL,
    `medication_quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `treatment_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `photo_path` VARCHAR(255) NULL,
    `vet_id` INT NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_subject` (`subject_type`, `subject_id`)
) ENGINE=InnoDB;

-- vet_health_records.cost exists in the live dump but in no sql/ file;
-- photo_path likewise (live dump + migration_v6 only).
ALTER TABLE `vet_health_records`
    ADD COLUMN IF NOT EXISTS `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `treatment`,
    ADD COLUMN IF NOT EXISTS `photo_path` VARCHAR(255) NULL AFTER `cost`;

-- 6. Feed formulas and production batches (whole feature's DDL was missing).
CREATE TABLE IF NOT EXISTS `feed_formulas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NULL,
    `target_animal` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` TEXT NULL,
    `cost_per_kg` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_code` (`code`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `feed_formula_ingredients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `formula_id` INT NOT NULL,
    `stock_item_id` INT NOT NULL,
    `percentage` DECIMAL(5,2) NOT NULL,
    `notes` TEXT NULL,
    INDEX `idx_formula` (`formula_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `feed_production_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_code` VARCHAR(50) NULL,
    `formula_id` INT NOT NULL,
    `target_stock_item_id` INT NOT NULL,
    `planned_quantity_kg` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `produced_quantity_kg` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `produced_by` INT NULL,
    `production_date` DATE NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    `total_raw_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `unit_cost_per_kg` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_formula` (`formula_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `feed_batch_ingredients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `production_batch_id` INT NOT NULL,
    `stock_item_id` INT NOT NULL,
    `required_quantity_kg` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `actual_quantity_kg` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `unit_cost` DECIMAL(14,4) NULL,
    `total_cost` DECIMAL(14,2) NULL,
    INDEX `idx_batch` (`production_batch_id`)
) ENGINE=InnoDB;

-- 7. Daily egg transfer tracking (same change as sql/013).
ALTER TABLE `poultry_egg_collections`
    ADD COLUMN IF NOT EXISTS `transferred_at` DATETIME NULL DEFAULT NULL AFTER `recorded_by`;

-- 8. Fulfilment costs (written by dept_material_request_fulfill() and
-- warehouse_direct_issue_to_dept()).
ALTER TABLE `dept_material_requests`
    ADD COLUMN IF NOT EXISTS `unit_cost` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER `shortage_quantity`,
    ADD COLUMN IF NOT EXISTS `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `unit_cost`;

-- 9. Attendance extra fields (written by the API attendance endpoint).
ALTER TABLE `attendance`
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `recorded_by` INT NULL AFTER `notes`;

-- 10. Cheques (mirrors the live dump; no CREATE TABLE existed in any sql/ file).
CREATE TABLE IF NOT EXISTS `fin_cheques` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cheque_type` ENUM('issued','received') NOT NULL,
    `cheque_number` VARCHAR(60) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_account_id` INT NULL,
    `payee_or_drawer` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `issue_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `status` ENUM('pending','cleared','bounced','cancelled') NOT NULL DEFAULT 'pending',
    `cleared_date` DATE NULL,
    `cleared_bank_account_id` INT NULL,
    `transaction_id` INT NULL,
    `related_module` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `recorded_by` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`cheque_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due_date` (`due_date`),
    INDEX `idx_cheque_num` (`cheque_number`)
) ENGINE=InnoDB;


-- ============================================================
-- Batch 2 (2026-11-15) — remaining audit fixes
-- ============================================================

-- 12. Login lockout + API token expiry (users table).
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `api_token` VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS `api_token_expires_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL;

-- 13. Warehouse item/partition columns (the live dump has them; fresh
--    installs from schema.sql did not). stock_receive() writes
--    partition_code and unit_cost into wh_stock_batches, so both are
--    required for a fresh install to receive stock at all.
ALTER TABLE `wh_stock_items`
    ADD COLUMN IF NOT EXISTS `department_scope` VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS `partition_code` ENUM('finished_products','feed_nutrition','materials_supplies') NOT NULL DEFAULT 'finished_products';
ALTER TABLE `wh_stock_batches`
    ADD COLUMN IF NOT EXISTS `partition_code` ENUM('finished_products','feed_nutrition','materials_supplies') NOT NULL DEFAULT 'finished_products',
    ADD COLUMN IF NOT EXISTS `unit_cost` DECIMAL(14,4) NOT NULL DEFAULT 0.0000;

-- 14. Department material request status enum: add the two values the
--    live dump already has and the code writes ('sent_to_department' on
--    confirm-receipt, 'completed' on fulfil). MODIFY is idempotent.
ALTER TABLE `dept_material_requests`
    MODIFY `status` ENUM('pending','partially_issued','fulfilled','shortage_purchase_raised','sent_to_department','completed','rejected') NOT NULL DEFAULT 'pending';

-- 15. fin_payslips: every column the payroll code writes.
--    NOTE: remaining_balance must be a PLAIN column, not GENERATED:
--    payroll_finalize(), add-bonus, recalculate and payroll_record_payment()
--    all write it explicitly, and MariaDB rejects writes to a generated
--    column — on DBs built from migration_v2 (or the live dump) those
--    writes fatal. MODIFY converts a generated column to plain; on a
--    plain column it is a no-op.
ALTER TABLE `fin_payslips`
    ADD COLUMN IF NOT EXISTS `daily_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `gross_pay` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `allowances` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `pension_employee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `pension_employer` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `total_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `remaining_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `fin_payslips`
    MODIFY `remaining_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00;

SET FOREIGN_KEY_CHECKS = 1;
