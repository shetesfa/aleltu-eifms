<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'finance_officer', 'super_admin']);
require_once __DIR__ . '/../../includes/header.php';

// ── Core finance data ────────────────────────────────────────────────────────
$cash       = finance_cash_position();
$byMethod   = finance_balance_by_method();
$receivable = finance_receivables_summary();
$payable    = finance_payables_summary();
$inventory  = finance_inventory_value();
$revenue    = finance_revenue_vs_collected();

$totalMoney  = ($byMethod['bank'] ?? 0) + ($byMethod['cash'] ?? 0) + ($byMethod['mobile'] ?? 0);
$netPosition = $totalMoney - (float)$payable['total_payable'];

// Month dates
$monthStart = date('Y-m-01');
$today      = date('Y-m-d');
$yearStart  = date('Y-01-01');
$monthRevenue = finance_revenue_vs_collected($monthStart, $today);

// Year-to-date quick P&L estimate
$ytdRev = (float)db()->query("SELECT COALESCE(SUM(total_price), 0) FROM dist_dispatches WHERE status NOT IN ('failed', 'cancelled') AND DATE(planned_dispatch_at) >= '{$yearStart}'")->fetchColumn();
$ytdExp = (float)db()->query("SELECT COALESCE(SUM(amount), 0) FROM fin_expenses WHERE status IN ('approved','paid') AND expense_date >= '{$yearStart}'")->fetchColumn();
$ytdSal = (float)db()->query("SELECT COALESCE(SUM(net_pay + allowances), 0) FROM fin_payslips ps JOIN fin_payroll_runs pr ON pr.id = ps.payroll_run_id WHERE pr.period_start >= '{$yearStart}'")->fetchColumn();
$ytdProfit = $ytdRev - ($ytdExp + $ytdSal);

// Department cost summary for current month (all units)
$unitCostRows = db()->query(
    "SELECT unit_tag,
            SUM(CASE WHEN direction='inflow'  THEN amount ELSE 0 END) AS inflow,
            SUM(CASE WHEN direction='outflow' THEN amount ELSE 0 END) AS outflow
     FROM fin_transactions
     WHERE transaction_date BETWEEN '{$monthStart}' AND '{$today}'
     GROUP BY unit_tag"
)->fetchAll();
$unitCosts = [];
foreach ($unitCostRows as $r) {
    $unitCosts[$r['unit_tag']] = $r;
}
// Special withdrawals (Holiday gifts, admin protocol, food, drawings)
$specialGifts = (float)db()->query("SELECT COALESCE(SUM(amount), 0) FROM fin_expenses WHERE withdrawal_type IN ('holiday_gift', 'admin_protocol', 'food_withdrawal', 'owner_drawing', 'cash_withdrawal') AND status IN ('approved','paid')")->fetchColumn();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            💰 <?= current_locale() === 'am' ? 'የፋይናንስና የሂሳብ አስተዳደር ማዕከል (Finance & Treasury Control Center)' : 'Finance & Treasury Command Center' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am' ? 'የኩባንያው አጠቃላይ የፋይናንስ አቋም — ባንክ፣ ጥሬ ገንዘብ፣ ተሰብሳቢ፣ ተከፋይ፣ ትርፍና ኪሳራ እና የሀብት ሚዛን' : 'Complete corporate financial position — Cash, Receivables, Payables, P&L, and Balance Sheet.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap" class="no-print">
        <a href="/aleltu-eifms/modules/finance/income_statement.php" class="btn" style="background:#2e7d32;border-color:#2e7d32">📊 <?= current_locale()==='am'?'የትርፍና ኪሳራ ሪፖርት (P&L)':'Income Statement' ?></a>
        <a href="/aleltu-eifms/modules/finance/expenses.php" class="btn secondary">💸 <?= h(t('fin.expenses','Expenses')) ?></a>
        <a href="/aleltu-eifms/modules/finance/purchasing.php" class="btn secondary">🛒 <?= current_locale()==='am'?'የግዢ ትዕዛዞች':'Purchasing' ?></a>
        <a href="/aleltu-eifms/modules/finance/receivables.php" class="btn secondary">👥 <?= current_locale()==='am'?'ተሰብሳቢ ዕዳ':'Receivables' ?></a>
        <a href="/aleltu-eifms/modules/finance/payroll.php" class="btn secondary">💳 <?= current_locale()==='am'?'ደሞዝ':'Payroll' ?></a>
    </div>
