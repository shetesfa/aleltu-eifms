<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager']);
require_once __DIR__ . '/../../includes/header.php';

// Operational KPIs across all units
$cowsCount = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased')")->fetchColumn();
$todayMilk = (float) db()->query(
    "SELECT COALESCE(SUM(e.yield_liters),0) FROM dairy_milk_yield_entries e
     JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
     WHERE s.session_date = CURDATE()"
)->fetchColumn();
$sickCows = (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE is_sick = 1")->fetchColumn();

$activeFlocks = (int) db()->query("SELECT COUNT(*) FROM poultry_flocks WHERE status = 'active'")->fetchColumn();
$totalBirds = (int) db()->query("SELECT COALESCE(SUM(initial_population - cumulative_mortality - cumulative_culled_sold + cumulative_transfers_in), 0) FROM poultry_flocks WHERE status = 'active'")->fetchColumn();
$todayEggs = (int) db()->query("SELECT COALESCE(SUM(whole_count), 0) FROM poultry_egg_collections WHERE collection_date = CURDATE()")->fetchColumn();

$fishPonds = (int) db()->query("SELECT COUNT(*) FROM fish_ponds WHERE status = 'active'")->fetchColumn();
$totalFish = (int) db()->query("SELECT COALESCE(SUM(stocking_quantity - cumulative_mortality), 0) FROM fish_stocks WHERE status = 'active'")->fetchColumn();

$totalStockItems = (int) db()->query("SELECT COUNT(*) FROM wh_stock_items")->fetchColumn();
$pendingWhReceiving = (int) db()->query("SELECT COUNT(*) FROM wh_store_receiving WHERE status = 'pending_receipt'")->fetchColumn();
$pendingMaterialReqs = (int) db()->query("SELECT COUNT(*) FROM dept_material_requests WHERE status IN ('pending', 'partially_issued')")->fetchColumn();

$cash = finance_cash_position();
$pendingPOs = (int) db()->query("SELECT COUNT(*) FROM fin_purchase_orders WHERE status = 'pending_approval'")->fetchColumn();
$activeEmployees = (int) db()->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$todayPresent = (int) db()->query("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = CURDATE() AND status = 'present'")->fetchColumn();
$pendingLeaves = (int) db()->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

// Pending Action Items for General Manager
$pendingItems = [];
if ($pendingPOs > 0) {
    $pendingItems[] = ['label' => "{$pendingPOs} Purchase Orders pending approval", 'url' => BASE_URL . '/modules/finance/purchasing.php', 'type' => 'finance'];
}
if ($pendingLeaves > 0) {
    $pendingItems[] = ['label' => "{$pendingLeaves} Staff leave requests awaiting decision", 'url' => BASE_URL . '/modules/employees/leave.php', 'type' => 'hr'];
}
if ($pendingMaterialReqs > 0) {
    $pendingItems[] = ['label' => "{$pendingMaterialReqs} Department material requests awaiting fulfillment", 'url' => BASE_URL . '/modules/warehouse/requisitions.php', 'type' => 'warehouse'];
}
if ($pendingWhReceiving > 0) {
    $pendingItems[] = ['label' => "{$pendingWhReceiving} Incoming shipments awaiting warehouse receipt verification", 'url' => BASE_URL . '/modules/warehouse/receiving.php', 'type' => 'warehouse'];
}

// Date filter for Department Profitability
$datePreset = $_GET['period'] ?? 'this_year';
$today = date('Y-m-d');
$ethYearR = eth_year_range();
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
        $filterStart = $_GET['start_date'] ?? $ethYearR['from'];
        $filterEnd   = $_GET['end_date'] ?? $today;
        break;
    case 'this_year':
    default:
        $datePreset  = 'this_year';
        $filterStart = $ethYearR['from'];
        $filterEnd   = $today;
        break;
}

