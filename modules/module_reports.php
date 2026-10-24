<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['super_admin', 'general_manager', 'dairy_manager', 'finance_officer', 'hr_officer', 'warehouse_keeper', 'poultry_manager', 'fish_manager', 'feed_production_manager', 'veterinary_officer']);

$mod  = $_GET['module'] ?? 'dairy';

$curEthYearNum  = eth_current_year();
$curEthMonthNum = eth_current_month();

$selEthYear  = isset($_GET['eth_year']) ? (int)$_GET['eth_year'] : $curEthYearNum;
$selEthMonth = isset($_GET['eth_month']) ? (int)$_GET['eth_month'] : 0;

if ($selEthMonth > 0) {
    $mRange = eth_month_range($selEthYear, $selEthMonth);
    $from = $mRange['from'];
    $to   = $mRange['to'];
} elseif (isset($_GET['eth_year']) && !isset($_GET['from'])) {
    $yRange = eth_year_range($selEthYear);
    $from = $yRange['from'];
    $to   = $yRange['to'];
} else {
    $curEthMonth = eth_month_range();
    $from = $_GET['from'] ?? $curEthMonth['from'];
    $to   = $_GET['to']   ?? date('Y-m-d');
}

$ethMonths = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];


$allPossibleModules = [
    'dairy'          => '🐄 ' . (current_locale() === 'am' ? 'የወተት ምርት' : 'Dairy Production'),
    'distribution'   => '🚛 ' . (current_locale() === 'am' ? 'ስርጭት እና ሽያጭ' : 'Distribution & Sales'),
    'warehouse'      => '🏪 ' . (current_locale() === 'am' ? 'የመጋዘን ክምችት' : 'Warehouse Stock'),
    'payroll'        => '💰 ' . (current_locale() === 'am' ? 'የደመወዝ ክፍያ' : 'Payroll & Salaries'),
    'employees'      => '👷 ' . (current_locale() === 'am' ? 'የሰራተኞች መገኘት' : 'Staff & Attendance'),
    'poultry'        => '🐓 ' . (current_locale() === 'am' ? 'የዶሮ እርባታ' : 'Poultry & Eggs'),
    'fish'           => '🐟 ' . (current_locale() === 'am' ? 'የዓሳ እርባታ' : 'Fish Harvest'),
    'feed'           => '🌾 ' . (current_locale() === 'am' ? 'የመኖ ምርት' : 'Feed & Crops'),
    'vet'            => '🩺 ' . (current_locale() === 'am' ? 'የእንስሳት ጤና' : 'Veterinary Health'),
];

$role = current_user()['role'] ?? '';
$restrictMap = [
    'dairy_manager'           => ['dairy'],
    'poultry_manager'         => ['poultry'],
    'fish_manager'            => ['fish'],
    'feed_production_manager' => ['feed'],
    'veterinary_officer'      => ['vet'],
    'warehouse_keeper'        => ['warehouse', 'distribution'],
    'finance_officer'         => ['payroll', 'distribution'],
    'hr_officer'              => ['employees'],
];

if (isset($restrictMap[$role])) {
    $allowedModules = [];
    foreach ($restrictMap[$role] as $k) {
        if (isset($allPossibleModules[$k])) {
            $allowedModules[$k] = $allPossibleModules[$k];
        }
    }
} else {
    $allowedModules = $allPossibleModules;
}

if (!isset($allowedModules[$mod])) {
    $mod = array_key_first($allowedModules) ?? 'dairy';
}

