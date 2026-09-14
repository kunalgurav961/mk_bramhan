<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/format-helpers.php';
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

// Search & Sort
$search = trim($_GET['search'] ?? '');
$like   = "%$search%";

$allowedSortCols = [
    'id', 'name', 'birth_year', 'city', 'registration_no',
    'salary', 'height', 'weight', 'education', 'gender', 'shortlisted', 'jaat'
];
$sortBy  = in_array($_GET['sort_by'] ?? '', $allowedSortCols, true) ? $_GET['sort_by'] : 'id';
$sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

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
        if ($sortDir === 'ASC') {
            $orderSQL = "ORDER BY (birth_year = '' OR birth_year IS NULL OR birth_year = '0'), CAST(birth_year AS UNSIGNED) ASC, id DESC";
        } else {
            $orderSQL = "ORDER BY CAST(birth_year AS UNSIGNED) DESC, id DESC";
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
        $orderSQL = "ORDER BY (registration_no = '' OR registration_no IS NULL), LENGTH(registration_no) {$sortDir}, registration_no {$sortDir}, id {$sortDir}";
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
    case 'id':
    default:
        $orderSQL = "ORDER BY id {$sortDir}";
        break;
}

$stmt = $conn->prepare("
    SELECT id, gender, birth_year, name, gotra, height_ft, height_in,
           salary, weight, education, city, registration_no, registration_year, shortlisted, jaat
    FROM   profiles
    WHERE  (name LIKE ? OR city LIKE ? OR gotra LIKE ? OR registration_no LIKE ?)
      AND  status != 'Inactive'
    {$orderSQL}
    LIMIT  200
");
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result   = $stmt->get_result();
$profiles = [];
while ($row = $result->fetch_assoc()) $profiles[] = $row;

function adminSortUrl($col, $currentSort, $currentDir, $search) {
    $dir = ($currentSort === $col && $currentDir === 'DESC') ? 'ASC' : 'DESC';
    $params = ['sort_by' => $col, 'sort_dir' => $dir];
    if ($search !== '') $params['search'] = $search;
    return 'admin-dashboard.php?' . http_build_query($params);
}

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
        <div class="mt-3">
            <a href="sheets-sync.php"
               class="flex items-center justify-center gap-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold py-3 rounded-xl transition-colors">
                📊 Google Sheets Sync
            </a>
        </div>
    </div>

    <!-- SEARCH & SORT -->
    <div class="px-4 pb-3 search-box">
        <form method="GET" class="space-y-2">
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
            <div class="flex items-center gap-2">
                <select name="sort_by" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50 text-gray-700 flex-1">
                    <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>नोंद क्र. (Newest)</option>
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>नाव (A-Z)</option>
                    <option value="birth_year" <?= $sortBy === 'birth_year' ? 'selected' : '' ?>>जन्म वर्ष (Age)</option>
                    <option value="salary" <?= $sortBy === 'salary' ? 'selected' : '' ?>>पगार (Salary)</option>
                    <option value="height" <?= $sortBy === 'height' ? 'selected' : '' ?>>उंची (Height)</option>
                    <option value="weight" <?= $sortBy === 'weight' ? 'selected' : '' ?>>वजन (Weight)</option>
                    <option value="education" <?= $sortBy === 'education' ? 'selected' : '' ?>>शिक्षण (Education)</option>
                    <option value="city" <?= $sortBy === 'city' ? 'selected' : '' ?>>शहर (City)</option>
                    <option value="registration_no" <?= $sortBy === 'registration_no' ? 'selected' : '' ?>>रजिस्टर क्र.</option>
                </select>
                <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sortDir) ?>">
                <button type="button" onclick="this.form.sort_dir.value = (this.form.sort_dir.value === 'ASC' ? 'DESC' : 'ASC'); this.form.submit();"
                        class="text-xs px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-bold text-gray-700 border border-gray-200"
                        title="Toggle Sort Direction">
                    <?= $sortDir === 'ASC' ? '↑ A-Z' : '↓ Z-A' ?>
                </button>
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
                    <th class="cursor-pointer hover:bg-[#252542]">
                        <a href="<?= adminSortUrl('gender', $sortBy, $sortDir, $search) ?>" class="flex items-center justify-between gap-1 text-white no-underline">
                            <span>M/F</span>
                            <span class="text-[9px] text-amber-300"><?= $sortBy === 'gender' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span>
                        </a>
                    </th>
                    <th class="cursor-pointer hover:bg-[#252542]">
                        <a href="<?= adminSortUrl('name', $sortBy, $sortDir, $search) ?>" class="flex items-center justify-between gap-1 text-white no-underline">
                            <span>नाव. जात</span>
                            <span class="text-[9px] text-amber-300"><?= $sortBy === 'name' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span>
                        </a>
                    </th>
                    <th class="cursor-pointer hover:bg-[#252542]">
                        <a href="<?= adminSortUrl('height', $sortBy, $sortDir, $search) ?>" class="flex items-center justify-between gap-1 text-white no-underline">
                            <span>उंची / वजन</span>
                            <span class="text-[9px] text-amber-300"><?= $sortBy === 'height' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span>
                        </a>
                    </th>
                    <th class="cursor-pointer hover:bg-[#252542]">
                        <a href="<?= adminSortUrl('salary', $sortBy, $sortDir, $search) ?>" class="flex items-center justify-between gap-1 text-white no-underline">
                            <span>पगार</span>
                            <span class="text-[9px] text-amber-300"><?= $sortBy === 'salary' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span>
                        </a>
                    </th>
                    <th class="cursor-pointer hover:bg-[#252542]">
                        <a href="<?= adminSortUrl('city', $sortBy, $sortDir, $search) ?>" class="flex items-center justify-between gap-1 text-white no-underline">
                            <span>शहर</span>
                            <span class="text-[9px] text-amber-300"><?= $sortBy === 'city' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span>
                        </a>
                    </th>
                    <th class="cursor-pointer hover:bg-[#252542]">
                        <a href="<?= adminSortUrl('birth_year', $sortBy, $sortDir, $search) ?>" class="flex items-center justify-between gap-1 text-white no-underline">
                            <span>नोंदणी क्र.</span>
                            <span class="text-[9px] text-amber-300"><?= $sortBy === 'birth_year' ? ($sortDir === 'ASC' ? '▲' : '▼') : '↕' ?></span>
                        </a>
                    </th>
                    <th>⚙</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profiles)): ?>
                <tr>
                    <td colspan="7" class="text-center py-8 text-gray-400">
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
                            <span class="gender-m">मुलगा</span>
                        <?php else: ?>
                            <span class="gender-f">मुलगी</span>
                        <?php endif; ?>
                    </td>
                    <td class="max-w-[70px] overflow-hidden text-ellipsis"><?= htmlspecialchars(fmtNameJaat($row['name'], $row['jaat'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(fmtHeightWeight((int)$row['height_ft'], (int)$row['height_in'], $row['weight'] ?? 0)) ?></td>
                    <td><?= fmtSalaryShort((int)$row['salary']) ?></td>
                    <td><?= htmlspecialchars($row['city']) ?></td>
                    <td><?= htmlspecialchars(fmtNondaniKramank($row['registration_year'] ?? '', $row['registration_no'])) ?></td>
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