$deptProf = finance_department_profitability($filterStart, $filterEnd);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px"><?= h(t('nav.gm_overview', 'General Manager Operations Dashboard')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የድርጅቱ ከፍተኛ የአመራር አፈጻጸም፣ የመምሪያዎች ምርት እና ውሳኔ የሚጠብቁ የስራ ሂደቶች' : 'High-level enterprise operational performance, department yields, and pending managerial decisions.' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/reports.php" class="btn"><?= h(t('nav.reports', 'Company Reports')) ?></a>
        <a href="<?= BASE_URL ?>/modules/module_reports.php" class="btn secondary"><?= h(t('nav.module_reports', 'Department Reports')) ?></a>
        <a href="<?= BASE_URL ?>/modules/finance/purchasing.php" class="btn secondary"><?= h(t('nav.purchasing', 'Purchasing & POs')) ?></a>
    </div>
</div>

<!-- Critical Action Items Banner -->
<?php if (!empty($pendingItems)): ?>
<div class="card" style="border-left:4px solid #f57c00;background:var(--card);margin-bottom:20px;padding:14px 18px">
    <h3 style="margin:0 0 10px 0;color:#e65100;font-size:16px"><?= current_locale() === 'am' ? 'ትኩረት እና ውሳኔ የሚሹ የስራ ሂደቶች' : 'Operational Items Requiring Attention' ?></h3>
    <ul style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px">
        <?php foreach ($pendingItems as $item): ?>
            <li>
                <a href="<?= h($item['url']) ?>" style="font-weight:600;text-decoration:underline"><?= h($item['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- DEPARTMENT FINANCIAL PROFITABILITY SECTION -->
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

<!-- Enterprise KPI Summary Row -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= h(t('dashboard.todays_milk', "Today's Milk Yield")) ?></div>
        <div class="value"><?= number_format($todayMilk, 1) ?> L</div>
    </div>
    <div class="kpi">
        <div class="label"><?= h(t('poultry.todays_eggs', "Today's Egg Production")) ?></div>
        <div class="value"><?= number_format($todayEggs) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'ዛሬ የተገኙ ሰራተኞች' : 'Staff Attendance Today' ?></div>
        <div class="value"><?= $todayPresent ?> <span style="font-size:13px;color:var(--text-muted)">/ <?= $activeEmployees ?> <?= current_locale() === 'am' ? 'ሰራተኞች' : 'active' ?></span></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'የገንዘብ አቋም (Treasury)' : 'Treasury Net Position' ?></div>
        <div class="value"><?= number_format($cash['balance'] ?? 0, 0) ?> ETB</div>
    </div>
</div>

<!-- Department Operations Grid -->
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0;margin-bottom:14px">📊 <?= current_locale() === 'am' ? 'የሁሉም የስራ ክፍሎች አጠቃላይ የስራ ሂደት' : 'Department Operational Status' ?></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px">
        
        <!-- Dairy -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">🐄 <?= h(t('section.dairy', 'Dairy Unit')) ?></strong>
                <a href="<?= BASE_URL ?>/modules/dairy/cows.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'ንቁ ላሞች:' : 'Active Herd:' ?> <strong><?= $cowsCount ?> <?= current_locale() === 'am' ? 'ላሞች' : 'cows' ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የዛሬ ወተት:' : "Today's Yield:" ?> <strong><?= number_format($todayMilk, 1) ?> L</strong></div>
                <div><?= current_locale() === 'am' ? 'የህክምና ክትትል:' : 'Veterinary Flag:' ?> <strong class="<?= $sickCows > 0 ? 'badge sold' : 'badge milking' ?>"><?= $sickCows ?> <?= current_locale() === 'am' ? 'የታመሙ' : 'sick' ?></strong></div>
            </div>
        </div>

        <!-- Poultry -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">🐓 <?= h(t('section.poultry', 'Poultry Unit')) ?></strong>
                <a href="<?= BASE_URL ?>/modules/poultry/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'መንጋዎች:' : 'Flocks:' ?> <strong><?= $activeFlocks ?> (<?= number_format($totalBirds) ?> <?= current_locale() === 'am' ? 'ዶሮዎች' : 'birds' ?>)</strong></div>
                <div><?= current_locale() === 'am' ? 'የዛሬ እንቁላል:' : "Today's Egg Collection:" ?> <strong><?= number_format($todayEggs) ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የምርት ሁኔታ:' : 'Production Health:' ?> <span class="badge milking"><?= current_locale() === 'am' ? 'ጥሩ / የተረጋጋ' : 'Stable' ?></span></div>
            </div>
        </div>

        <!-- Fish -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">🐟 <?= h(t('section.fish_farm', 'Fish Farm')) ?></strong>
                <a href="<?= BASE_URL ?>/modules/fish/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'ኩሬዎች:' : 'Active Ponds:' ?> <strong><?= $fishPonds ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የዓሳ ብዛት:' : 'Fish Stock:' ?> <strong><?= number_format($totalFish) ?> <?= current_locale() === 'am' ? 'ዓሳዎች' : 'fish' ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የምርት ሪፖርት:' : 'Harvest Trends:' ?> <a href="<?= BASE_URL ?>/modules/module_reports.php?module=fish" style="font-size:12px"><?= h(t('common.view', 'View report')) ?></a></div>
            </div>
        </div>

        <!-- Warehouse & Supply Chain -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">🏪 <?= h(t('section.warehouse', 'Warehouse & Inventory')) ?></strong>
                <a href="<?= BASE_URL ?>/modules/warehouse/stock.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'የዕቃ ዓይነቶች:' : 'Catalog Items:' ?> <strong><?= $totalStockItems ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የሚገቡ ዕቃዎች:' : 'Incoming Shipments:' ?> <strong><?= $pendingWhReceiving ?> <?= current_locale() === 'am' ? 'ማረጋገጫ የሚጠብቁ' : 'awaiting check' ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የመምሪያ እቃ ጥያቄዎች:' : 'Open Requisitions:' ?> <strong><?= $pendingMaterialReqs ?></strong></div>
            </div>
        </div>

        <!-- Finance Summary -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">💰 <?= h(t('section.finance', 'Finance & Cash Flow')) ?></strong>
                <a href="<?= BASE_URL ?>/modules/finance/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'የገንዘብ አቋም:' : 'Cash Balance:' ?> <strong><?= number_format($cash['balance'] ?? 0, 0) ?> ETB</strong></div>
                <div><?= current_locale() === 'am' ? 'ውሳኔ የሚጠብቁ የግዢ ትዕዛዞች:' : 'Pending PO Approvals:' ?> <strong class="<?= $pendingPOs > 0 ? 'badge sold' : '' ?>"><?= $pendingPOs ?></strong></div>
                <div><?= current_locale() === 'am' ? 'ሙሉ የሂሳብ መዝገብ:' : 'Detailed Ledger:' ?> <a href="<?= BASE_URL ?>/modules/finance/ledger.php" style="font-size:12px"><?= h(t('fin.full_ledger', 'View Ledger')) ?></a></div>
            </div>
        </div>

        <!-- HR & Attendance -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">👷 <?= h(t('section.employees', 'Workforce & HR')) ?></strong>
                <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'ንቁ ሰራተኞች:' : 'Active Employees:' ?> <strong><?= $activeEmployees ?></strong></div>
                <div><?= current_locale() === 'am' ? 'ዛሬ የተገኙ:' : 'Present Today:' ?> <strong><?= $todayPresent ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የፈቃድ ጥያቄዎች:' : 'Pending Leaves:' ?> <strong class="<?= $pendingLeaves > 0 ? 'badge dry' : '' ?>"><?= $pendingLeaves ?></strong></div>
            </div>
        </div>

    </div>
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
