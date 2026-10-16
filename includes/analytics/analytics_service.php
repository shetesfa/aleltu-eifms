<?php
/**
 * Super Admin analytics - query + accounting layer.
 *
 * ACCOUNTING DEFINITIONS (also shown to the user on the page and in
 * docs/SUPER_ADMIN_ANALYTICS.md). Keep them in sync.
 *
 *  PHYSICAL PRODUCTION  quantities from production logs (litres, eggs, kg).
 *                       Never money. Never called income.
 *  RECORDED SALES       dist_dispatches.total_price for dispatches that left the
 *  (income)             farm (status dispatched/in_transit/delivered/partially_delivered),
 *                       dated by dispatch date, ONLY for channels that are real
 *                       outside sales. Cow sales (dairy_cow_exit_records.sale_price)
 *                       are added to dairy. Sales to company shops are internal
 *                       transfers and are excluded unless the Super Admin setting
 *                       says shops are outside customers.
 *  CASH/BANK RECEIPTS   money actually received: dist_dispatch_payments by payment
 *                       date (+ ledger receipts for cow sales). Same channel rule.
 *  RECORDED EXPENSES    (a) approved fin_expenses vouchers (owner drawings excluded),
 *                       (b) stock consumed by the unit (wh_stock_issues valued at the
 *                           batch unit_cost; finished goods leaving for sale are NOT
 *                           an expense, purchases are NOT an expense until consumed),
 *                       (c) payroll cost of finalized payroll runs, by employee department
 *                           (gross = net_pay + deductions),
 *                       (d) ledger-only expense rows that have no voucher,
 *                       (e) stock write-offs (decrease adjustments).
 *                       Internal transfers, inventory purchases and payroll cash payments
 *                       are never expenses (they are shown in the reconciliation).
 *  CALCULATED PROFIT    recorded sales - recorded expenses, per unit "direct
 *  (accounting)         contribution" (before veterinary & shared overhead) and for
 *                       the company in total. Status 'incomplete' whenever a known
 *                       data gap touches the figure; 'cost_centre' for units without
 *                       any outside income.
 *
 * Every figure comes from ONE accumulator keyed by (date, unit, metric); the
 * month rows and the totals are sums of the same numbers, so they cannot disagree.
 * Money is accumulated as integer cents (no float drift).
 * No query runs per table row: each metric is one grouped query for the whole range.
 */

require_once __DIR__ . '/analytics_dates.php';

const AN_SHARED = 'shared';
const AN_UNATTR = 'unattributed';
const AN_REVENUE_UNITS = ['dairy', 'poultry', 'fish'];
const AN_COST_CENTRES = ['feed_production', 'veterinary'];
const AN_ALL_BUCKET_UNITS = ['dairy', 'poultry', 'fish', 'feed_production', 'veterinary', AN_SHARED, AN_UNATTR];

const AN_OPERATING_WITHDRAWAL_TYPES = ['operating_expense', 'holiday_gift', 'admin_protocol', 'food_withdrawal'];
const AN_DISPATCH_COUNTED_STATUSES = ['dispatched', 'in_transit', 'delivered', 'partially_delivered'];

function an_cents($v): int
{
    return (int)round(((float)$v) * 100);
}

/** Maps a fin unit_tag / stock destination_type / department business_unit to an analytics unit key. */
function an_unit_for_tag(?string $tag): string
{
    return match ($tag) {
        'dairy', 'poultry', 'fish', 'feed_production', 'veterinary' => $tag,
        default => AN_SHARED,
    };
}

/** Lists prerequisites that are missing (migration not run yet). Empty array = ready. */
function an_schema_status(PDO $pdo): array
{
    $missing = [];
    $col = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'dist_dispatches' AND column_name = 'sale_channel'"
    )->fetchColumn();
    if (!(int)$col) {
        $missing[] = 'dist_dispatches.sale_channel (run sql/011_super_admin_analytics.sql)';
    }
    $tbl = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = 'rpt_item_unit_map'"
    )->fetchColumn();
    if (!(int)$tbl) {
        $missing[] = 'table rpt_item_unit_map (run sql/011_super_admin_analytics.sql)';
    }
    return $missing;
}

function an_shop_treatment(PDO $pdo): string
{
    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'analytics_shop_dispatch_treatment'");
    $st->execute();
    $v = $st->fetchColumn();
    return $v === 'external' ? 'external' : 'internal';
}

/** Holds every number by (month-bucket-agnostic) date, unit and metric. */
final class AnAcc
{
    /** @var array<string, array<string, array<string, float|int>>> [date][unit][metric] */
    public array $byDate = [];
    /** @var array<string, array<string, true>> [unit][itemUnitLabel] */
    public array $qtyUnits = [];

    public function add(string $date, string $unit, string $metric, float|int $value): void
    {
        if ($value == 0) {
            return;
        }
        $this->byDate[$date][$unit][$metric] = ($this->byDate[$date][$unit][$metric] ?? 0) + $value;
    }

