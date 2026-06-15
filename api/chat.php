<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit();
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$message   = trim($body['message']   ?? '');
$context   = trim($body['context']   ?? 'Materi umum');
$course_id = intval($body['course_id'] ?? 0) ?: null;
$minggu_ke = intval($body['minggu_ke'] ?? 0) ?: null;

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Pesan kosong']); exit();
}

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Ambil info materi minggu sebagai context jika ada
$material_ctx = '';
if ($course_id && $minggu_ke) {
    $stmt = $conn->prepare(
        "SELECT cw.judul_minggu, cw.deskripsi_minggu,
                GROUP_CONCAT(m.judul SEPARATOR ', ') AS materi_list
         FROM course_weeks cw
         LEFT JOIN materials m ON m.week_id = cw.id
         WHERE cw.course_id = ? AND cw.minggu_ke = ?
         GROUP BY cw.id"
    );
    $stmt->bind_param('ii', $course_id, $minggu_ke);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $material_ctx = "Judul minggu: {$row['judul_minggu']}\n"
                      . "Deskripsi: {$row['deskripsi_minggu']}\n"
                      . "Materi: {$row['materi_list']}";
    }
}

$system_prompt = "Kamu adalah CAI, asisten belajar cerdas untuk platform CAI (Classroom Artificial Intelligence). "
    . "Tugasmu adalah membantu mahasiswa memahami materi perkuliahan dengan penjelasan yang jelas, akurat, dan ramah.\n\n"
    . "Konteks materi minggu ini:\n"
    . ($material_ctx ?: $context) . "\n\n"
    . "Jawab pertanyaan mahasiswa berdasarkan konteks di atas. Jika pertanyaan di luar konteks materi, "
    . "tetap bantu tapi ingatkan untuk fokus ke materi kuliah. Gunakan Bahasa Indonesia yang baik dan mudah dipahami.";

$answer = callGeminiAPI($system_prompt, $message);

// Simpan ke database
$stmt = $conn->prepare(
    "INSERT INTO chat_history (user_id, course_id, minggu_ke, pertanyaan, jawaban, feedback, is_verified)
     VALUES (?, ?, ?, ?, ?, 'none', 0)"
);
$stmt->bind_param('iiiss', $uid, $course_id, $minggu_ke, $message, $answer);
$stmt->execute();
$chat_id = $conn->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'answer' => $answer, 'chat_id' => $chat_id]);
