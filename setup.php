<?php
/**
 * MK Brahman — One-time Setup Script
 * -----------------------------------
 * Open this in browser: http://localhost/metrimony/setup.php
 * DELETE this file after setup is complete!
 */

// ── Security: block if already set up ──────────────────────────────────────
$lockFile = __DIR__ . '/.setup_done';
if (file_exists($lockFile)) {
    die('<div style="font-family:sans-serif;padding:30px;background:#f0fdf4;border:2px solid #86efac;border-radius:12px;max-width:500px;margin:40px auto;">
    <h2 style="color:#15803d;">✅ Setup Already Completed</h2>
    <p>The database has already been set up.</p>
    <a href="index.php" style="color:#16a34a;">→ Go to App</a></div>');
}

// ── Database config ─────────────────────────────────────────────────────────
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'mk_brahman';

$errors  = [];
$success = [];

// ── Connect without selecting DB first ─────────────────────────────────────
$conn = new mysqli($host, $user, $pass);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:30px;color:#dc2626;background:#fef2f2;border-radius:12px;max-width:500px;margin:40px auto;">
    <h2>❌ Database Connection Failed</h2>
    <p>' . htmlspecialchars($conn->connect_error) . '</p>
    <p><strong>Make sure LAMPP/XAMPP is running!</strong></p></div>');
}

// ── Run SQL ─────────────────────────────────────────────────────────────────
$sql = "
CREATE DATABASE IF NOT EXISTS `mk_brahman`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `mk_brahman`;

