/* ════════════════════════════════════════════════════════════
   CAI — Core JavaScript
   ════════════════════════════════════════════════════════════ */

/* ── Toast ───────────────────────────────────────────────────── */
const Toast = {
  container: null,
  _ensure() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  show(message, type = 'info', duration = 3500) {
    this._ensure();
    const icons = {
      success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>`,
      danger:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
      warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
      info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
    };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span><span>${message}</span>`;
    this.container.appendChild(el);
    setTimeout(() => {
      el.classList.add('out');
      el.addEventListener('animationend', () => el.remove());
    }, duration);
  }
};

/* ── Modal ───────────────────────────────────────────────────── */
const Modal = {
  open(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  close(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay.open').forEach(el => {
      el.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
};

/* Close modal on overlay click */
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) Modal.closeAll();
  if (e.target.dataset.closeModal) Modal.close(e.target.dataset.closeModal);
});

/* ── Auth tabs ───────────────────────────────────────────────── */
function switchTab(name) {
  document.querySelectorAll('.auth-tab').forEach((b, i) => {
    b.classList.toggle('active', (i === 0) === (name === 'login'));
  });
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  const pane = document.getElementById('pane-' + name);
  if (pane) pane.classList.add('active');
}

/* ── Username availability check ────────────────────────────── */
function initUsernameCheck() {
  const input  = document.getElementById('reg-username');
  const status = document.getElementById('username-status');
  if (!input || !status) return;

  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const val = input.value.trim();
    if (val.length < 3) { status.textContent = ''; return; }
    status.textContent = 'Memeriksa...';
    status.className = 'input-status';
    timer = setTimeout(async () => {
      try {
        const r = await fetch(`${CAI_BASE}/api/check_username.php?q=${encodeURIComponent(val)}`);
        const d = await r.json();
        if (d.available) {
          status.textContent = 'Username tersedia';
          status.className = 'input-status ok';
        } else {
          status.textContent = 'Username sudah digunakan';
          status.className = 'input-status err';
        }
      } catch { status.textContent = ''; }
    }, 500);
  });
}

/* ── Accordion ───────────────────────────────────────────────── */
function initAccordion() {
  document.querySelectorAll('.accordion-trigger').forEach(trigger => {
    trigger.addEventListener('click', () => {
      const item = trigger.closest('.accordion-item');
      const body = item.querySelector('.accordion-body');
      const isOpen = item.classList.contains('open');
      item.classList.toggle('open', !isOpen);
      if (body) body.classList.toggle('open', !isOpen);

      // Update context for chat
      if (!isOpen) {
        const title = trigger.querySelector('.accordion-title')?.textContent || '';
        const week  = trigger.querySelector('.week-num')?.textContent || '';
        updateChatContext(`Minggu ${week}: ${title}`);
      }
    });
  });
}

/* ── Chatbox (CAI) ───────────────────────────────────────── */
let chatContext = 'Materi umum mata kuliah';
let currentChatId = null;

function updateChatContext(ctx) {
  chatContext = ctx;
  const el = document.getElementById('chat-context-label');
  if (el) el.textContent = ctx;
}

function toggleChat() {
  const box = document.getElementById('chatbox');
  if (!box) return;
  box.classList.toggle('open');
  if (box.classList.contains('open')) {
    const input = box.querySelector('.chat-input');
    if (input) setTimeout(() => input.focus(), 200);
  }
}

function closeChat() {
  document.getElementById('chatbox')?.classList.remove('open');
}

