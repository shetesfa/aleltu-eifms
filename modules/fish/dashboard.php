<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'fish_manager']);
require_once __DIR__ . '/../../includes/header.php';

// KPIs
$activePonds     = (int)db()->query("SELECT COUNT(*) FROM fish_ponds WHERE status IN ('stocked','partially_harvested')")->fetchColumn();
$totalFish       = (int)db()->query("SELECT COALESCE(SUM(stocking_quantity - cumulative_mortality),0) FROM fish_stocks WHERE status='active'")->fetchColumn();
$monthHarvestKg  = (float)db()->query("SELECT COALESCE(SUM(quantity_kg),0) FROM fish_harvest_records WHERE MONTH(harvest_date)=MONTH(CURDATE()) AND YEAR(harvest_date)=YEAR(CURDATE())")->fetchColumn();
$monthMortality  = (int)db()->query("SELECT COALESCE(SUM(count),0) FROM fish_mortality_logs WHERE MONTH(log_date)=MONTH(CURDATE()) AND YEAR(log_date)=YEAR(CURDATE())")->fetchColumn();

// Active ponds with stock summary
$ponds = db()->query(
    "SELECT p.id, p.name, p.area_sq_m, p.status,
            COUNT(s.id) AS stock_count,
            COALESCE(SUM(s.stocking_quantity - s.cumulative_mortality),0) AS total_fish
     FROM fish_ponds p
     LEFT JOIN fish_stocks s ON s.pond_id=p.id AND s.status='active'
     GROUP BY p.id ORDER BY p.name"
)->fetchAll();

// Recent harvests
$harvests = db()->query(
    "SELECT hr.harvest_date, p.name AS pond, s.species, hr.quantity_kg, hr.grade
     FROM fish_harvest_records hr
     JOIN fish_stocks s ON s.id=hr.fish_stock_id
     JOIN fish_ponds p ON p.id=s.pond_id
     ORDER BY hr.harvest_date DESC LIMIT 10"
)->fetchAll();

