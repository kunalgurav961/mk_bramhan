<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin-dashboard.php');
    exit;
}

$stmt = $conn->prepare("DELETE FROM profiles WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    header('Location: admin-dashboard.php?deleted=1');
} else {
    header('Location: admin-dashboard.php?error=not_found');
}
exit;