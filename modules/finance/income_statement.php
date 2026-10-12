<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'finance_officer', 'super_admin']);
require_once __DIR__ . '/../../includes/header.php';

$dateFrom = $_GET['from'] ?? date('Y-01-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');

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
        <a href="/aleltu-eifms/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ዳሽቦርድ' : 'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card no-print" style="margin-bottom:20px;padding:14px 18px">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end">
        <div>
            <label style="display:block;font-size:12px;font-weight:bold;margin-bottom:4px"><?= current_locale() === 'am' ? 'ከ ቀን (From Date)' : 'From Date' ?></label>
            <input type="date" name="from" value="<?= h($dateFrom) ?>" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:bold;margin-bottom:4px"><?= current_locale() === 'am' ? 'እስከ ቀን (To Date)' : 'To Date' ?></label>
            <input type="date" name="to" value="<?= h($dateTo) ?>" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border);background:var(--bg);color:var(--text)">
        </div>
        <div>
            <button type="submit" class="btn" style="padding:7px 16px">🔍 <?= current_locale() === 'am' ? 'አጣራ (Filter)' : 'Filter' ?></button>
        </div>
        <div style="margin-left:auto;display:flex;gap:6px">
            <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn secondary" style="font-size:12px;padding:6px 10px"><?= current_locale() === 'am' ? 'የዚህ ወር' : 'This Month' ?></a>
            <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn secondary" style="font-size:12px;padding:6px 10px"><?= current_locale() === 'am' ? 'የዚህ ዓመት' : 'This Year' ?></a>
        </div>
    </form>
</div>

<!-- Key Highlights KPI Row (Version 1: 4 Core Pillars) -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi" style="border-left:4px solid #2e7d32">
        <div class="label">🟢 <?= current_locale() === 'am' ? 'ጠቅላላ የሽያጭ ገቢ (Revenue)' : 'Total Revenue' ?></div>
        <div class="value" style="color:#2e7d32"><?= number_format($totalRevenue, 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:4px solid #e65100">
        <div class="label">📦 <?= current_locale() === 'am' ? 'የምርትና መኖ ወጪ (COGS)' : 'Cost of Goods Sold' ?></div>
        <div class="value" style="color:#e65100"><?= number_format($totalCogs, 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border-left:4px solid #1565c0">
        <div class="label">🏢 <?= current_locale() === 'am' ? 'የሥራ ማስኬጃና ደሞዝ (OpEx)' : 'Operating Expenses & Payroll' ?></div>
        <div class="value" style="color:#1565c0"><?= number_format($totalOpEx, 2) ?> ETB</div>
    </div>
    <div class="kpi" style="border:2px solid <?= $netProfit >= 0 ? '#2e7d32' : '#b3261e' ?>;background:<?= $netProfit >= 0 ? 'rgba(46,125,50,0.06)' : 'rgba(179,38,30,0.06)' ?>">
        <div class="label">💰 <?= current_locale() === 'am' ? 'የተጣራ ትርፍ/ኪሳራ (Net Profit)' : 'Net Profit / (Loss)' ?></div>
        <div class="value" style="color:<?= $netProfit >= 0 ? '#2e7d32' : '#b3261e' ?>">
            <?= number_format($netProfit, 2) ?> ETB
            <span style="font-size:13px;font-weight:normal;display:block">(<?= number_format($netMargin, 1) ?>% Margin)</span>
        </div>
    </div>
</div>

<!-- Formal Financial Statement Table -->
<div class="card" style="margin-bottom:24px">
    <div style="text-align:center;border-bottom:2px solid var(--border);padding-bottom:14px;margin-bottom:16px">
        <h3 style="margin:0 0 4px 0;font-size:20px">ALELTU AGRICULTURAL & FARMING ENTERPRISE</h3>
        <div style="font-size:15px;font-weight:bold;color:var(--primary)">
            <?= current_locale() === 'am' ? 'የትርፍና ኪሳራ መግለጫ (STATEMENT OF PROFIT OR LOSS)' : 'STATEMENT OF PROFIT OR LOSS' ?>
        </div>
        <div style="font-size:13px;color:var(--text-muted)">
            <?= current_locale() === 'am' ? 'የተዘጋጀበት ጊዜ: ከ ' . h($dateFrom) . ' እስከ ' . h($dateTo) : 'For the period from ' . h($dateFrom) . ' to ' . h($dateTo) ?>
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
