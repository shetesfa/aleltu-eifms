-- =====================================================================
-- Migration 010: Two-Factor Authentication Enhancements
-- Creates table for one-time recovery backup codes
-- =====================================================================

CREATE TABLE IF NOT EXISTS user_2fa_recovery_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;
