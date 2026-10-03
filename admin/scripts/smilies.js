/* Smilies - админка смайлов */
(() => {
    'use strict';

    const page = document.querySelector('.sm-page');
    if (!page) return;

    const smilieUrl = page.dataset.smilieUrl || '';
    const imgUrl = file => smilieUrl + '/' + encodeURIComponent(file);

    // ── Удаление (список и форма) ────────────────────────
    const delForm = document.getElementById('smDeleteForm');

    page.addEventListener('click', e => {
        const btn = e.target.closest('[data-delete]');
        if (!btn || !delForm) return;
        e.preventDefault();

        const title = btn.dataset.title || 'this smilie';
        const submit = () => {
            delForm.querySelector('input[name="sid"]').value = btn.dataset.delete;
            delForm.submit();
        };

        if (!window.Swal) {
            if (confirm('Delete "' + title + '"? This cannot be undone.')) submit();
            return;
        }
        const text = document.createElement('div');
        text.textContent = 'Delete "' + title + '"? Posts that use its code will show the plain text instead.';

        Swal.fire({
            title: 'Delete smilie?',
            html: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545',
            reverseButtons: true,
            focusCancel: true
        }).then(r => { if (r.isConfirmed) submit(); });
    });

    // ── Копирование кода ─────────────────────────────────
    page.addEventListener('click', async e => {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            btn.classList.add('is-copied');
            setTimeout(() => btn.classList.remove('is-copied'), 1200);
        } catch (_) { /* буфер недоступен (http) - ничего не делаем */ }
    });

    // ── Список: фильтр, перетаскивание, отметка изменений ─
    const grid = document.getElementById('smGrid');
    if (grid) {
        const items   = () => [...grid.querySelectorAll('.sm-item')];
        const filter  = document.getElementById('smFilter');
        const count   = document.getElementById('smCount');
        const noMatch = document.getElementById('smNoMatch');
        const bar     = document.getElementById('smSaveBar');
        const dirty   = document.getElementById('smDirty');
        const clean   = document.getElementById('smClean');
        const form    = document.getElementById('smOrderForm');

        const original = new Map(items().map(it => {
            const inp = it.querySelector('.sm-order input');
            return [inp.name, inp.value];
        }));

        function refreshDirty() {
            let changed = false;
            items().forEach(it => {
                const inp = it.querySelector('.sm-order input');
                const diff = original.get(inp.name) !== inp.value;
                inp.classList.toggle('is-changed', diff);
                if (diff) changed = true;
            });
            bar?.classList.toggle('is-dirty', changed);
            if (dirty) dirty.hidden = !changed;
            if (clean) clean.hidden = changed;
            return changed;
        }

        grid.addEventListener('input', e => {
            if (e.target.matches('.sm-order input')) refreshDirty();
        });

        // Фильтр. Пока он активен, перетаскивание выключено: порядок
        // скрытых карточек иначе перепутался бы.
        filter?.addEventListener('input', () => {
            const q = filter.value.trim().toLowerCase();
            let shown = 0;
            items().forEach(it => {
                const hit = !q || it.dataset.search.includes(q);
                it.hidden = !hit;
                if (hit) shown++;
            });
            grid.classList.toggle('is-filtered', !!q);
            if (count) count.textContent = shown + ' shown';
            if (noMatch) noMatch.hidden = shown > 0;
        });

        // Перетаскивание: после отпускания номера пересчитываются 10, 20, 30...
        let dragged = null;

        // Карточка становится перетаскиваемой только за ручку: если сделать
        // draggable всю карточку, в поле порядка нельзя выделить текст мышью.
        grid.addEventListener('mousedown', e => {
            const grip = e.target.closest('.sm-grip');
            if (grip && !grid.classList.contains('is-filtered')) grip.closest('.sm-item').draggable = true;
        });
        document.addEventListener('mouseup', () => {
            if (!dragged) items().forEach(i => { i.draggable = false; });
        });

        grid.addEventListener('dragstart', e => {
            const it = e.target.closest('.sm-item');
            if (!it || !it.draggable) return;
            dragged = it;
            it.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });

        grid.addEventListener('dragover', e => {
            if (!dragged) return;
            e.preventDefault();
            const over = e.target.closest('.sm-item');
            if (!over || over === dragged) return;

            items().forEach(i => i.classList.remove('is-drop-target'));
            over.classList.add('is-drop-target');

            const r = over.getBoundingClientRect();
            const after = (e.clientY - r.top) > r.height / 2
                || ((e.clientY - r.top) > r.height * 0.25 && (e.clientX - r.left) > r.width / 2);
            grid.insertBefore(dragged, after ? over.nextSibling : over);
        });

        const endDrag = () => {
            if (!dragged) return;
            dragged.classList.remove('is-dragging');
            dragged.draggable = false;
            items().forEach((it, i) => {
                it.classList.remove('is-drop-target');
                it.querySelector('.sm-order input').value = String((i + 1) * 10);
            });
            dragged = null;
            refreshDirty();
        };
        grid.addEventListener('drop', e => { e.preventDefault(); endDrag(); });
        grid.addEventListener('dragend', endDrag);

        // Не потерять несохранённый порядок при уходе со страницы
        let submitting = false;
        form?.addEventListener('submit', () => { submitting = true; });
        window.addEventListener('beforeunload', e => {
            if (!submitting && refreshDirty()) { e.preventDefault(); e.returnValue = ''; }
        });
    }

    // ── Форма: живое превью, выбор файла, "последний" ────
    const spath = document.getElementById('spath');
    if (spath) {
        const stitle   = document.getElementById('stitle');
        const stext    = document.getElementById('stext');
        const big      = document.getElementById('smPreviewImg');
        const bigEmpty = document.getElementById('smPreviewEmpty');
        const inline   = document.getElementById('smPreviewInline');
        const code     = document.getElementById('smPreviewCode');
        const title    = document.getElementById('smPreviewTitle');

        function showImage(ok) {
            big.hidden = !ok;
            bigEmpty.hidden = ok;
            inline.hidden = !ok;
            code.hidden = ok;
        }

        function updateImage() {
            const f = spath.value.trim();
            if (!f || f.includes('/') || f.includes('\\')) { showImage(false); return; }
            const src = imgUrl(f);
            big.onload  = () => showImage(true);
            big.onerror = () => showImage(false);
            big.src = src;
            inline.src = src;
        }

        let t;
        spath.addEventListener('input', () => { clearTimeout(t); t = setTimeout(updateImage, 250); });
        stitle?.addEventListener('input', () => { title.textContent = stitle.value.trim() || 'No title'; });
        stext?.addEventListener('input', () => { code.textContent = stext.value.trim() || ':)'; });

        document.getElementById('smAutoOrder')?.addEventListener('click', e => {
            document.getElementById('sorder').value = e.currentTarget.dataset.next;
        });

        // Выбор файла из модалки
        const picker = document.getElementById('smPicker');
        picker?.addEventListener('click', e => {
            const b = e.target.closest('.sm-pick');
            if (!b) return;
            spath.value = b.dataset.file;
            picker.querySelectorAll('.sm-pick').forEach(p => p.classList.toggle('is-current', p === b));
            updateImage();
            if (window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(picker).hide();
        });

        const pf = document.getElementById('smPickerFilter');
        pf?.addEventListener('input', () => {
            const q = pf.value.trim().toLowerCase();
            picker.querySelectorAll('.sm-pick').forEach(p => { p.hidden = !!q && !p.dataset.search.includes(q); });
        });
        picker?.addEventListener('shown.bs.modal', () => {
            pf?.focus();
            picker.querySelector('.sm-pick.is-current')?.scrollIntoView({ block: 'center' });
        });
    }

    // ── Импорт: имя файла и перетаскивание ───────────────
    const file = document.getElementById('smImportFile');
    if (file) {
        const drop = file.closest('.sm-drop');
        const name = document.getElementById('smImportName');
        const show = () => { name.textContent = file.files[0]?.name || 'Choose a JSON file'; };
        file.addEventListener('change', show);

        ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
        drop.addEventListener('drop', e => {
            e.preventDefault();
            if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; show(); }
        });
    }
})();