</div>

<?php if ($netPosition < 0): ?>
<div style="background:#ffebee;border-left:5px solid #b3261e;border-radius:6px;padding:12px 18px;margin-bottom:18px">
    <strong>⚠️ <?= current_locale()==='am' ? 'ማስጠንቀቂያ፡ ጠቅላላ ፈሳሽ ገንዘብ ቀሪ ሒሳብ ከዜሮ በታች ነው' : 'Warning: Net liquid position is negative' ?></strong>
    <p style="margin:4px 0 0"><?= current_locale()==='am' ? 'ያልተከፈሉ የአቅራቢ ዕዳዎች ከሚገኘው የባንክና የጥሬ ገንዘብ ክምችት ይበልጣሉ።' : 'Outstanding supplier payables exceed available bank + cash on hand.' ?></p>
    <a href="/aleltu-eifms/modules/finance/payables.php" style="font-size:13px">→ <?= current_locale()==='am' ? 'ሁሉንም ተከፋይ ዕዳዎች ይመልከቱ' : 'View all supplier payables' ?></a>
</div>
<?php endif; ?>

<!-- ROW 1: Available Liquid Money & YTD Profit -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    💵 <?= current_locale()==='am' ? 'የሚገኝ ፈሳሽ ገንዘብ (ኦሮሚያ ባንክ፣ ቴሌብር፣ ካዝና) እና የዓመቱ የተጣራ ትርፍ' : 'Liquid Cash Position & YTD Profit' ?>
</div>
<div class="kpi-row" style="margin-bottom:20px">
    <?php foreach (($byMethod['_accounts'] ?? []) as $acc): 
        $accMethod = match($acc['type']) { 'bank' => 'bank', 'mobile' => 'telebirr', default => 'cash' };
    ?>
    <a href="/aleltu-eifms/modules/finance/ledger.php?method=<?= $accMethod ?>&account_id=<?= (int)$acc['id'] ?>" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-top:3px solid var(--primary)">
            <div class="label"><?= $acc['type']==='bank' ? '🏦' : ($acc['type']==='mobile'?'📱':'💵') ?> <?= h($acc['name']) ?></div>
            <div class="value" style="color:<?= $acc['balance']>=0 ? 'var(--primary)' : '#b3261e' ?>">
                <?= number_format($acc['balance'],0) ?> ETB
            </div>
            <div style="font-size:11px;color:var(--primary);margin-top:4px;font-weight:600">→ የባንክ መግለጫ (Statement)</div>
        </div>
    </a>
    <?php endforeach; ?>
    <div class="kpi" style="border:2px solid var(--primary)">
        <div class="label">💰 <?= current_locale()==='am' ? 'ጠቅላላ ፈሳሽ ገንዘብ' : 'Total Available Cash' ?></div>
        <div class="value"><strong style="color:<?= $totalMoney>=0?'var(--primary)':'#b3261e' ?>"><?= number_format($totalMoney,0) ?> ETB</strong></div>
    </div>
    <a href="/aleltu-eifms/modules/finance/expenses.php?filter_type=gifts" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="border-left:4px solid #7b1fa2">
            <div class="label">🎁 <?= current_locale()==='am' ? 'የበዓል ስጦታና ልዩ ወጪዎች' : 'Gifts & Withdrawals' ?></div>
            <div class="value" style="color:#7b1fa2">
                <?= number_format($specialGifts,0) ?> ETB
            </div>
        </div>
    </a>
    <a href="/aleltu-eifms/modules/finance/income_statement.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="border:2px solid <?= $ytdProfit>=0?'#2e7d32':'#b3261e' ?>;background:<?= $ytdProfit>=0?'rgba(46,125,50,0.06)':'rgba(179,38,30,0.06)' ?>">
            <div class="label">🏆 <?= current_locale()==='am' ? 'የዓመቱ ትርፍ (YTD Profit)' : 'YTD Net Profit' ?></div>
            <div class="value" style="color:<?= $ytdProfit>=0?'#2e7d32':'#b3261e' ?>">
                <?= number_format($ytdProfit,0) ?> ETB
            </div>
        </div>
    </a>
