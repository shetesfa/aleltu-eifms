-- Rollback for migration 011 (only if you must undo it). Restores nothing else.
-- Existing business data is not touched by 011, so rollback only removes what 011 added.
-- Take a backup first. Run in phpMyAdmin.
DROP TABLE IF EXISTS rpt_item_unit_map;
ALTER TABLE dist_dispatches DROP COLUMN IF EXISTS sale_channel;
DELETE FROM settings WHERE setting_key = 'analytics_shop_dispatch_treatment';
-- Indexes (harmless to keep; drop only if you want a perfectly clean schema):
-- DROP INDEX idx_an_egg_date_flock ON poultry_egg_collections;  ... (repeat for every idx_an_* index)
