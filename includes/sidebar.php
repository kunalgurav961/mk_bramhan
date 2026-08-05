<?php
// Sidebar include — requires $conn to already be set
$shortlistCount = 0;
if (isset($conn)) {
    $scRes = $conn->query("SELECT COUNT(*) AS total FROM profiles WHERE shortlisted = 1");
    if ($scRes) {
        $shortlistCount = (int)$scRes->fetch_assoc()['total'];
    }
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- OVERLAY -->
<div id="overlay" class="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR DRAWER -->
<aside id="sidebar" class="sidebar" role="navigation" aria-label="Main navigation">

    <div class="sidebar-header">
        <div class="sidebar-logo">
            <span class="sidebar-logo-icon">🕉️</span>
            <span class="sidebar-title">MK Brahman</span>
        </div>
        <button id="closeSidebar" class="close-btn" onclick="closeSidebar()" aria-label="Close menu">✕</button>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="side-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <span class="side-link-icon">👤</span>
            <span>सर्व प्रोफाइल</span>
        </a>
        <a href="shortlisted.php" class="side-link <?= $currentPage === 'shortlisted.php' ? 'active' : '' ?>">
            <span class="side-link-icon">❤️</span>
            <span>शॉर्टलिस्ट</span>
            <?php if ($shortlistCount > 0): ?>
                <span class="badge"><?= $shortlistCount ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <?php if (isset($_SESSION['admin_id'])): ?>
            <a href="admin-dashboard.php" class="admin-btn admin-btn--dashboard">
                <span>⚙️</span> Admin Dashboard
            </a>
            <a href="logout.php" class="admin-btn admin-btn--logout">
                <span>🚪</span> Logout
            </a>
        <?php else: ?>
            <a href="login.php" class="admin-btn">
                <span>🔐</span> Admin Login
            </a>
        <?php endif; ?>
    </div>

</aside>
