<?php
require_once 'includes/db.php';
$conn = getDB();

header('Content-Type: application/json');

// GET count for badge refresh
if (isset($_GET['count'])) {
    $r = $conn->query("SELECT COUNT(*) AS total FROM profiles WHERE shortlisted = 1");
    $total = (int)$r->fetch_assoc()['total'];
    echo json_encode(['count' => $total, 'total_shortlisted' => $total]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// Toggle shortlisted state
$stmt = $conn->prepare("UPDATE profiles SET shortlisted = CASE WHEN shortlisted = 1 THEN 0 ELSE 1 END WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// Fetch new state for this profile
$stmt2 = $conn->prepare("SELECT shortlisted FROM profiles WHERE id = ?");
$stmt2->bind_param('i', $id);
$stmt2->execute();
$row = $stmt2->get_result()->fetch_assoc();

// Total shortlisted count for badge update
$totalRes = $conn->query("SELECT COUNT(*) AS total FROM profiles WHERE shortlisted = 1");
$total    = (int)$totalRes->fetch_assoc()['total'];

echo json_encode([
    'shortlisted'       => (int)$row['shortlisted'],
    'id'                => $id,
    'total_shortlisted' => $total,
]);