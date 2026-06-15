<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('mahasiswa');

$uid     = (int)$_SESSION['user_id'];
$cid     = intval($_GET['id'] ?? 0);
$conn    = Database::getInstance()->getConnection();

// Verifikasi enrollment
$stmt = $conn->prepare(
    "SELECT c.id, c.kode_mk, c.nama_mk, c.sks, c.deskripsi, u.full_name AS dosen_nama
     FROM courses c
     JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
     JOIN users u ON u.id = c.dosen_id
     WHERE c.id = ?"
);
$stmt->bind_param('ii', $uid, $cid);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header('Location: ' . BASE_URL . '/mahasiswa/dashboard.php'); exit();
}

// Ambil semua minggu + materi + progres
$stmt = $conn->prepare(
    "SELECT cw.id AS week_id, cw.minggu_ke, cw.judul_minggu, cw.deskripsi_minggu,
            COALESCE(p.is_completed, 0) AS is_completed
     FROM course_weeks cw
     LEFT JOIN progress p ON p.course_id = cw.course_id AND p.minggu_ke = cw.minggu_ke AND p.user_id = ?
     WHERE cw.course_id = ?
     ORDER BY cw.minggu_ke"
);
$stmt->bind_param('ii', $uid, $cid);
$stmt->execute();
$weeks_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Materi per minggu
$week_ids = array_column($weeks_raw, 'week_id');
$materials_by_week = [];
if ($week_ids) {
    $in = implode(',', array_map('intval', $week_ids));
    $mats = $conn->query("SELECT * FROM materials WHERE week_id IN ($in) ORDER BY id");
    while ($m = $mats->fetch_assoc()) $materials_by_week[$m['week_id']][] = $m;
}

$total_weeks = count($weeks_raw);
$done_weeks  = count(array_filter($weeks_raw, fn($w) => $w['is_completed']));
$pct         = $total_weeks > 0 ? round($done_weeks / $total_weeks * 100) : 0;

$page_title = $course['nama_mk'];
require_once __DIR__ . '/../includes/header.php';
?>
<input type="hidden" id="current-course-id" value="<?= $cid ?>">
<input type="hidden" id="active-week" value="">

