/* Countries — staff panel (index.php?act=country) */
(() => {
  'use strict';

  const page = document.querySelector('.cn-page');
  if (!page) return;

  // ── Delete: SweetAlert2 (confirm() fallback) → hidden POST form ───────────
  const confirmDelete = name => {
    const title = `Delete ${name}?`;
    const text  = 'Members who picked this country will show no flag until they choose another.';
    if (typeof window.Swal === 'undefined') {
      return Promise.resolve(window.confirm(`${title}\n\n${text}`));
    }
    return window.Swal.fire({
      title,
      text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545',
      reverseButtons: true,
      focusCancel: true,
    }).then(r => r.isConfirmed === true);
  };

  const delForm = document.getElementById('cn-delete-form');

  page.addEventListener('click', e => {
    const btn = e.target.closest('[data-cn-delete]');
    if (!btn || !delForm || btn.classList.contains('is-busy')) return;

    confirmDelete(btn.dataset.name || 'this country').then(ok => {
      if (!ok) return;
      btn.classList.add('is-busy');
      const icon = btn.querySelector('i');
      if (icon) icon.className = 'fa-solid fa-spinner fa-spin';
      delForm.elements.namedItem('id').value = btn.dataset.id;
      HTMLFormElement.prototype.submit.call(delForm);
    });
  });

  // ── Client-side filters (list rows / flag picker) ─────────────────────────
  page.querySelectorAll('input[data-cn-filter]').forEach(input => {
    const selector = input.dataset.cnFilter;
    const items    = () => page.querySelectorAll(selector);
    const noMatch  = selector.startsWith('#cn-table')
      ? document.getElementById('cn-no-match')
      : document.getElementById('cn-no-flag');

    input.addEventListener('input', () => {
      const term = input.value.trim().toLowerCase();
      let shown = 0;
      items().forEach(el => {
        const hit = term === '' || (el.dataset.cnText || '').includes(term);
        el.hidden = !hit;
        if (hit) shown++;
      });
      if (noMatch) noMatch.hidden = shown > 0;
    });

    input.addEventListener('keydown', e => {
      if (e.key === 'Escape') { input.value = ''; input.dispatchEvent(new Event('input')); }
    });
  });

  // ── Live preview on the form ──────────────────────────────────────────────
  const nameIn  = document.getElementById('cn-name');
  const pvName  = document.getElementById('cn-preview-name');
  const pvFlag  = document.getElementById('cn-preview-flag');
  const pvFile  = document.getElementById('cn-preview-file');

  if (nameIn && pvName) {
    nameIn.addEventListener('input', () => {
      pvName.textContent = nameIn.value.trim() || 'Country name';
    });
  }

  page.addEventListener('change', e => {
    const radio = e.target.closest('input[name="flagpic"]');
    if (!radio || !pvFlag) return;
    const img = document.createElement('img');
    img.src = radio.dataset.cnSrc;
    img.alt = '';
    pvFlag.replaceChildren(img);
    if (pvFile) pvFile.textContent = radio.value;
  });

  // Scroll the pre-selected flag into view inside the picker
  // (only the picker scrolls, not the whole page)
  const picker  = page.querySelector('.cn-picker');
  const checked = picker?.querySelector('input:checked')?.closest('.cn-pick');
  if (picker && checked) {
    picker.scrollTop = checked.offsetTop - picker.offsetTop - picker.clientHeight / 2 + checked.clientHeight / 2;
  }

  // ── Busy state on submit ──────────────────────────────────────────────────
  page.querySelectorAll('form[data-cn-form]').forEach(form => {
    form.addEventListener('submit', e => {
      const btn = form.querySelector('[data-cn-submit]');
      if (!btn) return;
      if (btn.classList.contains('is-busy')) { e.preventDefault(); return; }
      btn.classList.add('is-busy');
      const icon = btn.querySelector('i');
      if (icon) icon.className = 'fa-solid fa-spinner fa-spin me-1';
    });
  });
})();
