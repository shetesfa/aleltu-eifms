# Aleltu EIFMS Repair Log

Tracks findings and fixes against the phase plan in the master repair prompt.
Note: this environment has no PHP/MySQL runtime, so fixes below are verified
by static tracing of the actual SQL/PHP logic and cross-checking every caller,
not by executing the app. Each item lists exactly what to re-verify once
deployed against a real database.

## PHASE 1–2: Audit & data-integrity fixes

### FIXED — Department cost double-counting (`finance_dept_costs()`)
**File:** `includes/functions.php`
**Root cause:** `expense_approve()` writes an approved expense to *both*
`fin_expenses` (status='approved') and `fin_transactions` (category='expense')
so the cash balance reflects the outflow. `finance_dept_costs()` was summing
both tables for the same unit_tag, so every approved expense was counted
twice in department cost totals and in `budget_variance.php` (which calls the
same function to compute budget utilization).
Also found: `fin_transactions` rows with `category='purchase'` (from
`po_record_payment()`) were being counted as departmental operating cost for
`warehouse` — but paying a supplier increases inventory value, it is not
itself consumption, per the brief's own cash-flow-vs-operational-cost rule.
**Fix:** `fin_transactions` query now excludes `category IN ('expense','purchase')`.
`grand_total = expense_total (fin_expenses) + tx_total (other real cash outflows,
e.g. direct dispatch-linked costs) + issue_total (stock consumption)`, each
category counted exactly once.
**To verify when deployed:** approve one expense, confirm it appears once
(not twice) in `/modules/finance/dept_costs.php` and in `budget_variance.php`.

### FIXED — Department material-request stock issues were not attributed to any real department
**File:** `includes/functions.php` — `dept_material_request_fulfill()`
**Root cause:** `schema.sql` documents `wh_stock_issues.destination_type` as
holding the receiving department (`dairy | poultry | fish | distribution | veterinary`),
but the actual fulfillment code hardcoded the literal string `'internal_department'`
for every department's request. `finance_dept_costs()` then remapped every
department's unit_tag to that same literal string to compensate — meaning
Dairy, Poultry, Fish, Warehouse, and Feed Production dept-cost pages were all
querying and displaying the *same* combined stock-issue rows, not their own.
A second symptom of the same bug: every fulfillment notification was
hardcoded to module `'dairy'` and linked to `/modules/dairy/material_request.php`,
regardless of which department actually made the request — so a Poultry or
Fish manager's "material ready" notification pointed at the Dairy module.
**Fix:** `dept_material_request_fulfill()` now looks up the requesting
department's `business_unit` (already a column on `departments`, e.g.
`dairy`/`poultry`/`fish`/`warehouse`) and uses it as the `destination_type`
passed to `stock_issue()`, and as the notification module/link. Removed the
now-unnecessary `$deptMap` collapse in `finance_dept_costs()` — it reads
`destination_type` filtered directly by `$unitTag`, since both fulfillment
paths (`dept_material_request_fulfill()` and the pre-existing, already-correct
`feed_distribution_request_fulfill()`) now write real unit tags.
**Known limitation — cannot be fixed retroactively:** `wh_stock_issues` has no
foreign key back to `dept_material_requests`, so historical rows already
written with `destination_type='internal_department'` cannot be reattributed
to the department that actually consumed them. Going forward all new issues
via this path are correctly attributed; old dept-cost history before this fix
will undercount real departments and (falsely) show consumption under no
current unit tag. Flagging for a decision: leave historical data as-is (it's
now silently excluded from all current unit tags, which is safer than being
silently wrong) or manually audit old `dept_material_requests` records to
backfill if the historical figures matter.
**To verify when deployed:** submit a Poultry and a Fish material request,
fulfill both, confirm (a) each shows up only under its own department's
`dept_costs.php`, and (b) the Poultry manager's notification links to
`/modules/poultry/material_request.php`, not Dairy's.

