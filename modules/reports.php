<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/charts.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['super_admin', 'general_manager']);
require_once __DIR__ . '/../includes/header.php';

// ---- Milk yield, last 7 days ----
$milkRows = db()->query(
    "SELECT s.session_date AS d, COALESCE(SUM(e.yield_liters),0) AS total
     FROM dairy_milking_sessions s
     LEFT JOIN dairy_milk_yield_entries e ON e.milking_session_id = s.id
     WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY s.session_date ORDER BY s.session_date"
)->fetchAll();
$milkByDate = [];
foreach ($milkRows as $r) { $milkByDate[$r['d']] = (float) $r['total']; }
$milkLabels = $milkValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $milkLabels[] = date('D', strtotime($d));
    $milkValues[] = round($milkByDate[$d] ?? 0, 1);
}

// ---- Egg collection, last 7 days ----
$eggRows = db()->query(
    "SELECT collection_date AS d, SUM(whole_count + cracked_count + broken_count) AS total
     FROM poultry_egg_collections
     WHERE collection_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY collection_date ORDER BY collection_date"
)->fetchAll();
$eggByDate = [];
foreach ($eggRows as $r) { $eggByDate[$r['d']] = (int) $r['total']; }
$eggLabels = $eggValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $eggLabels[] = date('D', strtotime($d));
    $eggValues[] = $eggByDate[$d] ?? 0;
}

// ---- Cash flow: inflow vs outflow, last 6 months ----
$cashRows = db()->query(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym, direction, SUM(amount) AS total
     FROM fin_transactions
     WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY ym, direction ORDER BY ym"
)->fetchAll();
$cashByMonth = [];
foreach ($cashRows as $r) { $cashByMonth[$r['ym']][$r['direction']] = (float) $r['total']; }
$cashLabels = $cashIn = $cashOut = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-{$i} months"));
    $cashLabels[] = date('M', strtotime($ym . '-01'));
    $cashIn[] = round($cashByMonth[$ym]['inflow'] ?? 0);
    $cashOut[] = round($cashByMonth[$ym]['outflow'] ?? 0);
}

// ---- Warehouse stock levels by item ----
$stockRows = db()->query(
    "SELECT i.name, COALESCE(SUM(b.quantity_on_hand),0) AS total
     FROM wh_stock_items i LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id
     GROUP BY i.id ORDER BY total DESC LIMIT 8"
)->fetchAll();
$stockLabels = array_map(fn ($r) => mb_strimwidth($r['name'], 0, 10, '…'), $stockRows);
$stockValues = array_map(fn ($r) => round((float) $r['total'], 1), $stockRows);

// ---- Top expense categories (approved, all-time) ----
$expenseRows = db()->query(
    "SELECT category, SUM(amount) AS total FROM fin_expenses WHERE status IN ('approved','paid') GROUP BY category ORDER BY total DESC LIMIT 6"
)->fetchAll();
$expenseLabels = array_map(fn ($r) => mb_strimwidth($r['category'], 0, 12, '…'), $expenseRows);
$expenseValues = array_map(fn ($r) => round((float) $r['total']), $expenseRows);
?>
<h2 class="mt-0">📈 <?= h(t('nav.reports', 'Reports & Analytics')) ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'የድርጅቱ አጠቃላይ የቀጥታ መረጃ ትንታኔ እና ገላጭ ግራፎች — የወተት ምርት፣ የእንቁላል ስብሰባ፣ የገንዘብ ፍሰት እና የክምችት መጠን' : 'Company-wide view, built from live data across every module — no separate export step needed.' ?></p>

<div class="card no-print">
    <button class="btn secondary" onclick="window.print()">🖨️ <?= current_locale() === 'am' ? 'ሪፖርቱን አትም / በ PDF አስቀምጥ' : 'Print / Save as PDF' ?></button>
    <p class="text-muted" style="margin-top:8px;margin-bottom:0"><?= current_locale() === 'am' ? 'የኮምፒውተርዎን ፕሪንተር ወይም "Save as PDF" በመምረጥ በቀላሉ ፒዲኤፍ ማውጣት ይችላሉ።' : 'Uses your browser\'s own print dialog — choose "Save as PDF" as the destination. No extra software needed.' ?></p>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የወተት ምርት — ያለፉት 7 ቀናት (በሊትር)' : 'Milk Production — Last 7 Days (liters)' ?></h3>
    <?= svg_line_chart($milkLabels, $milkValues, 'L') ?>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የእንቁላል ስብሰባ — ያለፉት 7 ቀናት (በቁጥር)' : 'Egg Collection — Last 7 Days (count)' ?></h3>
    <?= svg_bar_chart($eggLabels, $eggValues, current_locale() === 'am' ? 'እንቁላል' : 'eggs') ?>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የገንዘብ ፍሰት ገቢ እና ወጪ — ያለፉት 6 ወራት (በብር)' : 'Cash Flow — Last 6 Months (ETB)' ?></h3>
    <?= svg_grouped_bar_chart($cashLabels, $cashIn, $cashOut, current_locale() === 'am' ? 'ገቢ' : 'Inflow', current_locale() === 'am' ? 'ወጪ' : 'Outflow') ?>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'የመጋዘን ክምችት በዕቃ ዓይነት' : 'Warehouse Stock by Item (current)' ?></h3>
    <?= svg_bar_chart($stockLabels, $stockValues) ?>
</div>

<div class="card">
    <h3><?= current_locale() === 'am' ? 'ዋና ዋና የወጪ ዓይነቶች (የተፈቀዱ ወጪዎች በብር)' : 'Top Expense Categories (approved, all-time, ETB)' ?></h3>
    <?= svg_bar_chart($expenseLabels, $expenseValues) ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
