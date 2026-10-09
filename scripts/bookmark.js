'use strict';

/* ── i18n ─────────────────────────────────────────────────────────────────
   AGS_LANG is printed by the page (details.php) before the scripts.
   Missing dictionary/key -> English fallback. {1} and %1$s are both substituted
   ($lang->load() turns {1} into %1$s). Insert results as text, not HTML. */
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
    const str  = (dict && typeof dict[key] === 'string') ? dict[key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a || b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// Replaces the element content with <tag class="iconClass"></tag> + text node.
function agsIconText(el, iconClass, text, tag) {
    el.textContent = '';
    const icon = document.createElement(tag || 'i');
    icon.className = iconClass;
    el.appendChild(icon);
    el.appendChild(document.createTextNode(text));
}

// HTML-escape for the few places where a translation has to go into an HTML template.
function agsEsc(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

/* ── Popover content builder ─────────────────────────────────────── */

// Popover is rendered with html:true, so translations are escaped before going into the markup.
function getBookmarkPopover(bookmarked) {
    if (bookmarked) {
        return {
            title: t('bm_title_on', '✅ Bookmarked'),
            content:
                '<div class="bookmark-popover-content">' +
                    '<div class="mb-2">' +
                        '<strong>' + agsEsc(t('bm_on_head', 'In Your Bookmarks')) + '</strong>' +
                        '<div class="small text-muted">' + agsEsc(t('bm_on_sub', 'Easily accessible anytime')) + '</div>' +
                    '</div>' +
                    '<div class="small text-success">' +
                        '<i class="bi bi-check-circle me-1"></i>' + agsEsc(t('bm_on_note', 'Added to your collection')) +
                    '</div>' +
                '</div>',
        };
    }
    return {
        title: t('bm_title_off', '⭐ Add to Bookmarks'),
        content:
            '<div class="bookmark-popover-content">' +
                '<div class="mb-2">' +
                    '<strong>' + agsEsc(t('bm_off_head', 'Save for later')) + '</strong>' +
                    '<div class="small text-muted">' + agsEsc(t('bm_off_sub', 'Quick access to this torrent')) + '</div>' +
                '</div>' +
                '<div class="small">' +
                    '<i class="bi bi-link-45deg me-1"></i>' + agsEsc(t('bm_off_note', 'Torrent preview')) +
                '</div>' +
            '</div>',
    };
}

/* ── Popover updater ─────────────────────────────────────────────── */

function updatePopoverContent(element, bookmarked) {
    const popover = bootstrap.Popover.getInstance(element);
    if (!popover) return;

    const { title, content } = getBookmarkPopover(bookmarked);
    element.setAttribute('data-bs-title',   title);
    element.setAttribute('data-bs-content', content);

    // Пересоздаём экземпляр чтобы Bootstrap подхватил новые атрибуты
    popover.dispose();
    new bootstrap.Popover(element, { html: true, trigger: 'hover focus' });
}

/* ── Toggle bookmark ─────────────────────────────────────────────── */

async function toggleBookmark(torrentId, element) {
    // Защита от двойного клика
    if (element.dataset.loading === 'true') return;
    element.dataset.loading = 'true';

    const originalHTML     = element.innerHTML;
    element.innerHTML      = '<i class="fa-solid fa-spinner fa-spin fa-lg" style="color:#ffc107"></i>';
    element.style.pointerEvents = 'none';

    try {
        const response = await fetch('bookmark.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'toggle', id: torrentId }),
        });

        if (!response.ok) throw new Error(t('bm_http', 'HTTP error {1}', response.status));

        const data = await response.json();
        if (!data.success) throw new Error(data.message || t('bm_failed', 'Operation failed'));

        if (data.bookmarked) {
            element.innerHTML = '<i class="fa-solid fa-star fa-lg" style="color:#ffc107"></i>';
            element.classList.add('bookmarked');
            updatePopoverContent(element, true);
            showToast(t('bm_added', 'Bookmark added!'), 'success');
        } else {
            element.innerHTML = '<i class="fa-regular fa-star fa-lg" style="color:#6c757d"></i>';
            element.classList.remove('bookmarked');
            updatePopoverContent(element, false);
            showToast(t('bm_removed', 'Bookmark removed!'), 'info');
        }

    } catch (error) {
        console.error('Bookmark toggle error:', error);
        element.innerHTML = originalHTML;
        showToast(t('bm_error', 'Error: {1}', error.message), 'danger');
    } finally {
        element.style.pointerEvents = 'auto';
        delete element.dataset.loading;
    }
}

/* ── Click delegation ────────────────────────────────────────────── */

document.addEventListener('click', function (e) {
    const el = e.target.closest('.bookmark-toggle');
    if (!el) return;

    e.preventDefault();

    const torrentId = el.dataset.torrentId;
    if (!torrentId || isNaN(parseInt(torrentId, 10))) {
        console.error('Invalid torrent ID:', torrentId);
        return;
    }

    toggleBookmark(torrentId, el);
});

/* ── Init popovers ───────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.bookmark-toggle[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { html: true, trigger: 'hover focus' });
    });
});