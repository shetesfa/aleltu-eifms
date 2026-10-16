<?php
/**
 * Super Admin analytics - Ethiopian-calendar date filtering helpers.
 *
 * The database keeps ordinary (Gregorian) DATE/DATETIME values. This file only
 * converts what the Super Admin types/selects in Ethiopian dates into the
 * Gregorian range the SQL needs, and back again for display.
 * It reuses the project's EthiopianDate class (includes/ethiopian_calendar.php).
 *
 * "Today" is always computed in Africa/Addis_Ababa, never in the server's own
 * timezone, so the "Today / This month" presets are right around midnight.
 */

require_once __DIR__ . '/../ethiopian_calendar.php';

const AN_PRESETS = ['today', 'last7', 'current_month', 'prev_month', 'current_year', 'lifetime', 'custom'];
const AN_LIFETIME_START = '2000-01-01';

function an_now(?DateTimeImmutable $override = null): DateTimeImmutable
{
    return $override ?? new DateTimeImmutable('now', new DateTimeZone('Africa/Addis_Ababa'));
}

/** Ethiopian leap year: the year before a Gregorian leap year, i.e. year % 4 == 3. */
function an_eth_is_leap(int $ey): bool
{
    return $ey % 4 === 3;
}

function an_eth_days_in_month(int $ey, int $em): int
{
    if ($em >= 1 && $em <= 12) {
        return 30;
    }
    return an_eth_is_leap($ey) ? 6 : 5; // Pagume
}

