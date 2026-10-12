-- ============================================================
-- Aleltu Farm EIFMS - Security hardening pass
-- Adds the three roles the SAD called for that the original seed missed
-- (Feed Production, Veterinary, Distribution each need their own manager
-- role so per-page permission checks have someone to grant access to).
-- No schema changes needed for CSRF protection — it's session-based.
-- Import via phpMyAdmin -> aleltu_eifms -> Import tab.
-- ============================================================

INSERT INTO roles (name, description)
SELECT 'feed_production_manager', 'Feed Production module'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'feed_production_manager');

INSERT INTO roles (name, description)
SELECT 'veterinary_officer', 'Veterinary module (cross-unit: Dairy/Poultry/Fish)'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'veterinary_officer');

INSERT INTO roles (name, description)
SELECT 'distribution_officer', 'Distribution module'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'distribution_officer');
