// ── Who Posted modal ─────────────────────────────────────────────────────────
window.whoPosted = function (tid) {
    // Создаём модалку один раз
    if (!document.getElementById('whoPostedModal')) {
        var el = document.createElement('div');
        el.className = 'modal fade';
        el.id = 'whoPostedModal';
        el.setAttribute('tabindex', '-1');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">' +
                '<div class="modal-content border-0 shadow" id="whoPostedContent">' +
                    '<div class="text-center py-5">' +
                        '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(el);
    }

    var modal = new bootstrap.Modal(document.getElementById('whoPostedModal'));
    modal.show();

    whoPostedLoad(tid, 'posts');
};

window.whoPostedLoad = function (tid, sort) {
    var content = document.getElementById('whoPostedContent');
    content.innerHTML =
        '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
        '</div>';

    fetch(baseurl + '/misc.php?action=whoposted&tid=' + tid + '&sort=' + sort + '&modal=1')
        .then(function (r) { return r.text(); })
        .then(function (html) { content.innerHTML = html; })
        .catch(function () {
            content.innerHTML =
                '<p class="text-danger text-center py-4 px-3">Failed to load. Please try again.</p>';
        });
};