    public function label(string $unit, string $metric, string $itemUnit): void
    {
        if ($itemUnit !== '') {
            $this->qtyUnits[$unit . '|' . $metric][$itemUnit] = true;
        }
    }

    /** Sum of one metric for one unit across all dates. */
    public function total(string $unit, string $metric): float|int
    {
        $t = 0;
        foreach ($this->byDate as $units) {
            $t += $units[$unit][$metric] ?? 0;
        }
        return $t;
    }

    /** @return array<string, array<string, array<string, float|int>>> [monthKey][unit][metric] */
    public function byMonth(): array
    {
        $out = [];
        foreach ($this->byDate as $date => $units) {
            $mk = an_month_key($date);
            foreach ($units as $unit => $metrics) {
                foreach ($metrics as $m => $v) {
                    $out[$mk][$unit][$m] = ($out[$mk][$unit][$m] ?? 0) + $v;
                }
            }
        }
        ksort($out);
        return $out;
    }
}

/** A data-quality / missing-data finding. severity 'warn' makes the touched profit 'incomplete'. */
function an_dq(array &$dq, string $code, string $severity, string $unit, int $count = 0, int $cents = 0, float $qty = 0.0, string $extra = ''): void
{
    if ($count === 0 && $cents === 0 && $qty == 0.0) {
        return;
    }
    $dq[] = ['code' => $code, 'severity' => $severity, 'unit' => $unit, 'count' => $count, 'cents' => $cents, 'qty' => $qty, 'extra' => $extra];
}

/** Merges findings that share (code, severity, unit, extra) so the page lists each problem once. */
function an_dq_merge(array $dq): array
{
    $out = [];
    foreach ($dq as $f) {
        $k = $f['code'] . '|' . $f['severity'] . '|' . $f['unit'] . '|' . $f['extra'];
        if (!isset($out[$k])) {
            $out[$k] = $f;
        } else {
            $out[$k]['count'] += $f['count'];
            $out[$k]['cents'] += $f['cents'];
            $out[$k]['qty'] += $f['qty'];
        }
    }
    return array_values($out);
}

/**
 * Runs every grouped query for [$from, $to] and returns the filled accumulator,
 * data-quality list and the ledger-flow classification.
 */
