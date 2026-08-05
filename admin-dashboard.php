<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
$conn = getDB();

// Stats (single query for efficiency)
$statsRes = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(shortlisted = 1) AS shortlisted,
        SUM(gender = 1) AS male,
        SUM(gender = 2) AS female
    FROM profiles
    WHERE status != 'Inactive'
")->fetch_assoc();

$totalProfiles    = (int)$statsRes['total'];
$totalShortlisted = (int)$statsRes['shortlisted'];
$totalMale        = (int)$statsRes['male'];
$totalFemale      = (int)$statsRes['female'];

// Search
$search = trim($_GET['search'] ?? '');
$like   = "%$search%";

$stmt = $conn->prepare("
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, education, city, registration_no, shortlisted
    FROM   profiles
    WHERE  (name LIKE ? OR city LIKE ? OR gotra LIKE ? OR registration_no LIKE ?)
      AND  status != 'Inactive'
    ORDER  BY id DESC
    LIMIT  200
");
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result   = $stmt->get_result();
$profiles = [];
while ($row = $result->fetch_assoc()) $profiles[] = $row;

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MK Brahman Admin Dashboard">
    <title>Admin Dashboard — MK Brahman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .compact-admin-table { font-size: 11px; border-collapse: collapse; width: 100%; }
        .compact-admin-table th { padding: 8px 6px; text-align: left; background: #1a1a2e; color: #fff; white-space: nowrap; font-size: 10px; }
        .compact-admin-table td { padding: 7px 6px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; vertical-align: middle; }
        .compact-admin-table tr.clickable-row:hover td { background: #eef2ff; cursor: pointer; }
        .compact-admin-table tr.clickable-row:active td { background: #e0e7ff; }
        .gender-m { background: #d6eaf8; color: #1a5276; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 20px; }
        .gender-f { background: #e8daef; color: #7d3c98; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 20px; }
        .search-box input:focus { outline: none; border-color: #c0392b; box-shadow: 0 0 0 3px rgba(192,57,43,.12); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

<div class="max-w-md mx-auto bg-white min-h-screen shadow-lg">

    <!-- HEADER -->
    <div class="sticky top-0 z-50 bg-[#1a1a2e] text-white">
        <div class="flex items-center justify-between px-4 py-3">
            <div>
                <h1 class="font-bold text-base">⚙️ Admin Dashboard</h1>
                <p class="text-xs text-gray-300">नमस्ते, <?= $adminName ?></p>
            </div>
            <a href="logout.php"
               onclick="return confirm('Logout करायचे का?')"
               class="bg-red-700 hover:bg-red-800 text-white text-xs px-3 py-1.5 rounded-lg font-medium transition-colors">
                Logout
            </a>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-2 gap-3 p-4">
        <div class="bg-[#1a1a2e] text-white rounded-xl p-4">
            <p class="text-xs text-gray-400">एकूण प्रोफाइल</p>
            <p class="text-3xl font-bold mt-1"><?= $totalProfiles ?></p>
        </div>
        <div class="bg-red-700 text-white rounded-xl p-4">
            <p class="text-xs text-red-200">शॉर्टलिस्ट</p>
            <p class="text-3xl font-bold mt-1"><?= $totalShortlisted ?></p>
        </div>
        <div class="bg-blue-100 text-blue-800 rounded-xl p-4">
            <p class="text-xs text-blue-500">मुलगे</p>
            <p class="text-2xl font-bold mt-1"><?= $totalMale ?></p>
        </div>
        <div class="bg-purple-100 text-purple-800 rounded-xl p-4">
            <p class="text-xs text-purple-500">मुली</p>
            <p class="text-2xl font-bold mt-1"><?= $totalFemale ?></p>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="px-4 pb-3">
        <div class="grid grid-cols-2 gap-3 mb-3">
            <a href="add-profile.php"
               class="flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
                ➕ प्रोफाइल जोडा
            </a>
            <a href="index.php"
               class="flex items-center justify-center gap-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
                👁️ मुख्य पान
            </a>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <a href="upload-csv.php"
               class="flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
                📂 CSV Import
            </a>
            <a href="export-shortlisted.php"
               class="flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
                📥 CSV Export
            </a>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="px-4 pb-3 search-box">
        <form method="GET">
            <div class="relative">
                <input
                    type="search"
                    name="search"
                    placeholder="प्रोफाइल शोधा..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 pr-10 transition-all"
                >
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</button>
            </div>
        </form>
    </div>

    <!-- PROFILES TABLE -->
    <div class="px-2 pb-6 overflow-x-auto">
        <div class="text-xs text-gray-400 px-2 py-1 mb-1">
            <?= count($profiles) ?> प्रोफाइल दाखवत आहे
            <span class="text-gray-300 ml-2">(कोणत्याही row वर tap करा — प्रोफाइल उघडेल)</span>
        </div>
        <table class="compact-admin-table">
            <thead>
                <tr>
                    <th>M/F</th>
                    <th>वर्ष</th>
                    <th>नाव</th>
                    <th>गोत्र</th>
                    <th>उंची</th>
                    <th>पगार</th>
                    <th>शहर</th>
                    <th>नोंद</th>
                    <th>⚙</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profiles)): ?>
                <tr>
                    <td colspan="9" class="text-center py-8 text-gray-400">
                        <div class="text-3xl mb-2">🔍</div>
                        कोणतीही प्रोफाइल सापडली नाही
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($profiles as $row): ?>
                <tr class="clickable-row"
                    onclick="openProfileAdmin(<?= (int)$row['id'] ?>)"
                    role="button"
                    tabindex="0"
                    aria-label="<?= htmlspecialchars($row['name']) ?> — प्रोफाइल पहा"
                    onkeydown="if(event.key==='Enter')openProfileAdmin(<?= (int)$row['id'] ?>)">
                    <td>
                        <?php if ((int)$row['gender'] === 1): ?>
                            <span class="gender-m">1</span>
                        <?php else: ?>
                            <span class="gender-f">2</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['birth_year']) ?></td>
                    <td class="max-w-[70px] overflow-hidden text-ellipsis"><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['gotra']) ?></td>
                    <td><?= (int)$row['height_ft'] ?>'<?= (int)$row['height_in'] ?>"</td>
                    <td><?= htmlspecialchars($row['salary']) ?>L</td>
                    <td><?= htmlspecialchars($row['city']) ?></td>
                    <td><?= htmlspecialchars($row['registration_no']) ?></td>
                    <td onclick="event.stopPropagation()">
                        <div class="flex gap-1">
                            <a href="edit-profile.php?id=<?= (int)$row['id'] ?>"
                               class="text-blue-600 hover:text-blue-800 text-base"
                               title="Edit">✏️</a>
                            <a href="delete-profile.php?id=<?= (int)$row['id'] ?>"
                               onclick="return confirm('\"<?= htmlspecialchars(addslashes($row['name'])) ?>\" ची प्रोफाइल कायमची हटवायची का?')"
                               class="text-red-500 hover:text-red-700 text-base"
                               title="Delete">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function openProfileAdmin(id) {
    window.location.href = 'profile.php?id=' + id + '&from=admin-dashboard.php';
}
</script>
</body>
</html>