CREATE TABLE IF NOT EXISTS `profiles` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `registration_no`   VARCHAR(50)       NOT NULL,
    `registration_year` VARCHAR(4)        DEFAULT NULL COMMENT 'Registration year for nondani kramank',
    `gender`            TINYINT           NOT NULL COMMENT '1=Mulaga, 2=Mulagi',
    `birth_year`        VARCHAR(4)        NOT NULL,
    `name`              VARCHAR(150)      NOT NULL,
    `jaat`              VARCHAR(80)       DEFAULT NULL COMMENT 'जात (e.g. देशस्थ, कोकणस्थ)',
    `gotra`             VARCHAR(80)       NOT NULL,
    `rashi`             VARCHAR(50)       DEFAULT NULL COMMENT 'राशी',
    `nadi`              VARCHAR(50)       DEFAULT NULL COMMENT 'नाडी: प्रथम / मध्य / अंत्य',
    `height_ft`         TINYINT UNSIGNED  NOT NULL DEFAULT 5,
    `height_in`         TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    `salary`            INT UNSIGNED      NOT NULL DEFAULT 0,
    `weight`            SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Weight in kg',
    `varn`              VARCHAR(50)       DEFAULT NULL COMMENT 'Skin color / वर्ण',
    `chashma`           TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Glasses: 0=No, 1=Yes',
    `aahar`             VARCHAR(50)       DEFAULT NULL COMMENT 'Diet / आहार',
    `education`         VARCHAR(150)      DEFAULT NULL,
    `occupation`        VARCHAR(150)      DEFAULT NULL,
    `city`              VARCHAR(100)      NOT NULL,
    `mobile_no`         VARCHAR(30)       DEFAULT NULL,
    `mobile`            VARCHAR(30)       DEFAULT NULL,
    `mobile_2`          VARCHAR(30)       DEFAULT NULL,
    `mobile_3`          VARCHAR(30)       DEFAULT NULL,
    `mobile_4`          VARCHAR(30)       DEFAULT NULL,
    `father_name`       VARCHAR(150)      DEFAULT NULL,
    `mother_name`       VARCHAR(150)      DEFAULT NULL,
    `family_details`    TEXT              DEFAULT NULL,
    `about_me`          TEXT              DEFAULT NULL,
    `profile_image`     VARCHAR(255)      DEFAULT NULL,
    `profile_photo`     VARCHAR(255)      DEFAULT NULL,
    `sheet_img_1`       VARCHAR(500)      DEFAULT NULL,
    `sheet_img_2`       VARCHAR(500)      DEFAULT NULL,
    `sheet_img_3`       VARCHAR(500)      DEFAULT NULL,
    `sheet_img_4`       VARCHAR(500)      DEFAULT NULL,
    `shortlisted`       TINYINT(1)        NOT NULL DEFAULT 0,
    `status`            VARCHAR(20)       NOT NULL DEFAULT 'Active' COMMENT 'Active / Inactive',
    `sheets_synced_at`  DATETIME          DEFAULT NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_reg` (`registration_no`),
    INDEX `idx_name`       (`name`),
    INDEX `idx_birth_year` (`birth_year`),
    INDEX `idx_gender`     (`gender`),
    INDEX `idx_city`       (`city`),
    INDEX `idx_gotra`      (`gotra`),
    INDEX `idx_rashi`      (`rashi`),
    INDEX `idx_nadi`       (`nadi`),
    INDEX `idx_varn`       (`varn`),
    INDEX `idx_short`      (`shortlisted`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profile_images` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT UNSIGNED NOT NULL,
    `filename`   VARCHAR(255) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_profile_id` (`profile_id`),
    CONSTRAINT `fk_profile_images_profile` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50)  NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `full_name`  VARCHAR(100) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Execute multi-query
if ($conn->multi_query($sql)) {
    do { $conn->store_result(); } while ($conn->more_results() && $conn->next_result());
    $success[] = '✅ Database & Tables created successfully';
} else {
    $errors[] = 'SQL Error: ' . $conn->error;
}

// ── Insert default admin ────────────────────────────────────────────────────
$conn->select_db('mk_brahman');

// Check if admin exists
$check = $conn->query("SELECT id FROM admins WHERE username='admin' LIMIT 1");
if ($check && $check->num_rows === 0) {
    $hashedPwd = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO admins (username, password, full_name) VALUES (?, ?, ?)");
    $fullName = 'MK Brahman Admin';
    $stmt->bind_param('sss', 'admin', $hashedPwd, $fullName);
    if ($stmt->execute()) {
        $success[] = '✅ Default admin user created (username: <strong>admin</strong>, password: <strong>admin123</strong>)';
    } else {
        $errors[] = 'Admin insert error: ' . $conn->error;
    }
} else {
    $success[] = 'ℹ️ Admin user already exists — skipped';
}

// Check if mk_braman exists
$check2 = $conn->query("SELECT id FROM admins WHERE username='mk_braman' LIMIT 1");
if ($check2 && $check2->num_rows === 0) {
    $hashedPwd2 = password_hash('mkbramhan@123', PASSWORD_BCRYPT);
    $stmt2 = $conn->prepare("INSERT INTO admins (username, password, full_name) VALUES (?, ?, ?)");
    $fullName2 = 'MK Brahman Superadmin';
    $stmt2->bind_param('sss', 'mk_braman', $hashedPwd2, $fullName2);
    if ($stmt2->execute()) {
        $success[] = '✅ Superadmin user created (username: <strong>mk_braman</strong>, password: <strong>mkbramhan@123</strong>)';
    }
}

// ── Lock setup ──────────────────────────────────────────────────────────────
if (empty($errors)) {
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MK Brahman — Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Poppins',sans-serif;}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1a1a2e] to-[#c0392b] flex items-center justify-center p-4">

<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">

    <!-- Header -->
    <div class="bg-[#1a1a2e] px-6 py-5 text-white">
        <div class="flex items-center gap-3 mb-1">
            <span class="text-3xl">🕉️</span>
            <h1 class="text-xl font-bold">MK Brahman</h1>
        </div>
        <p class="text-sm text-gray-300">Database Setup Wizard</p>
    </div>

    <div class="p-6">

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
            <h3 class="font-bold text-red-700 mb-2">❌ Errors:</h3>
            <?php foreach ($errors as $e): ?>
                <p class="text-sm text-red-600"><?= $e ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
            <h3 class="font-bold text-green-700 mb-2">Setup Results:</h3>
            <?php foreach ($success as $s): ?>
                <p class="text-sm text-green-700 mb-1"><?= $s ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($errors)): ?>

        <!-- Credentials Card -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5">
            <h3 class="font-semibold text-amber-800 mb-3">🔐 Admin Credentials</h3>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="text-gray-500">URL</div>
                <div class="font-mono font-bold"><a href="login.php" class="text-blue-600">login.php</a></div>
                <div class="text-gray-500">Username</div>
                <div class="font-mono font-bold text-gray-800">admin</div>
                <div class="text-gray-500">Password</div>
                <div class="font-mono font-bold text-red-700">admin123</div>
            </div>
            <p class="text-xs text-amber-700 mt-3">⚠️ Change this password after first login!</p>
        </div>

        <!-- Schema Summary -->
        <div class="bg-slate-50 rounded-xl p-4 mb-5 text-sm">
            <h3 class="font-semibold text-gray-700 mb-2">📋 Tables Created</h3>
            <div class="space-y-1 text-gray-600">
                <div>✔ <code class="bg-white px-1 rounded">profiles</code> — matrimonial profiles</div>
                <div>✔ <code class="bg-white px-1 rounded">admins</code> — admin users</div>
            </div>
        </div>

        <!-- Next steps -->
        <div class="space-y-3">
            <a href="index.php"
               class="flex items-center justify-center gap-2 w-full bg-[#1a1a2e] hover:bg-[#2c2c5e] text-white font-semibold py-3 rounded-xl transition-colors text-sm">
                🏠 मुख्य पानावर जा (Home)
            </a>
            <a href="login.php"
               class="flex items-center justify-center gap-2 w-full bg-red-700 hover:bg-red-800 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
                🔐 Admin Login करा
            </a>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            ⚠️ Delete <code>setup.php</code> after setup for security.
        </p>

        <?php else: ?>
        <a href="setup.php"
           class="flex items-center justify-center w-full bg-red-700 text-white py-3 rounded-xl font-semibold text-sm mt-2">
            🔄 Try Again
        </a>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
