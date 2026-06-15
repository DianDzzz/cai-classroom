<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$conn     = Database::getInstance()->getConnection();
$msg      = '';
$msg_type = 'success';

/* ── POST: verifikasi / koreksi ─────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $chat_id = intval($_POST['chat_id'] ?? 0);

    if ($action === 'verify' && $chat_id) {
        $stmt = $conn->prepare("UPDATE chat_history SET is_verified=1, feedback_dosen=NULL WHERE id=?");
        $stmt->bind_param('i', $chat_id);
        $stmt->execute(); $stmt->close();
        $msg = 'Jawaban AI diverifikasi.';
    }
    if ($action === 'koreksi' && $chat_id) {
        $koreksi = trim($_POST['feedback_dosen'] ?? '');
        if ($koreksi) {
            $stmt = $conn->prepare("UPDATE chat_history SET is_verified=1, feedback_dosen=? WHERE id=?");
            $stmt->bind_param('si', $koreksi, $chat_id);
            $stmt->execute(); $stmt->close();
            $msg = 'Koreksi berhasil disimpan.';
        } else {
            $msg = 'Koreksi tidak boleh kosong.'; $msg_type = 'danger';
        }
    }
}

/* ── Filter ──────────────────────────────────────────────────── */
$f_course   = intval($_GET['course_id'] ?? 0);
$f_feedback = $_GET['feedback'] ?? '';
$f_verified = $_GET['verified'] ?? '';
$f_q        = trim($_GET['q']   ?? '');

$where  = [];
$params = [];
$types  = '';

if ($f_course) { $where[] = 'ch.course_id=?'; $params[] = $f_course; $types .= 'i'; }
if (in_array($f_feedback, ['up','down','none'], true)) { $where[] = 'ch.feedback=?'; $params[] = $f_feedback; $types .= 's'; }
if ($f_verified !== '') { $where[] = 'ch.is_verified=?'; $params[] = (int)$f_verified; $types .= 'i'; }
if ($f_q) { $like = "%$f_q%"; $where[] = '(ch.pertanyaan LIKE ? OR u.full_name LIKE ?)'; $params = array_merge($params, [$like,$like]); $types .= 'ss'; }

$sql = "SELECT ch.id, ch.pertanyaan, ch.jawaban, ch.feedback, ch.is_verified, ch.feedback_dosen,
               ch.created_at, ch.minggu_ke,
               u.full_name AS mhs_name, u.username,
               c.nama_mk, c.kode_mk
        FROM chat_history ch
        JOIN users u ON u.id = ch.user_id
        LEFT JOIN courses c ON c.id = ch.course_id"
      . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
      . " ORDER BY ch.is_verified ASC, ch.created_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$chats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Courses for filter
$courses_r = $conn->query("SELECT id, kode_mk, nama_mk FROM courses ORDER BY kode_mk");
$courses   = $courses_r->fetch_all(MYSQLI_ASSOC);

// Stats
$stat = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(is_verified=0) AS unverified,
    SUM(feedback='down') AS thumbs_down
    FROM chat_history")->fetch_assoc();

