<?php
/**
 * Super Admin - Farm Performance Analytics (main page).
 * Strictly super_admin. Every figure comes from includes/analytics/*.php
 * (parameterised SQL over the real ledgers/production tables).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
require_once __DIR__ . '/../../includes/analytics/analytics_ui.php';

analytics_guard();                      // 403 + audit for everybody else, before any output

$pdo = db();
$f = an_parse_filters($_GET);
$missing = an_schema_status($pdo);
$o = an_overview($pdo, $f['from'], $f['to']);
$acc = $o['acc'];
$units = $o['units'];
$company = $o['company'];
$snap = $o['snap'];
$months = $o['months'];
ksort($months);

/* Which unit groups are visible: the unit filter and result-status filter both really remove rows/columns. */
$visible = [];
foreach (AN_UNITS as $u) {
    if ($f['unit'] !== '' && $f['unit'] !== $u) {
        continue;
    }
    if ($f['status'] !== '' && ($units[$u]['status'] ?? '') !== $f['status']) {
        continue;
    }
    $visible[] = $u;
}

$mv = fn(string $mk, string $u, string $m) => (float)($months[$mk][$u][$m] ?? 0);
$monthSum = function (string $mk, string $m) use ($months) {
    $s = 0.0;
    foreach ($months[$mk] ?? [] as $u => $metrics) {
        $s += (float)($metrics[$m] ?? 0);
    }
    return $s;
};

/* ---------------- CSV export (before any HTML) ---------------- */
if (($_GET['export'] ?? '') === '1') {
    an_audit('export', 'analytics', 0, "from={$f['from']} to={$f['to']} unit={$f['unit']} status={$f['status']}");
    $rows = [];
    foreach ($visible as $u) {
        $r = $units[$u];
        $rows[] = [
            'business_unit' => $u, 'from' => $f['from'], 'to' => $f['to'],
            'income_recorded_sales' => number_format($r['income'] / 100, 2, '.', ''),
            'cash_bank_received' => number_format($r['receipts']['total'] / 100, 2, '.', ''),
            'expenses' => number_format($r['expenses'] / 100, 2, '.', ''),
            'profit_loss' => $r['profit'] === null ? '' : number_format($r['profit'] / 100, 2, '.', ''),
            'margin_percent' => $r['margin'] === null ? '' : number_format($r['margin'], 2, '.', ''),
            'status' => $r['status'],
        ];
    }
    $rows[] = [
        'business_unit' => 'COMPANY_TOTAL', 'from' => $f['from'], 'to' => $f['to'],
        'income_recorded_sales' => number_format($company['income'] / 100, 2, '.', ''),
        'cash_bank_received' => number_format($company['receipts']['total'] / 100, 2, '.', ''),
        'expenses' => number_format($company['expenses'] / 100, 2, '.', ''),
        'profit_loss' => number_format($company['profit'] / 100, 2, '.', ''),
        'margin_percent' => $company['margin'] === null ? '' : number_format($company['margin'], 2, '.', ''),
        'status' => $company['status'],
    ];
    export_csv(array_map(fn($r) => array_map('an_csv_safe', $r), $rows), 'analytics_' . $f['from'] . '_' . $f['to'] . '.csv');
}

an_audit('view', 'analytics', 0, "from={$f['from']} to={$f['to']}");

/* ---------------- Optional entity lists / search (one grouped query each) ---------------- */
$search = $f['q'] !== '' ? an_search($pdo, $f['q'], $f['from'], $f['to']) : null;
$cows = $flocks = $ponds = $plots = [];
if ($f['unit'] === '' || $f['unit'] === 'dairy')           { $cows   = an_cows_list($pdo, $f['from'], $f['to'], $f['q']); }
if ($f['unit'] === '' || $f['unit'] === 'poultry')         { $flocks = an_flocks_list($pdo, $f['from'], $f['to'], $f['q']); }
if ($f['unit'] === '' || $f['unit'] === 'fish')            { $ponds  = an_ponds_list($pdo, $f['from'], $f['to'], $f['q']); }
if ($f['unit'] === '' || $f['unit'] === 'feed_production') { $plots  = an_plots_list($pdo, $f['from'], $f['to'], $f['q']); }

