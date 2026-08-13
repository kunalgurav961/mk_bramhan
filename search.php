<?php
/**
 * MK Brahman — Smart Search API (search.php)
 *
 * Supports:
 *   - Mobile number search (7+ digits → mobile_no / mobile LIKE)
 *   - Numeric code (195 → gender=1, birth_year=95)
 *   - Marathi/English transliteration
 *   - Shortlisted filter
 *   - AJAX partial-HTML response (returns <tr> rows)
 */

require_once 'includes/db.php';
require_once 'includes/transliteration.php';

header('Content-Type: text/html; charset=utf-8');

$conn        = getDB();
$rawSearch      = trim($_GET['search']      ?? '');
$shortlisted    = isset($_GET['shortlisted']) && $_GET['shortlisted'] === '1';
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 50;
$offset         = ($page - 1) * $perPage;

// ── NEW FILTER PARAMS ────────────────────────────────────────────────────────
$filterGender    = $_GET['gender']     ?? '';          // '' | '0' | '1'
$filterCity      = trim($_GET['city']  ?? '');         // city name or ''
$filterBirthYear = trim($_GET['birth_year'] ?? '');    // year or ''

// Sort whitelist — maps safe token → SQL expression
$sortMap = [
    'birth_year_asc'  => 'birth_year ASC',
    'birth_year_desc' => 'birth_year DESC',
    'name_asc'        => 'name ASC',
    'id_desc'         => 'id DESC',
];
$sortToken  = $_GET['sort'] ?? 'birth_year_asc';
$orderBySQL = $sortMap[$sortToken] ?? 'birth_year ASC';

// ── SMART SEARCH PARSING ──────────────────────────────────────────────────────

$numericParsed   = null;
$mobileSearch    = false;     // True when input looks like a phone number
$searchTerms     = [];        // Array of strings to OR-search
$genderFilter    = null;
$birthYearFilter = null;

if ($rawSearch !== '') {
    // Rule 0: Mobile number — 7 or more consecutive digits (partial match allowed)
    // Must check BEFORE the 3-digit gender+year parser so "9876543210" is not mis-parsed.
    $digitsOnly = preg_replace('/[^0-9]/', '', $rawSearch);
    if (strlen($digitsOnly) >= 7) {
        $mobileSearch = true;
    } else {
        // Rule A: Pure numeric 3-digit code (e.g. "195", "093")
        $numericParsed = parseNumericSearch($rawSearch);

        if ($numericParsed !== null) {
            $genderFilter    = $numericParsed['gender'];
            $birthYearFilter = $numericParsed['birth_year'];
        } else {
            // Rule B: Text search — get transliteration candidates
            $candidates  = getTransliterationCandidates($rawSearch);
            $searchTerms = array_unique(array_merge([$rawSearch], $candidates));
        }
    }
}

// ── BUILD SQL QUERY ───────────────────────────────────────────────────────────

$whereClauses = [];
$params       = [];
$types        = '';

// Always filter active profiles
$whereClauses[] = "status != 'Inactive'";

if ($shortlisted) {
    $whereClauses[] = 'shortlisted = 1';
}

// Gender filter
if ($filterGender !== '' && in_array($filterGender, ['0', '1'], true)) {
    $whereClauses[] = 'gender = ?';
    $params[]        = (int)$filterGender;
    $types          .= 'i';
}

// City filter
if ($filterCity !== '') {
    $whereClauses[] = 'city = ?';
    $params[]        = $filterCity;
    $types          .= 's';
}

// Birth year filter
if ($filterBirthYear !== '') {
    $whereClauses[] = 'birth_year = ?';
    $params[]        = $filterBirthYear;
    $types          .= 's';
}

