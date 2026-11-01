/* ============================================================================
   browse.js — каталог раздач (browse.php)
   Автодополнение поиска, увеличенный постер, вид «таблица/плитки»,
   сворачивание карточек, восстановление фильтров, тосты модерации.
   Строки - AGS_LANG (ключи js_* из languages/<lang>/browse.lang.php без префикса).
   ============================================================================ */
(function () {
'use strict';

const page = document.getElementById('brPage');
if (!page) return;

// Нужен browse-moderation.js (он подключается после этого файла)
window.browseModTotalTorrents = parseInt(page.dataset.total || '0', 10) || 0;

function t(key, fallback, ...args) {
  const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
  const str = (typeof dict[key] === 'string') ? dict[key] : fallback;
  return str.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] === undefined ? m : String(args[n - 1])));
}

function store(key, value) {
  try {
    if (value === undefined) return localStorage.getItem(key);
    if (value === null) localStorage.removeItem(key); else localStorage.setItem(key, value);
  } catch (e) { /* приватный режим и т.п. */ }
  return null;
}

function escHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Автодополнение поиска ───────────────────────────────────────────────────
(function () {
  const input = document.getElementById('torrent-search');
  const results = document.getElementById('autocomplete-results');
  if (!input || !results) return;

  let timer = null;
  let ctrl = null;

  function hide() {
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
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 3) { hide(); return; }

    timer = setTimeout(() => {
      if (ctrl) ctrl.abort();
      ctrl = ('AbortController' in window) ? new AbortController() : null;
      fetch('xmlhttp.php?action=search_torrents&input=' + encodeURIComponent(q), ctrl ? { signal: ctrl.signal } : {})
        .then(r => r.json())
        .then(data => {
          if (!Array.isArray(data) || !data.length) { note(t('no_results', 'No results found')); return; }
          const frag = document.createDocumentFragment();
          data.forEach(item => {
            if (!item || !item.name || !item.id) return;
            const a = document.createElement('a');
            a.className = 'dropdown-item d-flex align-items-center';
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
        .catch(err => { if (!err || err.name !== 'AbortError') note(t('search_error', 'Error retrieving results')); });
    }, 300);
  });

  input.addEventListener('keydown', e => { if (e.key === 'Escape') hide(); });
  document.addEventListener('click', e => {
    if (!e.target.closest('#torrent-search, #autocomplete-results')) hide();
  });
})();

// ── Календарь дат добавления ────────────────────────────────────────────────
if (typeof window.flatpickr === 'function') {
  window.flatpickr('#added_from_input', { dateFormat: 'Y-m-d', allowInput: true });
  window.flatpickr('#added_to_input', { dateFormat: 'Y-m-d', allowInput: true });
}

// ── Увеличенный постер при наведении ────────────────────────────────────────
(function () {
  const img = document.getElementById('posterZoomImg');
  if (!img || !window.matchMedia('(hover: hover)').matches) return;
  let timer = null;

  document.addEventListener('mouseover', e => {
    const link = e.target.closest && e.target.closest('[data-zoom]');
    if (!link || (e.relatedTarget && link.contains(e.relatedTarget))) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      img.src = link.dataset.zoom;
      img.classList.add('visible');
    }, 150);
  });
  document.addEventListener('mouseout', e => {
    const link = e.target.closest && e.target.closest('[data-zoom]');
    if (!link || (e.relatedTarget && link.contains(e.relatedTarget))) return;
    clearTimeout(timer);
    img.classList.remove('visible');
  });
  document.addEventListener('mousemove', e => {
    if (!img.classList.contains('visible')) return;
    const x = e.clientX > window.innerWidth / 2 ? e.clientX - 300 : e.clientX + 20;
    const y = Math.max(10, Math.min(e.clientY - 120, window.innerHeight - 410));
    img.style.left = x + 'px';
    img.style.top = y + 'px';
  });
})();

// ── Сворачиваемые карточки (категории, быстрые фильтры) ─────────────────────
page.querySelectorAll('.collapse[data-br-remember]').forEach(el => {
  const key = 'browse_collapse_' + el.dataset.brRemember;
  const btn = page.querySelector('[data-bs-target="#' + el.id + '"]');
  if (store(key) === '0') {
    el.classList.remove('show');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }
  el.addEventListener('shown.bs.collapse', () => store(key, null));
  el.addEventListener('hidden.bs.collapse', () => store(key, '0'));
});

