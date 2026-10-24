<?php
/** Super Admin - one poultry flock. */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics/analytics_ui.php';

analytics_guard();
$id = an_id_param();
$f = an_parse_filters($_GET);
$d = $id > 0 ? an_flock_detail(db(), $id, $f['from'], $f['to']) : null;
if (!$d) {
    an_audit('detail_not_found', 'poultry_flocks', $id, 'analytics_flock');
    http_response_code(404);
    die('<h2>404 - ' . h(an_t('not_found')) . '</h2><p><a href="<?= BASE_URL ?>/modules/admin/analytics.php">' . h(an_t('back')) . '</a></p>');
}
an_audit('view_flock', 'poultry_flocks', $id, "from={$f['from']} to={$f['to']}");
$fl = $d['flock'];
$e = $d['eggs'];
$loc = current_locale() === 'am';

require_once __DIR__ . '/../../includes/header.php';
an_detail_head('🐓 #' . $id . ' — ' . $fl['breed'], $f, BASE_URL . '/modules/admin/analytics_flock.php', ['id' => $id]);
an_kv([
    [$loc ? 'ቤት' : 'House', h((string)$fl['house_name'])],
    [$loc ? 'የተጀመረበት' : 'Stocked', h(an_eth_label($fl['stocking_date']))],
    [$loc ? 'መጀመሪያ ብዛት' : 'Initial birds', an_num($fl['initial_population'])],
    [$loc ? 'ሕያው አሁን' : 'Live now', an_num($fl['live_pop'])],
    [$loc ? 'ሞት (ጠቅላላ)' : 'Mortality (lifetime)', an_num($fl['cumulative_mortality']) . ' (' . an_pct($fl['initial_population'] > 0 ? $fl['cumulative_mortality'] / $fl['initial_population'] * 100 : null) . ')'],
    [$loc ? 'ሁኔታ' : 'Status', h($fl['status'])],
]);
echo '<h2>🥚 ' . h(an_t('production')) . ' <small>(' . h(an_t('r_phys')) . ')</small></h2>';
an_kv([
    [an_t('period') . ': ' . ($loc ? 'ጤናማ/የተሰነጠቀ/የተሰበረ' : 'whole/cracked/broken'), an_num($e['whole_p']) . ' / ' . an_num($e['cracked_p']) . ' / ' . an_num($e['broken_p'])],
    [an_t('lifetime'), an_num($e['whole_life']) . ' / ' . an_num($e['cracked_life']) . ' / ' . an_num($e['broken_life'])],
    [$loc ? 'እንቁላል በወፍ በቀን' : 'Eggs per bird per day', $d['eggs_per_bird_day'] === null ? '—' : an_num($d['eggs_per_bird_day'], 2)],
    [$loc ? 'የተሰጠ ድርሻ' : 'Share of unit eggs', an_pct($d['share'] * 100)],
]);
echo '<div class="an-note an-n-info">' . h($loc ? 'እንቁላል ብዛት አካላዊ ምርት ነው እንጂ ገቢ አይደለም። ግምታዊ ድርሻ በእንቁላል ድርሻ ብቻ ነው።' : 'Egg counts are physical production, not income. The allocated value is an estimate by egg share of the unit\'s sales.') . '</div>';
an_kv([[$loc ? 'የተመደበ ግምታዊ ሽያጭ' : 'Allocated sales estimate', an_money((int)$d['allocated_value_cents']) . ' <small>(' . h($loc ? 'ግምት' : 'estimate') . ')</small>']]);
an_missing_box($d['missing']);
echo '<h2>' . h($loc ? 'ዕለታዊ እንቁላል' : 'Daily eggs') . '</h2>';
an_rows_table($d['daily'], ['d' => $loc ? 'ቀን' : 'Date', 'whole' => 'Whole', 'cracked' => 'Cracked', 'broken' => 'Broken'], ['d' => fn($v) => h(an_eth_label($v))]);
echo '<h2>☠ ' . h($loc ? 'ሞት' : 'Mortality') . '</h2>';
an_rows_table($d['mortality'], ['log_date' => $loc ? 'ቀን' : 'Date', 'count' => '#', 'cause_code' => $loc ? 'ምክንያት' : 'Cause'], ['log_date' => fn($v) => h(an_eth_label($v))]);
echo '<h2>' . h($loc ? 'ጤናና ክትባት' : 'Health and vaccinations') . '</h2>';
an_rows_table($d['health'], array_combine(array_keys($d['health'][0] ?? ['_' => 1]), array_keys($d['health'][0] ?? ['_' => 1])));
an_rows_table($d['vaccinations'], array_combine(array_keys($d['vaccinations'][0] ?? ['_' => 1]), array_keys($d['vaccinations'][0] ?? ['_' => 1])));
echo '</div>';
require_once __DIR__ . '/../../includes/footer.php';
