document.addEventListener("DOMContentLoaded", function() {
    // Инициализация popover
    var popoverElements = document.querySelectorAll('[data-toggle="popover"]');
    popoverElements.forEach(function(element) {
        element.addEventListener('mouseenter', function() {
            var imgSrc = element.getAttribute('data-img');
            var popoverContent = '<img class="rounded" border="0" width="250" src="' + imgSrc + '" />';
            // Здесь можно создать поповер с изображением
            showPopover(element, popoverContent);
        });

        element.addEventListener('mouseleave', function() {
            // Скрытие поповера при выходе мыши
            hidePopover();
        });
    });

    // Функции для отображения и скрытия поповера
    function showPopover(element, content) {
        var popover = document.createElement('div');
        popover.classList.add('popover');
        popover.innerHTML = content;
        document.body.appendChild(popover);

        var rect = element.getBoundingClientRect();
        popover.style.position = 'absolute';
        popover.style.top = rect.top + window.scrollY + rect.height + 'px';
        popover.style.left = rect.left + window.scrollX + 'px';
    }

    function hidePopover() {
        var popover = document.querySelector('.popover');
        if (popover) {
            popover.remove();
        }
    }
});