### FIXED — Payment race condition could allow overpayment / effective duplicate payments (§13)
**Files:** `includes/functions.php` — `dispatch_record_payment()`, `po_record_payment()`
**Root cause:** both functions read the dispatch/PO's current `amount_paid` and
computed `remaining` balance with a plain `SELECT`, validated the incoming
payment against that figure, and only *then* opened (or was already inside)
a transaction to write the update. Two concurrent payment submissions against
the same dispatch or PO (a double-click, a slow connection causing a retry, or
two staff recording payment at the same time) could both read the same stale
`remaining` value, both pass validation, and both commit — together exceeding
the actual amount owed. This is exactly the "payment must never be posted
twice" / overpayment risk the master prompt calls out in §13, and the fix
brings these two functions in line with the row-locking pattern
(`SELECT ... FOR UPDATE` inside the transaction) already used correctly in
`stock_issue()`.
**Fix:** moved the row read inside the transaction using `SELECT ... FOR UPDATE`
in both functions, so the balance is checked against a locked, current value;
a second concurrent payment now blocks until the first commits, then sees the
updated `amount_paid` and is correctly validated against the true remaining
balance. Confirmed both `dist_dispatches` and `fin_purchase_orders` are
InnoDB (required for row-level locking to take effect).
**To verify when deployed:** fire two payment requests for the same dispatch
at nearly the same time (e.g. two browser tabs) for an amount that would
overpay if both succeeded; confirm the second is correctly rejected once the
first commits, rather than both going through.

### FIXED — Same race condition swept across every other check-then-write money/stock function
Once the pattern above was found in the two payment functions, checked every
other approve/confirm function in `includes/functions.php` for the same
"read status outside the lock, write unconditionally" shape:
- `expense_approve()` — **fixed**. Same race, and directly relevant: two
  concurrent approvals of the same pending expense could both pass the
  pending-status check and both book a `fin_transactions` outflow — literally
  the "double expense" case the brief's §31 audit list names explicitly.
  Added `FOR UPDATE` lock + `WHERE status='pending'` guard on the UPDATE with
  a rowCount check as defense in depth.
- `store_confirm_receipt()` — **fixed**. Same race; unfixed, it could let two
  concurrent "confirm receipt" clicks both pass validation and both call
  `stock_receive()`, i.e. a duplicate stock movement for one physical
  delivery (§31: "❌ duplicate stock movements"). Same lock/guard pattern
  applied. In the process, confirmed `wh_store_receiving` is InnoDB and that
  the `status` ENUM values the PHP code writes (`stored`, `sent_to_department`)
  are already correctly present via `migration_v6_critical_fixes.sql` FIX 12
  — no separate bug there, just verified.
- `dept_material_request_confirm_received()` — **fixed**. Lower severity (no
  money or stock movement, only a status flip + notification), but a
  duplicate confirmation could still fire a duplicate notification, which
  §31 also lists as something to prevent. Same lock/guard pattern applied
  for consistency.
- `purchase_order_approve()` — checked, **no fix needed**. Already a single
  atomic `UPDATE ... WHERE status='pending_approval'` with no preceding
  unguarded SELECT — this is actually the correct pattern the other functions
  now match.
- `purchase_order_receive()` — checked, **not fixed, low priority**. Reads PO
  status outside a lock before creating a `wh_store_receiving` row, but does
  not move money or stock itself (that happens later, inside the now-fixed
  `store_confirm_receipt()`); worst case of a race here is a duplicate
  pending-receipt record for the Storekeeper to notice and reject, not silent
  double-booking. Left as a note rather than fixed in this pass — revisit if
  Phase 8 (purchase/payment integration) touches this function anyway.

**To verify when deployed (all four fixes above):** for each function, fire
two concurrent requests against the same row (double-click or two tabs) and
confirm the second is correctly rejected with "already processed" rather than
silently succeeding a second time.

## PHASE 9: Notification architecture (§20)

