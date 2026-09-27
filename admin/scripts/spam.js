/* spam.php — список ЛС + модалка просмотра (контент из spam_message.php) */
(function () {
  'use strict';

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  // ---------- Toast ----------
  function showToast(msg) {
    const toastEl = document.getElementById('copyToast');
    if (!toastEl || !window.bootstrap) { console.log(msg); return; }
    const text = toastEl.querySelector('.sp-toast-text') || toastEl.querySelector('.toast-body');
    text.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 1400 }).show();
  }
  window.showToast = showToast;

  // ---------- Загрузка сообщения ----------
  let currentRequest = 0;

  function loadMessage(pmid, subject) {
    const modal   = document.getElementById('msgModal');
    const titleEl = document.getElementById('msgModalTitle');
    const bodyEl  = document.getElementById('msgModalBody');
    if (!modal || !titleEl || !bodyEl) return;

    const endpoint = modal.dataset.endpoint || 'spam_message.php';
    const reqId = ++currentRequest; // защита от гонки при быстром переключении

    titleEl.textContent = subject || '(no subject)';
    bodyEl.innerHTML =
      '<div class="sp-loading"><div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>' +
      '<span>Loading message…</span></div>';

    fetch(endpoint + '?id=' + encodeURIComponent(pmid), { credentials: 'same-origin' })
      .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
      .then((html) => {
        if (reqId !== currentRequest) return;
        bodyEl.innerHTML = html;
        initTooltips(bodyEl);
      })
      .catch((err) => {
        if (reqId !== currentRequest) return;
        bodyEl.innerHTML =
          '<div class="alert alert-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>' +
          'Failed to load message. ' + esc(err) + '</div>';
      });
  }
  window.loadMessage = loadMessage;

  // Кнопка-триггер передаётся Bootstrap'ом как relatedTarget
  document.addEventListener('show.bs.modal', (e) => {
    if (e.target.id !== 'msgModal') return;
    const btn = e.relatedTarget;
    if (btn && btn.dataset.pmid) {
      loadMessage(btn.dataset.pmid, btn.dataset.subject || '');
    }
  });

  // ---------- Raw: копирование / скачивание (кнопки внутри фрагмента) ----------
  function rawText() {
    const pre = document.querySelector('#msgModalBody #rawMessage');
    return pre ? (pre.textContent || '') : null;
  }

  window.copyRawMessage = function () {
    const text = rawText();
    if (text === null) return;

    const fallbackCopy = () => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus(); ta.select();
      try { document.execCommand('copy'); showToast('Copied to clipboard'); } catch (e) { /* ignore */ }
      document.body.removeChild(ta);
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => showToast('Copied to clipboard')).catch(fallbackCopy);
    } else {
      fallbackCopy();
    }
  };

  window.downloadRawMessage = function () {
    const text = rawText();
    if (text === null) return;

    const wrap = document.querySelector('#msgModalBody .message-content[data-pmid][data-sent]');
    const id   = wrap?.getAttribute('data-pmid') || 'unknown';
    const sent = wrap?.getAttribute('data-sent') || new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19);
    const name = `pm-${id}-${sent}.txt`;

    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url; a.download = name;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showToast(`Saved ${name}`);
  };

  // ---------- Tooltips ----------
  function initTooltips(root) {
    if (!window.bootstrap) return;
    (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      bootstrap.Tooltip.getOrCreateInstance(el);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initTooltips());
  } else {
    initTooltips();
  }
})();
