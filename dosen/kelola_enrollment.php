<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

$msg = $err = '';

/* ── POST: enroll / unenroll ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = $_POST['action']    ?? '';
    $course_id = (int)($_POST['course_id'] ?? 0);
    $mhs_id   = (int)($_POST['user_id']    ?? 0);

    // Verify dosen owns this course
    $own = $conn->prepare("SELECT id FROM courses WHERE id=? AND dosen_id=?");
    $own->bind_param('ii', $course_id, $uid); $own->execute(); $own->store_result();
    $is_owner = $own->num_rows > 0; $own->close();

    if (!$is_owner) {
        $err = 'Anda tidak berhak mengubah enrollment untuk mata kuliah ini.';
    } elseif ($act === 'enroll') {
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?,?)"
        );
        $stmt->bind_param('ii', $mhs_id, $course_id);
        $stmt->execute() ? $msg = 'Mahasiswa berhasil didaftarkan.' : $err = 'Gagal mendaftarkan.';
        $stmt->close();
    } elseif ($act === 'unenroll') {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE user_id=? AND course_id=?");
        $stmt->bind_param('ii', $mhs_id, $course_id);
        $stmt->execute() ? $msg = 'Mahasiswa berhasil dikeluarkan dari mata kuliah.' : $err = 'Gagal menghapus.';
        $stmt->close();
    } elseif ($act === 'enroll_bulk') {
        $ids = $_POST['mhs_ids'] ?? [];
        $added = 0;
        $stmtb = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?,?)");
        foreach ($ids as $mid) {
            $mid = (int)$mid;
            if ($mid < 1) continue;
            $stmtb->bind_param('ii', $mid, $course_id);
            if ($stmtb->execute()) $added++;
        }
        $stmtb->close();
        $msg = "$added mahasiswa berhasil didaftarkan.";
    }
}

/* ── GET: selected course ─────────────────────────────────── */
$sel_course_id = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$sel_course = null;
if ($sel_course_id) {
    $cs = $conn->prepare("SELECT * FROM courses WHERE id=? AND dosen_id=?");
    $cs->bind_param('ii', $sel_course_id, $uid); $cs->execute();
    $sel_course = $cs->get_result()->fetch_assoc(); $cs->close();
    if (!$sel_course) { $sel_course_id = 0; $err = 'Mata kuliah tidak ditemukan.'; }
}

// All dosen's courses
$all_courses_stmt = $conn->prepare("SELECT id, kode_mk, nama_mk FROM courses WHERE dosen_id=? ORDER BY kode_mk");
$all_courses_stmt->bind_param('i', $uid); $all_courses_stmt->execute();
$all_courses = $all_courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $all_courses_stmt->close();

// Enrolled students for selected course
$enrolled = [];
if ($sel_course_id) {
    $es = $conn->prepare(
        "SELECT u.id, u.full_name, u.username, u.nrp_nip, u.is_active, e.enrolled_at
         FROM enrollments e JOIN users u ON u.id=e.user_id
         WHERE e.course_id=? AND u.role='mahasiswa' ORDER BY u.full_name"
    );
    $es->bind_param('i', $sel_course_id); $es->execute();
    $enrolled = $es->get_result()->fetch_all(MYSQLI_ASSOC); $es->close();
}

// All active mahasiswa NOT enrolled in selected course
$not_enrolled = [];
if ($sel_course_id) {
    $ne = $conn->prepare(
        "SELECT u.id, u.full_name, u.username, u.nrp_nip
         FROM users u
         WHERE u.role='mahasiswa' AND u.is_active=1
           AND u.id NOT IN (SELECT user_id FROM enrollments WHERE course_id=?)
         ORDER BY u.full_name"
    );
    $ne->bind_param('i', $sel_course_id); $ne->execute();
    $not_enrolled = $ne->get_result()->fetch_all(MYSQLI_ASSOC); $ne->close();
}

