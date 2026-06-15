<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) { echo json_encode(['available' => false]); exit(); }

$conn = Database::getInstance()->getConnection();
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param('s', $q);
$stmt->execute();
$stmt->store_result();
echo json_encode(['available' => $stmt->num_rows === 0]);
$stmt->close();
