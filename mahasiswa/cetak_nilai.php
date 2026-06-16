<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('mahasiswa');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare(
    "SELECT c.kode_mk, c.nama_mk, c.sks, g.nilai, g.keterangan, g.updated_at
     FROM grades g
     JOIN courses c ON c.id = g.course_id
     WHERE g.user_id = ?
     ORDER BY c.kode_mk"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$grades = $stmt->get_result();
$stmt->close();

$page_title = 'Nilai Saya';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-header d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:var(--s-3)">
    <div>
      <h1>Nilai Saya</h1>
      <p>Rekap nilai akhir mata kuliah — <?= h($_SESSION['full_name']) ?></p>
    </div>
	<div class="d-flex gap-1 no-print">
      <button onclick="window.print()" class="btn btn-outline btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export PDF
      </button>
    </div>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Kode MK</th><th>Mata Kuliah</th><th>SKS</th><th>Nilai</th><th>Keterangan</th><th>Tanggal</th></tr>
        </thead>
        <tbody>
          <?php while ($g = $grades->fetch_assoc()): ?>
          <tr>
            <td class="fw-700 text-accent"><?= h($g['kode_mk']) ?></td>
            <td class="fw-600"><?= h($g['nama_mk']) ?></td>
            <td><?= $g['sks'] ?></td>
            <td>
              <?php if ($g['nilai']): ?>
                <span class="badge" style="font-size:.9rem;background:var(--accent-dim);color:var(--blue);padding:3px 12px">
                  <?= h($g['nilai']) ?>
                </span>
              <?php else: ?>
                <span class="text-muted text-sm">Belum dinilai</span>
              <?php endif; ?>
            </td>
            <td class="text-sm text-muted"><?= h($g['keterangan'] ?? '—') ?></td>
            <td class="text-sm text-muted"><?= $g['updated_at'] ? date('d/m/Y', strtotime($g['updated_at'])) : '—' ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
