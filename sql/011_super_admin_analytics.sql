-- ============================================================================
-- Aleltu EIFMS -- Migration 011: Super Admin analytics (forward-only, re-runnable)
--
-- Target server : MariaDB 10.4+ (your dump says 10.4.32-MariaDB). It uses
--                 "IF NOT EXISTS" which MariaDB supports for indexes/columns.
-- BEFORE YOU RUN: take a full backup (phpMyAdmin -> Export -> Custom -> all tables
--                 -> "Add DROP TABLE" NOT needed -> Go). Keep that file safe.
-- It is SAFE to run more than once: every statement checks first.
-- It NEVER deletes, truncates or rewrites existing financial/production rows.
--   * It only adds: indexes, one column, one small mapping table, one setting.
--   * The single UPDATE only fills dist_dispatches.sale_channel where it is NULL.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Indexes so period/unit reports use index range scans instead of full scans
--    (names start with idx_an_ so they are easy to find / drop later)
-- ---------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_an_egg_date_flock   ON poultry_egg_collections (collection_date, flock_id);
CREATE INDEX IF NOT EXISTS idx_an_pmort_date_flock ON poultry_mortality_logs   (log_date, flock_id);
CREATE INDEX IF NOT EXISTS idx_an_fharv_date_stock ON fish_harvest_records     (harvest_date, fish_stock_id);
CREATE INDEX IF NOT EXISTS idx_an_fmort_date_stock ON fish_mortality_logs      (log_date, fish_stock_id);
CREATE INDEX IF NOT EXISTS idx_an_ffeed_done_stock ON fish_feeding_logs        (completed_at, fish_stock_id);
CREATE INDEX IF NOT EXISTS idx_an_fstock_date      ON fish_stocks              (stocking_date);
CREATE INDEX IF NOT EXISTS idx_an_fpharv_date      ON feed_harvest_records     (harvest_date);

CREATE INDEX IF NOT EXISTS idx_an_disp_status_date ON dist_dispatches          (status, dispatched_at, stock_item_id);
CREATE INDEX IF NOT EXISTS idx_an_dpay_created     ON dist_dispatch_payments   (created_at, dispatch_id);

CREATE INDEX IF NOT EXISTS idx_an_exp_date_unit    ON fin_expenses             (expense_date, unit_tag, status);
CREATE INDEX IF NOT EXISTS idx_an_tx_dir_date      ON fin_transactions         (direction, transaction_date);
CREATE INDEX IF NOT EXISTS idx_an_tx_source        ON fin_transactions         (source_table, source_id);

CREATE INDEX IF NOT EXISTS idx_an_issue_date_dest  ON wh_stock_issues          (issued_at, destination_type);
CREATE INDEX IF NOT EXISTS idx_an_recv_src_sent    ON wh_store_receiving       (source_type, sent_at);
CREATE INDEX IF NOT EXISTS idx_an_adj_date_type    ON wh_stock_adjustments     (created_at, adjustment_type);

CREATE INDEX IF NOT EXISTS idx_an_vhr_date         ON vet_health_records       (record_date);
CREATE INDEX IF NOT EXISTS idx_an_vvr_admin        ON vet_vaccination_records  (administered_date);
CREATE INDEX IF NOT EXISTS idx_an_vdi_date         ON vet_disease_incidents    (detected_date);
CREATE INDEX IF NOT EXISTS idx_an_dhr_cow_date     ON dairy_health_records     (cow_id, record_date);
CREATE INDEX IF NOT EXISTS idx_an_dvr_cow          ON dairy_vaccination_records(cow_id, administered_date);
CREATE INDEX IF NOT EXISTS idx_an_exit_date        ON dairy_cow_exit_records   (exit_date, exit_type);

CREATE INDEX IF NOT EXISTS idx_an_pr_status_end    ON fin_payroll_runs         (status, period_end);