/** Validates an Ethiopian y/m/d and returns the Gregorian 'Y-m-d', or null if it is not a real date. */
function an_eth_to_iso(int $ey, int $em, int $ed): ?string
{
    if ($ey < 1900 || $ey > 2300 || $em < 1 || $em > 13 || $ed < 1 || $ed > 30) {
        return null;
    }
    if ($ed > an_eth_days_in_month($ey, $em)) {
        return null;
    }
    [$gy, $gm, $gd] = EthiopianDate::toGregorian($ey, $em, $ed);
    // Round trip guard: converting back must give exactly what was typed.
    if (EthiopianDate::fromGregorian($gy, $gm, $gd) !== [$ey, $em, $ed]) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

/** Gregorian 'Y-m-d' -> [ey, em, ed]. */
function an_iso_to_eth(string $iso): array
{
    [$y, $m, $d] = array_map('intval', explode('-', substr($iso, 0, 10)));
    return EthiopianDate::fromGregorian($y, $m, $d);
}

/** Month bucket key like "2019-02" and its label like "ጥቅምት 2019". */
function an_month_key(string $iso): string
{
    [$ey, $em] = an_iso_to_eth($iso);
    return sprintf('%04d-%02d', $ey, $em);
}

function an_month_label(string $key): string
{
    [$ey, $em] = array_map('intval', explode('-', $key));
    return (EthiopianDate::$months[$em] ?? '?') . ' ' . $ey;
}

/** Ethiopian display of a plain date: "ጥቅምት 4/2019". */
function an_eth_label(?string $iso): string
{
    return $iso ? EthiopianDate::format($iso, 'medium') : '—';
}

/** Ethiopian display of a timestamp with the project's Ethiopian 12-hour clock. */
function an_eth_datetime_label(?string $ts): string
{
    return $ts ? EthiopianDate::formatDateTime($ts) : '—';
}

/** Returns ['from'=>iso,'to'=>iso] for a preset, evaluated at $now (Addis Ababa). */
function an_preset_range(string $preset, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    [$ey, $em, $ed] = an_iso_to_eth($today);

    switch ($preset) {
        case 'today':
            return ['from' => $today, 'to' => $today];
        case 'last7':
            return ['from' => $now->modify('-6 days')->format('Y-m-d'), 'to' => $today];
        case 'prev_month':
            $pm = $em - 1;
            $py = $ey;
            if ($pm < 1) {
                $pm = 13;
                $py = $ey - 1;
            }
            return [
                'from' => an_eth_to_iso($py, $pm, 1),
                'to'   => an_eth_to_iso($py, $pm, an_eth_days_in_month($py, $pm)),
            ];
        case 'current_year':
            return ['from' => an_eth_to_iso($ey, 1, 1), 'to' => $today];
        case 'lifetime':
            return ['from' => AN_LIFETIME_START, 'to' => $today];
        case 'current_month':
        default:
            return ['from' => an_eth_to_iso($ey, $em, 1), 'to' => $today];
    }
}

const AN_UNITS = ['dairy', 'poultry', 'fish', 'feed_production', 'veterinary'];

/**
 * Reads and validates every filter from a query-string array.
 * Never trusts input: unknown presets/units fall back to safe defaults and
 * bad Ethiopian dates produce a message instead of an exception.
 */
function an_parse_filters(array $get, ?DateTimeImmutable $now = null): array
{
    $now = an_now($now);
    $today = $now->format('Y-m-d');
    $errors = [];

    $preset = (string)($get['preset'] ?? 'current_month');
    if (!in_array($preset, AN_PRESETS, true)) {
        $preset = 'current_month';
    }

    if ($preset === 'custom') {
        $from = an_eth_to_iso((int)($get['ef_y'] ?? 0), (int)($get['ef_m'] ?? 0), (int)($get['ef_d'] ?? 0));
        $to   = an_eth_to_iso((int)($get['et_y'] ?? 0), (int)($get['et_m'] ?? 0), (int)($get['et_d'] ?? 0));
        if ($from === null || $to === null) {
            $errors[] = 'invalid_date';
            $r = an_preset_range('current_month', $now);
            $from = $r['from'];
            $to = $r['to'];
            $preset = 'current_month';
        }
    } else {
        $r = an_preset_range($preset, $now);
        $from = $r['from'];
        $to = $r['to'];
    }

    if ($from > $to) {
        $errors[] = 'range_reversed';
        [$from, $to] = [$to, $from];
    }
    if ($to > $today) {
        $errors[] = 'future_clamped';
        $to = $today;
        if ($from > $to) {
            $from = $to;
        }
    }

    $unit = (string)($get['unit'] ?? '');
    if (!in_array($unit, AN_UNITS, true)) {
        $unit = '';
    }
    $status = (string)($get['status'] ?? '');
    if (!in_array($status, ['profit', 'loss', 'incomplete', 'cost_centre'], true)) {
        $status = '';
    }
    $q = trim((string)($get['q'] ?? ''));
    $q = mb_substr($q, 0, 60);

    return [
        'preset' => $preset,
        'from'   => $from,
        'to'     => $to,
        'unit'   => $unit,
        'status' => $status,
        'q'      => $q,
        'errors' => $errors,
        'eth_from' => an_iso_to_eth($from),
        'eth_to'   => an_iso_to_eth($to),
    ];
}

/** Query-string for links that must keep the current filters. */
function an_filter_query(array $f, array $extra = []): string
{
    $base = ['preset' => 'custom'];
    [$fy, $fm, $fd] = $f['eth_from'];
    [$ty, $tm, $td] = $f['eth_to'];
    $base += ['ef_y' => $fy, 'ef_m' => $fm, 'ef_d' => $fd, 'et_y' => $ty, 'et_m' => $tm, 'et_d' => $td];
    if ($f['preset'] !== 'custom' && $f['preset'] !== 'lifetime') {
        $base = ['preset' => $f['preset']];
    } elseif ($f['preset'] === 'lifetime') {
        $base = ['preset' => 'lifetime'];
    }
    if ($f['unit'] !== '')   { $base['unit'] = $f['unit']; }
    if ($f['status'] !== '') { $base['status'] = $f['status']; }
    return http_build_query($extra + $base);
}

/** Exclusive upper bound for DATETIME comparisons: the day after $to at 00:00:00. */
function an_next_day(string $iso): string
{
    return (new DateTimeImmutable($iso))->modify('+1 day')->format('Y-m-d');
}
