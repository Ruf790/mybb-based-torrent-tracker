'use strict';

// ── Selection ─────────────────────────────────────────────
function toggleAllSelection(checkbox) {
    document.querySelectorAll('.torrent-checkbox').forEach(cb => { cb.checked = checkbox.checked; });
    updateSelectionCounter();
}

function updateSelectionCounter() {
    const all      = document.querySelectorAll('.torrent-checkbox');
    const selected = document.querySelectorAll('.torrent-checkbox:checked').length;
    const counter  = document.getElementById('selectedCounter');
    const execBtn  = document.getElementById('executeBtn');
    const selAll   = document.getElementById('selectAll');

    if (counter) counter.textContent = selected + ' selected';
    if (execBtn) execBtn.disabled = selected === 0;
    // «Select page» показывает частичный выбор
    if (selAll) {
        selAll.checked = all.length > 0 && selected === all.length;
        selAll.indeterminate = selected > 0 && selected < all.length;
    }

    document.querySelectorAll('.torrent-row').forEach(row => {
        const cb = row.querySelector('.torrent-checkbox');
        row.classList.toggle('selected', cb?.checked ?? false);
    });
}

function clearSelection() {
    document.querySelectorAll('.torrent-checkbox').forEach(cb => { cb.checked = false; });
    updateSelectionCounter();
}

// ── Filters ───────────────────────────────────────────────
function clearSearch() {
    const input = document.getElementById('torrent-search');
    if (input) input.value = '';
    document.getElementById('searchForm')?.submit();
}

function resetFilters() {
    // Раньше: manageTorrentScript (уже «index.php?act=manage_torrents&») + «?act=manage_torrents»
    // давало «…&?act=manage_torrents» — сброс оставлял фильтры в адресе
    window.location.href = window.location.pathname + '?act=manage_torrents';
}

function toggleMoveCategory(select) {
    const div = document.getElementById('moveCategory');
    if (div) div.style.display = select.value === 'move' ? 'inline-block' : 'none';
}

// ── Quick actions (одиночное действие над торрентом, вызывается из модалки) ──
function submitTorrentAction(id, action) {
    const label = action === 'delete'
        ? `Delete torrent #${id}?\n\nThis cannot be undone.`
        : `Toggle "${action}" for torrent #${id}?`;
    if (!confirm(label)) return;

    // Раньше в форме не было my_post_key — сервер всегда отвечал
    // «Security check failed», и быстрые действия из модалки не работали
    const key = document.querySelector('#torrentForm [name="my_post_key"]')?.value
             || window.manageTorrentKey || '';

    const form = Object.assign(document.createElement('form'), {
        method: 'POST',
        action: window.location.href,
    });
    form.style.display = 'none';

    const add = (name, value) => form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name, value }));
    add('do', 'update');
    add('actiontype', action);
    add('my_post_key', key);
    add('torrentid[]', String(id));

    document.body.appendChild(form);
    form.submit();
}

// Legacy aliases
const toggleTorrentField = (id, field) => submitTorrentAction(id, field);
const deleteTorrentQuick = (id)        => submitTorrentAction(id, 'delete');

// ── Modal ─────────────────────────────────────────────────
// Раньше обработчик вешался на ВСЕ [data-bs-toggle="modal"], в том числе чужие кнопки
function initModal() {
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.torrent-btn[data-id]');
        if (!btn) return;

        const id      = parseInt(btn.dataset.id, 10);
        const content = document.getElementById('manageTorrentContent');
        if (!content || !id) return;

        content.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
                <p class="mt-3 text-body-secondary">Loading torrent #${id}…</p>
            </div>`;

        try {
            const res = await fetch(window.manageBaseUrl + '/admin/manage_torrents_ajax.php?id=' + id, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            content.innerHTML = await res.text();
        } catch (err) {
            content.innerHTML = `
                <div class="alert alert-danger d-flex gap-2 rounded-4 mb-0">
                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                    <div>Could not load torrent #${id}. Please try again.</div>
                </div>`;
        }
    });
}

// ── Init ──────────────────────────────────────────────────
// Раньше тут был scroll-обработчик, добавлявший класс «sticky» панели действий
// на каждый пиксель прокрутки. Панель теперь «липкая» через CSS (position: sticky).
document.addEventListener('DOMContentLoaded', () => {
    updateSelectionCounter();
    initModal();

    // Клик по строке (не по ссылке/кнопке) ставит галочку
    document.getElementById('torrentTableBody')?.addEventListener('click', e => {
        if (e.target.closest('a, button, input, label, select')) return;
        const cb = e.target.closest('.torrent-row')?.querySelector('.torrent-checkbox');
        if (cb) { cb.checked = !cb.checked; updateSelectionCounter(); }
    });

    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
    }
});

Object.assign(window, {
    toggleAllSelection, updateSelectionCounter, clearSelection,
    clearSearch, resetFilters, toggleMoveCategory,
    submitTorrentAction, toggleTorrentField, deleteTorrentQuick,
});