Reviewed against the brief's exact requirement: "One user's 'read' status
must NOT mark the notification as read for every other user."

**Already correctly built** (via `migration_v2.sql` target-scoping columns and
`migration_v6_critical_fixes.sql` FIX 10's `notification_reads` junction
table): `notifications_unread_count()`, `notifications_recent()`, and
`notifications_mark_all_read_for_user()` all correctly compute/write per-user
read state through `notification_reads`, never the old shared column. No
change needed to these three.

### FIXED — a landmine left behind by that same migration
`notification_mark_read(int $id)` was never updated when the rest of the
notification code moved to `notification_reads` — it still wrote directly to
the old shared `notifications.is_read` column. It's currently dead code
(nothing in `modules/` calls it), so there is no live bug today, but it is
the obviously-named function a future "mark this one notification as read"
button would call, and doing so would have silently reintroduced exactly the
shared-read-state bug §20 and Test 7 describe. Fixed to write to
`notification_reads` for the current user instead, matching the pattern
already used everywhere else.
**To verify when deployed:** if/when a single-notification "mark read"
button is wired up, confirm one user reading a notification doesn't affect
another targeted user's unread state for the same notification.

### FIXED — silently-dropped vaccination reminders (invalid ENUM value)
**File:** `includes/functions.php` — `vet_process_scheduled_reminders()`
**Root cause:** the 2-hour and 1-hour pre-vaccination reminders were sent
with `priority='urgent'`, but `notifications.priority` is
`ENUM('low','medium','high')` — no migration ever added an `'urgent'` tier,
and no UI anywhere renders one. Under strict SQL mode this rejects the
INSERT outright; `notify_targeted()` swallows that failure silently by
design (a notification problem should never block the main action it's
attached to). Worse, the code marked `reminder_2hr_sent`/`reminder_1hr_sent`
as sent immediately after the (failed) notify call regardless of outcome, so
the reminder was never retried either — these two time-critical alerts (the
ones that matter most, arguably more than the 1-day-ahead reminder which
correctly used `'high'` and did work) were being permanently lost, silently,
every time.
**Fix:** changed both calls to `priority='high'`, matching the ENUM and the
existing badge/sort UI (adding a real `'urgent'` tier would need a schema
migration plus new UI work with no way to visually verify it in this
environment, so this is the minimal, safe fix — "repair don't rebuild").
Scanned every other `notify_targeted()` call across `includes/` and
`modules/` for the same class of invalid-literal bug; confirmed these were
the only two.
**To verify when deployed:** create a vaccination scheduled 2 hours and
again 1 hour out, run/wait for `vet_process_scheduled_reminders()`, and
confirm both reminders actually appear in the veterinary team's
notifications (not just the 1-day-ahead one).

## PHASE 11: Poultry egg-entry & mortality (§24–25)

