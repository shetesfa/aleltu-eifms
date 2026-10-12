-- ============================================================
-- Aleltu Farm EIFMS - Veterinary module (SAD section 5's Veterinary
-- cross-unit service, referenced throughout as "shared" in section 2.1/2.2/2.3)
-- Polymorphic across Dairy cows, Poultry flocks, and Fish stocks — one true
-- Veterinary module instead of three duplicated health-tracking systems.
-- Import via phpMyAdmin -> aleltu_eifms -> Import tab, after schema.sql
-- and the other 00x files.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- subject_type is one of: 'dairy_cow' | 'poultry_flock' | 'fish_stock'
-- subject_id points to dairy_cows.id / poultry_flocks.id / fish_stocks.id
-- respectively. No hard FK here by design (a polymorphic association can't
-- reference three different tables at once) — the application layer
-- enforces the subject actually exists.

CREATE TABLE IF NOT EXISTS vet_health_records (
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

CREATE TABLE IF NOT EXISTS vet_vaccination_records (
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

CREATE TABLE IF NOT EXISTS vet_disease_incidents (
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

SET FOREIGN_KEY_CHECKS = 1;
