/**
 * Attachment Types Manager (admin/attachment_types.php)
 * Список: фильтр, модалка удаления. Форма: «все/выбранные/никто», предпросмотр иконки.
 */
(function () {
    'use strict';

    // ── Все / выбранные / никто ───────────────────────────────
    function checkAction(id) {
        const checked = document.querySelector('.' + id + '_forums_groups_check:checked');
        const box = document.getElementById(id + '_forums_groups_custom');
        if (box) box.style.display = (checked && checked.value === 'custom') ? 'block' : 'none';
    }

    function initSelections() {
        ['groups', 'forums'].forEach(id => {
            document.querySelectorAll('.' + id + '_forums_groups_check')
                .forEach(r => r.addEventListener('change', () => checkAction(id)));
            checkAction(id);
        });
    }

    // ── Предпросмотр иконки ───────────────────────────────────
    // Берём только <i class="..." style="color:..."> — без выполнения произвольного HTML
    function initIconPreview() {
        const input = document.getElementById('icon');
        const box = document.getElementById('atIconPreview');
        if (!input || !box) return;

        function preview() {
            const v = input.value.trim();
            const span = document.createElement('span');
            span.className = 'at-icon at-preview';
            const cls = (v.match(/class\s*=\s*["']([^"']+)["']/i) || [])[1];
            const col = (v.match(/color\s*:\s*([#\w(),.\s%]+?)\s*[;"']/i) || [])[1];
            const i = document.createElement('i');
            if (v.startsWith('<') && cls) {
                i.className = cls;
                if (col) i.style.color = col;
            } else {
                span.classList.add('at-icon-empty');
                i.className = 'fa-solid fa-file';
            }
            span.appendChild(i);
            box.replaceChildren(span);
        }

        input.addEventListener('input', preview);
        document.querySelectorAll('.at .at-preset').forEach(b => b.addEventListener('click', () => {
            input.value = b.dataset.icon;
            preview();
        }));
        preview();
    }

    // ── Фильтр таблицы ────────────────────────────────────────
    function initFilter() {
        const f = document.getElementById('atFilter');
        const noMatch = document.getElementById('atNoMatch');
        if (!f) return;

        f.addEventListener('input', function () {
            const q = f.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('.at .at-table tbody tr[data-search]').forEach(tr => {
                const ok = !q || tr.dataset.search.includes(q);
                tr.hidden = !ok;
                if (ok) shown++;
            });
            if (noMatch) noMatch.hidden = shown !== 0;
        });
    }

    // ── Модалка удаления ──────────────────────────────────────
    function initDeleteModal() {
        const modal = document.getElementById('atDeleteModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            if (!btn) return;
            document.getElementById('atDeleteId').value = btn.dataset.atid;
            document.getElementById('atDeleteExt').textContent = btn.dataset.ext;
        });
    }

    function init() {
        initSelections();
        initIconPreview();
        initFilter();
        initDeleteModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
