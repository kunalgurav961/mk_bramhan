<?php
/**
 * MK Brahman — CSV Export (Shortlisted Profiles)
 * export-shortlisted.php
 *
 * Downloads a UTF-8 CSV (with BOM for Excel compatibility) of all shortlisted profiles.
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/format-helpers.php';

$conn = getDB();

// Optional: restrict to admin only — remove comment to enforce
// if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$stmt = $conn->prepare("
    SELECT registration_no, name, gender, birth_year,
           gotra, height_ft, height_in, salary, weight,
           education, occupation, city
    FROM   profiles
    WHERE  shortlisted = 1
    ORDER  BY id DESC
");
$stmt->execute();
$result = $stmt->get_result();

// ── Stream response as CSV download ──────────────────────────────────────────
$date     = date('Y-m-d');
$filename = "mk_brahman_shortlisted_{$date}.csv";

header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// UTF-8 BOM — required for Excel to render Devanagari correctly
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'नोंदणी क्र.',
    'नाव',
    'लिंग',
    'जन्म वर्ष',
    'गोत्र',
    'उंची',
    'वजन (kg)',
    'पगार (लाख)',
    'शिक्षण',
    'व्यवसाय',
    'शहर',
]);

// Data rows
while ($row = $result->fetch_assoc()) {
    $gender   = ((int)$row['gender'] === 1) ? '1' : '0';
    $fullYear = resolveFullYear($row['birth_year']);
    $height   = (int)$row['height_ft'] . "'" . (int)$row['height_in'] . '"';
    $salary   = $row['salary'] ? $row['salary'] . ' लाख' : '—';
    $weight   = (int)($row['weight'] ?? 0) > 0 ? (int)$row['weight'] : '—';

    fputcsv($out, [
        $row['registration_no'],
        $row['name'],
        $gender,
        $fullYear,
        $row['gotra'],
        $height,
        $weight,
        $salary,
        $row['education'] ?: '—',
        $row['occupation'] ?: '—',
        $row['city'],
    ]);
}

fclose($out);
exit;
