(function () {
'use strict';

// window.newsPostKey задаётся отдельным маленьким инлайн-скриптом в
// news.php (PHP-значение нельзя вынести в статичный файл) - здесь просто
// читаем его.
var newsPostKey = window.newsPostKey || '';

// ── Char counter ──────────────────────────────────────────────────────────────
var ta  = document.getElementById('newsMessage');
var cnt = document.getElementById('charCount');
if (ta && cnt) {
    ta.addEventListener('input', function () {
        var l = this.value.length;
        cnt.textContent = l;
        cnt.classList.toggle('text-danger', l > 4800);
        this.classList.toggle('is-invalid', l > 5000);
    });
}

// ── Notification ──────────────────────────────────────────────────────────────
function showNotification(message, type) {
    var cls = type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info');
    var ico = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    var n   = document.createElement('div');
    n.className = 'alert alert-' + cls + ' alert-dismissible fade-in-up position-fixed top-0 start-50 translate-middle-x mt-3';
    n.style.cssText = 'z-index:9999;min-width:300px;max-width:500px;box-shadow:0 5px 20px rgba(0,0,0,.2)';
    n.innerHTML = '<i class="fas ' + ico + ' me-2"></i>' + message +
                  '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(n);
    setTimeout(function () { if (n.parentNode) n.remove(); }, 3500);
}

// ── Update count / empty state ────────────────────────────────────────────────
function updateCount() {
    var cards = document.querySelectorAll('.news-card').length;
    var span  = document.getElementById('newsCount');
    if (span) span.textContent = cards;
    var list  = document.getElementById('newsList');
    if (cards === 0 && list && !list.querySelector('.empty-state')) {
        list.innerHTML =
            '<div class="text-center py-5 empty-state fade-in-up" id="emptyState">' +
            '<i class="fas fa-newspaper fa-4x text-muted mb-3"></i>' +
            '<h4 class="text-muted">No News Available</h4>' +
            '<p class="text-muted">Create your first news article!</p></div>';
    }
}

// ── Add news ──────────────────────────────────────────────────────────────────
var addForm = document.getElementById('newsAddForm');
if (addForm) {
    addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = this.querySelector('button[type="submit"]');
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Publishing...';
        btn.disabled  = true;

        var fd = new FormData(this);
        fd.append('action', 'add');
        fd.append('my_post_key', newsPostKey);

        fetch('news_ajax.php', { method: 'POST', body: new URLSearchParams(fd) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification('News published successfully!', 'success');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    showNotification(d.error || 'Failed to add news', 'error');
                    btn.innerHTML = orig;
                    btn.disabled  = false;
                }
            })
            .catch(function () {
                showNotification('Network error. Please try again.', 'error');
                btn.innerHTML = orig;
                btn.disabled  = false;
            });
    });
}

// ── Event delegation: edit & delete ──────────────────────────────────────────
var newsList = document.getElementById('newsList');
if (newsList) {
    newsList.addEventListener('click', function (e) {
        // ── Delete ────────────────────────────────────────────────────────────
        var delBtn = e.target.closest('.news-delete');
        if (delBtn) {
            e.preventDefault();
            var card  = delBtn.closest('.news-card');
            var id    = card ? card.dataset.newsid : null;
            var title = card ? (card.querySelector('.card-title') || {}).textContent || '' : '';
            if (!id) { showNotification('Invalid news item', 'error'); return; }

            if (!confirm('Delete "' + title.trim() + '"?\nThis cannot be undone.')) return;

            var origHTML    = delBtn.innerHTML;
            delBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';
            delBtn.disabled  = true;

            fetch('news_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&newsid=' + encodeURIComponent(id) + '&my_post_key=' + encodeURIComponent(newsPostKey)
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    card.style.transition = 'all .3s ease';
                    card.style.opacity    = '0';
                    card.style.transform  = 'translateY(-10px)';
                    setTimeout(function () { card.remove(); updateCount(); showNotification('News deleted!', 'success'); }, 300);
                } else {
                    showNotification(d.error || 'Failed to delete', 'error');
                    delBtn.innerHTML = origHTML;
                    delBtn.disabled  = false;
                }
            })
            .catch(function () {
                showNotification('Network error.', 'error');
                delBtn.innerHTML = origHTML;
                delBtn.disabled  = false;
            });
            return;
        }

        // ── Edit ──────────────────────────────────────────────────────────────
        var editBtn = e.target.closest('.news-edit');
        if (editBtn) {
            e.preventDefault();
            var card  = editBtn.closest('.news-card');
            var id    = card ? card.dataset.newsid : null;
            var title = card ? (card.querySelector('.card-title') || {}).textContent || '' : '';
            var body  = card ? (card.dataset.body || '') : '';
            if (!id) { showNotification('Invalid news item', 'error'); return; }

            document.getElementById('editNewsId').value = id;
            document.getElementById('editTitle').value  = title.trim();
            document.getElementById('editBody').value   = body;
            updatePreview();

            new bootstrap.Modal(document.getElementById('newsEditModal')).show();
        }
    });
}

