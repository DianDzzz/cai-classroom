<?php
// Caller sets $page_title before including this file.
$role      = $_SESSION['role']      ?? null;
$full_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? '');
$is_guest  = !isset($_SESSION['user_id']);

$current = basename($_SERVER['PHP_SELF']);
$cur_dir = basename(dirname($_SERVER['PHP_SELF']));
function isActive(string $file, string $dir = ''): string {
    global $current, $cur_dir;
    if ($dir && $cur_dir !== $dir) return '';
    return $current === $file ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'CAI') ?> &mdash; Classroom AI</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
  <script>const CAI_BASE = '<?= BASE_URL ?>';</script>
</head>
<body>

<?php if (!$is_guest): ?>
<nav class="navbar">
  <a href="<?= BASE_URL ?>/<?= $role === 'dosen' ? 'dosen' : 'mahasiswa' ?>/dashboard.php"
     class="nav-brand">C<span>AI</span></a>

  <div class="nav-links">
    <?php if ($role === 'dosen'): ?>
      <a href="<?= BASE_URL ?>/dosen/dashboard.php"         class="<?= isActive('dashboard.php','dosen') ?>">Dashboard</a>
      <a href="<?= BASE_URL ?>/dosen/kelola_mk.php"         class="<?= isActive('kelola_mk.php','dosen') ?>">Mata Kuliah</a>
      <a href="<?= BASE_URL ?>/dosen/kelola_materi.php"     class="<?= isActive('kelola_materi.php','dosen') ?>">Materi</a>
      <a href="<?= BASE_URL ?>/dosen/kelola_enrollment.php" class="<?= isActive('kelola_enrollment.php','dosen') ?>">Enrollment</a>
      <a href="<?= BASE_URL ?>/dosen/validasi_ai.php"       class="<?= isActive('validasi_ai.php','dosen') ?>">Validasi AI</a>
      <a href="<?= BASE_URL ?>/dosen/input_nilai.php"       class="<?= isActive('input_nilai.php','dosen') ?>">Input Nilai</a>
      <a href="<?= BASE_URL ?>/dosen/kelola_user.php"       class="<?= isActive('kelola_user.php','dosen') ?>">Kelola User</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php"   class="<?= isActive('dashboard.php','mahasiswa') ?>">Dashboard</a>
      <a href="<?= BASE_URL ?>/mahasiswa/cetak_nilai.php" class="<?= isActive('cetak_nilai.php','mahasiswa') ?>">Nilai Saya</a>
    <?php endif; ?>
  </div>

  <div class="nav-user">
    <div class="user-chip">
      <span class="user-role"><?= $role === 'dosen' ? 'Dosen' : 'MHS' ?></span>
      <span class="user-name"><?= htmlspecialchars($full_name) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">Keluar</a>
  </div>
</nav>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/script.js" defer></script>
