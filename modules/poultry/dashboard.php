<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'poultry_manager']);
require_once __DIR__ . '/../../includes/header.php';

// KPIs
$activeFlocks   = (int)db()->query("SELECT COUNT(*) FROM poultry_flocks WHERE status='active'")->fetchColumn();
$todaysEggs     = (int)db()->query("SELECT COALESCE(SUM(whole_count),0) FROM poultry_egg_collections WHERE collection_date=CURDATE()")->fetchColumn();
$monthMortality = (int)db()->query("SELECT COALESCE(SUM(count),0) FROM poultry_mortality_logs WHERE MONTH(log_date)=MONTH(CURDATE()) AND YEAR(log_date)=YEAR(CURDATE())")->fetchColumn();
$totalHouses    = (int)db()->query("SELECT COUNT(*) FROM poultry_houses")->fetchColumn();
$layingEff      = smart_get_poultry_laying_efficiency();

// Active Flocks
$flocks = db()->query(
    "SELECT f.id, CONCAT('FLOCK-', f.id) AS flock_code, f.breed,
            f.initial_population AS opening_count, f.stocking_date AS start_date, h.name AS house_name,
            f.cumulative_mortality AS total_mortality,
            (f.initial_population - f.cumulative_mortality - f.cumulative_culled_sold + f.cumulative_transfers_in) AS live_population
     FROM poultry_flocks f
     JOIN poultry_houses h ON h.id = f.poultry_house_id
     WHERE f.status='active'
     ORDER BY f.stocking_date DESC"
)->fetchAll();

// Last 7 days egg production
$eggTrend = db()->query(
    "SELECT collection_date, SUM(whole_count) AS whole, SUM(cracked_count) AS cracked, SUM(broken_count) AS broken
     FROM poultry_egg_collections
     WHERE collection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY collection_date ORDER BY collection_date DESC"
)->fetchAll();

