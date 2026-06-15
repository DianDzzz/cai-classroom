<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$uid  = (int)$_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

$msg      = '';
$msg_type = 'success';

/* ── POST: proses aksi ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Tambah materi */
    if ($action === 'add_material') {
        $course_id = intval($_POST['course_id'] ?? 0);
        $minggu_ke = intval($_POST['minggu_ke'] ?? 0);
        $judul     = trim($_POST['judul']       ?? '');
        $jenis     = $_POST['jenis_file']       ?? '';

        if (!in_array($jenis, ['pdf','ppt','link'], true) || !$judul || !$course_id || !$minggu_ke) {
            $msg = 'Data tidak lengkap.'; $msg_type = 'danger';
        } else {
            // Pastikan course_week ada
            $stmt = $conn->prepare("SELECT id FROM course_weeks WHERE course_id=? AND minggu_ke=?");
            $stmt->bind_param('ii', $course_id, $minggu_ke);
            $stmt->execute();
            $week = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$week) {
                $ins = $conn->prepare("INSERT INTO course_weeks (course_id, minggu_ke, judul_minggu) VALUES (?,?,?)");
                $title = "Minggu $minggu_ke";
                $ins->bind_param('iis', $course_id, $minggu_ke, $title);
                $ins->execute();
                $week_id = $conn->insert_id;
                $ins->close();
            } else {
                $week_id = $week['id'];
            }

            // Update judul week jika diisi
            $judul_minggu = trim($_POST['judul_minggu'] ?? '');
            if ($judul_minggu) {
                $upd = $conn->prepare("UPDATE course_weeks SET judul_minggu=? WHERE id=?");
                $upd->bind_param('si', $judul_minggu, $week_id);
                $upd->execute();
                $upd->close();
            }

            $link_atau_file = '';
            if ($jenis === 'link') {
                $link_atau_file = trim($_POST['url_link'] ?? '');
                if (!$link_atau_file) { $msg = 'URL link wajib diisi.'; $msg_type = 'danger'; goto done; }
            } else {
                // File upload
                $file = $_FILES['file_upload'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    $msg = 'File gagal diunggah.'; $msg_type = 'danger'; goto done;
                }
                $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allow = $jenis === 'pdf' ? ['pdf'] : ['ppt','pptx'];
                if (!in_array($ext, $allow, true)) {
                    $msg = "File harus berformat " . implode('/', $allow); $msg_type = 'danger'; goto done;
                }
                $fname = uniqid('mat_') . '.' . $ext;
                $dest  = __DIR__ . '/../uploads/' . $fname;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $msg = 'Gagal menyimpan file.'; $msg_type = 'danger'; goto done;
                }
                $link_atau_file = $fname;
            }

            $stmt = $conn->prepare("INSERT INTO materials (week_id, judul, jenis_file, link_atau_file) VALUES (?,?,?,?)");
            $stmt->bind_param('isss', $week_id, $judul, $jenis, $link_atau_file);
            $stmt->execute();
            $stmt->close();
            $msg = 'Materi berhasil ditambahkan.';
        }
    }

    /* Edit judul minggu */
    if ($action === 'edit_week') {
        $week_id      = intval($_POST['week_id'] ?? 0);
        $judul_minggu = trim($_POST['judul_minggu'] ?? '');
        $deskripsi    = trim($_POST['deskripsi_minggu'] ?? '');
        if ($week_id && $judul_minggu) {
            $stmt = $conn->prepare("UPDATE course_weeks SET judul_minggu=?, deskripsi_minggu=? WHERE id=?");
            $stmt->bind_param('ssi', $judul_minggu, $deskripsi, $week_id);
            $stmt->execute(); $stmt->close();
            $msg = 'Judul minggu diperbarui.';
        }
    }

    /* Hapus materi */
    if ($action === 'delete_material') {
        $mat_id = intval($_POST['material_id'] ?? 0);
        if ($mat_id) {
            $stmt = $conn->prepare("SELECT link_atau_file, jenis_file FROM materials WHERE id=?");
            $stmt->bind_param('i', $mat_id);
            $stmt->execute();
            $mat = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($mat && $mat['jenis_file'] !== 'link') {
                @unlink(__DIR__ . '/../uploads/' . $mat['link_atau_file']);
            }
            $del = $conn->prepare("DELETE FROM materials WHERE id=?");
            $del->bind_param('i', $mat_id);
            $del->execute(); $del->close();
            $msg = 'Materi dihapus.';
        }
    }
    done:
}

