(() => {
  'use strict';

  const page = document.getElementById('flPage');
  if (!page) return;

  const free   = Number(page.dataset.free)   || 0;
  const normal = Number(page.dataset.normal) || 0;
  const total  = Number(page.dataset.total)  || 0;
  const fmt    = (n) => n.toLocaleString();

  const KINDS = {
    free: {
      title: 'Enable FreeLeech?',
      icon: 'warning',
      color: '--bs-success',
      confirm: '<i class="fa-solid fa-gift"></i> Enable FreeLeech',
      after: [total, 0],
      plain: `Enable FreeLeech for all ${fmt(total)} torrents?`,
    },
    normal: {
      title: 'Restore normal mode?',
      icon: 'question',
      color: '--bs-primary',
      confirm: '<i class="fa-solid fa-rotate-left"></i> Restore normal',
      after: [0, total],
      plain: `Restore normal mode for all ${fmt(total)} torrents?`,
    },
  };

  const previewHtml = (after) => `
    <div class="fl-swal-grid">
      <div><small>Now</small>
        <span><i class="fa-solid fa-gift"></i> ${fmt(free)}</span>
        <span><i class="fa-solid fa-scale-balanced"></i> ${fmt(normal)}</span></div>
      <i class="fa-solid fa-arrow-right-long"></i>
      <div><small>After</small>
        <span><i class="fa-solid fa-gift"></i> ${fmt(after[0])}</span>
        <span><i class="fa-solid fa-scale-balanced"></i> ${fmt(after[1])}</span></div>
    </div>
    <p style="margin-top:1rem;opacity:.75">This changes the whole tracker at once.</p>`;

  const hasSwal = typeof window.Swal !== 'undefined';

  const askConfirm = async (cfg) => {
    if (!hasSwal) return window.confirm(cfg.plain);
    const res = await Swal.fire({
      title: cfg.title,
      html: previewHtml(cfg.after),
      icon: cfg.icon,
      showCancelButton: true,
      confirmButtonText: cfg.confirm,
      cancelButtonText: 'Cancel',
      confirmButtonColor: getComputedStyle(page).getPropertyValue(cfg.color).trim() || undefined,
      reverseButtons: true,
      focusCancel: true,
    });
    return res.isConfirmed;
  };

  const showError = (msg) => {
    if (hasSwal) Swal.fire({ icon: 'error', title: 'Action failed', text: msg });
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
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Working…';

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
              icon: 'success', title: 'Done', text: data.message,
              timer: 1800, timerProgressBar: true, showConfirmButton: false,
            });
          }
          window.location.replace(data.redirect || window.location.href);
          return;
        }
        showError((data && data.message) || `Server responded with ${res.status}.`);
      } catch (err) {
        console.error(err);
        showError('Network error. Check your connection and try again.');
      }

      btn.innerHTML = original;
      document.querySelectorAll('.fl-form button').forEach((b) => {
        b.disabled = b.closest('.fl-form').dataset.kind === 'free' ? normal === 0 : free === 0;
      });
    });
  });
})();
