<?php
session_start();
require_once 'includes/db.php';
$conn = getDB();

$result = $conn->query("
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, education, city, registration_no, shortlisted,
           COALESCE(profile_image, profile_photo) AS img_file
    FROM   profiles
    WHERE  shortlisted = 1
    ORDER  BY id DESC
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
                    <td colspan="10">
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
                    <td>
                        <?php if ((int)$row['gender'] === 1): ?>
                            <span class="gender-m">1</span>
                        <?php else: ?>
                            <span class="gender-f">0</span>
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
