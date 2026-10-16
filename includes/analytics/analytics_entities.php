<?php
/**
 * Super Admin analytics - entity level (cow / flock / pond / plot) and unit drill-down queries.
 *
 * Rules kept here:
 *  - one grouped query per table, joined by id (no query per row);
 *  - nothing is invented: when a cost or sale cannot be tied to the entity by a real
 *    record, the page shows an explicit "missing data" list instead of a number;
 *  - "allocated" values are clearly labelled estimates built from recorded unit sales
 *    and the entity's share of physical output. They are NOT booked income and are
 *    never used for profit.
 */

require_once __DIR__ . '/analytics_service.php';

/** Recorded sales that count as income for one unit in the period (dispatch channels only, same rule as the overview). */
function an_unit_counted_sales(PDO $pdo, string $unit, string $from, string $to): array
{
    $treat = an_shop_treatment($pdo);
    $channels = $treat === 'external' ? "('direct_sale','shop_transfer')" : "('direct_sale')";
    $statusIn = "'" . implode("','", AN_DISPATCH_COUNTED_STATUSES) . "'";
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(d.total_price),0) AS amt, COALESCE(SUM(d.quantity_dispatched),0) AS qty
           FROM dist_dispatches d
           JOIN rpt_item_unit_map m ON m.stock_item_id = d.stock_item_id AND m.business_unit = :u
          WHERE d.status IN ($statusIn) AND d.sale_channel IN $channels
            AND COALESCE(d.dispatched_at, d.planned_dispatch_at) >= :f
            AND COALESCE(d.dispatched_at, d.planned_dispatch_at) <  :t"
    );
    $st->execute([':u' => $unit, ':f' => $from, ':t' => an_next_day($to)]);
    $r = $st->fetch();
    return ['cents' => an_cents($r['amt']), 'qty' => (float)$r['qty']];
}

function an_like(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}

/* ------------------------------------------------------------------ LISTS */

function an_cows_list(PDO $pdo, string $from, string $to, string $q = ''): array
{
    $st = $pdo->prepare(
        "SELECT c.id, c.tag_number, c.name, c.sex, c.lifecycle_status, c.acquired_date, c.source,
                g.name AS group_name,
                COALESCE(y.l_period, 0) AS l_period, COALESCE(y.l_life, 0) AS l_life,
                y.first_d, y.last_d, COALESCE(h.n, 0) AS health_n,
                ex.exit_type, ex.exit_date, ex.sale_price
           FROM dairy_cows c
           LEFT JOIN dairy_cow_groups g ON g.id = c.cow_group_id
           LEFT JOIN (
                SELECT e.cow_id, SUM(e.yield_liters) AS l_life,
                       SUM(CASE WHEN s.session_date BETWEEN :f1 AND :t1 THEN e.yield_liters ELSE 0 END) AS l_period,
                       MIN(s.session_date) AS first_d, MAX(s.session_date) AS last_d
                  FROM dairy_milk_yield_entries e
                  JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
                 GROUP BY e.cow_id) y ON y.cow_id = c.id
           LEFT JOIN (
                SELECT sid, COUNT(*) AS n FROM (
                    SELECT cow_id AS sid, record_date AS d, COALESCE(condition_name,'') AS cn FROM dairy_health_records
                     WHERE record_date BETWEEN :f2 AND :t2
                    UNION
                    SELECT subject_id, record_date, COALESCE(condition_name,'') FROM vet_health_records
                     WHERE subject_type = 'dairy_cow' AND record_date BETWEEN :f3 AND :t3) u
                 GROUP BY sid) h ON h.sid = c.id
           LEFT JOIN dairy_cow_exit_records ex
                  ON ex.id = (SELECT MAX(x.id) FROM dairy_cow_exit_records x WHERE x.cow_id = c.id)
          WHERE (:q0 = '' OR c.tag_number LIKE :ql ESCAPE '\\\\' OR c.name LIKE :ql2 ESCAPE '\\\\' OR c.id = :qid)
          ORDER BY (c.lifecycle_status IN ('sold','deceased')), c.tag_number"
    );
    $st->execute([
        ':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to, ':f3' => $from, ':t3' => $to,
        ':q0' => $q, ':ql' => an_like($q), ':ql2' => an_like($q), ':qid' => ctype_digit($q) ? (int)$q : 0,
    ]);
    return $st->fetchAll();
}

