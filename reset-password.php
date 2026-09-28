<?php
/**
 * MK Brahman — Temporary Password Reset Tool
 * reset-password.php
 * 
 * ⚠️ WARNING: Delete this file after resetting your password!
 */
session_start();
require_once 'includes/db.php';
$conn = getDB();

$message = '';
$messageType = ''; // 'success' or 'error'

// Fetch all existing admins for the dropdown
$admins = [];
$res = $conn->query("SELECT id, username, full_name FROM admins ORDER BY username ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins[] = $row;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $selectedUsername = trim($_POST['username'] ?? '');
    $newPassword      = trim($_POST['new_password'] ?? '');
    $confirmPassword  = trim($_POST['confirm_password'] ?? '');

    if (empty($selectedUsername)) {
        $message = 'कृपया Username निवडा.';
        $messageType = 'error';
    } elseif (empty($newPassword)) {
        $message = 'कृपया नवीन Password टाका.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 4) {
        $message = 'Password किमान 4 अक्षरांचा असावा.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Password आणि Confirm Password जुळत नाहीत.';
        $messageType = 'error';
    } else {
        // Hash the password using secure BCRYPT
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        // Check if admin exists
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $selectedUsername);
        $stmt->execute();
        $adminResult = $stmt->get_result();

        if ($adminResult->num_rows > 0) {
            // Update existing admin
            $upd = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
            $upd->bind_param('ss', $hash, $selectedUsername);
            if ($upd->execute()) {
                $message = "✅ '{$selectedUsername}' चा Password यशस्वीरित्या बदलला आहे!";
                $messageType = 'success';
            } else {
                $message = 'Database error: ' . $conn->error;
                $messageType = 'error';
            }
        } else {
            // If user typed a new username that doesn't exist
            $fullName = ucfirst($selectedUsername) . ' Admin';
            $ins = $conn->prepare("INSERT INTO admins (username, password, full_name) VALUES (?, ?, ?)");
            $ins->bind_param('sss', $selectedUsername, $hash, $fullName);
            if ($ins->execute()) {
                $message = "✅ '{$selectedUsername}' हा नवीन Admin User तयार केला व Password सेट केला!";
                $messageType = 'success';
                // Refresh list
                $admins[] = ['id' => $conn->insert_id, 'username' => $selectedUsername, 'full_name' => $fullName];
            } else {
                $message = 'User तयार करताना एरर आला: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset (Temporary) — MK Brahman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-4">

<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-700">

    <!-- Top Banner -->
    <div class="bg-gradient-to-r from-red-600 to-amber-600 px-6 py-5 text-white">
        <div class="flex items-center gap-3">
            <span class="text-3xl">🔑</span>
            <div>
                <h1 class="text-xl font-bold">Password Reset</h1>
                <p class="text-xs text-amber-100">Temporary Admin Password Utility</p>
            </div>
        </div>
    </div>

    <!-- Security Warning -->
    <div class="bg-amber-50 border-b border-amber-200 px-6 py-3 text-xs text-amber-800 flex items-center gap-2">
        <span class="text-base">⚠️</span>
        <span><strong>टीप:</strong> Password बदलल्यानंतर सुरक्षेसाठी ही फाइल (<code>reset-password.php</code>) delete करा.</span>
    </div>

    <div class="p-6">

        <!-- Status Message -->
        <?php if (!empty($message)): ?>
            <?php if ($messageType === 'success'): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 text-sm">
                    <p class="font-medium"><?= htmlspecialchars($message) ?></p>
                    <div class="mt-3">
                        <a href="login.php" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-semibold text-xs transition">
                            🔐 आता Login करा →
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 text-sm">
                    <p class="font-medium">⚠️ <?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="reset-password.php" class="space-y-5">

            <!-- Select Username -->
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1">
                    Username निवडा:
                </label>
                <div class="relative">
                    <select id="username" name="username" required
                            class="w-full bg-slate-50 border border-gray-300 text-gray-900 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                        <option value="">-- Username निवडा --</option>
                        <?php foreach ($admins as $adm): ?>
                            <option value="<?= htmlspecialchars($adm['username']) ?>" <?= (isset($_POST['username']) && $_POST['username'] === $adm['username']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($adm['username']) ?> (<?= htmlspecialchars($adm['full_name'] ?: 'Admin') ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($admins)): ?>
                            <option value="admin" selected>admin (नवीन User तयार होईल)</option>
                            <option value="mk_braman">mk_braman (नवीन User तयार होईल)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <p class="text-xs text-gray-500 mt-1">किंवा Database मधील कोणताही Admin User निवडा.</p>
            </div>

            <!-- New Password -->
            <div>
                <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-1">
                    नवीन Password:
                </label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" required minlength="4"
                           placeholder="तुमचा नवीन password टाका..."
                           class="w-full bg-slate-50 border border-gray-300 text-gray-900 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                    <button type="button" onclick="togglePass('new_password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs px-2 py-1">
                        पहा
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-1">
                    Confirm Password (पुन्हा टाका):
                </label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="4"
                           placeholder="password पुन्हा टाका..."
                           class="w-full bg-slate-50 border border-gray-300 text-gray-900 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition">
                    <button type="button" onclick="togglePass('confirm_password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs px-2 py-1">
                        पहा
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" name="reset_password"
                    class="w-full bg-gradient-to-r from-red-600 to-amber-600 hover:from-red-700 hover:to-amber-700 text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg transition transform active:scale-95 text-sm flex items-center justify-center gap-2">
                <span>🔒</span>
                <span>Password Update करा</span>
            </button>

            <!-- Links -->
            <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
                <a href="login.php" class="text-blue-600 hover:underline">← Login पेजवर जा</a>
                <a href="index.php" class="text-gray-600 hover:underline">मुख्य पान (Home)</a>
            </div>

        </form>

    </div>

</div>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'लपवा';
    } else {
        input.type = 'password';
        btn.textContent = 'पहा';
    }
}
</script>

</body>
</html>
