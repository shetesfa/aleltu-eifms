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
    $pendingItems[] = ['label' => "{$pendingPOs} Purchase Orders pending approval", 'url' => '/aleltu-eifms/modules/finance/purchasing.php', 'type' => 'finance'];
}
if ($pendingLeaves > 0) {
    $pendingItems[] = ['label' => "{$pendingLeaves} Staff leave requests awaiting decision", 'url' => '/aleltu-eifms/modules/employees/leave.php', 'type' => 'hr'];
}
if ($pendingMaterialReqs > 0) {
    $pendingItems[] = ['label' => "{$pendingMaterialReqs} Department material requests awaiting fulfillment", 'url' => '/aleltu-eifms/modules/warehouse/requisitions.php', 'type' => 'warehouse'];
}
if ($pendingWhReceiving > 0) {
    $pendingItems[] = ['label' => "{$pendingWhReceiving} Incoming shipments awaiting warehouse receipt verification", 'url' => '/aleltu-eifms/modules/warehouse/receiving.php', 'type' => 'warehouse'];
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🏢 <?= h(t('nav.gm_overview', 'General Manager Operations Dashboard')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የድርጅቱ ከፍተኛ የአመራር አፈጻጸም፣ የመምሪያዎች ምርት እና ውሳኔ የሚጠብቁ የስራ ሂደቶች' : 'High-level enterprise operational performance, department yields, and pending managerial decisions.' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/reports.php" class="btn">📊 <?= h(t('nav.reports', 'Company Reports')) ?></a>
        <a href="/aleltu-eifms/modules/module_reports.php" class="btn secondary">📋 <?= h(t('nav.module_reports', 'Department Reports')) ?></a>
        <a href="/aleltu-eifms/modules/finance/purchasing.php" class="btn secondary">🛍️ <?= h(t('nav.purchasing', 'Purchasing & POs')) ?></a>
    </div>
</div>

<!-- Critical Action Items Banner -->
<?php if (!empty($pendingItems)): ?>
<div class="card" style="border-left:4px solid #f57c00;background:var(--card);margin-bottom:20px;padding:14px 18px">
    <h3 style="margin:0 0 10px 0;color:#e65100;font-size:16px">⚠️ <?= current_locale() === 'am' ? 'ትኩረት እና ውሳኔ የሚሹ የስራ ሂደቶች' : 'Operational Items Requiring Attention' ?></h3>
    <ul style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px">
        <?php foreach ($pendingItems as $item): ?>
            <li>
                <a href="<?= h($item['url']) ?>" style="font-weight:600;text-decoration:underline"><?= h($item['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

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
                <a href="/aleltu-eifms/modules/dairy/cows.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
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
                <a href="/aleltu-eifms/modules/poultry/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
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
                <a href="/aleltu-eifms/modules/fish/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'ኩሬዎች:' : 'Active Ponds:' ?> <strong><?= $fishPonds ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የዓሳ ብዛት:' : 'Fish Stock:' ?> <strong><?= number_format($totalFish) ?> <?= current_locale() === 'am' ? 'ዓሳዎች' : 'fish' ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የምርት ሪፖርት:' : 'Harvest Trends:' ?> <a href="/aleltu-eifms/modules/module_reports.php?module=fish" style="font-size:12px"><?= h(t('common.view', 'View report')) ?></a></div>
            </div>
        </div>

        <!-- Warehouse & Supply Chain -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">🏪 <?= h(t('section.warehouse', 'Warehouse & Inventory')) ?></strong>
                <a href="/aleltu-eifms/modules/warehouse/stock.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
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
                <a href="/aleltu-eifms/modules/finance/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'የገንዘብ አቋም:' : 'Cash Balance:' ?> <strong><?= number_format($cash['balance'] ?? 0, 0) ?> ETB</strong></div>
                <div><?= current_locale() === 'am' ? 'ውሳኔ የሚጠብቁ የግዢ ትዕዛዞች:' : 'Pending PO Approvals:' ?> <strong class="<?= $pendingPOs > 0 ? 'badge sold' : '' ?>"><?= $pendingPOs ?></strong></div>
                <div><?= current_locale() === 'am' ? 'ሙሉ የሂሳብ መዝገብ:' : 'Detailed Ledger:' ?> <a href="/aleltu-eifms/modules/finance/ledger.php" style="font-size:12px"><?= h(t('fin.full_ledger', 'View Ledger')) ?></a></div>
            </div>
        </div>

        <!-- HR & Attendance -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <strong style="font-size:15px">👷 <?= h(t('section.employees', 'Workforce & HR')) ?></strong>
                <a href="/aleltu-eifms/modules/employees/list.php" class="btn secondary" style="padding:2px 8px;font-size:11px"><?= h(t('common.view', 'Inspect')) ?> →</a>
            </div>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                <div><?= current_locale() === 'am' ? 'ንቁ ሰራተኞች:' : 'Active Employees:' ?> <strong><?= $activeEmployees ?></strong></div>
                <div><?= current_locale() === 'am' ? 'ዛሬ የተገኙ:' : 'Present Today:' ?> <strong><?= $todayPresent ?></strong></div>
                <div><?= current_locale() === 'am' ? 'የፈቃድ ጥያቄዎች:' : 'Pending Leaves:' ?> <strong class="<?= $pendingLeaves > 0 ? 'badge dry' : '' ?>"><?= $pendingLeaves ?></strong></div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
