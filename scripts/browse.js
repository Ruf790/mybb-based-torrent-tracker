(function () {
'use strict';

// ── i18n ────────────────────────────────────────────────────
// AGS_LANG выводит browse.php (ключи js_* из languages/<lang>/browse.lang.php
// без префикса). Fallback - английский текст, {1}, {2}… - аргументы.
function t(key, fallback, ...args) {
  const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
  const str = (typeof dict[key] === 'string') ? dict[key] : fallback;
  return str.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] === undefined ? m : String(args[n - 1])));
}

function disabledItem(text) {
  const a = document.createElement('a');
  a.className = 'dropdown-item disabled';
  a.textContent = text;
  return a;
}

// ── Autocomplete search ─────────────────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  const input = document.getElementById("torrent-search");
  const results = document.getElementById("autocomplete-results");
  if (!input || !results) return;

  let debounceTimer;

  input.addEventListener("input", function () {
    const query = input.value.trim();

    clearTimeout(debounceTimer);
    if (query.length < 3) {
      results.classList.remove("show");
      results.innerHTML = '';
      return;
    }

    debounceTimer = setTimeout(() => {
      fetch("xmlhttp.php?action=search_torrents&input=" + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
          results.innerHTML = '';

          if (!Array.isArray(data) || data.length === 0) {
            results.appendChild(disabledItem(t('no_results', 'No results found')));
            results.classList.add("show");
            return;
          }

          data.forEach(item => {
            if (!item.name || !item.id) return;
            const img = item.image_url ? `<img src="${item.image_url}" alt="" style="width:40px;height:auto;margin-right:10px;">` : "";
            const option = document.createElement("a");
            option.classList.add("dropdown-item", "d-flex", "align-items-center");
            option.href = "details.php?id=" + item.id;
            option.innerHTML = img + `<span>${item.name}</span>`;
            results.appendChild(option);
          });

          results.classList.add("show");
        })
        .catch(() => {
          results.innerHTML = '';
          results.appendChild(disabledItem(t('search_error', 'Error retrieving results')));
          results.classList.add("show");
        });
    }, 300);
  });

  document.addEventListener("click", function (e) {
    if (!e.target.closest("#torrent-search, #autocomplete-results")) {
      results.classList.remove("show");
      results.innerHTML = '';
    }
  });
});

// ── Poster zoom ────────────────────────────────────────────
(function () {
    const overlay = document.getElementById('posterZoomOverlay');
    const img     = document.getElementById('posterZoomImg');
    if (!overlay || !img) return;

    let timer = null;

    document.addEventListener('mouseenter', e => {
        const link = e.target?.closest?.('.poster-link[data-zoom]');
        if (!link) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            img.src = link.dataset.zoom;
            img.classList.add('visible');
        }, 150);
    }, true);

    document.addEventListener('mouseleave', e => {
        const link = e.target?.closest?.('.poster-link[data-zoom]');
        if (!link) return;
        clearTimeout(timer);
        img.classList.remove('visible');
        setTimeout(() => { img.src = ''; }, 200);
    }, true);

    document.addEventListener('mousemove', e => {
        if (!img.classList.contains('visible')) return;
        const offX = e.clientX > window.innerWidth  / 2 ? -300 : 20;
        const offY = e.clientY > window.innerHeight / 2 ? -420 : 20;
        img.style.cssText = `position:fixed;left:${e.clientX + offX}px;top:${e.clientY + offY}px;transform:none`;
    });
})();

// ── Dead rows ──────────────────────────────────────────────
document.querySelectorAll('.torrent-row').forEach(row => {
    if (+row.dataset.seeders === 0 && +row.dataset.leechers === 0 && row.dataset.external !== 'yes') {
        row.classList.add('is-dead');
    }
});

// ── Category highlight ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const categoryLinks = document.querySelectorAll('.category-link');
    const highlightedId = localStorage.getItem('highlightedCategory');

    if (highlightedId) {
        const targetBlock = document.querySelector('.category-container[data-category-id="' + highlightedId + '"]');
        if (targetBlock) {
            targetBlock.classList.add('category-highlight');
        }
        localStorage.removeItem('highlightedCategory');
    }

    categoryLinks.forEach(link => {
        link.addEventListener('click', function () {
            const catId = this.getAttribute('data-cat-id');
            localStorage.setItem('highlightedCategory', catId);
        });
    });
});

})();
