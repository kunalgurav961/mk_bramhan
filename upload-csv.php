<?php
/**
 * MK Brahman — CSV Import (Admin Only)
 * upload-csv.php
 *
 * Features:
 *  - Upload & validate CSV
 *  - Preview before import
 *  - Duplicate registration_no detection
 *  - Batch insert with individual row error tracking
 *  - UTF-8 / Marathi support
 */
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
$conn = getDB();

// ── Expected columns ──────────────────────────────────────────────────────────
const REQUIRED_COLS = ['registration_no', 'gender', 'birth_year', 'name', 'gotra', 'city'];
const ALL_COLS      = [
    'registration_no', 'gender', 'birth_year', 'name', 'gotra',
    'height_ft', 'height_in', 'salary', 'education', 'occupation', 'city',
    'father_name', 'mother_name', 'family_details', 'about_me',
];

$phase    = 'upload';   // upload | preview | result
$errors   = [];
$rows     = [];
$imported = 0;
$skipped  = 0;
$rowErrors= [];

// ── PHASE 1: Handle upload ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'फाइल upload करताना error आला.';
    } elseif ($file['size'] > 20 * 1024 * 1024) {
        $errors[] = 'CSV फाइल 20MB पेक्षा मोठी आहे.';
    } else {
        // Read & validate CSV
        $handle = fopen($file['tmp_name'], 'r');

        // Strip UTF-8 BOM if present
        $firstBytes = fread($handle, 3);
        if ($firstBytes !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $header = fgetcsv($handle, 0, ',');
        if (!$header) {
            $errors[] = 'CSV फाइल रिकामी आहे किंवा header नाही.';
        } else {
            // Normalize header
            $header = array_map(fn($h) => trim(mb_strtolower($h, 'UTF-8')), $header);

            // Check required columns
            $missing = array_diff(REQUIRED_COLS, $header);
            if (!empty($missing)) {
                $errors[] = 'CSV मध्ये खालील columns नाहीत: ' . implode(', ', $missing);
            }
        }

        if (empty($errors) && $header) {
            $lineNo = 1;
            while (($line = fgetcsv($handle, 0, ',')) !== false) {
                $lineNo++;
                if (empty(array_filter($line))) continue; // Skip blank lines

                $row = [];
                foreach ($header as $i => $col) {
                    $row[$col] = isset($line[$i]) ? trim($line[$i]) : '';
                }

                // Per-row validation
                $rowErr = [];
                if (empty($row['registration_no'])) $rowErr[] = 'नोंदणी क्र. आवश्यक';
                if (!in_array((int)($row['gender'] ?? ''), [1, 2])) $rowErr[] = 'लिंग 1 किंवा 2 असावे';
                if (empty($row['birth_year'])) $rowErr[] = 'जन्म वर्ष आवश्यक';
                if (empty($row['name']))  $rowErr[] = 'नाव आवश्यक';
                if (empty($row['gotra'])) $rowErr[] = 'गोत्र आवश्यक';
                if (empty($row['city']))  $rowErr[] = 'शहर आवश्यक';

                $rows[] = [
                    'line'   => $lineNo,
                    'data'   => $row,
                    'errors' => $rowErr,
                ];
            }
            fclose($handle);
            $phase = 'preview';
        }
    }
}

