<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/format-helpers.php';
$conn = getDB();

// Paginated initial load
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$totalCount = (int)$conn->query("SELECT COUNT(*) AS c FROM profiles WHERE status != 'Inactive'")->fetch_assoc()['c'];

// Sort params for initial load
$allowedSortCols = [
    'id', 'name', 'birth_year', 'city', 'registration_no',
    'salary', 'height', 'weight', 'education', 'gender', 'shortlisted', 'jaat', 'gotra',
    'varn', 'chashma', 'aahar', 'rashi', 'nadi'
];
$sortBy  = in_array($_GET['sort_by'] ?? '', $allowedSortCols, true)
           ? $_GET['sort_by']
           : 'registration_no';
// Default list order: birth year, then that year's registration serial.
$sortDir = strtoupper($_GET['sort_dir'] ?? ($sortBy === 'registration_no' ? 'ASC' : 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

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
        // Robust chronological sort supporting both 4-digit (2005) and 2-digit (05, 95) years; invalid/empty years go last
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
    case 'gotra':
        $orderSQL = "ORDER BY (gotra = '' OR gotra IS NULL), gotra {$sortDir}, id DESC";
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

$result = $conn->prepare("
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, weight, education, city, registration_no, registration_year, shortlisted,
           jaat, varn, chashma, aahar, rashi, nadi, COALESCE(mobile_no, mobile) AS display_mobile,
           COALESCE(profile_image, profile_photo) AS img_file
    FROM   profiles
    WHERE  status != 'Inactive'
    {$orderSQL}
    LIMIT  ? OFFSET ?
");
$result->bind_param('ii', $perPage, $offset);
$result->execute();
$res      = $result->get_result();
$profiles = [];
while ($row = $res->fetch_assoc()) $profiles[] = $row;
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MK Brahman — ब्राह्मण समाज विवाह नोंदणी केंद्र. सर्व प्रोफाइल पहा, शोधा व शॉर्टलिस्ट करा.">
    <title>MK Brahman — सर्व प्रोफाइल</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="container">

    <!-- HEADER -->
    <header class="header" role="banner">
        <div class="header-left">
            <button class="menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false">☰</button>
            <div>
                <span class="header-title">MK Brahman</span>
                <span class="header-subtitle">ब्राह्मण विवाह संस्था</span>
            </div>
        </div>
        <button class="more-btn" id="moreBtn" aria-label="More options">⋮</button>

        <!-- More dropdown -->
        <div id="moreMenu" class="more-menu" role="menu">
            <a href="shortlisted.php" role="menuitem">❤️ शॉर्टलिस्ट पहा</a>
            <a href="export-shortlisted.php" role="menuitem">📥 CSV Export</a>
            <a href="login.php" role="menuitem">🔐 Admin Login</a>
        </div>
    </header>

    <!-- SEARCH BOX -->
    <div class="search-box" id="searchBox">

        <!-- General search -->
        <div class="search-input-wrap">
            <input
                type="search"
                id="searchInput"
                data-url="search.php"
                placeholder="नाव, गोत्र, शहर किंवा 195 (मुलगा+1995)..."
                autocomplete="off"
                aria-label="Search profiles"
            >
            <span class="search-icon">🔍</span>
        </div>

        <!-- Mobile-specific search -->
        <div class="search-input-wrap mobile-search-wrap">
            <input
                type="tel"
                id="mobileSearchInput"
                data-url="search.php"
                placeholder="📱 मोबाईल नंबराने शोधा..."
                autocomplete="off"
                inputmode="numeric"
                maxlength="15"
                aria-label="Search by mobile number"
            >
            <span class="search-icon">📞</span>
        </div>

        <!-- FILTER BAR — one-line strip: [सर्व|मुलगा|मुलगी]  [sort▾][↓] -->
        <div class="filter-bar" id="filterBar" role="group" aria-label="Filters">

            <!-- Left: gender segmented control -->
            <div class="filter-gender-group">
                <button class="filter-chip active" id="genderAll"   data-gender=""  aria-pressed="true">सर्व</button>
                <button class="filter-chip"         id="genderBoy"  data-gender="1" aria-pressed="false">👦 मुलगा</button>
                <button class="filter-chip"         id="genderGirl" data-gender="2" aria-pressed="false">👧 मुलगी</button>
            </div>

            <!-- Right: sort controls -->
            <div class="filter-sort-group">
                <select id="sortBy" class="sort-select" aria-label="Sort by column">
                    <option value="registration_no" <?= $sortBy === 'registration_no' ? 'selected' : '' ?>>नोंद क्र. (जन्म वर्षानुसार)</option>
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>नोंद क्र. (पहिली नोंद आधी)</option>
                    <option value="birth_year" <?= $sortBy === 'birth_year' ? 'selected' : '' ?>>जन्म वर्ष (Age)</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>नाव (A→Z)</option>
                    <option value="rashi" <?= $sortBy === 'rashi' ? 'selected' : '' ?>>राशी (Rashi)</option>
                    <option value="nadi" <?= $sortBy === 'nadi' ? 'selected' : '' ?>>नाडी (Nadi)</option>
                    <option value="varn" <?= $sortBy === 'varn' ? 'selected' : '' ?>>वर्ण (Varn)</option>
                    <option value="salary" <?= $sortBy === 'salary' ? 'selected' : '' ?>>पगार (Salary)</option>
                    <option value="height" <?= $sortBy === 'height' ? 'selected' : '' ?>>उंची (Height)</option>
                    <option value="weight" <?= $sortBy === 'weight' ? 'selected' : '' ?>>वजन (Weight)</option>
                    <option value="education" <?= $sortBy === 'education' ? 'selected' : '' ?>>शिक्षण (Education)</option>
                    <option value="city" <?= $sortBy === 'city' ? 'selected' : '' ?>>शहर (City)</option>
                    <option value="gender" <?= $sortBy === 'gender' ? 'selected' : '' ?>>लिंग (Gender)</option>
                    <option value="shortlisted" <?= $sortBy === 'shortlisted' ? 'selected' : '' ?>>शॉर्टलिस्ट (❤)</option>
                </select>
                <button class="sort-dir-btn" id="sortDirBtn" data-dir="<?= $sortDir ?>" title="<?= $sortDir === 'ASC' ? 'चढता क्रम (A→Z / कमी ते जास्त)' : 'उतरता क्रम (Z→A / जास्त ते कमी)' ?>" aria-label="Toggle sort direction">
                    <span class="sort-icon"><?= $sortDir === 'ASC' ? '↑' : '↓' ?></span>
                </button>
            </div>

        </div>

    </div>
    <!-- /SEARCH BOX -->

    <!-- SECTION TAG -->
    <div class="section-tag" id="sectionTag">
        <span>एकूण प्रोफाइल</span>
        <span class="count" id="totalCount"><?= $totalCount ?></span>
    </div>

    <!-- SPINNER -->
    <div class="spinner" id="spinner"></div>

    <!-- DATA TABLE -->
    <div class="table-wrapper">
        <table class="compact-table" aria-label="Profiles list">
            <thead>
                <tr>
                    <!-- Column order: नाव | जात | जन्म | ठिकाण . व | उंची | गोत्र | शि. | पगार -->
                    <th scope="col" class="col-name sortable-th <?= $sortBy === 'name' ? 'th-sorted' : '' ?>" data-sort="name" role="button" tabindex="0" title="नावानुसार क्रमवारी लावा">
                        <div class="th-content"><span>नाव</span><span class="th-sort-icon"><?= $sortBy === 'name' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'jaat' ? 'th-sorted' : '' ?>" data-sort="jaat" role="button" tabindex="0" title="जातीनुसार क्रमवारी लावा">
                        <div class="th-content"><span>जात</span><span class="th-sort-icon"><?= $sortBy === 'jaat' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'birth_year' ? 'th-sorted' : '' ?>" data-sort="birth_year" role="button" tabindex="0" title="जन्म वर्षानुसार क्रमवारी लावा">
                        <div class="th-content"><span>जन्म</span><span class="th-sort-icon"><?= $sortBy === 'birth_year' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'city' ? 'th-sorted' : '' ?>" data-sort="city" role="button" tabindex="0" title="शहर / ठिकाणानुसार क्रमवारी लावा">
                        <div class="th-content"><span>ठिकाण . व</span><span class="th-sort-icon"><?= $sortBy === 'city' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'height' ? 'th-sorted' : '' ?>" data-sort="height" role="button" tabindex="0" title="उंचीनुसार क्रमवारी लावा">
                        <div class="th-content"><span>उंची</span><span class="th-sort-icon"><?= $sortBy === 'height' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'gotra' ? 'th-sorted' : '' ?>" data-sort="gotra" role="button" tabindex="0" title="गोत्रानुसार क्रमवारी लावा">
                        <div class="th-content"><span>गोत्र</span><span class="th-sort-icon"><?= $sortBy === 'gotra' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'education' ? 'th-sorted' : '' ?>" data-sort="education" role="button" tabindex="0" title="शिक्षणानुसार क्रमवारी लावा">
                        <div class="th-content"><span>शि.</span><span class="th-sort-icon"><?= $sortBy === 'education' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                    <th scope="col" class="sortable-th <?= $sortBy === 'salary' ? 'th-sorted' : '' ?>" data-sort="salary" role="button" tabindex="0" title="पगारानुसार क्रमवारी लावा">
                        <div class="th-content"><span>पगार</span><span class="th-sort-icon"><?= $sortBy === 'salary' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span></div>
                    </th>
                </tr>
            </thead>
            <tbody id="results">
                <?php if (empty($profiles)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:36px;color:#9ca3af;font-size:13px;">
                        <div style="font-size:36px;margin-bottom:8px;">👤</div>
                        <div>अजून कोणतीही प्रोफाइल नाही</div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($profiles as $row): ?>
                <tr class="data-row profile-row"
                    data-id="<?= (int)$row['id'] ?>"
                    onclick="openProfile(<?= (int)$row['id'] ?>)"
                    style="cursor:pointer;"
                    role="button"
                    tabindex="0"
                    aria-label="<?= htmlspecialchars($row['name']) ?> ची प्रोफाइल पहा"
                    onkeydown="if(event.key==='Enter')openProfile(<?= (int)$row['id'] ?>)">
                    <td class="col-name">
                        <?php if ((int)$row['gender'] === 1): ?>
                            <span class="gender-m">मुलगा</span>
                        <?php else: ?>
                            <span class="gender-f">मुलगी</span>
                        <?php endif; ?>
                        <span class="profile-name-text"><?= htmlspecialchars($row['name']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($row['jaat'] ?: '—') ?></td>
                    <td><?= htmlspecialchars(resolveFullYear($row['birth_year']) ?: ($row['birth_year'] ?: '—')) ?></td>
                    <td><?= htmlspecialchars($row['city'] . (!empty($row['weight']) ? ' . ' . (int)$row['weight'] : '')) ?></td>
                    <td><?= htmlspecialchars((int)$row['height_ft'] . "' " . (int)$row['height_in'] . '"') ?></td>
                    <td><?= htmlspecialchars($row['gotra'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($row['education'] ?: '—') ?></td>
                    <td><?= fmtSalaryShort((int)$row['salary']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.container -->

<script src="assets/js/app.js"></script>
</body>
</html>