function an_flocks_list(PDO $pdo, string $from, string $to, string $q = ''): array
{
    $st = $pdo->prepare(
        "SELECT f.id, f.breed, f.stocking_date, f.initial_population, f.cumulative_mortality, f.cumulative_culled_sold,
                f.cumulative_transfers_in, f.status, h.name AS house_name,
                (f.initial_population - f.cumulative_mortality - f.cumulative_culled_sold + f.cumulative_transfers_in) AS live_pop,
                COALESCE(e.whole,0) AS whole, COALESCE(e.cracked,0) AS cracked, COALESCE(e.broken,0) AS broken,
                COALESCE(e.days,0) AS egg_days, COALESCE(m.c,0) AS mort_period
           FROM poultry_flocks f
           JOIN poultry_houses h ON h.id = f.poultry_house_id
           LEFT JOIN (SELECT flock_id, SUM(whole_count) AS whole, SUM(cracked_count) AS cracked, SUM(broken_count) AS broken,
                             COUNT(DISTINCT collection_date) AS days
                        FROM poultry_egg_collections WHERE collection_date BETWEEN :f1 AND :t1 GROUP BY flock_id) e ON e.flock_id = f.id
           LEFT JOIN (SELECT flock_id, SUM(`count`) AS c FROM poultry_mortality_logs
                       WHERE log_date BETWEEN :f2 AND :t2 GROUP BY flock_id) m ON m.flock_id = f.id
          WHERE (:q0 = '' OR f.breed LIKE :ql ESCAPE '\\\\' OR h.name LIKE :ql2 ESCAPE '\\\\' OR f.id = :qid)
          ORDER BY f.status, f.id"
    );
    $st->execute([':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to,
        ':q0' => $q, ':ql' => an_like($q), ':ql2' => an_like($q), ':qid' => ctype_digit($q) ? (int)$q : 0]);
    return $st->fetchAll();
}

function an_ponds_list(PDO $pdo, string $from, string $to, string $q = ''): array
{
    $st = $pdo->prepare(
        "SELECT p.id, p.name, p.area_sq_m, p.status,
                COALESCE(s.stocked,0) AS stocked_total, COALESCE(s.active_stocks,0) AS active_stocks,
                COALESCE(hv.kg,0) AS harvest_kg, COALESCE(fd.kg,0) AS feed_kg, COALESCE(mo.c,0) AS mort_period
           FROM fish_ponds p
           LEFT JOIN (SELECT pond_id, SUM(stocking_quantity) AS stocked, SUM(status='active') AS active_stocks
                        FROM fish_stocks GROUP BY pond_id) s ON s.pond_id = p.id
           LEFT JOIN (SELECT fs.pond_id, SUM(h.quantity_kg) AS kg FROM fish_harvest_records h
                        JOIN fish_stocks fs ON fs.id = h.fish_stock_id
                       WHERE h.harvest_date BETWEEN :f1 AND :t1 GROUP BY fs.pond_id) hv ON hv.pond_id = p.id
           LEFT JOIN (SELECT fs.pond_id, SUM(l.quantity_kg) AS kg FROM fish_feeding_logs l
                        JOIN fish_stocks fs ON fs.id = l.fish_stock_id
                       WHERE l.status = 'completed' AND l.completed_at >= :f2 AND l.completed_at < :t2 GROUP BY fs.pond_id) fd ON fd.pond_id = p.id
           LEFT JOIN (SELECT fs.pond_id, SUM(m.`count`) AS c FROM fish_mortality_logs m
                        JOIN fish_stocks fs ON fs.id = m.fish_stock_id
                       WHERE m.log_date BETWEEN :f3 AND :t3 GROUP BY fs.pond_id) mo ON mo.pond_id = p.id
          WHERE (:q0 = '' OR p.name LIKE :ql ESCAPE '\\\\' OR p.id = :qid)
          ORDER BY p.id"
    );
    $st->execute([':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => an_next_day($to), ':f3' => $from, ':t3' => $to,
        ':q0' => $q, ':ql' => an_like($q), ':qid' => ctype_digit($q) ? (int)$q : 0]);
    return $st->fetchAll();
}

