<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]); exit();
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$chat_id  = intval($body['chat_id'] ?? 0);
$feedback = $body['feedback'] ?? 'none';

if (!in_array($feedback, ['up','down','none'], true)) $feedback = 'none';
if (!$chat_id) { echo json_encode(['success' => false]); exit(); }

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare("UPDATE chat_history SET feedback = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param('sii', $feedback, $chat_id, $uid);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success' => $ok]);
