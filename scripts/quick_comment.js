/* ── i18n ─────────────────────────────────────────────────────────────────
   AGS_LANG is printed by the page (details.php) before the scripts.
   Missing dictionary/key -> English fallback. {1} and %1$s are both substituted
   ($lang->load() turns {1} into %1$s). Insert results as text, not HTML. */
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
    const str  = (dict && typeof dict[key] === 'string') ? dict[key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a || b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// Replaces the element content with <tag class="iconClass"></tag> + text node.
function agsIconText(el, iconClass, text, tag) {
    el.textContent = '';
    const icon = document.createElement(tag || 'i');
    icon.className = iconClass;
    el.appendChild(icon);
    el.appendChild(document.createTextNode(text));
}

// HTML-escape for the few places where a translation has to go into an HTML template.
function agsEsc(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

var l_ajaxerror = t('qc_ajax_error', "There was a problem with the request. Please report this to the administrator.");
var l_updateerror = t('qc_update_error', "There was an error performing the update.\n\nError message: ");

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
    // Свои анимации вместо animate.css (добавляются на страницу один раз)
    if (!document.getElementById('qcModalAnim')) {
        const st = document.createElement('style');
        st.id = 'qcModalAnim';
        st.textContent =
            '@keyframes qcZoomIn{from{opacity:0;transform:scale3d(.3,.3,.3)}50%{opacity:1}}' +
            '@keyframes qcFadeOut{from{opacity:1}to{opacity:0}}' +
            '.qc-zoom-in{animation:qcZoomIn .5s both}' +
            '.qc-fade-out{animation:qcFadeOut .8s both}';
        document.head.appendChild(st);
    }

    // Если прошлое окно ошибки ещё открыто — убираем, чтобы не было двух #errorModal
    const old = document.getElementById('errorModal');
    if (old) old.remove();

    const modalHTML = `
        <div class="modal fade" id="errorModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg qc-zoom-in">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>${agsEsc(t('qc_error_title', 'Error'))}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="${agsEsc(t('qc_close', 'Close'))}"></button>
                    </div>
                    <div class="modal-body bg-body-tertiary">
                        <p class="mb-0 text-danger fw-bold">${message}</p>
                    </div>
                    <div class="modal-footer bg-body-tertiary">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                            <i class="fa-solid fa-circle-xmark me-1"></i>${agsEsc(t('qc_close', 'Close'))}
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalElement  = document.getElementById('errorModal');
    const modalContent  = modalElement.querySelector('.modal-content');
    const modalInstance = new bootstrap.Modal(modalElement);
    modalInstance.show();

    // Через 5 с плавно гасим и закрываем; если закрыли раньше — таймеры отменяются
    let hideTimer;
    const fadeTimer = setTimeout(function () {
        modalContent.classList.remove('qc-zoom-in');
        modalContent.classList.add('qc-fade-out');
        hideTimer = setTimeout(function () { modalInstance.hide(); }, 800);
    }, 5000);

    modalElement.addEventListener('hidden.bs.modal', function () {
        clearTimeout(fadeTimer);
        clearTimeout(hideTimer);
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

    var myPostKeyInput = form ? form.querySelector('input[name="my_post_key"]') : null;
    var myPostKeyValue = myPostKeyInput ? myPostKeyInput.value : '';

    var pars = {
        ajax_quick_comment: 1,
        id: intval(TorrentID),
        text: urlencode(message),
        page: currentPage,
        posthash: posthashValue,
        my_post_key: myPostKeyValue
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
        if (!response.ok) throw new Error(t('qc_network', 'Network response was not ok'));
        return response.text();
    })
    .then(result => {
        var match = result.match(/<error>(.*)<\/error>/);
        if (match) {
            showModalError(agsEsc(l_updateerror) + (match[1] || agsEsc(l_ajaxerror)));
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

            // Очищаем превью вложений.
            // posthash НЕ меняем: виджет загрузки (initAttachmentUploader) запомнил
            // его при загрузке страницы и продолжает слать файлы со старым хешем.
            // Новый хеш в форме = файлы следующего коммента не находились
            // (comment_id оставался 0). Повторно использовать хеш безопасно:
            // attach_to_comment() берёт только непривязанные черновики (comment_id = 0).
            var attList = document.getElementById('attPreviewList-' + posthashValue);
            if (attList) attList.innerHTML = '';
        }
    })
    .catch(error => {
        showModalError(agsEsc(l_ajaxerror + "\n\n" + error.message));
    })
    .finally(() => {
        if (loadingLayer) loadingLayer.style.display = 'none';
        quickCommentButtons.forEach(function(button) {
            button.disabled = false;
        });
    });
}
