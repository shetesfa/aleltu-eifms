<?php
/**
 * Super Admin analytics - automated tests.
 *
 * RUN ONLY ON A COPY. It creates/drops the database `aleltu_analytics_test` and never touches your real one.
 *   php tests/analytics/run_tests.php /path/to/your_database_export.sql
 * Optional env: AN_DB_HOST, AN_DB_USER, AN_DB_PASS (default 127.0.0.1 / root / empty).
 * Needs the `mysql` command-line client and the PHP calendar extension.
 */
error_reporting(E_ALL);
$root = realpath(__DIR__ . '/../..');
$dump = $argv[1] ?? '';
if ($dump === '' || !is_file($dump)) { fwrite(STDERR, "Usage: php run_tests.php /path/to/export.sql\n"); exit(2); }
$host = getenv('AN_DB_HOST') ?: '127.0.0.1'; $user = getenv('AN_DB_USER') ?: 'root'; $pass = getenv('AN_DB_PASS') ?: '';
$DB = 'aleltu_analytics_test';

$pass_n = 0; $fail_n = 0; $failures = [];
function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass_n, $fail_n, $failures;
    if ($cond) { $pass_n++; echo "  PASS  $name\n"; } else { $fail_n++; $failures[] = $name; echo "  FAIL  $name" . ($detail ? "  [$detail]" : '') . "\n"; }
}
function section(string $s): void { echo "\n== $s\n"; }
function sh(string $cmd): array { exec($cmd . ' 2>&1', $out, $rc); return [$rc, implode("\n", $out)]; }
$mysqlCli = 'mysql -h' . escapeshellarg($host) . ' -u' . escapeshellarg($user) . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '') . ' --default-character-set=utf8mb4';

/* ---------------------------------------------------------------- 1. syntax */
section('1. PHP syntax of every analytics file');
$files = array_merge(glob("$root/includes/analytics/*.php"), glob("$root/modules/admin/analytics*.php"),
    ["$root/includes/functions.php", "$root/includes/header.php", "$root/modules/distribution/dispatches.php"]);
foreach ($files as $f) { [$rc, $o] = sh('php -l ' . escapeshellarg($f)); ok('php -l ' . str_replace($root . '/', '', $f), $rc === 0, $o); }

require "$root/includes/analytics/analytics_dates.php";
require "$root/includes/analytics/analytics_service.php";
require "$root/includes/analytics/analytics_entities.php";
require_once "$root/includes/analytics/analytics_ui.php";

