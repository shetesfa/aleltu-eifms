# Aleltu Farm EIFMS — Plain PHP + MySQL (XAMPP Edition)

No Composer, no Laravel, no internet needed. Plain PHP files + one SQL file.
**This version has been tested end-to-end against a real MySQL database** —
login, cow registration, milking → warehouse transfer, stock issue with the
negative-stock guard, and the cow-sale lifecycle rule were all verified
working, not just syntax-checked.

## Setup (XAMPP on Windows, user: tesfa)

**If this is your first time installing:**
1. Copy this whole folder to: `C:\xampp\htdocs\aleltu-eifms`
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, create a new database named `aleltu_eifms`.
4. In phpMyAdmin, select that database → **Import** tab → choose `sql/schema.sql` → Go.
   (This creates all tables AND seeds a working login + starter warehouse/stock data.)
5. `config/db.php` already matches stock XAMPP defaults (root, no password) — no editing needed.
6. Visit `http://localhost/aleltu-eifms/`

**If you already imported `sql/schema.sql` before and just replaced the files with
this newer version:**
1. Overwrite your old project files with these.
2. In phpMyAdmin, select your `aleltu_eifms` database → **Import** tab, importing
   whichever of these you don't have yet, **in this order**:
   `sql/002_employee_management.sql` → `sql/003_finance.sql` → `sql/004_fish_farm.sql` → `sql/005_feed_production.sql` → `sql/006_veterinary.sql` → `sql/007_distribution.sql` → `sql/008_roles_and_security.sql` → `sql/009_two_factor_auth.sql`
   Your existing data in every module is untouched by any of these.
