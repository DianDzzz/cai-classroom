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
      <a href="<?= BASE_URL ?>/api/export_pdf.php?type=nilai" target="_blank" class="btn btn-danger btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Export PDF
      </a>
      <button onclick="window.print()" class="btn btn-ghost btn-sm">Cetak</button>
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