$page_title = 'Validasi AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:var(--s-3)">
    <div>
      <h1>Validasi Jawaban CAI</h1>
      <p>Tinjau interaksi mahasiswa dan berikan verifikasi atau koreksi pada jawaban AI</p>
    </div>
    <a href="<?= BASE_URL ?>/api/export_pdf.php?type=validasi" target="_blank" class="btn btn-danger btn-sm no-print">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Export PDF
    </a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="grid grid-3 mb-3">
    <div class="card stat-card">
      <div class="stat-value"><?= $stat['total'] ?></div>
      <div class="stat-label">Total Interaksi</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--warning)"><?= $stat['unverified'] ?></div>
      <div class="stat-label">Belum Diverifikasi</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--danger)"><?= $stat['thumbs_down'] ?></div>
      <div class="stat-label">Feedback Negatif</div>
    </div>
  </div>

  <!-- Filter -->
  <div class="card mb-3">
    <form method="GET" class="filter-bar">
      <div class="form-group">
        <label class="form-label">Cari</label>
        <input type="text" name="q" class="form-control" placeholder="Nama MHS / pertanyaan..." value="<?= h($f_q) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Mata Kuliah</label>
        <select name="course_id" class="form-control">
          <option value="">Semua MK</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $c['id']==$f_course?'selected':'' ?>>
            <?= h($c['kode_mk']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Feedback</label>
        <select name="feedback" class="form-control">
          <option value="">Semua</option>
          <option value="up"   <?= $f_feedback==='up'  ?'selected':'' ?>>Thumbs Up</option>
          <option value="down" <?= $f_feedback==='down'?'selected':'' ?>>Thumbs Down</option>
          <option value="none" <?= $f_feedback==='none'?'selected':'' ?>>Belum dinilai</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="verified" class="form-control">
          <option value="">Semua</option>
          <option value="0" <?= $f_verified==='0'?'selected':'' ?>>Belum diverifikasi</option>
          <option value="1" <?= $f_verified==='1'?'selected':'' ?>>Sudah diverifikasi</option>
        </select>
      </div>
      <div class="form-group" style="align-self:flex-end">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= BASE_URL ?>/dosen/validasi_ai.php" class="btn btn-ghost">Reset</a>
      </div>
    </form>
  </div>

  <!-- Chat list -->
  <?php if (!$chats): ?>
    <div class="card"><div class="empty-state"><p>Tidak ada riwayat chat yang ditemukan.</p></div></div>
  <?php endif; ?>

  <?php foreach ($chats as $ch): ?>
  <div class="chat-card <?= $ch['is_verified'] ? 'verified' : 'unverified' ?>">
    <!-- Meta -->
    <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap;gap:var(--s-2)">
      <div class="d-flex align-center gap-1" style="flex-wrap:wrap">
        <span class="badge badge-mahasiswa"><?= h($ch['mhs_name'] ?? $ch['username']) ?></span>
        <?php if ($ch['nama_mk']): ?>
        <span class="badge badge-link"><?= h($ch['kode_mk']) ?> &mdash; Minggu <?= $ch['minggu_ke'] ?></span>
        <?php endif; ?>
        <?php if ($ch['is_verified']): ?>
        <span class="badge badge-verified">Terverifikasi</span>
        <?php else: ?>
        <span class="badge badge-unverified">Belum diverifikasi</span>
        <?php endif; ?>
        <?php if ($ch['feedback'] === 'up'):   ?><span class="feedback-up">&#128077; Bermanfaat</span><?php endif; ?>
        <?php if ($ch['feedback'] === 'down'): ?><span class="feedback-down">&#128078; Kurang tepat</span><?php endif; ?>
      </div>
      <span class="text-xs text-muted"><?= date('d M Y H:i', strtotime($ch['created_at'])) ?></span>
    </div>

    <!-- Pertanyaan -->
    <div class="chat-q">
      <span style="color:var(--muted);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.06em">Pertanyaan</span><br>
      <?= h($ch['pertanyaan']) ?>
    </div>

    <!-- Jawaban AI -->
    <div style="margin-top:var(--s-3)">
      <span style="color:var(--muted);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.06em">Jawaban CAI</span>
      <div class="chat-a mt-1"><?= h($ch['jawaban']) ?></div>
    </div>

    <!-- Koreksi dosen (jika ada) -->
    <?php if ($ch['feedback_dosen']): ?>
    <div class="chat-correction mt-2">
      <strong>Koreksi Dosen:</strong> <?= h($ch['feedback_dosen']) ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="chat-meta">
      <?php if (!$ch['is_verified']): ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action"  value="verify">
        <input type="hidden" name="chat_id" value="<?= $ch['id'] ?>">
        <?php /* preserve filters */ foreach (['course_id'=>$f_course,'feedback'=>$f_feedback,'verified'=>$f_verified,'q'=>$f_q] as $k=>$v): if($v): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= h($v) ?>">
        <?php endif; endforeach; ?>
        <button type="submit" class="btn btn-success btn-sm">Verifikasi Jawaban</button>
      </form>
      <?php endif; ?>

      <button class="btn btn-warning btn-sm"
              onclick="openKoreksi(<?= $ch['id'] ?>, this.closest('.chat-card').querySelector('.chat-a').textContent)">
        <?= $ch['feedback_dosen'] ? 'Edit Koreksi' : 'Tambah Koreksi' ?>
      </button>
    </div>
  </div>
  <?php endforeach; ?>

</div>

<!-- ── Modal Koreksi ──────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-koreksi">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Koreksi Jawaban AI</span>
      <button class="modal-close" data-close-modal="modal-koreksi">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"  value="koreksi">
        <input type="hidden" name="chat_id" id="koreksi-chat-id">
        <div class="form-group">
          <label class="form-label">Komentar Koreksi *</label>
          <textarea name="feedback_dosen" id="koreksi-text" class="form-control" rows="5"
                    placeholder="Tuliskan koreksi atau klarifikasi jawaban AI di sini..." required></textarea>
          <span class="form-hint">Koreksi ini akan disimpan dan dapat dilihat dalam laporan validasi AI.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="modal-koreksi">Batal</button>
        <button type="submit" class="btn btn-warning">Simpan Koreksi</button>
      </div>
    </form>
  </div>
</div>

<script>
function openKoreksi(chatId, aiText) {
  document.getElementById('koreksi-chat-id').value = chatId;
  document.getElementById('koreksi-text').value    = '';
  Modal.open('modal-koreksi');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