// ── Вид: таблица / плитки ───────────────────────────────────────────────────
(function () {
  const tableWrap = document.getElementById('smartTorrentTableWrap');
  const cards = document.getElementById('smartTorrentCards');
  const tableBtn = document.getElementById('smartViewTable');
  const cardsBtn = document.getElementById('smartViewCards');
  if (!tableWrap || !cards || !tableBtn || !cardsBtn) return;

  const rows = tableWrap.querySelectorAll('tbody tr.torrent-row');
  if (!rows.length) {
    tableBtn.closest('.br-view').hidden = true;
    return;
  }

  let built = false;
  function build() {
    if (built) return;
    built = true;
    const statLabels = [t('card_size', 'Size'), t('card_downloads', 'Downloads'), t('card_seeders', 'Seeders'), t('card_leechers', 'Leechers')];
    rows.forEach(row => {
      const cells = row.querySelectorAll(':scope > td');
      if (cells.length < 7) return;

      const card = document.createElement('article');
      card.className = 'br-tcard' + (row.classList.contains('torrent-row-snatched') ? ' br-tcard--snatched' : '');

      const poster = cells[0].querySelector('.poster-link');
      if (poster) card.appendChild(poster.cloneNode(true));

      const title = cells[1].querySelector('.torrent-title');
      if (title) {
        const titleClone = title.cloneNode(true);
        // id закладки должен оставаться уникальным
        titleClone.querySelectorAll('[id]').forEach(n => n.removeAttribute('id'));
        card.appendChild(titleClone);
      }
      const meta = cells[1].querySelector('.torrent-meta');
      if (meta) card.appendChild(meta.cloneNode(true));

      const stats = document.createElement('div');
      stats.className = 'br-tcard__stats';
      [2, 3, 4, 5].forEach((i, n) => {
        const item = document.createElement('span');
        item.title = statLabels[n];
        const src = cells[i].querySelector('a, .br-size');
        if (src) {
          const c = src.cloneNode(true);
          c.querySelectorAll('[id]').forEach(x => x.removeAttribute('id'));
          if (i === 2) c.insertAdjacentHTML('afterbegin', '<i class="fa-solid fa-hard-drive" aria-hidden="true"></i>');
          item.appendChild(c);
        }
        stats.appendChild(item);
      });
      card.appendChild(stats);

      if (row.classList.contains('torrent-row-snatched')) {
        const badge = document.createElement('span');
        badge.className = 'st-badge st-soft-primary align-self-start';
        badge.textContent = t('card_already', 'Already downloaded');
        card.appendChild(badge);
      }
      cards.appendChild(card);
    });

    // поповеры скачивания в клонах (до DOMContentLoaded их подхватит popover.js)
    if (document.readyState !== 'loading' && window.bootstrap && bootstrap.Popover) {
      cards.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { container: 'body', trigger: 'hover focus', html: true });
      });
    }
  }

  function setView(view) {
    const showCards = view === 'cards';
    if (showCards) build();
    tableWrap.hidden = showCards;
    cards.hidden = !showCards;
    tableBtn.setAttribute('aria-pressed', String(!showCards));
    cardsBtn.setAttribute('aria-pressed', String(showCards));
    store('browseSmartView', view);
  }

  tableBtn.addEventListener('click', () => setView('table'));
  cardsBtn.addEventListener('click', () => setView('cards'));
  setView(store('browseSmartView') === 'cards' ? 'cards' : 'table');
})();

// ── «Выбрать всё по фильтру» (модерация) ────────────────────────────────────
page.querySelectorAll('[data-br-select-all]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (window.ModerationModal && typeof ModerationModal.selectAllFiltered === 'function') {
      ModerationModal.selectAllFiltered(btn);
    }
  });
});

// ── Запоминание последнего фильтра ──────────────────────────────────────────
// Фильтры живут в URL; если уйти со страницы обычной ссылкой, при следующем
// заходе на пустой browse.php предлагаем их восстановить.
(function () {
  const KEY = 'browse_last_filters';
  if (page.dataset.hasFilters === '1') {
    const q = new URLSearchParams(window.location.search);
    ['page', 'mod_success', 'mod_error'].forEach(k => q.delete(k));
    store(KEY, '?' + q.toString());
    return;
  }
  if (window.location.search.replace(/^\?/, '') !== '') return;

  const saved = store(KEY);
  const slot = document.getElementById('brRestoreSlot');
  if (!saved || saved === '?' || !slot) return;

  const box = document.createElement('div');
  box.className = 'st-card br-restore mb-3';

  const msg = document.createElement('span');
  msg.className = 'br-restore__msg';
  const icon = document.createElement('i');
  icon.className = 'fa-solid fa-clock-rotate-left';
  msg.append(icon, document.createTextNode(t('restore_filters_msg', 'You had filters applied last time you browsed.')));

  const restore = document.createElement('a');
  restore.className = 'btn btn-sm btn-primary st-pill';
  restore.href = window.location.pathname + saved;
  restore.textContent = t('restore_filters_btn', 'Restore filters');

  const dismiss = document.createElement('button');
  dismiss.type = 'button';
  dismiss.className = 'btn btn-sm btn-outline-secondary st-pill';
  dismiss.textContent = t('restore_filters_dismiss', 'Dismiss');
  dismiss.addEventListener('click', () => { store(KEY, null); box.remove(); });

  box.append(msg, restore, dismiss);
  slot.appendChild(box);
})();

// ── Тосты после модерации (?mod_success= / ?mod_error=) ─────────────────────
(function () {
  const ok = page.dataset.toastOk || '';
  const err = page.dataset.toastErr || '';
  if (!ok && !err) return;

  const show = () => {
    if (typeof window.showToast !== 'function') return;
    if (ok) window.showToast(escHtml(ok), 'success');
    if (err) window.showToast(escHtml(err), 'error');
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show); else show();

  // чтобы F5 не повторял тост
  if (window.history && window.history.replaceState) {
    const url = new URL(window.location.href);
    url.searchParams.delete('mod_success');
    url.searchParams.delete('mod_error');
    window.history.replaceState({}, document.title, url.toString());
  }
})();

})();
