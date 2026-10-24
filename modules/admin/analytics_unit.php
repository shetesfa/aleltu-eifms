<?php
/** Super Admin - one business unit: where its income/expenses come from. */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics/analytics_ui.php';

analytics_guard();
$unit = (string)($_GET['unit'] ?? '');
if (!in_array($unit, AN_UNITS, true)) {
    http_response_code(404);
    die('<h2>404</h2><p><a href="<?= BASE_URL ?>/modules/admin/analytics.php">' . h(an_t('back')) . '</a></p>');
}
$pdo = db();
$f = an_parse_filters($_GET);
an_audit('view_unit', 'analytics', 0, "unit=$unit from={$f['from']} to={$f['to']}");
$o = an_overview($pdo, $f['from'], $f['to']);
$r = $o['units'][$unit];
$b = an_unit_breakdown($pdo, $unit, $f['from'], $f['to']);

require_once __DIR__ . '/../../includes/header.php';
an_detail_head(an_t($unit), $f, BASE_URL . '/modules/admin/analytics_unit.php', ['unit' => $unit]);

$kv = [
    [an_t('income'), an_money($r['income'])],
    [an_t('r_cash'), an_money($r['receipts']['total'])],
    [an_t('receivable'), an_money($r['receivable'])],
    [an_t('expenses'), an_money($r['expenses'])],
    [an_t('profit'), '<span class="' . an_sign_class($r['profit']) . '">' . an_money($r['profit'], true) . '</span> ' . an_status_badge($r['status'])],
    [an_t('margin'), an_pct($r['margin'])],
];
an_kv($kv);

$p = $r['exp_parts'];
echo '<h2>' . h(an_t('expenses')) . '</h2>';
an_rows_table([
    ['k' => current_locale() === 'am' ? 'የገንዘብ ማውጫ ቫውቸር' : 'Expense vouchers', 'v' => $p['voucher']],
    ['k' => current_locale() === 'am' ? 'ከመጋዘን የወጡ ዕቃዎች (በዋጋ)' : 'Stock consumed (at batch cost)', 'v' => $p['inputs']],
    ['k' => current_locale() === 'am' ? 'ደመወዝ (ጠቅላላ)' : 'Payroll (gross)', 'v' => $p['payroll']],
    ['k' => current_locale() === 'am' ? 'በመዝገብ ብቻ ያሉ ወጪዎች' : 'Ledger-only expenses', 'v' => $p['ledger_only']],
    ['k' => current_locale() === 'am' ? 'ብክነት / ማስተካከያ' : 'Stock write-offs', 'v' => $p['writeoff']],
], ['k' => '', 'v' => an_t('expenses')], ['v' => fn($v) => an_money((int)$v)]);

echo '<h2>' . (current_locale() === 'am' ? 'ቫውቸሮች' : 'Vouchers') . '</h2>';
an_rows_table($b['vouchers'], ['category' => 'Category', 'withdrawal_type' => 'Type', 'n' => '#', 'amt' => an_t('expenses')], ['amt' => fn($v) => number_format((float)$v, 2)]);

echo '<h2>' . (current_locale() === 'am' ? 'የተጠቀሙ ዕቃዎች' : 'Inputs consumed') . '</h2>';
an_rows_table($b['inputs'], ['sku' => 'SKU', 'name' => 'Item', 'qty' => 'Qty', 'unit' => 'Unit', 'val' => 'Value', 'unpriced_qty' => 'Unpriced qty'],
    ['qty' => fn($v) => number_format((float)$v, 2), 'val' => fn($v) => number_format((float)$v, 2)]);

echo '<h2>' . (current_locale() === 'am' ? 'ደመወዝ በመምሪያ' : 'Payroll by department') . '</h2>';
an_rows_table($b['payroll'], ['dept' => 'Department', 'staff' => 'Staff', 'gross' => 'Gross'], ['gross' => fn($v) => number_format((float)$v, 2)]);

echo '<h2>' . (current_locale() === 'am' ? 'ሽያጭ በዕቃና በሽያጭ ዓይነት' : 'Sales by item and channel') . '</h2>';
an_rows_table($b['sales'], ['name' => 'Item', 'ch' => 'Channel', 'n' => '#', 'qty' => 'Qty', 'unit' => 'Unit', 'amt' => 'Recorded', 'paid' => 'Received'],
    ['qty' => fn($v) => number_format((float)$v, 2), 'amt' => fn($v) => number_format((float)$v, 2), 'paid' => fn($v) => number_format((float)$v, 2),
     'ch' => fn($v) => h($v ?? 'unclassified')]);
echo '<p class="an-hint">' . h(current_locale() === 'am' ? 'የ"shop_transfer" ሽያጮች በውስጥ ዝውውር ቅንብር መሠረት ከገቢ ሊወጡ ይችላሉ።' : 'Rows with channel "shop_transfer" are excluded from income while the shop treatment is "internal".') . '</p>';

foreach (an_dq_merge($o['dq']) as $d) {
    if ($d['unit'] === $unit) {
        [$am, $en] = an_dq_text($d['code']);
        echo '<div class="an-note an-n-warn">' . h(current_locale() === 'am' ? $am : $en) . '</div>';
    }
}
echo '</div>';
require_once __DIR__ . '/../../includes/footer.php';
