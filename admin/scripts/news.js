(function () {
'use strict';

// window.newsPostKey задаётся отдельным маленьким инлайн-скриптом в
// news.php (PHP-значение нельзя вынести в статичный файл) - здесь просто
// читаем его.
var newsPostKey = window.newsPostKey || '';

/* i18n: t(key, fallback, ...args) — AGS_LANG comes from news.php (js_* keys
   without the prefix); substitutes {1} and %1$s. The result is plain text:
   insert it with textContent, or escapeHtml() it before any HTML. */
function t(key, fallback) {
    var s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string')
        ? AGS_LANG[key] : fallback;
    for (var i = 2; i < arguments.length; i++) {
        var n = i - 1, a = String(arguments[i]);
        s = s.split('{' + n + '}').join(a).split('%' + n + '$s').join(a);
    }
    return s;
}

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

function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;
    return node;
}

// Button with a spinner + label (label inserted as text)
function setBusy(btn, label) {
    btn.textContent = '';
    btn.appendChild(el('i', 'fa-solid fa-spinner fa-spin me-2'));
    if (label) btn.appendChild(document.createTextNode(label));
}

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

// ── Notification (message comes from the server / lang: inserted as text) ────
function showNotification(message, type) {
    var cls = type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info');
    var ico = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
    var n   = el('div', 'alert alert-' + cls + ' alert-dismissible fade-in-up position-fixed top-0 start-50 translate-middle-x mt-3 nws-toast');
    n.setAttribute('role', 'alert');
    n.appendChild(el('i', 'fa-solid ' + ico + ' me-2'));
    n.appendChild(document.createTextNode(String(message ?? '')));
    var close = el('button', 'btn-close');
    close.type = 'button';
    close.setAttribute('data-bs-dismiss', 'alert');
    n.appendChild(close);
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
        var box = el('div', 'nws-card nws-empty empty-state fade-in-up');
        box.id = 'emptyState';
        var ico = el('div', 'nws-icon-sq nws-icon-lg nws-soft-secondary mx-auto mb-3');
        ico.appendChild(el('i', 'fa-solid fa-newspaper'));
        box.appendChild(ico);
        box.appendChild(el('h4', 'nws-empty__title', t('empty_title', 'No News Available')));
        box.appendChild(el('p', 'nws-muted mb-0', t('empty_text', 'Create your first news article!')));
        list.textContent = '';
        list.appendChild(box);
    }
}

// ── Add news ──────────────────────────────────────────────────────────────────
var addForm = document.getElementById('newsAddForm');
if (addForm) {
    addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = this.querySelector('button[type="submit"]');
        var orig = btn.innerHTML;
        setBusy(btn, t('publishing', 'Publishing…'));
        btn.disabled = true;

        var fd = new FormData(this);
        fd.append('action', 'add');
        fd.append('my_post_key', newsPostKey);

        fetch('news_ajax.php', { method: 'POST', body: new URLSearchParams(fd) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification(t('published', 'News published!'), 'success');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    showNotification(d.error || t('add_failed', 'Failed to publish the news.'), 'error');
                    btn.innerHTML = orig;
                    btn.disabled  = false;
                }
            })
            .catch(function () {
                showNotification(t('network', 'Network error. Please try again.'), 'error');
                btn.innerHTML = orig;
                btn.disabled  = false;
            });
    });
}

