# TEST PLAN (PHASE 8) — Aleltu Farm EIFMS

This document outlines the testing strategy, validation cases, and verification steps of the **Aleltu Farm EIFMS**.

---

## 1. Test Categories

1.  **Unit Tests**: Verify business service functions inside `includes/functions.php` (e.g. calculation of flock populations, expected calving dates).
2.  **Integration Tests**: Run test scripts against an isolated database instance to verify database transactions and ensure they rollback correctly on failure.
3.  **Security Tests**: Validate page-level authorization guards by trying to access restricted modules with a low-privilege user account.
4.  **Workflow Tests**: End-to-end verification of operational flows (e.g. converting a purchase request to a PO, receiving items into warehouse inventory, confirming vehicle dispatches).

---

## 2. Critical Test Cases

### A. Animal Lifecycle Rules
*   **TC-D01 (Exit Rule)**: Verify that attempting to add a milking session yield or health record to a cow marked as `deceased` throws a validation exception.
*   **TC-D02 (Calving Rule)**: Verify that logging a calving entry for a cow that is not pregnant throws a validation exception.

### B. Warehouse stock ledger Rules
*   **TC-W01 (Overdraw Block)**: Attempt to issue more stock than currently available in warehouse batches. Verify that the transaction is blocked and no inventory is deducted.
*   **TC-W02 (FEFO Selection)**: Seed two stock batches of the same item with different expiry dates. Issue half the stock and verify that the system automatically selects the batch expiring first.

---

## 3. Execution Procedure

*   **Manual Verification**: Use the safe, idempotent demo data seeder (`setup_demo_data.php`) to quickly create sample records for test logins. Log in with each specific role (e.g. Milker vs GM) and verify page access permissions.
*   **Database Verification**: Inspect the `audit_logs` table after executing test runs to confirm that database changes and operational events are logged correctly.