// ── PHASE 2: Actual import ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_import'])) {
    $jsonRows = $_POST['csv_rows'] ?? '[]';
    $allRows  = json_decode($jsonRows, true);

    if (!is_array($allRows)) {
        $errors[] = 'Import data corrupt. कृपया पुन्हा upload करा.';
    } else {
        // Fetch all existing reg numbers for dupe check
        $existingNos = [];
        $res = $conn->query("SELECT registration_no FROM profiles");
        while ($r = $res->fetch_assoc()) {
            $existingNos[$r['registration_no']] = true;
        }

        $stmt = $conn->prepare("
            INSERT INTO profiles
                (registration_no, gender, birth_year, name, gotra,
                 height_ft, height_in, salary, education, occupation, city,
                 father_name, mother_name, family_details, about_me)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $conn->begin_transaction();

        foreach ($allRows as $entry) {
            $row = $entry['data'] ?? [];
            $lineNo = $entry['line'] ?? '?';

            // Skip rows with validation errors
            if (!empty($entry['errors'])) {
                $skipped++;
                $rowErrors[] = "Line {$lineNo}: " . implode(', ', $entry['errors']);
                continue;
            }

            $regNo = trim($row['registration_no'] ?? '');

            // Duplicate check
            if (isset($existingNos[$regNo])) {
                $skipped++;
                $rowErrors[] = "Line {$lineNo}: नोंदणी क्र. '{$regNo}' आधीच database मध्ये आहे.";
                continue;
            }

            $gender      = (int)($row['gender'] ?? 1);
            $birthYear   = trim($row['birth_year'] ?? '');
            $name        = trim($row['name'] ?? '');
            $gotra       = trim($row['gotra'] ?? '');
            $heightFt    = (int)($row['height_ft'] ?? 5);
            $heightIn    = (int)($row['height_in'] ?? 0);
            $salary      = (int)($row['salary'] ?? 0);
            $education   = trim($row['education'] ?? '');
            $occupation  = trim($row['occupation'] ?? '');
            $city        = trim($row['city'] ?? '');
            $fatherName  = trim($row['father_name'] ?? '');
            $motherName  = trim($row['mother_name'] ?? '');
            $familyDet   = trim($row['family_details'] ?? '');
            $aboutMe     = trim($row['about_me'] ?? '');

            $stmt->bind_param('sisssiiisssssss',
                $regNo, $gender, $birthYear, $name, $gotra,
                $heightFt, $heightIn, $salary, $education, $occupation, $city,
                $fatherName, $motherName, $familyDet, $aboutMe
            );

            if ($stmt->execute()) {
                $imported++;
                $existingNos[$regNo] = true; // Prevent intra-batch duplicates
            } else {
                $skipped++;
                $rowErrors[] = "Line {$lineNo}: DB Error — " . htmlspecialchars($conn->error);
            }
        }

        $conn->commit();
        $phase = 'result';
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Import — MK Brahman Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .field { width:100%; border:1.5px solid #e5e7eb; border-radius:10px; padding:10px 14px; font-size:14px; background:#f9fafb; outline:none; transition:border-color .2s; }
        .field:focus { border-color:#1a1a2e; background:#fff; }
        .preview-table { width:100%; border-collapse:collapse; font-size:11px; }
        .preview-table th { background:#1a1a2e; color:#fff; padding:6px 8px; text-align:left; white-space:nowrap; }
        .preview-table td { padding:5px 8px; border-bottom:1px solid #f1f5f9; white-space:nowrap; max-width:120px; overflow:hidden; text-overflow:ellipsis; }
        .preview-table tr:hover td { background:#fafafa; }
        .row-ok   td:first-child { border-left:3px solid #27ae60; }
        .row-err  td:first-child { border-left:3px solid #c0392b; }
        .row-err  td { background:#fff5f5; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
<div class="max-w-2xl mx-auto bg-white min-h-screen shadow-lg">

    <!-- HEADER -->
    <div class="sticky top-0 z-50 bg-[#1a1a2e] text-white">
        <div class="flex items-center gap-3 px-4 py-3">
            <a href="admin-dashboard.php" class="w-8 h-8 bg-white/15 hover:bg-white/25 rounded-full flex items-center justify-center text-lg transition-colors">←</a>
            <div>
                <h1 class="font-bold text-base">📂 CSV Import</h1>
                <p class="text-xs text-gray-300">प्रोफाइल batch import करा</p>
            </div>
        </div>
    </div>

    <div class="p-4">

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm mb-4">
        <div class="font-semibold mb-1">⚠️ Error:</div>
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ────── PHASE: UPLOAD ────── -->
    <?php if ($phase === 'upload'): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm mb-5 text-blue-800">
        <strong>📋 CSV Format:</strong><br>
        <code class="text-xs">registration_no, gender (1=मुलगा / 2=मुलगी), birth_year (95/01), name, gotra, height_ft, height_in, salary, education, occupation, city, father_name, mother_name, family_details, about_me</code>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">CSV फाइल निवडा *</label>
            <input type="file" name="csv_file" accept=".csv,text/csv" class="field" required>
            <p class="text-xs text-gray-400 mt-1">UTF-8 / ANSI CSV स्वीकारले जाते. Max 20MB.</p>
        </div>
        <button type="submit" class="w-full bg-[#1a1a2e] hover:bg-[#2c2c5e] text-white font-semibold py-3 rounded-xl text-sm transition-colors">
            📤 CSV अपलोड करा व Preview पहा
        </button>
    </form>

    <!-- Download Sample -->
    <div class="mt-6 pt-4 border-t border-gray-100">
        <p class="text-xs text-gray-400 mb-2">Sample CSV download:</p>
        <a href="data:text/csv;charset=utf-8,%EF%BB%BFregistration_no%2Cgender%2Cbirth_year%2Cname%2Cgotra%2Cheight_ft%2Cheight_in%2Csalary%2Ceducation%2Coccupation%2Ccity%2Cfather_name%2Cmother_name%2Cfamily_details%2Cabout_me%0AMKB001%2C1%2C95%2C%E0%A4%85%E0%A4%AE%E0%A5%8B%E0%A4%B2%20%E0%A4%A6%E0%A5%87%E0%A4%B6%E0%A4%AA%E0%A4%BE%E0%A4%82%E0%A4%A1%E0%A5%87%2C%E0%A4%95%E0%A4%BE%E0%A4%B6%E0%A5%8D%E0%A4%AF%E0%A4%AA%2C5%2C8%2C10%2CB.E.%2CSoftware%20Eng%2C%E0%A4%AA%E0%A5%81%E0%A4%A3%E0%A5%87%2C%E0%A4%B0%E0%A4%BE%E0%A4%AE%20%E0%A4%A6%E0%A5%87%E0%A4%B6%E0%A4%AA%E0%A4%BE%E0%A4%82%E0%A4%A1%E0%A5%87%2C%E0%A4%B8%E0%A5%80%E0%A4%AE%E0%A4%BE%20%E0%A4%A6%E0%A5%87%E0%A4%B6%E0%A4%AA%E0%A4%BE%E0%A4%82%E0%A4%A1%E0%A5%87%2C%2C"
           download="sample_import.csv"
           class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-4 py-2 rounded-lg hover:bg-green-200 transition-colors">
            ⬇️ Sample CSV Download
        </a>
    </div>
    <?php endif; ?>


    <!-- ────── PHASE: PREVIEW ────── -->
    <?php if ($phase === 'preview' && !empty($rows)): ?>

    <?php
    $validRows   = array_filter($rows, fn($r) => empty($r['errors']));
    $invalidRows = array_filter($rows, fn($r) => !empty($r['errors']));
    ?>

    <div class="flex gap-3 mb-4">
        <div class="flex-1 bg-green-50 border border-green-200 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-green-700"><?= count($validRows) ?></div>
            <div class="text-xs text-green-600">Import होतील</div>
        </div>
        <div class="flex-1 bg-red-50 border border-red-200 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-red-700"><?= count($invalidRows) ?></div>
            <div class="text-xs text-red-600">Error असलेल्या rows</div>
        </div>
        <div class="flex-1 bg-gray-50 border border-gray-200 rounded-xl p-3 text-center">
            <div class="text-2xl font-bold text-gray-700"><?= count($rows) ?></div>
            <div class="text-xs text-gray-500">एकूण rows</div>
        </div>
    </div>

    <!-- Preview table -->
    <div class="overflow-x-auto rounded-xl border border-gray-200 mb-4">
        <table class="preview-table">
            <thead>
                <tr>
                    <th>Line</th>
                    <th>Status</th>
                    <th>नोंदणी क्र.</th>
                    <th>लिंग</th>
                    <th>वर्ष</th>
                    <th>नाव</th>
                    <th>गोत्र</th>
                    <th>शहर</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $entry): ?>
                <tr class="<?= empty($entry['errors']) ? 'row-ok' : 'row-err' ?>">
                    <td><?= $entry['line'] ?></td>
                    <td><?= empty($entry['errors']) ? '✅' : '❌' ?></td>
                    <td><?= htmlspecialchars($entry['data']['registration_no'] ?? '') ?></td>
                    <td><?= ((int)($entry['data']['gender'] ?? 0) === 1) ? 'मुलगा' : 'मुलगी' ?></td>
                    <td><?= htmlspecialchars($entry['data']['birth_year'] ?? '') ?></td>
                    <td><?= htmlspecialchars($entry['data']['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($entry['data']['gotra'] ?? '') ?></td>
                    <td><?= htmlspecialchars($entry['data']['city'] ?? '') ?></td>
                    <td style="color:#c0392b;font-size:10px;"><?= htmlspecialchars(implode('; ', $entry['errors'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($validRows) > 0): ?>
    <form method="POST">
        <input type="hidden" name="do_import" value="1">
        <input type="hidden" name="csv_rows" value="<?= htmlspecialchars(json_encode(array_values($rows), JSON_UNESCAPED_UNICODE)) ?>">

        <button type="submit"
                onclick="return confirm('<?= count($validRows) ?> प्रोफाइल import करायच्या का?')"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3.5 rounded-xl text-sm transition-colors mb-3">
            ✅ <?= count($validRows) ?> प्रोफाइल Import करा
        </button>
    </form>
    <?php endif; ?>

    <a href="upload-csv.php" class="block text-center text-sm text-gray-500 hover:text-gray-700 py-2">
        ← वेगळी CSV upload करा
    </a>
    <?php endif; ?>


    <!-- ────── PHASE: RESULT ────── -->
    <?php if ($phase === 'result'): ?>
    <div class="text-center py-8">
        <div class="text-5xl mb-4">
            <?= $imported > 0 ? '🎉' : '⚠️' ?>
        </div>
        <h2 class="text-xl font-bold mb-2">Import <?= $imported > 0 ? 'यशस्वी!' : 'पूर्ण' ?></h2>

        <div class="flex gap-4 justify-center my-6">
            <div class="bg-green-50 border border-green-200 rounded-xl px-6 py-4 text-center">
                <div class="text-3xl font-bold text-green-700"><?= $imported ?></div>
                <div class="text-xs text-green-600 mt-1">Import केल्या</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-xl px-6 py-4 text-center">
                <div class="text-3xl font-bold text-red-700"><?= $skipped ?></div>
                <div class="text-xs text-red-600 mt-1">Skip केल्या</div>
            </div>
        </div>

        <?php if (!empty($rowErrors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-left mb-6 text-xs text-red-700 max-h-48 overflow-y-auto">
            <div class="font-semibold mb-2">Skip केलेल्या rows:</div>
            <?php foreach ($rowErrors as $re): ?>
                <div class="py-0.5">• <?= htmlspecialchars($re) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-3">
            <a href="admin-dashboard.php"
               class="flex-1 bg-[#1a1a2e] hover:bg-[#2c2c5e] text-white font-semibold py-3 rounded-xl text-sm transition-colors text-center">
                ⚙️ Dashboard
            </a>
            <a href="upload-csv.php"
               class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-xl text-sm transition-colors text-center">
                📂 आणखी Import करा
            </a>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /.p-4 -->
</div>
</body>
</html>