-- ---------------------------------------------------------------------------
-- 2. Sales channel on each dispatch.
--    WHY: a dispatch to one of the company's own shops is an INTERNAL TRANSFER,
--    not income from an outside customer. Without a stored channel the report
--    cannot tell the two apart (the old data only has free text).
--    New dispatches get the channel from the dispatch form (patch P1);
--    old rows are classified once, below, by clearly documented text rules.
--    Rows the rules cannot classify stay NULL = "unclassified" and are shown
--    for review, never silently counted as revenue.
-- ---------------------------------------------------------------------------
ALTER TABLE dist_dispatches
    ADD COLUMN IF NOT EXISTS sale_channel ENUM('direct_sale','shop_transfer','internal_use') NULL DEFAULT NULL
        COMMENT 'direct_sale=outside buyer; shop_transfer=to company shop; internal_use=gift/protocol/food at price 0'
        AFTER payment_notes;

CREATE INDEX IF NOT EXISTS idx_an_disp_channel ON dist_dispatches (sale_channel);

-- Rule 1: gift / protocol / food dispatches (price 0 or the gift marker the form writes)
UPDATE dist_dispatches
   SET sale_channel = 'internal_use'
 WHERE sale_channel IS NULL
   AND (total_price = 0 OR destination_pos_location LIKE '🎁%');

-- Rule 2: direct buyer (the form writes "ቀጥታ ገዢ"; older seed data wrote "ቀጥተኛ ገዢ")
UPDATE dist_dispatches
   SET sale_channel = 'direct_sale'
 WHERE sale_channel IS NULL
   AND (destination_pos_location LIKE '%ቀጥ%ገዢ%'
        OR destination_pos_location LIKE '%Direct Buyer%'
        OR destination_pos_location LIKE '👤%');

-- Rule 3: a registered shop name appears in the destination text, or the form's
--         shop marker / the words ሱቅ / መሸጫ appear
UPDATE dist_dispatches d
   SET d.sale_channel = 'shop_transfer'
 WHERE d.sale_channel IS NULL
   AND (EXISTS (SELECT 1 FROM dist_shops s
                 WHERE LOCATE(s.shop_name, d.destination_pos_location) > 0)
        OR d.destination_pos_location LIKE '🏢%'
        OR d.destination_pos_location LIKE '%ሱቅ%'
        OR d.destination_pos_location LIKE '%መሸጫ%');

-- ---------------------------------------------------------------------------
-- 3. Which business unit does each stock item's SALES income belong to?
--    A warehouse item is not tied to a unit anywhere in the old schema.
--    Auto-filled only where the item itself proves it (milk class; SKU prefix
--    EGG / FISH). Anything else stays unmapped and is shown as "unattributed"
--    until the Super Admin assigns it on the analytics settings page.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rpt_item_unit_map (
    stock_item_id INT NOT NULL PRIMARY KEY,
    business_unit ENUM('dairy','poultry','fish','feed_production','veterinary') NOT NULL,
    source        VARCHAR(40) NOT NULL DEFAULT 'manual',
    updated_by    INT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_an_map_unit (business_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO rpt_item_unit_map (stock_item_id, business_unit, source)
SELECT id, 'dairy', 'auto:product_class=milk' FROM wh_stock_items WHERE product_class = 'milk';

INSERT IGNORE INTO rpt_item_unit_map (stock_item_id, business_unit, source)
SELECT id, 'poultry', 'auto:sku_prefix_EGG' FROM wh_stock_items WHERE sku LIKE 'EGG%';

INSERT IGNORE INTO rpt_item_unit_map (stock_item_id, business_unit, source)
SELECT id, 'fish', 'auto:sku_prefix_FISH' FROM wh_stock_items WHERE sku LIKE 'FISH%';

-- ---------------------------------------------------------------------------
-- 4. Setting: how to treat dispatches to company shops in income.
--    'internal' (default, safest) = NOT company income (internal transfer)
--    'external'                    = shops are independent customers -> income
--    Change it on the analytics settings page, not here.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value, description)
VALUES ('analytics_shop_dispatch_treatment', 'internal',
        'Super Admin analytics: internal = dispatches to company shops are transfers, not income; external = shops are outside customers');
