-- ============================================================
-- Aleltu Farm EIFMS - Fish Farm module additions (SAD section 2.3 / 5.3)
-- fish_ponds, fish_stocks, fish_water_quality_readings, fish_harvest_records
-- already exist from sql/schema.sql. This file adds the three that didn't:
-- feeding logs, growth samples, mortality logs.
-- Import via phpMyAdmin -> aleltu_eifms -> Import tab, after schema.sql.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- fish_stocks was created before stocking_density_per_sq_m existed in an
-- earlier version of this schema — add it if missing (safe to run twice).
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fish_stocks' AND COLUMN_NAME = 'stocking_density_per_sq_m'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE fish_stocks ADD COLUMN stocking_density_per_sq_m DECIMAL(8,2) NULL AFTER stocking_quantity',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS fish_feeding_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    quantity_kg DECIMAL(8,2) NULL,
    feed_type VARCHAR(80) NULL,
    status ENUM('scheduled','completed','missed') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fish_growth_samples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    sample_date DATE NOT NULL,
    sample_size INT NOT NULL,
    average_weight_g DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fish_mortality_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_stock_id INT NOT NULL,
    log_date DATE NOT NULL,
    count INT NOT NULL,
    suspected_cause VARCHAR(150) NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (fish_stock_id) REFERENCES fish_stocks(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO wh_warehouses (name, product_class, location)
SELECT 'Fish Cold Store', 'general', 'Main Site'
WHERE NOT EXISTS (SELECT 1 FROM wh_warehouses WHERE name = 'Fish Cold Store');

INSERT INTO wh_stock_items (sku, name, product_class, unit, is_perishable)
SELECT 'FISH-TILAPIA', 'Tilapia (harvested)', 'general', 'kg', 1
WHERE NOT EXISTS (SELECT 1 FROM wh_stock_items WHERE sku = 'FISH-TILAPIA');

SET FOREIGN_KEY_CHECKS = 1;