async function sendChat() {
  const input = document.getElementById('chat-msg-input');
  const msgs  = document.getElementById('chat-messages');
  if (!input || !msgs || !input.value.trim()) return;

  const text = input.value.trim();
  input.value = '';

  // User bubble
  msgs.innerHTML += `
    <div class="chat-msg user">
      <div class="msg-bubble">${escapeHtml(text)}</div>
    </div>`;

  // Typing indicator
  const typingId = 'typing-' + Date.now();
  msgs.innerHTML += `
    <div class="chat-msg ai" id="${typingId}">
      <div class="msg-bubble" style="padding:.5rem .75rem">
        <div class="typing-indicator">
          <div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>
        </div>
      </div>
    </div>`;
  msgs.scrollTop = msgs.scrollHeight;

  try {
    const courseId = document.getElementById('current-course-id')?.value || '';
    const minggu   = document.getElementById('active-week')?.value || '';

    const r = await fetch(`${CAI_BASE}/api/chat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, context: chatContext, course_id: courseId, minggu_ke: minggu }),
    });
    const d = await r.json();

    document.getElementById(typingId)?.remove();

    if (d.success) {
      currentChatId = d.chat_id;
      msgs.innerHTML += `
        <div class="chat-msg ai" data-chat-id="${d.chat_id}">
          <div>
            <div class="msg-bubble">${escapeHtml(d.answer).replace(/\n/g,'<br>')}</div>
            <div class="msg-feedback">
              <button class="feedback-btn" onclick="sendFeedback(${d.chat_id},'up',this)">&#128077; Membantu</button>
              <button class="feedback-btn" onclick="sendFeedback(${d.chat_id},'down',this)">&#128078; Kurang tepat</button>
            </div>
          </div>
        </div>`;
    } else {
      document.getElementById(typingId)?.remove();
      msgs.innerHTML += `<div class="chat-msg ai"><div class="msg-bubble text-danger">${escapeHtml(d.error || 'Error')}</div></div>`;
    }
  } catch {
    document.getElementById(typingId)?.remove();
    msgs.innerHTML += `<div class="chat-msg ai"><div class="msg-bubble text-danger">Koneksi gagal. Coba lagi.</div></div>`;
  }
  msgs.scrollTop = msgs.scrollHeight;
}

async function sendFeedback(chatId, type, btn) {
  const parent = btn.closest('.msg-feedback');
  parent?.querySelectorAll('.feedback-btn').forEach(b => b.classList.remove('active-up','active-down'));
  btn.classList.add(type === 'up' ? 'active-up' : 'active-down');

  try {
    await fetch(`${CAI_BASE}/api/feedback.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat_id: chatId, feedback: type }),
    });
    Toast.show(type === 'up' ? 'Terima kasih atas feedback positifnya!' : 'Feedback diterima. Dosen akan meninjau jawaban ini.', 'success');
  } catch { /* silent */ }
}

/* ── Progress checkbox ───────────────────────────────────────── */
async function saveProgress(checkbox, courseId, minggu) {
  const completed = checkbox.checked ? 1 : 0;
  const label = checkbox.closest('.week-complete-row')?.querySelector('label');
  if (label) label.textContent = completed ? 'Minggu ini selesai dipelajari' : 'Tandai minggu ini selesai';

  const item = checkbox.closest('.accordion-item');
  const badge = item?.querySelector('.accordion-badge-done');

  try {
    const r = await fetch(`${CAI_BASE}/api/save_progress.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ course_id: courseId, minggu_ke: minggu, is_completed: completed }),
    });
    const d = await r.json();
    if (d.success) {
      Toast.show(completed ? 'Progres disimpan!' : 'Progres dibatalkan.', completed ? 'success' : 'info');
      if (badge) badge.style.display = completed ? 'block' : 'none';
      updateOverallProgress(courseId);
    } else {
      Toast.show('Gagal menyimpan progres.', 'danger');
      checkbox.checked = !checkbox.checked;
    }
  } catch {
    Toast.show('Koneksi gagal.', 'danger');
    checkbox.checked = !checkbox.checked;
  }
}

async function updateOverallProgress(courseId) {
  try {
    const r = await fetch(`${CAI_BASE}/api/save_progress.php?get_progress=1&course_id=${courseId}`);
    const d = await r.json();
    if (d.success) {
      const bar   = document.getElementById('overall-progress-bar');
      const label = document.getElementById('overall-progress-label');
      if (bar)   bar.style.width = d.percent + '%';
      if (label) label.textContent = `${d.done}/${d.total} minggu (${d.percent}%)`;
    }
  } catch { /* silent */ }
}

/* ── File type radio toggle ─────────────────────────────────── */
function toggleFileType(type) {
  const fileArea = document.getElementById('file-upload-area');
  const linkArea = document.getElementById('link-url-area');
  if (!fileArea || !linkArea) return;
  if (type === 'link') {
    fileArea.style.display = 'none';
    linkArea.style.display = 'block';
  } else {
    fileArea.style.display = 'block';
    linkArea.style.display = 'none';
  }
}

/* ── Upload zone click ───────────────────────────────────────── */
function initUploadZone() {
  document.querySelectorAll('.upload-zone').forEach(zone => {
    zone.addEventListener('click', () => zone.querySelector('input[type=file]')?.click());
    zone.querySelector('input[type=file]')?.addEventListener('change', function() {
      const p = zone.querySelector('p');
      if (p && this.files[0]) p.textContent = this.files[0].name;
    });
  });
}

/* ── Utility ─────────────────────────────────────────────────── */
function escapeHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* Chat enter key */
document.addEventListener('keydown', e => {
  if (e.target.classList.contains('chat-input') && e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendChat();
  }
});

/* ── Init on DOM ready ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initUsernameCheck();
  initAccordion();
  initUploadZone();
});