function an_plots_list(PDO $pdo, string $from, string $to, string $q = ''): array
{
    $st = $pdo->prepare(
        "SELECT pl.id, pl.name, pl.area_hectares,
                COALESCE(pr.plantings,0) AS plantings, pr.crops,
                COALESCE(hv.kg,0) AS harvest_kg, COALESCE(hv.sent_kg,0) AS sent_kg
           FROM feed_plots pl
           LEFT JOIN (SELECT plot_id, COUNT(*) AS plantings, GROUP_CONCAT(DISTINCT crop_type SEPARATOR ', ') AS crops
                        FROM feed_planting_records GROUP BY plot_id) pr ON pr.plot_id = pl.id
           LEFT JOIN (SELECT r.plot_id, SUM(h.yield_kg) AS kg, SUM(CASE WHEN h.sent_to_store = 1 THEN h.yield_kg ELSE 0 END) AS sent_kg
                        FROM feed_harvest_records h JOIN feed_planting_records r ON r.id = h.planting_record_id
                       WHERE h.harvest_date BETWEEN :f AND :t GROUP BY r.plot_id) hv ON hv.plot_id = pl.id
          WHERE (:q0 = '' OR pl.name LIKE :ql ESCAPE '\\\\' OR pr.crops LIKE :ql2 ESCAPE '\\\\' OR pl.id = :qid)
          ORDER BY pl.id"
    );
    $st->execute([':f' => $from, ':t' => $to, ':q0' => $q, ':ql' => an_like($q), ':ql2' => an_like($q), ':qid' => ctype_digit($q) ? (int)$q : 0]);
    return $st->fetchAll();
}

/** Search by cow tag / flock id / pond id / inventory item (SKU, name, id). */
function an_search(PDO $pdo, string $q, string $from, string $to): array
{
    if ($q === '') {
        return ['cows' => [], 'flocks' => [], 'ponds' => [], 'items' => []];
    }
    $st = $pdo->prepare(
        "SELECT i.id, i.sku, i.name, i.unit, i.product_class,
                COALESCE((SELECT SUM(b.quantity_on_hand) FROM wh_stock_batches b WHERE b.stock_item_id = i.id), 0) AS on_hand
           FROM wh_stock_items i
          WHERE i.sku LIKE :l ESCAPE '\\\\' OR i.name LIKE :l2 ESCAPE '\\\\' OR i.id = :id
          ORDER BY i.sku LIMIT 25"
    );
    $st->execute([':l' => an_like($q), ':l2' => an_like($q), ':id' => ctype_digit($q) ? (int)$q : 0]);
    return [
        'cows' => array_slice(an_cows_list($pdo, $from, $to, $q), 0, 25),
        'flocks' => array_slice(an_flocks_list($pdo, $from, $to, $q), 0, 25),
        'ponds' => array_slice(an_ponds_list($pdo, $from, $to, $q), 0, 25),
        'items' => $st->fetchAll(),
    ];
}

/* ------------------------------------------------------------- UNIT DETAIL */