3. **If your existing users were assigned roles that predate this version**
   (e.g. someone was logging in as `general_manager` to manage Feed Production
   because `feed_production_manager` didn't exist yet), reassign them: edit the
   `users` table and update `role_id` to point at the new, more specific role
   now that it exists (`feed_production_manager`, `veterinary_officer`, or
   `distribution_officer`).

**Login:** `admin@aleltufarm.com` / `ChangeMe!2026` — change this for real use
(edit the `users` table, or add a "change password" page later).

## Two-Factor Authentication

Every user can turn this on for their own account — click the 🔐 icon in the top bar (next to 🔑 change password).

**How it works:** standard TOTP (RFC 6238), the same protocol Google Authenticator, Authy, and Microsoft Authenticator all use. There's no external service and no internet call involved — the 6-digit code is computed independently by your phone app and by this server, both from the same shared secret, using the current time. Verified against the official RFC 6238 test vectors before shipping, not just tested against itself.

**Setup:**
1. Click 🔐 in the top bar
2. In your authenticator app, choose "Enter a setup key manually" (there's no QR code here — that would need an image-generation library this offline setup doesn't have; manual entry is a standard, fully-supported alternative every authenticator app offers)
3. Type in the key shown on screen
4. Enter the 6-digit code your app is now showing, to confirm it's working
5. From then on, logging in asks for a fresh code after your password

Turning it off is the same 🔐 icon → Disable.

**One real limitation to know about:** there's no "lost my phone" recovery flow yet (no backup codes). If a user enables 2FA and then loses their authenticator app, the only way back in currently is a direct database edit: `UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE email = '...'`. Worth keeping in mind before turning this on for accounts you can't easily reach the database for.

## Getting sample data (do this right after setup)

The database starts nearly empty — just the default admin login and a couple of warehouses. To actually explore the system instead of staring at empty tables:

1. Log in as `admin@aleltufarm.com` / `ChangeMe!2026`
2. In the sidebar, under **System**, click **🌱 Demo Data Setup** (only `super_admin` sees this link)
3. Click **Generate Demo Data**

This creates, in one click:
- **11 test logins, one per role** (all use password `Demo@2026`) — full list and what to try with each is shown right on that page after you run it
- 5 dairy cows with 5 days of realistic milking history
- A poultry house with an active flock and 5 days of egg collection
- A stocked fish pond
- A feed production plot
- Real warehouse stock, including a minimum-stock rule the current level is deliberately below — so you'll see a real notification in the bell icon immediately, not just an empty inbox
- 3 employees (including a driver, so Distribution has someone to assign)
- A delivery vehicle
- 6 months of cash flow history and a few sample expenses, so the Reports charts aren't blank on first login

**Safe to click more than once** — it checks what already exists and only fills in what's missing, so re-running it after adding your own real data won't create duplicates or overwrite anything.

## Security

**Role-based access control is now enforced on every page**, not just "are you
logged in." Each module checks the user's actual role and returns a 403 if
they don't belong:

| Module | Allowed roles |
|---|---|
| Dairy (cow management) | `general_manager`, `dairy_manager` |
| Dairy (milking entry only) | `general_manager`, `dairy_manager`, `field_worker` |
| Poultry | `general_manager`, `poultry_manager`, `field_worker` |
| Fish (ponds) | `general_manager`, `fish_manager` |
| Fish (pond detail — feeding/water quality/harvest) | `general_manager`, `fish_manager`, `field_worker` |
| Feed Production | `general_manager`, `feed_production_manager` |
| Warehouse | `general_manager`, `warehouse_keeper` |
| Distribution | `general_manager`, `distribution_officer`, `warehouse_keeper` |
| Veterinary | `general_manager`, `veterinary_officer` |
| Employee Management | `general_manager`, `hr_officer` |
| Finance | `general_manager`, `finance_officer` |
| Audit Log | `super_admin`, `general_manager` |

`super_admin` always has access to everything, everywhere.

**CSRF protection is on every form.** Every one of the 47 forms across the app
now carries a hidden token, and every one of the 25 POST handlers verifies it
before touching the database. Tested directly: a submission with no token, or
a fake one, is rejected with a 419 and nothing is written to the database; a
submission with the real token (the way a browser actually behaves) works
normally.

## Module status

All six business-unit modules from the original spec are now built and tested
end-to-end against a live database (not just written — actually run, with real
data, checking the business rules actually fire).

| Module | Status |
|---|---|
| Auth / Audit Log | ✅ Built + tested |
| Dairy (cows, milking, exit) | ✅ Built + tested |
| Warehouse (stock, receive, issue) | ✅ Built + tested |
| Poultry (flocks, mortality) | ✅ Built + tested |
| Employee Management (attendance, tasks, leave) | ✅ Built + tested |
| Finance (cash flow, expenses, purchasing, budgets, payroll) | ✅ Built + tested |
| Fish Farm (ponds, feeding, water quality, growth, mortality, harvest) | ✅ Built + tested |
| Feed Production (plots, planting, harvest, internal distribution) | ✅ Built + tested |
| Veterinary (health, vaccination, disease/quarantine — cross-unit) | ✅ Built + tested |
| Distribution (vehicles, dispatch, delivery confirmation) | ✅ Built + tested |
| Role-based permissions on every page | ✅ Built + tested |
| CSRF protection on every form | ✅ Built + tested |
| Notifications inbox (bell icon, unread count, filters, mark read) | ✅ Built + tested |
| Amharic + English (persists per-user, works pre-login too) | ✅ Built + tested |
| Dark mode (persists per-user, works pre-login too) | ✅ Built + tested |
| Self-service password change | ✅ Built + tested |
| **Reports & Analytics — 5 live charts (Milk trend, Egg trend, Cash flow, Warehouse stock, Expense breakdown)** | ✅ Built + tested |
| **CSV export (Excel-compatible) — Warehouse stock, Expenses, Payroll, Audit Log** | ✅ Built + tested — **new** |
| **Print / Save-as-PDF on the Reports page** | ✅ Built + tested — **new** |
| **Demo data generator + one test login per role** | ✅ Built + tested |
| **Two-Factor Authentication (TOTP — Google Authenticator/Authy compatible)** | ✅ Built + tested — **new** |

**What's genuinely NOT done yet, and should not be assumed working:**
| Item | Status |
|---|---|
| Amharic translation coverage | ⚠️ The nav, login page, and main dashboard are translated and tested. Most module pages (forms, table headers inside e.g. Finance/Fish/Veterinary) are still English-only — the `t()` function and `includes/lang/am.php` are ready for anyone to extend, but it's not a full translation yet. **The Amharic text itself should also be reviewed by a native speaker before real use** — it was written by an AI without a way to verify fluency, not by a certified translator. |
| ~~Formal report exports~~ | ✅ Now built — see "Exporting data" below for exactly what's covered and how |
| Real POS integration | ⚠️ The integration point exists (`dispatch_push_to_pos()`) and is documented, but no live POS endpoint is configured — safe no-op until Aleltu's POS team provides one |
| ~~2FA~~ | ✅ Now built — TOTP-based, see "Two-Factor Authentication" below |
| Forgot-password / email-based reset | ❌ Not built — there's no mail server configured in this environment. Self-service change (for someone already logged in) works; recovering a completely lost password currently requires a database edit |
| Flutter mobile app | ❌ Not built — this is a web-only system currently |

## Exporting data

Two different, honest approaches — both work fully offline, neither needs a library:

**CSV (opens in Excel/LibreOffice/Google Sheets natively):**
- Warehouse → Stock Levels → "⬇️ Export to CSV"
- Finance → Expenses → "⬇️ Export to CSV"
- Finance → Payroll → (per payroll run) "⬇️ Export Payslips"
- Audit Log → "⬇️ Export to CSV" (last 1,000 entries)

Implemented in `includes/export.php` — plain PHP `fputcsv()`, no PhpSpreadsheet, no Composer package. Includes a UTF-8 byte-order mark so Amharic or other non-ASCII text opens correctly in Excel instead of showing as garbled characters.

**PDF (via your browser's own print dialog):**
- Reports & Analytics → "🖨️ Print / Save as PDF"

There's no PDF-generation library running on the server — this uses `window.print()` plus a print stylesheet (`assets/css/app.css`, the `@media print` block) that hides the navigation and buttons so only the report content shows. In the browser's print dialog, choose **"Save as PDF"** as the destination instead of a physical printer. This is a legitimate way to get real PDF output without any server dependency; the trade-off is it's one manual click (pick "Save as PDF") rather than a direct one-click download.

## What's in here

```
config/db.php          One file, one PDO connection. Edit 4 constants if your MySQL differs.
includes/auth.php       Session-based login (no Sanctum, no JWT — just PHP sessions)
includes/functions.php  All business logic: cow lifecycle, milking, warehouse stock rules
includes/header.php     Shared page shell (nav, flash messages, notification bell, locale + theme switchers)
includes/footer.php     Closes the page shell
includes/lang/en.php    English strings for the shared chrome — extend t('key') usage into module pages as needed
includes/lang/am.php    Amharic strings — same keys as en.php; missing keys fall back to English automatically
includes/charts.php     Dependency-free SVG chart generator (bar, grouped bar, line) — no CDN, no JS library
includes/export.php     CSV export helper (plain fputcsv, UTF-8 BOM for Excel/Amharic compatibility)
assets/css/app.css      Plain CSS, no CDN — works fully offline, includes a full dark theme via [data-theme="dark"] and a print stylesheet for PDF export
sql/schema.sql          Every table + seed data. Import this once via phpMyAdmin.
modules/auth/           Login / logout
modules/profile/        Change password, switch language, toggle dark mode
modules/notifications.php  Inbox for every alert raised across the app (low stock, water quality, delivery variance...)
modules/reports.php     Reports & Analytics — 5 charts pulling live data from Dairy, Poultry, Finance, Warehouse
includes/totp.php        RFC 6238 TOTP implementation (2FA) — verified against official test vectors
modules/setup_demo_data.php  Admin-only: one-click sample data + 11 test logins (see "Getting sample data" above)
modules/auth/two_factor_challenge.php  The "enter your code" step during login, when 2FA is on
modules/profile/two_factor.php  Enable/disable 2FA for your own account
modules/dairy/          Cow list, register cow, cow detail + exit (sale/death), milking entry
modules/warehouse/      Stock levels, receive, issue
modules/poultry/        Flock list, mortality logging
modules/employees/      Employee list/register, attendance, leave requests, tasks
modules/finance/        Cash flow, expenses, purchasing (PR->PO->receipt), budgets, payroll
modules/fish/           Ponds, stocking, feeding, water quality, growth samples, mortality, harvest
modules/feed_production/ Plots, planting, harvest into Warehouse, internal distribution requests
modules/veterinary/     Health records, vaccinations, disease incidents/quarantine — cross-unit (Dairy/Poultry/Fish)
modules/distribution/   Vehicles, dispatch board, delivery confirmation with variance detection
modules/audit_log.php   Every create/update/delete, who did it, when
index.php               Dashboard
```

## Business rules that are actually enforced (not just documented)

- **BR-D01** — a sold/deceased cow cannot be milked or edited further (tested: blocked correctly)
- **BR-W02** — you cannot issue more stock than currently exists (tested: refused a 999L issue against 15.5L on hand)
- **BR-W03** — low-stock alerts fire once, not on every request
- **BR-E01** — an employee can't have two overlapping approved leave requests (tested: blocked correctly)
- **BR-FN02** — purchase orders above a threshold need separate approval (default threshold: 10,000 ETB, edit via `settings` table key `po_approval_threshold`)
- **BR-FN03** — payroll can't be finalized until every active employee has attendance for the whole period (tested: blocked, then succeeded once complete)
- **BR-F01** — a water quality reading outside the safe range for the pond raises a High-priority alert automatically (tested: correctly fired on an out-of-range pH reading)
- **BR-F02** — a harvest can't exceed the pond's estimated live biomass (tested: blocked a 500kg harvest against ~147kg estimated biomass, then a valid 100kg harvest succeeded and landed in the Warehouse)
- **BR-FP01** — feed stock keeps a source tag (`internal_grown` vs `externally_purchased`) even though it shares one physical stock pool, for accurate costing later
- **BR-FP02** — a distribution request that can't be fully met from stock is never silently short-fulfilled: it's flagged, and automatically raises a Finance Purchase Request for the exact shortfall (tested: 2000kg requested, 1200kg on hand → 1200kg issued, 800kg shortfall correctly triggered a real Purchase Request)
- **BR-P02** — quarantining a poultry disease incident quarantines the whole house too, and clearing it un-quarantines the house (tested both directions)
- Veterinary is genuinely cross-unit — one health/vaccination/disease system covering Dairy, Poultry, and Fish, not three separate copies (tested: a health record on a cow correctly flagged it `is_sick` in the Dairy module too)
- **BR-DI01/BR-W02** — a dispatch can't exceed available warehouse stock (tested: blocked a 999L dispatch against 200L on hand)
- **BR-DI02** — stock is deducted the moment a dispatch is created, not when it's delivered, and a failed delivery requires an explicit stock-return entry rather than a silent reversal (tested: dispatched 30L → stock dropped by 30 → marked failed → stock correctly returned)
- **Role-based access** — a `field_worker` account was tested directly: correctly blocked (403) from Finance and Cow Management, correctly allowed into Milking Entry
- **CSRF protection** — tested directly: a form submission with no token, or a forged one, is rejected (419) and nothing is written to the database; a real submission (the way a browser behaves) works normally
- **Password change** — tested directly: wrong current password rejected, mismatched confirmation rejected, and after a valid change the *old* password stops working while the *new* one logs in successfully
- **Locale/theme preference** — tested directly: switching to Amharic or dark mode persists to the database and survives navigating to a completely different page, not just a session flag
- **Reports & Analytics** — tested directly: with zero data, charts render an honest empty state (0-height bars, or "No data yet" where there's nothing to plot at all); with real milking/expense/cash-flow data entered, the exact same numbers appear correctly in the chart tooltips. Also confirmed the page is restricted to `general_manager`/`super_admin` only, same as Audit Log.
- **CSV export** — tested directly across all four export points: correct HTTP headers (`Content-Disposition`, filename with today's date), and the actual downloaded file contains the real database rows — confirmed with a live expense (2500 ETB, Vet Supplies), a finalized payslip (2 days present, 5000 ETB net pay), and an audit log entry with correct actor attribution.
- **Demo data generator** — tested directly: ran it twice in a row and confirmed the second run created zero duplicate rows across users, cows, milking sessions, and notifications (true idempotency, not just "doesn't crash"). Confirmed one of the 11 seeded logins (`dairy@aleltufarm.com` / `Demo@2026`) actually authenticates, sees real cow data, and is still correctly blocked from Finance. Confirmed the deliberately-below-threshold stock rule fired a real notification that shows up in the bell icon immediately after seeding.
- **Two-Factor Authentication** — the TOTP algorithm was checked against both official RFC 6238 test vectors before anything else was built on top of it (exact match on both). Then tested end-to-end: enabled 2FA on a real account using a code generated the same way a phone app would (from the exact secret string shown on the setup page); confirmed password-alone no longer completes login and redirects to a code challenge instead; confirmed a wrong code is rejected and the dashboard stays inaccessible; confirmed the correct code completes login; confirmed disabling clears both the flag and the secret from the database.
- **FR-DI04** — a delivery confirmation with a different quantity than what was dispatched is automatically flagged as a variance and raises a High-priority alert (tested: 50L dispatched, 48L delivered → correctly flagged)
- **FR-D03** — milking session totals are always summed live, never hand-entered
- **FR-EM02** — marking attendance twice for the same employee/day updates the record instead of duplicating it
- **FR-FN05** — Purchase Request → Order → Goods Receipt actually pushes stock into Warehouse (via the same `stock_receive()` function Dairy/Poultry use) and books the payment as a cash outflow, tested end-to-end
- **FR-X03** — every create/update/delete writes to `audit_logs` automatically

## Extending this

Everything follows the same shape:
1. Add a table to `sql/schema.sql` (or a new `.sql` file you import separately).
2. Add a function to `includes/functions.php` for the business logic.
3. Add a page under `modules/{module}/` — copy an existing one as a template.
4. Add a link in `includes/header.php`'s sidebar.

There's no framework magic here — every page is a plain PHP file you can open
and read top to bottom.

## A note on one bug that was caught and fixed during testing

Several pages originally called `header('Location: ...')` for redirects
*after* the shared page template had already started printing HTML — which
silently fails in PHP (headers can't be sent once output has begun). This
was caught by actually running the app against a live database rather than
just checking syntax, and fixed by processing form submissions *before*
including the page template (`includes/header.php`). If you add a new page
with a form, follow the existing pattern: handle `$_POST` and any
`header()` redirect **first**, and only `require_once includes/header.php`
afterward.