// ── BBCode wrap ───────────────────────────────────────────────────────────────
window.wrapBB = function (open, close) {
    var ta  = document.getElementById('editBody');
    if (!ta) return;
    var s   = ta.selectionStart, en = ta.selectionEnd;
    var sel = ta.value.substring(s, en);
    ta.value = ta.value.substring(0, s) + open + sel + close + ta.value.substring(en);
    ta.focus();
    ta.setSelectionRange(s + open.length, s + open.length + sel.length);
    updatePreview();
};

// Экранирует сырой HTML, чтобы он не мог интерпретироваться как разметка.
// ДОЛЖНО выполняться до любой замены BBCode на HTML ниже - иначе вставленный
// пользователем <script> или любой другой тег выполнился бы прямо в превью.
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        switch (ch) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
        }
    });
}

let newsSpoilerCounter = 0;

// ── Live preview ──────────────────────────────────────────────────────────────
function updatePreview() {
    var ta  = document.getElementById('editBody');
    var pre = document.getElementById('bbcodeNewsPreview');
    if (!ta || !pre) return;

    if (!ta.value.trim()) {
        pre.innerHTML = '<small class="text-muted">Preview will appear here...</small>';
        return;
    }

    // Экранируем HTML первым делом, ДО разбора BBCode (см. escapeHtml выше).
    pre.innerHTML = escapeHtml(ta.value)
        .replace(/\[b\]([\s\S]*?)\[\/b\]/g,         '<strong>$1</strong>')
        .replace(/\[i\]([\s\S]*?)\[\/i\]/g,         '<em>$1</em>')
        .replace(/\[u\]([\s\S]*?)\[\/u\]/g,         '<u>$1</u>')
        .replace(/\[s\]([\s\S]*?)\[\/s\]/g,         '<s>$1</s>')
        .replace(/\[left\]([\s\S]*?)\[\/left\]/g,   '<div style="text-align:left">$1</div>')
        .replace(/\[center\]([\s\S]*?)\[\/center\]/g,'<div style="text-align:center">$1</div>')
        .replace(/\[right\]([\s\S]*?)\[\/right\]/g, '<div style="text-align:right">$1</div>')
        .replace(/\[color=(.*?)\]([\s\S]*?)\[\/color\]/g, '<span style="color:$1">$2</span>')
        .replace(/\[size=(\d+)\]([\s\S]*?)\[\/size\]/g,   '<span style="font-size:$1px">$2</span>')
        .replace(/\[url\]([\s\S]*?)\[\/url\]/g,     '<a href="$1" target="_blank">$1</a>')
        .replace(/\[url=(.*?)\]([\s\S]*?)\[\/url\]/g,'<a href="$1" target="_blank">$2</a>')
        .replace(/\[img=([1-9]\d*)x([1-9]\d*)\]([\s\S]*?)\[\/img\]/g, '<img src="$3" alt="" class="rounded" width="$1" height="$2">')
        .replace(/\[img\]([\s\S]*?)\[\/img\]/g,     '<img src="$1" alt="" class="rounded" style="max-width:400px">')
        .replace(/\[video\]([\s\S]*?)\[\/video\]/g, '<video controls style="max-width:300px; width:100%; height:auto;"><source src="$1" type="video/mp4"></video>')
        .replace(/\[youtube\]([\s\S]*?)\[\/youtube\]/g, '<iframe width="100%" height="315" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen referrerpolicy="no-referrer"></iframe>')
        .replace(/\[quote\]([\s\S]*?)\[\/quote\]/g, '<blockquote class="border-start border-3 border-primary ps-3 my-2">$1</blockquote>')
        .replace(/\[code\]([\s\S]*?)\[\/code\]/g,   '<code class="bg-dark text-light p-2 rounded d-block">$1</code>')
        .replace(/\[list\]([\s\S]*?)\[\/list\]/g,   '<ul>$1</ul>')
        .replace(/\[list=1\]([\s\S]*?)\[\/list\]/g, '<ol>$1</ol>')
        .replace(/\[\*\](.*?)(?=\n|$)/g,            '<li>$1</li>')
        .replace(/\[spoiler\]([\s\S]*?)\[\/spoiler\]/g, function (_, content) {
            const id = 'news-preview-spoiler-' + (++newsSpoilerCounter);
            return '<div class="mycode_spoiler my-2">'
                 + '<a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#' + id + '" role="button" aria-expanded="false" aria-controls="' + id + '">'
                 + '<i class="fa-solid fa-eye"></i> Spoiler (click to show)'
                 + '</a>'
                 + '<div class="collapse mt-2 p-2 border rounded bg-light" id="' + id + '">'
                 + content
                 + '</div>'
                 + '</div>';
        })
        .replace(/\[torrent=(\d+)\]/g, '<div class="mycode_torrent_card card d-inline-block my-2" data-torrent-preview-id="$1" style="max-width:420px;"><div class="card-body py-2 px-3 text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading torrent #$1...</div></div>')
        .replace(/\n/g, '<br>');

    loadTorrentEmbedPreviews(pre);
}

