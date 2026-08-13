<?php
/**
 * MK Brahman — Profile Detail Page (profile.php)
 * Accessible to all users; admin sees Edit/Delete buttons.
 */
session_start();
require_once 'includes/db.php';

$conn = getDB();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Single optimized query
$stmt = $conn->prepare("
    SELECT id, registration_no, gender, birth_year, name, gotra,
           height_ft, height_in, salary, education, occupation, city,
           father_name, mother_name, family_details, about_me,
           shortlisted, created_at, status,
           mobile_no, mobile, mobile_2, mobile_3, mobile_4,
           jaat, rashi,
           sheet_img_1, sheet_img_2, sheet_img_3, sheet_img_4,
           COALESCE(profile_image, profile_photo) AS img_file
    FROM   profiles
    WHERE  id = ?
    LIMIT  1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    ?>
<!DOCTYPE html><html lang="mr"><head><meta charset="UTF-8"><title>प्रोफाइल सापडली नाही</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>body{font-family:Poppins,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8f9fa;}
    .err{text-align:center;padding:32px;}.err h1{font-size:64px;margin:0;}.err p{color:#6b7280;margin:12px 0 24px;}
    .err a{background:#c0392b;color:#fff;padding:12px 28px;border-radius:12px;text-decoration:none;font-weight:600;}</style>
    </head><body><div class="err"><h1>😔</h1><p>ही प्रोफाइल सापडली नाही किंवा हटवली गेली आहे.</p>
    <a href="index.php">← मुख्य पानावर जा</a></div></body></html>
    <?php
    exit;
}

$p = $result->fetch_assoc();

// Fetch multiple images
$imgStmt = $conn->prepare("SELECT filename FROM profile_images WHERE profile_id = ? ORDER BY sort_order, id");
$imgStmt->bind_param('i', $id);
$imgStmt->execute();
$galleryImages = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Helpers ──────────────────────────────────────────────────────────────────
$isAdmin    = isset($_SESSION['admin_id']);
$genderText = ((int)$p['gender'] === 1) ? 'मुलगा' : 'मुलगी';
$genderClass= ((int)$p['gender'] === 1) ? 'gender-m' : 'gender-f';

// Birth year: 95 → 1995, 01 → 2001
$by = (int)$p['birth_year'];
$fullYear = ($by >= 0 && $by <= 30) ? "20{$p['birth_year']}" : "19{$p['birth_year']}";

// Salary formatting
function fmtSalary(int $lakh): string {
    if ($lakh === 0) return 'उपलब्ध नाही';
    return "{$lakh} लाख/वर्ष";
}

// Build gallery list — fall back to single img_file if no rows in profile_images
if (empty($galleryImages) && !empty($p['img_file'])) {
    $galleryImages = [['filename' => $p['img_file']]];
}

// Primary image for og / header
$primaryFile = $galleryImages[0]['filename'] ?? '';
$primaryPath = __DIR__ . '/uploads/profiles/' . basename($primaryFile);
$imgSrc      = ($primaryFile && file_exists($primaryPath))
             ? 'uploads/profiles/' . htmlspecialchars(basename($primaryFile))
             : 'assets/img/default-avatar.svg';

// Back URL (ref param for proper back navigation)
$backUrl = htmlspecialchars($_GET['from'] ?? 'index.php');

// Determine referring page for back button label
$backLabel = 'मुख्य पान';
if (str_contains($backUrl, 'shortlisted'))    $backLabel = 'शॉर्टलिस्ट';
if (str_contains($backUrl, 'admin-dashboard')) $backLabel = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MK Brahman — <?= htmlspecialchars($p['name']) ?> ची प्रोफाइल">
    <title><?= htmlspecialchars($p['name']) ?> — MK Brahman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #c0392b; --brand-dark: #922b21; --brand-light: #fadbd8;
            --male: #1a5276; --male-bg: #d6eaf8;
            --female: #7d3c98; --female-bg: #e8daef;
            --text: #1a1a2e; --muted: #6b7280;
            --border: #e5e7eb; --bg: #f8f9fa; --card: #fff;
            --header: #1a1a2e; --shadow: 0 4px 20px rgba(0,0,0,.12);
            --radius: 14px;
        }
        body {
            font-family: 'Poppins', 'Noto Sans Devanagari', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; font-family: inherit; }

        /* ── Layout ── */
        .page-wrap {
            max-width: 480px;
            margin: 0 auto;
            background: var(--card);
            min-height: 100vh;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        .ph-header {
            background: var(--header);
            color: #fff;
            padding: 0 14px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.3);
        }
        .ph-header-left { display: flex; align-items: center; gap: 10px; }
        .back-btn {
            width: 34px; height: 34px;
            background: rgba(255,255,255,.15);
            border: none; border-radius: 50%;
            color: #fff; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
            flex-shrink: 0;
        }
        .back-btn:hover { background: rgba(255,255,255,.28); }
        .ph-title { font-size: 15px; font-weight: 600; }
        .ph-sub   { font-size: 10px; color: rgba(255,255,255,.65); display: block; }

        /* Admin action buttons in header */
        .hdr-actions { display: flex; gap: 8px; }
        .hdr-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            transition: background .2s;
        }
        .hdr-btn--edit   { background: #2980b9; color: #fff; }
        .hdr-btn--edit:hover { background: #1a6596; }
        .hdr-btn--delete { background: var(--brand); color: #fff; }
        .hdr-btn--delete:hover { background: var(--brand-dark); }

        /* ── Profile Image / Gallery ── */
        .profile-img-wrap {
            position: relative;
            background: #1a1a2e;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        /* Wide rectangle gallery */
        .gallery-wrap {
            position: relative;
            width: 100%;
            height: 280px;
            overflow: hidden;
            background: #0d0d1a;
        }
        .gallery-slide {
            position: absolute; inset: 0;
            object-fit: cover;
            width: 100%; height: 100%;
            opacity: 0;
            transition: opacity .6s ease;
        }
        .gallery-slide.active { opacity: 1; }
        /* Gradient overlay at bottom for name readability */
        .gallery-wrap::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 50%;
            background: linear-gradient(to top, rgba(0,0,0,.65) 0%, transparent 100%);
            pointer-events: none;
        }
        .gallery-dots {
            display: flex; gap: 6px;
            position: absolute;
            bottom: 10px; left: 50%;
            transform: translateX(-50%);
            z-index: 10;
        }
        .gallery-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: rgba(255,255,255,.45);
            cursor: pointer; transition: background .2s, transform .2s;
            border: none;
        }
        .gallery-dot.active { background: #fff; transform: scale(1.3); }
        /* Single image — wide rectangle */
        .profile-img-rect {
            width: 100%;
            height: 280px;
            object-fit: cover;
            display: block;
            background: #0d0d1a;
        }
        .profile-name-block {
            text-align: center;
            padding: 14px 20px 20px;
            color: #fff;
        }
        .profile-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: .3px;
        }
        .profile-regno {
            font-size: 11px;
            color: rgba(255,255,255,.6);
            margin-top: 2px;
        }
        .gender-badge {
            display: inline-block;
            margin-top: 6px;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
        }
        .gender-m { background: var(--male-bg); color: var(--male); }
        .gender-f { background: var(--female-bg); color: var(--female); }

        /* ── Shortlist heart on profile page ── */
        .heart-section {
            background: rgba(255,255,255,.1);
            border-radius: 20px;
            padding: 6px 16px;
            margin-top: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .heart-section .shortlist-btn {
            background: none; border: none;
            font-size: 22px; cursor: pointer;
            transition: transform .15s;
        }
        .heart-section .shortlist-btn:active { transform: scale(1.3); }
        .heart-section .shortlist-btn.loading { opacity: .5; pointer-events: none; }
        .heart-section span { font-size: 12px; color: rgba(255,255,255,.75); }

        /* ── Info Card ── */
        .info-body { padding: 20px 16px 32px; }

        .info-section {
            background: var(--bg);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 14px;
            border: 1px solid var(--border);
        }
        .info-section-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid var(--brand-light);
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            min-width: 110px;
            flex-shrink: 0;
            padding-top: 1px;
        }
        .info-value {
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            flex: 1;
            word-break: break-word;
        }
        .info-value.empty { color: var(--muted); font-style: italic; font-weight: 400; }

        /* ── Admin actions at bottom ── */
        .admin-actions {
            padding: 0 16px 28px;
            display: flex; gap: 10px;
        }
        .act-btn {
            flex: 1;
            padding: 14px;
            border: none; border-radius: var(--radius);
            font-size: 14px; font-weight: 600;
            transition: background .2s, transform .1s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .act-btn:active { transform: scale(.97); }
        .act-btn--edit   { background: #2980b9; color: #fff; }
        .act-btn--edit:hover { background: #1a6596; }
        .act-btn--delete { background: var(--brand); color: #fff; }
        .act-btn--delete:hover { background: var(--brand-dark); }

        /* ── Export shortlist action (non-admin) ── */
        .export-row {
            padding: 0 16px 28px;
        }
        .export-btn {
            width: 100%;
            padding: 14px;
            border: none; border-radius: var(--radius);
            font-size: 14px; font-weight: 600;
            background: #27ae60; color: #fff;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            transition: background .2s;
        }
        .export-btn:hover { background: #1e8449; }

        /* ── Expand button on image ── */
        .expand-btn {
            position: absolute;
            top: 10px; right: 10px;
            z-index: 20;
            width: 36px; height: 36px;
            background: rgba(0,0,0,.55);
            border: 1.5px solid rgba(255,255,255,.35);
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .15s;
            backdrop-filter: blur(4px);
        }
        .expand-btn:hover { background: rgba(0,0,0,.8); transform: scale(1.08); }

        /* ── Lightbox overlay ── */
        .lb-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.96);
            z-index: 9999;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity .3s ease;
        }
        .lb-overlay.open { opacity: 1; pointer-events: all; }

        /* Close & counter bar */
        .lb-topbar {
            position: absolute; top: 0; left: 0; right: 0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px;
            background: linear-gradient(to bottom, rgba(0,0,0,.7) 0%, transparent 100%);
            z-index: 2;
        }
        .lb-counter { color: rgba(255,255,255,.8); font-size: 13px; font-family: 'Poppins',sans-serif; }
        .lb-close {
            width: 38px; height: 38px;
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.3);
            border-radius: 50%;
            color: #fff; font-size: 20px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
        }
        .lb-close:hover { background: rgba(255,255,255,.3); }

        /* Main image area */
        .lb-img-wrap {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .lb-img {
            max-width: 100%; max-height: 100%;
            object-fit: contain;
            border-radius: 4px;
            transition: transform .25s ease;
            transform-origin: center;
            user-select: none; -webkit-user-select: none;
        }

        /* Prev / Next arrows */
        .lb-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            z-index: 5;
            width: 42px; height: 42px;
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.25);
            border-radius: 50%; color: #fff; font-size: 20px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
            backdrop-filter: blur(4px);
        }
        .lb-arrow:hover { background: rgba(255,255,255,.3); }
        .lb-arrow.prev { left: 10px; }
        .lb-arrow.next { right: 10px; }

        /* Thumbnail strip at bottom */
        .lb-thumbs {
            position: absolute; bottom: 0; left: 0; right: 0;
            display: flex; gap: 6px;
            justify-content: center;
            padding: 12px 16px;
            background: linear-gradient(to top, rgba(0,0,0,.75) 0%, transparent 100%);
            overflow-x: auto;
            z-index: 2;
        }
        .lb-thumb {
            width: 48px; height: 48px; flex-shrink: 0;
            border-radius: 6px;
            object-fit: cover;
            border: 2px solid transparent;
            opacity: .55;
            cursor: pointer;
            transition: opacity .2s, border-color .2s;
        }
        .lb-thumb.active { border-color: #fff; opacity: 1; }

        /* ── Toast ── */
        .toast {
            position: fixed; bottom: 24px; left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: var(--header); color: #fff;
            padding: 10px 22px; border-radius: 20px;
            font-size: 13px; z-index: 10000;
            transition: transform .3s cubic-bezier(.4,0,.2,1);
            box-shadow: var(--shadow); white-space: nowrap;
        }
        .toast.show { transform: translateX(-50%) translateY(0); }

        @media (min-width: 480px) {
            .page-wrap { box-shadow: 0 0 40px rgba(0,0,0,.15); }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- HEADER -->
    <div class="ph-header">
        <div class="ph-header-left">
            <a href="<?= $backUrl ?>" class="back-btn" aria-label="<?= $backLabel ?> वर जा">←</a>
            <div>
                <span class="ph-title">प्रोफाइल</span>
                <span class="ph-sub"><?= htmlspecialchars($p['registration_no']) ?></span>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="hdr-actions">
            <a href="edit-profile.php?id=<?= $id ?>" class="hdr-btn hdr-btn--edit">✏️ Edit</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROFILE IMAGE + NAME BLOCK -->
    <div class="profile-img-wrap">

        <?php
        // Build JS-safe image list for lightbox
        $lbImages = [];
        foreach ($galleryImages as $gi => $gimg) {
            $gpath = __DIR__ . '/uploads/profiles/' . basename($gimg['filename']);
            if (file_exists($gpath)) {
                $lbImages[] = 'uploads/profiles/' . basename($gimg['filename']);
            }
        }
        if (empty($lbImages)) $lbImages[] = $imgSrc;
        ?>
        <!-- Safe: pass images to JS via script tag, NOT in onclick attr (avoids quote conflict) -->
        <script>window.LB_IMAGES = <?= json_encode($lbImages, JSON_UNESCAPED_SLASHES) ?>; window.LB_CUR = 0;</script>

        <?php if (count($galleryImages) > 1): ?>
        <!-- Multi-image slideshow: wide rectangle -->
        <div class="gallery-wrap" id="galleryWrap" style="position:relative;cursor:pointer;"
             onclick="openLightbox(window.LB_IMAGES, window.LB_CUR||0)">
            <?php foreach ($galleryImages as $gi => $gimg):
                $gsrc  = 'uploads/profiles/' . htmlspecialchars(basename($gimg['filename']));
                $gpath = __DIR__ . '/uploads/profiles/' . basename($gimg['filename']);
                if (!file_exists($gpath)) continue;
            ?>
            <img src="<?= $gsrc ?>" alt="Photo <?= $gi+1 ?>"
                 class="gallery-slide <?= $gi===0?'active':'' ?>"
                 loading="lazy">
            <?php endforeach; ?>
            <!-- dots inside the wrap, over gradient -->
            <div class="gallery-dots" id="galleryDots" onclick="event.stopPropagation()">
                <?php foreach ($galleryImages as $gi => $gimg): ?>
                <button class="gallery-dot <?= $gi===0?'active':'' ?>" data-idx="<?= $gi ?>"></button>
                <?php endforeach; ?>
            </div>
            <!-- Expand button -->
            <button class="expand-btn"
                    onclick="event.stopPropagation();openLightbox(window.LB_IMAGES, window.LB_CUR||0)"
                    title="फुलस्क्रीन पहा">⛶</button>
        </div>
        <?php else: ?>
        <!-- Single image: wide rectangle -->
        <div style="position:relative;width:100%;cursor:pointer;"
             onclick="openLightbox(window.LB_IMAGES, 0)">
            <img src="<?= $imgSrc ?>"
                 alt="<?= htmlspecialchars($p['name']) ?>"
                 class="profile-img-rect"
                 onerror="this.src='assets/img/default-avatar.svg'"
                 loading="lazy">
            <button class="expand-btn"
                    onclick="event.stopPropagation();openLightbox(window.LB_IMAGES, 0)"
                    title="फुलस्क्रीन पहा">⛶</button>
        </div>
        <?php endif; ?>

        <!-- Name block below the wide image -->
        <div class="profile-name-block" style="width:100%;background:var(--header);padding:14px 20px 18px;">
            <div class="profile-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="profile-regno">नोंदणी क्र. <?= htmlspecialchars($p['registration_no']) ?></div>
            <span class="gender-badge <?= $genderClass ?>"><?= $genderText ?></span>

            <!-- Shortlist toggle on profile page -->
            <div class="heart-section">
                <button class="shortlist-btn"
                        id="profileHeartBtn"
                        data-id="<?= $id ?>"
                        title="<?= $p['shortlisted'] ? 'शॉर्टलिस्टमधून काढा' : 'शॉर्टलिस्टला जोडा' ?>">
                    <?= $p['shortlisted'] ? '❤️' : '🤍' ?>
                </button>
                <span id="heartLabel"><?= $p['shortlisted'] ? 'शॉर्टलिस्टमध्ये आहे' : 'शॉर्टलिस्टला जोडा' ?></span>
            </div>
        </div>
    </div>

    <!-- INFO BODY -->
    <div class="info-body">

        <!-- Personal Info -->
        <div class="info-section">
            <div class="info-section-title">📋 वैयक्तिक माहिती</div>

            <div class="info-row">
                <span class="info-label">जन्म वर्ष</span>
                <span class="info-value"><?= htmlspecialchars($fullYear) ?> (<?= htmlspecialchars($p['birth_year']) ?>)</span>
            </div>
            <div class="info-row">
                <span class="info-label">गोत्र</span>
                <span class="info-value"><?= htmlspecialchars($p['gotra'] ?: '—') ?></span>
            </div>
            <?php if (!empty($p['jaat'])): ?>
            <div class="info-row">
                <span class="info-label">जात</span>
                <span class="info-value"><?= htmlspecialchars($p['jaat']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($p['rashi'])): ?>
            <div class="info-row">
                <span class="info-label">रास</span>
                <span class="info-value"><?= htmlspecialchars($p['rashi']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">उंची</span>
                <span class="info-value"><?= (int)$p['height_ft'] ?>'<?= (int)$p['height_in'] ?>"</span>
            </div>
            <div class="info-row">
                <span class="info-label">शहर</span>
                <span class="info-value"><?= htmlspecialchars($p['city'] ?: '—') ?></span>
            </div>
            <?php
                // Resolve mobile: prefer mobile_no, fallback to legacy mobile column
                $displayMobile = trim($p['mobile_no'] ?? '') ?: trim($p['mobile'] ?? '');
                $extraMobiles  = array_filter([
                    trim($p['mobile_2'] ?? ''),
                    trim($p['mobile_3'] ?? ''),
                    trim($p['mobile_4'] ?? ''),
                ]);
            ?>
            <?php if ($displayMobile): ?>
            <div class="info-row">
                <span class="info-label">📱 मोबाईल</span>
                <span class="info-value">
                    <a href="tel:<?= htmlspecialchars($displayMobile) ?>"
                       style="color:var(--brand);font-weight:600;text-decoration:none;">
                        <?= htmlspecialchars($displayMobile) ?>
                    </a>
                </span>
            </div>
            <?php endif; ?>
            <?php foreach ($extraMobiles as $mob): ?>
            <div class="info-row">
                <span class="info-label">📱 मोबाईल</span>
                <span class="info-value">
                    <a href="tel:<?= htmlspecialchars($mob) ?>"
                       style="color:var(--brand);font-weight:600;text-decoration:none;">
                        <?= htmlspecialchars($mob) ?>
                    </a>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Professional Info -->
        <div class="info-section">
            <div class="info-section-title">💼 व्यावसायिक माहिती</div>

            <div class="info-row">
                <span class="info-label">शिक्षण</span>
                <span class="info-value <?= !$p['education'] ? 'empty' : '' ?>">
                    <?= htmlspecialchars($p['education'] ?: 'उपलब्ध नाही') ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">व्यवसाय</span>
                <span class="info-value <?= !$p['occupation'] ? 'empty' : '' ?>">
                    <?= htmlspecialchars($p['occupation'] ?: 'उपलब्ध नाही') ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">पगार</span>
                <span class="info-value"><?= fmtSalary((int)$p['salary']) ?></span>
            </div>
        </div>

        <!-- Family Info -->
        <?php if ($p['father_name'] || $p['mother_name'] || $p['family_details']): ?>
        <div class="info-section">
            <div class="info-section-title">👨‍👩‍👧 कौटुंबिक माहिती</div>

            <?php if ($p['father_name']): ?>
            <div class="info-row">
                <span class="info-label">वडिलांचे नाव</span>
                <span class="info-value"><?= htmlspecialchars($p['father_name']) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($p['mother_name']): ?>
            <div class="info-row">
                <span class="info-label">आईचे नाव</span>
                <span class="info-value"><?= htmlspecialchars($p['mother_name']) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($p['family_details']): ?>
            <div class="info-row">
                <span class="info-label">कुटुंब</span>
                <span class="info-value" style="white-space:pre-wrap;"><?= htmlspecialchars($p['family_details']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- About Me -->
        <?php if ($p['about_me']): ?>
        <div class="info-section">
            <div class="info-section-title">💬 माझ्याबद्दल</div>
            <p style="font-size:13px;line-height:1.7;color:var(--text);white-space:pre-wrap;"><?= htmlspecialchars($p['about_me']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Created Date -->
        <div class="info-section">
            <div class="info-section-title">📅 नोंदणी माहिती</div>
            <div class="info-row">
                <span class="info-label">नोंदणी दिनांक</span>
                <span class="info-value">
                    <?= date('d M Y', strtotime($p['created_at'])) ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">नोंदणी क्रमांक</span>
                <span class="info-value"><?= htmlspecialchars($p['registration_no']) ?></span>
            </div>
        </div>

    </div><!-- /.info-body -->

    <!-- ADMIN ACTIONS -->
    <?php if ($isAdmin): ?>
    <div class="admin-actions">
        <a href="edit-profile.php?id=<?= $id ?>" class="act-btn act-btn--edit">
            ✏️ प्रोफाइल संपादित करा
        </a>
        <a href="delete-profile.php?id=<?= $id ?>"
           onclick="return confirm('\"<?= htmlspecialchars(addslashes($p['name'])) ?>\" ची प्रोफाइल कायमची हटवायची का?')"
           class="act-btn act-btn--delete">
            🗑️ हटवा
        </a>
    </div>
    <?php endif; ?>

</div><!-- /.page-wrap -->

<!-- ── LIGHTBOX ── -->
<div class="lb-overlay" id="lbOverlay" role="dialog" aria-modal="true" aria-label="फोटो फुलस्क्रीन">
    <div class="lb-topbar">
        <span class="lb-counter" id="lbCounter"></span>
        <button class="lb-close" id="lbClose" aria-label="बंद करा">×</button>
    </div>
    <div class="lb-img-wrap" id="lbImgWrap">
        <img class="lb-img" id="lbImg" src="" alt="Fullscreen photo">
    </div>
    <button class="lb-arrow prev" id="lbPrev" aria-label="मागील फोटो">‹</button>
    <button class="lb-arrow next" id="lbNext" aria-label="पुढिल फोटो">›</button>
    <div class="lb-thumbs" id="lbThumbs"></div>
</div>

<div id="toast" class="toast"></div>

<script>
(function() {
    /* ===== Toast ===== */
    function showToast(msg, dur = 2200) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), dur);
    }

    /* ===== Slideshow ===== */
    const slides = document.querySelectorAll('.gallery-slide');
    const dots   = document.querySelectorAll('.gallery-dot');
    let current  = 0;
    let autoplay;

    function goTo(idx) {
        slides[current]?.classList.remove('active');
        dots[current]?.classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current]?.classList.add('active');
        dots[current]?.classList.add('active');
    }
    // expose for lightbox open-at-current
    window.LB_CUR = current;

    if (slides.length > 1) {
        dots.forEach(d => d.addEventListener('click', () => {
            clearInterval(autoplay);
            goTo(+d.dataset.idx);
            window.LB_CUR = current;
            autoplay = setInterval(() => { goTo(current + 1); window.LB_CUR = current; }, 3500);
        }));
        autoplay = setInterval(() => { goTo(current + 1); window.LB_CUR = current; }, 3500);

        // Swipe on slideshow
        let sx = 0;
        const wrap = document.getElementById('galleryWrap');
        wrap?.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, {passive:true});
        wrap?.addEventListener('touchend',   e => {
            const d = sx - e.changedTouches[0].clientX;
            if (Math.abs(d) > 40) {
                clearInterval(autoplay);
                goTo(current + (d > 0 ? 1 : -1));
                window.LB_CUR = current;
                autoplay = setInterval(() => { goTo(current+1); window.LB_CUR=current; }, 3500);
            }
        });
    }

    /* ===== Lightbox ===== */
    const overlay  = document.getElementById('lbOverlay');
    const lbImg    = document.getElementById('lbImg');
    const lbWrap   = document.getElementById('lbImgWrap');
    const lbClose  = document.getElementById('lbClose');
    const lbPrev   = document.getElementById('lbPrev');
    const lbNext   = document.getElementById('lbNext');
    const lbThumbs = document.getElementById('lbThumbs');
    const lbCnt    = document.getElementById('lbCounter');

    let lbImages = [], lbIdx = 0, lbScale = 1;

    window.openLightbox = function(images, startIdx = 0) {
        lbImages = images;
        lbIdx    = Math.max(0, Math.min(startIdx, images.length - 1));
        buildThumbs();
        showLbImg();
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        // Hide arrows if only 1 image
        const multi = images.length > 1;
        lbPrev.style.display = multi ? '' : 'none';
        lbNext.style.display = multi ? '' : 'none';
    };

    function closeLightbox() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        lbImg.style.transform = 'scale(1)';
        lbScale = 1;
    }

    function showLbImg() {
        lbImg.style.opacity = '0';
        lbImg.style.transform = 'scale(1)';
        lbScale = 1;
        lbImg.src = lbImages[lbIdx];
        lbImg.onload = () => { lbImg.style.opacity = '1'; };
        lbCnt.textContent = lbImages.length > 1 ? `${lbIdx + 1} / ${lbImages.length}` : '';
        // Update thumbs
        document.querySelectorAll('.lb-thumb').forEach((th, i) => {
            th.classList.toggle('active', i === lbIdx);
        });
    }

    function buildThumbs() {
        lbThumbs.innerHTML = '';
        if (lbImages.length <= 1) return;
        lbImages.forEach((src, i) => {
            const th = document.createElement('img');
            th.src = src; th.className = 'lb-thumb'; th.alt = 'thumb';
            if (i === lbIdx) th.classList.add('active');
            th.addEventListener('click', () => { lbIdx = i; showLbImg(); });
            lbThumbs.appendChild(th);
        });
    }

    lbClose.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', e => { if (e.target === overlay || e.target === lbWrap) closeLightbox(); });

    lbPrev.addEventListener('click', () => { lbIdx = (lbIdx - 1 + lbImages.length) % lbImages.length; showLbImg(); });
    lbNext.addEventListener('click', () => { lbIdx = (lbIdx + 1) % lbImages.length; showLbImg(); });

    // Keyboard
    document.addEventListener('keydown', e => {
        if (!overlay.classList.contains('open')) return;
        if (e.key === 'Escape')      closeLightbox();
        if (e.key === 'ArrowRight')  { lbIdx = (lbIdx + 1) % lbImages.length; showLbImg(); }
        if (e.key === 'ArrowLeft')   { lbIdx = (lbIdx - 1 + lbImages.length) % lbImages.length; showLbImg(); }
        if (e.key === '+' || e.key === '=') zoomBy(0.25);
        if (e.key === '-')            zoomBy(-0.25);
    });

    // Double-tap to zoom
    let lastTap = 0;
    lbWrap.addEventListener('click', e => {
        if (e.target !== lbImg) return;
        const now = Date.now();
        if (now - lastTap < 300) {
            lbScale = lbScale > 1 ? 1 : 2;
            lbImg.style.transform = `scale(${lbScale})`;
        }
        lastTap = now;
    });

    // Zoom controls via buttons (hidden — keyboard/double-tap)
    function zoomBy(delta) {
        lbScale = Math.min(4, Math.max(0.5, lbScale + delta));
        lbImg.style.transform = `scale(${lbScale})`;
    }

    // Swipe inside lightbox
    let lbSx = 0;
    lbWrap.addEventListener('touchstart', e => { lbSx = e.touches[0].clientX; }, {passive:true});
    lbWrap.addEventListener('touchend', e => {
        if (lbImages.length <= 1) return;
        const diff = lbSx - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            lbIdx = (lbIdx + (diff > 0 ? 1 : -1) + lbImages.length) % lbImages.length;
            showLbImg();
        }
    });

    lbImg.style.transition = 'opacity .25s, transform .25s';

    /* ===== Shortlist toggle ===== */
    const btn = document.getElementById('profileHeartBtn');
    const lbl = document.getElementById('heartLabel');
    if (btn) {
        btn.addEventListener('click', async function() {
            this.classList.add('loading');
            try {
                const fd = new FormData();
                fd.append('id', this.dataset.id);
                const res  = await fetch('toggle-shortlist.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.shortlisted === 1) {
                    this.textContent = '❤️'; this.title = 'शॉर्टलिस्टमधून काढा';
                    if (lbl) lbl.textContent = 'शॉर्टलिस्टमध्ये आहे';
                    showToast('❤️ शॉर्टलिस्टमध्ये जोडले');
                } else {
                    this.textContent = '🤍'; this.title = 'शॉर्टलिस्टला जोडा';
                    if (lbl) lbl.textContent = 'शॉर्टलिस्टला जोडा';
                    showToast('🤍 शॉर्टलिस्टमधून काढले');
                }
            } catch(e) { showToast('Error. कृपया पुन्हा प्रयत्न करा.'); }
            finally { this.classList.remove('loading'); }
        });
    }
})();
</script>
</body>
</html>
