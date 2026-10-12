-- Migration v4: support pending_receipt status on purchase orders
-- and add sent_to_store tracking columns to fp_crops

ALTER TABLE fin_purchase_orders
    MODIFY COLUMN status ENUM(
        'pending_approval','approved','sent','pending_receipt','received','cancelled'
    ) NOT NULL DEFAULT 'pending_approval';

ALTER TABLE fin_purchase_orders
    ADD COLUMN IF NOT EXISTS warehouse_id INT NULL AFTER status,
    ADD CONSTRAINT IF NOT EXISTS fk_po_warehouse FOREIGN KEY (warehouse_id) REFERENCES wh_warehouses(id) ON DELETE SET NULL;

-- Track when a crop was sent to Store
ALTER TABLE fp_crops
    ADD COLUMN IF NOT EXISTS sent_to_store_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sent_qty_kg DECIMAL(12,3) NULL;

SELECT 'Migration v4 complete' AS result;