<div class="container">

  <!-- ── Course Header ─────────────────────────────────────── -->
  <div class="card mb-3">
    <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:var(--s-4)">
      <div>
        <div class="d-flex align-center gap-1 mb-1">
          <span class="badge badge-link"><?= h($course['kode_mk']) ?></span>
          <span class="text-sm text-muted"><?= $course['sks'] ?> SKS</span>
        </div>
        <h1 style="font-size:var(--text-xl);font-weight:700"><?= h($course['nama_mk']) ?></h1>
        <p class="text-sm text-muted mt-1"><?= h($course['dosen_nama']) ?></p>
        <?php if ($course['deskripsi']): ?>
        <p class="text-sm text-2 mt-2" style="max-width:600px"><?= h($course['deskripsi']) ?></p>
        <?php endif; ?>
      </div>
      <div style="text-align:right;min-width:160px">
        <div style="font-size:var(--text-3xl);font-weight:800;color:var(--accent);line-height:1"><?= $pct ?>%</div>
        <div class="text-xs text-muted mt-1" style="text-transform:uppercase;letter-spacing:.06em">Progres Belajar</div>
        <div class="progress-bar-bg mt-2" style="height:6px">
          <div class="progress-bar <?= $pct===100?'complete':'' ?>"
               id="overall-progress-bar" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="text-xs text-muted mt-1" id="overall-progress-label">
          <?= $done_weeks ?>/<?= $total_weeks ?> minggu
        </div>
      </div>
    </div>
    <div class="mt-2" style="padding-top:var(--s-4);border-top:1px solid var(--border)">
      <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="btn btn-ghost btn-sm">
        &larr; Kembali ke Dashboard
      </a>
    </div>
  </div>

  <!-- ── Accordion Minggu ──────────────────────────────────── -->
  <div class="section-label">Materi Perkuliahan</div>
  <div class="accordion">
    <?php foreach ($weeks_raw as $week):
      $wid   = $week['week_id'];
      $mats  = $materials_by_week[$wid] ?? [];
      $done  = (bool)$week['is_completed'];
    ?>
    <div class="accordion-item" id="week-item-<?= $week['minggu_ke'] ?>">
      <button class="accordion-trigger" type="button">
        <span class="week-num"><?= $week['minggu_ke'] ?></span>
        <span class="accordion-info">
          <span class="accordion-title"><?= h($week['judul_minggu'] ?? "Minggu {$week['minggu_ke']}") ?></span>
          <span class="accordion-sub">
            <?= count($mats) ?> materi
            <?php if ($done): ?>&nbsp;&bull;&nbsp;<span style="color:var(--success)">Selesai</span><?php endif; ?>
          </span>
        </span>
        <?php if ($done): ?>
          <span class="accordion-badge-done"></span>
        <?php else: ?>
          <span class="accordion-badge-done" style="display:none"></span>
        <?php endif; ?>
        <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </button>

      <div class="accordion-body">
        <div class="accordion-content">
          <div class="accordion-content-inner">

            <?php if ($week['deskripsi_minggu']): ?>
            <p class="text-sm text-2 mb-2" style="line-height:1.7"><?= h($week['deskripsi_minggu']) ?></p>
            <?php endif; ?>

            <?php if ($mats): ?>
            <div class="section-label" style="margin-top:var(--s-4)">Materi</div>
            <div class="material-list">
              <?php foreach ($mats as $m):
                $type = $m['jenis_file'];
                $href = $type === 'link' ? $m['link_atau_file'] : BASE_URL . '/uploads/' . basename($m['link_atau_file']);
                $target = $type === 'link' ? '_blank' : '_blank';
              ?>
              <a href="<?= h($href) ?>" target="<?= $target ?>" class="material-item" style="text-decoration:none;color:inherit">
                <span class="material-icon <?= h($type) ?>">
                  <?= strtoupper($type) ?>
                </span>
                <span class="material-name">
                  <?= h($m['judul']) ?>
                  <small><?= $type === 'link' ? 'Tautan eksternal' : strtoupper($type) . ' &mdash; klik untuk buka' ?></small>
                </span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted);flex-shrink:0">
                  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
              </a>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:var(--s-6)">
              <p>Belum ada materi untuk minggu ini.</p>
            </div>
            <?php endif; ?>

            <!-- Checkbox progres -->
            <div class="week-complete-row mt-2"
                 onclick="document.getElementById('chk-<?= $week['minggu_ke'] ?>').click()">
              <input type="checkbox"
                     id="chk-<?= $week['minggu_ke'] ?>"
                     <?= $done ? 'checked' : '' ?>
                     onclick="event.stopPropagation();saveProgress(this,<?= $cid ?>,<?= $week['minggu_ke'] ?>)">
              <label for="chk-<?= $week['minggu_ke'] ?>" onclick="event.stopPropagation()">
                <?= $done ? 'Minggu ini selesai dipelajari' : 'Tandai minggu ini selesai' ?>
              </label>
            </div>

          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!$weeks_raw): ?>
    <div class="card">
      <div class="empty-state">
        <p>Belum ada materi minggu untuk mata kuliah ini.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /container -->

<!-- ── Sevi AI Chatbox ────────────────────────────────────── -->
<button class="fab" onclick="toggleChat()" title="Tanya Sevi AI">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
</button>

<div class="chatbox" id="chatbox">
  <div class="chat-header">
    <div class="chat-avatar">AI</div>
    <div class="chat-header-info">
      <h4>Sevi AI</h4>
      <div class="chat-context-badge">
        <span class="chat-context-dot"></span>
        <span id="chat-context-label"><?= h($course['nama_mk']) ?></span>
      </div>
    </div>
    <button class="chat-close" onclick="closeChat()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>

  <div class="chat-messages" id="chat-messages">
    <div class="chat-msg ai">
      <div class="msg-bubble">
        Halo! Saya <strong>Sevi AI</strong>, asisten belajar Anda untuk mata kuliah <strong><?= h($course['nama_mk']) ?></strong>.
        Buka salah satu minggu lalu tanyakan apa saja tentang materi tersebut!
      </div>
    </div>
  </div>

  <div class="chat-input-row">
    <input class="chat-input" id="chat-msg-input" type="text" placeholder="Tanya tentang materi ini...">
    <button class="chat-send" onclick="sendChat()" title="Kirim">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>
</div>

<script>
// Set accordion to update chat context on open
document.querySelectorAll('.accordion-trigger').forEach(function(trigger) {
  trigger.addEventListener('click', function() {
    var item = trigger.closest('.accordion-item');
    if (!item.classList.contains('open')) {
      var weekNum = trigger.querySelector('.week-num').textContent;
      var title   = trigger.querySelector('.accordion-title').textContent;
      document.getElementById('active-week').value = weekNum;
      updateChatContext('Minggu ' + weekNum + ': ' + title);
    }
  });
}, true);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