/** Component tables for one unit's detail page (each is one grouped query). */
function an_unit_breakdown(PDO $pdo, string $unit, string $from, string $to): array
{
    $next = an_next_day($to);
    $tags = match ($unit) {
        'dairy', 'poultry', 'fish', 'feed_production', 'veterinary' => [$unit],
        default => [],
    };
    $out = ['vouchers' => [], 'inputs' => [], 'payroll' => [], 'sales' => [], 'receipts' => []];

    // vouchers by category (owner drawings excluded, same rule as the overview)
    $st = $pdo->prepare(
        "SELECT category, withdrawal_type, COUNT(*) AS n, SUM(amount) AS amt
           FROM fin_expenses
          WHERE status IN ('approved','paid') AND unit_tag = :u AND withdrawal_type <> 'owner_drawing'
            AND expense_date BETWEEN :f AND :t
          GROUP BY category, withdrawal_type ORDER BY amt DESC"
    );
    $st->execute([':u' => $unit, ':f' => $from, ':t' => $to]);
    $out['vouchers'] = $st->fetchAll();

    // stock consumed by item (qty, value, how much has no recorded cost)
    $st = $pdo->prepare(
        "SELECT i.id, i.sku, i.name, i.unit, i.product_class, SUM(iss.quantity) AS qty,
                SUM(ROUND(iss.quantity * COALESCE(b.unit_cost,0), 2)) AS val,
                SUM(CASE WHEN COALESCE(b.unit_cost,0) = 0 THEN iss.quantity ELSE 0 END) AS unpriced_qty
           FROM wh_stock_issues iss
           JOIN wh_stock_items i ON i.id = iss.stock_item_id
           LEFT JOIN wh_stock_batches b ON b.id = iss.stock_batch_id
          WHERE iss.destination_type = :u AND iss.issued_at >= :f AND iss.issued_at < :t
          GROUP BY i.id, i.sku, i.name, i.unit, i.product_class ORDER BY val DESC"
    );
    $st->execute([':u' => $unit, ':f' => $from, ':t' => $next]);
    $out['inputs'] = $st->fetchAll();

    // payroll by department (no individual salaries on this page)
    $st = $pdo->prepare(
        "SELECT COALESCE(dep.name,'—') AS dept, COUNT(DISTINCT ps.employee_id) AS staff, SUM(ps.net_pay + ps.deductions) AS gross
           FROM fin_payslips ps
           JOIN fin_payroll_runs pr ON pr.id = ps.payroll_run_id AND pr.status = 'finalized'
           JOIN employees e ON e.id = ps.employee_id
           LEFT JOIN departments dep ON dep.id = e.department_id
          WHERE dep.business_unit = :u AND pr.period_end BETWEEN :f AND :t
          GROUP BY dep.name"
    );
    $st->execute([':u' => $unit, ':f' => $from, ':t' => $to]);
    $out['payroll'] = $st->fetchAll();

    // sales by item and channel (all channels shown, with whether each counts as income)
    $st = $pdo->prepare(
        "SELECT i.name, i.unit, d.sale_channel AS ch, COUNT(*) AS n, SUM(d.quantity_dispatched) AS qty,
                SUM(d.total_price) AS amt, SUM(d.amount_paid) AS paid
           FROM dist_dispatches d
           JOIN rpt_item_unit_map m ON m.stock_item_id = d.stock_item_id AND m.business_unit = :u
           JOIN wh_stock_items i ON i.id = d.stock_item_id
          WHERE d.status IN ('dispatched','in_transit','delivered','partially_delivered')
            AND COALESCE(d.dispatched_at, d.planned_dispatch_at) >= :f
            AND COALESCE(d.dispatched_at, d.planned_dispatch_at) <  :t
          GROUP BY i.name, i.unit, d.sale_channel ORDER BY amt DESC"
    );
    $st->execute([':u' => $unit, ':f' => $from, ':t' => $next]);
    $out['sales'] = $st->fetchAll();

    return $out;
}

/* ------------------------------------------------------------- COW DETAIL */

