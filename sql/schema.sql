-- ============================================================
-- Aleltu Farm - Enterprise Integrated Farm Management System
-- Plain PHP + MySQL version - core schema
-- Import this file via phpMyAdmin into an empty database
-- (e.g. a database named aleltu_eifms) before using the site.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------- Identity / Access ----------------

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    business_unit VARCHAR(50) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,        -- super_admin, general_manager, dairy_manager, warehouse_keeper, field_worker, ...
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- password_hash() output
    role_id INT NOT NULL,
    department_id INT NULL,
    locale VARCHAR(5) NOT NULL DEFAULT 'en',  -- en | am  (FR-X04)
    theme VARCHAR(5) NOT NULL DEFAULT 'light', -- light | dark (FR-X05)
    two_factor_secret VARCHAR(64) NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- Simple audit log (FR-X03): every module writes here via log_activity().
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,             -- create | update | delete
    record_table VARCHAR(80) NOT NULL,
    record_id INT NOT NULL,
    details TEXT NULL,                        -- JSON of before/after values
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------- DAIRY (SAD section 2.1 / 5.1) ----------------

CREATE TABLE dairy_cow_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    pen_location VARCHAR(100) NULL,
    notes TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE dairy_cows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_number VARCHAR(50) NOT NULL UNIQUE,   -- FR-D01: unique permanent ID
    name VARCHAR(100) NULL,
    sex ENUM('female','male') NOT NULL,
    date_of_birth DATE NULL,
    breed VARCHAR(100) NULL,
    dam_id INT NULL,
    sire_id INT NULL,
    cow_group_id INT NULL,
    lifecycle_status ENUM('registered','calf','heifer','milking','dry','pregnant','sold','deceased') NOT NULL DEFAULT 'registered',
    is_sick TINYINT(1) NOT NULL DEFAULT 0,
    acquired_date DATE NULL,
    source ENUM('born_on_farm','purchased') NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dam_id) REFERENCES dairy_cows(id) ON DELETE SET NULL,
    FOREIGN KEY (sire_id) REFERENCES dairy_cows(id) ON DELETE SET NULL,
    FOREIGN KEY (cow_group_id) REFERENCES dairy_cow_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE dairy_milking_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_date DATE NOT NULL,
    session_type ENUM('morning','evening') NOT NULL,   -- FR-D02
    recorded_by INT NOT NULL,
    closed_at DATETIME NULL,
    UNIQUE KEY uniq_session (session_date, session_type),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE dairy_milk_yield_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    milking_session_id INT NOT NULL,
    cow_id INT NOT NULL,
    yield_liters DECIMAL(6,2) NOT NULL,
    observation VARCHAR(255) NULL,
    flagged_abnormal TINYINT(1) NOT NULL DEFAULT 0,
    recorded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_entry (milking_session_id, cow_id),
    FOREIGN KEY (milking_session_id) REFERENCES dairy_milking_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (cow_id) REFERENCES dairy_cows(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE dairy_health_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cow_id INT NOT NULL,
    record_date DATE NOT NULL,
    condition_name VARCHAR(150) NULL,
    treatment TEXT NULL,
    vet_id INT NULL,
    FOREIGN KEY (cow_id) REFERENCES dairy_cows(id) ON DELETE CASCADE,
    FOREIGN KEY (vet_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE dairy_vaccination_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cow_id INT NOT NULL,
    vaccine_name VARCHAR(150) NOT NULL,
    scheduled_date DATE NOT NULL,
    administered_date DATE NULL,
    status ENUM('scheduled','administered','missed') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (cow_id) REFERENCES dairy_cows(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dairy_breeding_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cow_id INT NOT NULL,
    method ENUM('natural','artificial_insemination') NOT NULL,
    sire_reference VARCHAR(100) NULL,
    breeding_date DATE NOT NULL,
    expected_calving_date DATE NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (cow_id) REFERENCES dairy_cows(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE dairy_calving_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    breeding_record_id INT NOT NULL,
    dam_id INT NOT NULL,
    calf_id INT NULL,
    calving_date DATE NOT NULL,
    outcome ENUM('live_birth','stillbirth','complication') NOT NULL,
    notes TEXT NULL,
    FOREIGN KEY (breeding_record_id) REFERENCES dairy_breeding_records(id) ON DELETE CASCADE,
    FOREIGN KEY (dam_id) REFERENCES dairy_cows(id),
    FOREIGN KEY (calf_id) REFERENCES dairy_cows(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE dairy_cow_exit_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cow_id INT NOT NULL,
    exit_type ENUM('death','sale') NOT NULL,
    exit_date DATE NOT NULL,
    cause_of_death VARCHAR(255) NULL,
    buyer_name VARCHAR(150) NULL,
    sale_price DECIMAL(12,2) NULL,
    notes TEXT NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (cow_id) REFERENCES dairy_cows(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;


-- ---------------- FEED PRODUCTION (SAD section 2.4 / 5.4) ----------------

CREATE TABLE feed_plots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    area_hectares DECIMAL(8,2) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE feed_planting_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plot_id INT NOT NULL,
    crop_type VARCHAR(100) NOT NULL,
    planting_date DATE NOT NULL,
    expected_harvest_date DATE NULL,
    recorded_by INT NOT NULL,
    status ENUM('growing','harvested') NOT NULL DEFAULT 'growing',
    FOREIGN KEY (plot_id) REFERENCES feed_plots(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE feed_harvest_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planting_record_id INT NOT NULL,
    harvest_date DATE NOT NULL,
    yield_kg DECIMAL(12,2) NOT NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (planting_record_id) REFERENCES feed_planting_records(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE feed_distribution_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requesting_unit ENUM('dairy','poultry','fish') NOT NULL,
    quantity_requested_kg DECIMAL(10,2) NOT NULL,
    quantity_fulfilled_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending','fulfilled','partially_fulfilled_backordered','triggered_purchase') NOT NULL DEFAULT 'pending',
    purchase_request_id INT NULL,
    requested_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------- WAREHOUSE (SAD section 2.5 / 5.5) ----------------

CREATE TABLE wh_warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    product_class ENUM('milk','feed','medicine','equipment','spare_parts','general') NOT NULL,
    location VARCHAR(150) NULL,
    requires_cold_storage TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE wh_stock_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    product_class ENUM('milk','feed','medicine','equipment','spare_parts','general') NOT NULL,
    unit VARCHAR(20) NOT NULL,
    is_perishable TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE wh_stock_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    batch_number VARCHAR(80) NULL,
    quantity_on_hand DECIMAL(12,2) NOT NULL DEFAULT 0,   -- BR-W01: only ever changed via receive/issue/adjust logic
    received_date DATE NULL,
    expiry_date DATE NULL,                                -- used for FEFO ordering (FR-WH07)
    source VARCHAR(30) NULL,
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    INDEX idx_fefo (warehouse_id, stock_item_id, expiry_date)
) ENGINE=InnoDB;

CREATE TABLE wh_stock_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    stock_batch_id INT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    source_type VARCHAR(30) NOT NULL,                     -- dairy | poultry | fish | feed_production | purchase
    received_by INT NOT NULL,
    received_at DATETIME NOT NULL,
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (stock_batch_id) REFERENCES wh_stock_batches(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE wh_stock_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    stock_batch_id INT NULL,
    quantity DECIMAL(12,2) NOT NULL,                       -- BR-W02: service layer refuses to let this exceed stock
    destination_type VARCHAR(30) NOT NULL,                 -- dairy | poultry | fish | distribution | veterinary
    issued_by INT NOT NULL,
    issued_at DATETIME NOT NULL,
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (stock_batch_id) REFERENCES wh_stock_batches(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE wh_minimum_stock_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    minimum_threshold DECIMAL(12,2) NOT NULL,
    last_alert_sent_at DATETIME NULL,                      -- BR-W03: fire once per breach
    UNIQUE KEY uniq_rule (warehouse_id, stock_item_id),
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id)
) ENGINE=InnoDB;

-- ---------------- POULTRY (SAD section 2.2 / 5.2) ----------------

CREATE TABLE poultry_houses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    quarantined TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE poultry_flocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poultry_house_id INT NOT NULL,
    breed VARCHAR(100) NOT NULL,
    stocking_date DATE NOT NULL,
    initial_population INT NOT NULL,
    cumulative_mortality INT NOT NULL DEFAULT 0,
    cumulative_culled_sold INT NOT NULL DEFAULT 0,
    cumulative_transfers_in INT NOT NULL DEFAULT 0,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    FOREIGN KEY (poultry_house_id) REFERENCES poultry_houses(id)
) ENGINE=InnoDB;

CREATE TABLE poultry_egg_collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flock_id INT NOT NULL,
    collection_date DATE NOT NULL,
    round_no TINYINT NOT NULL DEFAULT 1,
    whole_count INT NOT NULL DEFAULT 0,
    cracked_count INT NOT NULL DEFAULT 0,
    broken_count INT NOT NULL DEFAULT 0,
    recorded_by INT NOT NULL,
    FOREIGN KEY (flock_id) REFERENCES poultry_flocks(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE poultry_mortality_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flock_id INT NOT NULL,
    log_date DATE NOT NULL,
    count INT NOT NULL,
    cause_code ENUM('disease','heat_stress','predator','unknown') NOT NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (flock_id) REFERENCES poultry_flocks(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------- FISH FARM (SAD section 2.3 / 5.3) ----------------

CREATE TABLE fish_ponds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    area_sq_m DECIMAL(10,2) NULL,
    status ENUM('empty','stocked','partially_harvested') NOT NULL DEFAULT 'empty'
) ENGINE=InnoDB;

CREATE TABLE fish_stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pond_id INT NOT NULL,
    species VARCHAR(100) NOT NULL,
    stocking_date DATE NOT NULL,
    stocking_quantity INT NOT NULL,
    stocking_density_per_sq_m DECIMAL(8,2) NULL,
    cumulative_mortality INT NOT NULL DEFAULT 0,
    cumulative_harvested_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    FOREIGN KEY (pond_id) REFERENCES fish_ponds(id)
) ENGINE=InnoDB;

CREATE TABLE fish_water_quality_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pond_id INT NOT NULL,
    reading_at DATETIME NOT NULL,
    ph DECIMAL(4,2) NULL,
    dissolved_oxygen_mg_l DECIMAL(5,2) NULL,
    temperature_celsius DECIMAL(4,1) NULL,
    within_safe_range TINYINT(1) NOT NULL DEFAULT 1,       -- BR-F01: drives an alert when 0
    recorded_by INT NULL,
    FOREIGN KEY (pond_id) REFERENCES fish_ponds(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE fish_harvest_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    harvest_date DATE NOT NULL,
    quantity_kg DECIMAL(10,2) NOT NULL,
    grade VARCHAR(30) NULL,
    type ENUM('full','partial') NOT NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE fish_feeding_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    quantity_kg DECIMAL(8,2) NULL,
    feed_type VARCHAR(80) NULL,
    status ENUM('scheduled','completed','missed') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE fish_growth_samples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    sample_date DATE NOT NULL,
    sample_size INT NOT NULL,
    average_weight_g DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE fish_mortality_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    log_date DATE NOT NULL,
    count INT NOT NULL,
    suspected_cause VARCHAR(150) NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------- VETERINARY (cross-unit: Dairy/Poultry/Fish) ----------------
-- Polymorphic: subject_type in ('dairy_cow','poultry_flock','fish_stock'),
-- subject_id points into the matching table. No hard FK possible across
-- three tables — enforced at the application layer.

CREATE TABLE vet_health_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('dairy_cow','poultry_flock','fish_stock') NOT NULL,
    subject_id INT NOT NULL,
    record_date DATE NOT NULL,
    condition_name VARCHAR(150) NULL,
    treatment TEXT NULL,
    vet_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vet_id) REFERENCES users(id),
    INDEX idx_subject (subject_type, subject_id)
) ENGINE=InnoDB;

CREATE TABLE vet_vaccination_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('dairy_cow','poultry_flock','fish_stock') NOT NULL,
    subject_id INT NOT NULL,
    vaccine_name VARCHAR(150) NOT NULL,
    scheduled_date DATE NOT NULL,
    administered_date DATE NULL,
    status ENUM('scheduled','administered','missed') NOT NULL DEFAULT 'scheduled',
    vet_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vet_id) REFERENCES users(id),
    INDEX idx_subject (subject_type, subject_id),
    INDEX idx_due (status, scheduled_date)
) ENGINE=InnoDB;

CREATE TABLE vet_disease_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('dairy_cow','poultry_flock','fish_stock') NOT NULL,
    subject_id INT NOT NULL,
    disease_name VARCHAR(150) NOT NULL,
    detected_date DATE NOT NULL,
    quarantine_flag TINYINT(1) NOT NULL DEFAULT 0,
    quarantine_cleared_date DATE NULL,
    notes TEXT NULL,
    vet_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vet_id) REFERENCES users(id),
    INDEX idx_subject (subject_type, subject_id)
) ENGINE=InnoDB;

-- ---------------- EMPLOYEES (SAD section 2.8 / 5.8) ----------------

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    department_id INT NULL,
    employee_number VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    job_title VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    hire_date DATE NULL,
    base_salary DECIMAL(12,2) NULL,
    is_driver TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','on_leave','terminated') NOT NULL DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_at DATETIME NULL,
    check_out_at DATETIME NULL,
    status ENUM('present','absent','on_leave','half_day') NOT NULL DEFAULT 'present',
    UNIQUE KEY uniq_att (employee_id, attendance_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    status ENUM('pending','manager_approved','approved','rejected') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    assigned_to INT NOT NULL,          -- employees.id
    assigned_by INT NOT NULL,          -- users.id
    module VARCHAR(50) NULL,           -- dairy | poultry | fish | warehouse | ... (context only)
    due_date DATE NULL,
    status ENUM('pending','in_progress','completed','missed') NOT NULL DEFAULT 'pending',
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    attendance_rate_pct DECIMAL(5,2) NULL,
    task_completion_rate_pct DECIMAL(5,2) NULL,
    manager_notes TEXT NULL,
    reviewed_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------- DISTRIBUTION (SAD section 2.6 / 5.6) ----------------
-- Simplified to one product per dispatch, consistent with this codebase's
-- single-item pattern elsewhere (e.g. Purchase Orders).

CREATE TABLE dist_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50) NULL,
    capacity_kg DECIMAL(10,2) NULL,
    status ENUM('available','in_use','maintenance') NOT NULL DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE dist_dispatches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,
    destination_pos_location VARCHAR(150) NOT NULL,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    quantity_dispatched DECIMAL(12,2) NOT NULL,
    quantity_delivered DECIMAL(12,2) NULL,
    variance_flagged TINYINT(1) NOT NULL DEFAULT 0,
    planned_dispatch_at DATETIME NOT NULL,
    dispatched_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    status ENUM('planned','dispatched','in_transit','delivered','partially_delivered','failed') NOT NULL DEFAULT 'planned',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES dist_vehicles(id),
    FOREIGN KEY (driver_id) REFERENCES employees(id),
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------- FINANCE (SAD section 2.7 / 5.7) ----------------

CREATE TABLE fin_bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL DEFAULT 'Oromia Bank',
    account_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE fin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NULL,
    direction ENUM('inflow','outflow') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    unit_tag ENUM('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead') NOT NULL,
    category VARCHAR(50) NULL,
    source_table VARCHAR(50) NULL,
    source_id INT NULL,
    transaction_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    recorded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_account_id) REFERENCES fin_bank_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_date_unit (transaction_date, unit_tag)
) ENGINE=InnoDB;

