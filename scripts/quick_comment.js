var l_ajaxerror = "There was a problem with the request. Please report this to administrator.";
var l_updateerror = "There was an error performing the update.\n\nError Message:";

function intval(mixed_var, base) {
    var tmp;
    if (typeof(mixed_var) === 'string') {
        tmp = parseInt(mixed_var * 1);
        if (isNaN(tmp) || !isFinite(tmp)) {
            return 0;
        } else {
            return parseInt(tmp.toString(), base || 10);
        }
    } else if (typeof(mixed_var) === 'number' && isFinite(mixed_var)) {
        return Math.floor(mixed_var);
    } else {
        return 0;
    }
}

function urlencode(str) {
    return encodeURIComponent(str.toString()).replace(/%20/g, '+');
}

function showModalError(message) {
    if (!document.querySelector('link[href*="animate.min.css"]')) {
        let animateCSS = document.createElement('link');
        animateCSS.rel = 'stylesheet';
        animateCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
        document.head.appendChild(animateCSS);
    }

    var modalHTML = `
        <div class="modal fade" id="errorModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg animate__animated animate__zoomIn">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Error
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <p class="mb-0 text-danger fw-bold">${message}</p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    var modalElement = document.getElementById('errorModal');
    var modalContent = modalElement.querySelector('.modal-content');
    var modalInstance = new bootstrap.Modal(modalElement);
    modalInstance.show();

    setTimeout(function () {
        modalContent.classList.remove('animate__zoomIn');
        modalContent.classList.add('animate__fadeOut');
        setTimeout(function () {
            modalInstance.hide();
        }, 800);
    }, 5000);

    modalElement.addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

function TSajaxquickcomment(TorrentID) {
    var messageElement = document.getElementById('message');
    var message = messageElement ? messageElement.value : '';

    var pageInput = document.querySelector('input[name="page"]');
    var currentPage = pageInput ? parseInt(pageInput.value) || 1 : 1;

    var form = document.getElementById('comment');
    var posthashInput = form ? form.querySelector('input[name="posthash"]') : null;
    var posthashValue = posthashInput ? posthashInput.value : '';

    var pars = {
        ajax_quick_comment: 1,
        id: intval(TorrentID),
        text: urlencode(message),
        page: currentPage,
        posthash: posthashValue
    };

    // file_ids
    const fileInputs = document.querySelectorAll('#fileIdsContainer input[name="file_ids[]"]');
    fileInputs.forEach((input, index) => {
        pars['file_ids[' + index + ']'] = input.value;
    });

    // Показываем loading
    var loadingLayer = document.getElementById('loading-layer');
    if (loadingLayer) loadingLayer.style.display = 'block';

    var quickCommentButtons = document.querySelectorAll('#comment [name="quickcomment"]');
    quickCommentButtons.forEach(function(button) {
        button.disabled = true;
    });

    var formData = new FormData();
    for (var key in pars) {
        if (pars.hasOwnProperty(key)) {
            formData.append(key, pars[key]);
        }
    }

    fetch(baseurl + "/xmlhttp.php?action=quick_comment", {
        method: "POST",
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
    })
    .then(result => {
        var match = result.match(/<error>(.*)<\/error>/);
        if (match) {
            showModalError(l_updateerror + (match[1] || l_ajaxerror));
        } else {
            var redirectMatch = result.match(/<redirect>(.*?)<\/redirect>/);
            if (redirectMatch) {
                window.location.href = redirectMatch[1];
                return;
            }

            var ajaxCommentPreview = document.getElementById('ajax_comment_preview');
            if (ajaxCommentPreview) {
                var newDiv = document.createElement('div');
                newDiv.id = 'PostedReply';
                newDiv.innerHTML = result;
                ajaxCommentPreview.appendChild(newDiv);
            }

            // Очищаем текст
            if (messageElement) messageElement.value = '';

            // Очищаем file_ids
            var fileIdsContainer = document.getElementById('fileIdsContainer');
            if (fileIdsContainer) fileIdsContainer.innerHTML = '';

            // Очищаем превью вложений
            var oldPosthash = posthashInput ? posthashInput.value : '';
            var attList = document.getElementById('attPreviewList-' + oldPosthash);
            if (attList) attList.innerHTML = '';

            // Генерируем новый posthash
            if (posthashInput) {
                var newHash = [...crypto.getRandomValues(new Uint8Array(16))]
                    .map(b => b.toString(16).padStart(2, '0')).join('');
                posthashInput.value = newHash;
                // Обновляем data-posthash на виджете
                var uploader = document.querySelector('.comment-attachments-uploader');
                if (uploader) uploader.dataset.posthash = newHash;
            }
        }
    })
    .catch(error => {
        showModalError(l_ajaxerror + "\n\n" + error.message);
    })
    .finally(() => {
        if (loadingLayer) loadingLayer.style.display = 'none';
        quickCommentButtons.forEach(function(button) {
            button.disabled = false;
        });
    });
}