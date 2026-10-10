// ── Who Posted modal ─────────────────────────────────────────────────────────
(function () {
    // t(key, englishFallback, ...args): AGS_LANG is emitted by PHP before this script
    function t(key, fallback, ...args) {
        var s = (typeof AGS_LANG !== 'undefined' && AGS_LANG && AGS_LANG[key] != null) ? String(AGS_LANG[key]) : fallback;
        return s.replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
            var n = a || b;
            return args[n - 1] === undefined ? m : String(args[n - 1]);
        });
    }

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
            var p = document.createElement('p');
            p.className = 'text-danger text-center py-4 px-3';
            p.textContent = t('whoposted_failed', 'Failed to load. Please try again.');
            content.innerHTML = '';
            content.appendChild(p);
        });
};
})();
