# SYSTEM ARCHITECTURE (PHASE 2) — Aleltu Farm EIFMS

This document details the architectural layout, modular boundaries, and security patterns of the **Aleltu Farm EIFMS**.

---

## 1. Architectural Layers

The application uses a Clean-PHP MVC-inspired structured layout:

```
┌────────────────────────────────────────────────────────┐
│                      Presentation                      │
│ (modules/dairy, modules/warehouse, modules/finance...) │
└───────────────────────────┬────────────────────────────┘
                            │ (Calls helper service layer)
                            ▼
┌────────────────────────────────────────────────────────┐
│                Business Service Layer                  │
│              (includes/functions.php)                  │
└───────────────────────────┬────────────────────────────┘
                            │ (Uses parameterized PDO queries)
                            ▼
┌────────────────────────────────────────────────────────┐
│                   Data Access / PDO                    │
│                  (config/db.php)                       │
└────────────────────────────────────────────────────────┘
```

*   **Presentation Layer**: Formulates HTML templates, processes POST/GET data, validates CSRF tokens, and handles redirects. No raw database queries are declared here.
*   **Business Service Layer**: Implements core business logic rules (FEFO batch selection, employee salary calculations, calf registration rules, audit logs triggers).
*   **Access Layer**: Establishes database connections and manages transactions.

---

## 2. Shared Integration Boundaries

Modules are linked through database relationships and service integrations:

1.  **Warehouse Choke Point**: Operations never edit batch levels directly. They call `stock_receive()` or `stock_issue()` inside `functions.php`. This updates warehouse batches (`wh_stock_batches`), logs ledger entries (`wh_stock_receipts` or `wh_stock_issues`), and checks reorder alerts in one transactional cycle.
2.  **Audit Logs**: Every service method updates `audit_logs` using `log_activity()`, storing user context, action codes, and record state diffs.
3.  **Notifications Trigger**: Operations (e.g. calving due, low stock, extreme water quality values) insert alert entries into `notifications`, making them visible in the global alert system.

---

## 3. Localization & Personalization

*   **Translation Engine**: The function `t()` loads current user language files (`en.php` or `am.php`) to display localized navs, buttons, and system headers.
*   **Active Theme Config**: User selections (`light` or `dark`) write to `users.theme`, rendering CSS custom properties dynamically inside `header.php`.
