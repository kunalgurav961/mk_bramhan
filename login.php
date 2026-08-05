<?php
session_start();

$error = '';

if (isset($_POST['login'])) {
    require_once 'includes/db.php';
    $conn = getDB();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username आणि Password भरा.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                header('Location: admin-dashboard.php');
                exit;
            } else {
                $error = 'चुकीचा Password.';
            }
        } else {
            $error = 'Username सापडला नाही.';
        }
    }
}

if (isset($_SESSION['admin_id'])) {
    header('Location: admin-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MK Brahman Admin Login">
    <title>Admin Login — MK Brahman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .brand-gradient { background: linear-gradient(135deg, #1a1a2e 0%, #c0392b 100%); }
        .input-field {
            transition: border-color .2s, box-shadow .2s;
        }
        .input-field:focus {
            border-color: #c0392b;
            box-shadow: 0 0 0 3px rgba(192,57,43,.15);
        }
    </style>
</head>
<body class="min-h-screen brand-gradient flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        <!-- Logo card -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg mb-3">
                <span style="font-size:30px;">🕉️</span>
            </div>
            <h1 class="text-white text-2xl font-bold">MK Brahman</h1>
            <p class="text-red-200 text-sm mt-1">ब्राह्मण विवाह संस्था</p>
        </div>

        <!-- Login form card -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">

            <h2 class="text-lg font-semibold text-gray-800 mb-1">Admin Login</h2>
            <p class="text-gray-400 text-xs mb-5">फक्त अधिकृत व्यक्तींसाठी</p>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-4 flex items-center gap-2">
                <span>⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>

                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1" for="username">
                        Username
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autocomplete="username"
                        placeholder="admin"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="input-field w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none bg-gray-50"
                    >
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-medium text-gray-600 mb-1" for="password">
                        Password
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="input-field w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none bg-gray-50 pr-10"
                        >
                        <button type="button"
                            onclick="togglePwd()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm"
                            aria-label="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    name="login"
                    class="w-full bg-red-700 hover:bg-red-800 text-white font-semibold py-3 rounded-xl transition-colors duration-200 text-sm">
                    Login करा
                </button>

            </form>

            <div class="text-center mt-4">
                <a href="index.php" class="text-xs text-gray-400 hover:text-red-700 transition-colors">
                    ← मुख्य पानावर जा
                </a>
            </div>

        </div>

    </div>

    <script>
        function togglePwd() {
            const pwd = document.getElementById('password');
            pwd.type = pwd.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
