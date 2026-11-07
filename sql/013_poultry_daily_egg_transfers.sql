-- 013_poultry_daily_egg_transfers.sql
-- Enables daily aggregated egg transfer from Poultry to Store (Option B, matching Dairy flow)

ALTER TABLE `poultry_egg_collections` 
ADD COLUMN IF NOT EXISTS `transferred_at` DATETIME NULL DEFAULT NULL AFTER `recorded_by`;