/* ── Ambil mata kuliah dosen ─────────────────────────────── */
$sel_course = intval($_GET['course_id'] ?? 0);
$courses_r  = $conn->prepare("SELECT id, kode_mk, nama_mk FROM courses WHERE dosen_id = ? ORDER BY kode_mk");
$courses_r->bind_param('i', $uid);
$courses_r->execute();
$courses = $courses_r->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_r->close();

if (!$sel_course && $courses) $sel_course = $courses[0]['id'];

/* ── Ambil weeks + materi ────────────────────────────────── */
$weeks = [];
if ($sel_course) {
    $stmt = $conn->prepare(
        "SELECT cw.id, cw.minggu_ke, cw.judul_minggu, cw.deskripsi_minggu
         FROM course_weeks cw
         WHERE cw.course_id = ?
         ORDER BY cw.minggu_ke"
    );
    $stmt->bind_param('i', $sel_course);
    $stmt->execute();
    $weeks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Materials per week
    if ($weeks) {
        $ids = implode(',', array_column($weeks, 'id'));
        $mats_r = $conn->query("SELECT * FROM materials WHERE week_id IN ($ids) ORDER BY id");
        $mats_by_week = [];
        while ($m = $mats_r->fetch_assoc()) $mats_by_week[$m['week_id']][] = $m;
        foreach ($weeks as &$w) $w['materials'] = $mats_by_week[$w['id']] ?? [];
        unset($w);
    }
}

