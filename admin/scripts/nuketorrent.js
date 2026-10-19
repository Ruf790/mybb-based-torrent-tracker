/* Nuke Torrent — admin/nuketorrent.php
   AGS_LANG is printed by the page before this script (js_* lang keys without the prefix). */
(function () {
    'use strict';

    function t(key, fallback) {
        const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
        return (dict && typeof dict[key] === 'string') ? dict[key] : fallback;
    }

    function init() {
        const box   = document.getElementById('ntReason');
        const count = document.getElementById('ntCount');
        const hint  = document.getElementById('ntHint');
        const btn   = document.getElementById('ntSubmit');
        const chips = document.querySelectorAll('.nt-chip');
        const min = 6;
        const max = box ? (parseInt(box.getAttribute('maxlength'), 10) || 255) : 255;

        function update() {
            const len = box.value.trim().length;
            count.textContent = box.value.length + ' / ' + max;
            const ok = len >= min;
            btn.disabled = !ok;
            hint.classList.toggle('is-bad', len > 0 && !ok);
            chips.forEach(c => c.classList.toggle('active', c.dataset.reason === box.value.trim()));
        }

        if (box && count && hint && btn) {
            chips.forEach(chip => chip.addEventListener('click', () => {
                box.value = chip.dataset.reason;
                box.focus();
                update();
            }));
            box.addEventListener('input', update);
            update();
        }

        // Unnuke: SweetAlert2 confirmation with a plain confirm() fallback
        const unnukeBtn  = document.getElementById('ntUnnukeBtn');
        const unnukeForm = document.getElementById('ntUnnukeForm');
        if (unnukeBtn && unnukeForm) {
            unnukeBtn.addEventListener('click', () => {
                const title = t('unnuke_title', 'Remove the nuke?');
                const text  = t('unnuke_text', 'The torrent will be shown as normal again and the reason will be cleared.');

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: title,
                        text: text,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: t('unnuke_confirm', 'Yes, unnuke'),
                        cancelButtonText: t('cancel', 'Cancel'),
                        confirmButtonColor: 'var(--bs-success)',
                    }).then(res => { if (res.isConfirmed) unnukeForm.submit(); });
                } else if (window.confirm(title + '\n\n' + text)) {
                    unnukeForm.submit();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