// Export CSV
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $mod . '_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');

    switch ($mod) {
        case 'dairy':
            fputcsv($out, ['Session Date', 'Session Type', 'Cow Tag', 'Cow Name', 'Yield (L)']);
            $rows = db()->prepare("SELECT s.session_date, s.session_type, c.tag_number, c.name, e.yield_liters 
                                   FROM dairy_milk_yield_entries e 
                                   JOIN dairy_milking_sessions s ON s.id = e.milking_session_id 
                                   JOIN dairy_cows c ON c.id = e.cow_id 
                                   WHERE s.session_date BETWEEN ? AND ? 
                                   ORDER BY s.session_date DESC, c.tag_number");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'distribution':
            fputcsv($out, ['Date', 'Vehicle Plate', 'Driver Name', 'Item Sold', 'Qty', 'Unit Price (ETB)', 'Total Value (ETB)', 'Amount Paid (ETB)', 'Unpaid Balance (ETB)', 'Status']);
            $rows = db()->prepare("SELECT COALESCE(DATE(d.dispatched_at), DATE(d.created_at)) AS ddate, v.plate_number, 
                                          COALESCE(d.driver_name, v.driver_name, '—'), i.name, d.quantity_dispatched, 
                                          d.unit_price, d.total_price, d.amount_paid, 
                                          (d.total_price - d.amount_paid) AS balance, d.payment_status
                                   FROM dist_dispatches d
                                   JOIN dist_vehicles v ON v.id = d.vehicle_id
                                   JOIN wh_stock_items i ON i.id = d.stock_item_id
                                   WHERE (DATE(d.created_at) BETWEEN ? AND ? OR DATE(d.dispatched_at) BETWEEN ? AND ?)
                                   ORDER BY d.id DESC");
            $rows->execute([$from, $to, $from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'warehouse':
            fputcsv($out, ['Item Name', 'SKU', 'Class', 'Unit', 'Current Stock On Hand']);
            $rows = db()->prepare("SELECT i.name, i.sku, i.product_class, i.unit, COALESCE(SUM(b.quantity_on_hand), 0) AS cur_stock
                                   FROM wh_stock_items i
                                   LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
                                   GROUP BY i.id
                                   ORDER BY i.name ASC");
            $rows->execute();
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'payroll':
            fputcsv($out, ['Run Period', 'Employee', 'Emp No', 'Dept', 'Base Salary', 'Days Present', 'Net Pay', 'Total Paid', 'Remaining', 'Status']);
            $rows = db()->prepare("SELECT CONCAT(r.period_start,' to ',r.period_end), e.full_name, e.employee_number, d.name AS dept_name, 
                                          p.base_salary, p.days_present, p.net_pay, p.total_paid, (p.net_pay - p.total_paid), r.status
                                   FROM fin_payslips p 
                                   JOIN employees e ON e.id = p.employee_id 
                                   LEFT JOIN departments d ON d.id = e.department_id
                                   JOIN fin_payroll_runs r ON r.id = p.payroll_run_id 
                                   WHERE r.period_start >= ? AND r.period_end <= ? 
                                   ORDER BY r.id DESC, e.full_name");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'employees':
            fputcsv($out, ['Emp No', 'Full Name', 'Department', 'Job Title', 'Present Days', 'Absent Days']);
            $rows = db()->prepare("SELECT e.employee_number, e.full_name, d.name AS department, e.job_title, 
                                          COUNT(CASE WHEN a.status IN ('present','half_day') THEN 1 END) AS present_days, 
                                          COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS absent_days 
                                   FROM employees e 
                                   LEFT JOIN departments d ON d.id = e.department_id 
                                   LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ? 
                                   WHERE e.status = 'active' 
                                   GROUP BY e.id 
                                   ORDER BY e.full_name");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'poultry':
            fputcsv($out, ['Date', 'House', 'Breed', 'Round', 'Whole', 'Cracked', 'Broken']);
            $rows = db()->prepare("SELECT ec.collection_date, h.name AS house, f.breed, ec.round_no, ec.whole_count, ec.cracked_count, ec.broken_count 
                                   FROM poultry_egg_collections ec 
                                   JOIN poultry_flocks f ON f.id = ec.flock_id 
                                   JOIN poultry_houses h ON h.id = f.poultry_house_id 
                                   WHERE ec.collection_date BETWEEN ? AND ? 
                                   ORDER BY ec.collection_date DESC");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'fish':
            fputcsv($out, ['Date', 'Pond', 'Species', 'Grade', 'Qty (kg)']);
            $rows = db()->prepare("SELECT hr.harvest_date, p.name AS pond, s.species, hr.grade, hr.quantity_kg 
                                   FROM fish_harvest_records hr 
                                   JOIN fish_stocks s ON s.id = hr.fish_stock_id 
                                   JOIN fish_ponds p ON p.id = s.pond_id 
                                   WHERE hr.harvest_date BETWEEN ? AND ? 
                                   ORDER BY hr.harvest_date DESC");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'feed':
            fputcsv($out, ['Planting Date', 'Harvest Date', 'Plot', 'Crop', 'Harvest Yield (kg)', 'Sent to Store']);
            $rows = db()->prepare("SELECT p.planting_date, h.harvest_date, pl.name AS plot, p.crop_type, h.yield_kg, 
                                          CASE WHEN h.sent_to_store = 1 THEN 'Yes' ELSE 'No' END AS sent_status
                                   FROM feed_harvest_records h
                                   JOIN feed_planting_records p ON p.id = h.planting_record_id
                                   JOIN feed_plots pl ON pl.id = p.plot_id
                                   WHERE h.harvest_date BETWEEN ? AND ?
                                   ORDER BY h.harvest_date DESC");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;

        case 'vet':
            fputcsv($out, ['Record Date', 'Cow Tag', 'Condition / Disease', 'Treatment Given', 'Vet Name']);
            $rows = db()->prepare("SELECT r.record_date, c.tag_number, r.condition_name, r.treatment, u.name AS vet_name
                                   FROM vet_health_records r
                                   LEFT JOIN dairy_cows c ON c.id = r.subject_id AND r.subject_type = 'cow'
                                   LEFT JOIN users u ON u.id = r.vet_id
                                   WHERE r.record_date BETWEEN ? AND ?
                                   ORDER BY r.record_date DESC");
            $rows->execute([$from, $to]);
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $r) fputcsv($out, $r);
            break;
    }
    fclose($out);
    exit;
}
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">📊 <?= current_locale() === 'am' ? 'የስራ ክፍሎች እና ኦፕሬሽንስ ሪፖርቶች' : 'Operations & Departmental Reports' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የእያንዳንዱ የስራ ክፍል የምርት፣ የሽያጭ፣ የክምችት፣ የደመወዝ እና የጤና አጠቃላይ ሪፖርቶች እና ዳታ ኤክስፖርት' : 'Cross-unit operational analytics, production totals, sales revenue, and audit trails.' ?></p>
    </div>
</div>

<!-- Module Tabs -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
    <?php foreach ($allowedModules as $key => $label): ?>
        <a href="?module=<?= $key ?>&from=<?= h($from) ?>&to=<?= h($to) ?>"
           class="btn <?= $mod === $key ? '' : 'secondary' ?>" style="padding:7px 14px">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Date Filter with Ethiopian Calendar -->
<div class="card" style="padding:14px 18px;margin-bottom:18px">
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="module" value="<?= h($mod) ?>">
        
        <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">📅 ዓመተ ምህረት</label>
            <select name="eth_year" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border);font-size:13px;font-weight:600">
                <?php for ($y = $curEthYearNum + 1; $y >= $curEthYearNum - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $selEthYear === $y ? 'selected' : '' ?>><?= $y ?> ዓ.ም</option>
                <?php endfor; ?>
            </select>
        </div>

        <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">🗓️ ወር</label>
            <select name="eth_month" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border);font-size:13px;font-weight:600">
                <option value="0" <?= $selEthMonth === 0 ? 'selected' : '' ?>>— ሙሉ ዓመት —</option>
                <?php foreach ($ethMonths as $mNum => $mName): ?>
                    <option value="<?= $mNum ?>" <?= $selEthMonth === $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="btn" type="submit" style="padding:7px 16px;background:#1e5631;border-color:#1e5631">🔍 ሪፖርት አውጣ</button>
        <a href="?module=<?= h($mod) ?>&from=<?= $from ?>&to=<?= $to ?>&export=csv" class="btn secondary" style="padding:7px 14px">⬇ <?= h(t('common.export_csv', 'Export CSV')) ?></a>
        <button class="btn secondary no-print" onclick="window.print();return false" style="padding:7px 14px">🖨 <?= h(t('common.print', 'Print')) ?></button>

        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
            <a href="?module=<?= h($mod) ?>&eth_year=<?= $curEthYearNum ?>&eth_month=<?= $curEthMonthNum ?>" class="btn secondary" style="padding:6px 12px;font-size:12px">📅 የዚህ ወር (<?= $ethMonths[$curEthMonthNum] ?>)</a>
            <a href="?module=<?= h($mod) ?>&eth_year=<?= $curEthYearNum ?>&eth_month=0" class="btn secondary" style="padding:6px 12px;font-size:12px">🗓️ የዚህ ሙሉ ዓመት</a>
        </div>
    </form>
    <div style="margin-top:10px;font-size:12.5px;color:var(--text-muted);display:flex;gap:8px;align-items:center">
        <span>የሪፖርት ክፍለ-ጊዜ፦ <strong style="color:var(--text)"><?= eth_date($from) ?> — <?= eth_date($to) ?></strong></span>
    </div>
</div>

<?php
// ---- 1. DAIRY REPORT ----
if ($mod === 'dairy'):
    $kpis_raw = db()->prepare("SELECT s.session_date, SUM(e.yield_liters) AS total FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id = e.milking_session_id WHERE s.session_date BETWEEN ? AND ? GROUP BY s.session_date ORDER BY s.session_date DESC");
    $kpis_raw->execute([$from, $to]);
    $dailyYields = $kpis_raw->fetchAll();
    $totalLiters = array_sum(array_column($dailyYields, 'total'));
    $days        = count($dailyYields);
    $avgPerDay   = $days > 0 ? round($totalLiters / $days, 1) : 0;

    $topCows = db()->prepare("SELECT c.id AS cow_id, c.tag_number, c.name, c.breed, SUM(e.yield_liters) AS total_liters, COUNT(e.id) AS sessions FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id = e.milking_session_id JOIN dairy_cows c ON c.id = e.cow_id WHERE s.session_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total_liters DESC LIMIT 15");
    $topCows->execute([$from, $to]);
    $topCows = $topCows->fetchAll();
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi" style="border-left:4px solid var(--primary)"><div class="label">🥛 <?= current_locale()==='am'?'ጠቅላላ የተመረተ ወተት':'Total Milk Produced' ?></div><div class="value" style="color:var(--primary)"><?= number_format($totalLiters, 1) ?> L</div></div>
    <div class="kpi" style="border-left:4px solid #1565c0"><div class="label">📅 <?= current_locale()==='am'?'የታለበባቸው ቀናት':'Milking Days' ?></div><div class="value" style="color:#1565c0"><?= $days ?> <?= current_locale()==='am'?'ቀን':'days' ?></div></div>
    <div class="kpi" style="border-left:4px solid #2e7d32"><div class="label">📊 <?= current_locale()==='am'?'አማካይ የወተት ምርት በቀን':'Average Per Day' ?></div><div class="value" style="color:#2e7d32"><?= number_format($avgPerDay, 1) ?> L</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <h3>🐄 <?= current_locale()==='am'?'ከፍተኛ ወተት ያመነጩ ላሞች':'Top Milk Producing Cows' ?> — <?= eth_date($from) ?> <?= current_locale()==='am'?'እስከ':'to' ?> <?= eth_date($to) ?></h3>
    <table>
        <thead><tr><th>Rank</th><th>Tag</th><th>Name</th><th>Breed</th><th>Sessions</th><th>Total (L)</th><th>Avg/Session</th></tr></thead>
        <tbody>
        <?php foreach ($topCows as $i => $c): ?>
            <tr>
                <td><strong>#<?= $i + 1 ?></strong></td>
                <td><a href="<?= BASE_URL ?>/modules/dairy/cow_view.php?id=<?= $c['cow_id'] ?>"><strong><?= h($c['tag_number']) ?></strong></a></td>
                <td><?= h($c['name'] ?: '—') ?></td>
                <td><?= h($c['breed'] ?: '—') ?></td>
                <td><?= (int)$c['sessions'] ?></td>
                <td><strong><?= number_format($c['total_liters'], 1) ?> L</strong></td>
                <td><?= number_format($c['total_liters'] / max(1, $c['sessions']), 1) ?> L</td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($topCows)): ?><tr><td colspan="7" class="text-muted">No milking records found in this date range.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የዕለት የወተት ምርት መዝገብ' : 'Daily Milk Production Log' ?></h3>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'ቀን (በኢትዮጵያ)' : 'Milking Date' ?></th><th><?= current_locale() === 'am' ? 'የዕለቱ ጠቅላላ ምርት' : 'Total Daily Output' ?></th></tr></thead>
        <tbody>
        <?php foreach ($dailyYields as $d): ?>
            <tr><td><?= h(eth_date($d['session_date'])) ?></td><td><strong><?= number_format($d['total'], 1) ?> L</strong></td></tr>
        <?php endforeach; ?>
        <?php if (empty($dailyYields)): ?><tr><td colspan="2" class="text-muted"><?= h(t('common.no_records', 'No records.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 2. DISTRIBUTION & SALES REPORT ----
elseif ($mod === 'distribution'):
    $stmt = db()->prepare(
        "SELECT d.*, v.plate_number, COALESCE(d.driver_name, v.driver_name, '—') AS active_driver, i.name AS item_name, i.unit,
                COALESCE(DATE(d.dispatched_at), DATE(d.created_at)) AS ddate
         FROM dist_dispatches d
         JOIN dist_vehicles v ON v.id = d.vehicle_id
         JOIN wh_stock_items i ON i.id = d.stock_item_id
         WHERE (DATE(d.created_at) BETWEEN ? AND ? OR DATE(d.dispatched_at) BETWEEN ? AND ?)
         ORDER BY d.id DESC"
    );
    $stmt->execute([$from, $to, $from, $to]);
    $dispRows = $stmt->fetchAll();

    $totalDispatches = count($dispRows);
    $totalSalesVal   = array_sum(array_column($dispRows, 'total_price'));
    $totalCollected  = array_sum(array_column($dispRows, 'amount_paid'));
    $totalUnpaid     = $totalSalesVal - $totalCollected;
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi" style="border-left:4px solid var(--primary)"><div class="label">🚛 <?= current_locale()==='am'?'ጠቅላላ የተላኩ ስርጭቶች':'Total Dispatches' ?></div><div class="value"><?= $totalDispatches ?> <?= current_locale()==='am'?'ጭነቶች':'shipments' ?></div></div>
    <div class="kpi" style="border-left:4px solid #1565c0"><div class="label">💰 <?= current_locale()==='am'?'ጠቅላላ የሽያጭ ዋጋ':'Total Sales Value' ?></div><div class="value"><?= number_format($totalSalesVal, 2) ?> ETB</div></div>
    <div class="kpi" style="border-left:4px solid #2e7d32"><div class="label">💵 <?= current_locale()==='am'?'የተሰበሰበ ገንዘብ':'Money Collected' ?></div><div class="value" style="color:#2e7d32"><?= number_format($totalCollected, 2) ?> ETB</div></div>
    <div class="kpi" style="border-left:4px solid <?= $totalUnpaid > 0 ? '#b3261e' : '#2e7d32' ?>"><div class="label">⏳ <?= current_locale()==='am'?'ያልተሰበሰበ ቀሪ ዱቤ':'Unpaid Balance' ?></div><div class="value" style="color:<?= $totalUnpaid > 0 ? '#b3261e' : '#2e7d32' ?>"><?= number_format($totalUnpaid, 2) ?> ETB</div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">🚛 <?= current_locale() === 'am' ? 'የምርት ሽያጭ እና የክፍያ አሰባሰብ ዝርዝር መዝገብ' : 'Product Sales & Installment Payment Summary' ?></h3>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= current_locale()==='am'?'ቀን (ኢትዮጵያ)':'Date' ?></th>
                <th><?= current_locale()==='am'?'ተሽከርካሪና ሹፌር':'Vehicle & Driver' ?></th>
                <th><?= current_locale()==='am'?'የምርት አይነት':'Product' ?></th>
                <th><?= current_locale()==='am'?'መጠን':'Qty' ?></th>
                <th><?= current_locale()==='am'?'የአንዱ ዋጋ':'Unit Price' ?></th>
                <th><?= current_locale()==='am'?'ጠቅላላ ዋጋ':'Total Value' ?></th>
                <th><?= current_locale()==='am'?'የተከፈለ':'Amount Paid' ?></th>
                <th><?= current_locale()==='am'?'ቀሪ ዱቤ':'Unpaid Balance' ?></th>
                <th><?= current_locale()==='am'?'ሁኔታ':'Status' ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dispRows as $r): 
            $unpaid = (float)$r['total_price'] - (float)$r['amount_paid'];
            $stBadge = match($r['payment_status']) {
                'paid' => '<span class="badge milking">Fully Paid</span>',
                'partial' => '<span class="badge dry">Partial ('.number_format($r['amount_paid'], 2).' ETB)</span>',
                default => '<span class="badge sold">Unpaid</span>'
            };
        ?>
            <tr>
                <td style="font-size:12px"><?= h(eth_date($r['ddate'])) ?></td>
                <td><strong><?= h($r['plate_number']) ?></strong><br><small class="text-muted"><?= h($r['active_driver']) ?></small></td>
                <td><?= h($r['item_name']) ?></td>
                <td><strong><?= number_format($r['quantity_dispatched'], 2) ?> <?= h($r['unit']) ?></strong></td>
                <td><?= number_format($r['unit_price'], 2) ?> ETB</td>
                <td><strong><?= number_format($r['total_price'], 2) ?> ETB</strong></td>
                <td style="color:#2e7d32"><strong><?= number_format($r['amount_paid'], 2) ?> ETB</strong></td>
                <td style="color:<?= $unpaid > 0 ? '#b3261e' : '#2e7d32' ?>"><strong><?= number_format($unpaid, 2) ?> ETB</strong></td>
                <td><?= $stBadge ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dispRows)): ?><tr><td colspan="9" class="text-muted">No dispatches or sales in this date range.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 3. WAREHOUSE REPORT ----
elseif ($mod === 'warehouse'):
    $stmt = db()->prepare("SELECT i.*, COALESCE(SUM(b.quantity_on_hand), 0) AS cur_stock,
                                  COALESCE(MAX(r.minimum_threshold), 10.0) AS min_lim
                           FROM wh_stock_items i
                           LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.warehouse_id = 1 AND b.quantity_on_hand > 0
                           LEFT JOIN wh_minimum_stock_rules r ON r.stock_item_id = i.id AND r.warehouse_id = 1
                           GROUP BY i.id
                           ORDER BY i.product_class ASC, i.name ASC");
    $stmt->execute();
    $stockRows = $stmt->fetchAll();

    $totalItems = count($stockRows);
    $lowStockCount = count(array_filter($stockRows, fn($r) => (float)$r['cur_stock'] < (float)$r['min_lim']));
    $totalStockUnits = array_sum(array_column($stockRows, 'cur_stock'));
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi" style="border-left:4px solid var(--primary)"><div class="label">📦 <?= current_locale()==='am'?'ጠቅላላ የዕቃዎች ካታሎግ':'Total Stock Catalog' ?></div><div class="value"><?= $totalItems ?> <?= current_locale()==='am'?'ዕቃዎች':'items' ?></div></div>
    <div class="kpi" style="border-left:4px solid #1565c0"><div class="label">📊 <?= current_locale()==='am'?'በመጋዘን ያለ ጠቅላላ ክምችት':'Total Units On Hand' ?></div><div class="value"><?= number_format($totalStockUnits, 1) ?></div></div>
    <div class="kpi" style="border-left:4px solid <?= $lowStockCount > 0 ? '#b3261e' : '#2e7d32' ?>"><div class="label">⚠️ <?= current_locale()==='am'?'የእጥረት ማስጠንቀቂያዎች':'Low Stock Alerts' ?></div><div class="value" style="color:<?= $lowStockCount > 0 ? '#b3261e' : '#2e7d32' ?>"><?= $lowStockCount ?></div></div>
    <div class="kpi" style="border-left:4px solid #2e7d32"><div class="label">🏪 <?= current_locale()==='am'?'ዋና መጋዘን':'Central Warehouse' ?></div><div class="value"><?= current_locale()==='am'?'አለልቱ ማዕከል':'Aleltu Hub' ?></div></div>
</div>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">🏪 <?= current_locale() === 'am' ? 'የመጋዘን ዕቃዎች ወቅታዊ ክምችት ዝርዝር' : 'Current Warehouse Stock Levels' ?></h3>
        <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary" style="padding:4px 10px;font-size:12px">📦 <?= current_locale() === 'am' ? 'ወደ መጋዘን ክምችት ሂድ' : 'Open Stock Inventory' ?> →</a>
    </div>
    <table>
        <thead><tr><th>SKU</th><th><?= current_locale()==='am'?'የዕቃ ስም':'Item Name' ?></th><th><?= current_locale()==='am'?'ምድብ':'Class' ?></th><th><?= current_locale()==='am'?'መለኪያ':'Unit' ?></th><th><?= current_locale()==='am'?'የሚገኝ ክምችት':'Available Stock' ?></th><th><?= current_locale()==='am'?'ዝቅተኛ ወሰን':'Threshold' ?></th><th><?= current_locale()==='am'?'ሁኔታ':'Status' ?></th></tr></thead>
        <tbody>
        <?php foreach ($stockRows as $r): 
            $isLow = (float)$r['cur_stock'] < (float)$r['min_lim'];
        ?>
            <tr>
                <td><code><?= h($r['sku']) ?></code></td>
                <td><strong><?= h($r['name']) ?></strong></td>
                <td><span class="badge dry"><?= h(ucfirst($r['product_class'])) ?></span></td>
                <td><?= h($r['unit']) ?></td>
                <td><strong style="color:<?= $isLow ? '#b3261e' : '#2e7d32' ?>"><?= number_format($r['cur_stock'], 2) ?> <?= h($r['unit']) ?></strong></td>
                <td><?= number_format($r['min_lim'], 1) ?> <?= h($r['unit']) ?></td>
                <td><?= $isLow ? '<span class="badge sold">⚠️ Low Stock</span>' : '<span class="badge milking">✓ Healthy</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($stockRows)): ?><tr><td colspan="7" class="text-muted">No stock items in catalog.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 4. PAYROLL & SALARIES REPORT ----
elseif ($mod === 'payroll'):
    $stmt = db()->prepare("SELECT r.period_start, r.period_end, r.status AS run_status, e.full_name, e.employee_number, d.name AS department, 
                                  p.base_salary, p.daily_rate, p.days_present, p.net_pay, p.total_paid, (p.net_pay - p.total_paid) AS remaining 
                           FROM fin_payslips p 
                           JOIN employees e ON e.id = p.employee_id 
                           LEFT JOIN departments d ON d.id = e.department_id
                           JOIN fin_payroll_runs r ON r.id = p.payroll_run_id 
                           WHERE r.period_start >= ? AND r.period_end <= ? 
                           ORDER BY r.id DESC, e.full_name");
    $stmt->execute([$from, $to]);
    $payRows = $stmt->fetchAll();
    $totalNet     = array_sum(array_column($payRows, 'net_pay'));
    $totalPaid    = array_sum(array_column($payRows, 'total_paid'));
    $totalRemain  = array_sum(array_map(fn($r) => $r['net_pay'] - $r['total_paid'], $payRows));
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi" style="border-left:4px solid var(--primary)"><div class="label">💳 <?= current_locale()==='am'?'ጠቅላላ የተጣራ ደመወዝ':'Total Net Salaries' ?></div><div class="value"><?= number_format($totalNet, 2) ?> ETB</div></div>
    <div class="kpi" style="border-left:4px solid #2e7d32"><div class="label">💵 <?= current_locale()==='am'?'የተከፈለ ደመወዝ':'Total Disbursed' ?></div><div class="value" style="color:#2e7d32"><?= number_format($totalPaid, 2) ?> ETB</div></div>
    <div class="kpi" style="border-left:4px solid <?= $totalRemain > 0 ? '#b3261e' : '#2e7d32' ?>"><div class="label">⏳ <?= current_locale()==='am'?'ያልተከፈለ ቀሪ ደመወዝ':'Unpaid Salaries' ?></div><div class="value" style="color:<?= $totalRemain > 0 ? '#b3261e' : '#2e7d32' ?>"><?= number_format($totalRemain, 2) ?> ETB</div></div>
    <div class="kpi" style="border-left:4px solid #1565c0"><div class="label">👥 <?= current_locale()==='am'?'የሰራተኞች ብዛት':'Staff Count' ?></div><div class="value"><?= count($payRows) ?></div></div>
</div>
<div class="card">
    <h3>💼 <?= current_locale() === 'am' ? 'የደመወዝ ክፍያ ዙሮች ዝርዝር ሪፖርት' : 'Payroll Ledger Summary' ?></h3>
    <table>
        <thead><tr><th><?= current_locale()==='am'?'ክፍለ-ጊዜ (ኢትዮጵያ)':'Period' ?></th><th><?= current_locale()==='am'?'ሰራተኛ':'Employee' ?></th><th><?= current_locale()==='am'?'መለያ':'Emp No' ?></th><th><?= current_locale()==='am'?'ክፍል':'Dept' ?></th><th><?= current_locale()==='am'?'ተመን':'Daily Rate' ?></th><th><?= current_locale()==='am'?'ቀን':'Days' ?></th><th><?= current_locale()==='am'?'የተጣራ':'Net Pay' ?></th><th><?= current_locale()==='am'?'የተከፈለ':'Paid' ?></th><th><?= current_locale()==='am'?'ቀሪ':'Balance' ?></th><th><?= current_locale()==='am'?'ሁኔታ':'Status' ?></th></tr></thead>
        <tbody>
        <?php foreach ($payRows as $r): ?>
            <?php $rem = (float)$r['net_pay'] - (float)$r['total_paid']; ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap"><?= h(eth_date($r['period_start']) . ' — ' . eth_date($r['period_end'])) ?></td>
                <td><strong><?= h($r['full_name']) ?></strong></td>
                <td><code><?= h($r['employee_number']) ?></code></td>
                <td><?= h($r['department'] ?: '—') ?></td>
                <td><?= number_format($r['daily_rate'], 2) ?></td>
                <td><?= (int)$r['days_present'] ?> days</td>
                <td><strong><?= number_format($r['net_pay'], 2) ?> ETB</strong></td>
                <td><span class="badge milking"><?= number_format($r['total_paid'], 2) ?></span></td>
                <td><?= $rem > 0 ? '<span class="badge sold">'.number_format($rem, 2).'</span>' : '<span class="badge milking">Fully Paid</span>' ?></td>
                <td><span class="badge dry"><?= h($r['run_status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($payRows)): ?><tr><td colspan="10" class="text-muted">No payroll runs in this date range.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 5. STAFF & ATTENDANCE REPORT ----
elseif ($mod === 'employees'):
    $stmt = db()->prepare("SELECT e.employee_number, e.full_name, d.name AS department, e.job_title, 
                                  COUNT(CASE WHEN a.status IN ('present','half_day') THEN 1 END) AS present_days, 
                                  COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS absent_days, 
                                  COUNT(a.id) AS total_days 
                           FROM employees e 
                           LEFT JOIN departments d ON d.id = e.department_id
                           LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ? 
                           WHERE e.status = 'active' 
                           GROUP BY e.id 
                           ORDER BY e.full_name");
    $stmt->execute([$from, $to]);
    $empRows = $stmt->fetchAll();
?>
<div class="card">
    <h3>👥 <?= current_locale() === 'am' ? 'የሰራተኞች የስራ መገኘት አፈጻጸም' : 'Staff Attendance Performance' ?> — <?= eth_date($from) ?> <?= current_locale() === 'am' ? 'እስከ' : 'to' ?> <?= eth_date($to) ?></h3>
    <table>
        <thead><tr><th><?= current_locale()==='am'?'መለያ ቁጥር':'Emp No' ?></th><th><?= current_locale()==='am'?'ሙሉ ስም':'Full Name' ?></th><th><?= current_locale()==='am'?'የስራ ክፍል':'Department' ?></th><th><?= current_locale()==='am'?'የስራ መደብ':'Job Title' ?></th><th><?= current_locale()==='am'?'የተገኙበት ቀን':'Present Days' ?></th><th><?= current_locale()==='am'?'የቀሩበት ቀን':'Absent Days' ?></th><th><?= current_locale()==='am'?'የመገኘት ምጣኔ':'Attendance Rate' ?></th></tr></thead>
        <tbody>
        <?php foreach ($empRows as $r): ?>
            <?php $pct = $r['total_days'] > 0 ? round($r['present_days'] / $r['total_days'] * 100, 1) : 100; ?>
            <tr>
                <td><code><?= h($r['employee_number']) ?></code></td>
                <td><strong><?= h($r['full_name']) ?></strong></td>
                <td><?= h($r['department'] ?: '—') ?></td>
                <td><?= h($r['job_title'] ?: '—') ?></td>
                <td><span class="badge milking"><?= (int)$r['present_days'] ?> days</span></td>
                <td><?= $r['absent_days'] > 0 ? '<span class="badge sold">'.((int)$r['absent_days']).'</span>' : '0' ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="background:#dfe5dd;border-radius:4px;height:8px;width:100px">
                            <div style="background:var(--primary);height:8px;border-radius:4px;width:<?= min(100, $pct) ?>%"></div>
                        </div>
                        <strong><?= $pct ?>%</strong>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($empRows)): ?><tr><td colspan="7" class="text-muted">No staff attendance data found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 6. POULTRY REPORT ----
elseif ($mod === 'poultry'):
    $stmt = db()->prepare("SELECT ec.collection_date, h.name AS house, f.breed, SUM(ec.whole_count) AS whole, SUM(ec.cracked_count) AS cracked, SUM(ec.broken_count) AS broken 
                           FROM poultry_egg_collections ec 
                           JOIN poultry_flocks f ON f.id = ec.flock_id 
                           JOIN poultry_houses h ON h.id = f.poultry_house_id 
                           WHERE ec.collection_date BETWEEN ? AND ? 
                           GROUP BY ec.collection_date, h.id 
                           ORDER BY ec.collection_date DESC");
    $stmt->execute([$from, $to]);
    $eggRows = $stmt->fetchAll();
    $totalEggs = array_sum(array_column($eggRows, 'whole'));
    
    $mortalityStmt = db()->prepare("SELECT COALESCE(SUM(count),0) FROM poultry_mortality_logs WHERE log_date BETWEEN ? AND ?");
    $mortalityStmt->execute([$from, $to]);
    $totalMortality = (int) $mortalityStmt->fetchColumn();
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi" style="border-left:4px solid var(--primary)"><div class="label">🥚 <?= current_locale()==='am'?'ጤነኛ እንቁላል ምርት':'Whole Eggs Collected' ?></div><div class="value"><?= number_format($totalEggs) ?> <?= current_locale()==='am'?'እንቁላል':'eggs' ?></div></div>
    <div class="kpi" style="border-left:4px solid #1565c0"><div class="label">📅 <?= current_locale()==='am'?'የምርት መሰብሰቢያ ቀናት':'Collection Days' ?></div><div class="value"><?= count($eggRows) ?> <?= current_locale()==='am'?'ቀን':'days' ?></div></div>
    <div class="kpi" style="border-left:4px solid <?= $totalMortality > 0 ? '#b3261e' : '#2e7d32' ?>"><div class="label">⚠️ <?= current_locale()==='am'?'የሞቱ ዶሮዎች':'Bird Mortality' ?></div><div class="value" style="color:<?= $totalMortality > 0 ? '#b3261e' : 'inherit' ?>"><?= number_format($totalMortality) ?> <?= current_locale()==='am'?'ዶሮዎች':'birds' ?></div></div>
</div>
<div class="card">
    <h3><?= current_locale() === 'am' ? 'የዕለት የእንቁላል ምርት መዝገብ' : 'Egg Production by Day & Poultry House' ?></h3>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th><th><?= current_locale() === 'am' ? 'የዶሮ ቤት' : 'House' ?></th><th><?= current_locale() === 'am' ? 'ዝርያ' : 'Breed' ?></th><th><?= current_locale() === 'am' ? 'ጤነኛ እንቁላል' : 'Whole Eggs' ?></th><th><?= current_locale() === 'am' ? 'የተሰነጠቀ' : 'Cracked' ?></th><th><?= current_locale() === 'am' ? 'የተሰበረ' : 'Broken' ?></th></tr></thead>
        <tbody>
        <?php foreach ($eggRows as $r): ?>
            <tr>
                <td><?= h(eth_date($r['collection_date'])) ?></td>
                <td><strong><?= h($r['house']) ?></strong></td>
                <td><?= h($r['breed']) ?></td>
                <td><strong style="color:#2e7d32"><?= number_format($r['whole']) ?></strong></td>
                <td><?= number_format($r['cracked']) ?></td>
                <td><?= number_format($r['broken']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($eggRows)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No egg collection data in this range.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 7. FISH REPORT ----
elseif ($mod === 'fish'):
    $stmt = db()->prepare("SELECT hr.harvest_date, p.name AS pond, s.species, SUM(hr.quantity_kg) AS total_kg, hr.grade 
                           FROM fish_harvest_records hr 
                           JOIN fish_stocks s ON s.id = hr.fish_stock_id 
                           JOIN fish_ponds p ON p.id = s.pond_id 
                           WHERE hr.harvest_date BETWEEN ? AND ? 
                           GROUP BY hr.harvest_date, p.id, hr.grade 
                           ORDER BY hr.harvest_date DESC");
    $stmt->execute([$from, $to]);
    $harvestRows = $stmt->fetchAll();
    $totalHarvest = array_sum(array_column($harvestRows, 'total_kg'));
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'ጠቅላላ የአሳ ምርት' : 'Total Fish Harvest' ?></div><div class="value"><?= number_format($totalHarvest, 1) ?> kg</div></div>
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'የማሰባሰብ ዙሮች' : 'Harvest Events' ?></div><div class="value"><?= count($harvestRows) ?></div></div>
</div>
<div class="card">
    <h3><?= current_locale() === 'am' ? 'የአሳ ምርት ስብሰባ መዝገብ' : 'Fish Harvest Log' ?></h3>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th><th><?= current_locale() === 'am' ? 'ኩሬ' : 'Pond' ?></th><th><?= current_locale() === 'am' ? 'የዓሳ ዝርያ' : 'Species' ?></th><th><?= current_locale() === 'am' ? 'ደረጃ' : 'Grade' ?></th><th><?= current_locale() === 'am' ? 'የተሰበሰበ (ኪ.ግ)' : 'Harvest Yield' ?></th></tr></thead>
        <tbody>
        <?php foreach ($harvestRows as $r): ?>
            <tr>
                <td><?= h(eth_date($r['harvest_date'])) ?></td>
                <td><strong><?= h($r['pond']) ?></strong></td>
                <td><?= h($r['species']) ?></td>
                <td><?= h($r['grade'] ?: '—') ?></td>
                <td><strong><?= number_format($r['total_kg'], 1) ?> kg</strong></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($harvestRows)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No harvest records in this range.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 8. FEED PRODUCTION REPORT ----
elseif ($mod === 'feed'):
    $stmt = db()->prepare(
        "SELECT h.harvest_date, pl.name AS plot_name, p.crop_type, p.planting_date, h.yield_kg, h.sent_to_store
         FROM feed_harvest_records h
         JOIN feed_planting_records p ON p.id = h.planting_record_id
         JOIN feed_plots pl ON pl.id = p.plot_id
         WHERE h.harvest_date BETWEEN ? AND ?
         ORDER BY h.harvest_date DESC"
    );
    $stmt->execute([$from, $to]);
    $feedRows = $stmt->fetchAll();
    $totalFeedYield = array_sum(array_column($feedRows, 'yield_kg'));
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'ጠቅላላ የመኖ ሰብል ምርት' : 'Total Crop Yield' ?></div><div class="value"><?= number_format($totalFeedYield, 1) ?> kg</div></div>
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'የምርት ስብሰባዎች' : 'Harvest Records' ?></div><div class="value"><?= count($feedRows) ?></div></div>
</div>
<div class="card">
    <h3><?= current_locale() === 'am' ? 'የመኖ እና የሰብል ምርት ስብሰባ ማጠቃለያ' : 'Feed & Crop Harvest Summary' ?></h3>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'የምርት ቀን' : 'Harvest Date' ?></th><th><?= current_locale() === 'am' ? 'ማሳ' : 'Plot' ?></th><th><?= current_locale() === 'am' ? 'የሰብል ዓይነት' : 'Crop Type' ?></th><th><?= current_locale() === 'am' ? 'የተዘራበት ቀን' : 'Planting Date' ?></th><th><?= current_locale() === 'am' ? 'ምርት (ኪ.ግ)' : 'Yield (kg)' ?></th><th><?= current_locale() === 'am' ? 'ወደ መጋዘን' : 'Sent to Store' ?></th></tr></thead>
        <tbody>
        <?php foreach ($feedRows as $r): ?>
            <tr>
                <td><?= h(eth_date($r['harvest_date'])) ?></td>
                <td><strong><?= h($r['plot_name']) ?></strong></td>
                <td><?= h($r['crop_type']) ?></td>
                <td style="font-size:12px"><?= h(eth_date($r['planting_date'])) ?></td>
                <td><strong><?= number_format($r['yield_kg'], 1) ?> kg</strong></td>
                <td><?= $r['sent_to_store'] ? '<span class="badge milking">✓ Sent to Store</span>' : '<span class="badge dry">Pending</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($feedRows)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No crop harvest data in this range.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ---- 9. VETERINARY REPORT ----
elseif ($mod === 'vet'):
    $stmt = db()->prepare(
        "SELECT r.*, c.tag_number, c.name AS cow_name, u.name AS vet_name
         FROM vet_health_records r
         LEFT JOIN dairy_cows c ON c.id = r.subject_id AND r.subject_type = 'cow'
         LEFT JOIN users u ON u.id = r.vet_id
         WHERE r.record_date BETWEEN ? AND ?
         ORDER BY r.record_date DESC"
    );
    $stmt->execute([$from, $to]);
    $vetRows = $stmt->fetchAll();
    $totalIncidents = count($vetRows);
?>
<div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'ጠቅላላ የህክምና መዝገቦች' : 'Total Health Logs' ?></div><div class="value"><?= $totalIncidents ?></div></div>
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'የታከሙ እንስሳት' : 'Treated Animals' ?></div><div class="value"><?= count(array_unique(array_column($vetRows, 'subject_id'))) ?></div></div>
</div>
<div class="card">
    <h3><?= current_locale() === 'am' ? 'የእንስሳት ህክምና እና ጤና መዝገብ' : 'Veterinary Treatment & Health Log' ?></h3>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th><th><?= current_locale() === 'am' ? 'የከብት መለያ' : 'Cow Tag' ?></th><th><?= current_locale() === 'am' ? 'ስም' : 'Cow Name' ?></th><th><?= current_locale() === 'am' ? 'የበሽታ ዓይነት' : 'Condition / Disease' ?></th><th><?= current_locale() === 'am' ? 'የተሰጠ ህክምና' : 'Treatment Administered' ?></th><th><?= current_locale() === 'am' ? 'ባለሙያ' : 'Attending Officer' ?></th></tr></thead>
        <tbody>
        <?php foreach ($vetRows as $r): ?>
            <tr>
                <td style="font-size:12px"><?= h(eth_date($r['record_date'])) ?></td>
                <td><a href="<?= BASE_URL ?>/modules/dairy/cow_view.php?id=<?= (int)$r['subject_id'] ?>"><strong><?= h($r['tag_number'] ?: 'N/A') ?></strong></a></td>
                <td><?= h($r['cow_name'] ?: '—') ?></td>
                <td><strong><?= h($r['condition_name']) ?></strong></td>
                <td><?= h($r['treatment'] ?: '—') ?></td>
                <td><?= h($r['vet_name'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vetRows)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No veterinary health logs in this date range.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
