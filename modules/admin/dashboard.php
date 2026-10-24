<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);
require_once __DIR__ . '/../../includes/header.php';

// ── Financial Position ───────────────────────────────────────────────────────
$byMethod   = finance_balance_by_method();
$receivable = finance_receivables_summary();
$payable    = finance_payables_summary();
$inventory  = finance_inventory_value();
$totalMoney = ($byMethod['bank'] ?? 0) + ($byMethod['cash'] ?? 0) + ($byMethod['mobile'] ?? 0);

// ── Needs Attention ──────────────────────────────────────────────────────────
$unpaidDispatchCount = (int) db()->query("SELECT COUNT(*) FROM dist_dispatches WHERE payment_status != 'paid'")->fetchColumn();
$pendingPOApproval   = (int) db()->query("SELECT COUNT(*) FROM fin_purchase_orders WHERE status = 'pending_approval'")->fetchColumn();
$pendingMaterialReqs = (int) db()->query("SELECT COUNT(*) FROM dept_material_requests WHERE status IN ('pending','partially_issued')")->fetchColumn();
$pendingWhReceiving  = (int) db()->query("SELECT COUNT(*) FROM wh_store_receiving WHERE status = 'pending_receipt'")->fetchColumn();
$lowStockCount       = (int) db()->query(
    "SELECT COUNT(*) FROM wh_minimum_stock_rules r
     WHERE (SELECT COALESCE(SUM(b.quantity_on_hand),0) FROM wh_stock_batches b
            WHERE b.warehouse_id = r.warehouse_id AND b.stock_item_id = r.stock_item_id) < r.minimum_threshold"
)->fetchColumn();
$vaccinationsDue     = (int) db()->query("SELECT COUNT(*) FROM vet_vaccination_records WHERE status = 'scheduled' AND scheduled_date <= CURDATE()")->fetchColumn();
$pendingLeaves       = (int) db()->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

// ── Live Department Telemetry ────────────────────────────────────────────────
$cowsCount = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased')")->fetchColumn();
$todayMilk = (float) db()->query(
    "SELECT COALESCE(SUM(e.yield_liters),0) FROM dairy_milk_yield_entries e
     JOIN dairy_milking_sessions s ON s.id = e.milking_session_id WHERE s.session_date = CURDATE()"
)->fetchColumn();
$totalBirds  = (int) db()->query("SELECT COALESCE(SUM(initial_population - cumulative_mortality - cumulative_culled_sold + cumulative_transfers_in),0) FROM poultry_flocks WHERE status='active'")->fetchColumn();
$todayEggs   = (int) db()->query("SELECT COALESCE(SUM(whole_count),0) FROM poultry_egg_collections WHERE collection_date = CURDATE()")->fetchColumn();
$totalFish   = (int) db()->query("SELECT COALESCE(SUM(stocking_quantity - cumulative_mortality),0) FROM fish_stocks WHERE status='active'")->fetchColumn();
$totalStockItems = (int) db()->query("SELECT COUNT(*) FROM wh_stock_items")->fetchColumn();
$activeEmployees = (int) db()->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
$activeUsers = (int) db()->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();

// ── Workforce & Employee Oversight Metrics ──────────────────────────────────
$todayDate    = date('Y-m-d');
$todayPresent = (int) db()->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = '{$todayDate}' AND status = 'present'")->fetchColumn();
$todayAbsent  = (int) db()->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = '{$todayDate}' AND status = 'absent'")->fetchColumn();
$todayOnLeave = (int) db()->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND '{$todayDate}' BETWEEN start_date AND end_date")->fetchColumn();
$curEthYear   = eth_year_range();
$totalPayrollYtd = (float) db()->query("SELECT COALESCE(SUM(ps.net_pay + ps.allowances), 0) FROM fin_payslips ps JOIN fin_payroll_runs pr ON pr.id = ps.payroll_run_id WHERE pr.period_start >= '{$curEthYear['from']}'")->fetchColumn();

// ── Department Profitability Filter ──────────────────────────────────────────
$datePreset = $_GET['period'] ?? 'this_year';
$today = date('Y-m-d');
switch ($datePreset) {
    case 'today':
        $filterStart = $today;
        $filterEnd   = $today;
        break;
    case 'this_week':
        $filterStart = date('Y-m-d', strtotime('monday this week'));
        $filterEnd   = $today;
        break;
    case 'this_month':
        $filterStart = date('Y-m-01');
        $filterEnd   = $today;
        break;
    case 'all_time':
        $filterStart = '2024-01-01';
        $filterEnd   = $today;
        break;
    case 'custom':
        $filterStart = $_GET['start_date'] ?? $curEthYear['from'];
        $filterEnd   = $_GET['end_date'] ?? $today;
        break;
    case 'this_year':
    default:
        $datePreset  = 'this_year';
        $filterStart = $curEthYear['from'];
        $filterEnd   = $today;
        break;
}

$deptProf = finance_department_profitability($filterStart, $filterEnd);

