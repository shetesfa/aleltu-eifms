<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager']);

$today = date('Y-m-d');
$filterStart = $_GET['start_date'] ?? date('Y-m-01');
$filterEnd   = $_GET['end_date'] ?? $today;

// Farm KPIs
$kpis = [
    'total_cows'     => (int) db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status NOT IN ('sold', 'deceased')")->fetchColumn(),
    'daily_milk'     => (float) db()->query("SELECT COALESCE(SUM(e.yield_liters), 0) FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id = e.milking_session_id WHERE s.session_date = '{$today}'")->fetchColumn(),
    'poultry_birds'  => (int) db()->query("SELECT COALESCE(SUM(initial_population + cumulative_transfers_in - cumulative_mortality - cumulative_culled_sold), 0) FROM poultry_flocks WHERE status = 'active'")->fetchColumn(),
    'daily_eggs'     => (int) db()->query("SELECT COALESCE(SUM(whole_count + cracked_count), 0) FROM poultry_egg_collections WHERE collection_date = '{$today}'")->fetchColumn(),
    'fish_stocks'    => (int) db()->query("SELECT COALESCE(SUM(stocking_quantity - cumulative_mortality), 0) FROM fish_stocks WHERE status = 'active'")->fetchColumn(),
    'active_workers' => (int) db()->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn(),
];

// Department Profitability Analysis
$deptProf = finance_department_profitability($filterStart, $filterEnd);

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">👑 <?= current_locale() === 'am' ? 'የዋና አስተዳዳሪ አጠቃላይ መቆጣጠሪያ ማዕከል' : 'Super Admin Executive Dashboard' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የእርሻው አጠቃላይ ምርት፣ የትርፋማነት ማዕከላት፣ እና የስራ ክፍሎች የፋይናንስ እንቅስቃሴ መቆጣጠሪያ።' : 'High-level farm production, profit centers, and operational economics.' ?>
        </p>
    </div>

    <!-- Date Range Filter -->
    <form method="get" class="card no-print" style="padding:8px 14px;margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:13px;font-weight:700">📅 የወቅቱ ማጣሪያ፡</span>
        <input type="date" name="start_date" value="<?= h($filterStart) ?>" style="padding:4px 8px;font-size:12px">
        <span>እስከ</span>
        <input type="date" name="end_date" value="<?= h($filterEnd) ?>" style="padding:4px 8px;font-size:12px">
        <button type="submit" class="btn primary" style="padding:4px 12px;font-size:12px">አጣራ</button>
    </form>
</div>

<!-- TOP FARM PRODUCTION KPIS -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;margin-bottom:24px">
    <div class="card" style="border-left:4px solid #1565c0;padding:14px">
        <div style="font-size:12px;color:var(--text-muted)">🐄 ንቁ ላሞች</div>
        <div style="font-size:22px;font-weight:800;color:#1565c0;margin-top:4px"><?= number_format($kpis['total_cows']) ?> ራሶች</div>
        <small class="text-muted">የዛሬ ወተት፡ <?= number_format($kpis['daily_milk'], 1) ?> ሊትር</small>
    </div>

    <div class="card" style="border-left:4px solid #e65100;padding:14px">
        <div style="font-size:12px;color:var(--text-muted)">🐓 ንቁ ዶሮዎች</div>
        <div style="font-size:22px;font-weight:800;color:#e65100;margin-top:4px"><?= number_format($kpis['poultry_birds']) ?> ዶሮዎች</div>
        <small class="text-muted">የዛሬ እንቁላል፡ <?= number_format($kpis['daily_eggs']) ?> ፍሬ</small>
    </div>

    <div class="card" style="border-left:4px solid #00838f;padding:14px">
        <div style="font-size:12px;color:var(--text-muted)">🐟 የዓሣ ክምችት</div>
        <div style="font-size:22px;font-weight:800;color:#00838f;margin-top:4px"><?= number_format($kpis['fish_stocks']) ?> ዓሣዎች</div>
        <small class="text-muted">በመዋኛ ገንዳዎች</small>
    </div>

    <div class="card" style="border-left:4px solid #2e7d32;padding:14px">
        <div style="font-size:12px;color:var(--text-muted)">👥 ንቁ ሠራተኞች</div>
        <div style="font-size:22px;font-weight:800;color:#2e7d32;margin-top:4px"><?= number_format($kpis['active_workers']) ?> ሠራተኞች</div>
        <small class="text-muted">በሁሉም ክፍሎች</small>
    </div>
