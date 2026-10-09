/* Manage Avatars (staff panel) */
(() => {
    const form = document.getElementById('avatarForm');
    if (!form) return;

    // Lang: AGS_LANG is printed by manage_avatars.php (js_* keys without prefix)
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (key, fallback, ...args) => {
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1, v = String(a);
            s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v);
        });
        return s;
    };
    const esc = s => String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);

    const boxes      = () => Array.from(form.querySelectorAll('input[name="avatars[]"]'));
    const itemOf     = box => box.closest('.ma-item');
    const isVisible  = box => !itemOf(box).classList.contains('d-none');
    const selectAll  = document.getElementById('select_all');
    const countEl    = document.getElementById('selectedCount');
    const deleteBtn  = document.getElementById('deleteSelected');
    const clearBtn   = document.getElementById('clearSelection');
    const orphansBtn = document.getElementById('selectOrphans');
    const emptyEl    = document.getElementById('filterEmpty');

    function syncCard(box) {
        document.getElementById('card_' + box.id.slice(3))?.classList.toggle('selected', box.checked);
    }

    function refresh() {
        const all     = boxes();
        const checked = all.filter(b => b.checked).length;
        const shown   = all.filter(isVisible);

        if (countEl)   countEl.textContent = checked;
        if (deleteBtn) deleteBtn.disabled  = checked === 0;
        if (clearBtn)  clearBtn.disabled   = checked === 0;

        if (selectAll) {
            const shownChecked = shown.filter(b => b.checked).length;
            selectAll.checked       = shown.length > 0 && shownChecked === shown.length;
            selectAll.indeterminate = shownChecked > 0 && shownChecked < shown.length;
        }
    }

    function setBox(box, state) {
        box.checked = state;
        syncCard(box);
    }

    // Broken thumbnails -> default avatar (replaces inline onerror)
    const useFallback = img => {
        const fb = img.dataset.fallback;
        if (!fb || img.dataset.fellBack) return;
        img.dataset.fellBack = '1';
        img.src = fb;
    };
    form.addEventListener('error', ev => {
        if (ev.target instanceof HTMLImageElement) useFallback(ev.target);
    }, true);
    form.querySelectorAll('img[data-fallback]').forEach(img => {
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) useFallback(img);
    });

    // Card click / keyboard toggles selection
    form.querySelectorAll('.ma-card').forEach(card => {
        const box = card.querySelector('input[name="avatars[]"]');

        card.addEventListener('click', ev => {
            if (ev.target.closest('a, img, button, input')) return;
            setBox(box, !box.checked);
            refresh();
        });

        card.addEventListener('keydown', ev => {
            if (ev.target !== card) return;
            if (ev.key === ' ' || ev.key === 'Enter') {
                ev.preventDefault();
                setBox(box, !box.checked);
                refresh();
            }
        });

        box.addEventListener('change', () => { syncCard(box); refresh(); });
    });

    selectAll?.addEventListener('change', () => {
        boxes().filter(isVisible).forEach(b => setBox(b, selectAll.checked));
        refresh();
    });

    clearBtn?.addEventListener('click', () => {
        boxes().forEach(b => setBox(b, false));
        refresh();
    });

    orphansBtn?.addEventListener('click', () => {
        boxes().forEach(b => {
            if (itemOf(b).dataset.owner === '0' && isVisible(b)) setBox(b, true);
        });
        refresh();
    });

    // Filters (hidden items get unselected so nothing invisible is deleted)
    document.querySelectorAll('.ma-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ma-filter').forEach(b => b.classList.toggle('active', b === btn));
            const f = btn.dataset.filter;
            let shown = 0;

            boxes().forEach(box => {
                const item = itemOf(box);
                const show = f === 'all'
                    || (f === 'owned'   && item.dataset.owner === '1')
                    || (f === 'orphan'  && item.dataset.owner === '0')
                    || (f === 'flagged' && item.dataset.flag  === '1');
                item.classList.toggle('d-none', !show);
                if (show) shown++; else setBox(box, false);
            });

            emptyEl?.classList.toggle('d-none', shown > 0);
            refresh();
        });
    });

    // Confirm before delete
    let confirmed = false;
    form.addEventListener('submit', ev => {
        if (confirmed) return;
        ev.preventDefault();

        const selected = boxes().filter(b => b.checked);
        if (selected.length === 0) return;

        const owned   = selected.filter(b => itemOf(b).dataset.owner === '1').length;
        const orphans = selected.length - owned;

        const lines = [t('confirm_files', '{1} file(s) will be permanently deleted.', selected.length)];
        if (owned)   lines.push(t('confirm_owned', '{1} member(s) will lose their avatar.', owned));
        if (orphans) lines.push(t('confirm_orphans', '{1} orphaned file(s) have no owner.', orphans));

        const go = () => { confirmed = true; form.submit(); };

        if (typeof Swal === 'undefined') {
            if (window.confirm(lines.join('\n'))) go();
            return;
        }

        // Body built as DOM text nodes, not HTML
        const body = document.createElement('div');
        lines.forEach(l => {
            const row = document.createElement('div');
            row.textContent = l;
            body.appendChild(row);
        });

        Swal.fire({
            titleText: t('confirm_title', 'Delete selected avatars?'),
            html: body,
            icon: 'warning',
            showCancelButton: true,
            focusCancel: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa-solid fa-trash-can me-2"></i>' + esc(t('btn_delete', 'Delete')),
            cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i>' + esc(t('btn_cancel', 'Cancel'))
        }).then(r => { if (r.isConfirmed) go(); });
    });

    refresh();
})();