CREATE TABLE fin_expenses (
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

CREATE TABLE fin_purchase_requests (
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

CREATE TABLE fin_purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_request_id INT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    stock_item_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL,
    status ENUM('draft','pending_approval','approved','sent','received','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_request_id) REFERENCES fin_purchase_requests(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE fin_budgets (
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

CREATE TABLE fin_payroll_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('draft','attendance_locked','finalized') NOT NULL DEFAULT 'draft',
    processed_by INT NOT NULL,
    finalized_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE fin_payslips (
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

-- ---------------- SETTINGS ----------------

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------- SEED DATA ----------------

INSERT INTO roles (name, description) VALUES
('super_admin', 'Full system access'),
('general_manager', 'Company-wide read + approvals'),
('dairy_manager', 'Dairy Farm module'),
('poultry_manager', 'Poultry Farm module'),
('fish_manager', 'Fish Farm module'),
('feed_production_manager', 'Feed Production module'),
('veterinary_officer', 'Veterinary module (cross-unit: Dairy/Poultry/Fish)'),
('warehouse_keeper', 'Warehouse module'),
('distribution_officer', 'Distribution module'),
('finance_officer', 'Finance module'),
('hr_officer', 'Employee Management module'),
('field_worker', 'Mobile/quick data entry only');

INSERT INTO departments (name, code, business_unit) VALUES
('Management', 'MGMT', 'management'),
('Dairy Farm', 'DAIRY', 'dairy'),
('Poultry Farm', 'POULTRY', 'poultry'),
('Fish Farm', 'FISH', 'fish'),
('Warehouse', 'WH', 'warehouse');

-- Default login: admin@aleltufarm.com / ChangeMe!2026
-- (password below is a real password_hash('ChangeMe!2026', PASSWORD_DEFAULT) output — verified to work)
INSERT INTO users (name, email, password, role_id, department_id) VALUES
('System Administrator', 'admin@aleltufarm.com', '$2y$10$l0cVnRR6iTaVwhFd8yA8WerT.zjLmZbm0ittgMw3t3WM6KujQgPpW', 1, 1);

INSERT INTO wh_warehouses (name, product_class, location) VALUES
('Milk Cold Store', 'milk', 'Main Site'),
('Feed Store', 'feed', 'Main Site'),
('Medicine Store', 'medicine', 'Main Site'),
('Fish Cold Store', 'general', 'Main Site');

INSERT INTO wh_stock_items (sku, name, product_class, unit, is_perishable) VALUES
('MILK-RAW', 'Raw Milk', 'milk', 'liters', 1),
('FEED-LAYER', 'Layer Mash', 'feed', 'kg', 0),
('FISH-TILAPIA', 'Tilapia (harvested)', 'general', 'kg', 1),
('FEED-DAIRY-MIX', 'Dairy Feed Mix', 'feed', 'kg', 0);

INSERT INTO fin_bank_accounts (bank_name, account_name, account_number, opening_balance) VALUES
('Oromia Bank', 'Aleltu Farm Company - Main Account', '1000200030004000', 500000.00);

SET FOREIGN_KEY_CHECKS = 1;
