<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();
$msg  = '';
$msg_type = 'success';

/* ── POST: simpan nilai ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $nilai_arr = $_POST['nilai'] ?? [];

    if ($course_id && is_array($nilai_arr)) {
        $saved = 0;
        foreach ($nilai_arr as $mhs_id => $nilai) {
            $mhs_id = intval($mhs_id);
            $nilai  = strtoupper(trim($nilai));
            if (!$mhs_id) continue;

            if ($nilai === '' || $nilai === '-') {
                // Hapus nilai jika dikosongkan
                $d = $conn->prepare("DELETE FROM grades WHERE user_id=? AND course_id=?");
                $d->bind_param('ii', $mhs_id, $course_id);
                $d->execute(); $d->close();
            } elseif (in_array($nilai, ['A','B','C','D','E','F'], true)) {
                $ket = trim($_POST['keterangan'][$mhs_id] ?? '');
                $stmt = $conn->prepare(
                    "INSERT INTO grades (user_id, course_id, nilai, keterangan) VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE nilai=?, keterangan=?"
                );
                $stmt->bind_param('iissss', $mhs_id, $course_id, $nilai, $ket, $nilai, $ket);
                $stmt->execute(); $stmt->close();
                $saved++;
            }
        }
        $msg = "Nilai berhasil disimpan untuk $saved mahasiswa.";
    }
}

/* ── Ambil courses dosen ────────────────────────────────────── */
$sel_course = intval($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));
$stmt_c     = $conn->prepare("SELECT id, kode_mk, nama_mk FROM courses WHERE dosen_id=? ORDER BY kode_mk");
$stmt_c->bind_param('i', $uid);
$stmt_c->execute();
$courses = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_c->close();

if (!$sel_course && $courses) $sel_course = $courses[0]['id'];

/* ── Mahasiswa terdaftar + nilai ──────────────────────────────── */
$students = [];
if ($sel_course) {
    $stmt = $conn->prepare(
        "SELECT u.id, u.full_name, u.username, u.nrp_nip,
                g.nilai, g.keterangan
         FROM users u
         JOIN enrollments e ON e.user_id = u.id AND e.course_id = ?
         LEFT JOIN grades g ON g.user_id = u.id AND g.course_id = ?
         WHERE u.role = 'mahasiswa'
         ORDER BY u.full_name"
    );
    $stmt->bind_param('ii', $sel_course, $sel_course);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Grade distribution
$grade_dist = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0,'F'=>0,'-'=>0];
foreach ($students as $s) {
    $k = $s['nilai'] ?? '-';
    $grade_dist[$k] = ($grade_dist[$k] ?? 0) + 1;
}

$page_title = 'Input Nilai';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:var(--s-3)">
    <div>
      <h1>Input Nilai Akhir</h1>
      <p>Masukkan nilai akhir mahasiswa (A, B, C, D, E, atau F) per mata kuliah</p>
    </div>
    <a href="<?= BASE_URL ?>/api/export_pdf.php?type=nilai" target="_blank" class="btn btn-danger btn-sm no-print">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Export PDF Semua Nilai
    </a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Course selector -->
  <div class="card mb-3">
    <form method="GET" class="d-flex align-center gap-2" style="flex-wrap:wrap">
      <label class="form-label mb-0 fw-600">Mata Kuliah:</label>
      <select name="course_id" class="form-control" style="max-width:340px" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id']==$sel_course?'selected':'' ?>>
          <?= h($c['kode_mk']) ?> &mdash; <?= h($c['nama_mk']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (!$students): ?>
    <div class="card"><div class="empty-state"><p>Tidak ada mahasiswa terdaftar di mata kuliah ini.</p></div></div>
  <?php else: ?>

  <!-- Grade distribution -->
  <div class="card mb-3">
    <div class="card-header">
      <span class="card-title">Distribusi Nilai</span>
      <span class="text-sm text-muted"><?= count($students) ?> mahasiswa terdaftar</span>
    </div>
    <div class="d-flex gap-2" style="flex-wrap:wrap">
      <?php foreach (['A','B','C','D','E','F'] as $g): ?>
      <div style="text-align:center;padding:var(--s-3) var(--s-5);background:var(--surface);border-radius:var(--r-lg);min-width:60px">
        <div style="font-size:var(--text-lg);font-weight:800" class="grade-<?= $g ?>"><?= $g ?></div>
        <div class="text-xs text-muted"><?= $grade_dist[$g] ?> MHS</div>
      </div>
      <?php endforeach; ?>
      <div style="text-align:center;padding:var(--s-3) var(--s-5);background:var(--surface);border-radius:var(--r-lg);min-width:60px">
        <div style="font-size:var(--text-lg);font-weight:800;color:var(--muted)">—</div>
        <div class="text-xs text-muted"><?= $grade_dist['-'] ?> MHS</div>
      </div>
    </div>
  </div>

  <!-- Form nilai -->
  <form method="POST">
    <input type="hidden" name="course_id" value="<?= $sel_course ?>">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Daftar Mahasiswa</span>
        <button type="submit" class="btn btn-primary btn-sm">Simpan Semua Nilai</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Nama Mahasiswa</th>
              <th>NRP</th>
              <th>Nilai</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $s): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td>
                <div class="fw-600"><?= h($s['full_name'] ?? $s['username']) ?></div>
                <div class="text-xs text-muted"><?= h($s['username']) ?></div>
              </td>
              <td class="text-sm"><?= h($s['nrp_nip'] ?? '—') ?></td>
              <td>
                <select name="nilai[<?= $s['id'] ?>]"
                        class="grade-select"
                        onchange="this.className='grade-select grade-'+this.value">
                  <option value="-" <?= !$s['nilai']?'selected':'' ?>>—</option>
                  <?php foreach (['A','B','C','D','E','F'] as $g): ?>
                  <option value="<?= $g ?>" <?= $s['nilai']===$g?'selected':'' ?>><?= $g ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="keterangan[<?= $s['id'] ?>]"
                       class="form-control"
                       style="max-width:200px"
                       value="<?= h($s['keterangan'] ?? '') ?>"
                       placeholder="Opsional">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:var(--s-4);border-top:1px solid var(--border);display:flex;justify-content:flex-end">
        <button type="submit" class="btn btn-primary">Simpan Semua Nilai</button>
      </div>
    </div>
  </form>
  <?php endif; ?>

</div>

<script>
// Apply colour class on page load
document.querySelectorAll('.grade-select').forEach(function(sel) {
  if (sel.value && sel.value !== '-') sel.className = 'grade-select grade-' + sel.value;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
