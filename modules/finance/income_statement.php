<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'finance_officer', 'super_admin']);
require_once __DIR__ . '/../../includes/header.php';

$curEthYearNum  = eth_current_year();
$curEthMonthNum = eth_current_month();

$selEthYear  = isset($_GET['eth_year']) ? (int)$_GET['eth_year'] : $curEthYearNum;
$selEthMonth = isset($_GET['eth_month']) ? (int)$_GET['eth_month'] : $curEthMonthNum;

if ($selEthMonth > 0) {
    $mRange   = eth_month_range($selEthYear, $selEthMonth);
    $dateFrom = $mRange['from'];
    $dateTo   = $mRange['to'];
} elseif (isset($_GET['eth_year'])) {
    $yRange   = eth_year_range($selEthYear);
    $dateFrom = $yRange['from'];
    $dateTo   = $yRange['to'];
} else {
    $curEthYear = eth_year_range();
    $dateFrom   = $_GET['from'] ?? $curEthYear['from'];
    $dateTo     = $_GET['to']   ?? date('Y-m-d');
}

$ethMonths = [
    1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
    5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
    9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን'
];

// 1. REVENUE (From delivered/dispatched goods: milk, eggs, fish, feed, etc.)
$revStmt = db()->prepare(
    "SELECT i.product_class, i.name AS item_name, 
            COUNT(d.id) AS dispatch_count,
            COALESCE(SUM(d.quantity_dispatched), 0) AS total_qty,
            i.unit,
            COALESCE(SUM(d.total_price), 0) AS total_sales
     FROM dist_dispatches d
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     WHERE d.status NOT IN ('failed', 'cancelled')
       AND DATE(d.planned_dispatch_at) BETWEEN ? AND ?
     GROUP BY i.id
     ORDER BY total_sales DESC"
);
$revStmt->execute([$dateFrom, $dateTo]);
$revenueRows = $revStmt->fetchAll();

$totalRevenue = 0.0;
foreach ($revenueRows as $r) {
    $totalRevenue += (float)$r['total_sales'];
}

// 2. COST OF GOODS SOLD (COGS: Stock issues / feed consumption valued at batch unit cost)
$cogsStmt = db()->prepare(
    "SELECT i.name AS item_name, i.product_class,
            COALESCE(SUM(iss.quantity), 0) AS total_issued_qty,
            i.unit,
            COALESCE(SUM(iss.quantity * COALESCE(b.unit_cost, 0)), 0) AS total_cost
     FROM wh_stock_issues iss
     JOIN wh_stock_items i ON i.id = iss.stock_item_id
     LEFT JOIN wh_stock_batches b ON b.id = iss.stock_batch_id
     WHERE DATE(iss.issued_at) BETWEEN ? AND ?
       AND i.product_class IN ('feed', 'medicine', 'seed')
     GROUP BY i.id
     ORDER BY total_cost DESC"
);
$cogsStmt->execute([$dateFrom, $dateTo]);
$cogsRows = $cogsStmt->fetchAll();

$totalCogs = 0.0;
foreach ($cogsRows as $c) {
    $totalCogs += (float)$c['total_cost'];
}

$grossProfit = $totalRevenue - $totalCogs;
$grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0.0;

// 3. OPERATING EXPENSES (Approved expenses from fin_expenses)
$expStmt = db()->prepare(
    "SELECT category, unit_tag, COALESCE(SUM(amount), 0) AS total_amount, COUNT(id) AS tx_count
     FROM fin_expenses
     WHERE status IN ('approved', 'paid')
       AND expense_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY total_amount DESC"
);
$expStmt->execute([$dateFrom, $dateTo]);
$expenseRows = $expStmt->fetchAll();

$totalExpenses = 0.0;
foreach ($expenseRows as $e) {
    $totalExpenses += (float)$e['total_amount'];
}

