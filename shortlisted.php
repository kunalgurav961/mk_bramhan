<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/format-helpers.php';
$conn = getDB();

$result = $conn->query("
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, weight, education, city, registration_no, registration_year, shortlisted,
           jaat, varn, chashma, aahar, rashi, nadi, COALESCE(profile_image, profile_photo) AS img_file
    FROM   profiles
    WHERE  shortlisted = 1
    ORDER  BY CASE
        WHEN birth_year IS NULL OR birth_year = '' OR birth_year = '0' THEN 0
        WHEN CAST(birth_year AS UNSIGNED) <= 30 THEN 2000 + CAST(birth_year AS UNSIGNED)
        WHEN CAST(birth_year AS UNSIGNED) < 100 THEN 1900 + CAST(birth_year AS UNSIGNED)
        ELSE CAST(birth_year AS UNSIGNED)
    END DESC, id DESC
");

$profiles   = [];
while ($row = $result->fetch_assoc()) $profiles[] = $row;
$totalCount = count($profiles);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MK Brahman — शॉर्टलिस्ट केलेल्या प्रोफाइल">
    <title>MK Brahman — शॉर्टलिस्ट</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-page="shortlisted">

<?php include 'includes/sidebar.php'; ?>

<div class="container">

    <!-- PAGE HEADER -->
    <header class="page-header" role="banner">
        <a href="index.php" class="back-btn" aria-label="Back to home">←</a>
        <div style="flex:1;">
            <span class="header-title">शॉर्टलिस्ट</span>
            <span class="header-subtitle">निवडलेल्या प्रोफाइल</span>
        </div>
        <!-- Export button -->
        <?php if ($totalCount > 0): ?>
        <a href="export-shortlisted.php"
           class="back-btn"
           style="font-size:16px;background:rgba(39,174,96,.25);"
           title="CSV Export करा"
           aria-label="CSV Export">📥</a>
        <?php endif; ?>
        <button class="menu-btn" id="menuBtn" aria-label="Open menu">☰</button>
    </header>

    <!-- SEARCH -->
    <div class="search-box">
        <div class="search-input-wrap">
            <input
                type="search"
                id="searchInput"
                data-url="search.php"
                data-shortlisted="1"
                placeholder="शॉर्टलिस्टमध्ये शोधा..."
                autocomplete="off"
                aria-label="Search shortlisted profiles"
            >
            <span class="search-icon">🔍</span>
        </div>
    </div>

    <!-- SECTION TAG -->
    <div class="section-tag" id="sectionTag">
        <span>शॉर्टलिस्ट केलेल्या प्रोफाइल</span>
        <span class="count"><?= $totalCount ?></span>
    </div>

    <!-- SPINNER -->
    <div class="spinner" id="spinner"></div>

    <!-- DATA TABLE -->
    <div class="table-wrapper">
        <table class="compact-table" aria-label="Shortlisted profiles">
            <thead>
                <tr>
                    <th class="col-name sortable-th" data-sort="name" role="button" tabindex="0" title="नावानुसार क्रमवारी लावा">
                        <div class="th-content"><span>नाव. जात</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="sortable-th th-sorted" data-sort="birth_year" role="button" tabindex="0" title="जन्म वर्षानुसार क्रमवारी लावा">
                        <div class="th-content"><span>जन्म वर्ष</span><span class="th-sort-icon">▼</span></div>
                    </th>
                    <th class="sortable-th" data-sort="rashi" role="button" tabindex="0" title="राशीनुसार क्रमवारी लावा">
                        <div class="th-content"><span>राशी</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="sortable-th" data-sort="nadi" role="button" tabindex="0" title="नाडीनुसार क्रमवारी लावा">
                        <div class="th-content"><span>नाडी</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="sortable-th" data-sort="varn" role="button" tabindex="0" title="वर्णानुसार क्रमवारी लावा">
                        <div class="th-content"><span>वर्ण</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="col-secondary sortable-th" data-sort="height" role="button" tabindex="0" title="उंचीनुसार क्रमवारी लावा">
                        <div class="th-content"><span>उंची/वजन</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="col-secondary sortable-th" data-sort="salary" role="button" tabindex="0" title="पगारानुसार क्रमवारी लावा">
                        <div class="th-content"><span>पगार</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="col-secondary sortable-th" data-sort="city" role="button" tabindex="0" title="शहरानुसार क्रमवारी लावा">
                        <div class="th-content"><span>शहर</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="col-secondary sortable-th" data-sort="education" role="button" tabindex="0" title="शिक्षणानुसार क्रमवारी लावा">
                        <div class="th-content"><span>शिक्षण</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="col-secondary sortable-th" data-sort="aahar" role="button" tabindex="0" title="आहारानुसार क्रमवारी लावा">
                        <div class="th-content"><span>आहार</span><span class="th-sort-icon">↕</span></div>
                    </th>
                    <th class="sortable-th" data-sort="shortlisted" role="button" tabindex="0" title="शॉर्टलिस्टनुसार क्रमवारी लावा">
                        <div class="th-content"><span>❤</span><span class="th-sort-icon">↕</span></div>
                    </th>
                </tr>
            </thead>
            <tbody id="results">
                <?php if (empty($profiles)): ?>
                <tr>
                    <td colspan="11">
                        <div class="empty-state">
                            <div class="empty-icon">💔</div>
                            <h3>शॉर्टलिस्ट रिकामी आहे</h3>
                            <p>मुख्य पानावर जाऊन प्रोफाइल शॉर्टलिस्ट करा</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($profiles as $row): ?>
                <tr class="data-row profile-row"
                    data-id="<?= (int)$row['id'] ?>"
                    onclick="openProfile(<?= (int)$row['id'] ?>, 'shortlisted.php')"
                    style="cursor:pointer;"
                    role="button"
                    tabindex="0"
                    aria-label="<?= htmlspecialchars($row['name']) ?> ची प्रोफाइल पहा"
                    onkeydown="if(event.key==='Enter')openProfile(<?= (int)$row['id'] ?>, 'shortlisted.php')">
                    <td class="col-name">
                        <?php if ((int)$row['gender'] === 1): ?>
                            <span class="gender-m">मुलगा</span>
                        <?php else: ?>
                            <span class="gender-f">मुलगी</span>
                        <?php endif; ?>
                        <span class="profile-name-text"><?= htmlspecialchars(fmtNameJaat($row['name'], $row['jaat'] ?? '')) ?></span>
                    </td>
                    <td><?= htmlspecialchars(fmtBirthRegYear($row['birth_year'], $row['registration_year'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($row['rashi'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['nadi'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(fmtVarn($row['varn'] ?? '', (int)($row['chashma'] ?? 0))) ?></td>
                    <td class="col-secondary"><?= htmlspecialchars(fmtHeightWeight((int)$row['height_ft'], (int)$row['height_in'], $row['weight'] ?? 0)) ?></td>
                    <td class="col-secondary"><?= fmtSalaryShort((int)$row['salary']) ?></td>
                    <td class="col-secondary"><?= htmlspecialchars($row['city']) ?></td>
                    <td class="col-secondary"><?= htmlspecialchars($row['education']) ?></td>
                    <td class="col-secondary"><?= htmlspecialchars($row['aahar'] ?? '—') ?></td>
                    <td class="col-heart" onclick="event.stopPropagation()">
                        <button class="shortlist-btn"
                                data-id="<?= (int)$row['id'] ?>"
                                title="शॉर्टलिस्टमधून काढा">
                            ❤️
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
