-- ============================================================
-- Aleltu Farm EIFMS - Feed Production module (SAD section 2.4 / 5.4)
-- Import via phpMyAdmin -> aleltu_eifms -> Import tab, after schema.sql
-- and the other 00x files. Skip if doing a brand-new import of the
-- updated schema.sql, which already includes these tables.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS feed_plots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    area_hectares DECIMAL(8,2) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feed_planting_records (
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

CREATE TABLE IF NOT EXISTS feed_harvest_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planting_record_id INT NOT NULL,
    harvest_date DATE NOT NULL,
    yield_kg DECIMAL(12,2) NOT NULL,
    recorded_by INT NOT NULL,
    FOREIGN KEY (planting_record_id) REFERENCES feed_planting_records(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- BR-FP02: a request that can't be fully met is never silently
-- short-fulfilled — status makes the gap explicit, and can trigger a
-- Finance Purchase Request (soft link, no hard FK — Finance module is
-- independent and may not exist on every install).
CREATE TABLE IF NOT EXISTS feed_distribution_requests (
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

INSERT INTO wh_stock_items (sku, name, product_class, unit, is_perishable)
SELECT 'FEED-DAIRY-MIX', 'Dairy Feed Mix', 'feed', 'kg', 0
WHERE NOT EXISTS (SELECT 1 FROM wh_stock_items WHERE sku = 'FEED-DAIRY-MIX');

SET FOREIGN_KEY_CHECKS = 1;
