document.addEventListener('DOMContentLoaded', function () {
    // Копирование IP / порта по клику
    document.querySelectorAll('.dc .dc-copy[data-copy]').forEach(function (el) {
        el.addEventListener('click', function () {
            const text = el.dataset.copy;
            if (!text || !navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(function () {
                const icon = el.querySelector('i');
                const prev = icon ? icon.className : '';
                el.classList.add('is-copied');
                if (icon) icon.className = 'fas fa-check me-1';
                setTimeout(function () {
                    el.classList.remove('is-copied');
                    if (icon) icon.className = prev;
                }, 1200);
            });
        });
    });
});