// 4. PAYROLL / SALARIES EXPENSE (From approved/paid payroll runs in this period)
$payStmt = db()->prepare(
    "SELECT COALESCE(SUM(ps.net_pay + ps.allowances), 0) AS total_salaries
     FROM fin_payslips ps
     JOIN fin_payroll_runs pr ON pr.id = ps.payroll_run_id
     WHERE pr.period_start BETWEEN ? AND ?
        OR pr.period_end BETWEEN ? AND ?"
);
$payStmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$totalPayroll = (float)$payStmt->fetchColumn();

// 5. OTHER INFLOWS & OUTFLOWS (General finance transactions not categorized as dispatch/PO)
$otherInStmt = db()->prepare(
    "SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM fin_transactions
     WHERE direction = 'inflow'
       AND source_table IS NULL
       AND transaction_date BETWEEN ? AND ?
     GROUP BY category"
);
$otherInStmt->execute([$dateFrom, $dateTo]);
$otherInRows = $otherInStmt->fetchAll();
$totalOtherIn = 0.0;
foreach ($otherInRows as $oi) {
    $totalOtherIn += (float)$oi['total'];
}

$otherOutStmt = db()->prepare(
    "SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM fin_transactions
     WHERE direction = 'outflow'
       AND source_table IS NULL
       AND transaction_date BETWEEN ? AND ?
     GROUP BY category"
);
$otherOutStmt->execute([$dateFrom, $dateTo]);
$otherOutRows = $otherOutStmt->fetchAll();
$totalOtherOut = 0.0;
foreach ($otherOutRows as $oo) {
    $totalOtherOut += (float)$oo['total'];
}

// TOTAL OPERATING EXPENSES & NET PROFIT (VERSION 1: OPERATIONAL P&L)
$totalOpEx = $totalExpenses + $totalPayroll + $totalOtherOut;
$operatingProfit = $grossProfit - ($totalExpenses + $totalPayroll);
$netProfit = $grossProfit - $totalOpEx + $totalOtherIn;
$netMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0.0;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            📊 <?= current_locale() === 'am' ? 'የትርፍና ኪሳራ መግለጫ (Income Statement / P&L)' : 'Income Statement (Profit & Loss Statement)' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am'
                ? 'የድርጅቱ ጠቅላላ ገቢ፣ ቀጥተኛ የማምረቻ ወጪ፣ የስራ ማስኬጃና ደሞዝ፣ እና የተጣራ ትርፍ/ኪሳራ'
                : 'Summary of company revenues, cost of goods sold, operating expenses, and net profit/loss.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px" class="no-print">
        <button class="btn secondary" onclick="window.print()">🖨️ <?= current_locale() === 'am' ? 'አትም / PDF' : 'Print / Save PDF' ?></button>
        <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ዳሽቦርድ' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Filter Bar with Ethiopian Calendar -->
