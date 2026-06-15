<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

$msg = $err = '';

/* ── POST actions ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_mk') {
        $kode    = trim($_POST['kode_mk']  ?? '');
        $nama    = trim($_POST['nama_mk']  ?? '');
        $sks     = (int)($_POST['sks']     ?? 0);
        $sem     = (int)($_POST['semester']?? 0);
        $ta      = trim($_POST['tahun_akademik'] ?? '');
        $desc    = trim($_POST['deskripsi']    ?? '');

        if (!$kode || !$nama || $sks < 1) {
            $err = 'Kode MK, Nama MK, dan SKS wajib diisi.';
        } else {
            $chk = $conn->prepare("SELECT id FROM courses WHERE kode_mk=? AND dosen_id=?");
            $chk->bind_param('si', $kode, $uid);
            $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) {
                $err = 'Kode MK sudah digunakan.';
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO courses (dosen_id, kode_mk, nama_mk, sks, semester, tahun_akademik, deskripsi)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->bind_param('issiiis', $uid, $kode, $nama, $sks, $sem, $ta, $desc);
                $stmt->execute() ? $msg = 'Mata kuliah berhasil ditambahkan.' : $err = 'Gagal menyimpan.';
                $stmt->close();
            }
            $chk->close();
        }

    } elseif ($act === 'edit_mk') {
        $id   = (int)($_POST['mk_id'] ?? 0);
        $kode = trim($_POST['kode_mk'] ?? '');
        $nama = trim($_POST['nama_mk'] ?? '');
        $sks  = (int)($_POST['sks']    ?? 0);
        $sem  = (int)($_POST['semester']?? 0);
        $ta   = trim($_POST['tahun_akademik'] ?? '');
        $desc = trim($_POST['deskripsi']    ?? '');

        if (!$id || !$kode || !$nama || $sks < 1) {
            $err = 'Data tidak lengkap.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE courses SET kode_mk=?, nama_mk=?, sks=?, semester=?, tahun_akademik=?, deskripsi=?
                 WHERE id=? AND dosen_id=?"
            );
            $stmt->bind_param('ssiissii', $kode, $nama, $sks, $sem, $ta, $desc, $id, $uid);
            $stmt->execute() ? $msg = 'Mata kuliah berhasil diperbarui.' : $err = 'Gagal memperbarui.';
            $stmt->close();
        }

    } elseif ($act === 'delete_mk') {
        $id = (int)($_POST['mk_id'] ?? 0);
        // Only delete if no enrollments / materials — soft guard
        $chk = $conn->prepare("SELECT COUNT(*) AS c FROM enrollments WHERE course_id=?");
        $chk->bind_param('i', $id); $chk->execute();
        $cnt = $chk->get_result()->fetch_assoc()['c']; $chk->close();

        if ($cnt > 0) {
            $err = 'Tidak bisa dihapus: masih ada ' . $cnt . ' mahasiswa terdaftar.';
        } else {
            $stmt = $conn->prepare("DELETE FROM courses WHERE id=? AND dosen_id=?");
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute() ? $msg = 'Mata kuliah dihapus.' : $err = 'Gagal menghapus.';
            $stmt->close();
        }
    }
}

/* ── GET: load courses ────────────────────────────────────── */
$edit_mk = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $estmt = $conn->prepare("SELECT * FROM courses WHERE id=? AND dosen_id=?");
    $estmt->bind_param('ii', $eid, $uid); $estmt->execute();
    $edit_mk = $estmt->get_result()->fetch_assoc(); $estmt->close();
}

$all_mk = $conn->prepare(
    "SELECT c.*,
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id) AS total_mhs,
            (SELECT COUNT(*) FROM course_weeks cw WHERE cw.course_id=c.id) AS total_minggu
     FROM courses c WHERE c.dosen_id=? ORDER BY c.kode_mk"
);
$all_mk->bind_param('i', $uid); $all_mk->execute();
$courses = $all_mk->get_result()->fetch_all(MYSQLI_ASSOC); $all_mk->close();

