<?php
/** Super Admin - one cow: permanent history + period figures. */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics/analytics_ui.php';

analytics_guard();
$id = an_id_param();
$f = an_parse_filters($_GET);
$d = $id > 0 ? an_cow_detail(db(), $id, $f['from'], $f['to']) : null;
if (!$d) {
    an_audit('detail_not_found', 'dairy_cows', $id, 'analytics_cow');
    http_response_code(404);
    die('<h2>404 - ' . h(an_t('not_found')) . '</h2><p><a href="/aleltu-eifms/modules/admin/analytics.php">' . h(an_t('back')) . '</a></p>');
}
an_audit('view_cow', 'dairy_cows', $id, "from={$f['from']} to={$f['to']}");
$c = $d['cow'];
$m = $d['milk'];

require_once __DIR__ . '/../../includes/header.php';
an_detail_head('🐄 ' . $c['tag_number'] . ' — ' . $c['name'], $f, '/aleltu-eifms/modules/admin/analytics_cow.php', ['id' => $id]);
$loc = current_locale() === 'am';
an_kv([
    [$loc ? 'ዘር' : 'Breed', h($c['breed'] ?? '—')],
    [$loc ? 'ጾታ' : 'Sex', h($c['sex'])],
    [$loc ? 'የትውልድ ቀን' : 'Born', h(an_eth_label($c['date_of_birth']))],
    [$loc ? 'የተገኘበት' : 'Acquired', h(an_eth_label($c['acquired_date'])) . ' · ' . h((string)$c['source'])],
    [$loc ? 'ቡድን' : 'Group', h((string)$c['group_name'])],
    [$loc ? 'ሁኔታ' : 'Status', h($c['lifecycle_status']) . ($c['is_sick'] ? ' 🤒' : '')],
]);
echo '<h2>🥛 ' . h(an_t('production')) . ' <small>(' . h(an_t('r_phys')) . ')</small></h2>';
an_kv([
    [an_t('period') . ' L', an_num($m['l_period'] ?? 0, 1)],
    [an_t('lifetime') . ' L', an_num($m['l_life'] ?? 0, 1)],
    [$loc ? 'ጠዋት / ማታ (ጊዜው)' : 'Morning / evening (period)', an_num($m['am_period'] ?? 0, 1) . ' / ' . an_num($m['pm_period'] ?? 0, 1)],
    [$loc ? 'የወተት ቀናት' : 'Milking days (period)', an_num($m['days_period'] ?? 0)],
    [$loc ? 'የመጀመሪያ / የመጨረሻ' : 'First / last record', h(an_eth_label($m['first_d'] ?? null)) . ' / ' . h(an_eth_label($m['last_d'] ?? null))],
    [$loc ? 'የተሰጠ ድርሻ' : 'Share of unit litres', an_pct($d['share'] * 100)],
]);
echo '<div class="an-note an-n-info">' . h($loc ? 'ይህ አካላዊ ምርት ነው፤ ገቢ አይደለም። ከታች ያለው ዋጋ የሚገመተው በሊትር ድርሻ ብቻ ነው (ግምት)።' : 'This is physical production, not income. The value below is only an ESTIMATE allocated by litre share of the unit\'s recorded milk sales - it is not this cow\'s actual sale.') . '</div>';
an_kv([[$loc ? 'የተመደበ ግምታዊ ሽያጭ' : 'Allocated sales estimate', an_money((int)$d['allocated_value_cents']) . ' <small>(' . h($loc ? 'ግምት' : 'estimate') . ')</small>']]);
an_missing_box($d['missing']);

echo '<h2>' . h($loc ? 'ዕለታዊ ወተት' : 'Daily milk') . '</h2>';
an_rows_table($d['daily'], ['d' => $loc ? 'ቀን' : 'Date', 'l' => 'L', 'flagged' => $loc ? 'ያልተለመደ' : 'Flagged'], ['d' => fn($v) => h(an_eth_label($v)), 'l' => fn($v) => number_format((float)$v, 2)]);
echo '<h2>' . h($loc ? 'የጤና መዝገብ' : 'Health records') . '</h2>';
an_rows_table($d['health'], ['d' => $loc ? 'ቀን' : 'Date', 'cond' => $loc ? 'ሁኔታ' : 'Condition', 'treatment' => $loc ? 'ሕክምና' : 'Treatment'], ['d' => fn($v) => h(an_eth_label($v))]);
echo '<h2>' . h($loc ? 'ክትባቶች' : 'Vaccinations') . '</h2>';
an_rows_table($d['vaccinations'], ['v' => $loc ? 'ክትባት' : 'Vaccine', 'sd' => $loc ? 'የታቀደ' : 'Scheduled', 'ad' => $loc ? 'የተሰጠ' : 'Given', 'status' => 'Status'], ['sd' => fn($v) => h(an_eth_label($v)), 'ad' => fn($v) => h(an_eth_label($v))]);
echo '<h2>' . h($loc ? 'እርባታ፣ ጥጃ ወልዳ' : 'Breeding, calving, offspring') . '</h2>';
an_rows_table($d['breeding'], array_combine(array_keys($d['breeding'][0] ?? ['_' => 1]), array_keys($d['breeding'][0] ?? ['_' => 1])));
an_rows_table($d['calving'], array_combine(array_keys($d['calving'][0] ?? ['_' => 1]), array_keys($d['calving'][0] ?? ['_' => 1])));
an_rows_table($d['offspring'], array_combine(array_keys($d['offspring'][0] ?? ['_' => 1]), array_keys($d['offspring'][0] ?? ['_' => 1])));
echo '<h2>' . h($loc ? 'ሽያጭ / ሞት' : 'Sale / death') . '</h2>';
an_rows_table($d['exits'], array_combine(array_keys($d['exits'][0] ?? ['_' => 1]), array_keys($d['exits'][0] ?? ['_' => 1])));
an_rows_table($d['sale_receipts'], array_combine(array_keys($d['sale_receipts'][0] ?? ['_' => 1]), array_keys($d['sale_receipts'][0] ?? ['_' => 1])));
echo '</div>';
require_once __DIR__ . '/../../includes/footer.php';
