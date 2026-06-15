<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/fpdf.php';

require_auth();

$uid   = (int)$_SESSION['user_id'];
$role  = $_SESSION['role'];
$conn  = Database::getInstance()->getConnection();

$type = $_GET['type'] ?? 'progress';  // progress | nilai | validasi

/* ════════════════════════════════════════════════════════════
   Helper: render header + footer on each page
   ════════════════════════════════════════════════════════════ */
class CaiPdf extends FPDF {
    public string $doc_title = '';
    public string $sub       = '';

    function Header() {
        // Navy header bar
        $this->SetFillColor(3, 27, 89);
        $this->Rect(0, 0, 210, 22, 'F');
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 5);
        $this->Cell(0, 10, 'CAI - Classroom Artificial Intelligence', 0, 1, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(200, 228, 240);
        $this->SetX(10);
        $this->Cell(0, 6, $this->sub, 0, 1, 'L');

        // Gold accent line
        $this->SetFillColor(255, 204, 0);
        $this->Rect(0, 22, 210, 1.5, 'F');

        $this->SetY(28);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer() {
        $this->SetY(-14);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, 'Diekspor: ' . date('d/m/Y H:i') . '  |  CAI - Classroom Artificial Intelligence', 0, 0, 'L');
        $this->Cell(0, 5, 'Hal ' . $this->PageNo(), 0, 0, 'R');
    }

