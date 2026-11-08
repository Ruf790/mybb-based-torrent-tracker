/* ============================================================================
   browse.js — каталог торрентов (browse.php)
   Автодополнение, увеличение постера, сворачиваемые блоки, миниатюры
   карусели, календарь дат, флеш-сообщения модерации, «восстановить фильтры».
   Строки — из AGS_LANG (ключи js_* из browse.lang.php без префикса).
   ============================================================================ */
(function () {
'use strict';

function t(key, fallback, ...args) {
  const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
  const str = (typeof dict[key] === 'string') ? dict[key] : fallback;
  return str.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] === undefined ? m : String(args[n - 1])));
}

function store(method, key, value) {
  try { return localStorage[method](key, value); } catch (e) { return null; }
}

/** Текст -> безопасный HTML (showToast() принимает HTML) */
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = String(str);
  return d.innerHTML;
}

const page = document.querySelector('.br-page');

// ── Автодополнение ──────────────────────────────────────────
// Раньше имя торрента и URL постера вставлялись через innerHTML (XSS через
// название раздачи). Теперь только textContent / атрибуты.
(function () {
  const input = document.getElementById('torrent-search');
  const results = document.getElementById('autocomplete-results');
  if (!input || !results) return;

  let timer = null;
  let seq = 0;

  function close() {
    results.classList.remove('show');
    results.replaceChildren();
  }

  function note(text) {
    const a = document.createElement('span');
    a.className = 'dropdown-item disabled';
    a.textContent = text;
    results.replaceChildren(a);
    results.classList.add('show');
  }

  input.addEventListener('input', function () {
    const query = input.value.trim();
    clearTimeout(timer);
    if (query.length < 3) { close(); return; }

    timer = setTimeout(() => {
      const my = ++seq;
      fetch('xmlhttp.php?action=search_torrents&input=' + encodeURIComponent(query), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (my !== seq) return;              // пришёл ответ на старый запрос
          if (!Array.isArray(data) || data.length === 0) { note(t('no_results', 'No results found')); return; }

          const frag = document.createDocumentFragment();
          data.forEach(item => {
            if (!item || !item.name || !item.id) return;
            const a = document.createElement('a');
            a.className = 'dropdown-item';
            a.href = 'details.php?id=' + encodeURIComponent(item.id);
            if (item.image_url) {
              const img = document.createElement('img');
              img.src = item.image_url;
              img.alt = '';
              img.loading = 'lazy';
              a.appendChild(img);
            }
            const span = document.createElement('span');
            span.textContent = item.name;
            a.appendChild(span);
            frag.appendChild(a);
          });
          results.replaceChildren(frag);
          results.classList.add('show');
        })
        .catch(() => { if (my === seq) note(t('search_error', 'Error retrieving results')); });
    }, 300);
  });

  input.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  document.addEventListener('click', e => {
    if (!e.target.closest('#torrent-search, #autocomplete-results')) close();
  });
})();

// ── Увеличение постера при наведении ────────────────────────
(function () {
  const img = document.getElementById('posterZoomImg');
  if (!img || !window.matchMedia('(hover: hover)').matches) return;

  let timer = null;
  let lastX = 0, lastY = 0;

  function place() {
    const offX = lastX > window.innerWidth / 2 ? -300 : 20;
    const offY = lastY > window.innerHeight / 2 ? -420 : 20;
    img.style.left = Math.max(8, lastX + offX) + 'px';
    img.style.top = Math.max(8, lastY + offY) + 'px';
  }

  document.addEventListener('mouseover', e => {
    const link = e.target.closest && e.target.closest('.poster-link[data-zoom]');
    if (!link || link.contains(e.relatedTarget)) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      img.src = link.dataset.zoom;
      place();
      img.classList.add('visible');
    }, 150);
  });

  document.addEventListener('mouseout', e => {
    const link = e.target.closest && e.target.closest('.poster-link[data-zoom]');
    if (!link || link.contains(e.relatedTarget)) return;
    clearTimeout(timer);
    img.classList.remove('visible');
  });

  document.addEventListener('mousemove', e => {
    lastX = e.clientX; lastY = e.clientY;
    if (img.classList.contains('visible')) place();
  });
})();

// ── «Мёртвые» строки ────────────────────────────────────────
document.querySelectorAll('.torrent-row').forEach(row => {
  if (+row.dataset.seeders === 0 && +row.dataset.leechers === 0) row.classList.add('is-dead');
});

