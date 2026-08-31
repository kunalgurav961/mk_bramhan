<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once 'includes/db.php';
require_once 'includes/image-helper.php';
$conn = getDB();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: admin-dashboard.php'); exit; }

// Fetch profile
$stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
if (!$profile) die('<p style="padding:20px">Profile not found. <a href="admin-dashboard.php">Back</a></p>');

// Fetch existing images
function fetchImages(mysqli $conn, int $pid): array {
    $s = $conn->prepare("SELECT * FROM profile_images WHERE profile_id = ? ORDER BY sort_order, id");
    $s->bind_param('i', $pid);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

$success = '';
$errors  = [];

// ── DELETE single image (AJAX) ─────────────────────────────────────────────
if (isset($_POST['delete_image_id']) && isset($_POST['ajax'])) {
    $imgId = (int)$_POST['delete_image_id'];
    $s = $conn->prepare("SELECT filename FROM profile_images WHERE id = ? AND profile_id = ?");
    $s->bind_param('ii', $imgId, $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if ($row) {
        deleteProfileImage($row['filename']);
        $conn->prepare("DELETE FROM profile_images WHERE id = ?")->execute() ||
        $conn->query("DELETE FROM profile_images WHERE id = $imgId");
        $del = $conn->prepare("DELETE FROM profile_images WHERE id = ?");
        $del->bind_param('i', $imgId);
        $del->execute();
        // Update primary image on profiles table
        $imgs = fetchImages($conn, $id);
        $primary = $imgs[0]['filename'] ?? null;
        $up = $conn->prepare("UPDATE profiles SET profile_image = ? WHERE id = ?");
        $up->bind_param('si', $primary, $id);
        $up->execute();
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── ADD new images (AJAX) ──────────────────────────────────────────────────
if (isset($_POST['add_images']) && isset($_POST['ajax'])) {
    $existing = count(fetchImages($conn, $id));
    $safePrefix = preg_replace('/[^a-z0-9]/i', '_', $profile['registration_no']);
    $added = [];
    $errs  = [];

    if (!empty($_FILES['new_images']['name'][0])) {
        $files = $_FILES['new_images'];
        $slots = 5 - $existing;
        for ($i = 0; $i < min(count($files['name']), $slots); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $single = ['name'=>$files['name'][$i],'type'=>$files['type'][$i],
                       'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
            $res = processProfileImage($single, $safePrefix . '_' . ($existing + $i + 1));
            if ($res['success']) {
                $order = $existing + $i;
                $ins = $conn->prepare("INSERT INTO profile_images (profile_id, filename, sort_order) VALUES (?,?,?)");
                $ins->bind_param('isi', $id, $res['filename'], $order);
                $ins->execute();
                // Keep profile_image in sync (first image = primary)
                if ($existing === 0 && $i === 0) {
                    $up = $conn->prepare("UPDATE profiles SET profile_image = ? WHERE id = ?");
                    $up->bind_param('si', $res['filename'], $id);
                    $up->execute();
                }
                $added[] = ['id' => $conn->insert_id, 'filename' => $res['filename'], 'sort_order' => $order];
            } else {
                $errs[] = $res['error'];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'added' => $added, 'errors' => $errs, 'total' => $existing + count($added)]);
    exit;
}

// ── SET PRIMARY image ──────────────────────────────────────────────────────
if (isset($_POST['set_primary_id']) && isset($_POST['ajax'])) {
    $imgId = (int)$_POST['set_primary_id'];
    // Reset all sort_order, then set this one to 0
    $conn->query("UPDATE profile_images SET sort_order = sort_order + 1 WHERE profile_id = $id");
    $sp = $conn->prepare("UPDATE profile_images SET sort_order = 0 WHERE id = ? AND profile_id = ?");
    $sp->bind_param('ii', $imgId, $id);
    $sp->execute();
    // Sync profiles.profile_image
    $sf = $conn->prepare("SELECT filename FROM profile_images WHERE id = ?");
    $sf->bind_param('i', $imgId);
    $sf->execute();
    $fn = $sf->get_result()->fetch_assoc()['filename'] ?? null;
    $up = $conn->prepare("UPDATE profiles SET profile_image = ? WHERE id = ?");
    $up->bind_param('si', $fn, $id);
    $up->execute();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── SAVE profile details ───────────────────────────────────────────────────
if (isset($_POST['update'])) {
    $registration_no = trim($_POST['registration_no'] ?? '');
    $gender          = (int)($_POST['gender'] ?? 0);
    $birth_year      = trim($_POST['birth_year'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $gotra           = trim($_POST['gotra'] ?? '');
    $height_ft       = (int)($_POST['height_ft'] ?? 5);
    $height_in       = (int)($_POST['height_in'] ?? 0);
    $salary          = (int)($_POST['salary'] ?? 0);
    $weight          = (int)($_POST['weight'] ?? 0);
    $education       = trim($_POST['education'] ?? '');
    $occupation      = trim($_POST['occupation'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $mobile_no       = trim($_POST['mobile_no'] ?? '');
    $father_name     = trim($_POST['father_name'] ?? '');
    $mother_name     = trim($_POST['mother_name'] ?? '');
    $family_details  = trim($_POST['family_details'] ?? '');
    $about_me        = trim($_POST['about_me'] ?? '');

    if ($registration_no === '') $errors[] = 'नोंदणी क्रमांक आवश्यक आहे.';
    if (!in_array($gender, [1, 2])) $errors[] = 'लिंग निवडा.';
    if ($birth_year === '') $errors[] = 'जन्म वर्ष आवश्यक आहे.';
    if ($name === '')  $errors[] = 'नाव आवश्यक आहे.';
    if ($gotra === '') $errors[] = 'गोत्र आवश्यक आहे.';
    if ($city === '')  $errors[] = 'शहर आवश्यक आहे.';

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE profiles SET
                    registration_no=?, gender=?, birth_year=?, name=?, gotra=?,
                    height_ft=?, height_in=?, salary=?, weight=?,
                    education=?, occupation=?, city=?,
                    mobile_no=?,
                    father_name=?, mother_name=?, family_details=?, about_me=?,
                    updated_at=NOW()
                WHERE id=?
            ");
            $stmt->bind_param('ssissiiiissssssssi',
                $registration_no, $gender, $birth_year, $name, $gotra,
                $height_ft, $height_in, $salary, $weight, $education, $occupation, $city,
                $mobile_no, $father_name, $mother_name, $family_details, $about_me, $id
            );
            $stmt->execute();
            $success = 'प्रोफाइल यशस्वीरित्या अपडेट केली! ✅';
            $profile = array_merge($profile, compact('registration_no','gender','birth_year','name','gotra',
                'height_ft','height_in','salary','weight','education','occupation','city',
                'mobile_no','father_name','mother_name','family_details','about_me'));
        } catch (\mysqli_sql_exception $e) {
            $errors[] = $e->getCode() === 1062
                ? 'नोंदणी क्रमांक आधीच अस्तित्वात आहे.'
                : 'Database error: ' . $e->getMessage();
        }
    }
}

function val(string $key, array $p): string { return htmlspecialchars($_POST[$key] ?? $p[$key] ?? ''); }
$existingImages = fetchImages($conn, $id);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>प्रोफाइल संपादित करा — MK Brahman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .field-label { display:block;font-size:11px;font-weight:600;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px; }
        .field { width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 14px;font-size:14px;background:#f9fafb;transition:border-color .2s;outline:none;font-family:'Poppins',sans-serif; }
        .field:focus { border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,.08);background:#fff; }
        .section-title { font-size:12px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;padding:8px 0 4px;border-bottom:1px solid #f3f4f6;margin-bottom:12px; }

        .img-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:10px;margin-bottom:12px; }
        .img-card { position:relative;border-radius:10px;overflow:hidden;aspect-ratio:1;border:2px solid #e5e7eb;background:#f3f4f6; }
        .img-card img { width:100%;height:100%;object-fit:cover; }
        .img-card.primary-card { border-color:#1a1a2e;box-shadow:0 0 0 3px rgba(26,26,46,.2); }
        .img-badge { position:absolute;top:4px;left:4px;background:#1a1a2e;color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:8px; }
        .img-del { position:absolute;top:4px;right:4px;width:22px;height:22px;background:rgba(220,38,38,.9);color:#fff;border:none;border-radius:50%;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center; }
        .img-primary-btn { position:absolute;bottom:4px;left:50%;transform:translateX(-50%);background:rgba(26,26,46,.85);color:#fff;border:none;border-radius:8px;font-size:9px;padding:2px 8px;cursor:pointer;white-space:nowrap; }

        .upload-zone { border:2px dashed #d1d5db;border-radius:14px;padding:16px;background:#f9fafb;cursor:pointer;text-align:center;transition:border-color .2s; }
        .upload-zone:hover { border-color:#c0392b;background:#fff5f5; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
<div class="max-w-md mx-auto bg-white min-h-screen shadow-lg">

    <div class="sticky top-0 z-50 bg-[#c0392b] text-white">
        <div class="flex items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <a href="profile.php?id=<?= $id ?>&from=admin-dashboard.php" class="w-8 h-8 bg-white/15 hover:bg-white/25 rounded-full flex items-center justify-center text-lg">←</a>
                <div>
                    <h1 class="font-bold text-base">✏️ प्रोफाइल संपादित करा</h1>
                    <p class="text-xs text-red-200"><?= htmlspecialchars($profile['name']) ?></p>
                </div>
            </div>
            <span class="text-xs bg-white/20 px-2 py-1 rounded-lg">#<?= $id ?></span>
        </div>
    </div>

    <div class="p-4">

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm mb-4 flex items-center gap-2">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm mb-4">
            <div class="font-semibold mb-1">⚠️ त्रुटी:</div>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- ── PHOTO MANAGEMENT SECTION ── -->
        <div class="section-title">📸 फोटो व्यवस्थापन</div>

        <div class="bg-gray-50 rounded-xl p-3 mb-4 border border-gray-100">
            <!-- Existing images grid -->
            <div id="imgGrid" class="img-grid">
                <?php foreach ($existingImages as $i => $img):
                    $src = 'uploads/profiles/' . htmlspecialchars(basename($img['filename']));
                ?>
                <div class="img-card <?= $i === 0 ? 'primary-card' : '' ?>" id="imgCard<?= $img['id'] ?>">
                    <img src="<?= $src ?>" alt="Photo <?= $i+1 ?>">
                    <span class="img-badge"><?= $i === 0 ? '🌟 मुख्य' : '#'.($i+1) ?></span>
                    <button type="button" class="img-del" onclick="deleteImg(<?= $img['id'] ?>)" title="हटवा">✕</button>
                    <?php if ($i !== 0): ?>
                    <button type="button" class="img-primary-btn" onclick="setPrimary(<?= $img['id'] ?>)">मुख्य करा</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($existingImages)): ?>
                <p class="text-xs text-gray-400 col-span-full text-center py-4">अद्याप कोणताही फोटो नाही</p>
                <?php endif; ?>
            </div>

            <!-- Upload more photos -->
            <?php $remaining = 5 - count($existingImages); ?>
            <?php if ($remaining > 0): ?>
            <div class="upload-zone" id="addZone" onclick="document.getElementById('newImagesInput').click()">
                <div class="text-2xl mb-1">➕</div>
                <div class="text-xs font-semibold text-gray-600">नवीन फोटो जोडा</div>
                <div class="text-xs text-gray-400 mt-0.5">आणखी <?= $remaining ?> फोटो जोडता येतात</div>
                <input type="file" id="newImagesInput" name="new_images[]"
                       accept="image/jpeg,image/jpg,image/png,image/webp"
                       multiple style="display:none"
                       onchange="uploadNewImages(this.files)">
            </div>
            <div id="uploadStatus" class="text-xs text-center mt-2" style="display:none"></div>
            <?php else: ?>
            <p class="text-xs text-center text-orange-500 mt-2">📌 5 फोटोंची मर्यादा पूर्ण झाली आहे.</p>
            <?php endif; ?>
        </div>

        <!-- ── PROFILE DETAILS FORM ── -->
        <form method="POST" class="space-y-4" novalidate>

            <div class="section-title">ओळख तपशील</div>
            <div>
                <label class="field-label">नोंदणी क्रमांक *</label>
                <input type="text" name="registration_no" class="field" value="<?= val('registration_no',$profile) ?>" required>
            </div>
            <div>
                <label class="field-label">मोबाईल नंबर</label>
                <input type="tel" name="mobile_no" class="field"
                       placeholder="उदा. 9876543210"
                       maxlength="15"
                       value="<?= htmlspecialchars($_POST['mobile_no'] ?? $profile['mobile_no'] ?? $profile['mobile'] ?? '') ?>">
            </div>
            <div>
                <label class="field-label">लिंग *</label>
                <select name="gender" class="field" required>
                    <option value="">-- निवडा --</option>
                    <option value="1" <?= (($_POST['gender']??$profile['gender'])==1)?'selected':'' ?>>मुलगा</option>
                    <option value="2" <?= (($_POST['gender']??$profile['gender'])==2)?'selected':'' ?>>मुलगी</option>
                </select>
            </div>
            <div>
                <label class="field-label">जन्म वर्ष *</label>
                <input type="text" name="birth_year" class="field" maxlength="4" value="<?= val('birth_year',$profile) ?>" required>
            </div>

            <div class="section-title">वैयक्तिक माहिती</div>
            <div>
                <label class="field-label">नाव (मराठीत) *</label>
                <input type="text" name="name" class="field" value="<?= val('name',$profile) ?>" required>
            </div>
            <div>
                <label class="field-label">गोत्र *</label>
                <input type="text" name="gotra" class="field" value="<?= val('gotra',$profile) ?>" required>
            </div>
            <div>
                <label class="field-label">उंची</label>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="height_ft" class="field" placeholder="फूट" min="4" max="7" value="<?= val('height_ft',$profile) ?>">
                    <input type="number" name="height_in" class="field" placeholder="इंच" min="0" max="11" value="<?= val('height_in',$profile) ?>">
                </div>
            </div>

            <div class="section-title">व्यावसायिक माहिती</div>
            <div>
                <label class="field-label">पगार (लाख/वर्ष)</label>
                <input type="number" name="salary" class="field" min="0" value="<?= val('salary',$profile) ?>">
            </div>
            <div>
                <label class="field-label">वजन (kg)</label>
                <input type="number" name="weight" class="field" min="0" max="200" placeholder="उदा. 70" value="<?= val('weight',$profile) ?>">
            </div>
            <div>
                <label class="field-label">शिक्षण</label>
                <input type="text" name="education" class="field" value="<?= val('education',$profile) ?>">
            </div>
            <div>
                <label class="field-label">व्यवसाय</label>
                <input type="text" name="occupation" class="field" value="<?= val('occupation',$profile) ?>">
            </div>
            <div>
                <label class="field-label">शहर *</label>
                <input type="text" name="city" class="field" value="<?= val('city',$profile) ?>" required>
            </div>

            <div class="section-title">कौटुंबिक माहिती</div>
            <div>
                <label class="field-label">वडिलांचे नाव</label>
                <input type="text" name="father_name" class="field" value="<?= val('father_name',$profile) ?>">
            </div>
            <div>
                <label class="field-label">आईचे नाव</label>
                <input type="text" name="mother_name" class="field" value="<?= val('mother_name',$profile) ?>">
            </div>
            <div>
                <label class="field-label">कुटुंबाची माहिती</label>
                <textarea name="family_details" class="field" rows="3"><?= val('family_details',$profile) ?></textarea>
            </div>
            <div>
                <label class="field-label">माझ्याबद्दल</label>
                <textarea name="about_me" class="field" rows="3"><?= val('about_me',$profile) ?></textarea>
            </div>

            <div class="pt-2 pb-6">
                <button type="submit" name="update"
                        class="w-full bg-red-700 hover:bg-red-800 text-white font-semibold py-3.5 rounded-xl text-sm transition-colors">
                    💾 बदल सेव्ह करा
                </button>
            </div>
        </form>
    </div>
</div>

<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-sm px-5 py-2.5 rounded-full shadow-lg translate-y-20 transition-transform duration-300 z-50"></div>

<script>
const PROFILE_ID = <?= $id ?>;

function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.textContent  = msg;
    t.style.background = isError ? '#dc2626' : '#1a1a2e';
    t.classList.remove('translate-y-20');
    setTimeout(() => t.classList.add('translate-y-20'), 2500);
}

// ── Delete image ──────────────────────────────────────────────────────────
async function deleteImg(imgId) {
    if (!confirm('हा फोटो कायमचा हटवायचा का?')) return;
    const fd = new FormData();
    fd.append('delete_image_id', imgId);
    fd.append('ajax', '1');
    const res = await fetch('edit-profile.php?id=' + PROFILE_ID, {method:'POST', body:fd});
    if ((await res.json()).ok) {
        document.getElementById('imgCard' + imgId)?.remove();
        rebadgeGrid();
        showToast('🗑️ फोटो हटवला');
    }
}

// ── Set primary ───────────────────────────────────────────────────────────
async function setPrimary(imgId) {
    const fd = new FormData();
    fd.append('set_primary_id', imgId);
    fd.append('ajax', '1');
    await fetch('edit-profile.php?id=' + PROFILE_ID, {method:'POST', body:fd});
    // Move card to front visually
    const card  = document.getElementById('imgCard' + imgId);
    const grid  = document.getElementById('imgGrid');
    grid.prepend(card);
    rebadgeGrid();
    showToast('🌟 मुख्य फोटो बदलला');
}

// ── Upload new images ─────────────────────────────────────────────────────
async function uploadNewImages(files) {
    if (!files.length) return;
    const status = document.getElementById('uploadStatus');
    status.style.display = 'block';
    status.textContent   = '⏳ अपलोड होत आहे...';
    status.style.color   = '#6b7280';

    const fd = new FormData();
    for (const f of files) fd.append('new_images[]', f);
    fd.append('ajax', '1');
    fd.append('add_images', '1');

    const res  = await fetch('edit-profile.php?id=' + PROFILE_ID, {method:'POST', body:fd});
    const data = await res.json();

    if (data.added && data.added.length > 0) {
        data.added.forEach((img, i) => {
            const order = img.sort_order;
            const card  = document.createElement('div');
            card.className = 'img-card';
            card.id = 'imgCard' + img.id;
            card.innerHTML = `
                <img src="uploads/profiles/${img.filename}" alt="Photo">
                <span class="img-badge">#${order + 1}</span>
                <button type="button" class="img-del" onclick="deleteImg(${img.id})">✕</button>
                <button type="button" class="img-primary-btn" onclick="setPrimary(${img.id})">मुख्य करा</button>
            `;
            document.getElementById('imgGrid').appendChild(card);
        });
        rebadgeGrid();
        showToast(`✅ ${data.added.length} फोटो जोडले`);
        status.textContent = '';
        if (data.total >= 5) {
            document.getElementById('addZone').style.display = 'none';
        }
    }
    if (data.errors && data.errors.length) {
        status.textContent = '⚠️ ' + data.errors.join(', ');
        status.style.color = '#dc2626';
    }
    // Reset file input
    document.getElementById('newImagesInput').value = '';
}

// ── Rebadge grid after changes ────────────────────────────────────────────
function rebadgeGrid() {
    const cards = document.querySelectorAll('#imgGrid .img-card');
    cards.forEach((card, i) => {
        const badge  = card.querySelector('.img-badge');
        const primBtn= card.querySelector('.img-primary-btn');
        if (badge) badge.textContent = i === 0 ? '🌟 मुख्य' : '#' + (i + 1);
        card.classList.toggle('primary-card', i === 0);
        if (primBtn) primBtn.style.display = i === 0 ? 'none' : '';
    });
}
</script>
</body>
</html>
