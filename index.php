<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$error   = '';
$success = '';
$tab     = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn   = Database::getInstance()->getConnection();
    $action = $_POST['action'] ?? '';

    /* ── LOGIN ─────────────────────────────────────────────── */
    if ($action === 'login') {
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password']  ?? '';

        if (!$uname || !$pass) {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $conn->prepare(
                "SELECT id, username, full_name, password, role, is_active
                 FROM users WHERE username = ? LIMIT 1"
            );
            $stmt->bind_param('s', $uname);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = 'Username tidak ditemukan.';
            } elseif (!$user['is_active']) {
                $error = 'Akun Anda dinonaktifkan. Hubungi dosen.';
            } elseif (!password_verify($pass, $user['password'])) {
                $error = 'Password salah.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['role']      = $user['role'];
                $url = $user['role'] === 'dosen'
                    ? BASE_URL . '/dosen/dashboard.php'
                    : BASE_URL . '/mahasiswa/dashboard.php';
                header("Location: $url"); exit();
            }
        }

    /* ── REGISTER ──────────────────────────────────────────── */
    } elseif ($action === 'register') {
        $tab       = 'register';
        $full_name = trim($_POST['full_name']     ?? '');
        $uname     = trim($_POST['username']      ?? '');
        $email     = trim($_POST['email']         ?? '');
        $pass      = $_POST['password']           ?? '';
        $role      = $_POST['role']               ?? '';
        $nrp_nip   = trim($_POST['nrp_nip']      ?? '') ?: null;
        $tahun     = intval($_POST['tahun_masuk'] ?? 0) ?: null;

        if (!$full_name || !$uname || !$email || !$pass || !$role) {
            $error = 'Semua field bertanda * wajib diisi.';
        } elseif (!in_array($role, ['mahasiswa','dosen'], true)) {
            $error = 'Role tidak valid.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($pass) < 8) {
            $error = 'Password minimal 8 karakter.';
        } else {
            $chk = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $chk->bind_param('ss', $uname, $email);
            $chk->execute(); $chk->store_result(); $dup = $chk->num_rows > 0; $chk->close();

            if ($dup) {
                $error = 'Username atau email sudah terdaftar.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO users (full_name, username, email, password, role, nrp_nip, tahun_masuk)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->bind_param('ssssssi', $full_name, $uname, $email, $hash, $role, $nrp_nip, $tahun);
                if ($stmt->execute()) {
                    $success = 'Akun berhasil dibuat. Silakan login.';
                    $tab     = 'login';
                } else {
                    $error = 'Terjadi kesalahan server.';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CAI &mdash; Classroom Artificial Intelligence</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/global.css">
  <script>const CAI_BASE = '<?= BASE_URL ?>';</script>
</head>
<body>

<div class="auth-page">

  <!-- ── Left branding panel ── -->
  <div class="auth-panel-left">
    <div class="auth-brand">
      <div class="auth-brand-logo">
        <div class="auth-brand-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <div class="auth-brand-name">C<span>AI</span></div>
      </div>
      <div class="auth-tagline">Classroom<br>Artificial Intelligence</div>
      <p class="auth-desc">Platform pembelajaran berbasis AI untuk mendukung kegiatan perkuliahan. Didukung CAI &mdash; asisten cerdas berbasis Gemini.</p>
      <div class="auth-features">
        <div class="auth-feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
          <span>Materi perkuliahan terstruktur per minggu</span>
        </div>
        <div class="auth-feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Tracking progres belajar otomatis</span>
        </div>
        <div class="auth-feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span>CAI — tanya jawab materi real-time</span>
        </div>
        <div class="auth-feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          <span>Validasi AI dan rekap nilai akhir</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Right form panel ── -->
  <div class="auth-panel-right">
    <div class="auth-form-box">
      <div class="auth-form-title">Selamat Datang</div>
      <div class="auth-form-sub">Masuk atau buat akun baru untuk melanjutkan.</div>

      <?php if ($error):   ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

      <div class="auth-tabs">
        <button class="auth-tab <?= $tab==='login'?'active':'' ?>"    onclick="switchTab('login')">Masuk</button>
        <button class="auth-tab <?= $tab==='register'?'active':'' ?>" onclick="switchTab('register')">Buat Akun</button>
      </div>

      <!-- ── LOGIN ────────────────────────────────────────── -->
      <div id="pane-login" class="tab-pane <?= $tab==='login'?'active':'' ?>">
        <form method="POST" autocomplete="on">
          <input type="hidden" name="action" value="login">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
          </div>
          <button type="submit" class="btn btn-navy btn-block btn-lg" style="margin-top:var(--s-2)">Masuk</button>
        </form>
        <p class="text-sm text-muted mt-2" style="text-align:center">
          Belum punya akun? <a href="#" onclick="switchTab('register');return false">Buat akun di sini</a>
        </p>
      </div>

      <!-- ── REGISTER ─────────────────────────────────────── -->
      <div id="pane-register" class="tab-pane <?= $tab==='register'?'active':'' ?>">
        <form method="POST" autocomplete="off">
          <input type="hidden" name="action" value="register">
          <div class="form-row cols-2">
            <div class="form-group">
              <label class="form-label">Nama Lengkap *</label>
              <input type="text" name="full_name" class="form-control" placeholder="Nama lengkap" required>
            </div>
            <div class="form-group">
              <label class="form-label">Username *</label>
              <input type="text" name="username" id="reg-username" class="form-control" placeholder="Username unik" required autocomplete="off">
              <span id="username-status" class="input-status"></span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" placeholder="email@kampus.ac.id" required>
          </div>
          <div class="form-row cols-2">
            <div class="form-group">
              <label class="form-label">Password * <span class="text-muted text-xs">(min. 8 kar.)</span></label>
              <input type="password" name="password" id="reg-password" class="form-control" placeholder="Min. 8 karakter" required oninput="updatePasswordStrength(this.value)">
              <div class="pw-strength">
                <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-fill"></div></div>
                <div class="pw-strength-text" id="pw-text"></div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Role *</label>
              <select name="role" class="form-control" required>
                <option value="">-- Pilih Role --</option>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="dosen">Dosen</option>
              </select>
            </div>
          </div>
          <div class="form-row cols-2">
            <div class="form-group">
              <label class="form-label">NRP / NIP <span class="text-muted text-xs">(opsional)</span></label>
              <input type="text" name="nrp_nip" class="form-control" placeholder="NRP atau NIP">
            </div>
            <div class="form-group">
              <label class="form-label">Tahun Masuk <span class="text-muted text-xs">(opsional)</span></label>
              <input type="number" name="tahun_masuk" class="form-control" placeholder="2024" min="2000" max="2099">
            </div>
          </div>
          <button type="submit" class="btn btn-navy btn-block btn-lg" style="margin-top:var(--s-2)">Buat Akun</button>
        </form>
        <p class="text-sm text-muted mt-2" style="text-align:center">
          Sudah punya akun? <a href="#" onclick="switchTab('login');return false">Masuk di sini</a>
        </p>
      </div>

      <p class="text-xs text-muted mt-3" style="text-align:center">
        &copy; <?= date('Y') ?> CAI &mdash; Classroom Artificial Intelligence
      </p>
    </div>
  </div>

</div>

<script src="<?= BASE_URL ?>assets/js/script.js" defer></script>
<script>
function updatePasswordStrength(val) {
  const fill = document.getElementById('pw-fill');
  const text = document.getElementById('pw-text');
  if (!fill) return;
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
  if (/[0-9]/.test(val))   score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    {w:'0%',   c:'#B91C1C', t:''},
    {w:'25%',  c:'#B91C1C', t:'Lemah'},
    {w:'50%',  c:'#B45309', t:'Cukup'},
    {w:'75%',  c:'#187DC2', t:'Kuat'},
    {w:'100%', c:'#15803D', t:'Sangat Kuat'},
  ];
  const l = levels[Math.min(score, 4)];
  fill.style.width     = val ? l.w : '0%';
  fill.style.background = l.c;
  text.textContent     = val ? l.t : '';
  text.style.color     = l.c;
}
</script>
</body>
</html>
