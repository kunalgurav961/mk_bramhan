<?php
/**
 * MK Brahman — Smart Search & Filter API  (search.php  v3.0)
 *
 * GET parameters accepted
 * ─────────────────────────────────────────────────────────────────
 *  search       string   Text / mobile / numeric-code query
 *  gender       int      0 = girl, 1 = boy, '' = all
 *  sort_by      string   column to sort: id|name|birth_year|city  (default: registration_no)
 *  sort_dir     string   ASC | DESC  (default: ASC, birth year then serial)
 *  shortlisted  int      1 = only shortlisted profiles
 *  page         int      pagination page (default: 1)
 *
 * Returns AJAX partial-HTML  <tr> rows for the results tbody.
 */

require_once 'includes/db.php';
require_once 'includes/transliteration.php';
require_once 'includes/format-helpers.php';

header('Content-Type: text/html; charset=utf-8');

$conn        = getDB();
$rawSearch   = trim($_GET['search']   ?? '');
$shortlisted = isset($_GET['shortlisted']) && $_GET['shortlisted'] === '1';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 50;
$offset      = ($page - 1) * $perPage;

// ── FILTER PARAMS ─────────────────────────────────────────────────

// Gender filter: '' = all, '1' = boy (gender=1), '2' = girl (gender=2 in DB)
$genderParam = $_GET['gender'] ?? '';
$genderFilter = in_array($genderParam, ['1', '2'], true) ? (int)$genderParam : null;

// Sort
$allowedSortCols = [
    'id', 'name', 'birth_year', 'city', 'registration_no',
    'salary', 'height', 'weight', 'education', 'gender', 'shortlisted', 'jaat',
    'varn', 'chashma', 'aahar', 'rashi', 'nadi'
];
$sortBy  = in_array($_GET['sort_by'] ?? '', $allowedSortCols, true)
           ? $_GET['sort_by']
           : 'registration_no';