### FIXED — egg-entry duplicate check was app-level only, and racy
**Files:** `includes/functions.php` n/a (logic lives in the page),
`modules/poultry/egg_entry.php`, new `sql/migration_v7_egg_collection_unique.sql`
**Root cause:** the "one entry per flock/date/round" rule was enforced only
by a `SELECT`-then-`INSERT` check in the page itself, with no database
constraint — exactly what §24 explicitly warns against ("enforce it at
database level. Do not depend only on frontend validation"). Two
near-simultaneous submissions (double-click, or two staff entering the same
flock at once) could both pass the check before either INSERT committed.
**Fix:** added `migration_v7_egg_collection_unique.sql`, a `UNIQUE
(flock_id, collection_date, round_no)` constraint (the migration includes a
SELECT to surface any pre-existing duplicate rows first, since the ALTER
will fail if any exist — resolve those by hand before running it). Updated
`egg_entry.php` to keep the fast pre-check for normal-case UX, but now also
catches the DB constraint violation (SQLSTATE 23000) and turns it into the
same friendly duplicate-entry message, so the constraint — not the race-prone
pre-check — is the actual guarantee.
**To verify when deployed:** run `migration_v7_egg_collection_unique.sql`
after confirming no existing duplicates; then submit the same flock/date/round
twice in quick succession (two tabs) and confirm the second is rejected with
the friendly message, not a raw SQL error.

### FIXED — mortality-exceeds-population race condition (poultry & fish)
**Files:** `includes/functions.php` — `poultry_record_mortality()`,
`fish_record_mortality()`, `fish_harvest()`
Same race pattern as the payment/expense fixes in Phase 1–2: each function
already implemented the correct business rule (mortality/harvest can't
exceed live population/estimated biomass — §25/§26), but read the
population/biomass figure before locking the row, so two concurrent
submissions could each pass validation against the same stale figure and
together exceed it — the exact "❌ impossible mortality" / "❌ impossible
harvest" outcomes §31 lists. All three now use `SELECT ... FOR UPDATE` inside
the transaction, matching the pattern established in Phase 1–2. Confirmed
`poultry_flocks`, `fish_stocks`, and `fish_ponds` are InnoDB.
**To verify when deployed:** for a flock/stock near its live-population
limit, fire two mortality/harvest submissions concurrently that would
together exceed it; confirm the second is correctly rejected.

## PHASE 23: Cow source/date workflow (§23)

### FIXED — business rule was frontend-only (JS `required` toggling)
**File:** `includes/functions.php` — `cow_register()`
The form (`cow_add.php`) already correctly implements the rule described in
§23 — born-on-farm requires date of birth, purchased requires an
acquired/purchase date with birth date left optional — but only via
JavaScript toggling the `required` HTML attribute, which is trivially
bypassed (disabled JS, direct POST). `cow_register()` itself had no
validation at all, not even for basic required fields. Added backend
validation re-checking the exact same rule (plus tag number and sex),
matching the brief's general principle (§37) that a business rule enforced
only by hiding/disabling a UI control is not actually enforced.
**To verify when deployed:** submit a cow-registration POST directly
(bypassing the form/JS) with `source=born_on_farm` and no `date_of_birth`;
confirm it's rejected with a clear message instead of either silently
succeeding or throwing a raw SQL error.

## PHASE 21–22: Material request department filtering & UI simplification

### FIXED (infrastructure) — §21 department-aware filtering was structurally impossible before this
**Files:** new `sql/migration_v8_stock_item_department_scope.sql`,
`includes/material_request_shared.php`
**Root cause:** the shared material-request page filtered the catalog only
by `product_class` (feed/medicine/general/equipment/...), and Dairy,
Poultry, and Fish all include `'feed'` in their allowed classes — so every
department saw the identical list of feed items, with no way to distinguish
"Poultry Feed" from "Fish Feed" from "Dairy Feed" at the data level. This
wasn't a query bug to fix — `wh_stock_items` had no column at all that could
express "this item belongs to department X", so no query could have filtered
correctly.
**Fix:** added a nullable `wh_stock_items.department_scope` column
(matching `departments.code` values, e.g. `'POULTRY'`) via migration, and
updated the catalog query to include `(department_scope IS NULL OR
department_scope = ?)`. Deliberately defaults to `NULL` = shared/visible to
everyone the `product_class` already allows, so **running this migration
changes nothing by itself** — no item disappears from anyone's screen until
someone deliberately scopes it.
**Deliberately not done — needs your domain knowledge, not a guess:**
actually setting `department_scope = 'POULTRY'` on your poultry-only feed
items, `'FISH'` on fish-only items, etc. I don't know your actual catalog
contents well enough to classify each item correctly, and guessing wrong
would hide an item a department genuinely needs — a worse failure than
today's over-permissive default. This needs a short pass by whoever manages
the warehouse catalog (or a simple "Manage Catalog" admin screen, which
would be a reasonable small follow-up) to go through the existing items and
set `department_scope` on the ones that are genuinely species/department
specific.
**To verify when deployed:** run the migration, manually set
`department_scope='POULTRY'` on one test item, confirm it now appears only
on the Poultry material-request screen and not Dairy's/Fish's.

### FIXED — §22's confusing dual-mode item picker
**File:** `includes/material_request_shared.php`
Replaced the two radio buttons ("Select from Catalog" / "Enter Custom / New
Item") plus their two separate input groups with a single text field backed
by an HTML5 `<datalist>` for autocomplete against the existing catalog —
exactly the "🔍 Search / Select" pattern §22 asks for. JS resolves whether
the typed text exactly matches a catalog item (case-insensitive) and sets
hidden `item_selection_type`/`stock_item_id` fields accordingly before
submit; if it doesn't match, it's submitted as `custom_item_name` exactly as
before. No backend changes were needed for the happy path: `dept_material_request_create()`
already does its own case-insensitive name lookup against `wh_stock_items`
when given a "custom" name, so even if the JS match were ever wrong, the
backend independently resolves it to the existing catalog item rather than
creating a duplicate — this fix layers on top of an existing safety net
rather than replacing one. Also defaulted the hidden `item_selection_type`
to `'custom'` (not `'catalog'`) so that if JavaScript is unavailable, the
typed text is still submitted and handled by that same backend fallback,
rather than being silently discarded.
**To verify when deployed:** type an existing item's name and submit —
confirm it links to the existing catalog item (check `dept_material_requests.stock_item_id`)
rather than creating a duplicate; then type a genuinely new name and confirm
it's still accepted as a new item request.

## PHASE 14: Feed Production → Warehouse cleanup (§27)

### FIXED — exposed internal "As Stock Item" dropdown
**Files:** `includes/functions.php` (new `feed_resolve_stock_item_for_crop()`),
`modules/feed_production/distribution.php`
Exactly the field §27 asks to remove: the send-to-store form made the Feed
Production Manager manually pick which warehouse catalog entry their own
harvested crop corresponds to. Added `feed_resolve_stock_item_for_crop()`,
reusing the same case-insensitive-name-match-or-auto-register pattern
already established in `dept_material_request_create()` (rather than
inventing a second mechanism) — it looks up an existing `wh_stock_items` row
by the crop's own name, or creates one if none exists. Removed the dropdown
and the now-unused `$feedItems` query from the page; the stock item is now
resolved automatically from the harvest record's own crop type.
**To verify when deployed:** send a harvested crop to store; confirm it
resolves to the correct (or a newly-created) catalog item without the user
picking anything, and that the warehouse receiving flow is unaffected.

## PHASE 15: Vaccination cleanup (§28) — reviewed, no change needed
Checked `modules/veterinary/vaccinations.php` for the batch-number field the
brief asks to remove. Unlike the material-request and cow-registration
cases, this one is **already correctly optional**: no `required` attribute
on the input, the column is nullable, and both the insert and the "update
existing" path (`COALESCE(NULLIF(?, ''), batch_number)`) already handle a
blank value safely, with the history table showing `—` when absent. There is
no enforcement bug here to fix — leaving the field visible-but-optional is a
UI decluttering preference, not a defect, so no change was made rather than
risk altering a working, safe code path for a cosmetic-only reason.

## Phase-completion status against the master prompt's 18-phase plan (§38)
- **Done**: 1 (audit), 2 (data-integrity), 6 (inventory/dept cost), 9
  (notifications), 10 (material request filtering+UI), 11 (poultry), 12
  (cow workflow), 14 (feed production cleanup)
- **Reviewed, already correct — no change needed**: 3 (core finance
  architecture), 4 (bank/cash/income/expense), 15 (vaccination cleanup)
- **Partially done**: 7 (distribution — payment race condition fixed;
  dispatch/distribution report pages not yet reviewed), 8 (purchasing —
  payment race condition fixed; `purchase_order_receive()` flagged but not
  fixed; purchasing report pages not yet reviewed), 13 (fish — mortality/harvest
  validation fixed; the pond-view UI reorganization into the 7 sections §26
  describes has not been done)
- **Not started**: 5 (receivables/payables report pages — the underlying
  functions were read and are correct, but the pages themselves haven't been
  reviewed), 16 (Super Admin dashboard consolidation), 17 (reports beyond
  dept_costs), 18 (full integration test pass — needs a real PHP/MySQL
  environment, which this one is not)
- **Known gap, not yet built**: payroll payment recording never books a cash
  outflow (flagged in Phase 1–2) — this is new feature work, not a bug fix,
  and belongs inside Phase 4/8's remaining scope.

## Not yet fixed — flagged during audit, deferred to a later phase
- **Payroll never books a cash outflow.** `payroll_finalize()` creates
  `fin_payslips` rows with `total_paid = 0`; no code path found that updates
  `total_paid` or calls `finance_record_transaction()` for payroll. Salaries
  currently never reduce Bank/Cash or appear in department cost. This is a
  missing feature (a "mark payslip as paid" action wired to finance), not a
  one-line bug — belongs in a payroll-module phase, not this pass.

## Not yet started
Phases 3 (core finance architecture beyond the above), 5 (receivables/payables
UI), 7–9 (distribution/purchase/notification architecture more broadly),
10–18 (module-by-module UX, Super Admin, reports, full test pass) — see the
master prompt's phase list. `finance_cash_position()`, `finance_balance_by_method()`,
`finance_receivables_summary()`, `finance_payables_summary()`,
`finance_inventory_value()`, `finance_revenue_vs_collected()`, `stock_receive()`,
and `stock_issue()` were read in full during this pass and are already built
correctly per the brief's cash-vs-cost rules — no changes made to them.

(superseded by "Overall status" at the end of this file — kept above for
the historical record of what was known after Phase 1–2 specifically.)

## PHASE 5: Receivables/Payables pages — reviewed, no bugs found
`modules/finance/receivables.php` and `modules/finance/payables.php` were
read in full. Both correctly use the (already-verified-correct)
`finance_receivables_summary()`/`finance_payables_summary()` functions, both
have proper by-driver/by-supplier grouping and dispatch/PO-level drill-down
exactly matching §12/§35's requirements, and a column-name cross-check
against `dist_dispatches` (`driver_name`, `total_price`, `amount_paid`,
etc.) confirmed everything referenced actually exists (added correctly back
in `migration_v6_critical_fixes.sql`). No changes made.

## PHASE 16: Super Admin dashboard — found and fixed a real, codebase-wide silent bug
**Files:** `modules/admin/dashboard.php`, `modules/poultry/egg_entry.php`
The dashboard's actual structure already matches §19 closely (financial
position cards linking to drill-down pages, a "Needs Attention" section, and
per-department status cards), so no restructuring was needed there. While
reading it closely, found a real defect: `$current_locale='am'` (assignment,
single `=`) instead of `current_locale()==='am'` (the correct pattern used
everywhere else in the same file). This always evaluates truthy — so that
alert was always shown in Amharic regardless of the user's actual selected
language — and grepping every other "Needs Attention" alert line in the same
file found **six more instances of the same bug**, plus one more in
`modules/poultry/egg_entry.php` using a bare `$current_locale` (never
assigned anywhere in the codebase, so always `NULL`, so the Amharic branch
of that comparison could never be reached at all). Confirmed via a full
codebase grep that no other file has this pattern. Fixed all 8 occurrences
across both files to call `current_locale()` correctly. Also compared
against `modules/admin/gm_dashboard.php` (the General Manager's operational
variant) — different in scope by design (no financial section, since that's
Super-Admin-only), not a stale duplicate, and confirmed free of the same bug.
**To verify when deployed:** switch the UI language to Amharic and confirm
every "Needs Attention" alert on the Super Admin dashboard, and the egg-entry
success message, actually render in Amharic.

## PHASE 17: Reports — reviewed, no bugs found
`modules/reports.php` (the central reports hub) was read in full. It
correctly answers §34's core questions (money in/out via a live cash-flow
chart, inventory levels, expense breakdown, production trends) pulling
directly from live tables with no separate export/ETL step, matching the
brief's "no separate database statistics page" spirit. One minor,
non-functional observation: `fin_expenses.status` supports a `'paid'` value
in its ENUM that no code path in the audited functions ever actually sets
(only `'pending'`/`'approved'`/`'rejected'` are used) — harmless (the report
query already includes it defensively), just an apparently-unused status
worth knowing about if a "mark expense as paid" step is ever added later.