function an_cow_detail(PDO $pdo, int $id, string $from, string $to): ?array
{
    $st = $pdo->prepare(
        "SELECT c.*, g.name AS group_name, d.tag_number AS dam_tag, s.tag_number AS sire_tag
           FROM dairy_cows c
           LEFT JOIN dairy_cow_groups g ON g.id = c.cow_group_id
           LEFT JOIN dairy_cows d ON d.id = c.dam_id
           LEFT JOIN dairy_cows s ON s.id = c.sire_id
          WHERE c.id = :id"
    );
    $st->execute([':id' => $id]);
    $cow = $st->fetch();
    if (!$cow) {
        return null;
    }

    $st = $pdo->prepare(
        "SELECT COUNT(*) AS entries,
                COALESCE(SUM(e.yield_liters),0) AS l_life,
                COALESCE(SUM(CASE WHEN s.session_date BETWEEN :f1 AND :t1 THEN e.yield_liters END),0) AS l_period,
                COUNT(CASE WHEN s.session_date BETWEEN :f2 AND :t2 THEN 1 END) AS entries_period,
                COUNT(DISTINCT CASE WHEN s.session_date BETWEEN :f3 AND :t3 THEN s.session_date END) AS days_period,
                COALESCE(SUM(CASE WHEN s.session_date BETWEEN :f4 AND :t4 AND s.session_type='morning' THEN e.yield_liters END),0) AS am_period,
                COALESCE(SUM(CASE WHEN s.session_date BETWEEN :f5 AND :t5 AND s.session_type='evening' THEN e.yield_liters END),0) AS pm_period,
                MIN(s.session_date) AS first_d, MAX(s.session_date) AS last_d,
                COALESCE(SUM(e.flagged_abnormal),0) AS abnormal_life
           FROM dairy_milk_yield_entries e
           JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
          WHERE e.cow_id = :id"
    );
    $st->execute([':id' => $id, ':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to, ':f3' => $from, ':t3' => $to,
        ':f4' => $from, ':t4' => $to, ':f5' => $from, ':t5' => $to]);
    $milk = $st->fetch();

    // total dairy litres in the period (for the cow's share)
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(e.yield_liters),0) FROM dairy_milk_yield_entries e
           JOIN dairy_milking_sessions s ON s.id = e.milking_session_id WHERE s.session_date BETWEEN :f AND :t"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    $unitLitres = (float)$st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT s.session_date AS d, SUM(e.yield_liters) AS l, SUM(e.flagged_abnormal) AS flagged
           FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
          WHERE e.cow_id = :id AND s.session_date BETWEEN :f AND :t GROUP BY s.session_date ORDER BY s.session_date"
    );
    $st->execute([':id' => $id, ':f' => $from, ':t' => $to]);
    $daily = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT * FROM (
            SELECT record_date AS d, condition_name AS cond, treatment, 'dairy_health_records' AS src FROM dairy_health_records WHERE cow_id = :id1
            UNION
            SELECT record_date, condition_name, treatment, 'vet_health_records' FROM vet_health_records WHERE subject_type = 'dairy_cow' AND subject_id = :id2
         ) x ORDER BY d DESC LIMIT 200"
    );
    $st->execute([':id1' => $id, ':id2' => $id]);
    $health = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT * FROM (
            SELECT vaccine_name AS v, scheduled_date AS sd, administered_date AS ad, status, 'dairy_vaccination_records' AS src FROM dairy_vaccination_records WHERE cow_id = :id1
            UNION
            SELECT vaccine_name, scheduled_date, administered_date, status, 'vet_vaccination_records' FROM vet_vaccination_records WHERE subject_type = 'dairy_cow' AND subject_id = :id2
         ) x ORDER BY sd DESC LIMIT 200"
    );
    $st->execute([':id1' => $id, ':id2' => $id]);
    $vacc = $st->fetchAll();

    $st = $pdo->prepare("SELECT * FROM dairy_breeding_records WHERE cow_id = :id ORDER BY breeding_date DESC");
    $st->execute([':id' => $id]);
    $breeding = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT cr.*, calf.tag_number AS calf_tag FROM dairy_calving_records cr
           LEFT JOIN dairy_cows calf ON calf.id = cr.calf_id WHERE cr.dam_id = :id ORDER BY cr.calving_date DESC"
    );
    $st->execute([':id' => $id]);
    $calving = $st->fetchAll();

    $st = $pdo->prepare("SELECT id, tag_number, name, sex, lifecycle_status, date_of_birth FROM dairy_cows WHERE dam_id = :id ORDER BY date_of_birth");
    $st->execute([':id' => $id]);
    $offspring = $st->fetchAll();

    $st = $pdo->prepare("SELECT * FROM dairy_cow_exit_records WHERE cow_id = :id ORDER BY id DESC");
    $st->execute([':id' => $id]);
    $exits = $st->fetchAll();

    // receipts recorded in the ledger for this cow's sale (the posting code stores cow id as source_id)
    $st = $pdo->prepare(
        "SELECT transaction_date, amount, payment_method, description FROM fin_transactions
          WHERE direction = 'inflow' AND source_table = 'dairy_cow_exit_records' AND source_id = :id ORDER BY transaction_date"
    );
    $st->execute([':id' => $id]);
    $saleReceipts = $st->fetchAll();

    $sales = an_unit_counted_sales($pdo, 'dairy', $from, $to);
    $share = $unitLitres > 0 ? (float)$milk['l_period'] / $unitLitres : null;

    $missing = [
        'acquisition_price' => $cow['source'] === 'purchased' || $cow['source'] === null,
        'calf_milk' => true,
        'feed_cost_per_cow' => true,
        'vet_cost_per_treatment' => true,
        'milk_sales_per_cow' => true,
    ];

    return [
        'cow' => $cow, 'milk' => $milk, 'unit_litres' => $unitLitres, 'daily' => $daily,
        'health' => $health, 'vaccinations' => $vacc, 'breeding' => $breeding, 'calving' => $calving,
        'offspring' => $offspring, 'exits' => $exits, 'sale_receipts' => $saleReceipts,
        'share' => $share,
        'allocated_value_cents' => $share !== null ? (int)round($sales['cents'] * $share) : null,
        'unit_sales_cents' => $sales['cents'],
        'missing' => $missing,
    ];
}

