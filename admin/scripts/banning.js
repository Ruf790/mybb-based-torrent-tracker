/* ==========================================================================
   banning.js — Ban management (index.php?act=banning)
   - SweetAlert2 confirmations for delete / lift / prune (confirm() fallback)
   - Busy state on submit buttons
   - Reason character counter
   - Username autocomplete with keyboard navigation
   - Esc / backdrop on the no-JS confirmation page
   ========================================================================== */
(() => {
  'use strict';

  const page = document.querySelector('.bn-page');
  if (!page) return;

  const postKey = page.dataset.postKey || '';
  const cssVar  = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  // ── Confirmations ─────────────────────────────────────────────────────────
  const askConfirm = el => {
    const title = el.dataset.title || 'Are you sure?';
    const text  = el.dataset.text  || '';
    const ok    = el.dataset.ok    || 'Continue';
    const tone  = el.dataset.tone  || 'danger';

    if (typeof window.Swal === 'undefined') {
      return Promise.resolve(window.confirm(text ? `${title}\n\n${text}` : title));
    }

    return window.Swal.fire({
      title,
      text,
      icon: tone === 'success' ? 'question' : 'warning',
      showCancelButton: true,
      confirmButtonText: ok,
      cancelButtonText: 'Cancel',
      reverseButtons: true,
      focusCancel: true,
      confirmButtonColor: cssVar(`--bs-${tone}`) || undefined,
    }).then(r => r.isConfirmed === true);
  };

  const postTo = url => {
    const form  = document.createElement('form');
    form.method = 'post';
    form.action = url;
    form.hidden = true;

    const key = document.createElement('input');
    key.type  = 'hidden';
    key.name  = 'my_post_key';
    key.value = postKey;

    form.appendChild(key);
    document.body.appendChild(form);
    form.submit();
  };

  page.addEventListener('click', e => {
    const el = e.target.closest('a[data-bn-confirm]');
    if (!el || !page.contains(el)) return;

    e.preventDefault();
    if (el.classList.contains('is-busy')) return;

    askConfirm(el).then(ok => {
      if (!ok) return;
      el.classList.add('is-busy');
      const icon = el.querySelector('i');
      if (icon) icon.className = 'fa-solid fa-spinner fa-spin';
      postTo(el.getAttribute('href'));
    });
  });

  // ── Busy state on submit ──────────────────────────────────────────────────
  page.querySelectorAll('form[data-bn-form]').forEach(form => {
    form.addEventListener('submit', e => {
      const btn = form.querySelector('[data-bn-submit]');
      if (!btn) return;
      if (btn.classList.contains('is-busy')) {
        e.preventDefault();
        return;
      }
      btn.classList.add('is-busy');
      const icon = btn.querySelector('i');
      if (icon) icon.className = 'fa-solid fa-spinner fa-spin me-2';
    });
  });

  // ── Reason counter ────────────────────────────────────────────────────────
  page.querySelectorAll('textarea[data-bn-count]').forEach(area => {
    const out = document.getElementById(area.dataset.bnCount);
    if (!out) return;
    const max  = parseInt(area.getAttribute('maxlength'), 10) || 255;
    const sync = () => {
      const n = area.value.length;
      out.textContent = `${n} / ${max}`;
      out.classList.toggle('is-near', n >= max * 0.9);
    };
    area.addEventListener('input', sync);
    sync();
  });

  // ── Username autocomplete ─────────────────────────────────────────────────
  const input = document.getElementById('username');
  const box   = document.getElementById('usernameSuggestions');

  if (input && box) {
    let timer  = null;
    let ctrl   = null;
    let items  = [];
    let active = -1;

    const highlight = (name, term) => {
      const i = name.toLowerCase().indexOf(term.toLowerCase());
      if (i < 0) return esc(name);
      return esc(name.slice(0, i))
           + '<mark>' + esc(name.slice(i, i + term.length)) + '</mark>'
           + esc(name.slice(i + term.length));
    };

    const close = () => {
      box.classList.remove('is-open');
      box.innerHTML = '';
      items  = [];
      active = -1;
      input.setAttribute('aria-expanded', 'false');
    };

    const setActive = i => {
      items.forEach((b, n) => {
        b.classList.toggle('is-active', n === i);
        b.setAttribute('aria-selected', n === i ? 'true' : 'false');
      });
      active = i;
      if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    };

    const pick = btn => {
      input.value = btn.dataset.name;
      close();
      input.focus();
    };

    input.addEventListener('input', () => {
      const term = input.value.trim();
      clearTimeout(timer);

      if (term.length < 2) {
        close();
        return;
      }

      timer = setTimeout(() => {
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();

        fetch('index.php?act=banning&type=users&action=search_username&q=' + encodeURIComponent(term), {
          signal: ctrl.signal,
          credentials: 'same-origin',
        })
          .then(r => (r.ok ? r.json() : []))
          .then(users => {
            if (!Array.isArray(users) || users.length === 0) {
              close();
              return;
            }
            box.innerHTML = users.map(u =>
              `<button type="button" role="option" aria-selected="false" data-name="${esc(u.username)}">`
              + '<i class="fa-solid fa-user" aria-hidden="true"></i>'
              + `<span>${highlight(String(u.username ?? ''), term)}</span></button>`
            ).join('');
            items  = [...box.querySelectorAll('button')];
            active = -1;
            box.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
          })
          .catch(() => {});
      }, 250);
    });

    input.addEventListener('keydown', e => {
      if (!items.length) return;
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          setActive((active + 1) % items.length);
          break;
        case 'ArrowUp':
          e.preventDefault();
          setActive((active - 1 + items.length) % items.length);
          break;
        case 'Enter':
          if (active >= 0) {
            e.preventDefault();
            pick(items[active]);
          }
          break;
        case 'Escape':
          close();
          break;
      }
    });

    box.addEventListener('mousedown', e => e.preventDefault()); // keep focus in the input
    box.addEventListener('click', e => {
      const btn = e.target.closest('button');
      if (btn) pick(btn);
    });

    document.addEventListener('click', e => {
      if (e.target !== input && !box.contains(e.target)) close();
    });
  }

  // ── No-JS confirmation page: Esc / backdrop cancel ────────────────────────
  const cancelUrl = page.dataset.cancelUrl;
  if (cancelUrl) {
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') window.location.href = cancelUrl;
    });
    const backdrop = page.querySelector('.bn-confirm-backdrop');
    if (backdrop) backdrop.addEventListener('click', () => { window.location.href = cancelUrl; });
  }
})();