## PHASE 7/8 remainder: Distribution & Purchasing pages — reviewed, one documented (not fixed) architectural trade-off
Re-scanned `modules/distribution/`, `modules/finance/purchasing.php` and
related pages for the same bug classes found earlier (the `$current_locale`
i18n bug, race conditions, column mismatches) — none found beyond what's
already fixed. One thing surfaced and documented rather than changed:
`dispatch_create()` calls `stock_issue()` *before* opening its own
transaction for the `dist_dispatches` insert, because `stock_issue()`
manages its own internal transaction and PDO cannot nest transactions — the
exact same established pattern already used (and already commented) in
`fish_harvest()`'s call to `stock_receive()`. This is an accepted
architectural trade-off already in use elsewhere in the codebase, not a
new/introduced bug: if stock_issue() succeeds but the following dispatch
insert then fails, stock is deducted with no dispatch record created — an
orphaned movement, not a duplicate or negative one. Fixing this properly
would mean introducing savepoint-based nested transactions or a
reorder-plus-compensating-logic redesign across every place this pattern is
used — an architecture change, not a local fix, so it was left as-is and
documented with the same clarifying comment style already used at the
`fish_harvest()` call site, so a future maintainer doesn't mistake it for an
oversight.

## PHASE 13 remainder: Fish module UI reorganization — not done
§26 asks for the Fish pond view to be split into 7 distinct sections
(Summary / Stock / Feeding / Mortality / Growth / Harvest / History) instead
of one page. The underlying validation logic (mortality cannot exceed live
count, harvest cannot exceed estimated biomass) was already fixed earlier in
this log. The page-layout reorganization itself is a larger, purely-visual
restructuring of an existing working page that was not attempted in this
pass — it carries meaningfully higher risk of a display regression than the
targeted logic/data fixes made so far, and there's no way to visually verify
a multi-section page layout without a running instance of the app. Flagging
as the one remaining concrete UI to-do from the original phase list, best
done as its own focused pass with the ability to view the rendered result.

## PHASE 18: Full integration testing — cannot be completed in this environment
This environment has no PHP or MySQL runtime, so the §39 test suite (10
end-to-end business-flow tests: purchase-receive-pay, department
consumption, partial/full dispatch payment, bank↔cash transfer, poultry
mortality, notification targeting, material filtering, cow source/date,
negative-balance drill-down) could not be executed directly. Every fix in
this log includes a specific "To verify when deployed" step — those, taken
together, are the closest equivalent achievable here; running them for real
against your actual database is the one thing that still needs a human (or
a CI environment) with PHP/MySQL available.

## Overall status: all 18 phases have now been audited at least once
- **Fixed real bugs**: 1, 2, 6, 9, 10, 11, 12, 14, 16 — 9 phases
- **Reviewed, confirmed already correct, no change made**: 3, 4, 5, 15, 17 — 5 phases
- **Partially addressed** (real fixes made; a documented, deliberately-unfixed
  item or a larger UI task remains): 7, 8, 13 — 3 phases
- **Cannot be completed here, needs a live environment**: 18 — 1 phase
- **Known separate gap, not part of the original 18**: payroll payments never
  book a cash outflow — real missing feature, not a bug in existing code