// ── Audit Trail ─────────────────────────────────────────────────────────────
$recentAudits = audit_log_get(8);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px">
    <div>
        <div style="display:flex;align-items:center;gap:8px">
            <span style="background:#2e7d32;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">EXECUTIVE</span>
            <span style="font-size:12px;color:var(--text-muted)">Aleltu Agricultural Enterprise</span>
        </div>
        <h2 class="mt-0" style="margin:4px 0"><?= current_locale()==='am' ? 'የበላይ አመራር መቆጣጠሪያ ማዕከል' : 'Executive Control Center' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am' ? 'የኩባንያው አጠቃላይ የፋይናንስ ቁጥጥር፣ የላቀ አፈጻጸም ትንተና፣ የሰራተኞች አመራር እና የስርዓት ደህንነት' : 'Enterprise treasury control, strategic analytics, workforce oversight, and system security governance.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/admin/analytics.php" class="btn" style="background:#2e7d32;border-color:#2e7d32"><?= current_locale()==='am'?'የእርሻ አፈጻጸም ትንተና':'Performance Analytics' ?></a>
        <a href="<?= BASE_URL ?>/modules/finance/income_statement.php" class="btn secondary"><?= current_locale()==='am'?'ትርፍና ኪሳራ (P&L)':'Income Statement' ?></a>
        <a href="<?= BASE_URL ?>/modules/reports.php" class="btn secondary"><?= current_locale()==='am'?'ሪፖርቶች':'Reports' ?></a>
        <a href="<?= BASE_URL ?>/modules/admin/users.php" class="btn secondary"><?= current_locale()==='am'?'ተጠቃሚዎች':'Users' ?></a>
        <a href="<?= BASE_URL ?>/modules/admin/backup.php" class="btn secondary"><?= current_locale()==='am'?'ምትኬ (Backup)':'Backup' ?></a>
        <a href="<?= BASE_URL ?>/modules/audit_log.php" class="btn secondary"><?= current_locale()==='am'?'ኦዲት ሎግ':'Audit Log' ?></a>
    </div>
</div>

<!-- ═══ 1. ADVANCED EXECUTIVE ANALYTICS & STRATEGIC JUMPS ═══ -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:14px;margin-bottom:24px">
    <a href="<?= BASE_URL ?>/modules/admin/analytics.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #2e7d32;transition:transform .2s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <strong style="font-size:15px;display:block">📈 <?= current_locale()==='am'?'የእርሻ አፈጻጸም ጥልቅ ትንተና':'Farm Performance Analytics' ?></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'የትርፍ ህዳግ (Margin %)、በስራ ክፍል የተጣራ ትርፍና ወጪ':'Profit by unit, revenue vs receipts, margins' ?></div>
            </div>
            <span style="font-size:22px">📊</span>
        </div>
        <div style="margin-top:10px;font-size:12px;color:#2e7d32;font-weight:700">→ ሙሉ ትንታኔ ይመልከቱ</div>
    </a>

    <a href="<?= BASE_URL ?>/modules/finance/income_statement.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #1565c0;transition:transform .2s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <strong style="font-size:15px;display:block">📑 <?= current_locale()==='am'?'የትርፍና ኪሳራ መግለጫ (P&L)':'Income Statement (P&L)' ?></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'ጠቅላላ ገቢ፣ የማምረቻ ወጪ (COGS)፣ የተጣራ ትርፍ':'Revenues, COGS, operating costs, net margin' ?></div>
            </div>
            <span style="font-size:22px">💰</span>
        </div>
        <div style="margin-top:10px;font-size:12px;color:#1565c0;font-weight:700">→ የፋይናንስ መግለጫ ክፈት</div>
    </a>

    <a href="<?= BASE_URL ?>/modules/admin/gm_dashboard.php" class="card" style="text-decoration:none;color:inherit;padding:16px;border-left:4px solid #f57f17;transition:transform .2s">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <strong style="font-size:15px;display:block">🏢 <?= current_locale()==='am'?'የጠቅላላ ስራ አስኪያጅ እይታ':'GM Operations Overview' ?></strong>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= current_locale()==='am'?'የእርሻ ስራ አስኪያጁ የሚያየውን የኦፕሬሽን ዳሽቦርድ ይፈትሹ':'Live operations mirror of GM view' ?></div>
            </div>
            <span style="font-size:22px">👀</span>
        </div>
        <div style="margin-top:10px;font-size:12px;color:#f57f17;font-weight:700">→ የስራ አስኪያጅ ዳሽቦርድ</div>
    </a>
</div>

<!-- ═══ 2. WORKFORCE & EMPLOYEE CONTROL CENTER ═══ -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    👥 <?= current_locale()==='am'?'የሰራተኞችና የሰው ኃይል ቁጥጥር ማዕከል (Workforce Oversight)':'Workforce & Human Resources Oversight' ?>
</div>
<div class="kpi-row" style="margin-bottom:20px">
    <a href="<?= BASE_URL ?>/modules/employees/list.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #1565c0">
            <div class="label">👥 <?= current_locale()==='am'?'ጠቅላላ ንቁ ሰራተኞች':'Active Staff Members' ?></div>
            <div class="value" style="color:#1565c0"><?= $activeEmployees ?> <span style="font-size:12px;font-weight:normal;color:var(--text-muted)">ሰራተኞች</span></div>
            <div style="font-size:11px;color:#1565c0;margin-top:4px">→ ሙሉ ማህደር ይመልከቱ</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/modules/employees/attendance.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #2e7d32">
            <div class="label">🟢 <?= current_locale()==='am'?'ዛሬ የተገኙ (Present)':'Present Today' ?></div>
            <div class="value" style="color:#2e7d32"><?= $todayPresent ?> <span style="font-size:12px;font-weight:normal;color:var(--text-muted)">/ <?= $activeEmployees ?></span></div>
            <div style="font-size:11px;color:#2e7d32;margin-top:4px">→ የመገኘት ሮስተር ክፈት</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/modules/employees/leave.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #e65100">
            <div class="label">🏖️ <?= current_locale()==='am'?'በፈቃድ / የቀሩ':'On Leave / Absent' ?></div>
            <div class="value" style="color:#e65100">
                <?= $todayOnLeave ?> <span style="font-size:12px;font-weight:normal;color:var(--text-muted)">ፈቃድ | <?= $todayAbsent ?> ቀሪ</span>
            </div>
            <div style="font-size:11px;color:#e65100;margin-top:4px">→ የፈቃድ ጥያቄዎች (<?= $pendingLeaves ?> በመጠባበቅ ላይ)</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/modules/finance/payroll.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #7b1fa2">
            <div class="label">💳 <?= current_locale()==='am'?'የዓመቱ የደሞዝ ወጪ':'YTD Total Payroll' ?></div>
            <div class="value" style="color:#7b1fa2"><?= number_format($totalPayrollYtd, 0) ?> ETB</div>
            <div style="font-size:11px;color:#7b1fa2;margin-top:4px">→ የደመወዝ ክፍያ ማህደር</div>
        </div>
    </a>
