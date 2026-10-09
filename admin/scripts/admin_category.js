/* ArtCore Gangsta — Staff panel: Category Manager (admin/category.php) */
(function () {
    'use strict';

    function escapeHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    }

    // ── Ланг: AGS_LANG выводит admin/category.php (ключи js_* без префикса) ──
    // $lang->load() превращает {1} в %1$s, поэтому подставляем оба формата.
    const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    function t(key, fallback, ...args) {
        const str = typeof L[key] === 'string' ? L[key] : fallback;
        return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
            const i = Number(a || b) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }
    // Английские fallback для подписей в модалке редактирования
    const FB = {
        lbl_name:      'Name',
        lbl_parent:    'Parent category',
        lbl_icon:      'Icon',
        hint_has_subs: 'Has subcategories — must stay a main category',
    };
    function alertBox(text) {
        const d = document.createElement('div');
        d.className = 'alert alert-danger';
        d.textContent = text;
        return d;
    }

    // ── Выбор иконки — через делегирование, в пределах СВОЕГО .cm-icon-picker ──
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.cm-ico-toggle');
        if (toggle) {
            const grid = toggle.closest('.cm-icon-picker').querySelector('.cm-ico-grid');
            grid.hidden = !grid.hidden;
            return;
        }
        const opt = e.target.closest('.cm-ico-opt');
        if (opt) {
            const picker = opt.closest('.cm-icon-picker');
            const input = picker.querySelector('input[name="icon"]');
            input.value = opt.dataset.icon;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            picker.querySelector('.cm-ico-grid').hidden = true;
        }
    });

    document.addEventListener('input', function (e) {
        if (!e.target.matches('.cm-icon-picker input[name="icon"]')) return;
        const picker = e.target.closest('.cm-icon-picker');
        const val = e.target.value.trim();
        const i = document.createElement('i');
        i.className = val || 'fa-solid fa-icons';
        picker.querySelector('.cm-ico-preview').replaceChildren(i);
        picker.querySelectorAll('.cm-ico-opt').forEach(b => b.classList.toggle('is-active', b.dataset.icon === val));
    });

    document.addEventListener('DOMContentLoaded', function () {
        // Подтверждение удаления (?do=delete&id=…) — модалка уже в разметке
        const del = document.getElementById('deleteModal');
        if (del) bootstrap.Modal.getOrCreateInstance(del).show();

        // Остальное — только на странице списка
        const cfgEl = document.getElementById('cmConfig');
        if (!cfgEl) return;
        let cfg;
        try { cfg = JSON.parse(cfgEl.textContent); } catch (err) { console.error('cmConfig', err); return; }
        const { dropdownHtml, iconSelectorHtml, baseScript } = cfg;

        // Фильтр карточек
        const f = document.getElementById('cmFilter');
        f && f.addEventListener('input', function () {
            const q = f.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('#cmGrid .cm-cat-col').forEach(c => {
                const ok = !q || c.dataset.search.includes(q);
                c.hidden = !ok; if (ok) shown++;
            });
            document.getElementById('cmNoMatch').hidden = shown !== 0;
        });

        // Добавление подкатегории — подставляем родителя в модалку
        document.querySelectorAll('.add-subcategory-btn').forEach(btn => btn.addEventListener('click', function () {
            document.getElementById('parentCategoryId').value = this.dataset.id;
            document.getElementById('parentCategoryName').textContent = this.dataset.name;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addSubcategoryModal')).show();
        }));

        // Редактирование — данные подгружаются AJAX-ом
        document.querySelectorAll('.edit-category-btn').forEach(btn => btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const body = document.getElementById('editCategoryModalBody');
            const label = document.getElementById('editCategoryModalLabel');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editCategoryModal')).show();
            body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';

            fetch(baseScript + '&do=ajax_get_category&id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(data => {
                    if (data.error) { body.replaceChildren(alertBox(data.error)); return; }
                    body.innerHTML = `
                        <input type="hidden" name="do" value="edit">
                        <input type="hidden" name="what" value="save">
                        <input type="hidden" name="id" value="${escapeHtml(data.id)}">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label"><i class="fa-solid fa-tag"></i><span data-cm-t="lbl_name"></span> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" value="${escapeHtml(data.name)}" required></div>
                            <div class="col-md-6"><label class="form-label"><i class="fa-solid fa-sitemap"></i><span data-cm-t="lbl_parent"></span></label>${dropdownHtml}
                                ${data.has_subs ? '<div class="form-text text-warning"><i class="fa-solid fa-lock me-1"></i><span data-cm-t="hint_has_subs"></span></div>' : ''}</div>
                            <div class="col-12"><label class="form-label"><i class="fa-solid fa-icons"></i><span data-cm-t="lbl_icon"></span></label>${iconSelectorHtml}</div>
                        </div>`;
                    // Переводы — только текстом
                    body.querySelectorAll('[data-cm-t]').forEach(el => { el.textContent = t(el.dataset.cmT, FB[el.dataset.cmT] ?? ''); });
                    const sel = body.querySelector('select[name="cid"]');
                    if (sel) {
                        // сама себе не родитель; с подкатегориями — только «None»
                        sel.querySelector('option[value="' + CSS.escape(String(data.id)) + '"]')?.remove();
                        sel.value = data.pid || 0;
                        if (data.has_subs) sel.disabled = true;
                    }
                    const icon = body.querySelector('input[name="icon"]');
                    if (icon) { icon.value = data.icon || ''; icon.dispatchEvent(new Event('input', { bubbles: true })); }
                    label.textContent = t('edit_title', 'Edit “{1}”', data.name || '');
                })
                .catch(err => { body.replaceChildren(alertBox(t('err_load', 'Error loading category data'))); console.error(err); });
        }));
    });
})();
