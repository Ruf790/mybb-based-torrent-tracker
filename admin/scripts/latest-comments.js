/**
 * latest_comments.php — UI страницы Comments Admin.
 * Переключатель встроенной панели вставки торрента в модалке редактирования.
 */
'use strict';

(function () {
    const toggleBtn = document.getElementById('torrentPanelToggle');
    const panel = document.getElementById('torrentPanel');
    if (toggleBtn && panel) {
        toggleBtn.addEventListener('click', function () {
            panel.classList.toggle('d-none');
            toggleBtn.classList.toggle('active', !panel.classList.contains('d-none'));
            if (!panel.classList.contains('d-none')) {
                const input = document.getElementById('torrentIdInput');
                if (input) input.focus();
            }
        });
    }
})();
