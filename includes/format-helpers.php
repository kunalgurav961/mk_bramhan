<?php
/**
 * MK Brahman — Display Format Helpers
 * Shared formatting functions used across listing/profile pages.
 */

/**
 * Resolve a stored birth_year (2-digit or 4-digit) to a full 4-digit year.
 * 4-digit input → returned as-is.
 * 2-digit input → 00-30 → 2000-2030, 31-99 → 1931-1999.
 */
function resolveFullYear(string $raw): string {
    $raw = trim($raw);
    if (strlen($raw) === 4) return $raw;           // already full year
    $by = (int)$raw;
    return ($by >= 0 && $by <= 30) ? '20' . str_pad($raw, 2, '0', STR_PAD_LEFT)
                                   : '19' . $raw;
}

/**
 * Format height + weight for combined display: "5.5/70"
 * If weight is empty/0, shows just height: "5.5"
 */
function fmtHeightWeight(int $ft, int $in, $weight): string {
    $h = $ft . '.' . $in;
    $w = (int)$weight;
    return $w > 0 ? "{$h}/{$w}" : $h;
}

/**
 * Format name + jaat for combined display: "अपर्णा जोशी. को"
 * If jaat is empty, returns just name.
 */
function fmtNameJaat(string $name, string $jaat): string {
    $j = trim($jaat);
    return $j !== '' ? "{$name}. {$j}" : $name;
}

/**
 * Format salary stored in thousands for short display.
 * Examples: 0 → '—', 30 → '30k', 300 → '300k', 1000 → '10L', 1550 → '15.5L'
 * The DB stores salary in thousands (30 = ₹30,000).
 */
function fmtSalaryShort(int $val): string {
    if ($val <= 0) return '—';
    if ($val < 100) return $val . 'k';            // 30 → 30k
    if ($val < 1000) return $val . 'k';           // 300 → 300k
    // >= 1000 → lakhs (divide by 100)
    $lakhs = $val / 100;
    if ($lakhs == floor($lakhs)) {
        return (int)$lakhs . 'L';                 // 1000 → 10L
    }
    return rtrim(rtrim(number_format($lakhs, 1), '0'), '.') . 'L';  // 1550 → 15.5L
}

/**
 * Format नोंदणी क्रमांक: "registrationYear.regNo" (e.g. "1993.01")
 * Falls back to just regNo if regYear is empty.
 */
function fmtNondaniKramank(string $regYear, string $regNo): string {
    $regYear = trim($regYear);
    $regNo   = trim($regNo);
    if ($regYear !== '') {
        return $regYear . '.' . $regNo;
    }
    return $regNo;
}

/**
 * @deprecated Use fmtNondaniKramank() instead.
 * Kept for backward compatibility during transition.
 */
function fmtBirthReg(string $birthYear, string $regNo): string {
    return resolveFullYear($birthYear) . '.' . $regNo;
}
