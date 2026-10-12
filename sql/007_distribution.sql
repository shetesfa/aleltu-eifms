-- ============================================================
-- Aleltu Farm EIFMS - Distribution module (SAD section 2.6 / 5.6)
-- The final module: dispatch, delivery confirmation, and the boundary
-- to the external Aleltu POS System (this system supplies it; it does
-- not manage sales/customers/pricing at all).
-- Import via phpMyAdmin -> aleltu_eifms -> Import tab, after schema.sql
-- and the other 00x files.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS dist_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50) NULL,
    capacity_kg DECIMAL(10,2) NULL,
    status ENUM('available','in_use','maintenance') NOT NULL DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Simplified to one product per dispatch (consistent with the rest of this
-- codebase's single-item approach, e.g. Purchase Orders) — a dispatch
-- carrying several products can be logged as several dispatch rows.
CREATE TABLE IF NOT EXISTS dist_dispatches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,          -- employees.id (a Driver is an Employee)
    destination_pos_location VARCHAR(150) NOT NULL,
    warehouse_id INT NOT NULL,
    stock_item_id INT NOT NULL,
    quantity_dispatched DECIMAL(12,2) NOT NULL,
    quantity_delivered DECIMAL(12,2) NULL,
    variance_flagged TINYINT(1) NOT NULL DEFAULT 0,
    planned_dispatch_at DATETIME NOT NULL,
    dispatched_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    -- BR-DI01/DI02 (SAD 6.6) lifecycle: stock is deducted at Dispatch, not Delivery.
    status ENUM('planned','dispatched','in_transit','delivered','partially_delivered','failed') NOT NULL DEFAULT 'planned',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES dist_vehicles(id),
    FOREIGN KEY (driver_id) REFERENCES employees(id),
    FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id),
    FOREIGN KEY (stock_item_id) REFERENCES wh_stock_items(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