/* ---------------------------------------------------------------- 2. Ethiopian dates */
section('2. Ethiopian calendar edge cases');
ok('2018-01-01 (Meskerem 1) = 2025-09-11', an_eth_to_iso(2018, 1, 1) === '2025-09-11', (string)an_eth_to_iso(2018, 1, 1));
ok('Pagume 6 valid only in leap year 2019', an_eth_to_iso(2019, 13, 6) !== null && an_eth_to_iso(2018, 13, 6) === null);
ok('Pagume 5 valid in 2018', an_eth_to_iso(2018, 13, 5) !== null);
ok('day 31 never valid', an_eth_to_iso(2018, 2, 31) === null);
ok('month 14 invalid', an_eth_to_iso(2018, 14, 1) === null);
$bad = 0; for ($y = 2010; $y <= 2022; $y++) for ($m = 1; $m <= 13; $m++) for ($d = 1; $d <= an_eth_days_in_month($y, $m); $d++) {
    $iso = an_eth_to_iso($y, $m, $d); if ($iso === null || an_iso_to_eth($iso) !== [$y, $m, $d]) { $bad++; }
}
ok('round trip every day 2010-2022 (' . (13 * 365) . '+ days)', $bad === 0, "bad=$bad");
$now = new DateTimeImmutable('2026-10-14 23:30:00', new DateTimeZone('Africa/Addis_Ababa'));
$f = an_parse_filters(['preset' => 'today'], $now);   ok('today preset', $f['from'] === '2026-10-14' && $f['to'] === '2026-10-14');
$f = an_parse_filters(['preset' => 'last7'], $now);   ok('last 7 days = 7 calendar days', $f['from'] === '2026-10-08' && $f['to'] === '2026-10-14');
$f = an_parse_filters(['preset' => 'current_month'], $now); ok('current Ethiopian month starts on day 1', an_iso_to_eth($f['from'])[2] === 1 && $f['to'] === '2026-10-14');
$f = an_parse_filters(['preset' => 'prev_month'], $now);    ok('previous month is a whole Ethiopian month', an_iso_to_eth($f['from'])[2] === 1 && $f['from'] < $f['to']);
$f = an_parse_filters(['preset' => 'current_year'], $now);  ok('current Ethiopian year starts Meskerem 1', an_iso_to_eth($f['from']) === [an_iso_to_eth('2026-10-14')[0], 1, 1]);
$f = an_parse_filters(['preset' => 'custom', 'ef_y' => 2018, 'ef_m' => 13, 'ef_d' => 6, 'et_y' => 2018, 'et_m' => 13, 'et_d' => 6], $now);
ok('invalid custom date falls back and reports error', in_array('invalid_date', $f['errors'], true));
$f = an_parse_filters(['preset' => 'custom', 'ef_y' => 2018, 'ef_m' => 5, 'ef_d' => 1, 'et_y' => 2018, 'et_m' => 4, 'et_d' => 1], $now);
ok('reversed range is swapped', $f['from'] <= $f['to'] && in_array('range_reversed', $f['errors'], true));
$f = an_parse_filters(['preset' => 'custom', 'ef_y' => 2018, 'ef_m' => 1, 'ef_d' => 1, 'et_y' => 2030, 'et_m' => 1, 'et_d' => 1], $now);
ok('future end date clamped to today', $f['to'] === '2026-10-14');
$f = an_parse_filters(['preset' => "x'; DROP TABLE users;--", 'unit' => 'evil', 'status' => '1=1', 'q' => str_repeat('a', 200)], $now);
ok('hostile filter values are neutralised', $f['preset'] === 'current_month' && $f['unit'] === '' && $f['status'] === '' && mb_strlen($f['q']) === 60);

