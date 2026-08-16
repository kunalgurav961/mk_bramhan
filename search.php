<?php
/**
 * MK Brahman — Smart Search & Filter API  (search.php  v3.0)
 *
 * GET parameters accepted
 * ─────────────────────────────────────────────────────────────────
 *  search       string   Text / mobile / numeric-code query
 *  gender       int      0 = girl, 1 = boy, '' = all
 *  sort_by      string   column to sort: id|name|birth_year|city  (default: id)
 *  sort_dir     string   ASC | DESC  (default: DESC)
 *  shortlisted  int      1 = only shortlisted profiles
 *  page         int      pagination page (default: 1)
 *
 * Returns AJAX partial-HTML  <tr> rows for the results tbody.
 */

require_once 'includes/db.php';
require_once 'includes/transliteration.php';

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
$allowedSortCols = ['id', 'name', 'birth_year', 'city', 'registration_no'];
$sortBy  = in_array($_GET['sort_by'] ?? '', $allowedSortCols, true)
           ? $_GET['sort_by']
           : 'id';
$sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

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
$orderSQL = "ORDER BY `{$sortBy}` {$sortDir}";

$sql = "
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, education, city, registration_no, shortlisted,
           profile_image, profile_photo, mobile_no,
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
    <td colspan="10" style="text-align:center;padding:36px;color:#9ca3af;font-size:13px;">
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