</div>

<!-- ═══ 1. CORE PROFIT CENTERS (አምራችና ትርፋማ የስራ ክፍሎች) ═══ -->
<div class="card" style="margin-bottom:24px;border-top:4px solid #1b5e20">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
        <div>
            <h3 style="margin:0;color:#1b5e20;font-size:18px">
                🏭 ዋና አምራችና ገቢ አስገኚ ክፍሎች (Core Profit Centers)
            </h3>
            <small class="text-muted">በቀጥታ ምርት አምርተው ገቢ የሚያስገቡ እና ትርፋማነታቸው (P&L) የሚሰላባቸው ክፍሎች</small>
        </div>
        <div style="font-size:14px;background:#e8f5e9;padding:6px 12px;border-radius:6px;color:#1b5e20;font-weight:700">
            አጠቃላይ የተጣራ ትርፍ፦ <?= number_format($deptProf['total_net_profit'], 2) ?> ETB
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px">
        <?php foreach ($deptProf['departments'] as $dKey => $d): 
            if (empty($d['is_profit_center'])) continue; // Only show true profit centers
            $isProf = ($d['net_profit'] >= 0);
        ?>
            <div style="border:1px solid var(--border);border-top:4px solid <?= $d['color'] ?>;border-radius:8px;padding:14px;background:var(--card);display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 2px 6px rgba(0,0,0,0.04)">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                        <strong style="font-size:15px"><?= $d['icon'] ?> <?= h($d['name_am']) ?></strong>
                        <span class="badge" style="background:<?= $isProf ? '#e8f5e9' : '#ffebee' ?>;color:<?= $isProf ? '#2e7d32' : '#c62828' ?>;font-weight:700">
                            <?= $isProf ? 'ትርፍ' : 'ኪሳራ' ?>
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;background:var(--bg);padding:8px;border-radius:6px;margin-bottom:10px">
                        <div>
                            <span class="text-muted">ጠቅላላ ገቢ፡</span><br>
                            <strong style="color:#2e7d32;font-size:13px"><?= number_format($d['revenue'], 2) ?></strong>
                        </div>
                        <div>
                            <span class="text-muted">የስራ ወጪ፡</span><br>
                            <strong style="color:#c62828;font-size:13px"><?= number_format($d['expense'], 2) ?></strong>
                        </div>
                        <div style="grid-column:1 / -1;border-top:1px dashed var(--border);padding-top:4px">
                            <span class="text-muted">የተጣራ ትርፍ (Net Profit):</span><br>
                            <strong style="color:<?= $isProf ? '#2e7d32' : '#c62828' ?>;font-size:15px">
                                <?= ($d['net_profit'] > 0 ? '+' : '') . number_format($d['net_profit'], 2) ?> ETB
                            </strong>
                            <small class="text-muted" style="margin-left:6px">(ምጣኔ: <?= number_format($d['margin'], 1) ?>%)</small>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn secondary" style="width:100%;font-size:12px;padding:6px;font-weight:600" onclick="openDeptModal('<?= $dKey ?>')">
                    🔍 ዝርዝር የገንዘብ እንቅስቃሴ ይመልከቱ
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ 2. OPERATIONAL SUPPORT CENTERS (ድጋፍ ሰጪ እና የአስተዳደር ወጪ ማዕከላት) ═══ -->
<div class="card" style="margin-bottom:24px;border-top:4px solid #546e7a">
    <div style="margin-bottom:14px">
        <h3 style="margin:0;color:#37474f;font-size:17px">
            🛡️ ድጋፍ ሰጪ የስራ ክፍሎች እና የአስተዳደር ወጪዎች (Operational Support Centers)
        </h3>
        <small class="text-muted">እነዚህ ክፍሎች የፋብሪካው ደጋፊ ክፍሎች (Cost Centers) በመሆናቸው ገቢ የላቸውም፤ ወጪያቸው ብቻ በግልጽ ተለይቶ ይታያል</small>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px">
        <?php foreach ($deptProf['departments'] as $dKey => $d): 
            if (!empty($d['is_profit_center'])) continue; // Only show support/cost centers
        ?>
            <div style="border:1px solid var(--border);border-left:4px solid <?= $d['color'] ?>;border-radius:6px;padding:12px;background:var(--card)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <strong><?= $d['icon'] ?> <?= h($d['name_am']) ?></strong>
                    <span class="badge" style="background:#f5f5f5;color:#555">ድጋፍ ሰጪ</span>
                </div>
                <div style="font-size:12px;color:var(--text-muted)">የወጣ ጠቅላላ ወጪ፦</div>
                <div style="font-size:18px;font-weight:800;color:#c62828;margin-top:2px">
                    <?= number_format($d['expense'], 2) ?> ETB
                </div>
                <button type="button" class="btn secondary" style="width:100%;font-size:11px;padding:4px;margin-top:8px" onclick="openDeptModal('<?= $dKey ?>')">
                    📄 የወጪ ዝርዝሮችን ይመልከቱ
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ DEPARTMENT DETAIL BREAKDOWN MODAL ═══ -->
<div id="deptModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;backdrop-filter:blur(3px);overflow-y:auto;padding:20px 10px;align-items:center;justify-content:center">
    <div style="max-width:920px;width:95%;margin:auto;background:var(--card);border-radius:12px;box-shadow:0 12px 35px rgba(0,0,0,0.25);border:1px solid var(--border);overflow:hidden">
        <!-- Header -->
        <div style="padding:16px 20px;background:var(--bg);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div>
                <h3 id="modalTitle" style="margin:0;font-size:17px">የስራ ክፍል ዝርዝር</h3>
                <small id="modalSubtitle" style="color:var(--text-muted);font-size:12px">የገቢና ወጪ ዝርዝር ጥልቅ ትንተና</small>
            </div>
            <button type="button" onclick="closeDeptModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);line-height:1;padding:4px 8px">&times;</button>
        </div>

        <!-- Summary KPIs inside Modal -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:12px;padding:16px 20px;background:var(--hover);border-bottom:1px solid var(--border)">
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700">ጠቅላላ ገቢ (Revenue)</div>
                <div id="modalGrossRevenue" style="font-size:18px;font-weight:800;color:#2e7d32;margin-top:2px">0.00 ETB</div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700">ጠቅላላ ወጪ (Expenses)</div>
                <div id="modalTotalExpense" style="font-size:18px;font-weight:800;color:#c62828;margin-top:2px">0.00 ETB</div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700">የተጣራ ትርፍ / ኪሳራ</div>
                <div id="modalNetProfit" style="font-size:18px;font-weight:800;margin-top:2px">0.00 ETB</div>
            </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex;border-bottom:1px solid var(--border);padding:0 20px;background:var(--bg)">
            <button type="button" id="tabBtnRev" onclick="switchModalTab('rev')" style="background:none;border:none;border-bottom:3px solid #2e7d32;padding:10px 16px;font-weight:700;cursor:pointer;color:#2e7d32;font-size:13px">
                የገቢ ምንጮችና ሽያጮች (<span id="modalRevCount">0</span>)
            </button>
            <button type="button" id="tabBtnExp" onclick="switchModalTab('exp')" style="background:none;border:none;border-bottom:3px solid transparent;padding:10px 16px;font-weight:700;cursor:pointer;color:var(--text-muted);font-size:13px">
                የወጪ ዝርዝሮች (<span id="modalExpCount">0</span>)
            </button>
        </div>

        <!-- Content Area -->
        <div style="padding:16px 20px;max-height:420px;overflow-y:auto">
            <!-- Revenues Table -->
            <div id="modalSectionRev">
                <table style="width:100%;font-size:12.5px;border-collapse:collapse">
                    <thead>
                        <tr style="background:var(--bg);border-bottom:2px solid var(--border)">
                            <th>ቀን</th>
                            <th>የምርት ስም</th>
                            <th>መጠን</th>
                            <th>መዳረሻ (ሱቅ/ገዢ)</th>
                            <th>ጠቅላላ ዋጋ</th>
                            <th>የተከፈለ</th>
                            <th>ቀሪ ዕዳ</th>
                        </tr>
                    </thead>
                    <tbody id="modalRevTbody"></tbody>
                </table>
                <div id="modalRevEmpty" style="display:none;padding:24px;text-align:center;color:var(--text-muted)">ምንም የሽያጭ ገቢ አልተገኘም።</div>
            </div>

            <!-- Expenses Table -->
            <div id="modalSectionExp" style="display:none">
                <table style="width:100%;font-size:12.5px;border-collapse:collapse">
                    <thead>
                        <tr style="background:var(--bg);border-bottom:2px solid var(--border)">
                            <th>ቀን</th>
                            <th>የወጪ ምድብ</th>
                            <th>ተቀባይ</th>
                            <th>ዝርዝር ምክንያት</th>
                            <th>ምንጭ</th>
                            <th>መጠን</th>
                        </tr>
                    </thead>
                    <tbody id="modalExpTbody"></tbody>
                </table>
                <div id="modalExpEmpty" style="display:none;padding:24px;text-align:center;color:var(--text-muted)">ምንም የወጪ መዝገብ አልተገኘም።</div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:12px 20px;background:var(--hover);border-top:1px solid var(--border);display:flex;justify-content:flex-end">
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
    document.getElementById('modalSubtitle').innerText = 'የሽያጭ ገቢዎችና የወጪ ምክንያቶች ጥልቅ ትንተና';

    const rev = Number(d.revenue || 0);
    const exp = Number(d.expense || 0);
    const net = Number(d.net_profit || (rev - exp));

    document.getElementById('modalGrossRevenue').innerText = rev.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB';
    document.getElementById('modalTotalExpense').innerText = exp.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB';
    
    const netEl = document.getElementById('modalNetProfit');
    netEl.innerText = (net > 0 ? '+' : '') + net.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ETB';
    netEl.style.color = (net >= 0) ? '#2e7d32' : '#c62828';

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
            tr.innerHTML = `
                <td style="padding:8px;white-space:nowrap">${r.date || '—'}</td>
                <td style="padding:8px"><strong>${r.item || '—'}</strong></td>
                <td style="padding:8px">${r.quantity || '—'}</td>
                <td style="padding:8px">${r.destination || '—'}</td>
                <td style="padding:8px;text-align:right"><strong>${Number(r.total_amount).toLocaleString(undefined, {minimumFractionDigits:2})}</strong></td>
                <td style="padding:8px;text-align:right;color:#2e7d32">${Number(r.cash_paid).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                <td style="padding:8px;text-align:right;color:#c62828">${Number(r.credit_unpaid).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
            `;
            revTbody.appendChild(tr);
        });
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
        expRows.forEach(x => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid var(--border)';
            tr.innerHTML = `
                <td style="padding:8px;white-space:nowrap">${x.date || '—'}</td>
                <td style="padding:8px"><strong>${x.category || '—'}</strong></td>
                <td style="padding:8px">${x.recipient || '—'}</td>
                <td style="padding:8px;font-style:italic">${x.description || '—'}</td>
                <td style="padding:8px">${x.source || 'ካዝና'}</td>
                <td style="padding:8px;text-align:right;color:#c62828;font-weight:700">${Number(x.amount).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
            `;
            expTbody.appendChild(tr);
        });
    }

    // Open Modal
    switchModalTab(revRows.length > 0 ? 'rev' : 'exp');
    const modal = document.getElementById('deptModal');
    modal.style.display = 'flex';
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
