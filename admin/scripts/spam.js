/* spam.php — список ЛС + модалка просмотра (контент из spam_message.php) */
(function () {
  'use strict';

  // ---------- i18n ----------
  // AGS_LANG выводится spam.php перед подключением скрипта (ключи js_* без префикса).
  // Если словаря нет - работают английские fallback'и.
  const LANG = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
  function t(key, fallback, ...args) {
    let s = typeof LANG[key] === 'string' ? LANG[key] : fallback;
    args.forEach((a, i) => { s = s.split('{' + (i + 1) + '}').join(String(a)); });
    return s;
  }

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

    titleEl.textContent = subject || t('no_subject', '(no subject)');

    // Переводы вставляем только как текст (без innerHTML)
    const loading = document.createElement('div');
    loading.className = 'sp-loading';
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border spinner-border-sm';
    spinner.setAttribute('role', 'status');
    spinner.setAttribute('aria-hidden', 'true');
    const loadingText = document.createElement('span');
    loadingText.textContent = t('loading', 'Loading message…');
    loading.append(spinner, loadingText);
    bodyEl.replaceChildren(loading);

    fetch(endpoint + '?id=' + encodeURIComponent(pmid), { credentials: 'same-origin' })
      .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
      .then((html) => {
        if (reqId !== currentRequest) return;
        bodyEl.innerHTML = html;
        initTooltips(bodyEl);
      })
      .catch((err) => {
        if (reqId !== currentRequest) return;
        const box = document.createElement('div');
        box.className = 'alert alert-danger mb-0';
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-triangle-exclamation me-2';
        box.append(icon, document.createTextNode(t('load_failed', 'Failed to load message. {1}', err)));
        bodyEl.replaceChildren(box);
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
      try { document.execCommand('copy'); showToast(t('copied', 'Copied to clipboard')); } catch (e) { /* ignore */ }
      document.body.removeChild(ta);
    };

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => showToast(t('copied', 'Copied to clipboard'))).catch(fallbackCopy);
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

    showToast(t('saved', 'Saved {1}', name));
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
