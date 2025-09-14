

var l_ajaxerror="There was a problem with the request. Please report this to administrator.";

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
    // Load animate.css dynamically if not already loaded
    if (!document.querySelector('link[href*="animate.min.css"]')) {
        let animateCSS = document.createElement('link');
        animateCSS.rel = 'stylesheet';
        animateCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
        document.head.appendChild(animateCSS);
    }

    // Build the modal HTML
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

    // Append modal to the body
    $('body').append(modalHTML);

    var modalElement = document.getElementById('errorModal');
    var modalContent = $(modalElement).find('.modal-content');

    // Initialize Bootstrap modal
    var modalInstance = new bootstrap.Modal(modalElement);
    modalInstance.show();

    // Auto close modal after 5 seconds with fadeOut animation
    setTimeout(function () {
        modalContent
            .removeClass('animate__zoomIn')
            .addClass('animate__fadeOut');

        // Wait for animation to finish before hiding modal
        setTimeout(function () {
            modalInstance.hide();
        }, 800); // match fadeOut animation duration
    }, 5000);

    // Remove modal from DOM after hiding
    $('#errorModal').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}















function TSajaxquickcomment(TorrentID) {
    var message = $('#message').val();
    var pars = {
        ajax_quick_comment: 1,
        id: intval(TorrentID),
        text: urlencode(message)
    };

    // Добавляем каждый file_ids[] в запрос, если они существуют
    const fileInputs = document.querySelectorAll('#fileIdsContainer input[name="file_ids[]"]');
    fileInputs.forEach((input, index) => {
        pars['file_ids[' + index + ']'] = input.value;
    });

    $('#loading-layer').show();
    $('#comment [name="quickcomment"]').prop('disabled', true);

    $.ajax({
        url: baseurl + "/xmlhttp.php?action=quick_comment",
        method: "POST",
        data: $.param(pars),
        contentType: "application/x-www-form-urlencoded; charset=" + charset,
        success: function (result) {
            var match = result.match(/<error>(.*)<\/error>/);
            if (match) {
                var errorMessage = match[1] || l_ajaxerror;
                showModalError(l_updateerror + errorMessage);
            } else {
                var newDiv = $('<div>', { id: 'PostedReply', html: result });
                $('#ajax_comment_preview').append(newDiv);
                $('#message').val('');
                $('#fileIdsContainer').empty(); // Очистим загруженные file_ids
            }
            $('#loading-layer').hide();
            $('#comment [name="quickcomment"]').prop('disabled', false);
        },
        error: function (xhr, status, error) {
            showModalError(l_ajaxerror + "\n\n" + error);
            $('#loading-layer').hide();
            $('#comment [name="quickcomment"]').prop('disabled', false);
        }
    });
}

