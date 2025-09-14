document.addEventListener('DOMContentLoaded', function () {
    var universalModal = document.getElementById('universalImageModal');
    var previewImg = document.getElementById('universalImagePreview');
    var titleEl = document.getElementById('universalImageModalTitle');
    var sizeEl = document.getElementById('universalImageSize');
    var dimEl = document.getElementById('universalImageDimensions');
    var downloadBtn = document.getElementById('universalDownloadBtn');

    universalModal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        var imgSrc = trigger.getAttribute('data-img-src');
        var imgTitle = trigger.getAttribute('data-title');

        previewImg.src = imgSrc;
        titleEl.innerHTML = '<i class="bi bi-image text-primary"></i> ' + imgTitle;
        downloadBtn.href = imgSrc;

        // Получаем размеры картинки
        var tempImg = new Image();
        tempImg.onload = function () {
            dimEl.textContent = this.width + ' × ' + this.height + ' px';
        };
        tempImg.src = imgSrc;

        // Получаем размер файла
        fetch(imgSrc, { method: 'HEAD' }).then(res => {
            var size = res.headers.get('Content-Length');
            if (size) {
                var kb = (size / 1024).toFixed(1);
                sizeEl.textContent = kb + ' KB';
            }
        });
    });

    // Полноэкранный режим
    document.getElementById('universalFullscreenBtn').addEventListener('click', function () {
        if (previewImg.requestFullscreen) {
            previewImg.requestFullscreen();
        } else if (previewImg.webkitRequestFullscreen) {
            previewImg.webkitRequestFullscreen();
        }
    });
});