</div>

<!-- ROW 2: Core Operational Centers (Version 1) -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    📊 <?= current_locale()==='am' ? 'የስሪት 1 ዋና ዋና የፋይናንስና ካዝና ማዕከሎች' : 'Version 1 Core Finance & Cash Centers' ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:24px">
    <a href="/aleltu-eifms/modules/finance/income_statement.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #2e7d32;transition:transform .2s">
        <div style="font-size:24px;margin-bottom:6px">📊</div>
        <strong style="font-size:15px;display:block"><?= current_locale()==='am'?'ቀላል የትርፍና ኪሳራ መግለጫ':'Simple Profit & Loss (P&L)' ?></strong>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'ገቢ ሲቀነስ ወጪ = የተጣራ ትርፍ':'Revenue - Costs = Net Cash Profit' ?></div>
    </a>

    <a href="/aleltu-eifms/modules/finance/expenses.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #c62828;transition:transform .2s">
        <div style="font-size:24px;margin-bottom:6px">💸</div>
        <strong style="font-size:15px;display:block"><?= current_locale()==='am'?'ዕለታዊ የስራ ወጪዎች':'Daily Expenses' ?></strong>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'ነዳጅ፣ ጥገና፣ መብራትና የዕለት ወጪ':'Fuel, repairs, utilities & daily spend' ?></div>
    </a>

    <a href="/aleltu-eifms/modules/finance/purchasing.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #1565c0;transition:transform .2s">
        <div style="font-size:24px;margin-bottom:6px">🛒</div>
        <strong style="font-size:15px;display:block"><?= current_locale()==='am'?'የግዢ ትዕዛዞችና ክፍያዎች':'Purchasing & POs' ?></strong>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'የመኖና እቃዎች ግዢ (3-Way Match)':'Orders, receiving lock & payments' ?></div>
    </a>

    <a href="/aleltu-eifms/modules/finance/receivables.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #f57f17;transition:transform .2s">
        <div style="font-size:24px;margin-bottom:6px">👥</div>
        <strong style="font-size:15px;display:block"><?= current_locale()==='am'?'የደንበኞች ዱቤና ተሰብሳቢ':'Customer Receivables' ?></strong>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'ያልተሰበሰቡ ሽያጮችና የዱቤ ክትትል':'Unpaid dispatches & debt collection' ?></div>
    </a>

    <a href="/aleltu-eifms/modules/finance/payroll.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #6a1b9a;transition:transform .2s">
        <div style="font-size:24px;margin-bottom:6px">💳</div>
        <strong style="font-size:15px;display:block"><?= current_locale()==='am'?'የሰራተኞች ወርሃዊ ደሞዝ':'Staff Payroll & Salaries' ?></strong>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'መሰረታዊ ደሞዝ፣ አበልና የተጣራ ክፍያ':'Net salary calculations & payouts' ?></div>
    </a>
</div>

