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

        if (typeof showToast === 'function') showToast('Rating saved!', 'success');
    });
}