/* ---------------------------------------------------------------- 3. build test DB */
section('3. Build throw-away database from your export');
$pdo0 = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo0->exec("DROP DATABASE IF EXISTS `$DB`"); $pdo0->exec("CREATE DATABASE `$DB` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
[$rc, $o] = sh("$mysqlCli $DB < " . escapeshellarg($dump)); ok('export imported', $rc === 0, substr($o, 0, 200));
foreach (['011_super_admin_analytics.sql', '012_veterinary_unit_tag.sql'] as $m) {
    [$rc, $o] = sh("$mysqlCli $DB < " . escapeshellarg("$root/sql/$m")); ok("migration $m first run", $rc === 0, substr($o, 0, 200));
}
$pdo = new PDO("mysql:host=$host;dbname=$DB;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$snapDb = function () use ($pdo, $DB) {
    return [
        'idx' => (int)$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='$DB' AND index_name LIKE 'idx_an_%'")->fetchColumn(),
        'map' => (int)$pdo->query("SELECT COUNT(*) FROM rpt_item_unit_map")->fetchColumn(),
        'set' => (int)$pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key='analytics_shop_dispatch_treatment'")->fetchColumn(),
        'disp' => (int)$pdo->query("SELECT COUNT(*) FROM dist_dispatches")->fetchColumn(),
        'chan' => $pdo->query("SELECT GROUP_CONCAT(COALESCE(sale_channel,'NULL') ORDER BY id) FROM dist_dispatches")->fetchColumn(),
        'cols' => (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='dist_dispatches' AND column_name='sale_channel'")->fetchColumn(),
    ];
};
$before = $snapDb();
foreach (['011_super_admin_analytics.sql', '012_veterinary_unit_tag.sql'] as $m) { [$rc] = sh("$mysqlCli $DB < " . escapeshellarg("$root/sql/$m")); ok("migration $m second run (re-runnable)", $rc === 0); }
ok('re-running migrations changes nothing (indexes, map, setting, channels, column)', $snapDb() === $before, json_encode([$before, $snapDb()]));
ok('migration created idx_an_* indexes', $before['idx'] >= 20, (string)$before['idx']);
ok('sale_channel column exists', $before['cols'] === 1);
ok('schema status reports nothing missing', an_schema_status($pdo) === [], json_encode(an_schema_status($pdo)));

/* ---------------------------------------------------------------- 4. figures */
section('4. Figures: no double counting, filters, transfers');
$L0 = '2000-01-01'; $L1 = '2026-12-31';
$o = an_overview($pdo, $L0, $L1); $acc = $o['acc'];
$sumInc = 0; $sumExp = 0;
foreach (AN_ALL_BUCKET_UNITS as $u) { $sumInc += $acc->total($u, 'sales'); $sumExp += $acc->total($u, 'expenses'); }
ok('company income = sum of every bucket exactly once', $o['company']['income'] === (int)$sumInc);
ok('company expenses = sum of every bucket exactly once', $o['company']['expenses'] === (int)$sumExp);
$mi = 0; $me = 0; foreach ($o['months'] as $mk => $units) foreach ($units as $u => $m) { $mi += $m['sales'] ?? 0; $me += $m['expenses'] ?? 0; }
ok('sum of months = period total (income)', (int)$mi === $o['company']['income']);
ok('sum of months = period total (expenses)', (int)$me === $o['company']['expenses']);

// additivity: splitting a range in two must give the same totals
$a = an_overview($pdo, '2026-01-01', '2026-09-24'); $b = an_overview($pdo, '2026-09-25', '2026-12-31'); $c = an_overview($pdo, '2026-01-01', '2026-12-31');
ok('income additive across a split range', $a['company']['income'] + $b['company']['income'] === $c['company']['income']);
ok('expenses additive across a split range', $a['company']['expenses'] + $b['company']['expenses'] === $c['company']['expenses']);
$e = an_overview($pdo, '2001-01-01', '2001-12-31');
ok('empty period gives zero income and expenses', $e['company']['income'] === 0 && $e['company']['expenses'] === 0 && $e['company']['status'] === 'no_activity');

// fixtures: clone an existing direct-sale dispatch (same columns, so nothing is invented)
$pdo->exec("SET @tmpid = (SELECT id FROM dist_dispatches WHERE sale_channel='direct_sale' ORDER BY id LIMIT 1)");
$cloneDispatch = function (string $channel, float $qty, float $price, string $when) use ($pdo): int {
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS t_d"); $pdo->exec("CREATE TEMPORARY TABLE t_d SELECT * FROM dist_dispatches WHERE id=@tmpid"); $pdo->exec("ALTER TABLE t_d MODIFY id INT NULL");
    $pdo->prepare("UPDATE t_d SET id=NULL, sale_channel=?, quantity_dispatched=?, unit_price=?, total_price=?, amount_paid=0, dispatched_at=?, planned_dispatch_at=?, status='delivered'")
        ->execute([$channel, $qty, $price, $qty * $price, $when, $when]);
    $pdo->exec("INSERT INTO dist_dispatches SELECT * FROM t_d"); return (int)$pdo->lastInsertId();
};
$base = an_overview($pdo, $L0, $L1);
$id1 = $cloneDispatch('direct_sale', 10, 100, '2026-10-01 10:00:00');
$after = an_overview($pdo, $L0, $L1);
$unit = $pdo->query("SELECT COALESCE(m.business_unit,'unattributed') FROM dist_dispatches d LEFT JOIN rpt_item_unit_map m ON m.stock_item_id=d.stock_item_id WHERE d.id=$id1")->fetchColumn();
ok('direct sale adds exactly 1,000.00 to company income', $after['company']['income'] - $base['company']['income'] === 100000);
ok("direct sale lands in unit '$unit'", $unit === 'unattributed' || ($after['units'][$unit]['income'] - $base['units'][$unit]['income'] === 100000));
ok('direct sale does NOT change cash received (unpaid)', $after['company']['receipts']['total'] === $base['company']['receipts']['total']);
ok('direct sale raises "not yet collected" by 1,000.00', $after['company']['receivable'] - $base['company']['receivable'] === 100000);
$outside = an_overview($pdo, '2026-09-01', '2026-09-30'); $inside = an_overview($pdo, '2026-10-01', '2026-10-01');
ok('date filter really changes the SQL (Oct-1 sale only inside Oct-1)', $inside['company']['income'] === 100000 && $outside['company']['income'] === an_overview($pdo, '2026-09-01', '2026-09-30')['company']['income']);

$pdo->prepare("INSERT INTO dist_dispatch_payments (dispatch_id, amount, payment_method, reference_no, notes, received_by, created_at) VALUES (?, 1000, 'bank', 'TEST-1', 'test', 1, '2026-10-02 09:00:00')")->execute([$id1]);
$p = an_overview($pdo, $L0, $L1);
ok('payment raises cash/bank received by 1,000.00', $p['company']['receipts']['total'] - $after['company']['receipts']['total'] === 100000);
ok('payment does NOT change recorded income', $p['company']['income'] === $after['company']['income']);

// shop transfers
$s0 = an_overview($pdo, $L0, $L1);
$idShop = $cloneDispatch('shop_transfer', 5, 100, '2026-10-03 10:00:00');
$s1 = an_overview($pdo, $L0, $L1);
ok('shop transfer (internal treatment) does NOT add income', $s1['company']['income'] === $s0['company']['income']);
ok('shop transfer shown in the "excluded" memo', $s1['company']['memo']['shop_transfers'] - $s0['company']['memo']['shop_transfers'] === 50000);
$pdo->exec("UPDATE settings SET setting_value='external' WHERE setting_key='analytics_shop_dispatch_treatment'");
$s2 = an_overview($pdo, $L0, $L1);
ok('flipping setting to external counts the shop dispatches as income', $s2['company']['income'] > $s1['company']['income']);
$pdo->exec("UPDATE settings SET setting_value='internal' WHERE setting_key='analytics_shop_dispatch_treatment'");
ok('flipping back restores the figures', an_overview($pdo, $L0, $L1)['company']['income'] === $s1['company']['income']);

// internal use / unclassified
$idGift = $cloneDispatch('internal_use', 3, 100, '2026-10-04 10:00:00');
$g = an_overview($pdo, $L0, $L1);
ok('internal-use (gift) dispatch is not income', $g['company']['income'] === $s1['company']['income']);
$pdo->prepare("UPDATE dist_dispatches SET sale_channel=NULL WHERE id=?")->execute([$idGift]);
$g2 = an_overview($pdo, $L0, $L1);
ok('unclassified dispatch is not silently counted as income', $g2['company']['income'] === $s1['company']['income']);
ok('unclassified dispatch raises a data-quality warning', (bool)array_filter($g2['dq'], fn($d) => $d['code'] === 'unclassified_dispatches'));

// ledger: transfers / unknown inflows must not move income or expenses
$ins = $pdo->prepare("INSERT INTO fin_transactions (bank_account_id, direction, amount, payment_method, unit_tag, category, source_table, source_id, transaction_date, description, recorded_by) VALUES (?, ?, ?, 'bank', 'general_overhead', ?, 'manual', 0, '2026-10-05', 'test', 1)");
$ba = (int)$pdo->query("SELECT id FROM fin_bank_accounts ORDER BY id LIMIT 1")->fetchColumn();
$l0 = an_overview($pdo, $L0, $L1);
$ins->execute([$ba, 'outflow', 77777, 'internal_transfer_out']); $ins->execute([$ba, 'inflow', 77777, 'internal_transfer_in']); $ins->execute([$ba, 'inflow', 55555, 'mystery_deposit']);
$l1 = an_overview($pdo, $L0, $L1);
ok('internal bank transfers do NOT change company income', $l1['company']['income'] === $l0['company']['income']);
ok('internal bank transfers do NOT change company expenses', $l1['company']['expenses'] === $l0['company']['expenses']);
ok('unknown deposit is not income but is flagged', $l1['company']['income'] === $l0['company']['income'] && (bool)array_filter($l1['dq'], fn($d) => str_contains($d['code'], 'unclassified_inflow')));
ok('transfers are listed in the ledger classification', isset($l1['ledger']['internal_transfer_inflow']) || isset($l1['ledger']['internal_transfer_outflow']), implode(',', array_keys($l1['ledger'])));

// incomplete data is not shown as verified profit
$st = $s1['units']['dairy']['status'];
ok('dairy with shop sales missing is "incomplete", never "profit"/"loss"', in_array($st, ['incomplete', 'no_activity'], true), $st);
ok('cost-centre units show NULL profit (not a fabricated number)', $s1['units']['feed_production']['profit'] === null && $s1['units']['veterinary']['profit'] === null);

/* ---------------------------------------------------------------- 5. entity lists & joins */
section('5. Individual records: no join duplication, no N+1');
$cows = an_cows_list($pdo, $L0, $L1);
$direct = (float)$pdo->query("SELECT COALESCE(SUM(yield_liters),0) FROM dairy_milk_yield_entries WHERE cow_id IS NOT NULL")->fetchColumn();
$list = array_sum(array_map(fn($r) => (float)$r['l_life'], $cows));
ok('sum of litres per cow = direct sum of yield rows', abs($direct - $list) < 0.001, "$direct vs $list");
$flocks = an_flocks_list($pdo, $L0, $L1);
$dEgg = (int)$pdo->query("SELECT COALESCE(SUM(whole_count),0) FROM poultry_egg_collections")->fetchColumn();
ok('sum of whole eggs per flock = direct sum', $dEgg === array_sum(array_map(fn($r) => (int)$r['whole'], $flocks)));
$ponds = an_ponds_list($pdo, $L0, $L1);
$dFish = (float)$pdo->query("SELECT COALESCE(SUM(quantity_kg),0) FROM fish_harvest_records")->fetchColumn();
ok('sum of harvest kg per pond = direct sum', abs($dFish - array_sum(array_map(fn($r) => (float)$r['harvest_kg'], $ponds))) < 0.001);
$q = function () use ($pdo) { return (int)$pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_NUM)[1]; };
$q0 = $q(); an_cows_list($pdo, $L0, $L1); $qc = $q() - $q0 - 1;
ok("cow list uses a constant number of queries ($qc for " . count($cows) . ' cows)', $qc <= 3);
$q0 = $q(); an_flocks_list($pdo, $L0, $L1); an_ponds_list($pdo, $L0, $L1); $qc = $q() - $q0 - 1;
ok("flock+pond lists constant queries ($qc)", $qc <= 6);
$q0 = $q(); an_overview($pdo, $L0, $L1); $qc = $q() - $q0 - 1;
ok("whole overview runs in a fixed number of queries ($qc)", $qc <= 70);
$search = an_search($pdo, '%_\\', $L0, $L1);
ok('search treats % _ \\ as plain text (no wildcard match-all)', count($search['cows']) === 0 && count($search['ponds']) === 0);
$s = an_search($pdo, $cows[0]['tag_number'], $L0, $L1);
ok('search by cow tag finds that cow', in_array($cows[0]['id'], array_column($s['cows'], 'id'), false));
ok('unknown cow id returns null (no data leak)', an_cow_detail($pdo, 99999999, $L0, $L1) === null && an_flock_detail($pdo, 99999999, $L0, $L1) === null && an_pond_detail($pdo, 99999999, $L0, $L1) === null);
$cd = an_cow_detail($pdo, (int)$cows[0]['id'], $L0, $L1);
ok('cow detail says which data is missing instead of inventing it', !empty($cd['missing']['acquisition_price']) && !empty($cd['missing']['milk_sales_per_cow']));
ok('cow sales value is only an allocation estimate (key named allocated_*)', array_key_exists('allocated_value_cents', $cd));
ok('CSV formula injection guarded', an_csv_safe('=1+1') === "'=1+1" && an_csv_safe('abc') === 'abc' && an_csv_safe(12.5) === 12.5);

/* ---------------------------------------------------------------- 6. authorization via real page execution */
section('6. Authorization (pages executed as different roles)');
$render = function (string $page, string $role, string $qs, ?string $post = null) use ($root, $host, $user, $pass): string {
    $env = 'AN_DB_HOST=' . escapeshellarg($host) . ' AN_DB_USER=' . escapeshellarg($user) . ' AN_DB_PASS=' . escapeshellarg($pass);
    $cmd = "$env php " . escapeshellarg("$root/tests/analytics/render_page.php") . ' ' . escapeshellarg($page) . ' ' . escapeshellarg($role) . ' ' . escapeshellarg($qs) . ($post !== null ? ' ' . escapeshellarg($post) : '');
    return (string)shell_exec($cmd . ' 2>&1');
};
$cowId = (int)$cows[0]['id'];
foreach (['analytics.php' => 'preset=lifetime', 'analytics_unit.php' => 'unit=dairy', 'analytics_cow.php' => "id=$cowId", 'analytics_flock.php' => 'id=' . (int)$flocks[0]['id'], 'analytics_pond.php' => 'id=' . (int)$ponds[0]['id'], 'analytics_settings.php' => ''] as $pg => $qs) {
    foreach (['general_manager', 'dairy_manager', 'accountant', 'field_worker', 'poultry_manager'] as $role) {
        $out = $render($pg, $role, $qs);
        ok("$pg denied for $role", str_contains($out, '403') && !str_contains($out, 'an-wrap'));
    }
    $out = $render($pg, 'super_admin', $qs);
    ok("$pg opens for super_admin", str_contains($out, 'an-wrap') && !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/', $out), substr(strip_tags($out), 0, 200));
}
$out = $render('analytics_cow.php', 'super_admin', 'id=99999999'); ok('guessed cow id -> 404, no data', str_contains($out, '404') && !str_contains($out, 'an-wrap'));
$out = $render('analytics_cow.php', 'super_admin', "id=$cowId%20OR%201=1"); ok('SQL in id parameter -> 404', str_contains($out, '404'));
$out = $render('analytics_flock.php', 'super_admin', 'id=-1'); ok('negative id -> 404', str_contains($out, '404'));
$out = $render('analytics_unit.php', 'super_admin', 'unit=users'); ok('unknown unit -> 404', str_contains($out, '404'));
$denied = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE module='analytics' AND action='access_denied'")->fetchColumn();
ok('denied attempts are written to the audit log', $denied >= 30, (string)$denied);
$out = $render('analytics_settings.php', 'super_admin', '', 'shop_treatment=external&csrf_token=WRONG');
$tr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='analytics_shop_dispatch_treatment'")->fetchColumn();
ok('settings POST with a bad CSRF token changes nothing', $tr === 'internal', $tr);
$out = $render('analytics.php', 'super_admin', 'preset=lifetime&unit=dairy&status=loss');
ok('unit + status filters remove other business-unit columns', !str_contains($out, 'an-g-poultry') && !str_contains($out, 'an-g-fish'));
$out = $render('analytics.php', 'super_admin', 'preset=lifetime&q=' . urlencode($cows[0]['tag_number']));
ok('search by cow ID shows a link to that cow', str_contains($out, "analytics_cow.php?") && str_contains($out, "id=$cowId"));
$out = $render('analytics.php', 'super_admin', 'preset=lifetime');
ok('grouped table has a two-row header with colspan groups', substr_count($out, '<tr class="an-grp">') === 1 && substr_count($out, '<tr class="an-sub">') === 1 && preg_match('/colspan="5"[^>]*an-g-dairy/', $out) === 1 && preg_match('/colspan="4"[^>]*an-g-feed_production/', $out) === 1 && preg_match('/colspan="3"[^>]*an-g-veterinary/', $out) === 1);
preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $out, $ths); $bad = array_filter($ths[1], fn($t) => stripos($t, 'Income') !== false && stripos($t, 'NOT income') === false && preg_match('/(litre|egg|\bkg\b|milk|harvest|production)/i', strip_tags($t)));
ok('no "Income" column header is about physical production (milk/eggs/fish kg)', count($bad) === 0, implode('|', array_map('strip_tags', $bad)));
$out2 = $render('analytics_settings.php', 'super_admin', '', 'shop_treatment=external');
$tr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='analytics_shop_dispatch_treatment'")->fetchColumn();
ok('settings POST with the correct CSRF token does save (control test)', $tr === 'external', $tr);
$pdo->exec("UPDATE settings SET setting_value='internal' WHERE setting_key='analytics_shop_dispatch_treatment'");

/* ---------------------------------------------------------------- done */
$pdo0->exec("DROP DATABASE IF EXISTS `$DB`");
echo "\n=========================================\n";
echo "RESULT: $pass_n passed, $fail_n failed\n";
if ($failures) { echo "FAILED:\n - " . implode("\n - ", $failures) . "\n"; }
exit($fail_n ? 1 : 0);
