'use strict';

// ── Selection ─────────────────────────────────────────────
function toggleAllSelection(checkbox) {
    document.querySelectorAll('.torrent-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateSelectionCounter();
}

function updateSelectionCounter() {
    const selected = document.querySelectorAll('.torrent-checkbox:checked').length;
    const counter  = document.getElementById('selectedCounter');
    const execBtn  = document.getElementById('executeBtn');

    if (counter) counter.textContent = selected + ' selected';
    if (execBtn) execBtn.disabled = selected === 0;

    document.querySelectorAll('.torrent-row').forEach(row => {
        const cb = row.querySelector('.torrent-checkbox');
        row.classList.toggle('selected', cb?.checked ?? false);
    });
}

function clearSelection() {
    document.querySelectorAll('.torrent-checkbox').forEach(cb => cb.checked = false);
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    updateSelectionCounter();
}

// ── Filters ───────────────────────────────────────────────
function clearSearch() {
    const input = document.getElementById('torrent-search');
    if (input) input.value = '';
    document.getElementById('searchForm')?.submit();
}

function resetFilters() {
    window.location.href = window.manageTorrentScript + '?act=manage_torrents';
}

function toggleMoveCategory(select) {
    const div = document.getElementById('moveCategory');
    if (div) div.style.display = select.value === 'move' ? 'inline-block' : 'none';
}

// ── Quick actions (объединены из двух дублирующихся функций) ─
function submitTorrentAction(id, action) {
    const label = action === 'delete'
        ? `⚠️ WARNING: Delete torrent #${id}?\nThis cannot be undone!`
        : `Toggle "${action}" for torrent #${id}?`;

    if (!confirm(label)) return;

    const form = Object.assign(document.createElement('form'), {
        method: 'POST',
        action: window.location.href,
        style: 'display:none',
    });

    const fields = { do: 'update', actiontype: action };
    Object.entries(fields).forEach(([k, v]) => {
        form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: k, value: v }));
    });

    const id_input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'torrentid[]', value: id });
    form.appendChild(id_input);

    document.body.appendChild(form);
    form.submit();
}

// Legacy aliases
const toggleTorrentField  = (id, field)  => submitTorrentAction(id, field);
const deleteTorrentQuick  = (id)         => submitTorrentAction(id, 'delete');

// ── Sticky bulk bar ───────────────────────────────────────
window.addEventListener('scroll', () => {
    const bar = document.getElementById('bulkActionsBar');
    if (bar) bar.classList.toggle('sticky', bar.getBoundingClientRect().top <= 0);
});

// ── Modal ─────────────────────────────────────────────────
function initModal() {
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id      = this.dataset.id;
            const content = document.getElementById('manageTorrentContent');
            if (!content) return;

            content.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading torrent #${id}...</p>
                </div>`;

            try {
                const res  = await fetch(window.manageBaseUrl + '/admin/manage_torrents_ajax.php?id=' + id);
                const html = await res.text();
                content.innerHTML = html;
            } catch (e) {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading torrent data. Please try again.
                    </div>`;
            }
        });
    });
}

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    updateSelectionCounter();
    initModal();

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        if (typeof bootstrap !== 'undefined') new bootstrap.Tooltip(el);
    });
});

// Expose globals
Object.assign(window, {
    toggleAllSelection, updateSelectionCounter, clearSelection,
    clearSearch, resetFilters, toggleMoveCategory,
    submitTorrentAction, toggleTorrentField, deleteTorrentQuick,
});