// ── Delete confirmation: SweetAlert2, confirm() fallback ─────────────────────
function confirmDelete(title, onYes) {
    if (!window.Swal) {
        if (confirm(t('delete_title', 'Delete this news article?') + '\n' +
                    t('delete_text', '"{1}" will be removed permanently. This cannot be undone.', title))) {
            onYes();
        }
        return;
    }
    Swal.fire({
        icon: 'warning',
        titleText: t('delete_title', 'Delete this news article?'),
        text: t('delete_text', '"{1}" will be removed permanently. This cannot be undone.', title),
        showCancelButton: true,
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        // Button labels are HTML (icon + text), so the lang text is escaped
        confirmButtonText: '<i class="fa-solid fa-trash-can me-2"></i>' + escapeHtml(t('btn_delete', 'Delete')),
        cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i>' + escapeHtml(t('btn_cancel', 'Cancel')),
        customClass: {
            confirmButton: 'btn btn-danger rounded-pill px-4 mx-1',
            cancelButton: 'btn btn-outline-secondary rounded-pill px-4 mx-1'
        }
    }).then(function (r) { if (r.isConfirmed) onYes(); });
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
            if (!id) { showNotification(t('invalid_item', 'Invalid news item.'), 'error'); return; }

            confirmDelete(title.trim(), function () {
                var origHTML = delBtn.innerHTML;
                setBusy(delBtn, '');
                delBtn.disabled = true;

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
                        setTimeout(function () {
                            card.remove();
                            updateCount();
                            showNotification(t('deleted', 'News deleted!'), 'success');
                        }, 300);
                    } else {
                        showNotification(d.error || t('delete_failed', 'Failed to delete.'), 'error');
                        delBtn.innerHTML = origHTML;
                        delBtn.disabled  = false;
                    }
                })
                .catch(function () {
                    showNotification(t('network', 'Network error. Please try again.'), 'error');
                    delBtn.innerHTML = origHTML;
                    delBtn.disabled  = false;
                });
            });
            return;
        }

        // ── Edit ──────────────────────────────────────────────────────────────
        var editBtn = e.target.closest('.news-edit');
        if (editBtn) {
            e.preventDefault();
            var ecard  = editBtn.closest('.news-card');
            var eid    = ecard ? ecard.dataset.newsid : null;
            var etitle = ecard ? (ecard.querySelector('.card-title') || {}).textContent || '' : '';
            var ebody  = ecard ? (ecard.dataset.body || '') : '';
            if (!eid) { showNotification(t('invalid_item', 'Invalid news item.'), 'error'); return; }

            document.getElementById('editNewsId').value = eid;
            document.getElementById('editTitle').value  = etitle.trim();
            document.getElementById('editBody').value   = ebody;
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

let newsSpoilerCounter = 0;

// Torrent stats line: "<category> · <size> · N seeders · N leechers" (all escaped)
function torrentStatsHtml(data) {
    return escapeHtml(data.catname) + ' &middot; ' + escapeHtml(data.size)
        + ' &middot; <span class="text-success">' + escapeHtml(t('seeders', '{1} seeders', data.seeders)) + '</span>'
        + ' &middot; <span class="text-danger">' + escapeHtml(t('leechers', '{1} leechers', data.leechers)) + '</span>';
}

// ── Live preview ──────────────────────────────────────────────────────────────
function updatePreview() {
    var ta  = document.getElementById('editBody');
    var pre = document.getElementById('bbcodeNewsPreview');
    if (!ta || !pre) return;

    if (!ta.value.trim()) {
        pre.textContent = '';
        pre.appendChild(el('small', 'nws-muted', t('preview_empty', 'Preview will appear here…')));
        return;
    }

    var spoilerLabel = escapeHtml(t('spoiler', 'Spoiler (click to show)'));

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
                 + '<i class="fa-solid fa-eye"></i> ' + spoilerLabel
                 + '</a>'
                 + '<div class="collapse mt-2 p-2 border rounded bg-body-tertiary" id="' + id + '">'
                 + content
                 + '</div>'
                 + '</div>';
        })
        .replace(/\[torrent=(\d+)\]/g, function (_, tid) {
            return '<div class="mycode_torrent_card card d-inline-block my-2" data-torrent-preview-id="' + tid + '" style="max-width:420px;">'
                 + '<div class="card-body py-2 px-3 text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>'
                 + escapeHtml(t('loading_torrent', 'Loading torrent #{1}…', tid))
                 + '</div></div>';
        })
        .replace(/\n/g, '<br>');

    loadTorrentEmbedPreviews(pre);
}

// [torrent=ID] требует данных с сервера - рендерим плейсхолдер сразу вместе
// с остальным BBCode (синхронно), а карточку подгружаем отдельно через
// ajax_torrent_preview.php и точечно заменяем содержимое плейсхолдера.
function loadTorrentEmbedPreviews(container) {
    container.querySelectorAll('[data-torrent-preview-id]').forEach(function (node) {
        const id = node.getAttribute('data-torrent-preview-id');
        fetch((window.newsBaseUrl || '') + '/ajax_torrent_preview.php?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    node.innerHTML = '<div class="card-body py-2 px-3 text-danger small"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + escapeHtml(data.error) + '</div>';
                    return;
                }
                const img = data.image
                    ? '<img src="' + escapeHtml(data.image) + '" class="card-img-top" style="height:100px;object-fit:cover;">'
                    : '';
                node.innerHTML = img
                    + '<div class="card-body py-2 px-3">'
                    + '<div class="fw-bold text-truncate small"><i class="fa-solid fa-magnet me-1"></i>' + escapeHtml(data.name) + '</div>'
                    + '<div class="text-muted small">' + torrentStatsHtml(data) + '</div>'
                    + '</div>';
            })
            .catch(function () {
                node.innerHTML = '<div class="card-body py-2 px-3 text-danger small">' + escapeHtml(t('preview_failed', 'Failed to load preview.')) + '</div>';
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

    if (!title.trim()) { showNotification(t('title_empty', 'Title cannot be empty.'), 'error'); return; }
    if (!body.trim())  { showNotification(t('content_empty', 'Content cannot be empty.'), 'error'); return; }

    var btn  = document.getElementById('saveEditBtn');
    var orig = btn.innerHTML;
    setBusy(btn, t('saving', 'Saving…'));
    btn.disabled = true;

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
                showNotification(t('updated', 'News updated!'), 'success');
                setTimeout(function () { location.reload(); }, 900);
            } else {
                showNotification(d.error || t('edit_failed', 'Edit failed.'), 'error');
                btn.innerHTML = orig;
                btn.disabled  = false;
            }
        })
        .catch(function () {
            showNotification(t('network', 'Network error. Please try again.'), 'error');
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
            torrentPreviewEdit.innerHTML = '<div class="text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>'
                + escapeHtml(t('loading_preview', 'Loading preview…')) + '</div>';
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
                        '<div class="text-muted small">' + torrentStatsHtml(data) + '</div></div></div>';
                })
                .catch(function () {
                    torrentPreviewEdit.innerHTML = '<div class="text-danger small">' + escapeHtml(t('preview_failed', 'Failed to load preview.')) + '</div>';
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
