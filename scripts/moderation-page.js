// moderation.php — мелкие обработчики страниц «нового стиля» (без inline-скриптов в шаблоне).
(function () {
    'use strict';

    // Объединение постов: предпросмотр разделителя (#hrPreview / #newlinePreview)
    function syncSeparator() {
        const checked = document.querySelector('input[name="sep"]:checked');
        const hr = document.getElementById('hrPreview');
        const nl = document.getElementById('newlinePreview');
        if (!checked || !hr || !nl) return;
        hr.classList.toggle('d-none', checked.value !== 'hr');
        nl.classList.toggle('d-none', checked.value !== 'new_line');
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'sep') syncSeparator();
    });
    document.addEventListener('DOMContentLoaded', syncSeparator);
})();
