(() => {
  'use strict';

  const page = document.getElementById('flPage');
  if (!page) return;

  // ── Lang ──
  const LANG = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
  // t(key, fallback, ...args): {1} и %1$s (после $lang->load()) → args.
  const t = (key, fallback, ...args) => {
    const str = typeof LANG[key] === 'string' && LANG[key] !== '' ? LANG[key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
      const i = Number(a || b) - 1;
      return i >= 0 && i < args.length ? String(args[i]) : m;
    });
  };
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
  const icon = (cls) => {
    const i = document.createElement('i');
    i.className = `fa-solid ${cls}`;
    return i;
  };
  const el = (tag, ...children) => {
    const node = document.createElement(tag);
    children.forEach((c) => node.append(c));
    return node;
  };

  const free   = Number(page.dataset.free)   || 0;
  const normal = Number(page.dataset.normal) || 0;
  const total  = Number(page.dataset.total)  || 0;
  const fmt    = (n) => n.toLocaleString();

  const KINDS = {
    free: {
      title: t('confirm_free_title', 'Enable FreeLeech?'),
      icon: 'warning',
      color: '--bs-success',
      // confirmButtonText у Swal — HTML: иконка + экранированный текст.
      confirm: `<i class="fa-solid fa-gift"></i> ${esc(t('confirm_free_btn', 'Enable FreeLeech'))}`,
      after: [total, 0],
      plain: t('confirm_free_plain', 'Enable FreeLeech for all {1} torrents?', fmt(total)),
    },
    normal: {
      title: t('confirm_normal_title', 'Restore normal mode?'),
      icon: 'question',
      color: '--bs-primary',
      confirm: `<i class="fa-solid fa-rotate-left"></i> ${esc(t('confirm_normal_btn', 'Restore normal'))}`,
      after: [0, total],
      plain: t('confirm_normal_plain', 'Restore normal mode for all {1} torrents?', fmt(total)),
    },
  };

  // Превью собирается через DOM — переводы попадают как текст.
  const previewNode = (after) => {
    const col = (label, a, b) => el('div',
      el('small', label),
      el('span', icon('fa-gift'), ` ${fmt(a)}`),
      el('span', icon('fa-scale-balanced'), ` ${fmt(b)}`));

    const grid = el('div',
      col(t('now', 'Now'), free, normal),
      icon('fa-arrow-right-long'),
      col(t('after', 'After'), after[0], after[1]));
    grid.className = 'fl-swal-grid';

    const note = el('p', t('whole_tracker', 'This changes the whole tracker at once.'));
    note.style.marginTop = '1rem';
    note.style.opacity = '.75';

    return el('div', grid, note);
  };

  const hasSwal = typeof window.Swal !== 'undefined';

  const askConfirm = async (cfg) => {
    if (!hasSwal) return window.confirm(cfg.plain);
    const res = await Swal.fire({
      titleText: cfg.title,
      html: previewNode(cfg.after),
      icon: cfg.icon,
      showCancelButton: true,
      confirmButtonText: cfg.confirm,
      cancelButtonText: esc(t('cancel', 'Cancel')),
      confirmButtonColor: getComputedStyle(page).getPropertyValue(cfg.color).trim() || undefined,
      reverseButtons: true,
      focusCancel: true,
    });
    return res.isConfirmed;
  };

  const showError = (msg) => {
    if (hasSwal) Swal.fire({ icon: 'error', titleText: t('error_title', 'Action failed'), text: msg });
    else window.alert(msg);
  };

  document.querySelectorAll('.fl-form').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const cfg = KINDS[form.dataset.kind];
      const btn = form.querySelector('button');
      if (!cfg || btn.disabled) return;
      if (!(await askConfirm(cfg))) return;

      const original = btn.innerHTML;
      document.querySelectorAll('.fl-form button').forEach((b) => { b.disabled = true; });
      btn.replaceChildren(icon('fa-spinner fa-spin'), ` ${t('working', 'Working…')}`);

      try {
        const res = await fetch(form.getAttribute('action'), {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams(new FormData(form)),
          credentials: 'same-origin',
        });
        let data = null;
        try { data = await res.json(); } catch { /* not JSON */ }

        if (res.ok && data && data.status === 'success') {
          if (hasSwal) {
            await Swal.fire({
              icon: 'success', titleText: t('done_title', 'Done'), text: data.message,
              timer: 1800, timerProgressBar: true, showConfirmButton: false,
            });
          }
          window.location.replace(data.redirect || window.location.href);
          return;
        }
        showError((data && data.message) || t('server_status', 'Server responded with {1}.', res.status));
      } catch (err) {
        console.error(err);
        showError(t('network_error', 'Network error. Check your connection and try again.'));
      }

      // original — серверная разметка кнопки, не перевод из JS.
      btn.innerHTML = original;
      document.querySelectorAll('.fl-form button').forEach((b) => {
        b.disabled = b.closest('.fl-form').dataset.kind === 'free' ? normal === 0 : free === 0;
      });
    });
  });
})();
