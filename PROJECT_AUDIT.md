# PROJECT AUDIT (PHASE 0) — Aleltu Farm EIFMS

This document provides a technical audit of the pre-existing codebase for the **Aleltu Farm Enterprise Integrated Farm Management System (EIFMS)**.

---

## 1. Codebase Inventory

The project structure consists of the following components:

*   **`/config`**: Database settings (`db.php`) and stubs for POS configuration (`pos.php`).
*   **`/includes`**: Shared libraries, helper functions, and visual layouts.
    *   `auth.php`: CSRF protection, multi-step TOTP 2FA, session checks, and role guards (`require_role()` / `has_role()`).
    *   `functions.php`: Database connection initialization, operational helpers for cattle, poultry, fish, warehouse stock ledger, distribution dispatch, employee payroll, audit logging, and demo data seeding.
    *   `header.php` / `footer.php`: Main layout wraps with a role-filtered sidebar.
    *   `charts.php`: SVG charts generation.
    *   `export.php`: CSV downloader.
    *   `totp.php`: TOTP 2FA utilities.
    *   **`/lang`**: Translation dictionary arrays (`en.php`, `am.php`).
*   **`/modules`**: Page controllers and views:
    *   `/auth`: Login/logout, 2FA handler.
    *   `/admin`: `users.php` (User listings, role edits, blocking controls).
    *   `/dairy`: Cattle lists, milk records, single-cow files, breeding logs.
    *   `/poultry`: House/flock management, egg collection.
    *   `/fish`: Ponds register, water quality logger.
    *   `/feed_production`: Crop plots, planting diaries, distribution request fulfillment.
    *   `/warehouse`: Current inventory, stock receive/issue forms.
    *   `/distribution`: Vehicle fleet, dispatch schedules.
    *   `/veterinary`: Health summaries, quarantine statuses, vaccinations calendar.
    *   `/finance`: Budgets, payroll runs, bank accounts, purchase requests, PO logs.
    *   `/employees`: Employee database, attendance logging, leave approvals, tasks board.
    *   `reports.php`: General manager analytical reports.
    *   `audit_log.php` / `notifications.php`: Logs and alert centers.
    *   `setup_demo_data.php`: Safe idempotent data seeder.
*   **`/sql`**: Core SQL database schema (`schema.sql`) and schema patches.

---

## 2. Technical Strengths Identified

1.  **Strict Transaction-Based Inventory Ledger**: Physical stock changes (`wh_stock_batches`) are never direct overwrites. All transactions pass through explicitly logged receipts (`wh_stock_receipts`) or FEFO issues (`wh_stock_issues`), providing historical auditability.
2.  **No Dynamic External CDN Assets**: Visual layouts run fully offline using local CSS and raw SVG graphics, satisfying network disconnection resiliency requirements.
3.  **Basic 2FA Integration**: TOTP multi-step verification forms exist on auth modules.

---

## 3. Key Risks & Gaps

1.  **Weak Access Permissions System**: The system relies on simple role-name checks (`require_role()`). It lacks a dedicated table mapping actions to specific roles, preventing fine-grained, customizable permission controls.
2.  **Lack of REST API Module**: There is no API layer (e.g. `/api/v1/`) to support the future Flutter mobile application.
3.  **Hardcoded Workflow Rules**: Approval steps for purchases, leave, and expenses are implemented ad-hoc in separate PHP pages rather than driven by a centralized rules engine.
4.  **Isolated Domain Modules**: Important cross-departmental operations (e.g. crop harvest transfer, veterinary medicine deduction, poultry feeding stock deductions) are not automatically linked to the warehouse stock ledger.
