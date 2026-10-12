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

// ── Audit Trail ─────────────────────────────────────────────────────────────
$recentAudits = audit_log_get(8);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🛡️ <?= h(t('nav.super_admin','Super Administrator Control Center')) ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am' ? 'ሙሉ የኩባንያ ፋይናንስ አቋም፣ የሥራ ሂደት ቁጥጥር እና ደህንነት ክትትል' : 'Full company financial position, operational status, and security oversight.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/admin/users.php" class="btn">👤 <?= h(t('nav.user_mgmt','Users')) ?></a>
        <a href="/aleltu-eifms/modules/admin/settings.php" class="btn secondary">⚙️ <?= h(t('nav.settings','Settings')) ?></a>
        <a href="/aleltu-eifms/modules/reports.php" class="btn secondary">📊 <?= h(t('nav.reports','Reports')) ?></a>
        <a href="/aleltu-eifms/modules/audit_log.php" class="btn secondary">📋 <?= h(t('nav.audit_log','Audit Log')) ?></a>
    </div>
</div>

<!-- ═══ FINANCIAL POSITION ═══ -->
<div style="margin-bottom:6px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">
    💰 <?= current_locale()==='am'?'የኩባንያ ፋይናንስ አቋም':'Company Financial Position' ?>
</div>
<div class="kpi-row" style="margin-bottom:8px">
    <?php foreach (($byMethod['_accounts'] ?? []) as $acc): ?>
    <a href="/aleltu-eifms/modules/finance/ledger.php" style="text-decoration:none;color:inherit;flex:1">
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
    <a href="/aleltu-eifms/modules/finance/receivables.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #2e7d32">
            <div class="label">👥 <?= current_locale()==='am'?'ተሰብሳቢ':'Receivables' ?></div>
            <div class="value" style="color:#2e7d32"><?= number_format($receivable['total_receivable'],0) ?> ETB</div>
        </div>
    </a>
    <a href="/aleltu-eifms/modules/finance/payables.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid <?= $payable['total_payable']>0?'#c62828':'var(--border)' ?>">
            <div class="label">🏪 <?= current_locale()==='am'?'ተከፋይ':'Payables' ?></div>
            <div class="value" style="color:<?= $payable['total_payable']>0?'#c62828':'inherit' ?>"><?= number_format($payable['total_payable'],0) ?> ETB</div>
        </div>
    </a>
    <a href="/aleltu-eifms/modules/finance/inventory_value.php" style="text-decoration:none;color:inherit;flex:1">
        <div class="kpi" style="cursor:pointer;border-left:4px solid #1565c0">
            <div class="label">📦 <?= current_locale()==='am'?'የዕቃ ዋጋ':'Inventory Value' ?></div>
            <div class="value" style="color:#1565c0"><?= number_format($inventory['total_inventory_value'],0) ?> ETB</div>
        </div>
    </a>
</div>

<!-- ═══ NEEDS ATTENTION ═══ -->
<?php
$alerts = [];
if ($unpaidDispatchCount > 0) $alerts[] = ['icon'=>'👥','color'=>'#c62828','msg'=> $unpaidDispatchCount.' '.(current_locale()==='am'?'ያልተሰበሰቡ ሽያጭ ክፍያዎች':'unpaid dispatch(es)') ,'url'=>'/aleltu-eifms/modules/finance/receivables.php'];
if ($pendingPOApproval > 0)   $alerts[] = ['icon'=>'🛒','color'=>'#e65100','msg'=> $pendingPOApproval.' '.(current_locale()==='am'?'PO ፈቃድ ይጠብቃሉ':'purchase order(s) awaiting approval'),'url'=>'/aleltu-eifms/modules/finance/purchasing.php'];
if ($pendingMaterialReqs > 0) $alerts[] = ['icon'=>'📦','color'=>'#e65100','msg'=> $pendingMaterialReqs.' '.(current_locale()==='am'?'የዕቃ ጥያቄ ይጠብቃሉ':'material request(s) pending'),'url'=>'/aleltu-eifms/modules/warehouse/requisitions.php'];
if ($pendingWhReceiving > 0)  $alerts[] = ['icon'=>'📥','color'=>'#1565c0','msg'=> $pendingWhReceiving.' '.(current_locale()==='am'?'ያልተቀበሉ ዕቃዎች':'incoming goods awaiting receipt'),'url'=>'/aleltu-eifms/modules/warehouse/receiving.php'];
if ($lowStockCount > 0)       $alerts[] = ['icon'=>'⚠️','color'=>'#c62828','msg'=> $lowStockCount.' '.(current_locale()==='am'?'ዕቃዎች ደረጃ ዝቅ ብሏል':'item(s) below minimum stock'),'url'=>'/aleltu-eifms/modules/warehouse/stock.php'];
if ($vaccinationsDue > 0)     $alerts[] = ['icon'=>'💉','color'=>'#c62828','msg'=> $vaccinationsDue.' '.(current_locale()==='am'?'ክትባቶች ዛሬ ወይም ዘጠኙ':'vaccination(s) due/overdue'),'url'=>'/aleltu-eifms/modules/veterinary/vaccinations.php'];
if ($pendingLeaves > 0)       $alerts[] = ['icon'=>'🏖️','color'=>'#795548','msg'=> $pendingLeaves.' '.(current_locale()==='am'?'የፈቃድ ጥያቄዎች':'leave request(s) pending'),'url'=>'/aleltu-eifms/modules/employees/leave.php'];
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
            <a href="/aleltu-eifms/modules/dairy/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ንቁ ላሞች:':'Active Cows:' ?> <strong><?= $cowsCount ?></strong></div>
            <div><?= current_locale()==='am'?"የዛሬ ወተት:":"Today's Milk:" ?> <strong><?= number_format($todayMilk,1) ?> L</strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🐓 <?= h(t('section.poultry','Poultry')) ?></strong>
            <a href="/aleltu-eifms/modules/poultry/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ዶሮዎች:':'Live Birds:' ?> <strong><?= number_format($totalBirds) ?></strong></div>
            <div><?= current_locale()==='am'?"የዛሬ እንቁላል:":"Today's Eggs:" ?> <strong><?= number_format($todayEggs) ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🐟 <?= h(t('section.fish_farm','Fish')) ?></strong>
            <a href="/aleltu-eifms/modules/fish/dashboard.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ህያው ዓሳ:':'Live Fish:' ?> <strong><?= number_format($totalFish) ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🏪 <?= h(t('section.warehouse','Warehouse')) ?></strong>
            <a href="/aleltu-eifms/modules/warehouse/stock.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
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
            <a href="/aleltu-eifms/modules/employees/list.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <div><?= current_locale()==='am'?'ንቁ ሠራተኞች:':'Active Staff:' ?> <strong><?= $activeEmployees ?></strong></div>
            <div><?= current_locale()==='am'?'ፈቃድ ጥያቄ:':'Pending Leaves:' ?> <strong class="<?= $pendingLeaves>0?'badge dry':'' ?>"><?= $pendingLeaves ?></strong></div>
        </div>
    </div>

    <div style="border:1px solid var(--border);border-radius:8px;padding:14px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong>🔐 <?= current_locale()==='am'?'ሲስተም ተጠቃሚዎች':'System / Users' ?></strong>
            <a href="/aleltu-eifms/modules/admin/users.php" class="btn secondary" style="padding:2px 8px;font-size:11px">Manage →</a>
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
        <a href="/aleltu-eifms/modules/audit_log.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all','View Full Log')) ?> →</a>
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
                <td style="font-size:12px;white-space:nowrap"><?= h($log['created_at']) ?></td>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
