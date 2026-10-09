/* Manage Torrents — JS страницы (вынесено из manage_torrents.php).
 * Подключается ДО manage_torrents.js: глобалы window.* нужны ему сразу. */
(function () {
    'use strict';

    // ── Переводы: const AGS_LANG выводит manage_torrents.php перед скриптами ──
    // t('key', 'English fallback', arg1, arg2…) → подстановка {1}, {2}… за один проход
    const t = (key, fallback, ...args) => {
        const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
        const str  = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : String(fallback ?? key);
        return str.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] !== undefined ? String(args[n - 1]) : m));
    };

    // ── Конфиг из <script type="application/json" id="mtConfig"> ──
    let cfg = {};
    const cfgEl = document.getElementById('mtConfig');
    if (cfgEl) {
        try { cfg = JSON.parse(cfgEl.textContent || '{}'); } catch (e) { cfg = {}; }
    }
    window.manageBaseUrl       = String(cfg.baseUrl ?? '');
    window.manageTorrentScript = String(cfg.script ?? '');
    window.torrentCount        = Number(cfg.torrentCount ?? 0);
    window.totalTorrents       = Number(cfg.totalTorrents ?? 0);

    // ── Делегированные вызовы вместо inline onclick/onchange ──
    // data-mt-call="fnName"            → fnName(element)
    // data-mt-call="fnName" data-mt-args='[1,"x"]' → fnName(1, "x")
    // Функции живут в manage_torrents.js, поэтому ищем их в window в момент события.
    const callFrom = (el) => {
        const fn = window[el.dataset.mtCall];
        if (typeof fn !== 'function') return;
        let args = [el];
        if (el.dataset.mtArgs !== undefined) {
            try { args = JSON.parse(el.dataset.mtArgs); } catch (e) { return; }
        }
        fn.apply(el, args);
    };

    const isChangeTarget = (el) => el.tagName === 'SELECT' || (el.tagName === 'INPUT' && (el.type === 'checkbox' || el.type === 'radio'));

    document.addEventListener('click', (e) => {
        // Копирование в буфер (кнопки в модалке, контент приходит по AJAX)
        const copyBtn = e.target.closest('[data-mt-copy]');
        if (copyBtn) {
            const input = copyBtn.closest('.input-group')?.querySelector('input');
            if (input && navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(() => {
                    copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                });
            }
            return;
        }
        const el = e.target.closest('[data-mt-call]');
        if (el && !isChangeTarget(el)) callFrom(el);
    });

    document.addEventListener('change', (e) => {
        const el = e.target.closest('[data-mt-call]');
        if (el && isChangeTarget(el)) callFrom(el);
    });

    document.addEventListener('DOMContentLoaded', () => {
        // ── Тост об успешном действии (PRG) ──
        if (typeof cfg.successMsg === 'string' && cfg.successMsg !== '') {
            const msg = cfg.successMsg;
            if (typeof showToast === 'function') {
                showToast(msg.replace(/[<>&]/g, ''), 'success');
            } else {
                const box = document.createElement('div');
                box.className = 'position-fixed bottom-0 end-0 p-3 mt-toast-host';
                box.innerHTML = '<div class="toast show border-0 shadow" role="alert"><div class="toast-body d-flex align-items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i><span></span></div></div>';
                box.querySelector('span').textContent = msg;
                document.body.appendChild(box);
                setTimeout(() => box.remove(), 5000);
            }
        }

        // ── Подтверждение опасных массовых действий ──
        const form = document.getElementById('torrentForm');
        const actionSelect = document.getElementById('actionType');
        const confirmModalEl = document.getElementById('bulkConfirmModal');
        if (!form || !actionSelect || !confirmModalEl || typeof bootstrap === 'undefined') return;
        const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);

        const destructiveActions = {
            delete: {
                title:   t('bulk_delete_title', 'Delete selected torrents?'),
                message: t('bulk_delete_msg', 'The torrent files, images, screenshots, comments and all related data are removed permanently.'),
            },
            banned: {
                title:   t('bulk_ban_title', 'Toggle ban on selected torrents?'),
                message: t('bulk_ban_msg', 'Banned torrents are hidden from users and cannot be downloaded.'),
            },
        };

        let confirmed = false;
        form.addEventListener('submit', (e) => {
            if (confirmed) { confirmed = false; return; }
            const c = destructiveActions[actionSelect.value];
            if (!c) return;
            e.preventDefault();
            const count = document.querySelectorAll('.torrent-checkbox:checked').length;
            document.getElementById('bulkConfirmTitle').textContent = c.title;
            const countText = count === 1
                ? t('bulk_selected_one', '1 torrent selected')
                : t('bulk_selected_many', '{1} torrents selected', count);
            document.getElementById('bulkConfirmMessage').textContent = c.message + ' (' + countText + ')';
            confirmModal.show();
        });
        document.getElementById('bulkConfirmBtn').addEventListener('click', () => {
            confirmed = true;
            confirmModal.hide();
            form.requestSubmit();
        });
    });
})();
