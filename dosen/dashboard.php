<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Stats
$s_mhs   = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='mahasiswa' AND is_active=1")->fetch_assoc()['c'];
$s_mk    = $conn->query("SELECT COUNT(*) AS c FROM courses WHERE dosen_id=$uid")->fetch_assoc()['c'];
$s_chat  = $conn->query("SELECT COUNT(*) AS c FROM chat_history")->fetch_assoc()['c'];
$s_unver = $conn->query("SELECT COUNT(*) AS c FROM chat_history WHERE is_verified=0")->fetch_assoc()['c'];

// Mahasiswa terbaru (5)
$recent_mhs = $conn->query(
    "SELECT id, full_name, username, nrp_nip, is_active, created_at
     FROM users WHERE role='mahasiswa' ORDER BY created_at DESC LIMIT 5"
);

// MK list
$my_courses = $conn->prepare("SELECT id, kode_mk, nama_mk, sks FROM courses WHERE dosen_id=? ORDER BY kode_mk");
$my_courses->bind_param('i', $uid);
$my_courses->execute();
$courses = $my_courses->get_result()->fetch_all(MYSQLI_ASSOC);
$my_courses->close();

$page_title = 'Dashboard Dosen';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <!-- Greeting -->
  <div class="page-header">
    <h1>Selamat datang, <?= h($_SESSION['full_name']) ?></h1>
    <p>Dashboard Dosen &mdash; CAI Classroom Artificial Intelligence</p>
  </div>

  <!-- Stats -->
  <div class="grid grid-4 mb-3">
    <div class="card stat-card">
      <div class="stat-value"><?= $s_mhs ?></div>
      <div class="stat-label">Mahasiswa Aktif</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= $s_mk ?></div>
      <div class="stat-label">Mata Kuliah Saya</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= $s_chat ?></div>
      <div class="stat-label">Total Interaksi AI</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--warning)"><?= $s_unver ?></div>
      <div class="stat-label">Belum Divalidasi</div>
    </div>
  </div>

  <div class="grid grid-2 mb-3">

    <!-- Mata kuliah saya -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Mata Kuliah Saya</span>
        <a href="<?= BASE_URL ?>/dosen/kelola_materi.php" class="btn btn-ghost btn-sm">Kelola Materi</a>
      </div>
      <?php if (!$courses): ?>
        <div class="empty-state"><p>Belum ada mata kuliah.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Kode</th><th>Mata Kuliah</th><th>SKS</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($courses as $c): ?>
            <tr>
              <td class="fw-600 text-accent"><?= h($c['kode_mk']) ?></td>
              <td><?= h($c['nama_mk']) ?></td>
              <td><?= $c['sks'] ?></td>
              <td><a href="<?= BASE_URL ?>/dosen/kelola_materi.php?course_id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Materi</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Akses cepat -->
    <div class="card">
      <div class="card-header"><span class="card-title">Akses Cepat</span></div>
      <div style="display:flex;flex-direction:column;gap:var(--s-3)">
        <?php
        $links = [
          ['Mata Kuliah',       BASE_URL.'/dosen/kelola_mk.php',         'Tambah, edit, dan hapus mata kuliah'],
          ['Enrollment',        BASE_URL.'/dosen/kelola_enrollment.php', 'Daftarkan mahasiswa ke mata kuliah'],
          ['Kelola Materi',     BASE_URL.'/dosen/kelola_materi.php',     'Upload PDF, PPT, atau link per minggu'],
          ['Validasi AI',       BASE_URL.'/dosen/validasi_ai.php',       "Verifikasi $s_unver jawaban Sevi AI yang belum ditinjau"],
          ['Input Nilai',       BASE_URL.'/dosen/input_nilai.php',       'Masukkan nilai akhir A–F untuk mahasiswa'],
          ['Kelola User',       BASE_URL.'/dosen/kelola_user.php',       'Aktivasi, edit, dan reset password mahasiswa'],
        ];
        foreach ($links as [$lbl, $href, $desc]): ?>
        <a href="<?= $href ?>" class="d-flex align-center gap-2" style="padding:var(--s-3) var(--s-4);background:var(--surface);border-radius:var(--r-lg);border:1px solid var(--border);text-decoration:none;transition:border-color .15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
          <div class="flex-1">
            <div class="fw-600 text-sm"><?= $lbl ?></div>
            <div class="text-xs text-muted"><?= $desc ?></div>
          </div>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted);flex-shrink:0">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Mahasiswa terbaru -->
  <div class="card mb-3">
    <div class="card-header">
      <span class="card-title">Mahasiswa Terbaru</span>
      <a href="<?= BASE_URL ?>/dosen/kelola_user.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>NRP</th><th>Status</th><th>Terdaftar</th></tr></thead>
        <tbody>
          <?php while ($row = $recent_mhs->fetch_assoc()): ?>
          <tr>
            <td class="text-muted"><?= $row['id'] ?></td>
            <td class="fw-600"><?= h($row['full_name'] ?? $row['username']) ?></td>
            <td class="text-muted text-sm"><?= h($row['username']) ?></td>
            <td class="text-sm"><?= h($row['nrp_nip'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $row['is_active']?'active':'inactive' ?>"><?= $row['is_active']?'Aktif':'Nonaktif' ?></span></td>
            <td class="text-sm text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RBAC & CRUD table (assignment) -->
  <div class="card mb-3">
    <div class="card-header"><span class="card-title">Tabel RBAC — Role-Based Access Control</span></div>
    <div class="matrix-wrap">
      <table class="matrix-table">
        <thead><tr><th>Fitur / Modul</th><th>Dosen</th><th>Mahasiswa</th><th>Keterangan</th></tr></thead>
        <tbody>
          <?php foreach ([
            ['Login & Logout',                '&#10003;','&#10003;','Semua user terdaftar'],
            ['Registrasi Akun',               '&#10003;','&#10003;','Publik, memilih role saat daftar'],
            ['Dashboard Dosen',               '&#10003;','&mdash;', 'Hanya role dosen'],
            ['Dashboard Mahasiswa',           '&mdash;', '&#10003;','Hanya role mahasiswa'],
            ['Upload / Edit / Hapus Materi',  '&#10003;','&mdash;', 'Dosen pemilik mata kuliah'],
            ['Akses Materi Perkuliahan',      '&#10003;','&#10003;','Mahasiswa yang terdaftar di MK'],
            ['Tandai Progres Belajar',        '&mdash;', '&#10003;','Hanya mahasiswa sendiri'],
            ['Lihat Progres Semua Mahasiswa', '&#10003;','&mdash;', 'Dosen untuk monitoring'],
            ['Chat Sevi AI',                  '&mdash;', '&#10003;','Mahasiswa aktif yang login'],
            ['Lihat Semua Chat History',      '&#10003;','&mdash;', 'Untuk keperluan validasi'],
            ['Validasi & Koreksi Jawaban AI', '&#10003;','&mdash;', 'Hak eksklusif dosen'],
            ['Feedback Thumbs Up/Down',       '&mdash;', '&#10003;','Mahasiswa memberi rating AI'],
            ['Input Nilai Akhir (A–F)',        '&#10003;','&mdash;', 'Hanya dosen'],
            ['Lihat Nilai Sendiri',           '&mdash;', '&#10003;','Mahasiswa hanya nilai sendiri'],
            ['Kelola User (aktif/nonaktif)',  '&#10003;','&mdash;', 'Dosen sebagai admin'],
          ] as [$f,$d,$m,$k]): ?>
          <tr>
            <td><?= $f ?></td>
            <td class="<?= $d==='&#10003;'?'ok':'no' ?>"><?= $d ?></td>
            <td class="<?= $m==='&#10003;'?'ok':'no' ?>"><?= $m ?></td>
            <td class="text-xs text-muted"><?= $k ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Metrik Security &mdash; Tabel CRUD</span></div>
    <div class="matrix-wrap">
      <table class="matrix-table">
        <thead><tr><th>Resource</th><th>Create (C)</th><th>Read (R)</th><th>Update (U)</th><th>Delete (D)</th></tr></thead>
        <tbody>
          <?php foreach ([
            ['Akun User',       'Publik (Register)',  'Dosen: semua &bull; MHS: sendiri', 'Dosen/MHS sendiri',         'Dosen (soft delete)'],
            ['Mata Kuliah',     'Dosen',              'Dosen &bull; MHS (terdaftar)',      'Dosen pemilik',             'Dosen pemilik'],
            ['Materi Minggu',   'Dosen',              'Dosen &bull; MHS (terdaftar)',      'Dosen pemilik MK',          'Dosen pemilik MK'],
            ['Progres Belajar', 'Mahasiswa',          'Dosen: semua &bull; MHS: sendiri', 'Mahasiswa sendiri',         '&mdash;'],
            ['Chat / AI',       'Mahasiswa',          'Dosen: semua &bull; MHS: sendiri', 'Dosen (validasi) + MHS (feedback)','&mdash;'],
            ['Nilai Akhir',     'Dosen',              'Dosen: semua &bull; MHS: sendiri', 'Dosen',                     'Dosen'],
          ] as [$r,$c,$rd,$u,$d]): ?>
          <tr>
            <td><?= $r ?></td>
            <td class="text-sm"><?= $c ?></td>
            <td class="text-sm"><?= $rd ?></td>
            <td class="text-sm"><?= $u ?></td>
            <td class="text-sm"><?= $d ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