function an_collect(PDO $pdo, string $from, string $to): array
{
    $acc = new AnAcc();
    $dq = [];
    $treat = an_shop_treatment($pdo);
    $next = an_next_day($to);
    $statusIn = "'" . implode("','", AN_DISPATCH_COUNTED_STATUSES) . "'";

    /* ---------- A. RECORDED SALES (dispatches) + physical dispatched quantity ---------- */
    $st = $pdo->prepare(
        "SELECT DATE(COALESCE(d.dispatched_at, d.planned_dispatch_at)) AS dt,
                COALESCE(m.business_unit, 'unattributed') AS unit,
                d.sale_channel AS ch,
                i.unit AS item_unit,
                SUM(d.total_price) AS amt, SUM(d.quantity_dispatched) AS qty,
                SUM(GREATEST(d.total_price - d.amount_paid, 0)) AS outstanding,
                COUNT(*) AS n, SUM(d.status = 'partially_delivered') AS variance_n
           FROM dist_dispatches d
           JOIN wh_stock_items i ON i.id = d.stock_item_id
           LEFT JOIN rpt_item_unit_map m ON m.stock_item_id = d.stock_item_id
          WHERE d.status IN ($statusIn)
            AND COALESCE(d.dispatched_at, d.planned_dispatch_at) >= :f
            AND COALESCE(d.dispatched_at, d.planned_dispatch_at) <  :t
          GROUP BY dt, unit, ch, item_unit"
    );
    $st->execute([':f' => $from, ':t' => $next]);
    foreach ($st->fetchAll() as $r) {
        $unit = $r['unit'];
        $cents = an_cents($r['amt']);
        $acc->add($r['dt'], $unit, 'disp_qty', (float)$r['qty']);
        $acc->label($unit, 'disp_qty', (string)$r['item_unit']);
        $counted = ($r['ch'] === 'direct_sale') || ($r['ch'] === 'shop_transfer' && $treat === 'external');
        if ($counted) {
            $acc->add($r['dt'], $unit, 'sales', $cents);
            $acc->add($r['dt'], $unit, 'sold_qty', (float)$r['qty']);
            $acc->label($unit, 'sold_qty', (string)$r['item_unit']);
            $acc->add($r['dt'], $unit, 'receivable', an_cents($r['outstanding']));
        } elseif ($r['ch'] === 'shop_transfer') {
            $acc->add($r['dt'], $unit, 'memo_shop_transfer', $cents);
            $acc->add($r['dt'], $unit, 'memo_shop_transfer_qty', (float)$r['qty']);
            an_dq($dq, 'shop_sales_not_in_system', 'warn', $unit, (int)$r['n'], $cents);
        } elseif ($r['ch'] === 'internal_use') {
            $acc->add($r['dt'], $unit, 'memo_internal_use_qty', (float)$r['qty']);
            if ($cents > 0) {
                an_dq($dq, 'internal_use_with_price', 'warn', $unit, (int)$r['n'], $cents);
            }
        } else { // NULL channel
            $acc->add($r['dt'], $unit, 'memo_unclassified', $cents);
            an_dq($dq, 'unclassified_dispatches', 'warn', $unit, (int)$r['n'], $cents);
        }
        if ($unit === AN_UNATTR && $cents > 0 && $counted) {
            an_dq($dq, 'unmapped_sales_item', 'warn', AN_UNATTR, (int)$r['n'], $cents);
        }
        if ((int)$r['variance_n'] > 0) {
            an_dq($dq, 'partial_delivery_priced_on_dispatch', 'info', $unit, (int)$r['variance_n']);
        }
    }

    /* ---------- B. CASH / BANK RECEIPTS for dispatches ---------- */
    $st = $pdo->prepare(
        "SELECT DATE(p.created_at) AS dt, COALESCE(m.business_unit, 'unattributed') AS unit,
                d.sale_channel AS ch, p.payment_method AS pm, SUM(p.amount) AS amt, COUNT(*) AS n
           FROM dist_dispatch_payments p
           JOIN dist_dispatches d ON d.id = p.dispatch_id
           LEFT JOIN rpt_item_unit_map m ON m.stock_item_id = d.stock_item_id
          WHERE p.created_at >= :f AND p.created_at < :t
          GROUP BY dt, unit, ch, pm"
    );
    $st->execute([':f' => $from, ':t' => $next]);
    foreach ($st->fetchAll() as $r) {
        $cents = an_cents($r['amt']);
        $counted = ($r['ch'] === 'direct_sale') || ($r['ch'] === 'shop_transfer' && $treat === 'external');
        if ($counted) {
            $acc->add($r['dt'], $r['unit'], 'rcpt_total', $cents);
            $key = match ($r['pm']) { 'cash' => 'rcpt_cash', 'bank' => 'rcpt_bank', 'telebirr', 'mobile' => 'rcpt_telebirr', default => 'rcpt_other' };
            $acc->add($r['dt'], $r['unit'], $key, $cents);
        } elseif ($r['ch'] === 'shop_transfer') {
            $acc->add($r['dt'], $r['unit'], 'memo_rcpt_internal', $cents); // settlement from a company shop: not income
        } else {
            $acc->add($r['dt'], $r['unit'], 'memo_rcpt_unclassified', $cents);
            an_dq($dq, 'unclassified_receipts', 'warn', $r['unit'], (int)$r['n'], $cents);
        }
    }

    /* ---------- C. COW SALES (recorded sale price) + their ledger receipts ---------- */
    $st = $pdo->prepare(
        "SELECT exit_date AS dt, SUM(COALESCE(sale_price,0)) AS amt, COUNT(*) AS n,
                SUM(sale_price IS NULL OR sale_price = 0) AS no_price
           FROM dairy_cow_exit_records
          WHERE exit_type = 'sale' AND exit_date BETWEEN :f AND :t
          GROUP BY exit_date"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    $livestockN = 0; $livestockCents = 0;
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['amt']);
        $acc->add($r['dt'], 'dairy', 'sales', $c);
        $acc->add($r['dt'], 'dairy', 'sales_livestock', $c);
        $acc->add($r['dt'], 'dairy', 'livestock_sold_n', (int)$r['n']);
        $livestockN += (int)$r['n'];
        $livestockCents += $c;
        if ((int)$r['no_price'] > 0) {
            an_dq($dq, 'cow_sale_without_price', 'warn', 'dairy', (int)$r['no_price']);
        }
    }
    if ($livestockN > 0) {
        an_dq($dq, 'livestock_cost_basis_missing', 'warn', 'dairy', $livestockN, $livestockCents);
    }
    $st = $pdo->prepare(
        "SELECT transaction_date AS dt, payment_method AS pm, SUM(amount) AS amt
           FROM fin_transactions
          WHERE direction = 'inflow' AND source_table = 'dairy_cow_exit_records'
            AND transaction_date BETWEEN :f AND :t
          GROUP BY transaction_date, payment_method"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    $livestockRcpt = 0;
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['amt']);
        $acc->add($r['dt'], 'dairy', 'rcpt_total', $c);
        $acc->add($r['dt'], 'dairy', 'rcpt_livestock', $c);
        $acc->add($r['dt'], 'dairy', $r['pm'] === 'cash' ? 'rcpt_cash' : 'rcpt_bank', $c);
        $livestockRcpt += $c;
    }
    if ($livestockCents > $livestockRcpt) {
        an_dq($dq, 'cow_sale_receipts_not_linked', 'warn', 'dairy', 0, $livestockCents - $livestockRcpt);
    }

    /* ---------- D. EXPENSES ---------- */
    // D1 vouchers
    $st = $pdo->prepare(
        "SELECT expense_date AS dt, unit_tag, withdrawal_type AS wt, SUM(amount) AS amt, COUNT(*) AS n
           FROM fin_expenses
          WHERE status IN ('approved','paid') AND expense_date BETWEEN :f AND :t
          GROUP BY expense_date, unit_tag, withdrawal_type"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['amt']);
        $unit = an_unit_for_tag($r['unit_tag']);
        if ($r['wt'] === 'owner_drawing') {
            $acc->add($r['dt'], AN_SHARED, 'memo_owner_drawings', $c); // equity withdrawal, not an expense
        } elseif (in_array($r['wt'], AN_OPERATING_WITHDRAWAL_TYPES, true)) {
            $acc->add($r['dt'], $unit, 'exp_voucher', $c);
            $acc->add($r['dt'], $unit, 'expenses', $c);
        } else {
            $acc->add($r['dt'], AN_SHARED, 'memo_unclassified_withdrawal', $c);
            an_dq($dq, 'unclassified_withdrawals', 'warn', AN_SHARED, (int)$r['n'], $c, 0.0, (string)$r['wt']);
        }
    }

    // D2 stock consumed by units (finished goods leaving for sale are not an expense)
    $st = $pdo->prepare(
        "SELECT DATE(iss.issued_at) AS dt, iss.destination_type AS dest, i.product_class AS cls,
                SUM(iss.quantity) AS qty,
                SUM(ROUND(iss.quantity * COALESCE(b.unit_cost, 0), 2)) AS val,
                SUM(CASE WHEN COALESCE(b.unit_cost, 0) = 0 THEN iss.quantity ELSE 0 END) AS unpriced_qty,
                SUM(COALESCE(b.unit_cost, 0) = 0) AS unpriced_n, COUNT(*) AS n
           FROM wh_stock_issues iss
           JOIN wh_stock_items i ON i.id = iss.stock_item_id
           LEFT JOIN wh_stock_batches b ON b.id = iss.stock_batch_id
          WHERE iss.issued_at >= :f AND iss.issued_at < :t
            AND iss.destination_type <> 'distribution'
          GROUP BY dt, dest, cls"
    );
    $st->execute([':f' => $from, ':t' => $next]);
    foreach ($st->fetchAll() as $r) {
        $unit = an_unit_for_tag($r['dest']);
        $c = an_cents($r['val']);
        $acc->add($r['dt'], $unit, 'exp_inputs', $c);
        $acc->add($r['dt'], $unit, 'expenses', $c);
        if ($r['cls'] === 'feed') {
            $acc->add($r['dt'], $unit, 'feed_issued_qty', (float)$r['qty']);
            $acc->add($r['dt'], $unit, 'feed_issued_value', $c);
        }
        if ($r['cls'] === 'medicine' && $unit === 'veterinary') {
            $acc->add($r['dt'], 'veterinary', 'medicine_value', $c);
            $acc->add($r['dt'], 'veterinary', 'medicine_issue_n', (int)$r['n']);
        }
        if ((float)$r['unpriced_qty'] > 0) {
            an_dq($dq, 'unpriced_stock_issues', 'warn', $unit, (int)$r['unpriced_n'], 0, (float)$r['unpriced_qty'], (string)$r['cls']);
        }
        if (!in_array($r['dest'], ['dairy', 'poultry', 'fish', 'feed_production', 'veterinary', 'warehouse', 'general_overhead'], true) && $c !== 0) {
            an_dq($dq, 'stock_issue_unknown_destination', 'warn', AN_SHARED, 0, $c, 0.0, (string)$r['dest']);
        }
    }

    // D3 payroll (finalized runs; period assigned by period_end)
    $st = $pdo->prepare(
        "SELECT pr.period_end AS dt, COALESCE(dep.business_unit, 'unassigned') AS bu,
                SUM(ps.net_pay + ps.deductions) AS gross, COUNT(*) AS n
           FROM fin_payslips ps
           JOIN fin_payroll_runs pr ON pr.id = ps.payroll_run_id AND pr.status = 'finalized'
           JOIN employees e ON e.id = ps.employee_id
           LEFT JOIN departments dep ON dep.id = e.department_id
          WHERE pr.period_end BETWEEN :f AND :t
          GROUP BY pr.period_end, bu"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    $payrollRows = 0;
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['gross']);
        $unit = an_unit_for_tag($r['bu']);
        $acc->add($r['dt'], $unit, 'exp_payroll', $c);
        $acc->add($r['dt'], $unit, 'expenses', $c);
        $payrollRows += (int)$r['n'];
    }
    if ($payrollRows > 0) {
        an_dq($dq, 'employer_pension_not_recorded', 'info', AN_SHARED, $payrollRows);
    }

    // D4 ledger-only expenses: 'expense' cash-outs that have NO voucher behind them
    $st = $pdo->prepare(
        "SELECT t.transaction_date AS dt, t.unit_tag, SUM(t.amount) AS amt, COUNT(*) AS n
           FROM fin_transactions t
          WHERE t.direction = 'outflow' AND t.category = 'expense'
            AND t.transaction_date BETWEEN :f AND :t
            AND NOT EXISTS (SELECT 1 FROM fin_expenses e WHERE t.source_table = 'fin_expenses' AND e.id = t.source_id)
          GROUP BY t.transaction_date, t.unit_tag"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['amt']);
        $unit = an_unit_for_tag($r['unit_tag']);
        $acc->add($r['dt'], $unit, 'exp_ledger_only', $c);
        $acc->add($r['dt'], $unit, 'expenses', $c);
        an_dq($dq, 'ledger_expense_without_voucher', 'info', $unit, (int)$r['n'], $c);
    }

    // D5 write-offs (decrease adjustments), valued at the batch cost when known
    $st = $pdo->prepare(
        "SELECT DATE(a.created_at) AS dt,
                SUM(ROUND(a.quantity * COALESCE(b.unit_cost, 0), 2)) AS val,
                SUM(COALESCE(b.unit_cost, 0) = 0) AS unpriced_n
           FROM wh_stock_adjustments a
           LEFT JOIN wh_stock_batches b ON b.id = a.stock_batch_id
          WHERE a.adjustment_type = 'decrease' AND a.created_at >= :f AND a.created_at < :t
          GROUP BY dt"
    );
    $st->execute([':f' => $from, ':t' => $next]);
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['val']);
        $acc->add($r['dt'], AN_SHARED, 'exp_writeoff', $c);
        $acc->add($r['dt'], AN_SHARED, 'expenses', $c);
        an_dq($dq, 'writeoff_unpriced', 'warn', AN_SHARED, (int)$r['unpriced_n']);
    }

    /* ---------- E. PHYSICAL PRODUCTION ---------- */
    $st = $pdo->prepare(
        "SELECT s.session_date AS dt, SUM(e.yield_liters) AS l,
                SUM(CASE WHEN s.session_type = 'morning' THEN e.yield_liters ELSE 0 END) AS am,
                SUM(CASE WHEN s.session_type = 'evening' THEN e.yield_liters ELSE 0 END) AS pm
           FROM dairy_milking_sessions s
           JOIN dairy_milk_yield_entries e ON e.milking_session_id = s.id
          WHERE s.session_date BETWEEN :f AND :t
          GROUP BY s.session_date"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'dairy', 'milk_l', (float)$r['l']);
        $acc->add($r['dt'], 'dairy', 'milk_morning_l', (float)$r['am']);
        $acc->add($r['dt'], 'dairy', 'milk_evening_l', (float)$r['pm']);
    }

    // warehouse hand-overs by producing unit (sent vs actually received)
    $st = $pdo->prepare(
        "SELECT DATE(sent_at) AS dt, source_type AS src, SUM(sent_quantity) AS sent,
                SUM(COALESCE(received_quantity, 0)) AS recv,
                SUM(status = 'pending_receipt') AS pending_n,
                SUM(CASE WHEN status = 'pending_receipt' THEN sent_quantity ELSE 0 END) AS pending_qty
           FROM wh_store_receiving
          WHERE source_type IN ('dairy','poultry','fish','feed_production')
            AND sent_at >= :f AND sent_at < :t
          GROUP BY dt, src"
    );
    $st->execute([':f' => $from, ':t' => $next]);
    foreach ($st->fetchAll() as $r) {
        $unit = $r['src'];
        $acc->add($r['dt'], $unit, 'store_sent_qty', (float)$r['sent']);
        $acc->add($r['dt'], $unit, 'store_received_qty', (float)$r['recv']);
        $acc->add($r['dt'], $unit, 'store_pending_qty', (float)$r['pending_qty']);
        if ((int)$r['pending_n'] > 0) {
            an_dq($dq, 'store_receipt_pending', 'info', $unit, (int)$r['pending_n'], 0, (float)$r['pending_qty']);
        }
    }

    $st = $pdo->prepare(
        "SELECT collection_date AS dt, SUM(whole_count) AS whole, SUM(cracked_count) AS cracked, SUM(broken_count) AS broken
           FROM poultry_egg_collections WHERE collection_date BETWEEN :f AND :t GROUP BY collection_date"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'poultry', 'eggs_whole', (int)$r['whole']);
        $acc->add($r['dt'], 'poultry', 'eggs_cracked', (int)$r['cracked']);
        $acc->add($r['dt'], 'poultry', 'eggs_broken', (int)$r['broken']);
        $acc->add($r['dt'], 'poultry', 'eggs_total', (int)$r['whole'] + (int)$r['cracked'] + (int)$r['broken']);
    }
    $st = $pdo->prepare("SELECT log_date AS dt, SUM(`count`) AS c FROM poultry_mortality_logs WHERE log_date BETWEEN :f AND :t GROUP BY log_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'poultry', 'bird_mortality', (int)$r['c']);
    }

    $st = $pdo->prepare("SELECT harvest_date AS dt, SUM(quantity_kg) AS kg FROM fish_harvest_records WHERE harvest_date BETWEEN :f AND :t GROUP BY harvest_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'fish', 'fish_harvest_kg', (float)$r['kg']);
    }
    $st = $pdo->prepare(
        "SELECT DATE(completed_at) AS dt, SUM(quantity_kg) AS kg FROM fish_feeding_logs
          WHERE status = 'completed' AND completed_at >= :f AND completed_at < :t GROUP BY dt"
    );
    $st->execute([':f' => $from, ':t' => $next]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'fish', 'fish_feed_kg', (float)$r['kg']);
    }
    $st = $pdo->prepare("SELECT log_date AS dt, SUM(`count`) AS c FROM fish_mortality_logs WHERE log_date BETWEEN :f AND :t GROUP BY log_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'fish', 'fish_mortality', (int)$r['c']);
    }
    $st = $pdo->prepare("SELECT stocking_date AS dt, SUM(stocking_quantity) AS q FROM fish_stocks WHERE stocking_date BETWEEN :f AND :t GROUP BY stocking_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'fish', 'fish_stocked', (int)$r['q']);
    }

    $st = $pdo->prepare("SELECT harvest_date AS dt, SUM(yield_kg) AS kg FROM feed_harvest_records WHERE harvest_date BETWEEN :f AND :t GROUP BY harvest_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'feed_production', 'feed_harvest_kg', (float)$r['kg']);
    }

    /* ---------- F. VETERINARY / HEALTH (de-duplicated between the two cow health tables) ---------- */
    $st = $pdo->prepare(
        "SELECT dt, st, COUNT(*) AS n FROM (
            SELECT record_date AS dt, 'dairy_cow' AS st, cow_id AS sid, COALESCE(condition_name,'') AS c FROM dairy_health_records
             WHERE record_date BETWEEN :f1 AND :t1
            UNION
            SELECT record_date, subject_type, subject_id, COALESCE(condition_name,'') FROM vet_health_records
             WHERE record_date BETWEEN :f2 AND :t2
         ) x GROUP BY dt, st"
    );
    $st->execute([':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'veterinary', 'vet_treatments', (int)$r['n']);
        $acc->add($r['dt'], 'veterinary', 'vet_treatments_' . $r['st'], (int)$r['n']);
    }
    $st = $pdo->prepare(
        "SELECT dt, COUNT(*) AS n FROM (
            SELECT administered_date AS dt, cow_id AS sid, vaccine_name AS v FROM dairy_vaccination_records
             WHERE status = 'administered' AND administered_date BETWEEN :f1 AND :t1
            UNION
            SELECT administered_date, subject_id, vaccine_name FROM vet_vaccination_records
             WHERE status = 'administered' AND administered_date BETWEEN :f2 AND :t2
         ) x GROUP BY dt"
    );
    $st->execute([':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'veterinary', 'vet_vaccinations', (int)$r['n']);
    }
    $st = $pdo->prepare("SELECT detected_date AS dt, COUNT(*) AS n, SUM(quarantine_flag) AS q FROM vet_disease_incidents WHERE detected_date BETWEEN :f AND :t GROUP BY detected_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'veterinary', 'vet_incidents', (int)$r['n']);
        $acc->add($r['dt'], 'veterinary', 'vet_quarantines', (int)$r['q']);
    }
    $st = $pdo->prepare("SELECT exit_date AS dt, COUNT(*) AS n FROM dairy_cow_exit_records WHERE exit_type = 'death' AND exit_date BETWEEN :f AND :t GROUP BY exit_date");
    $st->execute([':f' => $from, ':t' => $to]);
    foreach ($st->fetchAll() as $r) {
        $acc->add($r['dt'], 'dairy', 'cow_deaths', (int)$r['n']);
    }

    /* ---------- G. LEDGER FLOWS (reconciliation: what moved through cash/bank and how it is treated) ---------- */
    $st = $pdo->prepare(
        "SELECT direction, COALESCE(category,'') AS category, COALESCE(source_table,'') AS src,
                SUM(amount) AS amt, COUNT(*) AS n
           FROM fin_transactions
          WHERE transaction_date BETWEEN :f AND :t
          GROUP BY direction, category, src"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    $ledger = [];
    foreach ($st->fetchAll() as $r) {
        $c = an_cents($r['amt']);
        [$bucket, $treatment] = an_classify_ledger($r['direction'], $r['category'], $r['src']);
        $ledger[$bucket]['cents'] = ($ledger[$bucket]['cents'] ?? 0) + $c;
        $ledger[$bucket]['n'] = ($ledger[$bucket]['n'] ?? 0) + (int)$r['n'];
        $ledger[$bucket]['direction'] = $r['direction'];
        $ledger[$bucket]['treatment'] = $treatment;
        if ($bucket === 'unclassified_outflow' || $bucket === 'unclassified_inflow') {
            an_dq($dq, $bucket . 's_excluded', 'warn', AN_SHARED, (int)$r['n'], $c, 0.0, (string)$r['category']);
        }
    }
    // ledger copy of dispatch receipts vs the payment table (they should match)
    $ledgerDispatch = (int)($ledger['sales_receipts']['cents'] ?? 0);
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM dist_dispatch_payments WHERE created_at >= :f AND created_at < :t");
    $st->execute([':f' => $from, ':t' => $next]);
    $payTable = an_cents($st->fetchColumn());
    if ($ledgerDispatch !== $payTable) {
        an_dq($dq, 'receipts_ledger_mismatch', 'info', AN_SHARED, 0, $payTable - $ledgerDispatch);
    }

    $dq = an_dq_merge($dq);

    return ['acc' => $acc, 'dq' => $dq, 'ledger' => $ledger, 'treatment' => $treat, 'ledger_vs_payments' => ['ledger' => $ledgerDispatch, 'payments' => $payTable]];
}

/** Classifies a ledger row: [bucket, how reports treat it]. */
function an_classify_ledger(string $direction, string $category, string $source): array
{
    $isTransfer = (bool)preg_match('/transfer/i', $category) && !preg_match('/fee/i', $category);
    if ($isTransfer) {
        return ['internal_transfer_' . $direction, 'excluded: internal transfer between own accounts'];
    }
    if ($direction === 'inflow') {
        if ($source === 'dist_dispatches') {
            return ['sales_receipts', 'receipts of recorded sales (counted via payment table)'];
        }
        if ($source === 'dairy_cow_exit_records') {
            return ['livestock_receipts', 'receipts of cow sales'];
        }
        return ['unclassified_inflow', 'NOT income: unknown inflow, review'];
    }
    return match (true) {
        $category === 'purchase' => ['inventory_purchases', 'not an expense until the stock is consumed'],
        $category === 'payroll_payment' => ['payroll_cash', 'cash settlement; expense counted from payslips'],
        $category === 'expense' => ['expense_payments', 'expense counted from vouchers'],
        str_starts_with($category, 'withdrawal_') => ['withdrawal_payments', 'voucher based; owner drawings excluded from expenses'],
        default => ['unclassified_outflow', 'NOT counted as expense: unknown outflow, review'],
    };
}

/** Point-in-time stock/inventory figures (not date-filtered: they describe "now"). */
function an_snapshots(PDO $pdo): array
{
    $s = [];
    $rows = $pdo->query(
        "SELECT i.product_class AS cls, COALESCE(m.business_unit,'') AS unit, i.unit AS u,
                SUM(b.quantity_on_hand) AS qty, SUM(ROUND(b.quantity_on_hand * COALESCE(b.unit_cost,0), 2)) AS val,
                SUM(CASE WHEN COALESCE(b.unit_cost,0) = 0 THEN b.quantity_on_hand ELSE 0 END) AS unpriced_qty
           FROM wh_stock_batches b
           JOIN wh_stock_items i ON i.id = b.stock_item_id
           LEFT JOIN rpt_item_unit_map m ON m.stock_item_id = i.id
          WHERE b.quantity_on_hand > 0
          GROUP BY cls, unit, u"
    )->fetchAll();
    $s['inventory'] = $rows;
    $s['feed_qty'] = 0.0; $s['feed_cents'] = 0; $s['feed_unpriced_qty'] = 0.0;
    $s['dairy_qty'] = 0.0; $s['poultry_qty'] = 0.0; $s['fish_qty'] = 0.0;
    foreach ($rows as $r) {
        if ($r['cls'] === 'feed') {
            $s['feed_qty'] += (float)$r['qty'];
            $s['feed_cents'] += an_cents($r['val']);
            $s['feed_unpriced_qty'] += (float)$r['unpriced_qty'];
        }
        if (in_array($r['unit'], ['dairy', 'poultry', 'fish'], true)) {
            $s[$r['unit'] . '_qty'] += (float)$r['qty'];
        }
    }
    $s['cows_active'] = (int)$pdo->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased')")->fetchColumn();
    $s['cows_sick'] = (int)$pdo->query("SELECT COUNT(*) FROM dairy_cows WHERE is_sick = 1 AND lifecycle_status NOT IN ('sold','deceased')")->fetchColumn();
    $s['flocks_active'] = (int)$pdo->query("SELECT COUNT(*) FROM poultry_flocks WHERE status = 'active'")->fetchColumn();
    $s['birds_live'] = (int)$pdo->query(
        "SELECT COALESCE(SUM(initial_population - cumulative_mortality - cumulative_culled_sold + cumulative_transfers_in),0)
           FROM poultry_flocks WHERE status = 'active'"
    )->fetchColumn();
    $s['ponds_stocked'] = (int)$pdo->query("SELECT COUNT(*) FROM fish_ponds WHERE status <> 'empty'")->fetchColumn();
    return $s;
}

/**
 * Builds per-unit totals, status and the company consolidation from the accumulator.
 * @return array{units: array, company: array, months: array}
 */
function an_assemble(array $collected, array $snap): array
{
    /** @var AnAcc $acc */
    $acc = $collected['acc'];
    $dq = $collected['dq'];
    $units = [];

    foreach (AN_ALL_BUCKET_UNITS as $u) {
        $t = fn(string $m) => $acc->total($u, $m);
        $income = (int)$t('sales');
        $expenses = (int)$t('expenses');
        $units[$u] = [
            'income' => $income,
            'expenses' => $expenses,
            'exp_parts' => [
                'voucher' => (int)$t('exp_voucher'),
                'inputs' => (int)$t('exp_inputs'),
                'payroll' => (int)$t('exp_payroll'),
                'ledger_only' => (int)$t('exp_ledger_only'),
                'writeoff' => (int)$t('exp_writeoff'),
            ],
            'receipts' => [
                'total' => (int)$t('rcpt_total'), 'cash' => (int)$t('rcpt_cash'),
                'bank' => (int)$t('rcpt_bank'), 'telebirr' => (int)$t('rcpt_telebirr'), 'other' => (int)$t('rcpt_other'),
            ],
            'receivable' => (int)$t('receivable'),
            'profit' => null, 'margin' => null, 'status' => 'no_activity', 'warnings' => [],
        ];
    }

    foreach ($dq as $f) {
        if ($f['severity'] === 'warn' && isset($units[$f['unit']])) {
            $units[$f['unit']]['warnings'][$f['code']] = true;
        }
    }

    foreach ($units as $u => &$r) {
        if (in_array($u, AN_REVENUE_UNITS, true) || ($r['income'] > 0 && in_array($u, AN_COST_CENTRES, true))) {
            if ($r['income'] === 0 && $r['expenses'] === 0) {
                $r['status'] = 'no_activity';
                continue;
            }
            $r['profit'] = $r['income'] - $r['expenses'];
            $r['margin'] = $r['income'] > 0 ? $r['profit'] / $r['income'] * 100 : null;
            $r['status'] = !empty($r['warnings']) ? 'incomplete' : ($r['profit'] >= 0 ? 'profit' : 'loss');
        } elseif (in_array($u, AN_COST_CENTRES, true)) {
            $r['status'] = $r['expenses'] > 0 ? 'cost_centre' : 'no_activity';
        } else {
            $r['status'] = $r['expenses'] > 0 || $r['income'] > 0 ? 'shared' : 'no_activity';
        }
    }
    unset($r);

    // Company = every bucket exactly once (no unit is added twice)
    $cIncome = $cExpenses = 0;
    $cRcpt = ['total' => 0, 'cash' => 0, 'bank' => 0, 'telebirr' => 0, 'other' => 0];
    $cRecv = 0;
    foreach ($units as $r) {
        $cIncome += $r['income'];
        $cExpenses += $r['expenses'];
        $cRecv += $r['receivable'];
        foreach ($cRcpt as $k => $_) {
            $cRcpt[$k] += $r['receipts'][$k];
        }
    }
    $companyWarn = [];
    foreach ($dq as $f) {
        if ($f['severity'] === 'warn') {
            $companyWarn[$f['code']] = true;
        }
    }
    $cProfit = $cIncome - $cExpenses;
    $company = [
        'income' => $cIncome, 'expenses' => $cExpenses, 'profit' => $cProfit,
        'margin' => $cIncome > 0 ? $cProfit / $cIncome * 100 : null,
        'receipts' => $cRcpt, 'receivable' => $cRecv,
        'warnings' => $companyWarn,
        'status' => ($cIncome === 0 && $cExpenses === 0) ? 'no_activity' : (!empty($companyWarn) ? 'incomplete' : ($cProfit >= 0 ? 'profit' : 'loss')),
        'memo' => [
            'shop_transfers' => (int)array_sum(array_map(fn($u) => $acc->total($u, 'memo_shop_transfer'), AN_ALL_BUCKET_UNITS)),
            'unclassified_sales' => (int)array_sum(array_map(fn($u) => $acc->total($u, 'memo_unclassified'), AN_ALL_BUCKET_UNITS)),
            'owner_drawings' => (int)$acc->total(AN_SHARED, 'memo_owner_drawings'),
            'shop_settlements' => (int)array_sum(array_map(fn($u) => $acc->total($u, 'memo_rcpt_internal'), AN_ALL_BUCKET_UNITS)),
        ],
    ];

    return ['units' => $units, 'company' => $company, 'months' => $acc->byMonth(), 'snap' => $snap];
}

/** One call for the page: collect + snapshots + assemble. */
function an_overview(PDO $pdo, string $from, string $to): array
{
    $collected = an_collect($pdo, $from, $to);
    $snap = an_snapshots($pdo);
    $assembled = an_assemble($collected, $snap);
    $assembled['dq'] = $collected['dq'];
    $assembled['ledger'] = $collected['ledger'];
    $assembled['treatment'] = $collected['treatment'];
    $assembled['acc'] = $collected['acc'];
    $assembled['ledger_vs_payments'] = $collected['ledger_vs_payments'];
    $assembled['range'] = ['from' => $from, 'to' => $to];
    return $assembled;
}
