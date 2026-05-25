'use strict';

/* ── Popover content builder ─────────────────────────────────────── */

function getBookmarkPopover(bookmarked) {
    if (bookmarked) {
        return {
            title: '✅ Bookmarked',
            content:
                '<div class="bookmark-popover-content">' +
                    '<div class="mb-2">' +
                        '<strong>In Your Bookmarks</strong>' +
                        '<div class="small text-muted">Easily accessible anytime</div>' +
                    '</div>' +
                    '<div class="small text-success">' +
                        '<i class="bi bi-check-circle me-1"></i>Added to your collection' +
                    '</div>' +
                '</div>',
        };
    }
    return {
        title: '⭐ Add to Bookmarks',
        content:
            '<div class="bookmark-popover-content">' +
                '<div class="mb-2">' +
                    '<strong>Save for later</strong>' +
                    '<div class="small text-muted">Quick access to this torrent</div>' +
                '</div>' +
                '<div class="small">' +
                    '<i class="bi bi-link-45deg me-1"></i>Torrent preview' +
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

        if (!response.ok) throw new Error('HTTP error ' + response.status);

        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Operation failed');

        if (data.bookmarked) {
            element.innerHTML = '<i class="fa-solid fa-star fa-lg" style="color:#ffc107"></i>';
            element.classList.add('bookmarked');
            updatePopoverContent(element, true);
            showToast('Bookmark added!', 'success');
        } else {
            element.innerHTML = '<i class="fa-regular fa-star fa-lg" style="color:#6c757d"></i>';
            element.classList.remove('bookmarked');
            updatePopoverContent(element, false);
            showToast('Bookmark removed!', 'info');
        }

    } catch (error) {
        console.error('Bookmark toggle error:', error);
        element.innerHTML = originalHTML;
        showToast('Error: ' + error.message, 'danger');
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