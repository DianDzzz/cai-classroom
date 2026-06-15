<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit();
}

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

/* ── GET: return current progress ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cid = intval($_GET['course_id'] ?? 0);
    if (!$cid) { echo json_encode(['success' => false]); exit(); }

    $total = $conn->query("SELECT COUNT(*) AS c FROM course_weeks WHERE course_id = $cid")->fetch_assoc()['c'];
    $done  = $conn->prepare("SELECT COUNT(*) AS c FROM progress WHERE user_id=? AND course_id=? AND is_completed=1");
    $done->bind_param('ii', $uid, $cid);
    $done->execute();
    $doneCount = $done->get_result()->fetch_assoc()['c'];
    $done->close();
    $pct = $total > 0 ? round($doneCount / $total * 100) : 0;
    echo json_encode(['success' => true, 'done' => $doneCount, 'total' => $total, 'percent' => $pct]);
    exit();
}

/* ── POST: save progress ── */
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$course_id = intval($body['course_id'] ?? 0);
$minggu_ke = intval($body['minggu_ke'] ?? 0);
$completed = (int)(bool)($body['is_completed'] ?? 0);

if (!$course_id || !$minggu_ke) {
    echo json_encode(['success' => false, 'error' => 'Missing params']); exit();
}

$stmt = $conn->prepare(
    "INSERT INTO progress (user_id, course_id, minggu_ke, is_completed)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE is_completed = ?"
);
$stmt->bind_param('iiiii', $uid, $course_id, $minggu_ke, $completed, $completed);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success' => $ok]);