<!-- ROW 3: Receivables / Payables / Inventory Value -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    📊 <?= current_locale()==='am' ? 'ተሰብሳቢ / ተከፋይ / የዕቃ ዋጋ' : 'Receivables / Payables / Inventory Value' ?>
</div>
<div class="kpi-row" style="margin-bottom:20px">
    <a href="/aleltu-eifms/modules/finance/receivables.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #2e7d32">
            <div class="label">👥 <?= current_locale()==='am' ? 'ደንበኞች የሚከፍሉን (Receivables)' : 'Customer Receivables' ?></div>
            <div class="value" style="color:#2e7d32"><?= number_format($receivable['total_receivable'],0) ?> ETB</div>
        </div>
    </a>
    <a href="/aleltu-eifms/modules/finance/payables.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid <?= $payable['total_payable']>0?'#c62828':'var(--border)' ?>">
            <div class="label">🏪 <?= current_locale()==='am' ? 'ለአቅራቢዎች የሚከፈል (Payables)' : 'Supplier Payables' ?></div>
            <div class="value" style="color:<?= $payable['total_payable']>0?'#c62828':'inherit' ?>"><?= number_format($payable['total_payable'],0) ?> ETB</div>
        </div>
    </a>
    <a href="/aleltu-eifms/modules/finance/inventory_value.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #1565c0">
            <div class="label">📦 <?= current_locale()==='am' ? 'የዕቃ ክምችት ዋጋ (Inventory)' : 'Inventory Value' ?></div>
            <div class="value" style="color:#1565c0"><?= number_format($inventory['total_inventory_value'],0) ?> ETB</div>
        </div>
    </a>
</div>

<!-- ROW 4: Revenue vs Cash Collected -->
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">📈 <?= current_locale()==='am' ? 'የዚህ ወር ሽያጭ vs የተሰበሰበ ክፍያ' : "This Month's Revenue vs Cash Collected" ?></h3>
    <div class="kpi-row">
        <div class="kpi">
            <div class="label"><?= current_locale()==='am' ? 'ጠቅላላ ዋጋ (የተላኩ ምርቶች)' : 'Total Dispatched Value (Revenue)' ?></div>
            <div class="value"><?= number_format((float)$monthRevenue['total_revenue'],0) ?> ETB</div>
        </div>
        <div class="kpi">
            <div class="label" style="color:#2e7d32">💵 <?= current_locale()==='am' ? 'የተሰበሰበ ጥሬ ገንዘብ' : 'Cash Collected' ?></div>
            <div class="value" style="color:#2e7d32"><?= number_format((float)$monthRevenue['cash_collected'],0) ?> ETB</div>
        </div>
        <div class="kpi">
            <div class="label" style="color:#c62828">⏳ <?= current_locale()==='am' ? 'ገና ያልተሰበሰበ' : 'Still Outstanding' ?></div>
            <div class="value" style="color:#c62828"><?= number_format((float)$monthRevenue['outstanding'],0) ?> ETB</div>
        </div>
    </div>
</div>

<!-- ROW 5: Department Cost Summary (This Month) -->
<div class="card" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">🏭 <?= current_locale()==='am' ? 'የዚህ ወር የክፍሎች ወጪ / ገቢ' : "This Month's Dept. Cash Flow" ?></h3>
    </div>
    <table>
        <tr>
            <th><?= current_locale()==='am'?'ክፍል':'Unit' ?></th>
            <th><?= current_locale()==='am'?'ወጪ':'Outflow' ?></th>
            <th><?= current_locale()==='am'?'ገቢ':'Inflow' ?></th>
            <th><?= current_locale()==='am'?'ሒሳብ':'Net' ?></th>
            <th></th>
        </tr>
        <?php foreach (FINANCE_UNIT_TAGS as $unit):
            $label = t('section.'.$unit, ucwords(str_replace('_',' ',$unit)));
            $out = (float)($unitCosts[$unit]['outflow'] ?? 0);
            $in  = (float)($unitCosts[$unit]['inflow']  ?? 0);
            $net = $in - $out;
        ?>
        <tr>
            <td><strong><?= h($label) ?></strong></td>
            <td style="color:#c62828"><?= number_format($out,0) ?> ETB</td>
            <td style="color:#2e7d32"><?= number_format($in,0) ?> ETB</td>
            <td style="font-weight:700;color:<?= $net>=0?'#2e7d32':'#c62828' ?>"><?= ($net>=0?'+':'-').number_format(abs($net),0) ?> ETB</td>
            <td>
                <a href="/aleltu-eifms/modules/finance/ledger.php?unit=<?= urlencode($unit) ?>" style="font-size:12px">
                    <?= current_locale()==='am'?'ዝርዝር':'Detail' ?> →
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