if ($mobileSearch) {
    // Mobile number: search both mobile_no and legacy mobile column
    $like = "%{$rawSearch}%";
    $whereClauses[] = '(mobile_no LIKE ? OR mobile LIKE ?)';
    $params[]        = $like;
    $params[]        = $like;
    $types          .= 'ss';

} elseif ($numericParsed !== null) {
    // Numeric: exact gender + birth_year match
    // Skip if filter-bar already provides gender/birth_year to avoid duplicate clauses
    if ($genderFilter === null && $filterGender === '') {
        if ($numericParsed['gender'] !== null) {
            $whereClauses[] = 'gender = ?';
            $params[]        = $numericParsed['gender'];
            $types          .= 'i';
        }
    }
    if ($filterBirthYear === '') {
        $whereClauses[] = 'birth_year = ?';
        $params[]        = $birthYearFilter;
        $types          .= 's';
    }

} elseif (!empty($searchTerms)) {
    // Text search: each term becomes a LIKE group
    $termClauses = [];
    foreach ($searchTerms as $term) {
        $like = "%{$term}%";
        // Search across all relevant columns
        $termClauses[] = '(name LIKE ? OR gotra LIKE ? OR city LIKE ? OR registration_no LIKE ? OR education LIKE ? OR occupation LIKE ? OR father_name LIKE ? OR mobile_no LIKE ? OR mobile LIKE ?)';
        for ($k = 0; $k < 9; $k++) {
            $params[] = $like;
            $types   .= 's';
        }
    }
    $whereClauses[] = '(' . implode(' OR ', $termClauses) . ')';

} elseif ($rawSearch === '') {
    // Empty search → show all
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

$sql = "
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, education, city, registration_no, shortlisted,
           profile_image, profile_photo, mobile_no,
           COALESCE(mobile_no, mobile) AS display_mobile
    FROM   profiles
    {$whereSQL}
    ORDER  BY {$orderBySQL}
    LIMIT  ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// ── RENDER HTML ROWS ──────────────────────────────────────────────────────────

/**
 * Get the best image src for a profile row.
 */
function getImgSrc(array $row): string {
    $filename = $row['profile_image'] ?? $row['profile_photo'] ?? '';
    if ($filename && file_exists(__DIR__ . '/uploads/profiles/' . basename($filename))) {
        return 'uploads/profiles/' . htmlspecialchars(basename($filename));
    }
    return 'assets/img/default-avatar.svg';
}

if (empty($rows)): ?>
<tr>
    <td colspan="10" style="text-align:center;padding:36px;color:#9ca3af;font-size:13px;">
        <div style="font-size:36px;margin-bottom:8px;"><?= $shortlisted ? '💔' : '🔍' ?></div>
        <div><?= $shortlisted ? 'शॉर्टलिस्ट रिकामी आहे' : 'कोणतीही प्रोफाइल सापडली नाही' ?></div>
    </td>
</tr>
<?php else: ?>
<?php foreach ($rows as $row): ?>
<tr class="data-row profile-row" data-id="<?= (int)$row['id'] ?>" onclick="openProfile(<?= (int)$row['id'] ?>)" style="cursor:pointer;" role="button" tabindex="0" aria-label="<?= htmlspecialchars($row['name']) ?> ची प्रोफाइल पहा">
    <td>
        <?php if ((int)$row['gender'] === 1): ?>
            <span class="gender-m">मुलगा</span>
        <?php else: ?>
            <span class="gender-f">मुलगी</span>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($row['birth_year']) ?></td>
    <td style="max-width:80px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['gotra']) ?></td>
    <td><?= (int)$row['height_ft'] ?>'<?= (int)$row['height_in'] ?>"</td>
    <td><?= htmlspecialchars($row['salary']) ?>L</td>
    <td style="max-width:70px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($row['education']) ?></td>
    <td><?= htmlspecialchars($row['city']) ?></td>
    <td><?= htmlspecialchars($row['registration_no']) ?></td>
    <td class="col-heart" onclick="event.stopPropagation()">
        <button class="shortlist-btn"
                data-id="<?= (int)$row['id'] ?>"
                title="<?= $row['shortlisted'] ? 'शॉर्टलिस्टमधून काढा' : 'शॉर्टलिस्टला जोडा' ?>">
            <?= $row['shortlisted'] ? '❤️' : '🤍' ?>
        </button>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>