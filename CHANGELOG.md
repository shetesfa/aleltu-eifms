# CHANGELOG — Aleltu Farm EIFMS

## [v2.0.0] - 2026-08-09

### 🚀 New Features & Architecture Enhancements
*   **Granular Role-Based Access Control (RBAC)**: Added `permissions` and `role_permissions` schema, enabling action-level permissions (`has_permission()`, `require_permission()`).
*   **Farm → Store Receiving Confirmation Flow**: Implemented `wh_store_receiving` module (`receiving.php`). Dispatches from Dairy, Poultry, Fish, or Feed Production now enter a `pending_receipt` state and require physical Storekeeper inspection and receipt confirmation before entering active warehouse stock. Variance logging tracks shortfalls.
*   **Department Material Requisitions**: Added `dept_material_requests` module (`requisitions.php`). Departments request feed, medicine, or supplies directly from the central Store. When stock is insufficient, the system automatically issues available stock and converts the shortage into a Purchase Request to Finance.
*   **Auto-Generated Employee ID**: Employee registration now automatically generates sequential identifiers starting at `TKE0001` (`employee_generate_number()`).
*   **Official Employee ID Card Generator**: Added printable employee ID card view (`id_card.php`) with Aleltu logo, profile information, and unique barcode styling.
*   **Daily-Rate Attendance Payroll & Finance Payment Ledger**:
    *   Updated `payroll_finalize()` to compute salary as Daily Rate × Present Days.
    *   Added partial/full payment transaction tracking (`fin_payroll_payments`) displaying calculated net pay, total paid, and remaining balance per payslip.
*   **Cow "View All" Milk Yield History & Exit Form Correction**:
    *   Added paginated full milk yield history page (`cow_milk_history.php`).
    *   Removed Buyer Name requirement from Cow Exit Record form as per Section 10.
*   **Feed Production Workflow Adjustment**: Removed expected harvest date requirement from plant crop form.
*   **Poultry & Fish Operational Visibility**:
    *   Added `flock_view.php` for complete poultry history (egg production rounds, mortality cause codes, vaccination schedules).
    *   Added feeding history, growth samples history, and harvest history tables to `pond_view.php`.
*   **Targeted Scoped Notifications**: Added `target_user_id`, `target_role_id`, and `target_department_id` to notification system (`notify_targeted()`).
