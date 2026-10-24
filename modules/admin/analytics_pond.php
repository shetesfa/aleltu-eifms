<?php
/** Super Admin - one fish pond. */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/analytics/analytics_ui.php';

analytics_guard();
$id = an_id_param();
$f = an_parse_filters($_GET);
$d = $id > 0 ? an_pond_detail(db(), $id, $f['from'], $f['to']) : null;
if (!$d) {
    an_audit('detail_not_found', 'fish_ponds', $id, 'analytics_pond');
    http_response_code(404);
    die('<h2>404 - ' . h(an_t('not_found')) . '</h2><p><a href="<?= BASE_URL ?>/modules/admin/analytics.php">' . h(an_t('back')) . '</a></p>');
}
an_audit('view_pond', 'fish_ponds', $id, "from={$f['from']} to={$f['to']}");
$p = $d['pond'];
$loc = current_locale() === 'am';

require_once __DIR__ . '/../../includes/header.php';
an_detail_head('🐟 #' . $id . ' — ' . $p['name'], $f, BASE_URL . '/modules/admin/analytics_pond.php', ['id' => $id]);
an_kv([
    [$loc ? 'ስፋት (ካሬ ሜትር)' : 'Area (m²)', an_num($p['area_sq_m'], 1)],
    [$loc ? 'ሁኔታ' : 'Status', h($p['status'])],
    [$loc ? 'ምርት (ጊዜው) kg' : 'Harvest (period) kg', an_num($d['pond_kg_period'], 1)],
    [$loc ? 'የተሰጠ ድርሻ' : 'Share of unit harvest', an_pct($d['share'] * 100)],
]);
echo '<div class="an-note an-n-info">' . h($loc ? 'የዓሳ መከር አካላዊ ምርት ነው፤ ገቢ አይደለም። ግምታዊ ድርሻ በኪሎ ድርሻ ብቻ ነው።' : 'Harvest kg is physical production, not income. The allocated value is an estimate by harvest-kg share of the unit\'s sales.') . '</div>';
an_kv([[$loc ? 'የተመደበ ግምታዊ ሽያጭ' : 'Allocated sales estimate', an_money((int)$d['allocated_value_cents']) . ' <small>(' . h($loc ? 'ግምት' : 'estimate') . ')</small>']]);
an_missing_box($d['missing']);
echo '<h2>' . h($loc ? 'የዓሳ ስብስቦች (ክምችት)' : 'Stockings') . '</h2>';
an_rows_table($d['stocks'], ['id' => '#', 'species' => $loc ? 'ዝርያ' : 'Species', 'stocking_date' => $loc ? 'የተጀመረ' : 'Stocked', 'stocking_quantity' => $loc ? 'የተጨመረ' : 'Qty', 'cumulative_mortality' => '☠', 'harvest_life' => 'kg ' . an_t('lifetime'), 'harvest_p' => 'kg ' . an_t('period'), 'feed_p' => 'feed kg ' . an_t('period'), 'status' => 'Status'],
    ['stocking_date' => fn($v) => h(an_eth_label($v))]);
echo '<h2>' . h($loc ? 'መከር' : 'Harvests') . '</h2>';
an_rows_table($d['harvests'], ['harvest_date' => $loc ? 'ቀን' : 'Date', 'quantity_kg' => 'kg', 'type' => 'Type', 'grade' => 'Grade', 'species' => $loc ? 'ዝርያ' : 'Species'], ['harvest_date' => fn($v) => h(an_eth_label($v))]);
echo '<h2>' . h($loc ? 'መኖ' : 'Feeding') . '</h2>';
an_rows_table($d['feeding'], ['d' => $loc ? 'ቀን' : 'Date', 'feed_type' => $loc ? 'የመኖ ዓይነት' : 'Feed', 'kg' => 'kg'], ['d' => fn($v) => h(an_eth_label($v))]);
echo '<h2>☠ ' . h($loc ? 'ሞት' : 'Mortality') . '</h2>';
an_rows_table($d['mortality'], array_combine(array_keys($d['mortality'][0] ?? ['_' => 1]), array_keys($d['mortality'][0] ?? ['_' => 1])));
echo '<h2>' . h($loc ? 'የውሃ ጥራት' : 'Water quality') . '</h2>';
an_rows_table($d['water'], ['reading_at' => $loc ? 'ጊዜ' : 'Time', 'ph' => 'pH', 'dissolved_oxygen_mg_l' => 'DO mg/L', 'temperature_celsius' => '°C', 'within_safe_range' => $loc ? 'ደህንነቱ የተጠበቀ' : 'Safe range'], ['reading_at' => fn($v) => h(an_eth_datetime_label($v))]);
echo '<h2>' . h($loc ? 'ጤና' : 'Health') . '</h2>';
an_rows_table($d['health'], array_combine(array_keys($d['health'][0] ?? ['_' => 1]), array_keys($d['health'][0] ?? ['_' => 1])));
echo '</div>';
require_once __DIR__ . '/../../includes/footer.php';