// [torrent=ID] требует данных с сервера - рендерим плейсхолдер сразу вместе
// с остальным BBCode (синхронно), а карточку подгружаем отдельно через
// ajax_torrent_preview.php и точечно заменяем содержимое плейсхолдера.
function loadTorrentEmbedPreviews(container) {
    container.querySelectorAll('[data-torrent-preview-id]').forEach(function (el) {
        const id = el.getAttribute('data-torrent-preview-id');
        fetch((window.newsBaseUrl || '') + '/ajax_torrent_preview.php?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    el.innerHTML = '<div class="card-body py-2 px-3 text-danger small"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + escapeHtml(data.error) + '</div>';
                    return;
                }
                const img = data.image
                    ? '<img src="' + escapeHtml(data.image) + '" class="card-img-top" style="height:100px;object-fit:cover;">'
                    : '';
                el.innerHTML = img
                    + '<div class="card-body py-2 px-3">'
                    + '<div class="fw-bold text-truncate small"><i class="fa-solid fa-magnet me-1"></i>' + escapeHtml(data.name) + '</div>'
                    + '<div class="text-muted small">' + escapeHtml(data.catname) + ' &middot; ' + escapeHtml(data.size)
                    + ' &middot; <span class="text-success">' + data.seeders + ' seeders</span>'
                    + ' &middot; <span class="text-danger">' + data.leechers + ' leechers</span>'
                    + '</div>';
            })
            .catch(function () {
                el.innerHTML = '<div class="card-body py-2 px-3 text-danger small">Failed to load preview</div>';
            });
    });
}

window.updateNewsPreview = updatePreview;

document.getElementById('editBody')?.addEventListener('input', updatePreview);

document.getElementById('newsEditModal')?.addEventListener('shown.bs.modal', function () {
    updatePreview();
    document.getElementById('editBody')?.focus();
});

// ── Submit edit ───────────────────────────────────────────────────────────────
window.submitNewsEdit = function () {
    var id    = (document.getElementById('editNewsId') || {}).value || '';
    var title = (document.getElementById('editTitle')  || {}).value || '';
    var body  = (document.getElementById('editBody')   || {}).value || '';

    if (!title.trim()) { showNotification('Title cannot be empty.', 'error'); return; }
    if (!body.trim())  { showNotification('Content cannot be empty.', 'error'); return; }

    var btn  = document.getElementById('saveEditBtn');
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    btn.disabled  = true;

    var fd = new FormData();
    fd.append('action', 'edit');
    fd.append('newsid', id);
    fd.append('title',  title);
    fd.append('body',   body);
    fd.append('my_post_key', newsPostKey);

    fetch('news_ajax.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification('News updated!', 'success');
                setTimeout(function () { location.reload(); }, 900);
            } else {
                showNotification(d.error || 'Edit failed.', 'error');
                btn.innerHTML = orig;
                btn.disabled  = false;
            }
        })
        .catch(function () {
            showNotification('Network error.', 'error');
            btn.innerHTML = orig;
            btn.disabled  = false;
        });
};

