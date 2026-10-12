# DATABASE DESIGN (PHASE 3) — Aleltu Farm EIFMS

This document maps out the entity relationship properties, table structures, index rules, and transaction safety controls of the MySQL schema.

---

## 1. Schema Entity Relationships

The relational layout uses a normalized database structure to ensure scalability:

*   **Access & Profile Identity**:
    *   `users` belong to `roles` and `departments`.
    *   `employees` optionally link to a `users` login account.
*   **Warehouse Stock Ledger**:
    *   `wh_stock_batches` map to a specific `wh_warehouses` and `wh_stock_items`.
    *   `wh_stock_receipts` and `wh_stock_issues` track every change back to its batch and employee.
    *   `wh_minimum_stock_rules` prevent unexpected stockouts.
*   **Dairy Herd Registry**:
    *   `dairy_cows` reference mother (`dam_id`) and father (`sire_id`) using recursive foreign keys.
    *   `dairy_breeding_records` track inseminations, and calvings (`dairy_calving_records`) link back to successful breeding attempts.
*   **Distribution Fleet**:
    *   `dist_dispatches` map vehicles (`dist_vehicles`), drivers (`employees`), warehouses, and stock items.

---

## 2. Key Indexes & Constraints

To optimize page loads and enforce data integrity, the schema implements:

1.  **Unique Tag Constraints**:
    *   `users.email` (prevent duplicate logins)
    *   `dairy_cows.tag_number` (prevent duplicate cow identifiers)
    *   `wh_stock_items.sku` (prevent duplicate inventory codes)
    *   `attendance(employee_id, attendance_date)` (prevent duplicate timecards)
2.  **Lookup Indexes**:
    *   `wh_stock_batches(warehouse_id, stock_item_id, expiry_date)`: Accelerates FEFO (First Expired, First Out) batch selections.
    *   `vet_vaccination_records(status, scheduled_date)`: Speeds up calendar schedules loading.

---

## 3. Transaction Safety (Rollback Policy)

Any operation modifying multiple tables must run within a PDO transaction block.
Example flow inside `stock_issue()`:
1.  **Start Transaction**: `db()->beginTransaction()`
2.  **Row Level Locks**: `SELECT ... FOR UPDATE` locks selected stock batches, preventing concurrent double-spending.
3.  **Update Batch Levels**: Updates remaining quantity per batch.
4.  **Log Ledger Entry**: Inserts a record into `wh_stock_issues`.
5.  **Audit Log Entry**: Logs changes in `audit_logs`.
6.  **Verify & Commit**: `db()->commit()`. If any SQL statement throws an exception, `db()->rollBack()` is executed to restore the original state.
