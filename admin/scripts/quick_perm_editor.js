/**
 * QuickPermEditor — перенос прав между зонами Allowed / Denied в таблице прав
 * (admin/management.php: главная вкладка Permissions, Add/Edit Forum, строка после AJAX-сохранения).
 *
 * Разметка строки:
 *   <tr data-group-id="{gid}">
 *     #enabled-{gid} / #disabled-{gid}  — зоны с .permission-badge[data-perm]
 *     #fields_{gid}, #fields_inherit_{gid}, #fields_default_{gid} — скрытые поля формы
 *
 * Публичный API прежний: init(id), initAll(), debounceInitAll(), resetAllToInherited(),
 * window.resetPermissions(), validateFormFields().
 */
/** Строка из AGS_LANG (js_* без префикса) с английским fallback и подстановкой {1}, {2}… */
function qpeT(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
    let str = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
    args.forEach((a, i) => { str = str.split('{' + (i + 1) + '}').join(String(a)); });
    return str;
}

/** Иконка + текст в элементе (текст — только через textNode) */
function qpeSetIconText(el, iconClass, text) {
    const i = document.createElement('i');
    i.className = iconClass;
    el.replaceChildren(i, document.createTextNode(text));
}

/** showToast() из toast.js вставляет сообщения через innerHTML — экранируем */
function qpeEscape(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

const QuickPermEditor = {
    /** Английские подписи — fallback; перевод — getPermissionLabel() → AGS_LANG.qpe_perm_* */
    labels: {
        canview:        'View',
        canpostthreads: 'Post Threads',
        canpostreplys:  'Post Replies',
        canpostpolls:   'Post Polls'
    },

    /** Текущее перетаскивание: dataTransfer.getData() недоступен в dragover, поэтому храним здесь */
    _drag: null,

    init: function (id) {
        id = String(id); // из dataset приходит строка, из popup.js/серверных init — число

        const enabled  = document.getElementById('enabled-' + id);
        const disabled = document.getElementById('disabled-' + id);
        const fields   = document.getElementById('fields_' + id);
        if (!enabled || !disabled || !fields) return;

        // Повторный init (initAll после AJAX и т.п.) не должен навешивать обработчики второй раз
        [enabled, disabled].forEach(zone => {
            if (zone._qpeBound === id) return;
            zone._qpeBound = id;
            this.setupDropZone(zone, id);
            this.setupClickHandler(zone, id);
        });
        this.makeBadgesDraggable(id);
    },

    /** Все бейджи группы (обе зоны) */
    badgesOf: function (id) {
        return document.querySelectorAll('#enabled-' + id + ' .permission-badge, #disabled-' + id + ' .permission-badge');
    },

    makeBadgesDraggable: function (id) {
        this.badgesOf(id).forEach(badge => {
            badge.setAttribute('draggable', 'true');
            if (badge._qpeDrag) return;
            badge._qpeDrag = true;

            badge.addEventListener('dragstart', e => {
                this._drag = { id, perm: badge.dataset.perm, source: badge.parentElement };
                e.dataTransfer.setData('text/plain', badge.dataset.perm || '');
                e.dataTransfer.effectAllowed = 'move';
                badge.classList.add('dragging');
            });
            badge.addEventListener('dragend', () => {
                badge.classList.remove('dragging');
                document.querySelectorAll('.qpe-over').forEach(z => z.classList.remove('qpe-over'));
                this._drag = null;
            });
        });
    },

    setupDropZone: function (zone, id) {
        const accepts = () => this._drag && this._drag.id === id && this._drag.source !== zone;

        zone.addEventListener('dragover', e => {
            if (!this._drag || this._drag.id !== id) return; // чужая группа — дроп запрещён
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (accepts()) zone.classList.add('qpe-over');
        });
        zone.addEventListener('dragleave', e => {
            if (!zone.contains(e.relatedTarget)) zone.classList.remove('qpe-over');
        });
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('qpe-over');
            if (!accepts()) return;

            const badge = Array.from(this._drag.source.querySelectorAll('.permission-badge'))
                .find(b => b.dataset.perm === this._drag.perm);
            if (!badge) return;
            this.moveBadge(id, badge, zone);
        });
    },

    setupClickHandler: function (zone, id) {
        zone.addEventListener('click', e => {
            const badge = e.target.closest('.permission-badge');
            if (!badge || !zone.contains(badge)) return;
            const target = zone.id.startsWith('enabled-')
                ? document.getElementById('disabled-' + id)
                : document.getElementById('enabled-' + id);
            if (target) this.moveBadge(id, badge, target);
        });
    },

    /** Перенос бейджа в зону + пересчёт скрытых полей и статуса строки */
    moveBadge: function (id, badge, zone) {
        zone.appendChild(badge);
        this.updateBadgeClasses(badge, zone);
        this.buildFieldsList(id);
        this.updatePermissionStatus(id, false);
    },

    updateBadgeClasses: function (badge, zone) {
        const on = zone.id.startsWith('enabled-');
        badge.classList.remove('bg-success', 'text-success', 'bg-danger', 'text-danger');
        badge.classList.add('bg-opacity-10', on ? 'bg-success' : 'bg-danger', on ? 'text-success' : 'text-danger');
    },

    buildFieldsList: function (id) {
        const enabled = document.getElementById('enabled-' + id);
        const fields  = document.getElementById('fields_' + id);
        const inherit = document.getElementById('fields_inherit_' + id);
        if (!enabled || !fields) return;

        fields.value = Array.from(enabled.querySelectorAll('.permission-badge'))
            .map(b => b.dataset.perm)
            .filter(Boolean)
            .join(',');
        if (inherit) inherit.value = '0';
    },

    rowOf: function (id) {
        return document.querySelector('tr[data-group-id="' + id + '"]');
    },

    /** Метка источника: новая разметка .fm2-tag (t-sub / t-cat) или старый .badge bg-info / bg-warning */
    updatePermissionStatus: function (id, isInherited) {
        const row = this.rowOf(id);
        if (!row) return;

        const tag = row.querySelector('.fm2-tag.t-sub, .fm2-tag.t-cat');
        if (tag) {
            tag.className = 'fm2-tag ' + (isInherited ? 't-sub' : 't-cat');
            if (isInherited) qpeSetIconText(tag, 'fa-solid fa-arrow-turn-down', qpeT('qpe_inherited', 'Inherited'));
            else             qpeSetIconText(tag, 'fa-solid fa-sliders', qpeT('qpe_custom', 'Custom'));
        } else {
            const badge = row.querySelector('.badge.bg-info, .badge.bg-warning');
            if (badge) {
                badge.className = 'badge ' + (isInherited ? 'bg-info bg-opacity-10 text-info' : 'bg-warning bg-opacity-10 text-warning') + ' px-3 py-2';
                if (isInherited) qpeSetIconText(badge, 'fas fa-link me-1', qpeT('qpe_inherited', 'Inherited'));
                else             qpeSetIconText(badge, 'fas fa-pen me-1', qpeT('qpe_custom', 'Custom'));
            }
        }

        if (isInherited) this.removeClearButton(id);
        else this.addClearButton(id, row);
    },

    /**
     * Кнопка «отменить изменения» для строки, изменённой в браузере (ещё не сохранённой).
     * Класс .qpe-revert-btn, а не .clear-permission-btn: тот открывает серверную модалку
     * удаления прав (scripts/forum_management.js) и требует data-pid.
     */
    addClearButton: function (id, row) {
        const cell = row.querySelector('td:last-child');
        if (!cell) return;
        // Серверные custom-права уже имеют свою кнопку сброса
        if (cell.querySelector('.clear-permission-btn, .qpe-revert-btn')) return;

        const legacy = cell.querySelector('.btn-group');
        const btn = document.createElement('a');
        btn.href = 'javascript:void(0);';
        btn.className = legacy
            ? 'btn btn-outline-danger btn-sm ms-1 qpe-revert-btn'
            : 'fm2-act text-danger qpe-revert-btn';
        btn.title = qpeT('qpe_undo_title', 'Undo changes (back to inherited)');
        btn.innerHTML = '<i class="fas fa-rotate-left"></i>';
        btn.addEventListener('click', e => {
            e.preventDefault();
            this.clearPermissions(id);
        });

        (legacy || cell).appendChild(btn);
    },

    removeClearButton: function (id) {
        this.rowOf(id)?.querySelector('.qpe-revert-btn')?.remove();
    },

    confirmAction: function (text, onConfirm) {
        if (window.Swal && typeof Swal.fire === 'function') {
            Swal.fire({
                text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: qpeT('qpe_yes_reset', 'Yes, reset'),
                cancelButtonText: qpeT('cancel', 'Cancel')
            }).then(r => { if (r.isConfirmed) onConfirm(); });
        } else if (confirm(text)) {
            onConfirm();
        }
    },

    /** Вернуть одну группу к значениям, с которыми страница была загружена, и пометить как inherited */
    revertGroup: function (id) {
        const defaults = document.getElementById('fields_default_' + id);
        const fields   = document.getElementById('fields_' + id);
        const inherit  = document.getElementById('fields_inherit_' + id);
        if (!defaults || !fields) return false;

        const list = defaults.value.split(',').filter(Boolean);
        this.resetPermissionsToDefault(id, list);
        fields.value = list.join(',');
        if (inherit) inherit.value = '1';
        this.updatePermissionStatus(id, true);
        return true;
    },

    clearPermissions: function (id) {
        id = String(id);
        this.confirmAction(qpeT('qpe_confirm_revert', 'Revert this group to inherited permissions?'), () => {
            if (this.revertGroup(id)) this.showNotification(qpeT('qpe_reverted', 'Permissions reset to inherited values'), 'info');
        });
    },

    /** Перестроить обе зоны: права из defaults — в Allowed, остальные — в Denied */
    resetPermissionsToDefault: function (id, defaults) {
        const enabled  = document.getElementById('enabled-' + id);
        const disabled = document.getElementById('disabled-' + id);
        if (!enabled || !disabled) return;

        enabled.innerHTML = '';
        disabled.innerHTML = '';
        Object.keys(this.labels).forEach(perm => {
            const on = defaults.includes(perm);
            (on ? enabled : disabled).appendChild(this.createPermissionBadge(perm, this.getPermissionLabel(perm), on));
        });
        this.makeBadgesDraggable(id);
    },

    /** Та же разметка, что у серверных бейджей (fm_perm_row) */
    createPermissionBadge: function (perm, label, enabled = true) {
        const badge = document.createElement('span');
        badge.className = 'badge ' + (enabled ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger') + ' me-1 mb-1 permission-badge';
        badge.dataset.perm = perm;
        badge.setAttribute('draggable', 'true');
        badge.textContent = label;
        return badge;
    },

    getPermissionLabel: function (perm) {
        return qpeT('qpe_perm_' + perm, this.labels[perm] || perm);
    },

    showNotification: function (message, type = 'success') {
        if (typeof window.showToast === 'function' && (type === 'success' || type === 'error')) {
            window.showToast([qpeEscape(message)], type);
            return;
        }
        let box = document.getElementById('notification-container');
        if (!box) {
            box = document.createElement('div');
            box.id = 'notification-container';
            box.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:350px;';
            document.body.appendChild(box);
        }
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
        alert.role = 'alert';
        const icon = document.createElement('i');
        icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'info-circle') + ' me-2';
        alert.append(icon, document.createTextNode(message));
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', qpeT('close', 'Close'));
        alert.appendChild(close);
        box.appendChild(alert);
        setTimeout(() => alert.remove(), 3000);
    },

    initAll: function () {
        document.querySelectorAll('tr[data-group-id]').forEach(row => {
            if (row.dataset.groupId) this.init(row.dataset.groupId);
        });
    },

    debounceInitAll: (() => {
        let timeout;
        return function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => QuickPermEditor.initAll(), 100);
        };
    })(),

    resetAllToInherited: function () {
        this.confirmAction(qpeT('qpe_confirm_reset_all', 'Reset all permission changes to inherited values?'), () => {
            document.querySelectorAll('tr[data-group-id]').forEach(row => this.revertGroup(row.dataset.groupId));
            this.showNotification(qpeT('qpe_reset_all_done', 'All permissions reset to inherited values'), 'success');
        });
        return false;
    },

    validateFormFields: function () {
        let ok = true;
        document.querySelectorAll('tr[data-group-id]').forEach(row => {
            if (!document.getElementById('fields_' + row.dataset.groupId)) {
                console.error('QuickPermEditor: missing fields_' + row.dataset.groupId);
                ok = false;
            }
        });
        return ok;
    },

    addStyles: function () {
        if (document.getElementById('quick-perm-editor-styles')) return;
        const style = document.createElement('style');
        style.id = 'quick-perm-editor-styles';
        style.textContent = `
            .permission-badge[draggable="true"] { cursor: grab; user-select: none; }
            .permission-badge.dragging { opacity: .5; cursor: grabbing; }
            .enabled-permissions, .disabled-permissions { border-radius: .6rem; transition: background-color .12s ease, outline-color .12s ease; outline: 2px dashed transparent; outline-offset: -2px; }
            .qpe-over { outline-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .08); }
            @keyframes qpeSlideIn { from { transform: translateX(100%); opacity: 0; } to { transform: none; opacity: 1; } }
            #notification-container .alert { animation: qpeSlideIn .3s ease; box-shadow: 0 4px 12px rgba(0,0,0,.15); border: none; border-radius: 8px; }
        `;
        document.head.appendChild(style);
    },

    initEditor: function () {
        this.addStyles();
        window.resetPermissions = () => this.resetAllToInherited();
        this.debounceInitAll();
    }
};

// Инициализация всех строк tr[data-group-id] на странице
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => QuickPermEditor.initEditor());
} else {
    QuickPermEditor.initEditor();
}