// Recent mortality
$mortality = db()->query(
    "SELECT m.log_date, m.count, m.cause_code, CONCAT('FLOCK-', f.id) AS flock_code, h.name AS house
     FROM poultry_mortality_logs m
     JOIN poultry_flocks f ON f.id=m.flock_id
     JOIN poultry_houses h ON h.id=f.poultry_house_id
     ORDER BY m.log_date DESC, m.id DESC LIMIT 10"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <h2 class="mt-0" style="margin-bottom:0">🐓 <?= h(t('nav.poultry_dashboard', 'Poultry Manager Dashboard')) ?></h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/poultry/egg_entry.php" class="btn" style="background:#2e7d32;border-color:#2e7d32;font-size:14px;padding:8px 14px">
            🥚 <?= current_locale() === 'am' ? 'የዛሬ እንቁላል አስገባ (Egg Entry)' : 'Enter Today\'s Eggs' ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/poultry/flocks.php" class="btn secondary">
            🐓 <?= current_locale() === 'am' ? 'መንጋዎች / ሞት (Flocks)' : 'Flocks & Mortality' ?>
        </a>
        <a href="<?= BASE_URL ?>/modules/poultry/material_request.php" class="btn secondary">📦 <?= h(t('reqs.title_poultry', 'Material Request')) ?></a>
        <a href="<?= BASE_URL ?>/modules/module_reports.php?module=poultry" class="btn secondary">📋 <?= h(t('nav.module_reports', 'Reports')) ?></a>
    </div>
</div>

<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi"><div class="label"><?= h(t('poultry.active_flocks', 'Active Flocks')) ?></div><div class="value"><?= $activeFlocks ?></div></div>
    <div class="kpi"><div class="label"><?= h(t('poultry.houses', 'Poultry Houses')) ?></div><div class="value"><?= $totalHouses ?></div></div>
    <div class="kpi"><div class="label"><?= h(t('poultry.todays_eggs', "Today's Eggs")) ?></div><div class="value" style="color:#2e7d32"><?= number_format($todaysEggs) ?></div></div>
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'የእንቁላል ጣይነት (Laying Rate)' : 'Laying Rate %' ?></div><div class="value" style="color:#006837"><?= $layingEff['rate_percent'] ?>%</div></div>
    <div class="kpi"><div class="label"><?= h(t('poultry.month_mortality', 'This Month Mortality')) ?></div><div class="value" style="color:<?= $monthMortality > 0 ? '#b3261e' : 'inherit' ?>"><?= number_format($monthMortality) ?></div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0"><?= h(t('poultry.active_flocks', 'Active Flocks')) ?></h3>
        <a href="<?= BASE_URL ?>/modules/poultry/flocks.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all', 'View All')) ?> →</a>
    </div>
    <table>
        <thead><tr><th><?= h(t('poultry.house', 'House')) ?></th><th><?= h(t('poultry.flock_code', 'Flock Code')) ?></th><th><?= h(t('dairy.breed', 'Breed')) ?></th><th><?= h(t('poultry.opening_pop', 'Opening')) ?></th><th><?= h(t('poultry.live_population', 'Live Population')) ?></th><th><?= current_locale() === 'am' ? 'የቆይታ ቀናት' : 'Days Active' ?></th><th><?= h(t('common.actions', 'Action')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($flocks as $f):
            $livePop    = (int)$f['live_population'];
            $daysActive = max(0, (int)((time() - strtotime($f['start_date'])) / 86400));
        ?>
            <tr>
                <td><?= h($f['house_name']) ?></td>
                <td><strong><?= h($f['flock_code']) ?></strong></td>
                <td><?= h($f['breed']) ?></td>
                <td><?= number_format($f['opening_count']) ?></td>
                <td>
                    <strong><?= number_format($livePop) ?></strong>
                    <?php if ($f['total_mortality'] > 0): ?>
                        <span class="badge sold" style="font-size:10px">-<?= number_format($f['total_mortality']) ?> <?= current_locale() === 'am' ? 'ሞት' : 'mortality' ?></span>
                    <?php endif; ?>
                </td>
                <td><?= $daysActive ?> <?= current_locale() === 'am' ? 'ቀናት' : 'days' ?></td>
                <td><a href="<?= BASE_URL ?>/modules/poultry/flock_view.php?id=<?= (int)$f['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= h(t('common.view', 'View')) ?> →</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($flocks)): ?><tr><td colspan="7" class="text-muted"><?= h(t('common.no_records', 'No active flocks.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h3><?= current_locale() === 'am' ? 'የእንቁላል ምርት — ያለፉት 7 ቀናት' : 'Egg Production — Last 7 Days' ?></h3>
        <table>
            <thead><tr><th><?= h(t('common.date', 'Date')) ?></th><th><?= h(t('poultry.whole', 'Whole')) ?></th><th><?= h(t('poultry.cracked', 'Cracked')) ?></th><th><?= h(t('poultry.broken', 'Broken')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($eggTrend as $e): ?>
                <tr>
                    <td><?= eth_date($e['collection_date']) ?></td>
                    <td><strong style="color:#2e7d32"><?= number_format($e['whole']) ?></strong></td>
                    <td><?= number_format($e['cracked']) ?></td>
                    <td><?= number_format($e['broken']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($eggTrend)): ?><tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No egg data this week.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3><?= h(t('poultry.mortality_log', 'Recent Mortality')) ?></h3>
        <table>
            <thead><tr><th><?= h(t('common.date', 'Date')) ?></th><th><?= h(t('poultry.house', 'House')) ?></th><th><?= h(t('poultry.flock_code', 'Flock')) ?></th><th><?= h(t('common.quantity', 'Count')) ?></th><th><?= h(t('poultry.cause', 'Cause')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($mortality as $m): ?>
                <tr>
                    <td><?= eth_date($m['log_date']) ?></td>
                    <td><?= h($m['house']) ?></td>
                    <td><?= h($m['flock_code']) ?></td>
                    <td><span class="badge sold"><?= (int)$m['count'] ?></span></td>
                    <td><?= h($m['cause_code'] ? t('poultry.cause_' . $m['cause_code'], $m['cause_code']) : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($mortality)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No mortality recorded.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