<div class="card no-print" style="margin-bottom:20px;padding:16px 20px">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
        <div>
            <label style="display:block;font-size:12px;font-weight:bold;margin-bottom:4px">📅 የኢትዮጵያ በጀት ዓመት</label>
            <select name="eth_year" style="padding:7px 12px;border-radius:6px;border:1px solid var(--border);font-size:13px;font-weight:600">
                <?php for ($y = $curEthYearNum + 1; $y >= $curEthYearNum - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $selEthYear === $y ? 'selected' : '' ?>><?= $y ?> ዓ.ም</option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:bold;margin-bottom:4px">🗓️ ወር ይምረጡ</label>
            <select name="eth_month" style="padding:7px 12px;border-radius:6px;border:1px solid var(--border);font-size:13px;font-weight:600">
                <option value="0" <?= $selEthMonth === 0 ? 'selected' : '' ?>>— ሙሉ በጀት ዓመት (All Year) —</option>
                <?php foreach ($ethMonths as $mNum => $mName): ?>
                    <option value="<?= $mNum ?>" <?= $selEthMonth === $mNum ? 'selected' : '' ?>><?= $mName ?> ወር</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn" style="padding:7px 18px;background:#1e5631;border-color:#1e5631">🔍 ሪፖርቱን አሳይ</button>
        </div>
        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
            <a href="?eth_year=<?= $curEthYearNum ?>&eth_month=<?= $curEthMonthNum ?>" class="btn secondary" style="font-size:12px;padding:6px 12px">📅 የዚህ ወር (<?= $ethMonths[$curEthMonthNum] ?>)</a>
            <a href="?eth_year=<?= $curEthYearNum ?>&eth_month=0" class="btn secondary" style="font-size:12px;padding:6px 12px">🗓️ የዚህ ሙሉ ዓመት (<?= $curEthYearNum ?> ዓ.ም)</a>
        </div>
    </form>
</div>

<!-- Easy to Understand Grand Summary Box -->
<div class="card" style="margin-bottom:20px;padding:18px 20px;border-radius:10px;background:var(--card);box-shadow:0 2px 10px rgba(0,0,0,0.06)">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;border-bottom:1px solid var(--border);padding-bottom:10px">
        <h3 style="margin:0;font-size:17px">
            <?= $selEthMonth > 0 ? "📅 የ{$ethMonths[$selEthMonth]} ወር {$selEthYear} ዓ.ም" : "🗓️ የ{$selEthYear} ሙሉ በጀት ዓመት" ?> የትርፍና ኪሳራ ቀላል ስሌት
        </h3>
        <span class="badge active" style="font-size:12.5px;padding:4px 10px">
            <?= eth_date($dateFrom) ?> — <?= eth_date($dateTo) ?>
        </span>
    </div>

    <?php 
    $totalAllOutflows = $totalExpenses + $totalPayroll;
    $cleanNetProfit = $totalRevenue - $totalAllOutflows;
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:14px;align-items:center">
        <!-- 1. REVENUE -->
        <div style="background:rgba(46,125,50,0.07);border:2px solid #2e7d32;border-radius:8px;padding:14px;text-align:center">
            <div style="font-size:12px;font-weight:700;color:#2e7d32;text-transform:uppercase">🟢 1. ጠቅላላ የተገኘ ገቢ</div>
            <div style="font-size:20px;font-weight:800;color:#2e7d32;margin-top:4px">+<?= number_format($totalRevenue, 2) ?> ETB</div>
            <small class="text-muted" style="font-size:11px">ከምርት ሽያጭ የተገኘ</small>
        </div>

        <!-- 2. EXPENSES -->
        <div style="background:rgba(198,40,40,0.07);border:2px solid #c62828;border-radius:8px;padding:14px;text-align:center">
            <div style="font-size:12px;font-weight:700;color:#c62828;text-transform:uppercase">🔴 2. ጠቅላላ የወጣ ወጪና ደሞዝ</div>
            <div style="font-size:20px;font-weight:800;color:#c62828;margin-top:4px">-<?= number_format($totalAllOutflows, 2) ?> ETB</div>
            <small class="text-muted" style="font-size:11px">የስራ ማስኬጃ (<?= number_format($totalExpenses,0) ?>) + ደሞዝ (<?= number_format($totalPayroll,0) ?>)</small>
        </div>

        <!-- 3. NET PROFIT / LOSS -->
        <div style="background:<?= $cleanNetProfit >= 0 ? 'rgba(46,125,50,0.12)' : 'rgba(179,38,30,0.12)' ?>;border:3px solid <?= $cleanNetProfit >= 0 ? '#2e7d32' : '#b3261e' ?>;border-radius:8px;padding:14px;text-align:center">
            <div style="font-size:12px;font-weight:700;color:<?= $cleanNetProfit >= 0 ? '#2e7d32' : '#b3261e' ?>;text-transform:uppercase">
                <?= $cleanNetProfit >= 0 ? '🏆 3. የተጣራ ትርፍ (Net Profit)' : '⚠️ 3. የተጣራ ኪሳራ (Net Loss)' ?>
            </div>
            <div style="font-size:22px;font-weight:800;color:<?= $cleanNetProfit >= 0 ? '#2e7d32' : '#b3261e' ?>;margin-top:4px">
                <?= $cleanNetProfit >= 0 ? '+' : '' ?><?= number_format($cleanNetProfit, 2) ?> ETB
            </div>
            <small style="font-size:11.5px;font-weight:600;color:<?= $cleanNetProfit >= 0 ? '#2e7d32' : '#b3261e' ?>">
                <?= $cleanNetProfit >= 0 ? '✓ ድርጅቱ ትርፍ ላይ ይገኛል' : '⚠️ ወጪ ከገቢ በልጧል' ?>
            </small>
        </div>
    </div>
</div>

<!-- Formal Financial Statement Table -->
<div class="card" style="margin-bottom:24px">
    <div style="text-align:center;border-bottom:2px solid var(--border);padding-bottom:14px;margin-bottom:16px">
        <h3 style="margin:0 0 4px 0;font-size:19px"><?= current_locale() === 'am' ? 'ተክሉ ጌታቸው የከብት እርባታ እና የወተት ተዋጽኦ ማቀነባበሪያ ድርጅት' : 'TEKLU GETACHEW CATTLE BREEDING & DAIRY PROCESSING ENTERPRISE' ?></h3>
        <div style="font-size:15px;font-weight:bold;color:var(--primary)">
            <?= current_locale() === 'am' ? 'የትርፍና ኪሳራ መግለጫ (STATEMENT OF PROFIT OR LOSS)' : 'STATEMENT OF PROFIT OR LOSS' ?>
        </div>
        <div style="font-size:13px;color:var(--text-muted)">
            <?= current_locale() === 'am' ? 'የተዘጋጀበት ጊዜ (በኢትዮጵያ ካላንደር)፦ ከ ' . h(eth_date($dateFrom, 'long')) . ' እስከ ' . h(eth_date($dateTo, 'long')) : 'For the period from ' . h($dateFrom) . ' to ' . h($dateTo) ?>
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:14px">
        <!-- 1. REVENUES -->
        <tr style="background:var(--bg);font-weight:bold">
            <td colspan="2" style="padding:10px 12px;color:#2e7d32;font-size:15px">
                1. <?= current_locale() === 'am' ? 'የሽያጭ ገቢዎች (OPERATING REVENUES)' : 'OPERATING REVENUES' ?>
            </td>
            <td style="text-align:right;padding:10px 12px;color:#2e7d32;font-size:15px">ETB</td>
        </tr>
        <?php if (empty($revenueRows)): ?>
            <tr>
                <td colspan="2" style="padding:8px 24px;color:var(--text-muted);font-style:italic">
                    <?= current_locale() === 'am' ? 'በዚህ ጊዜ ውስጥ ምንም ሽያጭ አልተመዘገበም።' : 'No sales records in this period.' ?>
                </td>
                <td style="text-align:right;padding:8px 12px">0.00</td>
            </tr>
        <?php else: ?>
            <?php foreach ($revenueRows as $r): ?>
                <tr>
                    <td style="padding:6px 24px">
                        <?= h($r['item_name']) ?>
                        <span class="text-muted" style="font-size:12px">(<?= number_format((float)$r['total_qty'], 1) ?> <?= h($r['unit']) ?>)</span>
                    </td>
                    <td style="text-align:right;padding:6px 12px;color:var(--text-muted);font-size:12px">
                        <?= (int)$r['dispatch_count'] ?> <?= current_locale() === 'am' ? 'ስርጭቶች' : 'dispatches' ?>
                    </td>
                    <td style="text-align:right;padding:6px 12px;font-weight:600">
                        <?= number_format((float)$r['total_sales'], 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr style="border-top:1px solid var(--border);border-bottom:2px solid var(--border);font-weight:bold;background:rgba(46,125,50,0.05)">
            <td colspan="2" style="padding:10px 12px">
                <?= current_locale() === 'am' ? 'ጠቅላላ ገቢ (Total Operating Revenue)' : 'Total Operating Revenue' ?>
            </td>
            <td style="text-align:right;padding:10px 12px;color:#2e7d32;font-size:16px">
                <?= number_format($totalRevenue, 2) ?>
            </td>
        </tr>

        <!-- 2. COST OF GOODS SOLD -->
        <tr style="background:var(--bg);font-weight:bold">
            <td colspan="2" style="padding:10px 12px;color:#e65100;font-size:15px;margin-top:10px">
                2. <?= current_locale() === 'am' ? 'የተሸጡ እቃዎችና የምርት ቀጥተኛ ወጪ (COST OF GOODS SOLD)' : 'COST OF GOODS SOLD (COGS)' ?>
            </td>
            <td style="text-align:right;padding:10px 12px;color:#e65100;font-size:15px">ETB</td>
        </tr>
        <?php if (empty($cogsRows)): ?>
            <tr>
                <td colspan="2" style="padding:8px 24px;color:var(--text-muted);font-style:italic">
                    <?= current_locale() === 'am' ? 'በዚህ ጊዜ ውስጥ የወጣ የምርት ጥሬ ዕቃ የለም።' : 'No raw material / feed usage recorded.' ?>
                </td>
                <td style="text-align:right;padding:8px 12px">0.00</td>
            </tr>
        <?php else: ?>
            <?php foreach ($cogsRows as $c): ?>
                <tr>
                    <td style="padding:6px 24px">
                        <?= h($c['item_name']) ?> (<?= h(ucfirst($c['product_class'])) ?>)
                    </td>
                    <td style="text-align:right;padding:6px 12px;color:var(--text-muted);font-size:12px">
                        <?= number_format((float)$c['total_issued_qty'], 1) ?> <?= h($c['unit']) ?>
                    </td>
                    <td style="text-align:right;padding:6px 12px;color:#e65100">
                        (<?= number_format((float)$c['total_cost'], 2) ?>)
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr style="border-top:1px solid var(--border);border-bottom:2px solid var(--border);font-weight:bold;background:rgba(230,81,0,0.05)">
            <td colspan="2" style="padding:10px 12px">
                <?= current_locale() === 'am' ? 'ጠቅላላ የምርት ቀጥተኛ ወጪ (Total COGS)' : 'Total Cost of Goods Sold' ?>
            </td>
            <td style="text-align:right;padding:10px 12px;color:#e65100;font-size:16px">
                (<?= number_format($totalCogs, 2) ?>)
            </td>
        </tr>

        <!-- GROSS PROFIT -->
        <tr style="border-bottom:2px solid var(--border);font-weight:bold;background:var(--card)">
            <td colspan="2" style="padding:12px;font-size:16px">
                💵 <?= current_locale() === 'am' ? 'አጠቃላይ ትርፍ (GROSS PROFIT)' : 'GROSS PROFIT' ?>
                <span class="text-muted" style="font-size:12px;font-weight:normal;margin-left:8px">(Margin: <?= number_format($grossMargin, 1) ?>%)</span>
            </td>
            <td style="text-align:right;padding:12px;font-size:17px;color:<?= $grossProfit >= 0 ? '#2e7d32' : '#b3261e' ?>">
                <?= number_format($grossProfit, 2) ?>
            </td>
        </tr>

        <!-- 3. OPERATING EXPENSES -->
        <tr style="background:var(--bg);font-weight:bold">
            <td colspan="2" style="padding:10px 12px;color:#1565c0;font-size:15px">
                3. <?= current_locale() === 'am' ? 'የስራ ማስኬጃና አጠቃላይ ወጪዎች (OPERATING EXPENSES)' : 'OPERATING EXPENSES' ?>
            </td>
            <td style="text-align:right;padding:10px 12px;color:#1565c0;font-size:15px">ETB</td>
        </tr>
        <?php if ($totalPayroll > 0): ?>
            <tr>
                <td style="padding:6px 24px">
                    👥 <?= current_locale() === 'am' ? 'የሰራተኞች ደሞዝና አበል (Staff Salaries & Wages)' : 'Salaries, Wages & Allowances' ?>
                </td>
                <td style="text-align:right;padding:6px 12px;color:var(--text-muted);font-size:12px">Payroll Runs</td>
                <td style="text-align:right;padding:6px 12px;color:#c62828">
                    (<?= number_format($totalPayroll, 2) ?>)
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($expenseRows as $e): ?>
            <tr>
                <td style="padding:6px 24px">
                    <?= h($e['category']) ?>
                    <span class="text-muted" style="font-size:12px">(<?= h($e['unit_tag']) ?>)</span>
                </td>
                <td style="text-align:right;padding:6px 12px;color:var(--text-muted);font-size:12px">
                    <?= (int)$e['tx_count'] ?> <?= current_locale() === 'am' ? 'ደረሰኞች' : 'vouchers' ?>
                </td>
                <td style="text-align:right;padding:6px 12px;color:#c62828">
                    (<?= number_format((float)$e['total_amount'], 2) ?>)
                </td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($otherOutRows as $oo): ?>
            <tr>
                <td style="padding:6px 24px">
                    <?= h($oo['category'] ?: 'Other Finance Outflow') ?>
                </td>
                <td style="text-align:right;padding:6px 12px;color:var(--text-muted);font-size:12px">Direct Outflow</td>
                <td style="text-align:right;padding:6px 12px;color:#c62828">
                    (<?= number_format((float)$oo['total'], 2) ?>)
                </td>
            </tr>
        <?php endforeach; ?>
        <tr style="border-top:1px solid var(--border);border-bottom:2px solid var(--border);font-weight:bold;background:rgba(21,101,192,0.05)">
            <td colspan="2" style="padding:10px 12px">
                <?= current_locale() === 'am' ? 'ጠቅላላ የስራ ማስኬጃና የደሞዝ ወጪ (Total Operating Expenses & Payroll)' : 'Total Operating Expenses & Payroll' ?>
            </td>
            <td style="text-align:right;padding:10px 12px;color:#c62828;font-size:16px">
                (<?= number_format($totalOpEx, 2) ?>)
            </td>
        </tr>

        <!-- 4. OTHER INCOME -->
        <?php if ($totalOtherIn > 0): ?>
            <tr style="background:var(--bg);font-weight:bold">
                <td colspan="2" style="padding:8px 12px;color:#2e7d32">
                    4. <?= current_locale() === 'am' ? 'ሌሎች ገቢዎች (OTHER NON-OPERATING INCOME)' : 'OTHER NON-OPERATING INCOME' ?>
                </td>
                <td style="text-align:right;padding:8px 12px">ETB</td>
            </tr>
            <?php foreach ($otherInRows as $oi): ?>
                <tr>
                    <td colspan="2" style="padding:6px 24px"><?= h($oi['category'] ?: 'Miscellaneous Income') ?></td>
                    <td style="text-align:right;padding:6px 12px;font-weight:600"><?= number_format((float)$oi['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- FINAL NET PROFIT / LOSS -->
        <tr style="border-top:3px double var(--border);border-bottom:3px double var(--border);background:<?= $netProfit >= 0 ? 'rgba(46,125,50,0.1)' : 'rgba(179,38,30,0.1)' ?>;font-weight:900">
            <td colspan="2" style="padding:14px 12px;font-size:18px">
                🏆 <?= current_locale() === 'am' ? 'የተጣራ የትርፍ / ኪሳራ ውጤት (NET PROFIT / LOSS)' : 'NET OPERATIONAL PROFIT / (LOSS)' ?>
            </td>
            <td style="text-align:right;padding:14px 12px;font-size:20px;color:<?= $netProfit >= 0 ? '#1b5e20' : '#b3261e' ?>">
                <?= number_format($netProfit, 2) ?> ETB
            </td>
        </tr>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
