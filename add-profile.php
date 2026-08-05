<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/image-helper.php';
$conn = getDB();

// Enable exception mode for cleaner error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success = '';
$errors  = [];

if (isset($_POST['save'])) {
    $registration_no = trim($_POST['registration_no'] ?? '');
    $gender          = (int)($_POST['gender'] ?? 0);
    $birth_year      = trim($_POST['birth_year'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $gotra           = trim($_POST['gotra'] ?? '');
    $height_ft       = (int)($_POST['height_ft'] ?? 5);
    $height_in       = (int)($_POST['height_in'] ?? 0);
    $salary          = (int)($_POST['salary'] ?? 0);
    $education       = trim($_POST['education'] ?? '');
    $occupation      = trim($_POST['occupation'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $mobile_no       = trim($_POST['mobile_no'] ?? '');
    $father_name     = trim($_POST['father_name'] ?? '');
    $mother_name     = trim($_POST['mother_name'] ?? '');
    $family_details  = trim($_POST['family_details'] ?? '');
    $about_me        = trim($_POST['about_me'] ?? '');

    // Basic validation
    if ($registration_no === '') $errors[] = 'नोंदणी क्रमांक आवश्यक आहे.';
    if (!in_array($gender, [1, 2])) $errors[] = 'लिंग निवडा.';
    if ($birth_year === '')  $errors[] = 'जन्म वर्ष आवश्यक आहे (उदा. 95, 01).';
    if ($name === '')  $errors[] = 'नाव आवश्यक आहे.';
    if ($gotra === '') $errors[] = 'गोत्र आवश्यक आहे.';
    if ($city === '')  $errors[] = 'शहर आवश्यक आहे.';

    // ── Process multiple images ───────────────────────────────────────────
    $uploadedImages = [];   // ['filename' => ..., 'sort_order' => ...]
    $safePrefix     = preg_replace('/[^a-z0-9]/i', '_', $registration_no);

    if (!empty($_FILES['profile_images']['name'][0])) {
        $files = $_FILES['profile_images'];
        $count = count($files['name']);
        if ($count > 5) {
            $errors[] = 'जास्तीत जास्त 5 फोटो अपलोड करता येतात.';
        } else {
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $singleFile = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                $result = processProfileImage($singleFile, $safePrefix . '_' . ($i + 1));
                if (!$result['success']) {
                    $errors[] = 'फोटो ' . ($i + 1) . ' error: ' . $result['error'];
                } else {
                    $uploadedImages[] = ['filename' => $result['filename'], 'sort_order' => $i];
                }
            }
        }
    }

    if (empty($errors)) {
        // Determine primary image for backward-compat profile_image column
        $primaryImg = !empty($uploadedImages) ? $uploadedImages[0]['filename'] : null;

        try {
            $stmt = $conn->prepare("
                INSERT INTO profiles
                    (registration_no, gender, birth_year, name, gotra,
                     height_ft, height_in, salary, education, occupation, city,
                     mobile_no, father_name, mother_name, family_details, about_me, profile_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssissiiisssssssss',
                $registration_no, $gender, $birth_year, $name, $gotra,
                $height_ft, $height_in, $salary, $education, $occupation, $city,
                $mobile_no, $father_name, $mother_name, $family_details, $about_me, $primaryImg
            );
            $stmt->execute();
            $newProfileId = $conn->insert_id;

            // Save each image to profile_images table
            if (!empty($uploadedImages)) {
                $imgStmt = $conn->prepare(
                    "INSERT INTO profile_images (profile_id, filename, sort_order) VALUES (?, ?, ?)"
                );
                foreach ($uploadedImages as $img) {
                    $imgStmt->bind_param('isi', $newProfileId, $img['filename'], $img['sort_order']);
                    $imgStmt->execute();
                }
            }

            $success = 'प्रोफाइल यशस्वीरित्या जोडली गेली! 🎉';
            $_POST   = [];

        } catch (\mysqli_sql_exception $e) {
            // Rollback uploaded files on DB failure
            foreach ($uploadedImages as $img) deleteProfileImage($img['filename']);
            if ($e->getCode() === 1062) {
                $errors[] = 'नोंदणी क्रमांक "' . htmlspecialchars($registration_no) . '" आधीच अस्तित्वात आहे. कृपया वेगळा क्रमांक वापरा.';
            } else {
                $errors[] = 'Database error (' . $e->getCode() . '): ' . $e->getMessage();
            }
        }
    } else {
        // Validation failed — clean up any already-uploaded images
        foreach ($uploadedImages as $img) deleteProfileImage($img['filename']);
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>प्रोफाइल जोडा — MK Brahman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .field-label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px; }
        .field { width: 100%; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; font-size: 14px; background: #f9fafb; transition: border-color .2s, box-shadow .2s; outline: none; font-family: 'Poppins', sans-serif; }
        .field:focus { border-color: #1a1a2e; box-shadow: 0 0 0 3px rgba(26,26,46,.08); background: #fff; }
        .section-title { font-size: 12px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; padding: 8px 0 4px; border-bottom: 1px solid #f3f4f6; margin-bottom: 12px; }

        /* ── Multi-image upload zone ── */
        .upload-zone {
            border: 2px dashed #d1d5db;
            border-radius: 14px;
            padding: 20px 16px;
            background: #f9fafb;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            text-align: center;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: #1a1a2e;
            background: #f0f0f8;
        }
        .upload-zone input[type="file"] { display: none; }

        /* Preview grid */
        #previewGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .preview-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 1;
            border: 2px solid #e5e7eb;
            background: #f3f4f6;
        }
        .preview-item img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .preview-item .badge {
            position: absolute; top: 4px; left: 4px;
            background: #1a1a2e; color: #fff;
            font-size: 9px; font-weight: 700;
            padding: 2px 6px; border-radius: 8px;
        }
        .preview-item .remove-btn {
            position: absolute; top: 4px; right: 4px;
            width: 22px; height: 22px;
            background: rgba(220,38,38,.9); color: #fff;
            border: none; border-radius: 50%;
            font-size: 13px; line-height: 1;
            cursor: pointer; display: flex;
            align-items: center; justify-content: center;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

<div class="max-w-md mx-auto bg-white min-h-screen shadow-lg">

    <!-- HEADER -->
    <div class="sticky top-0 z-50 bg-[#1a1a2e] text-white">
        <div class="flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="admin-dashboard.php" class="w-8 h-8 bg-white/15 hover:bg-white/25 rounded-full flex items-center justify-center text-lg transition-colors">←</a>
                <div>
                    <h1 class="font-bold text-base">➕ प्रोफाइल जोडा</h1>
                    <p class="text-xs text-gray-300">नवीन प्रोफाइल नोंदवा</p>
                </div>
            </div>
        </div>
    </div>

    <div class="p-4">

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm mb-4 flex items-center gap-2">
            <span class="text-lg">✅</span> <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm mb-4">
            <div class="font-semibold mb-1">⚠️ कृपया खालील त्रुटी दुरुस्त करा:</div>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>

            <div class="section-title">ओळख तपशील</div>

            <!-- Registration No -->
            <div>
                <label class="field-label" for="registration_no">नोंदणी क्रमांक *</label>
                <input type="text" id="registration_no" name="registration_no" class="field"
                       placeholder="उदा. MKB001"
                       value="<?= htmlspecialchars($_POST['registration_no'] ?? '') ?>" required>
            </div>

            <!-- Mobile Number -->
            <div>
                <label class="field-label" for="mobile_no">मोबाईल नंबर</label>
                <input type="tel" id="mobile_no" name="mobile_no" class="field"
                       placeholder="उदा. 9876543210"
                       maxlength="15"
                       pattern="[0-9+\-\s]{7,15}"
                       value="<?= htmlspecialchars($_POST['mobile_no'] ?? '') ?>">
            </div>

            <!-- Gender -->
            <div>
                <label class="field-label" for="gender">लिंग *</label>
                <select id="gender" name="gender" class="field" required>
                    <option value="">-- निवडा --</option>
                    <option value="1" <?= (($_POST['gender'] ?? '') == '1') ? 'selected' : '' ?>>मुलगा (Mulaga)</option>
                    <option value="2" <?= (($_POST['gender'] ?? '') == '2') ? 'selected' : '' ?>>मुलगी (Mulagi)</option>
                </select>
            </div>

            <!-- Birth Year -->
            <div>
                <label class="field-label" for="birth_year">जन्म वर्ष *</label>
                <input type="text" id="birth_year" name="birth_year" class="field"
                       placeholder="उदा. 95, 99, 01"
                       maxlength="4"
                       value="<?= htmlspecialchars($_POST['birth_year'] ?? '') ?>" required>
            </div>

            <div class="section-title">वैयक्तिक माहिती</div>

            <!-- ── Multi-Photo Upload ── -->
            <div>
                <label class="field-label">प्रोफाइल फोटो (जास्तीत जास्त 5)</label>

                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('profile_images').click()">
                    <div class="text-3xl mb-1">📸</div>
                    <div class="text-sm font-semibold text-gray-600">फोटो निवडा किंवा drag करा</div>
                    <div class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP • प्रत्येक max 8MB • जास्तीत जास्त 5</div>
                    <input type="file" id="profile_images" name="profile_images[]"
                           accept="image/jpeg,image/jpg,image/png,image/webp"
                           multiple
                           onchange="handleImageSelect(this.files)">
                </div>

                <!-- Preview grid -->
                <div id="previewGrid"></div>
                <p id="imgCount" class="text-xs text-gray-400 mt-2 text-center" style="display:none"></p>
            </div>

            <!-- Name -->
            <div>
                <label class="field-label" for="name">नाव (मराठीत) *</label>
                <input type="text" id="name" name="name" class="field"
                       placeholder="पूर्ण नाव मराठीत लिहा"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>

            <!-- Gotra -->
            <div>
                <label class="field-label" for="gotra">गोत्र *</label>
                <input type="text" id="gotra" name="gotra" class="field"
                       placeholder="उदा. कश्यप, वसिष्ठ"
                       value="<?= htmlspecialchars($_POST['gotra'] ?? '') ?>" required>
            </div>

            <!-- Height -->
            <div>
                <label class="field-label">उंची</label>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="height_ft" class="field" placeholder="फूट (5)"
                           min="4" max="7" value="<?= htmlspecialchars($_POST['height_ft'] ?? '5') ?>">
                    <input type="number" name="height_in" class="field" placeholder="इंच (0)"
                           min="0" max="11" value="<?= htmlspecialchars($_POST['height_in'] ?? '0') ?>">
                </div>
            </div>

            <div class="section-title">व्यावसायिक माहिती</div>

            <!-- Salary -->
            <div>
                <label class="field-label" for="salary">पगार (लाख/वर्ष)</label>
                <input type="number" id="salary" name="salary" class="field"
                       placeholder="उदा. 5 (= 5 लाख)"
                       min="0" value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>">
            </div>

            <!-- Education -->
            <div>
                <label class="field-label" for="education">शिक्षण</label>
                <input type="text" id="education" name="education" class="field"
                       placeholder="उदा. B.E., M.B.A."
                       value="<?= htmlspecialchars($_POST['education'] ?? '') ?>">
            </div>

            <!-- Occupation -->
            <div>
                <label class="field-label" for="occupation">व्यवसाय</label>
                <input type="text" id="occupation" name="occupation" class="field"
                       placeholder="उदा. Software Engineer"
                       value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
            </div>

            <!-- City -->
            <div>
                <label class="field-label" for="city">शहर *</label>
                <input type="text" id="city" name="city" class="field"
                       placeholder="उदा. पुणे, नागपूर"
                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
            </div>

            <div class="section-title">कौटुंबिक माहिती</div>

            <!-- Father Name -->
            <div>
                <label class="field-label" for="father_name">वडिलांचे नाव</label>
                <input type="text" id="father_name" name="father_name" class="field"
                       placeholder="मराठीत लिहा"
                       value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>">
            </div>

            <!-- Mother Name -->
            <div>
                <label class="field-label" for="mother_name">आईचे नाव</label>
                <input type="text" id="mother_name" name="mother_name" class="field"
                       placeholder="मराठीत लिहा"
                       value="<?= htmlspecialchars($_POST['mother_name'] ?? '') ?>">
            </div>

            <!-- Family Details -->
            <div>
                <label class="field-label" for="family_details">कुटुंबाची माहिती</label>
                <textarea id="family_details" name="family_details" class="field" rows="3"
                          placeholder="भावंडे, कुटुंबाची पार्श्वभूमी इ."><?= htmlspecialchars($_POST['family_details'] ?? '') ?></textarea>
            </div>

            <!-- About Me -->
            <div>
                <label class="field-label" for="about_me">माझ्याबद्दल</label>
                <textarea id="about_me" name="about_me" class="field" rows="3"
                          placeholder="स्वतःबद्दल थोडक्यात लिहा..."><?= htmlspecialchars($_POST['about_me'] ?? '') ?></textarea>
            </div>

            <!-- Submit -->
            <div class="pt-2 pb-6">
                <button type="submit" name="save"
                        class="w-full bg-[#1a1a2e] hover:bg-[#2c2c5e] text-white font-semibold py-3.5 rounded-xl text-sm transition-colors">
                    ✅ प्रोफाइल सेव्ह करा
                </button>
            </div>

        </form>
    </div>

</div>

<script>
// ── File list maintained in JS (separate from input due to multi-add behavior) ──
let selectedFiles = [];

function handleImageSelect(newFiles) {
    for (const f of newFiles) {
        if (selectedFiles.length >= 5) break;
        // Avoid duplicates by name+size
        if (!selectedFiles.find(e => e.name === f.name && e.size === f.size)) {
            selectedFiles.push(f);
        }
    }
    syncInputAndPreview();
}

function removeImage(idx) {
    selectedFiles.splice(idx, 1);
    syncInputAndPreview();
}

function syncInputAndPreview() {
    // Rebuild the file input with a DataTransfer object
    const dt    = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('profile_images').files = dt.files;

    // Rebuild preview grid
    const grid  = document.getElementById('previewGrid');
    const count = document.getElementById('imgCount');
    grid.innerHTML = '';

    selectedFiles.forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const item = document.createElement('div');
            item.className = 'preview-item';
            item.innerHTML = `
                <img src="${e.target.result}" alt="Preview ${i+1}">
                <span class="badge">${i === 0 ? '🌟 मुख्य' : '#' + (i+1)}</span>
                <button type="button" class="remove-btn" onclick="removeImage(${i})" title="काढा">✕</button>
            `;
            grid.appendChild(item);
        };
        reader.readAsDataURL(file);
    });

    if (selectedFiles.length > 0) {
        count.style.display = 'block';
        count.textContent   = `${selectedFiles.length} / 5 फोटो निवडले • पहिला फोटो मुख्य (Cover) असेल`;
    } else {
        count.style.display = 'none';
    }
}

// ── Drag & Drop ──
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    handleImageSelect(e.dataTransfer.files);
});
</script>
</body>
</html>
