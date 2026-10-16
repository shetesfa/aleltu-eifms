# Super Admin Farm Performance Analytics

Page: `/aleltu-eifms/modules/admin/analytics.php` (Super Admin only; sidebar: System & Admin).

## 1. What each number means
| Word on screen | Meaning | Source |
|---|---|---|
| Physical production | Litres, eggs, fish kg, feed kg. **Never income.** | milking entries, egg collections, fish harvests, feed harvests |
| Income (recorded sales) | Value of dispatches that left the farm to an outside buyer (`direct_sale`), by dispatch date | `dist_dispatches.total_price` |
| Cash/bank received | Money actually received against those sales | `dist_dispatch_payments` (and cow-sale ledger rows) |
| Not yet collected | recorded sales − cash received | calculated |
| Expenses | Approved vouchers + stock **consumed** (at batch cost) + payroll gross + ledger-only expenses + write-offs | vouchers, `wh_stock_issues`, payslips |
| Profit/Loss | Income − Expenses (direct contribution) | calculated |

Not counted as income or expense: bank/cash transfers between own accounts, owner drawings, purchases of stock that is still in the store, dispatches to company shops (internal transfer, switchable on the Settings page), gifts/protocol (price 0), unknown ledger rows (listed for review).

Feed production and Veterinary are **cost centres**: they show cost, not profit; their cost is already inside Company expenses. A unit whose data is incomplete is labelled "Incomplete data" and the reason is listed in the "Data quality" panel. Cow/flock/pond "allocated sales" are labelled **estimate** (pooled sales split by litre/egg/kg share).

Company total = every unit + shared/unattributed bucket, each amount once.

## 2. Apply (beginner steps)
1. **Backup first**: phpMyAdmin → select database → Export → Custom → all tables → Go. Keep the file.
2. phpMyAdmin → SQL tab → paste `sql/011_super_admin_analytics.sql` → Go. (Safe to run twice.)
3. Upload the new/changed files from the package keeping the same folders.
4. Log in as Super Admin → sidebar → "Farm Performance Analytics".
5. Open **Settings** (button on the page) and confirm: (a) are your shops part of the company? (keep "Internal transfer"); (b) map any unmapped sold items to a business unit.
6. Optional: `sql/012_veterinary_unit_tag.sql` + add `'veterinary'` to `FINANCE_UNIT_TAGS` in `includes/functions.php` so vet expenses can be tagged.

## 3. Undo
* Files: restore the previous copies of `includes/functions.php`, `includes/header.php`, `modules/distribution/dispatches.php`; delete the new files.
* Database: run `sql/011_super_admin_analytics_rollback.sql` (drops only the mapping table, the `sale_channel` column and the setting). Indexes are harmless; drop commands are in that file as comments. No farm record is ever changed by the migration.

## 4. Tests
`php tests/analytics/run_tests.php /path/to/export.sql` — builds a throw-away database `aleltu_analytics_test`, never touches the real one. Last run: 123 passed, 0 failed (MariaDB 10.11 sandbox, copy of your export). Not browser-verified.

## 5. Metrics that still need more data
* Calf milk consumption (milk drunk by calves is not measured → litres per cow vs sold litres cannot be reconciled).
* Purchase price of animals (cow gain/loss on sale, depreciation).
* Feed eaten per cow / flock / pond (feed is issued per department only).
* Cost per treatment / per vaccination (only stock issues and vouchers exist).
* Sales per cow, per flock, per pond (sales are pooled; only an allocation estimate).
* Broiler / culled-bird sales per flock.
* Sales made by company shops to the public (POS is not connected) → shop-fed units stay "Incomplete".
* Employer pension contribution.
* Internal transfer price of feed from Feed Production to other units (feed is valued at stock cost).
* Link between a cow-sale record and its bank receipt.
* Allocation of shared overhead (admin, utilities) to units — shown separately, not spread.

## 6. Existing-code issues found (not changed unless listed)
Cow sale TypeError; `stock_receive` accepts NULL unit cost; `log_activity()` writes `table_name` but the table column is `record_table` (audit rows silently lost); a `telebirr` value is not valid for the ledger enum; wrong enum filters in some reports; income statement counts payroll twice. The analytics pages use their own correct audit writer.
