<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Mata kuliah + progres
$stmt = $conn->prepare(
    "SELECT c.id, c.kode_mk, c.nama_mk, c.sks,
            u.full_name AS dosen_nama,
            (SELECT COUNT(*) FROM course_weeks cw WHERE cw.course_id = c.id) AS total_minggu,
            (SELECT COUNT(*) FROM progress p WHERE p.user_id=? AND p.course_id=c.id AND p.is_completed=1) AS done_minggu
     FROM courses c
     JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
     JOIN users u ON u.id = c.dosen_id
     ORDER BY c.kode_mk"
);
$stmt->bind_param('ii', $uid, $uid);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nilai akhir
$stmt2 = $conn->prepare(
    "SELECT c.kode_mk, c.nama_mk, g.nilai
     FROM grades g JOIN courses c ON c.id = g.course_id
     WHERE g.user_id = ? ORDER BY g.updated_at DESC LIMIT 4"
);
$stmt2->bind_param('i', $uid);
$stmt2->execute();
$grades = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Total progres global
$total_all = array_sum(array_column($courses, 'total_minggu'));
$done_all  = array_sum(array_column($courses, 'done_minggu'));
$global_pct = $total_all > 0 ? round($done_all / $total_all * 100) : 0;

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <!-- Greeting -->
  <div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:var(--s-4)">
    <div>
      <h1 style="font-size:var(--text-xl);font-weight:700">Halo, <?= h($_SESSION['full_name']) ?></h1>
      <p class="text-sm text-muted">Selamat datang di CAI &mdash; Classroom Artificial Intelligence</p>
    </div>
    <div style="text-align:right">
      <div style="font-size:var(--text-2xl);font-weight:800;color:var(--accent)"><?= $global_pct ?>%</div>
      <div class="text-xs text-muted" style="text-transform:uppercase;letter-spacing:.06em">Progres Total</div>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid grid-3 mb-3">
    <div class="card stat-card">
      <div class="stat-value"><?= count($courses) ?></div>
      <div class="stat-label">Mata Kuliah</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= $done_all ?><span style="font-size:var(--text-lg);color:var(--muted)">/<?= $total_all ?></span></div>
      <div class="stat-label">Minggu Selesai</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= count($grades) ?></div>
      <div class="stat-label">Nilai Diterima</div>
    </div>
  </div>

  <!-- Mata kuliah -->
  <div class="section-label">Mata Kuliah Saya</div>
  <?php if (!$courses): ?>
    <div class="card mb-3">
      <div class="empty-state">
        <p>Belum terdaftar di mata kuliah. Hubungi dosen untuk pendaftaran.</p>
      </div>
    </div>
  <?php else: ?>
  <div class="course-grid mb-3">
    <?php foreach ($courses as $c):
      $pct = $c['total_minggu'] > 0 ? round($c['done_minggu'] / $c['total_minggu'] * 100) : 0;
    ?>
    <a href="<?= BASE_URL ?>/mahasiswa/detail_matkul.php?id=<?= $c['id'] ?>" class="course-card">
      <div class="course-code"><?= h($c['kode_mk']) ?> &bull; <?= $c['sks'] ?> SKS</div>
      <div class="course-name"><?= h($c['nama_mk']) ?></div>
      <div class="course-meta"><?= h($c['dosen_nama']) ?></div>
      <div class="progress-wrap">
        <div class="progress-label">
          <span>Progres</span>
          <span><?= $c['done_minggu'] ?>/<?= $c['total_minggu'] ?> &bull; <?= $pct ?>%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar <?= $pct===100?'complete':'' ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Nilai terbaru -->
  <?php if ($grades): ?>
  <div class="section-label">Nilai Terbaru</div>
  <div class="card">
    <div class="card-header">
      <span class="card-title">Rekap Nilai Akhir</span>
      <a href="<?= BASE_URL ?>/mahasiswa/cetak_nilai.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Mata Kuliah</th><th>Nilai</th></tr></thead>
        <tbody>
          <?php foreach ($grades as $g): ?>
          <tr>
            <td>
              <div class="fw-600"><?= h($g['nama_mk']) ?></div>
              <div class="text-xs text-muted"><?= h($g['kode_mk']) ?></div>
            </td>
            <td>
              <span class="badge" style="font-size:1rem;background:var(--accent-dim);color:var(--accent)">
                <?= h($g['nilai']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