// ── Подсветка категории после клика по плитке ───────────────
(function () {
  const id = store('getItem', 'highlightedCategory');
  if (id) {
    const tile = document.querySelector('.category-container[data-category-id="' + CSS.escape(id) + '"]');
    if (tile) tile.classList.add('category-highlight');
    store('removeItem', 'highlightedCategory');
  }
  document.querySelectorAll('.category-link').forEach(link => {
    link.addEventListener('click', () => store('setItem', 'highlightedCategory', link.dataset.catId || ''));
  });
})();

// ── Сворачиваемые блоки (состояние запоминается) ────────────
document.querySelectorAll('[data-br-fold]').forEach(btn => {
  const id = btn.dataset.brFold;
  const body = document.getElementById(id);
  if (!body) return;
  const key = 'br_fold_' + id;

  function set(open, save) {
    btn.setAttribute('aria-expanded', String(open));
    if (open) body.removeAttribute('data-br-folded'); else body.setAttribute('data-br-folded', '');
    if (save) store('setItem', key, open ? '1' : '0');
  }

  set(store('getItem', key) !== '0', false);
  btn.addEventListener('click', () => set(btn.getAttribute('aria-expanded') !== 'true', true));
});

// ── Миниатюры карусели (они вне .carousel, Bootstrap их не отмечает) ──
document.querySelectorAll('[data-br-thumbs]').forEach(strip => {
  const carousel = document.querySelector(strip.dataset.brThumbs);
  if (!carousel) return;
  carousel.addEventListener('slid.bs.carousel', e => {
    strip.querySelectorAll('.br-thumb').forEach((b, i) => {
      const on = i === e.to;
      b.classList.toggle('active', on);
      if (on) {
        b.setAttribute('aria-current', 'true');
        strip.scrollTo({ left: b.offsetLeft - strip.clientWidth / 2 + b.clientWidth / 2, behavior: 'smooth' });
      } else {
        b.removeAttribute('aria-current');
      }
    });
  });
});

// ── Календарь для «Добавлен с / по» ─────────────────────────
if (typeof flatpickr === 'function') {
  ['#added_from_input', '#added_to_input'].forEach(sel => {
    if (document.querySelector(sel)) flatpickr(sel, { dateFormat: 'Y-m-d', allowInput: true });
  });
}

// ── Флеш-сообщения после модерации ─────────────────────────
// Текст приходит из ?mod_success= / ?mod_error= - экранируем перед showToast().
if (page && (page.dataset.flashOk || page.dataset.flashErr)) {
  if (typeof window.showToast === 'function') {
    if (page.dataset.flashOk)  window.showToast(escHtml(page.dataset.flashOk), 'success');
    if (page.dataset.flashErr) window.showToast(escHtml(page.dataset.flashErr), 'error');
  }
  // F5 не должен повторять уведомление
  if (window.history && window.history.replaceState) {
    const url = new URL(window.location.href);
    url.searchParams.delete('mod_success');
    url.searchParams.delete('mod_error');
    window.history.replaceState({}, document.title, url.toString());
  }
}

// ── Запоминание последнего фильтра ─────────────────────────
// С активными фильтрами - сохраняем query string; на «чистом» browse.php
// предлагаем восстановить.
(function () {
  if (!page) return;
  const KEY = 'browse_last_filters';

  if (page.dataset.hasFilters === '1') {
    const url = new URL(window.location.href);
    ['page', 'mod_success', 'mod_error'].forEach(p => url.searchParams.delete(p));
    store('setItem', KEY, url.search);
    return;
  }

  const saved = store('getItem', KEY);
  const slot = document.getElementById('brRestoreSlot');
  if (!saved || saved === '?' || !slot) return;

  const bar = document.createElement('div');
  bar.className = 'st-card st-toolbar br-restore mb-3';

  const msg = document.createElement('span');
  msg.className = 'd-inline-flex align-items-center gap-2';
  const icon = document.createElement('i');
  icon.className = 'fa-solid fa-clock-rotate-left text-primary';
  icon.setAttribute('aria-hidden', 'true');
  msg.append(icon, document.createTextNode(t('restore_filters_msg', 'You had filters applied last time you browsed.')));

  const actions = document.createElement('span');
  actions.className = 'd-flex flex-wrap gap-2';
  const restore = document.createElement('a');
  restore.href = window.location.pathname + saved;
  restore.className = 'btn btn-sm btn-primary st-pill';
  restore.textContent = t('restore_filters_btn', 'Restore filters');
  const dismiss = document.createElement('button');
  dismiss.type = 'button';
  dismiss.className = 'btn btn-sm btn-outline-secondary st-pill';
  dismiss.textContent = t('restore_filters_dismiss', 'Dismiss');
  dismiss.addEventListener('click', () => { store('removeItem', KEY); bar.remove(); });
  actions.append(restore, dismiss);

  bar.append(msg, actions);
  slot.appendChild(bar);
})();

})();
