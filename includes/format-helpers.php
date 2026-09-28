<?php
/**
 * MK Brahman — Display Format Helpers
 * Shared formatting functions used across listing/profile pages.
 */

/**
 * Resolve a stored birth_year (2-digit or 4-digit) to a full 4-digit year.
 * 4-digit input → returned as-is.
 * 2-digit input → 00-30 → 2000-2030, 31-99 → 1931-1999.
 * Empty/null/invalid → returns empty string ''.
 */
function resolveFullYear(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0') return '';
    if (strlen($raw) === 4 && ctype_digit($raw)) return $raw;
    if (ctype_digit($raw)) {
        $by = (int)$raw;
        return ($by >= 0 && $by <= 30) ? '20' . str_pad($raw, 2, '0', STR_PAD_LEFT)
                                       : '19' . str_pad($raw, 2, '0', STR_PAD_LEFT);
    }
    return $raw;
}

/**
 * 2-digit birth-year reference value (00 – 99) for internal/reference purposes only.
 * MUST NOT be visible to general public audience.
 */
function getBirthYearRef(?string $raw): string {
    $year = resolveFullYear($raw);
    if (strlen($year) >= 2) {
        return substr($year, -2);
    }
    return '';
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
 * Format जन्म वर्ष.नोंदणी वर्ष: "1994.04"
 * Examples: (1994, 04) -> "1994.04", (94, 4) -> "1994.04", (1994, 2004) -> "1994.04", (1994, '') -> "1994"
 */
function fmtBirthRegYear(?string $birthYear, ?string $regYear): string {
    $by = resolveFullYear(trim((string)$birthYear));
    if ($by === '') return '—';
    $ry = trim((string)$regYear);
    if ($ry !== '') {
        if (strlen($ry) === 4) {
            $ry = substr($ry, -2);
        } else {
            $ry = str_pad($ry, 2, '0', STR_PAD_LEFT);
        }
        return $by . '.' . $ry;
    }
    return $by;
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
 * Normalise varn values to the project's required dropdown values.
 * Supports the legacy Marathi labels as well.
 */
function normalizeVarnValue(?string $varn): string {
    $v = strtolower(trim((string)$varn));
    $map = [
        'gora' => 'Gora',
        'गोरा' => 'Gora',
        'gahu' => 'Gahu',
        'गहू' => 'Gahu',
        'गव्हाळ' => 'Gahu',
        'sawala' => 'Sawala',
        'सावळा' => 'Sawala',
    ];
    return $map[$v] ?? trim((string)$varn);
}

function varnSortOrder(?string $varn): int {
    $v = strtolower(trim((string)$varn));
    $map = [
        'gora' => 1,
        'गोरा' => 1,
        'gahu' => 2,
        'गहू' => 2,
        'गव्हाळ' => 2,
        'sawala' => 3,
        'सावळा' => 3,
    ];
    return $map[$v] ?? 99;
}

function fmtChashmaText(int $chashma): string {
    if ($chashma === 2) return 'lense';
    if ($chashma === 1) return 'aahe';
    return 'nahi';
}

/**
 * Format varn + chashma for combined display: "Gora / aahe"
 * If varn is empty, shows chashma status if applicable.
 * If chashma is 0 and varn is empty, returns '—'.
 */
function fmtVarnChashma(string $varn, int $chashma): string {
    $v = normalizeVarnValue($varn);
    $status = fmtChashmaText($chashma);

    if ($v !== '' && $status !== 'nahi') {
        return $v . ' / ' . $status;
    }
    if ($v !== '') {
        return $v;
    }
    return $status === 'nahi' ? '—' : $status;
}

/**
 * Format वर्ण (Varna) for member listing:
 * Displays the required Gora / Gahu / Sawala values and adds a label when chashma is present.
 */
function fmtVarn(?string $varn, int $chashma = 0): string {
    $v = normalizeVarnValue($varn);
    if ($v === '') {
        $status = fmtChashmaText($chashma);
        return $status === 'nahi' ? '—' : $status;
    }

    $status = fmtChashmaText($chashma);
    return $status === 'nahi' ? $v : $v . ' / ' . $status;
}

/**
 * Format Rashi and Nadi together if desired:
 * Examples:
 *   ("कर्क", "मध्य") → "कर्क — मध्य"
 *   ("कर्क", "")     → "कर्क"
 *   ("", "मध्य")     → "मध्य"
 */
function fmtRashiNadi(?string $rashi, ?string $nadi): string {
    $r = trim((string)$rashi);
    $n = trim((string)$nadi);
    if ($r !== '' && $n !== '') {
        return "{$r} — {$n}";
    }
    return $r !== '' ? $r : ($n !== '' ? $n : '—');
}

/**
 * @deprecated Use fmtNondaniKramank() instead.
 * Kept for backward compatibility during transition.
 */
function fmtBirthReg(string $birthYear, string $regNo): string {
    return resolveFullYear($birthYear) . '.' . $regNo;
}