/* ----------------------------------------------------------- FLOCK DETAIL */

function an_flock_detail(PDO $pdo, int $id, string $from, string $to): ?array
{
    $st = $pdo->prepare(
        "SELECT f.*, h.name AS house_name, h.capacity, h.quarantined,
                (f.initial_population - f.cumulative_mortality - f.cumulative_culled_sold + f.cumulative_transfers_in) AS live_pop
           FROM poultry_flocks f JOIN poultry_houses h ON h.id = f.poultry_house_id WHERE f.id = :id"
    );
    $st->execute([':id' => $id]);
    $flock = $st->fetch();
    if (!$flock) {
        return null;
    }

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(whole_count),0) AS whole_life, COALESCE(SUM(cracked_count),0) AS cracked_life, COALESCE(SUM(broken_count),0) AS broken_life,
                COALESCE(SUM(CASE WHEN collection_date BETWEEN :f1 AND :t1 THEN whole_count END),0) AS whole_p,
                COALESCE(SUM(CASE WHEN collection_date BETWEEN :f2 AND :t2 THEN cracked_count END),0) AS cracked_p,
                COALESCE(SUM(CASE WHEN collection_date BETWEEN :f3 AND :t3 THEN broken_count END),0) AS broken_p,
                COUNT(DISTINCT CASE WHEN collection_date BETWEEN :f4 AND :t4 THEN collection_date END) AS days_p,
                MIN(collection_date) AS first_d, MAX(collection_date) AS last_d
           FROM poultry_egg_collections WHERE flock_id = :id"
    );
    $st->execute([':id' => $id, ':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to, ':f3' => $from, ':t3' => $to, ':f4' => $from, ':t4' => $to]);
    $eggs = $st->fetch();

    $st = $pdo->prepare(
        "SELECT collection_date AS d, SUM(whole_count) AS whole, SUM(cracked_count) AS cracked, SUM(broken_count) AS broken
           FROM poultry_egg_collections WHERE flock_id = :id AND collection_date BETWEEN :f AND :t
          GROUP BY collection_date ORDER BY collection_date"
    );
    $st->execute([':id' => $id, ':f' => $from, ':t' => $to]);
    $daily = $st->fetchAll();

    $st = $pdo->prepare("SELECT log_date, `count`, cause_code FROM poultry_mortality_logs WHERE flock_id = :id ORDER BY log_date DESC");
    $st->execute([':id' => $id]);
    $mortality = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT record_date, condition_name, treatment FROM vet_health_records WHERE subject_type = 'poultry_flock' AND subject_id = :id ORDER BY record_date DESC LIMIT 100"
    );
    $st->execute([':id' => $id]);
    $health = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT vaccine_name, scheduled_date, administered_date, status FROM vet_vaccination_records
          WHERE subject_type = 'poultry_flock' AND subject_id = :id ORDER BY scheduled_date DESC LIMIT 100"
    );
    $st->execute([':id' => $id]);
    $vacc = $st->fetchAll();

    // all poultry eggs in the period, for this flock's share of unit output
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(whole_count + cracked_count + broken_count),0) FROM poultry_egg_collections WHERE collection_date BETWEEN :f AND :t"
    );
    $st->execute([':f' => $from, ':t' => $to]);
    $unitEggs = (int)$st->fetchColumn();
    $flockEggs = (int)$eggs['whole_p'] + (int)$eggs['cracked_p'] + (int)$eggs['broken_p'];

    $sales = an_unit_counted_sales($pdo, 'poultry', $from, $to);
    $share = $unitEggs > 0 ? $flockEggs / $unitEggs : null;

    $isLayer = ((int)$eggs['whole_life'] + (int)$eggs['cracked_life'] + (int)$eggs['broken_life']) > 0;

    return [
        'flock' => $flock, 'eggs' => $eggs, 'daily' => $daily, 'mortality' => $mortality, 'health' => $health, 'vaccinations' => $vacc,
        'flock_eggs_period' => $flockEggs, 'unit_eggs_period' => $unitEggs, 'share' => $share,
        'allocated_value_cents' => ($share !== null && $isLayer) ? (int)round($sales['cents'] * $share) : null,
        'unit_sales_cents' => $sales['cents'],
        'is_layer' => $isLayer,
        'eggs_per_bird_day' => ($isLayer && (int)$flock['live_pop'] > 0 && (int)$eggs['days_p'] > 0)
            ? $flockEggs / ((int)$flock['live_pop'] * (int)$eggs['days_p']) : null,
        'missing' => ['feed_per_flock' => true, 'medicine_cost' => true, 'sales_per_flock' => true, 'broiler_sales' => !$isLayer],
    ];
}