// ── Torrent embed (inline panel в модалке Edit, не отдельная модалка) ──────────
// Достаём ID из чего угодно: голого числа, полного URL (torrent-17.html),
// query-параметра (?id=17) или ссылки со всем этим внутри.
function extractTorrentIdEdit(raw) {
    raw = raw || '';
    var m = raw.match(/torrent-(\d+)\.html/i)
         || raw.match(/[?&](?:id|tid)=(\d+)/i)
         || raw.match(/(\d+)/);
    return m ? m[1] : '';
}

var torrentPanelToggleEdit = document.getElementById('torrentPanelToggleEdit');
var torrentPanelEdit       = document.getElementById('torrentPanelEdit');
var torrentBtnEdit         = document.getElementById('insertTorrentBtnEdit');
var torrentInputEdit       = document.getElementById('torrentIdInputEdit');
var torrentPreviewEdit     = document.getElementById('torrentPreviewEdit');
var torrentTimerEdit       = null;

// Клик по кнопке "Torrent" в тулбаре - показать/скрыть встроенную панель.
if (torrentPanelToggleEdit && torrentPanelEdit) {
    torrentPanelToggleEdit.addEventListener('click', function () {
        torrentPanelEdit.classList.toggle('d-none');
        if (!torrentPanelEdit.classList.contains('d-none') && torrentInputEdit) {
            torrentInputEdit.focus();
        }
    });
}

if (torrentInputEdit && torrentPreviewEdit) {
    torrentInputEdit.addEventListener('input', function () {
        clearTimeout(torrentTimerEdit);
        var id = extractTorrentIdEdit(torrentInputEdit.value);
        if (!id) {
            torrentPreviewEdit.innerHTML = '';
            return;
        }
        torrentTimerEdit = setTimeout(function () {
            torrentPreviewEdit.innerHTML = '<div class="text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading preview...</div>';
            fetch((window.newsBaseUrl || '') + '/ajax_torrent_preview.php?id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) {
                        torrentPreviewEdit.innerHTML = '<div class="text-danger small"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + escapeHtml(data.error) + '</div>';
                        return;
                    }
                    var img = data.image
                        ? '<img src="' + escapeHtml(data.image) + '" class="card-img-top" style="height:100px;object-fit:cover;">'
                        : '';
                    torrentPreviewEdit.innerHTML =
                        '<div class="card">' + img +
                        '<div class="card-body py-2 px-3">' +
                        '<div class="fw-bold text-truncate small"><i class="fa-solid fa-magnet me-1"></i>' + escapeHtml(data.name) + '</div>' +
                        '<div class="text-muted small">' + escapeHtml(data.catname) + ' &middot; ' + escapeHtml(data.size) +
                        ' &middot; <span class="text-success">' + data.seeders + ' seeders</span>' +
                        ' &middot; <span class="text-danger">' + data.leechers + ' leechers</span>' +
                        '</div></div></div>';
                })
                .catch(function () {
                    torrentPreviewEdit.innerHTML = '<div class="text-danger small">Failed to load preview</div>';
                });
        }, 400);
    });
}

if (torrentBtnEdit && torrentInputEdit) {
    var doInsertTorrentEdit = function () {
        var id = extractTorrentIdEdit(torrentInputEdit.value);
        if (!id) {
            torrentInputEdit.focus();
            return;
        }
        wrapBB('[torrent=' + id + ']', '');
        torrentInputEdit.value = '';
        if (torrentPreviewEdit) torrentPreviewEdit.innerHTML = '';
        if (torrentPanelEdit) torrentPanelEdit.classList.add('d-none');
    };
    torrentBtnEdit.addEventListener('click', doInsertTorrentEdit);
    torrentInputEdit.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doInsertTorrentEdit();
        }
    });
}

})();