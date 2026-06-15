<?php
/* ──────────────────────────────────────────────────────────────
   Kelola User — Dosen RBAC: Create, Read, Update, (soft) Delete
   ────────────────────────────────────────────────────────────── */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('dosen');

$conn    = Database::getInstance()->getConnection();
$msg     = '';
$msg_type = 'success';

/* ── POST: proses aksi ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    // Toggle aktif / nonaktif
    if ($action === 'toggle_active' && $user_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Status akun berhasil diperbarui.';
    }

    // Edit nama & email
    if ($action === 'edit' && $user_id > 0) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $nrp_nip   = trim($_POST['nrp_nip']  ?? '') ?: null;
        $role      = $_POST['role']            ?? '';

        if (!$full_name || !$email || !in_array($role, ['mahasiswa','dosen'], true)) {
            $msg      = 'Data tidak valid.';
            $msg_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg      = 'Format email tidak valid.';
            $msg_type = 'danger';
        } else {
            // Cek duplikat email (kecuali milik sendiri)
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->bind_param('si', $email, $user_id);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $msg      = 'Email sudah digunakan akun lain.';
                $msg_type = 'danger';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE users SET full_name=?, email=?, nrp_nip=?, role=? WHERE id=?"
                );
                $stmt->bind_param('ssssi', $full_name, $email, $nrp_nip, $role, $user_id);
                $stmt->execute();
                $stmt->close();
                $msg = 'Data pengguna berhasil diperbarui.';
            }
            $chk->close();
        }
    }

    // Reset password ke default "admin123"
    if ($action === 'reset_pass' && $user_id > 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Password direset ke: admin123';
    }

    // Hapus user (hard delete — hanya mahasiswa)
    if ($action === 'delete' && $user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'mahasiswa'");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Akun mahasiswa berhasil dihapus.';
    }
}

/* ── GET: ambil data user ───────────────────────────────────── */
$filter_role   = $_GET['role']   ?? '';
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filter_role && in_array($filter_role, ['mahasiswa','dosen'], true)) {
    $where[] = 'role = ?';
    $params[] = $filter_role;
    $types   .= 's';
}
if ($filter_status !== '') {
    $where[] = 'is_active = ?';
    $params[] = (int)$filter_status;
    $types   .= 'i';
}
if ($search) {
    $like     = "%$search%";
    $where[]  = '(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR nrp_nip LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}

$sql = "SELECT id, full_name, username, email, role, nrp_nip, tahun_masuk, is_active, created_at
        FROM users" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY role, created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

// Untuk form edit — ambil data user yang dipilih
$edit_user = null;
if (isset($_GET['edit'])) {
    $eid  = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT id, full_name, username, email, role, nrp_nip, is_active FROM users WHERE id = ?");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$page_title = 'Kelola User';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <div class="page-header d-flex justify-between align-center">
    <div>
      <h1>Kelola Pengguna</h1>
      <p>Manajemen akun mahasiswa &amp; dosen (RBAC)</p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- ── Form Edit User ────────────────────────────────────── -->
  <?php if ($edit_user): ?>
  <div class="card mb-3">
    <div class="card-header">
      <span class="card-title">Edit Pengguna: <?= h($edit_user['username']) ?></span>
      <a href="<?= BASE_URL ?>/dosen/kelola_user.php" class="btn btn-ghost btn-sm">Batal</a>
    </div>
    <form method="POST">
      <input type="hidden" name="action"  value="edit">
      <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
      <div class="form-row cols-2">
        <div class="form-group">
          <label>Nama Lengkap *</label>
          <input type="text" name="full_name" class="form-control" value="<?= h($edit_user['full_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" class="form-control" value="<?= h($edit_user['email']) ?>" required>
        </div>
      </div>
      <div class="form-row cols-2">
        <div class="form-group">
          <label>NRP / NIP</label>
          <input type="text" name="nrp_nip" class="form-control" value="<?= h($edit_user['nrp_nip'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role" class="form-control" required>
            <option value="mahasiswa" <?= $edit_user['role']==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
            <option value="dosen"     <?= $edit_user['role']==='dosen'    ?'selected':'' ?>>Dosen</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- ── Filter & Search ────────────────────────────────────── -->
  <div class="card mb-3">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="Cari nama / username / email..." value="<?= h($search) ?>">
      <select name="role" class="form-control">
        <option value="">Semua Role</option>
        <option value="mahasiswa" <?= $filter_role==='mahasiswa'?'selected':'' ?>>Mahasiswa</option>
        <option value="dosen"     <?= $filter_role==='dosen'    ?'selected':'' ?>>Dosen</option>
      </select>
      <select name="status" class="form-control">
        <option value="">Semua Status</option>
        <option value="1" <?= $filter_status==='1'?'selected':'' ?>>Aktif</option>
        <option value="0" <?= $filter_status==='0'?'selected':'' ?>>Nonaktif</option>
      </select>
      <button type="submit" class="btn btn-primary">Cari</button>
      <a href="<?= BASE_URL ?>/dosen/kelola_user.php" class="btn btn-ghost">Reset</a>
    </form>
  </div>

  <!-- ── Tabel User ─────────────────────────────────────────── -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Daftar Pengguna</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Lengkap</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>NRP / NIP</th>
            <th>Status</th>
            <th>Terdaftar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($u = $users->fetch_assoc()): ?>
          <tr>
            <td class="text-muted"><?= $u['id'] ?></td>
            <td class="fw-600"><?= h($u['full_name'] ?? $u['username']) ?></td>
            <td class="text-muted"><?= h($u['username']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td class="text-sm"><?= h($u['nrp_nip'] ?? '&mdash;') ?></td>
            <td>
              <span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>">
                <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td class="text-sm text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="td-actions">
                <a href="?edit=<?= $u['id'] ?><?= $search?"&q=".urlencode($search):'' ?>" class="btn btn-ghost btn-sm">Edit</a>

                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"  value="toggle_active">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                    <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </button>
                </form>

                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"  value="reset_pass">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"
                          onclick="return confirm('Reset password ke admin123?')">
                    Reset PW
                  </button>
                </form>

                <?php if ($u['role'] === 'mahasiswa'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          onclick="return confirm('Hapus permanen akun <?= h(addslashes($u['username'])) ?>?')">
                    Hapus
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
