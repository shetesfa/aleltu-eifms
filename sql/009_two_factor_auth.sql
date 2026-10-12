-- ============================================================
-- Aleltu Farm EIFMS - TOTP Two-Factor Authentication
-- Adds two columns to users; no new tables needed.
-- Import via phpMyAdmin -> aleltu_eifms -> Import tab.
-- Safe to run even if columns already exist (checked first).
-- ============================================================

SET @col1_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'two_factor_secret'
);
SET @sql1 = IF(@col1_exists = 0,
    'ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(64) NULL AFTER theme',
    'SELECT 1'
);
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @col2_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'two_factor_enabled'
);
SET @sql2 = IF(@col2_exists = 0,
    'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