require_once __DIR__ . '/../../includes/header.php';

/* ---- small renderers for the production / health cells ---- */
$cellProd = function (string $u, callable $g): string {
    switch ($u) {
        case 'dairy':
            return an_num($g('milk_l'), 1) . ' L';
        case 'poultry':
            return an_num($g('eggs_total')) . ' 🥚<br><small>' . an_num($g('eggs_whole')) . ' / ' . an_num($g('eggs_cracked')) . ' / ' . an_num($g('eggs_broken')) . '</small><br><small>☠ ' . an_num($g('bird_mortality')) . '</small>';
        case 'fish':
            return an_num($g('fish_harvest_kg'), 1) . ' kg<br><small>🐟+ ' . an_num($g('fish_stocked')) . ' · ☠ ' . an_num($g('fish_mortality')) . ' · 🍽 ' . an_num($g('fish_feed_kg'), 1) . ' kg</small>';
    }
    return '';
};
$span = fn(string $u) => $u === 'feed_production' || $u === 'veterinary' ? 3 : 5;
$colsFor = ['dairy' => 5, 'poultry' => 5, 'fish' => 5, 'feed_production' => 4, 'veterinary' => 3];
?>
<link rel="stylesheet" href="/aleltu-eifms/assets/css/analytics.css">
<div class="an-wrap">
    <div class="an-head">
        <h1>📈 <?= h(an_t('title')) ?></h1>
        <div class="an-head-actions">
            <a class="btn secondary" href="/aleltu-eifms/modules/admin/analytics_settings.php">⚙ <?= current_locale() === 'am' ? 'መቼቶች' : 'Settings' ?></a>
            <a class="btn secondary" href="?<?= h(an_filter_query($f, ['q' => $f['q'], 'export' => 1])) ?>">⬇ <?= h(an_t('export')) ?></a>
        </div>
    </div>

    <?php an_render_schema_notice($missing); ?>
    <?php an_render_filters($f, '/aleltu-eifms/modules/admin/analytics.php'); ?>

    <p class="an-hint"><?= current_locale() === 'am'
        ? 'ገቢ = የተመዘገበ ሽያጭ ብቻ። አካላዊ ምርት (ወተት፣ እንቁላል፣ ዓሳ) ገቢ አይደለም። የባንክ ዝውውሮችና የባለቤት ወጪዎች ከትርፍ ተገልለዋል።'
        : 'Income = recorded sales only. Physical production (milk, eggs, fish) is never income. Bank transfers and owner drawings are excluded from profit.' ?></p>

    <!-- ===================== Company summary ===================== -->
    <section class="an-cards">
        <article class="an-card an-card-company">
            <h3><?= h(an_t('company')) ?></h3>
            <dl>
                <dt><?= h(an_t('income')) ?></dt><dd><?= an_money($company['income']) ?></dd>
                <dt><?= h(an_t('expenses')) ?></dt><dd><?= an_money($company['expenses']) ?></dd>
                <dt><?= h(an_t('profit')) ?></dt><dd class="<?= an_sign_class($company['profit']) ?>"><?= an_money($company['profit'], true) ?></dd>
                <dt><?= h(an_t('margin')) ?></dt><dd><?= an_pct($company['margin']) ?></dd>
            </dl>
            <?= an_status_badge($company['status']) ?>
            <small class="an-sub"><?= current_locale() === 'am' ? 'ሁልጊዜ ሁሉንም ክፍሎች ያካትታል (ማጣሪያ አይለውጠውም)' : 'Always company-wide (filters do not change it)' ?></small>
        </article>
        <?php foreach ($visible as $u): $r = $units[$u]; ?>
            <a class="an-card an-card-link" href="<?= an_link('analytics_unit.php', ['unit' => $u], $f) ?>">
                <h3><?= h(an_t($u)) ?> →</h3>
                <dl>
                    <?php if ($u === 'feed_production' || $u === 'veterinary'): ?>
                        <dt><?= h(an_t('cost')) ?></dt><dd><?= an_money($r['expenses']) ?></dd>
                        <?php if ($u === 'feed_production'): ?>
                            <dt><?= h(an_t('production')) ?></dt><dd><?= an_num($acc->total('feed_production', 'feed_harvest_kg'), 1) ?> kg</dd>
                        <?php else: ?>
                            <dt><?= h(an_t('medicine_usage')) ?></dt><dd><?= an_money((int)$acc->total('veterinary', 'medicine_value')) ?></dd>
                        <?php endif; ?>
                    <?php else: ?>
                        <dt><?= h(an_t('income')) ?></dt><dd><?= an_money($r['income']) ?></dd>
                        <dt><?= h(an_t('expenses')) ?></dt><dd><?= an_money($r['expenses']) ?></dd>
                        <dt><?= h(an_t('profit')) ?></dt><dd class="<?= an_sign_class($r['profit']) ?>"><?= an_money($r['profit'], true) ?></dd>
                        <dt><?= h(an_t('margin')) ?></dt><dd><?= an_pct($r['margin']) ?></dd>
                    <?php endif; ?>
                </dl>
                <?= an_status_badge($r['status']) ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$visible): ?><p class="an-empty"><?= h(an_t('no_data')) ?></p><?php endif; ?>
    </section>

    <!-- ===================== Grouped table (two-row header, colspan) ===================== -->
    <section class="an-section">
        <h2><?= current_locale() === 'am' ? 'በወር (የኢትዮጵያ ወር) ዝርዝር' : 'By Ethiopian month' ?></h2>
        <div class="an-scroll">
            <table class="an-table" id="anGrouped">
                <thead>
                    <tr class="an-grp">
                        <th rowspan="2" class="an-sticky"><?= current_locale() === 'am' ? 'ወር' : 'Month' ?></th>
                        <?php foreach ($visible as $u): ?>
                            <th colspan="<?= $colsFor[$u] ?>" class="an-g-<?= h($u) ?>"><a href="<?= an_link('analytics_unit.php', ['unit' => $u], $f) ?>"><?= h(an_t($u)) ?></a></th>
                        <?php endforeach; ?>
                        <th colspan="2" class="an-g-shared"><?= h(an_t('shared')) ?> / <?= h(an_t('unattributed')) ?></th>
                        <th colspan="4" class="an-g-company"><?= h(an_t('company')) ?></th>
                    </tr>
                    <tr class="an-sub">
                        <?php foreach ($visible as $u): ?>
                            <?php if ($u === 'dairy' || $u === 'poultry' || $u === 'fish'): ?>
                                <th><?= h(an_t('income')) ?></th><th><?= h(an_t('expenses')) ?></th><th><?= h(an_t('profit')) ?></th><th><?= h(an_t('margin')) ?></th>
                                <th><?= h($u === 'dairy' ? an_t('production') : ($u === 'poultry' ? an_t('eggs_flock') : an_t('harvest_pond'))) ?></th>
                            <?php elseif ($u === 'feed_production'): ?>
                                <th><?= h(an_t('production')) ?></th><th><?= h(an_t('internal_dist')) ?></th><th><?= h(an_t('cost')) ?></th><th><?= h(an_t('remaining')) ?></th>
                            <?php else: ?>
                                <th><?= h(an_t('treat_costs')) ?></th><th><?= h(an_t('medicine_usage')) ?></th><th><?= h(an_t('health_ind')) ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <th><?= h(an_t('income')) ?></th><th><?= h(an_t('expenses')) ?></th>
                        <th><?= h(an_t('income')) ?></th><th><?= h(an_t('expenses')) ?></th><th><?= h(an_t('profit')) ?></th><th><?= h(an_t('margin')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($months as $mk => $_): ?>
                    <?php
                    $cInc = 0; $cExp = 0;
                    foreach (AN_ALL_BUCKET_UNITS as $bu) { $cInc += (int)$mv($mk, $bu, 'sales'); $cExp += (int)$mv($mk, $bu, 'expenses'); }
                    $cProf = $cInc - $cExp;
                    $shInc = (int)($mv($mk, AN_SHARED, 'sales') + $mv($mk, AN_UNATTR, 'sales'));
                    $shExp = (int)($mv($mk, AN_SHARED, 'expenses') + $mv($mk, AN_UNATTR, 'expenses'));
                    if ($cInc === 0 && $cExp === 0 && !array_filter(array_map(fn($u) => array_sum($months[$mk][$u] ?? []), AN_UNITS))) { continue; }
                    ?>
                    <tr>
                        <th class="an-sticky"><?= h(an_month_label($mk)) ?></th>
                        <?php foreach ($visible as $u): $g = fn(string $m) => $mv($mk, $u, $m); ?>
                            <?php if ($u === 'dairy' || $u === 'poultry' || $u === 'fish'):
                                $i = (int)$g('sales'); $e = (int)$g('expenses'); $p = $i - $e; ?>
                                <td class="num"><?= an_money($i) ?></td>
                                <td class="num"><?= an_money($e) ?></td>
                                <td class="num <?= an_sign_class($p) ?>"><?= an_money($p, true) ?></td>
                                <td class="num"><?= an_pct($i > 0 ? $p / $i * 100 : null) ?></td>
                                <td class="num"><?= $cellProd($u, $g) ?></td>
                            <?php elseif ($u === 'feed_production'): ?>
                                <td class="num"><?= an_num($g('feed_harvest_kg'), 1) ?> kg</td>
                                <td class="num"><?= an_num($monthSum($mk, 'feed_issued_qty'), 1) ?><br><small><?= an_money((int)$monthSum($mk, 'feed_issued_value')) ?></small></td>
                                <td class="num"><?= an_money((int)$g('expenses')) ?></td>
                                <td class="num">—</td>
                            <?php else: ?>
                                <td class="num"><?= an_money((int)$g('expenses')) ?></td>
                                <td class="num"><?= an_money((int)$g('medicine_value')) ?><br><small><?= an_num($g('medicine_issue_n')) ?> ×</small></td>
                                <td class="num"><small>💉 <?= an_num($g('vet_vaccinations')) ?> · 💊 <?= an_num($g('vet_treatments')) ?> · 🦠 <?= an_num($g('vet_incidents')) ?></small></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <td class="num"><?= an_money($shInc) ?></td>
                        <td class="num"><?= an_money($shExp) ?></td>
                        <td class="num"><?= an_money($cInc) ?></td>
                        <td class="num"><?= an_money($cExp) ?></td>
                        <td class="num <?= an_sign_class($cProf) ?>"><?= an_money($cProf, true) ?></td>
                        <td class="num"><?= an_pct($cInc > 0 ? $cProf / $cInc * 100 : null) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$months): ?>
                    <tr><td colspan="40" class="an-empty"><?= h(an_t('no_data')) ?></td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="an-total">
                        <th class="an-sticky"><?= h(an_t('period')) ?></th>
                        <?php foreach ($visible as $u): $r = $units[$u]; $g = fn(string $m) => (float)$acc->total($u, $m); ?>
                            <?php if ($u === 'dairy' || $u === 'poultry' || $u === 'fish'): ?>
                                <td class="num"><?= an_money($r['income']) ?></td>
                                <td class="num"><?= an_money($r['expenses']) ?></td>
                                <td class="num <?= an_sign_class($r['profit']) ?>"><?= an_money($r['profit'], true) ?><br><?= an_status_badge($r['status']) ?></td>
                                <td class="num"><?= an_pct($r['margin']) ?></td>
                                <td class="num"><?= $cellProd($u, $g) ?>
                                    <?php if ($u === 'poultry'): ?><br><small>🐓 <?= an_num($snap['birds_live']) ?> / <?= an_num($snap['flocks_active']) ?> <?= current_locale() === 'am' ? 'መንጋ' : 'flocks' ?></small>
                                    <?php elseif ($u === 'fish'): ?><br><small><?= an_num($snap['ponds_stocked']) ?> <?= current_locale() === 'am' ? 'የተሞሉ ኩሬዎች' : 'stocked ponds' ?></small>
                                    <?php else: ?><br><small>🐄 <?= an_num($snap['cows_active']) ?> (<?= an_num($snap['cows_sick']) ?> <?= current_locale() === 'am' ? 'የታመሙ' : 'sick' ?>)</small><?php endif; ?></td>
                            <?php elseif ($u === 'feed_production'):
                                $issQ = 0; $issV = 0; foreach (AN_ALL_BUCKET_UNITS as $bu) { $issQ += $acc->total($bu, 'feed_issued_qty'); $issV += (int)$acc->total($bu, 'feed_issued_value'); } ?>
                                <td class="num"><?= an_num($g('feed_harvest_kg'), 1) ?> kg</td>
                                <td class="num"><?= an_num($issQ, 1) ?><br><small><?= an_money($issV) ?></small></td>
                                <td class="num"><?= an_money($r['expenses']) ?></td>
                                <td class="num"><?= an_num($snap['feed_qty'], 1) ?><br><small><?= an_money((int)$snap['feed_cents']) ?></small>
                                    <?php if ($snap['feed_unpriced_qty'] > 0): ?><br><small class="an-neg">⚠ <?= an_num($snap['feed_unpriced_qty'], 1) ?> <?= current_locale() === 'am' ? 'ዋጋ የሌለው' : 'unpriced' ?></small><?php endif; ?></td>
                            <?php else: ?>
                                <td class="num"><?= an_money($r['expenses']) ?></td>
                                <td class="num"><?= an_money((int)$g('medicine_value')) ?><br><small><?= an_num($g('medicine_issue_n')) ?> ×</small></td>
                                <td class="num"><small>💉 <?= an_num($g('vet_vaccinations')) ?> · 💊 <?= an_num($g('vet_treatments')) ?> · 🦠 <?= an_num($g('vet_incidents')) ?> · 🔒 <?= an_num($g('vet_quarantines')) ?></small></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php $shI = (int)($acc->total(AN_SHARED, 'sales') + $acc->total(AN_UNATTR, 'sales')); $shE = (int)($acc->total(AN_SHARED, 'expenses') + $acc->total(AN_UNATTR, 'expenses')); ?>
                        <td class="num"><?= an_money($shI) ?></td>
                        <td class="num"><?= an_money($shE) ?></td>
                        <td class="num"><?= an_money($company['income']) ?></td>
                        <td class="num"><?= an_money($company['expenses']) ?></td>
                        <td class="num <?= an_sign_class($company['profit']) ?>"><?= an_money($company['profit'], true) ?><br><?= an_status_badge($company['status']) ?></td>
                        <td class="num"><?= an_pct($company['margin']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="an-hint"><?= current_locale() === 'am'
            ? 'የድርጅት ጠቅላላ = ሁሉም ክፍሎች + የጋራ ወጪ፣ እያንዳንዱ ብር አንድ ጊዜ ብቻ። ወተትና እንቁላል ለሱቅ የተላከው እንደ ውስጥ ዝውውር ተለይቷል።'
            : 'Company total = every unit + shared/unattributed, each amount counted exactly once. Feed, veterinary and shared costs are cost centres: their cost is already inside the company expenses.' ?></p>
    </section>

    <!-- ===================== Five-way reconciliation ===================== -->
    <section class="an-section">
        <h2><?= h(an_t('recon_title')) ?></h2>
        <div class="an-scroll">
        <table class="an-table an-recon">
            <thead><tr>
                <th><?= h(an_t('unit')) ?></th><th><?= h(an_t('r_phys')) ?></th><th><?= h(an_t('r_sales')) ?></th>
                <th><?= h(an_t('r_cash')) ?></th><th><?= h(an_t('receivable')) ?></th><th><?= h(an_t('r_exp')) ?></th><th><?= h(an_t('r_profit')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach (AN_UNITS as $u): $r = $units[$u];
                if ($f['unit'] !== '' && $f['unit'] !== $u) { continue; }
                $phys = match ($u) {
                    'dairy' => an_num($acc->total('dairy', 'milk_l'), 1) . ' L',
                    'poultry' => an_num($acc->total('poultry', 'eggs_total')) . ' ' . (current_locale() === 'am' ? 'እንቁላል' : 'eggs'),
                    'fish' => an_num($acc->total('fish', 'fish_harvest_kg'), 1) . ' kg',
                    'feed_production' => an_num($acc->total('feed_production', 'feed_harvest_kg'), 1) . ' kg',
                    default => '—',
                }; ?>
                <tr>
                    <th><?= h(an_t($u)) ?></th>
                    <td class="num"><?= h($phys) ?></td>
                    <td class="num"><?= an_money($r['income']) ?></td>
                    <td class="num"><?= an_money($r['receipts']['total']) ?></td>
                    <td class="num"><?= an_money($r['receivable']) ?></td>
                    <td class="num"><?= an_money($r['expenses']) ?></td>
                    <td class="num <?= an_sign_class($r['profit']) ?>"><?= $r['profit'] === null ? '<small>' . h(an_t('s_' . $r['status'])) . '</small>' : an_money($r['profit'], true) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="an-total">
                    <th><?= h(an_t('company')) ?></th><td class="num">—</td>
                    <td class="num"><?= an_money($company['income']) ?></td>
                    <td class="num"><?= an_money($company['receipts']['total']) ?></td>
                    <td class="num"><?= an_money($company['receivable']) ?></td>
                    <td class="num"><?= an_money($company['expenses']) ?></td>
                    <td class="num <?= an_sign_class($company['profit']) ?>"><?= an_money($company['profit'], true) ?></td>
                </tr>
            </tbody>
        </table>
        </div>
        <?php $mm = $company['memo']; ?>
        <div class="an-note an-n-info">
            <strong><?= h(an_t('internal_excl')) ?>:</strong>
            <?= current_locale() === 'am' ? 'ወደ ሱቆች የተላከ' : 'Shop transfers' ?>: <?= an_money($mm['shop_transfers']) ?> ·
            <?= current_locale() === 'am' ? 'ከሱቆች የተቀበለ' : 'Shop settlements received' ?>: <?= an_money($mm['shop_settlements']) ?> ·
            <?= current_locale() === 'am' ? 'የባለቤት ወጪ' : 'Owner drawings' ?>: <?= an_money($mm['owner_drawings']) ?> ·
            <?= current_locale() === 'am' ? 'ያልተመደበ ሽያጭ' : 'Unclassified sales' ?>: <?= an_money($mm['unclassified_sales']) ?>
            <br><small><?= current_locale() === 'am' ? 'የሱቅ ሽያጭ አያያዝ' : 'Shop dispatch treatment' ?>: <strong><?= h($o['treatment']) ?></strong></small>
        </div>
    </section>

    <!-- ===================== Ledger classification ===================== -->
    <section class="an-section">
        <h2><?= h(an_t('ledger_title')) ?></h2>
        <div class="an-scroll">
        <table class="an-table">
            <thead><tr><th>Class</th><th><?= current_locale() === 'am' ? 'አቅጣጫ' : 'Direction' ?></th><th>#</th><th><?= current_locale() === 'am' ? 'መጠን' : 'Amount' ?></th><th><?= current_locale() === 'am' ? 'አያያዝ' : 'Treatment' ?></th></tr></thead>
            <tbody>
            <?php foreach ($o['ledger'] as $cls => $row): ?>
                <tr>
                    <td><?= h($cls) ?></td><td><?= h($row['direction'] ?? '') ?></td>
                    <td class="num"><?= an_num($row['n'] ?? 0) ?></td>
                    <td class="num"><?= an_money((int)($row['cents'] ?? 0)) ?></td>
                    <td><small><?= h($row['treatment'] ?? '') ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$o['ledger']): ?><tr><td colspan="5" class="an-empty"><?= h(an_t('no_data')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <!-- ===================== Data quality ===================== -->
    <section class="an-section">
        <h2>⚠ <?= h(an_t('dq_title')) ?></h2>
        <?php $dq = an_dq_merge($o['dq']); ?>
        <?php if (!$dq): ?><p class="an-empty"><?= h(an_t('no_data')) ?></p><?php endif; ?>
        <ul class="an-dq">
        <?php foreach ($dq as $d): [$am, $en] = an_dq_text($d['code']); ?>
            <li class="an-dq-<?= h($d['severity']) ?>">
                <span class="an-badge an-b-<?= $d['severity'] === 'warn' ? 'warn' : 'info' ?>"><?= $d['severity'] === 'warn' ? '⚠' : 'ℹ' ?></span>
                <strong><?= h($d['unit'] ?: '—') ?></strong>
                <?= h(current_locale() === 'am' ? $am : $en) ?>
                <small>(<?= (int)$d['count'] ?> × <?= $d['cents'] ? an_money($d['cents']) : '' ?><?= $d['qty'] ? ' ' . an_num($d['qty'], 1) . ' qty' : '' ?><?= $d['extra'] !== '' ? ' · ' . h($d['extra']) : '' ?>)</small>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>

    <!-- ===================== Search results ===================== -->
    <?php if ($search !== null): ?>
    <section class="an-section">
        <h2>🔎 "<?= h($f['q']) ?>"</h2>
        <?php foreach ($search['cows'] as $c): ?>
            <div><a href="<?= an_link('analytics_cow.php', ['id' => $c['id']], $f) ?>">🐄 <?= h($c['tag_number']) ?> — <?= h($c['name']) ?></a></div>
        <?php endforeach; ?>
        <?php foreach ($search['flocks'] as $c): ?>
            <div><a href="<?= an_link('analytics_flock.php', ['id' => $c['id']], $f) ?>">🐓 #<?= (int)$c['id'] ?> — <?= h($c['breed']) ?></a></div>
        <?php endforeach; ?>
        <?php foreach ($search['ponds'] as $c): ?>
            <div><a href="<?= an_link('analytics_pond.php', ['id' => $c['id']], $f) ?>">🐟 #<?= (int)$c['id'] ?> — <?= h($c['name']) ?></a></div>
        <?php endforeach; ?>
        <?php foreach ($search['items'] as $c): ?>
            <div>📦 <?= h($c['sku']) ?> — <?= h($c['name']) ?> <small>(<?= an_num($c['on_hand'], 1) ?> <?= h($c['unit']) ?>)</small></div>
        <?php endforeach; ?>
        <?php if (!$search['cows'] && !$search['flocks'] && !$search['ponds'] && !$search['items']): ?><p class="an-empty"><?= h(an_t('no_data')) ?></p><?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ===================== Individual records ===================== -->
    <?php if ($cows): ?>
    <section class="an-section">
        <h2>🐄 <?= current_locale() === 'am' ? 'ላሞች' : 'Cows' ?> <small>(<?= count($cows) ?>)</small></h2>
        <div class="an-scroll"><table class="an-table">
            <thead><tr><th class="an-sticky">ID</th><th><?= current_locale() === 'am' ? 'ስም' : 'Name' ?></th><th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th><th><?= h(an_t('period')) ?> L</th><th><?= h(an_t('lifetime')) ?> L</th><th><?= current_locale() === 'am' ? 'የጤና መዝገብ' : 'Health records' ?></th></tr></thead>
            <tbody>
            <?php foreach ($cows as $c): ?>
                <tr>
                    <th class="an-sticky"><a href="<?= an_link('analytics_cow.php', ['id' => $c['id']], $f) ?>"><?= h($c['tag_number']) ?></a></th>
                    <td><?= h($c['name']) ?></td><td><?= h($c['exit_type'] ?: $c['lifecycle_status']) ?></td>
                    <td class="num"><?= an_num($c['l_period'], 1) ?></td><td class="num"><?= an_num($c['l_life'], 1) ?></td><td class="num"><?= an_num($c['health_n']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </section>
    <?php endif; ?>

    <?php if ($flocks): ?>
    <section class="an-section">
        <h2>🐓 <?= current_locale() === 'am' ? 'መንጋዎች' : 'Flocks' ?> <small>(<?= count($flocks) ?>)</small></h2>
        <div class="an-scroll"><table class="an-table">
            <thead><tr><th class="an-sticky">ID</th><th><?= current_locale() === 'am' ? 'ዝርያ' : 'Breed' ?></th><th><?= current_locale() === 'am' ? 'ሕያው' : 'Live' ?></th><th>🥚 <?= h(an_t('period')) ?></th><th>☠</th></tr></thead>
            <tbody>
            <?php foreach ($flocks as $c): ?>
                <tr>
                    <th class="an-sticky"><a href="<?= an_link('analytics_flock.php', ['id' => $c['id']], $f) ?>">#<?= (int)$c['id'] ?></a></th>
                    <td><?= h($c['breed']) ?></td><td class="num"><?= an_num($c['live_pop']) ?></td>
                    <td class="num"><?= an_num($c['whole'] + $c['cracked'] + $c['broken']) ?></td><td class="num"><?= an_num($c['mort_period']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </section>
    <?php endif; ?>

    <?php if ($ponds): ?>
    <section class="an-section">
        <h2>🐟 <?= current_locale() === 'am' ? 'ኩሬዎች' : 'Ponds' ?> <small>(<?= count($ponds) ?>)</small></h2>
        <div class="an-scroll"><table class="an-table">
            <thead><tr><th class="an-sticky">ID</th><th><?= current_locale() === 'am' ? 'ስም' : 'Name' ?></th><th><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th><th>kg <?= h(an_t('period')) ?></th><th>☠</th></tr></thead>
            <tbody>
            <?php foreach ($ponds as $c): ?>
                <tr>
                    <th class="an-sticky"><a href="<?= an_link('analytics_pond.php', ['id' => $c['id']], $f) ?>">#<?= (int)$c['id'] ?></a></th>
                    <td><?= h($c['name']) ?></td><td><?= h($c['status']) ?></td>
                    <td class="num"><?= an_num($c['harvest_kg'], 1) ?></td><td class="num"><?= an_num($c['mort_period']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </section>
    <?php endif; ?>

    <?php if ($plots): ?>
    <section class="an-section">
        <h2>🌾 <?= current_locale() === 'am' ? 'የመኖ ማሳዎች' : 'Feed plots' ?> <small>(<?= count($plots) ?>)</small></h2>
        <div class="an-scroll"><table class="an-table">
            <thead><tr><th class="an-sticky">ID</th><th><?= current_locale() === 'am' ? 'ስም' : 'Name' ?></th><th>ha</th><th><?= current_locale() === 'am' ? 'ሰብል' : 'Crops' ?></th><th>kg <?= h(an_t('period')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($plots as $c): ?>
                <tr><th class="an-sticky">#<?= (int)$c['id'] ?></th><td><?= h($c['name']) ?></td><td class="num"><?= an_num($c['area_hectares'], 2) ?></td><td><?= h($c['crops']) ?></td><td class="num"><?= an_num($c['harvest_kg'], 1) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </section>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
