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

var userRating = 0;
var torrentId  = 0;
var ratingBaseUrl = '';

function ratingInit(uid, tid, baseUrl) {
    userRating   = uid;
    torrentId    = tid;
    ratingBaseUrl = baseUrl;

    document.querySelectorAll('.user-star').forEach(function(s, idx, all) {
        s.addEventListener('mouseover', function() {
            all.forEach(function(st, i) {
                st.style.color = i <= idx ? '#f59e0b' : '#dee2e6';
            });
        });
        s.addEventListener('mouseout', function() {
            all.forEach(function(st, i) {
                st.style.color = i < userRating ? '#f59e0b' : '#dee2e6';
            });
        });
    });
}

function rateTorrent(val) {
    var stars = document.querySelectorAll('.user-star');
    stars.forEach(function(s) {
        s.style.pointerEvents = 'none';
        s.style.opacity = '0.5';
    });

    fetch(ratingBaseUrl + '/xmlhttp.php?action=rate_torrent', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'torrent_id=' + torrentId + '&rating=' + val
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;

        userRating = val;
        stars.forEach(function(s, i) {
            s.style.pointerEvents = 'auto';
            s.style.opacity = '1';
            s.classList.toggle('active', i < val);
            s.style.color = i < val ? '#f59e0b' : '#dee2e6';
        });

        var scoreEl = document.querySelector('.rating-score');
        if (scoreEl) scoreEl.textContent = data.avg;

        var hintEl = document.getElementById('rating-hint');
        if (hintEl) hintEl.textContent = val + '/10';

        var displayEl = document.getElementById('rating-display');
        if (displayEl) {
            var html = '';
            for (var i = 1; i <= 10; i++) {
                if (data.avg >= i)       html += '<i class="bi bi-star-fill rating-star-filled"></i>';
                else if (data.avg >= i - 0.5) html += '<i class="bi bi-star-half rating-star-filled"></i>';
                else                     html += '<i class="bi bi-star rating-star-empty"></i>';
            }
            displayEl.innerHTML = html;
        }

        if (typeof showToast === 'function') showToast(t('rate_saved', 'Rating saved!'), 'success');
    });
}
function rateThread(val) {
    var stars = document.querySelectorAll('.user-star');
    stars.forEach(function(s) {
        s.style.pointerEvents = 'none';
        s.style.opacity = '0.5';
    });

    fetch(ratingBaseUrl + '/xmlhttp.php?action=rate_thread', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'tid=' + torrentId + '&rating=' + val
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;

        userRating = val;
        stars.forEach(function(s, i) {
            s.style.pointerEvents = 'auto';
            s.style.opacity = '1';
            s.classList.toggle('active', i < val);
            s.style.color = i < val ? '#f59e0b' : '#dee2e6';
        });

        var scoreEl = document.querySelector('.rating-score');
        if (scoreEl) scoreEl.textContent = data.avg;

        var hintEl = document.getElementById('rating-hint');
        if (hintEl) hintEl.textContent = val + '/10';

        var displayEl = document.getElementById('rating-display');
        if (displayEl) {
            var html = '';
            for (var i = 1; i <= 10; i++) {
                if (data.avg >= i)            html += '<i class="bi bi-star-fill rating-star-filled"></i>';
                else if (data.avg >= i - 0.5) html += '<i class="bi bi-star-half rating-star-filled"></i>';
                else                          html += '<i class="bi bi-star rating-star-empty"></i>';
            }
            displayEl.innerHTML = html;
        }

        if (typeof showToast === 'function') showToast(t('rate_saved', 'Rating saved!'), 'success');
    });
}