/* ------------------------------------------------------------ POND DETAIL */

function an_pond_detail(PDO $pdo, int $id, string $from, string $to): ?array
{
    $st = $pdo->prepare("SELECT * FROM fish_ponds WHERE id = :id");
    $st->execute([':id' => $id]);
    $pond = $st->fetch();
    if (!$pond) {
        return null;
    }
    $next = an_next_day($to);

    // one row per stocking cycle with its harvest / feed / mortality totals (period + lifetime)
    $st = $pdo->prepare(
        "SELECT s.*,
                COALESCE(h.kg_life,0) AS harvest_life, COALESCE(h.kg_p,0) AS harvest_p, COALESCE(h.n,0) AS harvests_n,
                COALESCE(f.kg_life,0) AS feed_life, COALESCE(f.kg_p,0) AS feed_p,
                COALESCE(m.c_life,0) AS mort_life_logs, COALESCE(m.c_p,0) AS mort_p
           FROM fish_stocks s
           LEFT JOIN (SELECT fish_stock_id, SUM(quantity_kg) AS kg_life,
                             SUM(CASE WHEN harvest_date BETWEEN :f1 AND :t1 THEN quantity_kg ELSE 0 END) AS kg_p, COUNT(*) AS n
                        FROM fish_harvest_records GROUP BY fish_stock_id) h ON h.fish_stock_id = s.id
           LEFT JOIN (SELECT fish_stock_id, SUM(quantity_kg) AS kg_life,
                             SUM(CASE WHEN completed_at >= :f2 AND completed_at < :t2 THEN quantity_kg ELSE 0 END) AS kg_p
                        FROM fish_feeding_logs WHERE status = 'completed' GROUP BY fish_stock_id) f ON f.fish_stock_id = s.id
           LEFT JOIN (SELECT fish_stock_id, SUM(`count`) AS c_life,
                             SUM(CASE WHEN log_date BETWEEN :f3 AND :t3 THEN `count` ELSE 0 END) AS c_p
                        FROM fish_mortality_logs GROUP BY fish_stock_id) m ON m.fish_stock_id = s.id
          WHERE s.pond_id = :id ORDER BY s.stocking_date DESC"
    );
    $st->execute([':id' => $id, ':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $next, ':f3' => $from, ':t3' => $to]);
    $stocks = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT h.harvest_date, h.quantity_kg, h.type, h.grade, s.species FROM fish_harvest_records h
           JOIN fish_stocks s ON s.id = h.fish_stock_id WHERE s.pond_id = :id ORDER BY h.harvest_date DESC LIMIT 200"
    );
    $st->execute([':id' => $id]);
    $harvests = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT DATE(l.completed_at) AS d, l.feed_type, SUM(l.quantity_kg) AS kg FROM fish_feeding_logs l
           JOIN fish_stocks s ON s.id = l.fish_stock_id
          WHERE s.pond_id = :id AND l.status = 'completed' AND l.completed_at >= :f AND l.completed_at < :t
          GROUP BY d, l.feed_type ORDER BY d DESC LIMIT 200"
    );
    $st->execute([':id' => $id, ':f' => $from, ':t' => $next]);
    $feeding = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT m.log_date, m.`count`, m.suspected_cause FROM fish_mortality_logs m JOIN fish_stocks s ON s.id = m.fish_stock_id
          WHERE s.pond_id = :id ORDER BY m.log_date DESC LIMIT 200"
    );
    $st->execute([':id' => $id]);
    $mortality = $st->fetchAll();

    $st = $pdo->prepare("SELECT reading_at, ph, dissolved_oxygen_mg_l, temperature_celsius, within_safe_range FROM fish_water_quality_readings WHERE pond_id = :id ORDER BY reading_at DESC LIMIT 10");
    $st->execute([':id' => $id]);
    $water = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT v.record_date, v.condition_name, v.treatment, s.species FROM vet_health_records v
           JOIN fish_stocks s ON s.id = v.subject_id AND v.subject_type = 'fish_stock'
          WHERE s.pond_id = :id ORDER BY v.record_date DESC LIMIT 100"
    );
    $st->execute([':id' => $id]);
    $health = $st->fetchAll();

    // pond's share of all fish harvested in the period
    $st = $pdo->prepare("SELECT COALESCE(SUM(quantity_kg),0) FROM fish_harvest_records WHERE harvest_date BETWEEN :f AND :t");
    $st->execute([':f' => $from, ':t' => $to]);
    $unitKg = (float)$st->fetchColumn();
    $pondKg = array_sum(array_map(fn($s) => (float)$s['harvest_p'], $stocks));
    $sales = an_unit_counted_sales($pdo, 'fish', $from, $to);
    $share = $unitKg > 0 ? $pondKg / $unitKg : null;

    // a live-count estimate is only reliable before any harvest (harvests are recorded by weight only)
    foreach ($stocks as &$s) {
        $s['live_count_estimate'] = ((float)$s['harvest_life'] == 0.0 && $s['status'] === 'active')
            ? (int)$s['stocking_quantity'] - (int)$s['cumulative_mortality'] : null;
    }
    unset($s);

    return [
        'pond' => $pond, 'stocks' => $stocks, 'harvests' => $harvests, 'feeding' => $feeding, 'mortality' => $mortality,
        'water' => $water, 'health' => $health, 'share' => $share, 'pond_kg_period' => $pondKg, 'unit_kg_period' => $unitKg,
        'allocated_value_cents' => $share !== null ? (int)round($sales['cents'] * $share) : null,
        'unit_sales_cents' => $sales['cents'],
        'missing' => ['fish_sales_per_pond' => true, 'feed_cost_per_pond' => true, 'medicine_cost' => true, 'maintenance_cost' => true],
    ];
}
