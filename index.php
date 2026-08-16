<?php
session_start();
require_once 'includes/db.php';
$conn = getDB();

// Paginated initial load
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$totalCount = (int)$conn->query("SELECT COUNT(*) AS c FROM profiles WHERE status != 'Inactive'")->fetch_assoc()['c'];

$result = $conn->prepare("
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, education, city, registration_no, shortlisted,
           COALESCE(profile_image, profile_photo) AS img_file
    FROM   profiles
    WHERE  status != 'Inactive'
    ORDER  BY id DESC
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
                    <option value="id"         selected>नोंद क्र.</option>
                    <option value="name">नाव</option>
                    <option value="birth_year">वर्ष</option>
                    <option value="city">शहर</option>
                </select>
                <button class="sort-dir-btn" id="sortDirBtn" data-dir="DESC" title="Descending order" aria-label="Toggle sort direction">
                    <span class="sort-icon">↓</span>
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
                    <th>M/F</th>
                    <th>वर्ष</th>
                    <th>नाव</th>
                    <th>गोत्र</th>
                    <th>उंची</th>
                    <th>पगार</th>
                    <th>शिक्षण</th>
                    <th>शहर</th>
                    <th>नोंद</th>
                    <th>❤</th>
                </tr>
            </thead>
            <tbody id="results">
                <?php if (empty($profiles)): ?>
                <tr>
                    <td colspan="10" style="text-align:center;padding:36px;color:#9ca3af;font-size:13px;">
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
                    <td>
                        <?php if ((int)$row['gender'] === 1): ?>
                            <span class="gender-m">मुलगा</span>
                        <?php else: ?>
                            <span class="gender-f">मुलगी</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['birth_year']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['gotra']) ?></td>
                    <td><?= (int)$row['height_ft'] ?>'<?= (int)$row['height_in'] ?>"</td>
                    <td><?= htmlspecialchars($row['salary']) ?>L</td>
                    <td><?= htmlspecialchars($row['education']) ?></td>
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
            </tbody>
        </table>
    </div>

</div><!-- /.container -->

<script src="assets/js/app.js"></script>
</body>
</html>