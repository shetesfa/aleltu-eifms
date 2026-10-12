-- ============================================================
-- Aleltu Farm EIFMS - Employee Management extension
-- Adds Tasks + Performance Reviews (SAD section 2.8 / 5.8).
-- Import this AFTER sql/schema.sql (Import tab in phpMyAdmin,
-- same "aleltu_eifms" database).
-- Safe to skip if you're doing a brand-new import of the updated
-- schema.sql, which already includes these tables further below.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS tasks (
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

CREATE TABLE IF NOT EXISTS performance_reviews (
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

SET FOREIGN_KEY_CHECKS = 1;
