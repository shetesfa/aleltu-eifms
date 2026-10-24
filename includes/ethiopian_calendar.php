<?php
/**
 * የኢትዮጵያ ዘመን አቆጣጠር እና ሰዓት አስተዳዳሪ ሞጁል (Ethiopian Calendar & Time Helper)
 * Aleltu Enterprise Integrated Farm Management System
 */

class EthiopianDate {
    const ETHIOPIC_EPOCH = 1723856;

    public static array $months = [
        1 => 'መስከረም',
        2 => 'ጥቅምት',
        3 => 'ህዳር',
        4 => 'ታህሳስ',
        5 => 'ጥር',
        6 => 'የካቲት',
        7 => 'መጋቢት',
        8 => 'ሚያዝያ',
        9 => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜን'
    ];

    public static array $weekdays = [
        0 => 'እሁድ',
        1 => 'ሰኞ',
        2 => 'ማክሰኞ',
        3 => 'ረቡዕ',
        4 => 'ሐሙስ',
        5 => 'አርብ',
        6 => 'ቅዳሜ'
    ];

    public static function fromGregorian(int $gy, int $gm, int $gd): array {
        $jdn = gregoriantojd($gm, $gd, $gy);
        $r = ($jdn - self::ETHIOPIC_EPOCH) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $ey = 4 * intdiv($jdn - self::ETHIOPIC_EPOCH, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $em = intdiv($n, 30) + 1;
        $ed = ($n % 30) + 1;
        return [$ey, $em, $ed];
    }

    public static function toGregorian(int $ey, int $em, int $ed): array {
        $jdn = self::ETHIOPIC_EPOCH + 365 * $ey + intdiv($ey, 4) + 30 * ($em - 1) + $ed - 1;
        $greg = jdtogregorian($jdn);
        [$gm, $gd, $gy] = explode('/', $greg);
        return [(int)$gy, (int)$gm, (int)$gd];
    }

    public static function format(?string $dateStr, string $format = 'medium'): string {
        if (empty($dateStr)) return '—';
        $time = strtotime($dateStr);
        if ($time === false) return $dateStr;

        $gy = (int)date('Y', $time);
        $gm = (int)date('n', $time);
        $gd = (int)date('j', $time);
        $w  = (int)date('w', $time);

        [$ey, $em, $ed] = self::fromGregorian($gy, $gm, $gd);
        $monthName = self::$months[$em] ?? '';
        $weekdayName = self::$weekdays[$w] ?? '';

        return match ($format) {
            'short'   => sprintf('%02d/%02d/%04d', $ed, $em, $ey),
            'numeric' => sprintf('%04d-%02d-%02d', $ey, $em, $ed),
            'long'    => sprintf('%s፣ %s %d ቀን %d ዓ.ም', $weekdayName, $monthName, $ed, $ey),
            default   => sprintf('%s %d/%d', $monthName, $ed, $ey)
        };
    }

    public static function formatTime(?string $timeStr): string {
        if (empty($timeStr)) return '—';
        $timestamp = strtotime($timeStr);
        if ($timestamp === false) return $timeStr;

        $h = (int)date('H', $timestamp);
        $m = (int)date('i', $timestamp);

        // የኢትዮጵያ 12 ሰዓት አቆጣጠር
        // 06:00 => 12:00 ጠዋት
        // 07:00 => 01:00 ጠዋት
        $ethHour = ($h >= 6) ? ($h - 6) : ($h + 6);
        if ($ethHour === 0) $ethHour = 12;

        $period = '';
        if ($h >= 6 && $h < 12) {
            $period = 'ከጠዋቱ';
        } elseif ($h >= 12 && $h < 18) {
            $period = 'ከቀኑ';
        } elseif ($h >= 18 && $h < 24) {
            $period = 'ከምሽቱ';
        } else {
            $period = 'ከሌሊቱ';
        }

        return sprintf('%s %d:%02d', $period, $ethHour, $m);
    }

    public static function formatDateTime(?string $dateTimeStr): string {
        if (empty($dateTimeStr)) return '—';
        $d = self::format($dateTimeStr, 'medium');
        $t = self::formatTime($dateTimeStr);
        return "{$d} {$t}";
    }

    public static function today(): array {
        $t = time();
        return self::fromGregorian((int)date('Y', $t), (int)date('n', $t), (int)date('j', $t));
    }

    public static function currentYear(): int {
        return self::today()[0];
    }

    public static function currentMonth(): int {
        return self::today()[1];
    }

    public static function currentDay(): int {
        return self::today()[2];
    }

    public static function currentMonthName(): string {
        return self::$months[self::currentMonth()] ?? '';
    }

    public static function ethMonthRange(?int $ey = null, ?int $em = null): array {
        if ($ey === null || $em === null) {
            [$curY, $curM] = self::today();
            $ey = $ey ?? $curY;
            $em = $em ?? $curM;
        }
        $lastDay = ($em === 13) ? ((($ey % 4) === 3) ? 6 : 5) : 30;
        [$sy, $sm, $sd] = self::toGregorian($ey, $em, 1);
        [$ey2, $em2, $ed2] = self::toGregorian($ey, $em, $lastDay);
        return [
            'from' => sprintf('%04d-%02d-%02d', $sy, $sm, $sd),
            'to'   => sprintf('%04d-%02d-%02d', $ey2, $em2, $ed2)
        ];
    }

    public static function ethYearRange(?int $ey = null): array {
        $ey = $ey ?? self::currentYear();
        $pagumeDays = (($ey % 4) === 3) ? 6 : 5;
        [$sy, $sm, $sd] = self::toGregorian($ey, 1, 1);
        [$ey2, $em2, $ed2] = self::toGregorian($ey, 13, $pagumeDays);
        return [
            'from' => sprintf('%04d-%02d-%02d', $sy, $sm, $sd),
            'to'   => sprintf('%04d-%02d-%02d', $ey2, $em2, $ed2)
        ];
    }

    public static function getMonths(): array {
        return self::$months;
    }
}

// Global procedural helper functions
function eth_date(?string $dateStr, string $format = 'medium'): string {
    return EthiopianDate::format($dateStr, $format);
}

function eth_time(?string $timeStr): string {
    return EthiopianDate::formatTime($timeStr);
}

function eth_datetime(?string $dateTimeStr): string {
    return EthiopianDate::formatDateTime($dateTimeStr);
}

function eth_month_range(?int $ey = null, ?int $em = null): array {
    return EthiopianDate::ethMonthRange($ey, $em);
}

function eth_year_range(?int $ey = null): array {
    return EthiopianDate::ethYearRange($ey);
}

function eth_current_year(): int {
    return EthiopianDate::currentYear();
}

function eth_current_month(): int {
    return EthiopianDate::currentMonth();
}