$sortDir = strtoupper($_GET['sort_dir'] ?? ($sortBy === 'registration_no' ? 'ASC' : 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

// ── SMART SEARCH PARSING ──────────────────────────────────────────

$numericParsed   = null;
$mobileSearch    = false;
$searchTerms     = [];
$numGenderFilter = null;   // from numeric code only
$birthYearFilter = null;

if ($rawSearch !== '') {
    $digitsOnly = preg_replace('/[^0-9]/', '', $rawSearch);
    if (strlen($digitsOnly) >= 7) {
        // Looks like a phone number
        $mobileSearch = true;
    } else {
        $numericParsed = parseNumericSearch($rawSearch);
        if ($numericParsed !== null) {
            $numGenderFilter = $numericParsed['gender'];
            $birthYearFilter = $numericParsed['birth_year'];
        } else {
            $candidates  = getTransliterationCandidates($rawSearch);
            $searchTerms = array_unique(array_merge([$rawSearch], $candidates));
        }
    }
}

// ── BUILD SQL QUERY ───────────────────────────────────────────────

$whereClauses = [];
$params       = [];
$types        = '';

// Always filter active profiles
$whereClauses[] = "status != 'Inactive'";

if ($shortlisted) {
    $whereClauses[] = 'shortlisted = 1';
}

// UI Gender filter (dropdown/toggle) — takes precedence over numeric-code gender
if ($genderFilter !== null) {
    $whereClauses[] = 'gender = ?';
    $params[]        = $genderFilter;
    $types          .= 'i';
}

if ($mobileSearch) {
    $like = "%{$rawSearch}%";
    $whereClauses[] = '(mobile_no LIKE ? OR mobile LIKE ?)';
    $params[]        = $like;
    $params[]        = $like;
    $types          .= 'ss';

} elseif ($numericParsed !== null) {
    // Numeric code: apply gender from code only if no UI gender filter set
    if ($genderFilter === null && $numGenderFilter !== null) {
        $whereClauses[] = 'gender = ?';
        $params[]        = $numGenderFilter;
        $types          .= 'i';
    }
    $whereClauses[] = 'birth_year = ?';
    $params[]        = $birthYearFilter;
    $types          .= 's';

} elseif (!empty($searchTerms)) {
    $termClauses = [];
    foreach ($searchTerms as $term) {
        $like = "%{$term}%";
        $termClauses[] = '(name LIKE ? OR gotra LIKE ? OR city LIKE ? OR registration_no LIKE ? OR education LIKE ? OR occupation LIKE ? OR father_name LIKE ? OR mobile_no LIKE ? OR mobile LIKE ?)';
        for ($k = 0; $k < 9; $k++) {
            $params[] = $like;
            $types   .= 's';
        }
    }
    $whereClauses[] = '(' . implode(' OR ', $termClauses) . ')';
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

// Safe column + direction (validated above)
switch ($sortBy) {
    case 'height':
        $orderSQL = "ORDER BY (height_ft = 0), (height_ft * 12 + height_in) {$sortDir}, id DESC";
        break;
    case 'salary':
        if ($sortDir === 'ASC') {
            $orderSQL = "ORDER BY (salary = 0 OR salary IS NULL), salary ASC, id DESC";
        } else {
            $orderSQL = "ORDER BY salary DESC, id DESC";
        }
        break;
    case 'birth_year':
        $yearNormSQL = "CASE
            WHEN birth_year IS NULL OR birth_year = '' OR birth_year = '0' THEN 0
            WHEN CAST(birth_year AS UNSIGNED) <= 30 THEN 2000 + CAST(birth_year AS UNSIGNED)
            WHEN CAST(birth_year AS UNSIGNED) < 100 THEN 1900 + CAST(birth_year AS UNSIGNED)
            ELSE CAST(birth_year AS UNSIGNED)
        END";
        if ($sortDir === 'ASC') {
            $orderSQL = "ORDER BY ({$yearNormSQL} = 0) ASC, {$yearNormSQL} ASC, id DESC";
        } else {
            $orderSQL = "ORDER BY ({$yearNormSQL} = 0) ASC, {$yearNormSQL} DESC, id DESC";
        }
        break;
    case 'weight':
        if ($sortDir === 'ASC') {
            $orderSQL = "ORDER BY (weight = 0 OR weight IS NULL), weight ASC, id DESC";
        } else {
            $orderSQL = "ORDER BY weight DESC, id DESC";
        }
        break;
    case 'name':
        $orderSQL = "ORDER BY (name = '' OR name IS NULL), name {$sortDir}, id DESC";
        break;
    case 'city':
        $orderSQL = "ORDER BY (city = '' OR city IS NULL), city {$sortDir}, id DESC";
        break;
    case 'education':
        $orderSQL = "ORDER BY (education = '' OR education IS NULL), education {$sortDir}, id DESC";
        break;
    case 'registration_no':
        $orderSQL = "ORDER BY
            CASE WHEN registration_no REGEXP '^[0-9]{4}[.][0-9]+$' THEN 0 ELSE 1 END ASC,
            CAST(SUBSTRING_INDEX(registration_no, '.', 1) AS UNSIGNED) {$sortDir},
            CAST(SUBSTRING_INDEX(registration_no, '.', -1) AS UNSIGNED) {$sortDir},
            id {$sortDir}";
        break;
    case 'gender':
        $orderSQL = "ORDER BY gender {$sortDir}, id DESC";
        break;
    case 'shortlisted':
        $orderSQL = "ORDER BY shortlisted {$sortDir}, id DESC";
        break;
    case 'jaat':
        $orderSQL = "ORDER BY (jaat = '' OR jaat IS NULL), jaat {$sortDir}, id DESC";
        break;
    case 'varn':
        $orderSQL = "ORDER BY (
            CASE
                WHEN varn IS NULL OR varn = '' THEN 99
                WHEN LOWER(TRIM(varn)) IN ('gora', 'गोरा') THEN 1
                WHEN LOWER(TRIM(varn)) IN ('gahu', 'गहू', 'गव्हाळ') THEN 2
                WHEN LOWER(TRIM(varn)) IN ('sawala', 'सावळा') THEN 3
                ELSE 99
            END
        ) ASC, id DESC";
        break;
    case 'chashma':
        $orderSQL = "ORDER BY chashma {$sortDir}, id DESC";
        break;
    case 'aahar':
        $orderSQL = "ORDER BY (aahar = '' OR aahar IS NULL), aahar {$sortDir}, id DESC";
        break;
    case 'rashi':
        $orderSQL = "ORDER BY (rashi = '' OR rashi IS NULL), rashi {$sortDir}, id DESC";
        break;
    case 'nadi':
        $orderSQL = "ORDER BY (nadi = '' OR nadi IS NULL), nadi {$sortDir}, id DESC";
        break;
    case 'id':
    default:
        $orderSQL = "ORDER BY id {$sortDir}";
        break;
}

$sql = "
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, weight, education, city, registration_no, registration_year, shortlisted,
           jaat, varn, chashma, aahar, rashi, nadi, profile_image, profile_photo, mobile_no,
           COALESCE(mobile_no, mobile) AS display_mobile
    FROM   profiles
    {$whereSQL}
    {$orderSQL}
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

// ── RENDER HTML ROWS ──────────────────────────────────────────────

function getImgSrc(array $row): string {
    $filename = $row['profile_image'] ?? $row['profile_photo'] ?? '';
    if ($filename && file_exists(__DIR__ . '/uploads/profiles/' . basename($filename))) {
        return 'uploads/profiles/' . htmlspecialchars(basename($filename));
    }
    return 'assets/img/default-avatar.svg';
}

if (empty($rows)): ?>
<tr>
    <td colspan="7" style="text-align:center;padding:36px;color:#9ca3af;font-size:13px;">
        <div style="font-size:36px;margin-bottom:8px;"><?= $shortlisted ? '💔' : '🔍' ?></div>
        <div><?= $shortlisted ? 'शॉर्टलिस्ट रिकामी आहे' : 'कोणतीही प्रोफाइल सापडली नाही' ?></div>
    </td>
</tr>
<?php else: ?>
<?php foreach ($rows as $row): ?>
<tr class="data-row profile-row"
    data-id="<?= (int)$row['id'] ?>"
    onclick="openProfile(<?= (int)$row['id'] ?>)"
    style="cursor:pointer;"
    role="button"
    tabindex="0"
    aria-label="<?= htmlspecialchars($row['name']) ?> ची प्रोफाइल पहा">
    <td class="col-name">
        <?php if ((int)$row['gender'] === 1): ?>
            <span class="gender-m">मुलगा</span>
        <?php else: ?>
            <span class="gender-f">मुलगी</span>
        <?php endif; ?>
        <span class="profile-name-text"><?= htmlspecialchars($row['name']) ?></span>
    </td>
    <td><?= htmlspecialchars(resolveFullYear($row['birth_year']) ?: ($row['birth_year'] ?: '—')) ?></td>
    <td><?= htmlspecialchars($row['city'] . (!empty($row['weight']) ? ' . ' . (int)$row['weight'] : '')) ?></td>
    <td><?= htmlspecialchars((int)$row['height_ft'] . "' " . (int)$row['height_in'] . '"') ?></td>
    <td><?= htmlspecialchars($row['gotra'] ?: '—') ?></td>
    <td><?= htmlspecialchars($row['education'] ?: '—') ?></td>
    <td><?= fmtSalaryShort((int)$row['salary']) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