$page_title = 'Kelola Enrollment';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <div class="page-header mb-3">
    <h1>Kelola Enrollment</h1>
    <p>Tambah atau keluarkan mahasiswa dari mata kuliah.</p>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger" ><?= h($err) ?></div><?php endif; ?>

  <!-- Course selector -->
  <div class="card mb-3">
    <div class="card-header"><span class="card-title">Pilih Mata Kuliah</span></div>
    <form method="GET" class="d-flex align-center gap-2" style="padding:0 var(--s-5) var(--s-5);flex-wrap:wrap">
      <div class="form-group" style="flex:1;margin:0;min-width:220px">
        <select name="course_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- Pilih Mata Kuliah --</option>
          <?php foreach ($all_courses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $sel_course_id===$c['id']?'selected':'' ?>>
              <?= h($c['kode_mk']) ?> — <?= h($c['nama_mk']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if ($sel_course): ?>

  <div class="grid grid-2">

    <!-- Mahasiswa terdaftar -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Terdaftar <span class="badge badge-mahasiswa"><?= count($enrolled) ?></span></span>
      </div>
      <?php if (!$enrolled): ?>
        <div class="empty-state"><p>Belum ada mahasiswa terdaftar.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama</th><th>NRP</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($enrolled as $mhs): ?>
            <tr>
              <td>
                <div class="fw-600"><?= h($mhs['full_name'] ?: $mhs['username']) ?></div>
                <div class="text-xs text-muted"><?= h($mhs['username']) ?></div>
              </td>
              <td class="text-sm"><?= h($mhs['nrp_nip'] ?: '—') ?></td>
              <td><span class="badge badge-<?= $mhs['is_active']?'active':'inactive' ?>"><?= $mhs['is_active']?'Aktif':'Nonaktif' ?></span></td>
              <td>
                <form method="POST" onsubmit="return confirm('Keluarkan mahasiswa ini?')">
                  <input type="hidden" name="action"    value="unenroll">
                  <input type="hidden" name="course_id" value="<?= $sel_course_id ?>">
                  <input type="hidden" name="user_id"   value="<?= $mhs['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Keluarkan</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Mahasiswa belum terdaftar -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Belum Terdaftar <span class="badge badge-inactive"><?= count($not_enrolled) ?></span></span>
      </div>
      <?php if (!$not_enrolled): ?>
        <div class="empty-state"><p>Semua mahasiswa aktif sudah terdaftar.</p></div>
      <?php else: ?>
      <form method="POST">
        <input type="hidden" name="action"    value="enroll_bulk">
        <input type="hidden" name="course_id" value="<?= $sel_course_id ?>">
        <div class="table-wrap">
          <table>
            <thead><tr><th><input type="checkbox" id="check-all" onchange="toggleAll(this)"></th><th>Nama</th><th>NRP</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($not_enrolled as $mhs): ?>
              <tr>
                <td><input type="checkbox" name="mhs_ids[]" value="<?= $mhs['id'] ?>" class="mhs-check"></td>
                <td>
                  <div class="fw-600"><?= h($mhs['full_name'] ?: $mhs['username']) ?></div>
                  <div class="text-xs text-muted"><?= h($mhs['username']) ?></div>
                </td>
                <td class="text-sm"><?= h($mhs['nrp_nip'] ?: '—') ?></td>
                <td>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action"    value="enroll">
                    <input type="hidden" name="course_id" value="<?= $sel_course_id ?>">
                    <input type="hidden" name="user_id"   value="<?= $mhs['id'] ?>">
                    <button type="submit" class="btn btn-success btn-sm">Tambahkan</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:var(--s-4);border-top:1px solid var(--border-2)">
          <button type="submit" class="btn btn-primary">Daftarkan yang Dipilih</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

  </div>

  <?php endif; ?>

</div>

<script>
function toggleAll(master) {
  document.querySelectorAll('.mhs-check').forEach(c => c.checked = master.checked);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
