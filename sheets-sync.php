<?php
/**
 * MK Brahman — Google Sheets Sync Page
 * Admin-only. Triggers sync from Google Sheets → MySQL.
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/sheets-service.php';

$conn    = getDB();
$result  = null;
$ping    = null;
$sheets  = null;

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'ping') {
        $ping = SheetsService::ping()
            ? ['ok' => true,  'msg' => '✅ Apps Script is reachable!']
            : ['ok' => false, 'msg' => '❌ Cannot reach Apps Script. Check the URL in sheets-service.php.'];
    }

    if ($action === 'list_sheets') {
        $sheets = SheetsService::getSheetNames();
    }

    if ($action === 'sync_all') {
        $result = SheetsService::sync($conn, '');
    }

    if ($action === 'sync_sheet' && !empty($_POST['sheet_name'])) {
        $result = SheetsService::sync($conn, trim($_POST['sheet_name']));
    }
}

// Last sync time
$lastSync = $conn->query(
    "SELECT MAX(sheets_synced_at) AS last FROM profiles WHERE sheets_synced_at IS NOT NULL"
)->fetch_assoc()['last'];

$syncedCount = (int)$conn->query(
    "SELECT COUNT(*) AS c FROM profiles WHERE sheets_synced_at IS NOT NULL"
)->fetch_assoc()['c'];

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sheets Sync — MK Brahman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen">

<div class="max-w-md mx-auto bg-white min-h-screen shadow-lg">

    <!-- HEADER -->
    <div class="sticky top-0 z-50 bg-[#1a1a2e] text-white">
        <div class="flex items-center gap-3 px-4 py-3">
            <a href="admin-dashboard.php"
               class="w-8 h-8 bg-white/15 hover:bg-white/25 rounded-full flex items-center justify-center transition-colors">←</a>
            <div>
                <h1 class="font-bold text-base">📊 Google Sheets Sync</h1>
                <p class="text-xs text-gray-300">प्रोफाइल डेटा Sheet मधून आयात करा</p>
            </div>
        </div>
    </div>

    <div class="p-4 space-y-4">

        <!-- STATUS CARD -->
        <div class="bg-[#1a1a2e] text-white rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400">Sheet मधून Sync केलेल्या प्रोफाइल</p>
                <p class="text-3xl font-bold mt-1"><?= $syncedCount ?></p>
            </div>
            <div class="text-right text-xs text-gray-400">
                <p>शेवटचा Sync</p>
                <p class="text-white font-medium mt-0.5">
                    <?= $lastSync ? date('d M Y, h:i A', strtotime($lastSync)) : 'अजून नाही' ?>
                </p>
            </div>
        </div>

        <!-- URL WARNING if not configured -->
        <?php if (SHEETS_SCRIPT_URL === ''): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            <p class="font-semibold mb-1">⚠️ Apps Script URL अजून सेट केली नाही</p>
            <p class="text-xs leading-5">
                <code>includes/sheets-service.php</code> फाईल उघडा आणि<br>
                <code>define('SHEETS_SCRIPT_URL', '...')</code> मध्ये तुमची URL टाका.
            </p>
        </div>
        <?php endif; ?>

        <!-- PING TEST -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">📡 Connection Test</p>
            <form method="POST">
                <input type="hidden" name="action" value="ping">
                <button type="submit"
                        class="w-full bg-slate-700 hover:bg-slate-800 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Apps Script ला Ping करा
                </button>
            </form>
            <?php if ($ping !== null): ?>
            <div class="mt-3 text-sm <?= $ping['ok'] ? 'text-green-700' : 'text-red-700' ?> font-medium">
                <?= $ping['msg'] ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- LIST SHEETS -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">📋 Sheet Tabs</p>
            <form method="POST">
                <input type="hidden" name="action" value="list_sheets">
                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    उपलब्ध Tabs पहा
                </button>
            </form>
            <?php if ($sheets !== null && ($sheets['status'] ?? '') === 'ok'): ?>
            <div class="mt-3 space-y-2">
                <?php foreach ($sheets['sheets'] as $s): ?>
                <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-slate-200">
                    <div>
                        <span class="text-sm font-medium"><?= htmlspecialchars($s['name']) ?></span>
                        <span class="ml-2 text-xs <?= $s['gender'] == 1 ? 'text-blue-600' : 'text-purple-600' ?>">
                            <?= $s['gender'] == 1 ? '♂ मुलगे' : '♀ मुली' ?>
                        </span>
                    </div>
                    <span class="text-xs text-gray-500"><?= (int)$s['rows'] ?> rows</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($sheets !== null): ?>
            <p class="mt-3 text-sm text-red-600"><?= htmlspecialchars($sheets['message'] ?? 'Error') ?></p>
            <?php endif; ?>
        </div>

        <!-- SYNC ALL -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">🔄 सर्व Sync करा</p>
            <p class="text-xs text-green-600 mb-3">सर्व tabs मधील प्रोफाइल MySQL मध्ये import होतील.</p>
            <form method="POST"
                  onsubmit="return confirm('सर्व Google Sheet डेटा MySQL मध्ये sync करायचा का?')">
                <input type="hidden" name="action" value="sync_all">
                <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl text-sm transition-colors">
                    ▶ सर्व Sync करा
                </button>
            </form>
        </div>

        <!-- SYNC RESULT -->
        <?php if ($result !== null): ?>
        <div class="bg-white border rounded-xl p-4 space-y-2">
            <p class="text-sm font-semibold text-gray-700 mb-2">📊 Sync निकाल</p>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-green-50 rounded-lg p-3">
                    <p class="text-2xl font-bold text-green-700"><?= $result['added'] ?></p>
                    <p class="text-xs text-green-600 mt-0.5">नवीन जोडले</p>
                </div>
                <div class="bg-blue-50 rounded-lg p-3">
                    <p class="text-2xl font-bold text-blue-700"><?= $result['updated'] ?></p>
                    <p class="text-xs text-blue-600 mt-0.5">Updated</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-2xl font-bold text-slate-500"><?= $result['skipped'] ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">Skipped</p>
                </div>
            </div>

            <?php if (!empty($result['errors'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mt-2">
                <p class="text-xs font-semibold text-red-700 mb-1">⚠️ Errors (<?= count($result['errors']) ?>)</p>
                <ul class="text-xs text-red-600 space-y-0.5 list-disc list-inside">
                    <?php foreach (array_slice($result['errors'], 0, 10) as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($result['errors']) > 10): ?>
                    <li class="text-red-400">... आणि <?= count($result['errors']) - 10 ?> अधिक</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($result['added'] > 0 || $result['updated'] > 0): ?>
            <a href="index.php"
               class="block text-center bg-[#1a1a2e] text-white font-semibold py-2.5 rounded-xl text-sm mt-2 hover:bg-[#2c2c5e] transition-colors">
                → मुख्य पान पहा
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- HOW TO SECTION -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">📖 Setup कसा करायचा</p>
            <ol class="text-xs text-gray-600 space-y-2 list-decimal list-inside leading-5">
                <li>Google Sheet मध्ये <strong>मुलगे</strong> आणि <strong>मुली</strong> नावाचे tabs बनवा</li>
                <li>Column order: जन्म, अ.क्र., उंची, नाव, पगार, शिक्षण, ठिकाण, जात, गोत्र, रास, मोबाईल 1-4, इमेज 1-4</li>
                <li>Apps Script उघडा (Extensions → Apps Script) आणि <code>Code.gs</code> paste करा</li>
                <li>Deploy → New Deployment → Web App (Execute as: Me, Access: Anyone)</li>
                <li>URL कॉपी करा → <code>includes/sheets-service.php</code> मध्ये paste करा</li>
                <li>वर "Ping" करून connection तपासा → मग "Sync" करा</li>
            </ol>
        </div>

    </div><!-- /p-4 -->
</div>

</body>
</html>