</div>

<!-- ═══ DEPARTMENT PROFITABILITY & UNIT ECONOMICS ═══ -->
<div class="card" style="box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:24px;border-top:4px solid var(--primary);padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:12px">
        <div>
            <h3 style="margin:0 0 4px 0;display:flex;align-items:center;gap:8px">
                💰 <?= current_locale() === 'am' ? 'የስራ ክፍሎች የገቢ፣ የወጪ እና የትርፋማነት ማዕከል' : 'Department Profitability & Unit Economics' ?>
            </h3>
            <small class="text-muted">
                <?= current_locale() === 'am' 
                    ? 'በተመረጠው የቀን ገደብ ውስጥ የእያንዳንዱ ክፍል (ወተት፣ ዶሮ፣ አሳ፣ መኖ፣ መጋዘን፣ ስርጭት) የወጣ ወጪ፣ የተገኘ ገቢ እና የተጣራ ትርፍ።'
                    : 'Analyze revenue, operating expenses, and net profit per operating department with customizable date filters.' ?>
            </small>
        </div>

        <!-- Date Range Filter Buttons -->
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <a href="?period=today" class="btn <?= $datePreset === 'today' ? '' : 'secondary' ?>" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'ዛሬ' : 'Today' ?></a>
            <a href="?period=this_week" class="btn <?= $datePreset === 'this_week' ? '' : 'secondary' ?>" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'በዚህ ሳምንት' : 'This Week' ?></a>
            <a href="?period=this_month" class="btn <?= $datePreset === 'this_month' ? '' : 'secondary' ?>" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'በዚህ ወር' : 'This Month' ?></a>
            <a href="?period=this_year" class="btn <?= $datePreset === 'this_year' ? '' : 'secondary' ?>" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'በዚህ ዓመት' : 'This Year' ?></a>
            <a href="?period=all_time" class="btn <?= $datePreset === 'all_time' ? '' : 'secondary' ?>" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'ከመጀመሪያው ጀምሮ' : 'All Time' ?></a>
        </div>
    </div>

    <!-- Active Range Badge -->
    <div style="background:var(--hover);padding:8px 14px;border-radius:6px;font-size:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <span><strong><?= current_locale() === 'am' ? 'ወቅት፦' : 'Period:' ?></strong> <?= eth_date($filterStart) ?> &mdash; <?= eth_date($filterEnd) ?> (<?= $filterStart ?> to <?= $filterEnd ?>)</span>
        <div style="display:flex;gap:12px;font-weight:700">
            <span style="color:#2e7d32"><?= current_locale() === 'am' ? 'ጠቅላላ ገቢ፦' : 'Total Revenue:' ?> <?= number_format($deptProf['total_revenue'], 2) ?> ETB</span>
            <span style="color:#c62828"><?= current_locale() === 'am' ? 'ጠቅላላ ወጪ፦' : 'Total Expense:' ?> <?= number_format($deptProf['total_expense'], 2) ?> ETB</span>
            <span style="color:<?= $deptProf['total_net_profit'] >= 0 ? '#1565c0' : '#c62828' ?>"><?= current_locale() === 'am' ? 'አጠቃላይ ትርፍ፦' : 'Net Profit:' ?> <?= number_format($deptProf['total_net_profit'], 2) ?> ETB</span>
        </div>
    </div>

    <!-- Department Cards Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:14px">
        <?php foreach ($deptProf['departments'] as $dKey => $d): 
            $isProf = ($d['net_profit'] >= 0);
            $badgeBg = $isProf ? '#e8f5e9' : '#ffebee';
            $badgeColor = $isProf ? '#2e7d32' : '#c62828';
        ?>
            <div onclick="openDeptModal('<?= $dKey ?>')" style="border:1px solid var(--border);border-top:3px solid <?= $d['color'] ?>;border-radius:8px;padding:12px;background:var(--card);box-shadow:0 1px 4px rgba(0,0,0,0.03);cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;flex-direction:column;justify-content:space-between" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 10px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.03)'">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span style="font-weight:800;font-size:13.5px;color:var(--text)">
                            <?= current_locale() === 'am' ? $d['name_am'] : $d['name_en'] ?>
                        </span>
                        <span class="badge" style="background:<?= $badgeBg ?>;color:<?= $badgeColor ?>;font-size:11px;font-weight:700">
                            <?= $isProf ? (current_locale() === 'am' ? 'ትርፍ' : 'Profit') : (current_locale() === 'am' ? 'ኪሳራ' : 'Loss') ?>
                        </span>
                    </div>

                    <div style="font-size:12px;display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
                        <div style="display:flex;justify-content:space-between">
                            <span class="text-muted"><?= current_locale() === 'am' ? 'ገቢ (Revenue):' : 'Revenue:' ?></span>
                            <strong style="color:#2e7d32"><?= number_format($d['revenue'], 2) ?> ETB</strong>
                        </div>
                        <?php if ($d['revenue'] > 0 && !empty($d['cash_collected']) && $d['cash_collected'] < $d['revenue']): ?>
                            <div style="font-size:10.5px;color:var(--text-muted);display:flex;justify-content:space-between;padding-left:8px;background:var(--hover);padding:3px 6px;border-radius:4px">
                                <span><?= current_locale() === 'am' ? 'ጥሬ ገንዘብ:' : 'Cash:' ?> <?= number_format($d['cash_collected'], 2) ?></span>
                                <span style="color:#e65100;font-weight:700" title="ምርቱ ተረክቦ ገና ያልተከፈለ ቀሪ"><?= current_locale() === 'am' ? 'በብድር (ተሰብሳቢ):' : 'Receivables:' ?> <?= number_format($d['revenue'] - $d['cash_collected'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between">
                            <span class="text-muted"><?= current_locale() === 'am' ? 'ወጪ (Expense):' : 'Expense:' ?></span>
                            <strong style="color:#c62828"><?= number_format($d['expense'], 2) ?> ETB</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;border-top:1px dashed var(--border);padding-top:4px">
                            <span style="font-weight:700"><?= current_locale() === 'am' ? 'የተጣራ ትርፍ:' : 'Net Profit:' ?></span>
                            <strong style="color:<?= $isProf ? '#2e7d32' : '#c62828' ?>;font-size:13px">
                                <?= ($d['net_profit'] > 0 ? '+' : '') . number_format($d['net_profit'], 2) ?> ETB
                            </strong>
                        </div>
                    </div>

                    <?php if ($d['revenue'] > 0): ?>
                        <div style="font-size:11px;color:var(--text-muted);text-align:right">
                            <?= current_locale() === 'am' ? 'የትርፍ ምጣኔ (Margin):' : 'Margin:' ?> <strong><?= $d['margin'] ?>%</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" onclick="event.stopPropagation();openDeptModal('<?= $dKey ?>')" class="btn secondary" style="width:100%;margin-top:10px;padding:5px 8px;font-size:11.5px;display:flex;align-items:center;justify-content:center;gap:4px">
                    <span><?= current_locale() === 'am' ? 'የገንዘቡን ዝርዝር ይመልከቱ' : 'View Details' ?></span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($deptProf['total_revenue'] == 0 && $deptProf['total_expense'] == 0): ?>
        <div style="margin-top:14px;padding:12px 16px;background:#e3f2fd;color:#0d47a1;border:1px solid #bbdefb;border-radius:6px;font-size:12.5px;display:flex;align-items:center;gap:10px">
            <div>
                <strong>በተመረጠው ወቅት የተመዘገበ የገንዘብ እንቅስቃሴ የለም።</strong> 
                የእርሻውን እንቅስቃሴዎች ለማየት ከላይ ያሉትን <strong>«በዚህ ዓመት»</strong> ወይም <strong>«ከመጀመሪያው ጀምሮ»</strong> ማጣሪያዎች ይጫኑ።
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ═══ FINANCIAL POSITION ═══ -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    💰 <?= current_locale()==='am'?'የኩባንያ ፋይናንስ አቋም':'Company Financial Position' ?>
</div>
<div class="kpi-row" style="margin-bottom:8px">
    <?php foreach (($byMethod['_accounts'] ?? []) as $acc): ?>
    <a href="<?= BASE_URL ?>/modules/finance/ledger.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer">
            <div class="label"><?= $acc['type']==='bank'?'🏦':($acc['type']==='mobile'?'📱':'💵') ?> <?= h($acc['name']) ?></div>
            <div class="value" style="color:<?= $acc['balance']>=0?'var(--primary)':'#b3261e' ?>"><?= number_format($acc['balance'],0) ?> ETB</div>
        </div>
    </a>
    <?php endforeach; ?>
    <div class="kpi" style="border:2px solid var(--primary)">
        <div class="label">💰 <?= current_locale()==='am'?'ጠቅላላ':'Total Available' ?></div>
        <div class="value"><strong style="color:<?= $totalMoney>=0?'var(--primary)':'#b3261e' ?>"><?= number_format($totalMoney,0) ?> ETB</strong></div>
    </div>
</div>
<div class="kpi-row" style="margin-bottom:20px">
    <a href="<?= BASE_URL ?>/modules/finance/receivables.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #2e7d32">
            <div class="label">👥 <?= current_locale()==='am'?'ተሰብሳቢ':'Receivables' ?></div>
            <div class="value" style="color:#2e7d32"><?= number_format($receivable['total_receivable'],0) ?> ETB</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/modules/finance/payables.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid <?= $payable['total_payable']>0?'#c62828':'var(--border)' ?>">
            <div class="label">🏪 <?= current_locale()==='am'?'ተከፋይ':'Payables' ?></div>
            <div class="value" style="color:<?= $payable['total_payable']>0?'#c62828':'inherit' ?>"><?= number_format($payable['total_payable'],0) ?> ETB</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/modules/finance/inventory_value.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #1565c0">
            <div class="label">📦 <?= current_locale()==='am'?'የዕቃ ዋጋ':'Inventory Value' ?></div>
            <div class="value" style="color:#1565c0"><?= number_format($inventory['total_inventory_value'],0) ?> ETB</div>
        </div>
    </a>
</div>

<!-- ═══ NEEDS ATTENTION ═══ -->
<?php
$alerts = [];
if ($unpaidDispatchCount > 0) $alerts[] = ['icon'=>'👥','color'=>'#c62828','msg'=> $unpaidDispatchCount.' '.(current_locale()==='am'?'ያልተሰበሰቡ ሽያጭ ክፍያዎች':'unpaid dispatch(es)') ,'url'=>BASE_URL . '/modules/finance/receivables.php'];
if ($pendingPOApproval > 0)   $alerts[] = ['icon'=>'🛒','color'=>'#e65100','msg'=> $pendingPOApproval.' '.(current_locale()==='am'?'PO ፈቃድ ይጠብቃሉ':'purchase order(s) awaiting approval'),'url'=>BASE_URL . '/modules/finance/purchasing.php'];
if ($pendingMaterialReqs > 0) $alerts[] = ['icon'=>'📦','color'=>'#e65100','msg'=> $pendingMaterialReqs.' '.(current_locale()==='am'?'የዕቃ ጥያቄ ይጠብቃሉ':'material request(s) pending'),'url'=>BASE_URL . '/modules/warehouse/requisitions.php'];
if ($pendingWhReceiving > 0)  $alerts[] = ['icon'=>'📥','color'=>'#1565c0','msg'=> $pendingWhReceiving.' '.(current_locale()==='am'?'ያልተቀበሉ ዕቃዎች':'incoming goods awaiting receipt'),'url'=>BASE_URL . '/modules/warehouse/receiving.php'];
if ($lowStockCount > 0)       $alerts[] = ['icon'=>'⚠️','color'=>'#c62828','msg'=> $lowStockCount.' '.(current_locale()==='am'?'ዕቃዎች ደረጃ ዝቅ ብሏል':'item(s) below minimum stock'),'url'=>BASE_URL . '/modules/warehouse/stock.php'];
if ($vaccinationsDue > 0)     $alerts[] = ['icon'=>'💉','color'=>'#c62828','msg'=> $vaccinationsDue.' '.(current_locale()==='am'?'ክትባቶች ዛሬ ወይም ዘጠኙ':'vaccination(s) due/overdue'),'url'=>BASE_URL . '/modules/veterinary/vaccinations.php'];
if ($pendingLeaves > 0)       $alerts[] = ['icon'=>'🏖️','color'=>'#795548','msg'=> $pendingLeaves.' '.(current_locale()==='am'?'የፈቃድ ጥያቄዎች':'leave request(s) pending'),'url'=>BASE_URL . '/modules/employees/leave.php'];
?>
<?php if (!empty($alerts)): ?>
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    🚨 <?= current_locale()==='am'?'ትኩረት የሚሹ ጉዳዮች':'Needs Attention' ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;margin-bottom:20px">
    <?php foreach ($alerts as $a): ?>
    <a href="<?= h($a['url']) ?>" style="display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-left:4px solid <?= $a['color'] ?>;border-radius:8px;padding:10px 14px;text-decoration:none;color:inherit">
        <span style="font-size:20px"><?= $a['icon'] ?></span>
        <span style="font-weight:600;color:<?= $a['color'] ?>"><?= h($a['msg']) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ DEPARTMENT TELEMETRY ═══ -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    🏢 <?= current_locale()==='am'?'የቀጥታ የሥራ ክፍሎች ሁኔታ':'Live Department Status' ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:20px">

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🐄 <?= h(t('section.dairy','Dairy')) ?></strong>
            <a href="<?= BASE_URL ?>/modules/dairy/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ንቁ ላሞች:':'Active Cows:' ?> <strong><?= $cowsCount ?></strong></div>
            <div><?= current_locale()==='am'?"የዛሬ ወተት:":"Today's Milk:" ?> <strong><?= number_format($todayMilk,1) ?> L</strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🐓 <?= h(t('section.poultry','Poultry')) ?></strong>
            <a href="<?= BASE_URL ?>/modules/poultry/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ዶሮዎች:':'Live Birds:' ?> <strong><?= number_format($totalBirds) ?></strong></div>
            <div><?= current_locale()==='am'?"የዛሬ እንቁላል:":"Today's Eggs:" ?> <strong><?= number_format($todayEggs) ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🐟 <?= h(t('section.fish_farm','Fish')) ?></strong>
            <a href="<?= BASE_URL ?>/modules/fish/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ህያው ዓሳ:':'Live Fish:' ?> <strong><?= number_format($totalFish) ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🏪 <?= h(t('section.warehouse','Warehouse')) ?></strong>
            <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ዕቃ ዓይነቶች:':'Stock Items:' ?> <strong><?= $totalStockItems ?></strong></div>
            <div><?= current_locale()==='am'?'ደረጃ ዝቅ:':'Low Stock:' ?> <strong class="<?= $lowStockCount>0?'badge sold':'' ?>"><?= $lowStockCount ?></strong></div>
            <div><?= current_locale()==='am'?'ሊቀበሉ:':'Pending Receipts:' ?> <strong class="<?= $pendingWhReceiving>0?'badge dry':'' ?>"><?= $pendingWhReceiving ?></strong></div>
            <div><?= current_locale()==='am'?'ጥያቄዎች:':'Dept Reqs:' ?> <strong class="<?= $pendingMaterialReqs>0?'badge dry':'' ?>"><?= $pendingMaterialReqs ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>👷 <?= h(t('section.employees','HR')) ?></strong>
            <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ንቁ ሠራተኞች:':'Active Staff:' ?> <strong><?= $activeEmployees ?></strong></div>
            <div><?= current_locale()==='am'?'ፈቃድ ጥያቄ:':'Pending Leaves:' ?> <strong class="<?= $pendingLeaves>0?'badge dry':'' ?>"><?= $pendingLeaves ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🔐 <?= current_locale()==='am'?'ሲስተም ተጠቃሚዎች':'System / Users' ?></strong>
            <a href="<?= BASE_URL ?>/modules/admin/users.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ንቁ ተጠቃሚዎች:':'Active Users:' ?> <strong><?= $activeUsers ?></strong></div>
        </div>
    </div>

</div>

<!-- ═══ AUDIT TRAIL ═══ -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">🛡️ <?= current_locale()==='am'?'የቅርብ ጊዜ ደህንነት ክትትል':'Live Security & Audit Trail' ?></h3>
        <a href="<?= BASE_URL ?>/modules/audit_log.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all','View Full Log')) ?> →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= current_locale()==='am'?'ሰዓት':'Time' ?></th>
                <th><?= current_locale()==='am'?'ተጠቃሚ':'User' ?></th>
                <th><?= current_locale()==='am'?'ሞጁል':'Module' ?></th>
                <th><?= current_locale()==='am'?'ድርጊት':'Action' ?></th>
                <th><?= current_locale()==='am'?'ዝርዝር':'Details' ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentAudits as $log): ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap"><?= h(eth_datetime($log['created_at'])) ?></td>
                <td><strong><?= h($log['user_name'] ?: (current_locale()==='am'?'ሲስተም':'System')) ?></strong></td>
                <td><span class="badge"><?= h(ucwords(str_replace('_',' ',$log['module']))) ?></span></td>
                <td><code><?= h($log['action']) ?></code></td>
                <td><?= h(mb_substr($log['details'] ?? '—', 0, 60)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentAudits)): ?>
            <tr><td colspan="5" class="text-muted"><?= h(t('common.no_records','No audit records yet.')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ═══ DEPARTMENT DETAIL BREAKDOWN MODAL ═══ -->
<div id="deptModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;backdrop-filter:blur(3px);overflow-y:auto;padding:20px 10px">
    <div style="max-width:920px;margin:20px auto;background:var(--card);border-radius:12px;box-shadow:0 12px 35px rgba(0,0,0,0.25);border:1px solid var(--border);overflow:hidden;animation:modalFadeIn .2s ease-out">
        <!-- Modal Header -->
        <div style="padding:16px 20px;background:var(--hover);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div>
                <h3 id="modalTitle" style="margin:0;font-size:17px">የስራ ክፍል ዝርዝር</h3>
                <small id="modalSubtitle" style="color:var(--text-muted);font-size:12px">የገቢና ወጪ ምንጮች ጥልቅ ትንተና</small>
            </div>
            <button type="button" onclick="closeDeptModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);line-height:1;padding:4px 8px">&times;</button>
        </div>

        <div style="padding:20px">
            <!-- 3 Summary KPI Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-bottom:18px">
                <div style="background:var(--hover);padding:12px;border-radius:8px;border-left:4px solid #2e7d32">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">ጠቅላላ ገቢ (Gross Revenue)</div>
                    <div id="modalGrossRevenue" style="font-size:18px;font-weight:800;color:#2e7d32;margin-top:2px">0.00 ETB</div>
                    <div id="modalRevenueSplit" style="font-size:11px;color:var(--text-muted);margin-top:4px">ጥሬ ገንዘብ: 0.00 | በብድር: 0.00</div>
                </div>

                <div style="background:var(--hover);padding:12px;border-radius:8px;border-left:4px solid #c62828">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">ጠቅላላ ወጪ (Total Expenses)</div>
                    <div id="modalTotalExpense" style="font-size:18px;font-weight:800;color:#c62828;margin-top:2px">0.00 ETB</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px">የኦፕሬሽንና ቀጥተኛ ወጪዎች ድምር</div>
                </div>

                <div style="background:var(--hover);padding:12px;border-radius:8px;border-left:4px solid #1565c0">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">የተጣራ ትርፍ / ኪሳራ (Net P&L)</div>
                    <div id="modalNetProfit" style="font-size:18px;font-weight:800;margin-top:2px">0.00 ETB</div>
                    <div id="modalMargin" style="font-size:11px;color:var(--text-muted);margin-top:4px">የትርፍ ምጣኔ: 0%</div>
                </div>
            </div>

            <!-- Credit Educational Notice -->
            <div style="margin-bottom:20px;padding:12px 14px;background:#f8fafc;border:1px solid #cbd5e1;border-left:4px solid #0284c7;border-radius:6px;font-size:12px;color:#334155">
                <strong>«በብድር (Credit)» ማለት ምን ማለት ነው?</strong><br>
                ምርቱ ለደንበኛው ወይም ለሽያጭ ማዕከሉ ተረክቦ ነገር ግን ደንበኛው ገንዘቡን ገና ያልከፈለበት <strong>ቀሪ ተሰብሳቢ ሂሳብ (Accounts Receivable)</strong> ማለት ነው። ለምሳሌ፡ ለሆቴሎች ወይም ለቋሚ ደንበኞች በወር መጨረሻ እንዲከፍሉ የተሰጠ ሽያጭ ነው። ደንበኛው ገንዘቡን ሲከፍል በቀጥታ ወደ ጥሬ ገንዘብ ገቢነት ይለወጣል።
            </div>

            <!-- Tab Navigation -->
            <div style="display:flex;gap:8px;border-bottom:2px solid var(--border);margin-bottom:14px">
                <button type="button" id="tabBtnRev" onclick="switchModalTab('rev')" style="background:none;border:none;border-bottom:3px solid #2e7d32;padding:8px 16px;font-weight:700;cursor:pointer;color:#2e7d32;font-size:13px">
                    የገቢ ምንጮችና ሽያጮች (<span id="modalRevCount">0</span>)
                </button>
                <button type="button" id="tabBtnExp" onclick="switchModalTab('exp')" style="background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px;font-weight:700;cursor:pointer;color:var(--text-muted);font-size:13px">
                    የወጪ ዝርዝሮች (<span id="modalExpCount">0</span>)
                </button>
            </div>

            <!-- Section 1: Revenue Table -->
            <div id="modalSectionRev">
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <thead>
                            <tr style="background:var(--hover);text-align:left">
                                <th style="padding:8px">ቀን</th>
                                <th style="padding:8px">ምርት / ዕቃ</th>
                                <th style="padding:8px">መጠን</th>
                                <th style="padding:8px">ገዢ / መዳረሻ ሱቅ</th>
                                <th style="padding:8px;text-align:right">ጠቅላላ ዋጋ</th>
                                <th style="padding:8px;text-align:right;color:#2e7d32">ጥሬ ገንዘብ (ተከፈለ)</th>
                                <th style="padding:8px;text-align:right;color:#e65100">በብድር (ቀሪ)</th>
                                <th style="padding:8px">የክፍያ ሁኔታ</th>
                            </tr>
                        </thead>
                        <tbody id="modalRevTbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <div id="modalRevEmpty" style="display:none;padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
                    በዚህ ወቅት የተመዘገበ የሽያጭ ወይም የገቢ እንቅስቃሴ የለም።
                </div>
            </div>

            <!-- Section 2: Expense Table -->
            <div id="modalSectionExp" style="display:none">
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <thead>
                            <tr style="background:var(--hover);text-align:left">
                                <th style="padding:8px">ቀን</th>
                                <th style="padding:8px">የወጪ ምድብ</th>
                                <th style="padding:8px">የማውጫ ዓይነት</th>
                                <th style="padding:8px">ተቀባይ / አካል</th>
                                <th style="padding:8px">የከፈለው አካውንት</th>
                                <th style="padding:8px;text-align:right;color:#c62828">የወጣው ብር (ETB)</th>
                                <th style="padding:8px">ሁኔታ</th>
                            </tr>
                        </thead>
                        <tbody id="modalExpTbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <div id="modalExpEmpty" style="display:none;padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
                    በዚህ ወቅት የተመዘገበ የወጪ እንቅስቃሴ የለም።
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="padding:12px 20px;background:var(--hover);border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <a id="modalFinanceLink" href="<?= BASE_URL ?>/modules/finance/expenses.php" class="btn secondary" style="padding:6px 12px;font-size:12px">
                📄 በፋይናንስ ወጪ መዝገብ ውስጥ ሙሉውን ተመልከት →
            </a>
            <button type="button" onclick="closeDeptModal()" class="btn secondary" style="padding:6px 16px">ዝጋ (Close)</button>
        </div>
    </div>
</div>

<script>
const deptData = <?= json_encode($deptProf['departments'], JSON_UNESCAPED_UNICODE) ?>;

function openDeptModal(deptKey) {
    const d = deptData[deptKey];
    if (!d) return;

    document.getElementById('modalTitle').innerText = (d.name_am || d.name_en) + ' — ዝርዝር የገንዘብ እንቅስቃሴ';
    document.getElementById('modalSubtitle').innerText = 'የወቅቱ የሽያጭ ደረሰኞች፣ የወጪ ምክንያቶች እና የክፍያ ሁኔታዎች ትንተና';

    const rev = Number(d.revenue || 0);
    const cash = Number(d.cash_collected || 0);
    const credit = Number(d.credit_unpaid || Math.max(0, rev - cash));
    const exp = Number(d.expense || 0);
    const net = Number(d.net_profit || (rev - exp));
    const margin = Number(d.margin || 0);

    document.getElementById('modalGrossRevenue').innerText = rev.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB';
    document.getElementById('modalRevenueSplit').innerHTML = '<strong>ጥሬ ገንዘብ:</strong> ' + cash.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB | <span style="color:#e65100"><strong>በብድር:</strong> ' + credit.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB</span>';

    document.getElementById('modalTotalExpense').innerText = exp.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB';
    
    const netEl = document.getElementById('modalNetProfit');
    netEl.innerText = (net > 0 ? '+' : '') + net.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB';
    netEl.style.color = (net >= 0) ? '#2e7d32' : '#c62828';

    document.getElementById('modalMargin').innerText = 'የትርፍ ምጣኔ (Margin): ' + margin + '%';

    // Populate Revenues
    const revRows = (d.breakdown && d.breakdown.revenues) ? d.breakdown.revenues : [];
    document.getElementById('modalRevCount').innerText = revRows.length;
    const revTbody = document.getElementById('modalRevTbody');
    revTbody.innerHTML = '';
    if (revRows.length === 0) {
        document.getElementById('modalRevEmpty').style.display = 'block';
    } else {
        document.getElementById('modalRevEmpty').style.display = 'none';
        revRows.forEach(r => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid var(--border)';
            let statusBadge = '<span class="badge" style="background:#e8f5e9;color:#2e7d32">ሙሉ ተከፍሏል</span>';
            if (r.payment_status === 'partial') {
                statusBadge = '<span class="badge" style="background:#fff3e0;color:#e65100">በከፊል ተከፍሏል</span>';
            } else if (r.payment_status === 'unpaid') {
                statusBadge = '<span class="badge" style="background:#ffebee;color:#c62828">ያልተከፈለ (በብድር)</span>';
            }

            tr.innerHTML = `
                <td style="padding:8px;white-space:nowrap">${r.date || '—'}</td>
                <td style="padding:8px"><strong>${r.item || '—'}</strong></td>
                <td style="padding:8px">${r.quantity || '—'}</td>
                <td style="padding:8px;max-width:200px">${r.destination || '—'}</td>
                <td style="padding:8px;text-align:right"><strong>${Number(r.total_amount).toLocaleString(undefined, {minimumFractionDigits:2})}</strong></td>
                <td style="padding:8px;text-align:right;color:#2e7d32">${Number(r.cash_paid).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                <td style="padding:8px;text-align:right;color:#e65100;font-weight:${r.credit_unpaid > 0 ? '700' : 'normal'}">${Number(r.credit_unpaid).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                <td style="padding:8px">${statusBadge} ${r.notes ? '<div style="font-size:10.5px;color:var(--text-muted);margin-top:2px">'+r.notes+'</div>' : ''}</td>
            `;
            revTbody.appendChild(tr);
        });
    }

    // Set Link to Finance Expenses page for this unit
    const finLink = document.getElementById('modalFinanceLink');
    if (finLink) {
        finLink.href = BASE_URL . '/modules/finance/expenses.php?unit=' + encodeURIComponent(deptKey);
        finLink.innerText = 'በፋይናንስ ወጪ ገጽ ላይ «' + (d.name_am || d.name_en) + '» ዝርዝር ክፈት →';
    }

    // Populate Expenses
    const expRows = (d.breakdown && d.breakdown.expenses) ? d.breakdown.expenses : [];
    document.getElementById('modalExpCount').innerText = expRows.length;
    const expTbody = document.getElementById('modalExpTbody');
    expTbody.innerHTML = '';
    if (expRows.length === 0) {
        document.getElementById('modalExpEmpty').style.display = 'block';
    } else {
        document.getElementById('modalExpEmpty').style.display = 'none';
        const typeLabels = {
            'operating_expense': 'የስራ ማስኬጃ',
            'holiday_gift': 'የበዓል ስጦታ',
            'admin_protocol': 'አስተዳደር ፕሮቶኮል',
            'food_withdrawal': 'ምግብና መስተንግዶ',
            'owner_drawing': 'የባለቤት ማውጣት',
            'cash_withdrawal': 'ጥሬ ገንዘብ ማውጣት'
        };

        expRows.forEach(e => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid var(--border)';
            const wType = typeLabels[e.withdrawal_type] || e.withdrawal_type || 'መደበኛ ወጪ';
            tr.innerHTML = `
                <td style="padding:8px;white-space:nowrap">${e.date || '—'}</td>
                <td style="padding:8px"><strong>${e.category || '—'}</strong><div style="font-size:11px;color:var(--text-muted)">${e.description || ''}</div></td>
                <td style="padding:8px"><span class="badge" style="font-size:11px">${wType}</span></td>
                <td style="padding:8px">${e.recipient || '—'}</td>
                <td style="padding:8px;color:var(--text-muted);font-size:11.5px">${e.bank_name || 'ዋና ካዝና'}</td>
                <td style="padding:8px;text-align:right;color:#c62828;font-weight:700">${Number(e.amount).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                <td style="padding:8px"><span class="badge" style="background:#e8f5e9;color:#2e7d32">${e.status || 'ጸድቋል'}</span></td>
            `;
            expTbody.appendChild(tr);
        });
    }

    // Reset to Revenue Tab
    switchModalTab('rev');

    document.getElementById('deptModal').style.display = 'block';
}

function closeDeptModal() {
    document.getElementById('deptModal').style.display = 'none';
}

function switchModalTab(tab) {
    const tabRev = document.getElementById('modalSectionRev');
    const tabExp = document.getElementById('modalSectionExp');
    const btnRev = document.getElementById('tabBtnRev');
    const btnExp = document.getElementById('tabBtnExp');

    if (tab === 'rev') {
        tabRev.style.display = 'block';
        tabExp.style.display = 'none';
        btnRev.style.borderBottomColor = '#2e7d32';
        btnRev.style.color = '#2e7d32';
        btnExp.style.borderBottomColor = 'transparent';
        btnExp.style.color = 'var(--text-muted)';
    } else {
        tabRev.style.display = 'none';
        tabExp.style.display = 'block';
        btnExp.style.borderBottomColor = '#c62828';
        btnExp.style.color = '#c62828';
        btnRev.style.borderBottomColor = 'transparent';
        btnRev.style.color = 'var(--text-muted)';
    }
}

// Close on backdrop click or ESC
window.addEventListener('click', function(e) {
    const modal = document.getElementById('deptModal');
    if (e.target === modal) closeDeptModal();
});
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeptModal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