$page_title = 'Kelola Materi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:var(--s-4)">
    <div>
      <h1>Kelola Materi</h1>
      <p>Upload materi per minggu untuk setiap mata kuliah</p>
    </div>
    <div class="d-flex gap-1">
      <?php if ($sel_course > 0): ?>
      <a href="<?= BASE_URL ?>/api/export_pdf.php?type=progress&course_id=<?= $sel_course ?>" target="_blank" class="btn btn-danger btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Export Progres PDF
      </a>
      <?php endif; ?>
      <button class="btn btn-primary" onclick="Modal.open('modal-add')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Materi
      </button>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Course selector -->
  <div class="card mb-3">
    <form method="GET" class="d-flex align-center gap-2" style="flex-wrap:wrap">
      <label class="form-label mb-0 fw-600" style="white-space:nowrap">Mata Kuliah:</label>
      <select name="course_id" class="form-control" style="max-width:320px" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id']==$sel_course?'selected':'' ?>>
          <?= h($c['kode_mk']) ?> &mdash; <?= h($c['nama_mk']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (!$courses): ?>
    <div class="card"><div class="empty-state"><p>Anda belum memiliki mata kuliah. Tambahkan mata kuliah terlebih dahulu.</p></div></div>
  <?php elseif (!$weeks): ?>
    <div class="card">
      <div class="empty-state">
        <p>Belum ada minggu perkuliahan. Tambahkan materi pertama untuk mulai membuat jadwal minggu.</p>
      </div>
    </div>
  <?php else: ?>

  <!-- Accordion weeks -->
  <div class="accordion">
    <?php foreach ($weeks as $w): ?>
    <div class="accordion-item" id="week-<?= $w['id'] ?>">
      <div class="accordion-trigger">
        <span class="week-num"><?= $w['minggu_ke'] ?></span>
        <span class="accordion-info">
          <span class="accordion-title"><?= h($w['judul_minggu'] ?? "Minggu {$w['minggu_ke']}") ?></span>
          <span class="accordion-sub"><?= count($w['materials']) ?> materi</span>
        </span>
        <div class="d-flex align-center gap-1" onclick="event.stopPropagation()">
          <button class="btn btn-ghost btn-sm" type="button"
            onclick="openEditWeek(<?= $w['id'] ?>, <?= $w['minggu_ke'] ?>, '<?= addslashes(h($w['judul_minggu'] ?? '')) ?>', '<?= addslashes(h($w['deskripsi_minggu'] ?? '')) ?>')">
            Edit Judul
          </button>
          <button class="btn btn-primary btn-sm" type="button"
            onclick="openAddForWeek(<?= $sel_course ?>, <?= $w['minggu_ke'] ?>)">
            + Materi
          </button>
        </div>
        <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </div>

      <div class="accordion-body">
        <div class="accordion-content">
          <div class="accordion-content-inner">
            <?php if ($w['deskripsi_minggu']): ?>
            <p class="text-sm text-2 mb-2"><?= h($w['deskripsi_minggu']) ?></p>
            <?php endif; ?>

            <?php if ($w['materials']): ?>
            <div class="material-list">
              <?php foreach ($w['materials'] as $m): ?>
              <div class="material-item">
                <span class="material-icon <?= h($m['jenis_file']) ?>"><?= strtoupper($m['jenis_file']) ?></span>
                <span class="material-name">
                  <?= h($m['judul']) ?>
                  <small><?= h($m['link_atau_file']) ?></small>
                </span>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="delete_material">
                  <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="course_id" value="<?= $sel_course ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"
                    onclick="return confirm('Hapus materi ini?')" style="color:var(--danger)">Hapus</button>
                </form>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-muted">Belum ada materi. Klik "+ Materi" untuk menambahkan.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<!-- ── Modal Tambah Materi ───────────────────────────────────── -->
<div class="modal-overlay" id="modal-add">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Tambah Materi</span>
      <button class="modal-close" data-close-modal="modal-add">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="action" value="add_material">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Mata Kuliah</label>
            <select name="course_id" id="add-course-id" class="form-control" required>
              <?php foreach ($courses as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $c['id']==$sel_course?'selected':'' ?>>
                <?= h($c['kode_mk']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Minggu Ke</label>
            <select name="minggu_ke" id="add-minggu" class="form-control" required>
              <?php for ($i = 1; $i <= 16; $i++): ?>
              <option value="<?= $i ?>">Minggu <?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Judul Minggu <span class="text-muted">(opsional, untuk update judul)</span></label>
          <input type="text" name="judul_minggu" class="form-control" placeholder="Contoh: Pengantar HTML5">
        </div>
        <div class="form-group">
          <label class="form-label">Judul Materi *</label>
          <input type="text" name="judul" class="form-control" placeholder="Judul materi" required>
        </div>
        <div class="form-group">
          <label class="form-label">Jenis File *</label>
          <div class="d-flex gap-2" style="margin-top:var(--s-2)">
            <?php foreach (['pdf'=>'PDF','ppt'=>'PowerPoint','link'=>'Tautan URL'] as $val=>$lbl): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:var(--text-sm)">
              <input type="radio" name="jenis_file" value="<?= $val ?>"
                     <?= $val==='pdf'?'checked':'' ?>
                     onchange="toggleFileType(this.value)">
              <?= $lbl ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div id="file-upload-area" class="form-group">
          <label class="form-label">Upload File *</label>
          <div class="upload-zone">
            <input type="file" name="file_upload" accept=".pdf,.ppt,.pptx">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--muted);margin:0 auto">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <p>Klik atau seret file ke sini</p>
          </div>
        </div>
        <div id="link-url-area" class="form-group" style="display:none">
          <label class="form-label">URL Tautan *</label>
          <input type="url" name="url_link" class="form-control" placeholder="https://...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="modal-add">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Materi</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal Edit Judul Minggu ───────────────────────────────── -->
<div class="modal-overlay" id="modal-edit-week">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Edit Judul Minggu</span>
      <button class="modal-close" data-close-modal="modal-edit-week">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="edit_week">
        <input type="hidden" name="course_id" value="<?= $sel_course ?>">
        <input type="hidden" name="week_id" id="edit-week-id">
        <div class="form-group">
          <label class="form-label">Judul Minggu *</label>
          <input type="text" name="judul_minggu" id="edit-week-title" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi Minggu</label>
          <textarea name="deskripsi_minggu" id="edit-week-desc" class="form-control" rows="4"
                    placeholder="Penjelasan singkat tentang materi minggu ini"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="modal-edit-week">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddForWeek(courseId, minggu) {
  document.getElementById('add-course-id').value = courseId;
  document.getElementById('add-minggu').value = minggu;
  Modal.open('modal-add');
}
function openEditWeek(id, minggu, title, desc) {
  document.getElementById('edit-week-id').value    = id;
  document.getElementById('edit-week-title').value = title;
  document.getElementById('edit-week-desc').value  = desc;
  Modal.open('modal-edit-week');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
