
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('universalImageModal');
    const img = document.getElementById('universalImagePreview');

    // Открытие модалки
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        const imgSrc = trigger.getAttribute('data-img-src');
        if (!imgSrc) return;

        img.src = imgSrc;
    });

    // Очистка при закрытии
    modal.addEventListener('hidden.bs.modal', function () {
        img.src = '';
        document.activeElement.blur(); // убирает aria warning
    });

    // Клик по картинке = закрыть
    img.addEventListener('click', function () {
        const instance = bootstrap.Modal.getInstance(modal);
        if (instance) instance.hide();
    });

});