// Recent feeding
$feedings = db()->query(
    "SELECT COALESCE(fl.completed_at, fl.scheduled_at) AS feed_date, p.name AS pond, s.species, fl.quantity_kg, fl.feed_type
     FROM fish_feeding_logs fl
     JOIN fish_stocks s ON s.id=fl.fish_stock_id
     JOIN fish_ponds p ON p.id=s.pond_id
     ORDER BY feed_date DESC, fl.id DESC LIMIT 10"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <h2 class="mt-0" style="margin-bottom:0">🐟 <?= h(t('nav.fish_dashboard', 'Fish Manager Dashboard')) ?></h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/fish/ponds.php" class="btn" style="background:#0277bd;border-color:#0277bd">
            🌊 <?= current_locale() === 'am' ? 'ኩሬዎች / አዲስ ኩሬ (Ponds)' : 'Manage Ponds' ?>
        </a>
        <a href="/aleltu-eifms/modules/fish/material_request.php" class="btn secondary">📦 <?= h(t('reqs.title_fish', 'Feed & Supplies Request')) ?></a>
        <a href="/aleltu-eifms/modules/module_reports.php?module=fish" class="btn secondary">📋 <?= h(t('nav.module_reports', 'Reports')) ?></a>
    </div>
</div>

<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi"><div class="label"><?= h(t('fish.stocked_ponds', 'Stocked Ponds')) ?></div><div class="value"><?= $activePonds ?></div></div>
    <div class="kpi"><div class="label"><?= h(t('fish.total_live_fish', 'Total Live Fish')) ?></div><div class="value" style="color:#0277bd"><?= number_format($totalFish) ?></div></div>
    <div class="kpi"><div class="label"><?= h(t('fish.month_harvest', 'This Month Harvest')) ?></div><div class="value" style="color:#2e7d32"><?= number_format($monthHarvestKg, 1) ?> kg</div></div>
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'የዚህ ወር የሞቱ ዓሳዎች' : 'This Month Mortality' ?></div><div class="value" style="color:<?= $monthMortality > 0 ? '#b3261e' : 'inherit' ?>"><?= number_format($monthMortality) ?> <?= current_locale() === 'am' ? 'ዓሳዎች' : 'fish' ?></div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">🌊 <?= current_locale() === 'am' ? 'የሁሉም ኩሬዎች አጠቃላይ እይታ እና ቀጥታ እርምጃዎች' : 'Ponds & Quick Actions' ?></h3>
        <a href="/aleltu-eifms/modules/fish/ponds.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all', 'Manage Ponds')) ?> →</a>
    </div>
    <table>
        <thead><tr><th><?= h(t('fish.pond', 'Pond Name')) ?></th><th><?= h(t('fish.area', 'Area (m²)')) ?></th><th><?= h(t('common.status', 'Status')) ?></th><th><?= current_locale() === 'am' ? 'ንቁ ዝርያዎች' : 'Active Stocks' ?></th><th><?= h(t('fish.total_live_fish', 'Total Fish')) ?></th><th><?= h(t('common.actions', 'Action')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($ponds as $p): ?>
            <tr>
                <td><strong><?= h($p['name']) ?></strong></td>
                <td><?= $p['area_sq_m'] ? number_format($p['area_sq_m'], 1) : '—' ?></td>
                <td><span class="badge <?= $p['status']==='stocked'?'milking':($p['status']==='partially_harvested'?'dry':'') ?>"><?= h(t('common.' . $p['status'], $p['status'])) ?></span></td>
                <td><?= (int)$p['stock_count'] ?></td>
                <td><strong style="color:#0277bd"><?= number_format($p['total_fish']) ?></strong></td>
                <td>
                    <a href="/aleltu-eifms/modules/fish/pond_view.php?id=<?= (int)$p['id'] ?>" class="btn secondary" style="padding:4px 10px;font-size:12px">
                        🐟 <?= current_locale() === 'am' ? 'መመገብ / ሞት / ማጨድ' : 'Feed / Mortality / Harvest' ?> →
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($ponds)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No ponds found.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h3><?= h(t('fish.harvest_history', 'Recent Harvest Log')) ?></h3>
        <table>
            <thead><tr><th><?= h(t('common.date', 'Date')) ?></th><th><?= h(t('fish.pond', 'Pond')) ?></th><th><?= h(t('fish.species', 'Species')) ?></th><th><?= current_locale() === 'am' ? 'ደረጃ' : 'Grade' ?></th><th><?= h(t('fish.quantity_kg', 'Qty (kg)')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($harvests as $h): ?>
                <tr>
                    <td><?= h($h['harvest_date']) ?></td>
                    <td><?= h($h['pond']) ?></td>
                    <td><?= h($h['species']) ?></td>
                    <td><?= h($h['grade'] ?: '—') ?></td>
                    <td><strong style="color:#2e7d32"><?= number_format($h['quantity_kg'], 1) ?> kg</strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($harvests)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No harvests recorded.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3><?= h(t('fish.recent_feeding', 'Recent Feeding Log')) ?></h3>
        <table>
            <thead><tr><th><?= current_locale() === 'am' ? 'ቀን/ሰዓት' : 'Date/Time' ?></th><th><?= h(t('fish.pond', 'Pond')) ?></th><th><?= h(t('fish.species', 'Species')) ?></th><th><?= h(t('fish.feed_type', 'Feed Type')) ?></th><th><?= h(t('fish.quantity_kg', 'Qty (kg)')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($feedings as $f): ?>
                <tr>
                    <td><?= h($f['feed_date'] ?: '—') ?></td>
                    <td><?= h($f['pond']) ?></td>
                    <td><?= h($f['species']) ?></td>
                    <td><?= h($f['feed_type'] ?: '—') ?></td>
                    <td><?= $f['quantity_kg'] ? number_format($f['quantity_kg'], 2) . ' kg' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($feedings)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No feedings recorded.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
