<?php
/**
 * Super Admin analytics - shared page helpers (guard, audit, formatting,
 * translations, filter form). No SQL about money lives here.
 */

require_once __DIR__ . '/analytics_dates.php';
require_once __DIR__ . '/analytics_service.php';
require_once __DIR__ . '/analytics_entities.php';

/**
 * STRICT access control for every analytics page.
 * Only the super_admin role passes. Everybody else gets HTTP 403 and the
 * attempt (including the URL they tried, e.g. a guessed cow ID) is audited.
 * Call this BEFORE any query and BEFORE header.php prints anything.
 */
function analytics_guard(): array
{
    require_login();
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'super_admin') {
        an_audit('access_denied', 'analytics', 0, 'url=' . substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 200));
        http_response_code(403);
        die('<h2>403 - Only the Super Admin can open farm performance analytics.</h2>'
          . '<p><a href="/aleltu-eifms/index.php">Back to dashboard</a></p>');
    }
    return $user;
}

/** Writes to audit_log using the REAL column names (record_table) and the real session user id. */
function an_audit(string $action, string $table, int $recordId = 0, string $details = ''): void
{
    try {
        $uid = (int)(current_user()['id'] ?? 0);
        $st = db()->prepare(
            "INSERT INTO audit_log (user_id, module, action, record_table, record_id, details, ip_address, created_at)
             VALUES (?, 'analytics', ?, ?, ?, ?, ?, NOW())"
        );
        $st->execute([$uid ?: null, substr($action, 0, 50), substr($table, 0, 80), $recordId, $details, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    } catch (Throwable) {
        // auditing must never break the page
    }
}

/** Reads a positive integer ID from GET. 0 = missing/invalid (never trust it further than this). */
function an_id_param(string $key = 'id'): int
{
    $v = $_GET[$key] ?? '';
    return (is_string($v) && ctype_digit($v) && strlen($v) <= 10) ? (int)$v : 0;
}

/* ============================ translations ============================ */

function an_t(string $key): string
{
    static $d = null;
    if ($d === null) {
        $d = [
            'title' => ['am' => 'የእርሻ አፈጻጸም ትንተና', 'en' => 'Farm Performance Analytics'],
            'dairy' => ['am' => 'የወተት ላሞች', 'en' => 'DAIRY'],
            'poultry' => ['am' => 'ዶሮ', 'en' => 'POULTRY'],
            'fish' => ['am' => 'ዓሳ', 'en' => 'FISH'],
            'feed_production' => ['am' => 'የመኖ ምርት', 'en' => 'FEED PRODUCTION'],
            'veterinary' => ['am' => 'የእንስሳት ሕክምና', 'en' => 'VETERINARY'],
            'shared' => ['am' => 'የጋራ ወጪ', 'en' => 'Shared overhead'],
            'unattributed' => ['am' => 'ያልተመደበ', 'en' => 'Unattributed'],
            'company' => ['am' => 'የድርጅት ጠቅላላ', 'en' => 'COMPANY TOTAL'],
            'income' => ['am' => 'የተመዘገበ ሽያጭ (ገቢ)', 'en' => 'Income (recorded sales)'],
            'expenses' => ['am' => 'ወጪ', 'en' => 'Expenses'],
            'profit' => ['am' => 'ትርፍ/ኪሳራ', 'en' => 'Profit/Loss'],
            'margin' => ['am' => 'ህዳግ', 'en' => 'Margin'],
            'production' => ['am' => 'ምርት', 'en' => 'Production'],
            'eggs_flock' => ['am' => 'እንቁላልና የመንጋ አፈጻጸም', 'en' => 'Eggs and Flock Performance'],
            'harvest_pond' => ['am' => 'ምርት (መከር) እና የኩሬ አፈጻጸም', 'en' => 'Harvest and Pond Performance'],
            'internal_dist' => ['am' => 'ለውስጥ የተሰጠ', 'en' => 'Internal Distribution'],
            'cost' => ['am' => 'ወጪ', 'en' => 'Cost'],
            'remaining' => ['am' => 'የቀረ ክምችት', 'en' => 'Remaining Inventory'],
            'treat_costs' => ['am' => 'የሕክምና ወጪ', 'en' => 'Treatment Costs'],
            'medicine_usage' => ['am' => 'የመድኃኒት አጠቃቀም', 'en' => 'Medicine Usage'],
            'health_ind' => ['am' => 'የጤና ጠቋሚዎች', 'en' => 'Health Indicators'],
            'filters' => ['am' => 'ማጣሪያ', 'en' => 'Filters'],
            'apply' => ['am' => 'ተግብር', 'en' => 'Apply'],
            'reset' => ['am' => 'አጽዳ', 'en' => 'Reset'],
            'p_today' => ['am' => 'ዛሬ', 'en' => 'Today'],
            'p_last7' => ['am' => 'ያለፉት 7 ቀናት', 'en' => 'Last 7 days'],
            'p_current_month' => ['am' => 'የዚህ ወር', 'en' => 'Current month'],
            'p_prev_month' => ['am' => 'ያለፈው ወር', 'en' => 'Previous month'],
            'p_current_year' => ['am' => 'የዚህ ዓመት', 'en' => 'Current Ethiopian year'],
            'p_lifetime' => ['am' => 'ከመጀመሪያ እስከ ዛሬ', 'en' => 'Lifetime'],
            'p_custom' => ['am' => 'የተወሰነ ጊዜ', 'en' => 'Custom range'],
            'from' => ['am' => 'ከ', 'en' => 'From'],
            'to' => ['am' => 'እስከ', 'en' => 'To'],
            'unit' => ['am' => 'የሥራ ክፍል', 'en' => 'Business unit'],
            'all_units' => ['am' => 'ሁሉም', 'en' => 'All units'],
            'status' => ['am' => 'ሁኔታ', 'en' => 'Result'],
            'all_status' => ['am' => 'ሁሉም', 'en' => 'All results'],
            's_profit' => ['am' => 'ትርፍ', 'en' => 'Profit'],
            's_loss' => ['am' => 'ኪሳራ', 'en' => 'Loss'],
            's_incomplete' => ['am' => 'ያልተሟላ መረጃ', 'en' => 'Incomplete data'],
            's_cost_centre' => ['am' => 'የወጪ ማዕከል', 'en' => 'Cost centre'],
            's_no_activity' => ['am' => 'እንቅስቃሴ የለም', 'en' => 'No activity'],
            's_shared' => ['am' => 'የጋራ', 'en' => 'Shared'],
            'search' => ['am' => 'ፈልግ (ላም/መንጋ/ኩሬ/ዕቃ)', 'en' => 'Search cow / flock / pond / item'],
            'bad_date' => ['am' => 'የተሳሳተ የኢትዮጵያ ቀን ተሰጥቷል። የዚህ ወር ተተክቷል።', 'en' => 'That Ethiopian date does not exist. Current month was used instead.'],
            'reversed' => ['am' => 'ከ ቀን ከ እስከ ቀን ይበልጥ ነበር፤ ተቀያይረዋል።', 'en' => 'From-date was after To-date; they were swapped.'],
            'future' => ['am' => 'የወደፊት ቀን ወደ ዛሬ ተቀንሷል።', 'en' => 'Future date was reduced to today.'],
            'recon_title' => ['am' => 'አምስቱ ልዩነቶች (ማስታረቂያ)', 'en' => 'Five different things (reconciliation)'],
            'r_phys' => ['am' => '1. አካላዊ ምርት (ገቢ አይደለም)', 'en' => '1. Physical production (NOT income)'],
            'r_sales' => ['am' => '2. የተመዘገበ ሽያጭ', 'en' => '2. Recorded sales'],
            'r_cash' => ['am' => '3. የተቀበለ ገንዘብ (ጥሬ/ባንክ)', 'en' => '3. Cash/bank received'],
            'r_exp' => ['am' => '4. ወጪ', 'en' => '4. Expenses'],
            'r_profit' => ['am' => '5. የተሰላ የሂሳብ ትርፍ/ኪሳራ', 'en' => '5. Calculated accounting profit/loss'],
            'receivable' => ['am' => 'ያልተሰበሰበ (ሽያጭ − የተቀበለ)', 'en' => 'Not yet collected (sales − received)'],
            'dq_title' => ['am' => 'የመረጃ ጥራትና የጎደለ መረጃ', 'en' => 'Data quality and missing data'],
            'details' => ['am' => 'ዝርዝር', 'en' => 'Details'],
            'back' => ['am' => '← ወደ ትንተናው ተመለስ', 'en' => '← Back to analytics'],
            'export' => ['am' => 'CSV አውርድ', 'en' => 'Export CSV'],
            'no_data' => ['am' => 'በዚህ ጊዜ መረጃ የለም', 'en' => 'No records in this period'],
            'schema_missing' => ['am' => 'የመረጃ ቋት ማሻሻያ ገና አልተሰራም', 'en' => 'Database update not applied yet'],
            'not_found' => ['am' => 'አልተገኘም', 'en' => 'Not found'],
            'period' => ['am' => 'የተመረጠ ጊዜ', 'en' => 'Selected period'],
            'lifetime' => ['am' => 'ሙሉ ታሪክ', 'en' => 'Lifetime'],
            'ledger_title' => ['am' => 'የገንዘብ እንቅስቃሴ ምደባ (ገቢ/ወጪ ያልሆኑ ተለይተዋል)', 'en' => 'Ledger flow classification (non-revenue / non-expense shown separately)'],
            'internal_excl' => ['am' => 'ከትርፍ የተገለሉ (የውስጥ ዝውውር)', 'en' => 'Excluded from profit (internal movements)'],
        ];
    }
    $loc = function_exists('current_locale') ? current_locale() : 'en';
    return $d[$key][$loc] ?? $d[$key]['en'] ?? $key;
}

/* ============================ formatting ============================ */

/** Cents (int) -> "12,345.67". NULL -> em dash (unknown is never shown as zero). */
function an_money(?int $cents, bool $sign = false): string
{
    if ($cents === null) {
        return '—';
    }
    $s = number_format($cents / 100, 2);
    return ($sign && $cents > 0) ? '+' . $s : $s;
}

function an_num($v, int $dec = 0): string
{
    return $v === null ? '—' : number_format((float)$v, $dec);
}

function an_pct(?float $v): string
{
    return $v === null ? '—' : number_format($v, 1) . '%';
}

/** CSS class for a signed amount. */
function an_sign_class(?int $cents): string
{
    if ($cents === null || $cents === 0) {
        return '';
    }
    return $cents > 0 ? 'an-pos' : 'an-neg';
}

function an_status_badge(string $status): string
{
    $map = ['profit' => 'ok', 'loss' => 'bad', 'incomplete' => 'warn', 'cost_centre' => 'info', 'no_activity' => 'mute', 'shared' => 'info'];
    $cls = $map[$status] ?? 'mute';
    return '<span class="an-badge an-b-' . $cls . '">' . h(an_t('s_' . $status)) . '</span>';
}

/** Link to a detail page, keeping the current period filters. */
function an_link(string $page, array $extra, array $f): string
{
    $q = an_filter_query($f, $extra);
    return '/aleltu-eifms/modules/admin/' . $page . '?' . h($q);
}

/** Protects CSV cells from spreadsheet formula injection (=, +, -, @ at the start of text). */
function an_csv_safe($v)
{
    if (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) {
        return "'" . $v;
    }
    return $v;
}

/* ============================ filter form ============================ */

function an_render_filters(array $f, string $action, array $hidden = [], bool $showUnitStatus = true, bool $showSearch = true): void
{
    $loc = current_locale();
    $months = EthiopianDate::$months;
    [$fy, $fm, $fd] = $f['eth_from'];
    [$ty, $tm, $td] = $f['eth_to'];
    $curEy = an_iso_to_eth(an_now()->format('Y-m-d'))[0];
    $presets = ['today', 'last7', 'current_month', 'prev_month', 'current_year', 'lifetime', 'custom'];
    ?>
    <form method="get" action="<?= h($action) ?>" class="an-filters" id="anFilters">
        <?php foreach ($hidden as $k => $v): ?>
            <input type="hidden" name="<?= h((string)$k) ?>" value="<?= h((string)$v) ?>">
        <?php endforeach; ?>
        <div class="an-f-row">
            <label><?= h(an_t('period')) ?>
                <select name="preset" id="anPreset">
                    <?php foreach ($presets as $p): ?>
                        <option value="<?= h($p) ?>" <?= $f['preset'] === $p ? 'selected' : '' ?>><?= h(an_t('p_' . $p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($showUnitStatus): ?>
            <label><?= h(an_t('unit')) ?>
                <select name="unit">
                    <option value=""><?= h(an_t('all_units')) ?></option>
                    <?php foreach (AN_UNITS as $u): ?>
                        <option value="<?= h($u) ?>" <?= $f['unit'] === $u ? 'selected' : '' ?>><?= h(an_t($u)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= h(an_t('status')) ?>
                <select name="status">
                    <option value=""><?= h(an_t('all_status')) ?></option>
                    <?php foreach (['profit', 'loss', 'incomplete', 'cost_centre'] as $s): ?>
                        <option value="<?= h($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= h(an_t('s_' . $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($showSearch): ?>
            <label class="an-f-search"><?= h(an_t('search')) ?>
                <input type="search" name="q" value="<?= h($f['q']) ?>" maxlength="60" placeholder="COW-001 / flock / pond / SKU">
            </label>
            <?php endif; ?>
        </div>
        <div class="an-f-row an-custom" id="anCustom" <?= $f['preset'] === 'custom' ? '' : 'hidden' ?>>
            <?php foreach ([['ef', an_t('from'), $fy, $fm, $fd], ['et', an_t('to'), $ty, $tm, $td]] as [$p, $lab, $y, $m, $d]): ?>
                <fieldset>
                    <legend><?= h($lab) ?></legend>
                    <select name="<?= $p ?>_d" aria-label="day">
                        <?php for ($i = 1; $i <= 30; $i++): ?><option value="<?= $i ?>" <?= $d === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                    </select>
                    <select name="<?= $p ?>_m" aria-label="month">
                        <?php for ($i = 1; $i <= 13; $i++): ?><option value="<?= $i ?>" <?= $m === $i ? 'selected' : '' ?>><?= h($months[$i]) ?></option><?php endfor; ?>
                    </select>
                    <select name="<?= $p ?>_y" aria-label="year">
                        <?php for ($i = $curEy; $i >= $curEy - 12; $i--): ?><option value="<?= $i ?>" <?= $y === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                    </select>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <div class="an-f-row">
            <button type="submit" class="btn"><?= h(an_t('apply')) ?></button>
            <a class="btn secondary" href="<?= h($action) ?><?= $hidden ? '?' . h(http_build_query($hidden)) : '' ?>"><?= h(an_t('reset')) ?></a>
            <span class="an-range">📅 <?= h(an_eth_label($f['from'])) ?> → <?= h(an_eth_label($f['to'])) ?>
                <small>(<?= h($f['from']) ?> → <?= h($f['to']) ?>)</small></span>
        </div>
        <?php foreach ($f['errors'] as $e): ?>
            <div class="an-note an-n-warn">
                <?= h($e === 'invalid_date' ? an_t('bad_date') : ($e === 'range_reversed' ? an_t('reversed') : an_t('future'))) ?>
            </div>
        <?php endforeach; ?>
    </form>
    <script>
    (function () {
        var p = document.getElementById('anPreset'), c = document.getElementById('anCustom');
        if (p && c) { p.addEventListener('change', function () { c.hidden = (p.value !== 'custom'); }); }
    })();
    </script>
    <?php
}

/** Shown when migration 011 has not been applied yet. */
function an_render_schema_notice(array $missing): void
{
    if (!$missing) {
        return;
    }
    echo '<div class="an-note an-n-warn"><strong>' . h(an_t('schema_missing')) . ':</strong><ul>';
    foreach ($missing as $m) {
        echo '<li>' . h($m) . '</li>';
    }
    echo '</ul></div>';
}

/** Human text for each data-quality code (what is missing and what to do). */
function an_dq_text(string $code): array
{
    $am = current_locale() === 'am';
    $t = [
        'shop_sales_not_in_system' => [
            'ወደ ድርጅቱ ሱቆች የተላከ ወተት/እንቁላል እንደ ውስጣዊ ዝውውር ተቆጥሯል፤ ሱቆቹ ለውጭ ደንበኛ የሸጡት በዚህ ሥርዓት አልተመዘገበም (POS ሽያጭ ከዚህ ጋር አልተያያዘም)። ስለዚህ ትርፉ ያልተሟላ ነው።',
            'Dispatches to company shops are counted as internal transfers. What the shops later sold to outside customers is not linked here (POS sales are not connected), so profit is incomplete. Change the treatment on the settings page if shops are independent customers.',
        ],
        'unclassified_dispatches' => [
            'የሽያጭ ዓይነታቸው ያልታወቀ ሽያጮች አሉ። በመቼቶች ገጽ ላይ አስተካክሉ።',
            'Some dispatches have no sale channel yet (could not be classified from old text). They are NOT counted as income until reviewed.',
        ],
        'unmapped_sales_item' => [
            'ሽያጭ ያላቸው ዕቃዎች ከሥራ ክፍል ጋር አልተገናኙም (ያልተመደበ)። በመቼቶች ገጽ ይመድቧቸው።',
            'Sold items are not mapped to a business unit, so their income is shown as "unattributed". Map them on the settings page.',
        ],
        'unclassified_receipts' => [
            'ምንጫቸው ያልታወቀ የገቢ ገንዘብ እንቅስቃሴ አለ፤ ገቢ ተብሎ አልተቆጠረም።',
            'Money-in rows of unknown origin exist. They are NOT counted as income until reviewed.',
        ],
        'unclassified_withdrawals' => [
            'ዓይነታቸው ያልታወቀ የገንዘብ ማውጫ ቫውቸሮች አሉ፤ ወጪ ተብለው አልተቆጠሩም።',
            'Withdrawal vouchers of unknown type exist. They are NOT counted as expenses until reviewed.',
        ],
        'cow_sale_receipts_not_linked' => [
            'የላም ሽያጭ ገንዘብ ከሽያጭ መዝገቡ ጋር በቀጥታ አልተያያዘም።',
            'Cow-sale cash is not linked row-by-row to the sale record (matched by ledger source only).',
        ],
        'cow_sale_without_price' => [
            'ዋጋ ያልተመዘገበለት የላም ሽያጭ አለ።',
            'A cow was sold with no sale price recorded, so its income is unknown.',
        ],
        'livestock_cost_basis_missing' => [
            'የተሸጠው ላም የግዢ ዋጋ አይታወቅም፤ ስለዚህ የላም ሽያጭ ትርፍ ሊሰላ አልቻለም።',
            'The purchase cost of sold cows is not recorded, so gain/loss on cow sales cannot be calculated.',
        ],
        'internal_use_with_price' => [
            'ለውስጥ አገልግሎት የተላከ ነገር ዋጋ ተመዝግቦለታል፤ ገቢ ተብሎ አልተቆጠረም።',
            'Internal-use dispatches carry a price; they are not counted as income.',
        ],
        'stock_issue_unknown_destination' => [
            'ከመጋዘን የወጣ ዕቃ መድረሻው ከሥራ ክፍል ጋር አልተገናኘም፤ በ"ያልተመደበ" ውስጥ ተቆጥሯል።',
            'Stock was issued to a destination that maps to no business unit; cost is shown as unattributed.',
        ],
        'unpriced_stock_issues' => [
            'ዋጋ የሌላቸው የክምችት ዕቃዎች ወጥተዋል፤ ወጪያቸው ዜሮ ሆኖ ተቆጥሯል — ትርፉ ከእውነቱ ከፍ ሊል ይችላል።',
            'Stock was issued without a unit cost, so its cost counts as zero - profit may be overstated.',
        ],
        'writeoff_unpriced' => [
            'ዋጋ የሌለው የክምችት ማስተካከያ (ብክነት) አለ።',
            'Stock write-offs without a unit cost exist; their value is unknown.',
        ],
        'ledger_expense_without_voucher' => [
            'በመዝገቡ ውስጥ ከቫውቸር ጋር ያልተያያዘ ወጪ አለ፤ ተቆጥሯል።',
            'Ledger expenses without a matching voucher exist; they were counted once as ledger-only.',
        ],
        'employer_pension_not_recorded' => [
            'የአሰሪ የጡረታ መዋጮ አልተመዘገበም፤ የሠራተኛ ወጪ ዝቅ ብሎ ሊሆን ይችላል።',
            'Employer pension contribution is not recorded, so payroll cost may be understated.',
        ],
        'partial_delivery_priced_on_dispatch' => [
            'በከፊል የደረሱ ሽያጮች በተላከው መጠን ዋጋ ተሰጥቷቸዋል።',
            'Partially delivered dispatches are priced on the quantity dispatched.',
        ],
        'receipts_ledger_mismatch' => [
            'የክፍያ ሠንጠረዥና የገንዘብ መዝገብ ልዩነት አለ።',
            'Payments table and finance ledger totals differ for this period.',
        ],
        'store_receipt_pending' => [
            'ወደ መጋዘን ገና ያልተቀበሉ ዕቃዎች አሉ።',
            'Items handed to the store are still waiting to be received.',
        ],
    ];
    if (!isset($t[$code]) && str_ends_with($code, '_excluded')) {
        return ['ዓይነታቸው ያልታወቀ የገንዘብ እንቅስቃሴ ከትርፍ ስሌት ተገልሏል (መፈተሽ ያስፈልጋል)።',
                'Ledger movements of unknown type were left OUT of income/expenses until someone classifies them.'];
    }
    return $t[$code] ?? [$code, $code];
}

/* ============================ detail-page helpers ============================ */

/** Key/value tiles. $items = [[label, valueHtml(already escaped)], ...] */
function an_kv(array $items): void
{
    echo '<div class="an-kv">';
    foreach ($items as [$label, $val]) {
        echo '<div><span>' . h($label) . '</span><b>' . $val . '</b></div>';
    }
    echo '</div>';
}

/** Plain table from rows. $cols = [key => label]; $fmt = optional [key => callable(value,row): htmlEscaped]. */
function an_rows_table(array $rows, array $cols, array $fmt = []): void
{
    if (!$rows) {
        echo '<p class="an-empty">' . h(an_t('no_data')) . '</p>';
        return;
    }
    echo '<div class="an-scroll"><table class="an-table"><thead><tr>';
    foreach ($cols as $label) {
        echo '<th>' . h($label) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($cols as $k => $_) {
            $v = $r[$k] ?? null;
            echo '<td>' . (isset($fmt[$k]) ? $fmt[$k]($v, $r) : h((string)$v)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

/** Explains every figure that could NOT be calculated and why (never fabricated). */
function an_missing_box(array $missing): void
{
    $am = current_locale() === 'am';
    $txt = [
        'acquisition_price' => ['የግዢ ዋጋ አልተመዘገበም', 'Purchase price of the animal is not recorded'],
        'calf_milk' => ['ጥጃ የጠጣው ወተት አይለካም', 'Milk drunk by calves is not measured'],
        'feed_cost_per_cow' => ['ለእያንዳንዱ ላም የተመገበው መኖ አልተመዘገበም (መኖ በቡድን ነው የሚወጣው)', 'Feed eaten per cow is not recorded (feed is issued per department/group)'],
        'vet_cost_per_treatment' => ['የእያንዳንዱ ሕክምና ወጪ አልተመዘገበም', 'Cost of each treatment is not recorded'],
        'milk_sales_per_cow' => ['ወተት የሚሸጠው ከሁሉም ላሞች ተደባልቆ ነው፤ የላም-ደረጃ ሽያጭ ሊታወቅ አይችልም', 'Milk is sold pooled, so sales per cow cannot be known; only an allocation by litre share is shown'],
        'feed_per_flock' => ['ለእያንዳንዱ መንጋ የተሰጠ መኖ አልተመዘገበም', 'Feed given per flock is not recorded'],
        'medicine_cost' => ['የመድኃኒት ወጪ በዚህ መዝገብ ላይ አልተመዘገበም', 'Medicine cost is not recorded against this record'],
        'sales_per_flock' => ['እንቁላል የሚሸጠው ከሁሉም መንጋ ተደባልቆ ነው', 'Eggs are sold pooled, so sales per flock cannot be known; only an allocation by egg share is shown'],
        'broiler_sales' => ['የሥጋ ዶሮ ሽያጭ በመንጋ አልተያያዘም', 'Broiler/culled-bird sales are not linked to the flock'],
        'fish_sales_per_pond' => ['ዓሳ ሽያጭ በኩሬ አልተያያዘም', 'Fish sales are not linked to the pond; only an allocation by harvest-kg share is shown'],
        'feed_cost_per_pond' => ['ለእያንዳንዱ ኩሬ የመኖ ወጪ አልተመዘገበም', 'Feed cost per pond is not recorded (only kilograms)'],
        'maintenance_cost' => ['የኩሬ ጥገና ወጪ አልተመዘገበም', 'Pond maintenance cost is not recorded'],
    ];
    $items = [];
    foreach ($missing as $k => $on) {
        if ($on && isset($txt[$k])) {
            $items[] = $am ? $txt[$k][0] : $txt[$k][1];
        }
    }
    if (!$items) {
        return;
    }
    echo '<div class="an-missing"><strong>' . ($am ? 'የሚጎድል መረጃ — ስለዚህ እውነተኛ ትርፍ አልተሰላም:' : 'Missing data - so a true profit is NOT calculated:') . '</strong><ul>';
    foreach ($items as $i) {
        echo '<li>' . h($i) . '</li>';
    }
    echo '</ul></div>';
}

/** Common top of a detail page (title + back link + filters). Returns nothing. */
function an_detail_head(string $title, array $f, string $action, array $hidden): void
{
    echo '<link rel="stylesheet" href="/aleltu-eifms/assets/css/analytics.css"><div class="an-wrap">';
    echo '<div class="an-head"><h1>' . h($title) . '</h1><div class="an-head-actions"><a class="btn secondary" href="'
        . h('/aleltu-eifms/modules/admin/analytics.php?' . an_filter_query($f)) . '">' . h(an_t('back')) . '</a></div></div>';
    an_render_filters($f, $action, $hidden, false, false);
}
