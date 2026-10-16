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
var ratingPostKey = '';

/* CSRF key: 4th argument of ratingInit(), otherwise taken from the page
   (MyBB's global my_post_key or any <input name="my_post_key">). */
function ratingGetPostKey() {
    if (ratingPostKey) return ratingPostKey;
    if (typeof my_post_key !== 'undefined' && my_post_key) return String(my_post_key);
    var inp = document.querySelector('input[name="my_post_key"]');
    if (inp && inp.value) return inp.value;
    var meta = document.querySelector('meta[name="my_post_key"]');
    return meta ? (meta.getAttribute('content') || '') : '';
}

/* ratingInit(rating, id, baseUrl, postKey)            — torrent (details.php)
   ratingInit(rating, id, baseUrl, 'thread', postKey)  — forum thread (showthread.php)
   The 'thread'/'torrent' label is only a marker and is skipped. */
function ratingInit(uid, tid, baseUrl, postKey, postKey2) {
    if (postKey === 'thread' || postKey === 'torrent') postKey = postKey2;
    userRating    = uid;
    torrentId     = tid;
    ratingBaseUrl = baseUrl;
    ratingPostKey = postKey || '';

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

// Torrent and thread rating share everything except the action and the id field
function rateTorrent(val) { ratingSend('rate_torrent', 'torrent_id', val); }
function rateThread(val)  { ratingSend('rate_thread',  'tid',        val); }

function ratingSend(action, idField, val) {
    var stars = document.querySelectorAll('.user-star');
    var setStars = function (rating) {
        stars.forEach(function(s, i) {
            s.style.pointerEvents = 'auto';
            s.style.opacity = '1';
            s.classList.toggle('active', i < rating);
            s.style.color = i < rating ? '#f59e0b' : '#dee2e6';
        });
    };
    stars.forEach(function(s) {
        s.style.pointerEvents = 'none';
        s.style.opacity = '0.5';
    });

    var body = new URLSearchParams();
    body.set(idField, String(torrentId));
    body.set('rating', String(val));
    body.set('my_post_key', ratingGetPostKey());

    fetch(ratingBaseUrl + '/xmlhttp.php?action=' + action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data || !data.success) {
            setStars(userRating);   // unlock the stars, keep the previous rating
            if (typeof showToast === 'function') {
                showToast((data && data.error) || t('rate_error', 'Could not save your rating. Reload the page and try again.'), 'error');
            }
            return;
        }

        userRating = val;
        setStars(val);

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
    })
    .catch(function() {
        setStars(userRating);
        if (typeof showToast === 'function') showToast(t('rate_error', 'Could not save your rating. Reload the page and try again.'), 'error');
    });
}