    function SectionTitle(string $title) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(235, 246, 250);
        $this->SetTextColor(3, 27, 89);
        $this->SetDrawColor(190, 224, 240);
        $this->Cell(0, 8, '  ' . $title, 'B', 1, 'L', true);
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    function TableHeader(array $cols, array $widths) {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(3, 27, 89);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(3, 27, 89);
        foreach ($cols as $i => $col) {
            $this->Cell($widths[$i], 7, $col, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(235, 246, 250);
        $this->SetDrawColor(212, 236, 247);
    }

    function TableRow(array $cells, array $widths, bool $fill = false, array $aligns = []) {
        $this->SetFont('Arial', '', 8);
        $this->SetFillColor($fill ? 235 : 255, $fill ? 246 : 255, $fill ? 250 : 255);
        foreach ($cells as $i => $cell) {
            $align = $aligns[$i] ?? 'L';
            $this->Cell($widths[$i], 6, $cell, 1, 0, $align, $fill);
        }
        $this->Ln();
    }
}

$pdf = new CaiPdf();
$pdf->SetAuthor('CAI Classroom AI');
$pdf->SetCreator('CAI System');

/* ════════════════════════════════════════════════════════════
   TYPE: progress
   ════════════════════════════════════════════════════════════ */
if ($type === 'progress') {
    if ($role !== 'dosen') { http_response_code(403); echo 'Akses ditolak.'; exit; }

    $course_id = (int)($_GET['course_id'] ?? 0);
    if (!$course_id) { echo 'Pilih mata kuliah.'; exit; }

    $cs = $conn->prepare("SELECT * FROM courses WHERE id=? AND dosen_id=?");
    $cs->bind_param('ii', $course_id, $uid); $cs->execute();
    $course = $cs->get_result()->fetch_assoc(); $cs->close();
    if (!$course) { http_response_code(404); echo 'Mata kuliah tidak ditemukan.'; exit; }

    $rows = $conn->prepare(
        "SELECT u.full_name, u.nrp_nip,
                COUNT(DISTINCT cw.id) AS total_minggu,
                SUM(CASE WHEN pr.is_completed=1 THEN 1 ELSE 0 END) AS done_minggu
         FROM enrollments e
         JOIN users u ON u.id=e.user_id
         LEFT JOIN course_weeks cw ON cw.course_id=?
         LEFT JOIN progress pr ON pr.user_id=e.user_id AND pr.course_id=? AND pr.minggu_ke=cw.minggu_ke AND pr.is_completed=1
         WHERE e.course_id=? AND u.role='mahasiswa'
         GROUP BY u.id, u.full_name, u.nrp_nip
         ORDER BY u.full_name"
    );
    $rows->bind_param('iii', $course_id, $course_id, $course_id); $rows->execute();
    $data = $rows->get_result()->fetch_all(MYSQLI_ASSOC); $rows->close();

    $pdf->doc_title = 'Rekap Progres Belajar';
    $pdf->sub       = $course['kode_mk'] . ' — ' . $course['nama_mk'];
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(3, 27, 89);
    $pdf->Cell(0, 10, 'Rekap Progres Belajar Mahasiswa', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 6, $course['kode_mk'] . ' — ' . $course['nama_mk'] . '  |  Diekspor: ' . date('d/m/Y'), 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->TableHeader(['No', 'Nama Mahasiswa', 'NRP', 'Selesai', 'Total', 'Progres (%)'], [10,70,35,22,22,31]);
    $i = 1;
    foreach ($data as $r) {
        $total = (int)$r['total_minggu'];
        $done  = (int)$r['done_minggu'];
        $pct   = $total > 0 ? round($done / $total * 100) : 0;
        $pdf->TableRow([
            $i++,
            $r['full_name'],
            $r['nrp_nip'] ?: '—',
            $done,
            $total,
            $pct . '%'
        ], [10,70,35,22,22,31], ($i % 2 === 0), ['C','L','C','C','C','C']);
    }

    $pdf->Output('I', 'progres_' . $course['kode_mk'] . '_' . date('Ymd') . '.pdf');

/* ════════════════════════════════════════════════════════════
   TYPE: nilai
   ════════════════════════════════════════════════════════════ */
} elseif ($type === 'nilai') {
    if ($role === 'mahasiswa') {
        // Mahasiswa: cetak nilai sendiri
        $grades = $conn->prepare(
            "SELECT c.kode_mk, c.nama_mk, c.sks, g.nilai, g.updated_at
             FROM grades g JOIN courses c ON c.id=g.course_id
             WHERE g.user_id=? ORDER BY c.kode_mk"
        );
        $grades->bind_param('i', $uid); $grades->execute();
        $data = $grades->get_result()->fetch_all(MYSQLI_ASSOC); $grades->close();

        $pdf->doc_title = 'Rekap Nilai Akhir';
        $pdf->sub       = 'Mahasiswa: ' . ($_SESSION['full_name'] ?? '');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(3, 27, 89);
        $pdf->Cell(0, 10, 'Rekap Nilai Akhir', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 6, 'Mahasiswa: ' . ($_SESSION['full_name'] ?? '') . '  |  Diekspor: ' . date('d/m/Y'), 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->TableHeader(['No', 'Kode MK', 'Nama Mata Kuliah', 'SKS', 'Nilai'], [10, 28, 100, 18, 34]);
        $i = 1;
        foreach ($data as $r) {
            $pdf->TableRow([$i++, $r['kode_mk'], $r['nama_mk'], $r['sks'], $r['nilai']], [10,28,100,18,34], ($i%2===0), ['C','C','L','C','C']);
        }
    } else {
        // Dosen: nilai semua MK milik dosen
        $stmt = $conn->prepare(
            "SELECT c.kode_mk, c.nama_mk, u.full_name, u.nrp_nip, g.nilai
             FROM grades g
             JOIN courses c ON c.id=g.course_id AND c.dosen_id=?
             JOIN users u ON u.id=g.user_id
             ORDER BY c.kode_mk, u.full_name"
        );
        $stmt->bind_param('i', $uid); $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

        $pdf->doc_title = 'Rekap Nilai Semua MK';
        $pdf->sub       = 'Dosen: ' . ($_SESSION['full_name'] ?? '');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(3, 27, 89);
        $pdf->Cell(0, 10, 'Rekap Nilai Akhir Mahasiswa', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 6, 'Dosen: ' . ($_SESSION['full_name'] ?? '') . '  |  Diekspor: ' . date('d/m/Y'), 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->TableHeader(['No', 'Kode MK', 'Mata Kuliah', 'NRP', 'Nama Mahasiswa', 'Nilai'], [10,22,60,28,52,18]);
        $i = 1;
        foreach ($data as $r) {
            $pdf->TableRow([$i++, $r['kode_mk'], $r['nama_mk'], $r['nrp_nip']?:'—', $r['full_name'], $r['nilai']], [10,22,60,28,52,18], ($i%2===0), ['C','C','L','C','L','C']);
        }
    }

    $pdf->Output('I', 'nilai_' . date('Ymd') . '.pdf');

/* ════════════════════════════════════════════════════════════
   TYPE: validasi (dosen only)
   ════════════════════════════════════════════════════════════ */
} elseif ($type === 'validasi') {
    if ($role !== 'dosen') { http_response_code(403); echo 'Akses ditolak.'; exit; }

    $stmt = $conn->prepare(
        "SELECT ch.id, u.full_name, c.kode_mk, c.nama_mk, ch.minggu_ke,
                ch.message, ch.answer, ch.is_verified, ch.feedback, ch.feedback_dosen, ch.created_at
         FROM chat_history ch
         JOIN users u ON u.id=ch.user_id
         JOIN courses c ON c.id=ch.course_id AND c.dosen_id=?
         ORDER BY ch.created_at DESC LIMIT 200"
    );
    $stmt->bind_param('i', $uid); $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    $pdf->doc_title = 'Laporan Validasi AI Sevi';
    $pdf->sub       = 'Dosen: ' . ($_SESSION['full_name'] ?? '') . '  |  Total: ' . count($data) . ' chat';
    $pdf->AddPage('P', 'A4');
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(3, 27, 89);
    $pdf->Cell(0, 10, 'Laporan Validasi AI — CAI', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 6, 'Dosen: ' . ($_SESSION['full_name'] ?? '') . '  |  ' . count($data) . ' chat  |  Diekspor: ' . date('d/m/Y'), 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(30, 41, 59);
    $odd = false;
    foreach ($data as $r) {
        $odd = !$odd;
        $pdf->SetFillColor($odd ? 235 : 255, $odd ? 246 : 255, $odd ? 250 : 255);
        $pdf->SetDrawColor(212, 236, 247);

        $header = '#' . $r['id'] . '  ' . $r['full_name'] . ' | ' . $r['kode_mk'] . ' Minggu-' . $r['minggu_ke']
                . '  |  ' . ($r['is_verified'] ? 'VERIFIED' : 'UNVERIFIED')
                . ($r['feedback'] ? '  FB:' . strtoupper($r['feedback']) : '')
                . '  ' . date('d/m/Y H:i', strtotime($r['created_at']));

        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->SetFillColor(3, 27, 89); $pdf->SetTextColor(255,255,255);
        $pdf->MultiCell(0, 6, $header, 1, 'L', true);
        $pdf->SetFillColor($odd?235:255, $odd?246:255, $odd?250:255);
        $pdf->SetTextColor(30,41,59);

        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->MultiCell(0, 5.5, 'Q: ' . $r['message'], 'LR', 'L', $odd);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->MultiCell(0, 5.5, 'A: ' . mb_substr($r['answer'], 0, 500) . (mb_strlen($r['answer']) > 500 ? '...' : ''), 'LRB', 'L', $odd);

        if ($r['feedback_dosen']) {
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->SetTextColor(185, 28, 28);
            $pdf->MultiCell(0, 5, 'Koreksi Dosen: ' . $r['feedback_dosen'], 'LRB', 'L');
            $pdf->SetTextColor(30,41,59);
        }
        $pdf->Ln(1);

        // prevent endless single page
        if ($pdf->GetY() > 270) $pdf->AddPage();
    }

    $pdf->Output('I', 'validasi_ai_' . date('Ymd') . '.pdf');
} else {
    echo 'Tipe export tidak dikenali.';
}