$page_title = 'Kelola Mata Kuliah';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <div class="page-header d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:var(--s-4)">
    <div>
      <h1>Kelola Mata Kuliah</h1>
      <p>Tambah, edit, dan hapus mata kuliah Anda.</p>
    </div>
    <button onclick="Modal.open('modal-add')" class="btn btn-navy">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Tambah MK
    </button>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger" ><?= h($err) ?></div><?php endif; ?>

  <?php if ($edit_mk): ?>
  <!-- Inline edit form -->
  <div class="card mb-3" style="border:2px solid var(--blue)">
    <div class="card-header" style="background:var(--blue-mist)">
      <span class="card-title">Edit Mata Kuliah — <?= h($edit_mk['kode_mk']) ?></span>
      <a href="<?= BASE_URL ?>/dosen/kelola_mk.php" class="btn btn-ghost btn-sm">Batal</a>
    </div>
    <div class="card-body" style="padding:var(--s-5)">
      <form method="POST">
        <input type="hidden" name="action" value="edit_mk">
        <input type="hidden" name="mk_id"  value="<?= $edit_mk['id'] ?>">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Kode MK *</label>
            <input type="text" name="kode_mk" class="form-control" value="<?= h($edit_mk['kode_mk']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama MK *</label>
            <input type="text" name="nama_mk" class="form-control" value="<?= h($edit_mk['nama_mk']) ?>" required>
          </div>
        </div>
        <div class="form-row cols-3">
          <div class="form-group">
            <label class="form-label">SKS *</label>
            <input type="number" name="sks" class="form-control" value="<?= $edit_mk['sks'] ?>" min="1" max="6" required>
          </div>
          <div class="form-group">
            <label class="form-label">Semester</label>
            <input type="number" name="semester" class="form-control" value="<?= $edit_mk['semester'] ?>" min="1" max="14">
          </div>
          <div class="form-group">
            <label class="form-label">Tahun Akademik</label>
            <input type="text" name="tahun_akademik" class="form-control" value="<?= h($edit_mk['tahun_akademik']) ?>" placeholder="2024/2025 Genap">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <textarea name="deskripsi" class="form-control"><?= h($edit_mk['deskripsi']) ?></textarea>
        </div>
        <div class="d-flex justify-end gap-2">
          <a href="<?= BASE_URL ?>/dosen/kelola_mk.php" class="btn btn-ghost">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Table -->
  <div class="card">
    <div class="card-header"><span class="card-title">Daftar Mata Kuliah (<?= count($courses) ?>)</span></div>
    <?php if (!$courses): ?>
      <div class="empty-state"><p>Belum ada mata kuliah. Tambahkan mata kuliah pertama Anda.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Kode</th><th>Nama MK</th><th>SKS</th><th>Semester</th><th>TA</th><th>Mahasiswa</th><th>Minggu</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($courses as $mk): ?>
          <tr>
            <td class="fw-700 text-accent"><?= h($mk['kode_mk']) ?></td>
            <td>
              <div class="fw-600"><?= h($mk['nama_mk']) ?></div>
              <?php if ($mk['deskripsi']): ?>
                <div class="text-xs text-muted truncate" style="max-width:200px"><?= h($mk['deskripsi']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= $mk['sks'] ?></td>
            <td><?= $mk['semester'] ?: '—' ?></td>
            <td class="text-sm"><?= h($mk['tahun_akademik'] ?: '—') ?></td>
            <td><span class="badge badge-mahasiswa"><?= $mk['total_mhs'] ?> MHS</span></td>
            <td><span class="badge badge-mk"><?= $mk['total_minggu'] ?> Minggu</span></td>
            <td>
              <div class="td-actions">
                <a href="?edit=<?= $mk['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                <a href="<?= BASE_URL ?>/dosen/kelola_materi.php?course_id=<?= $mk['id'] ?>" class="btn btn-outline btn-sm">Materi</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus mata kuliah ini?')">
                  <input type="hidden" name="action" value="delete_mk">
                  <input type="hidden" name="mk_id" value="<?= $mk['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Modal Tambah MK -->
<div id="modal-add" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Tambah Mata Kuliah Baru</span>
      <button class="modal-close" onclick="Modal.close('modal-add')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_mk">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Kode MK *</label>
            <input type="text" name="kode_mk" class="form-control" placeholder="IF4321" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama MK *</label>
            <input type="text" name="nama_mk" class="form-control" placeholder="Nama mata kuliah" required>
          </div>
        </div>
        <div class="form-row cols-3">
          <div class="form-group">
            <label class="form-label">SKS *</label>
            <input type="number" name="sks" class="form-control" placeholder="3" min="1" max="6" required>
          </div>
          <div class="form-group">
            <label class="form-label">Semester</label>
            <input type="number" name="semester" class="form-control" placeholder="6" min="1" max="14">
          </div>
          <div class="form-group">
            <label class="form-label">Tahun Akademik</label>
            <input type="text" name="tahun_akademik" class="form-control" placeholder="2024/2025 Genap">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi <span class="text-muted">(opsional)</span></label>
          <textarea name="deskripsi" class="form-control" placeholder="Deskripsi singkat mata kuliah..."></textarea>
        </div>
        <div class="modal-footer" style="margin:0 calc(-1*var(--s-6)) calc(-1*var(--s-6));padding:var(--s-4) var(--s-6)">
          <button type="button" onclick="Modal.close('modal-add')" class="btn btn-ghost">Batal</button>
          <button type="submit" class="btn btn-primary">Tambah MK</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
