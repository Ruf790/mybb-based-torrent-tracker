// ===== Edit post: BBCode live preview, save, torrent embed panel =====

// Escapes raw HTML so it can never be interpreted as real markup.
// MUST run before any BBCode-to-HTML substitution below.
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

// BBCode functions
function wrapBBCode(startTag, endTag, pid) {
    const ta = document.getElementById("editPostTextarea" + pid);
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const selected = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + startTag + selected + endTag + ta.value.substring(end);
    ta.focus();
    ta.setSelectionRange(start + startTag.length, end + startTag.length + selected.length);
    renderPreview(pid);
}

// Live Preview
let editPostSpoilerCounter = 0;

function renderPreview(pid) {
    const ta = document.getElementById("editPostTextarea" + pid);
    const preview = document.getElementById("editPostPreview" + pid);
    let content = ta.value;

    // Escape raw HTML first, THEN convert BBCode to HTML for preview
    content = escapeHtml(content)
        .replace(/\[b\](.*?)\[\/b\]/gi, "<b>$1</b>")
        .replace(/\[i\](.*?)\[\/i\]/gi, "<i>$1</i>")
        .replace(/\[u\](.*?)\[\/u\]/gi, "<u>$1</u>")
        .replace(/\[s\](.*?)\[\/s\]/gi, "<s>$1</s>")
        .replace(/\[left\](.*?)\[\/left\]/gi, '<div style="text-align: left;">$1</div>')
        .replace(/\[center\](.*?)\[\/center\]/gi, '<div style="text-align: center;">$1</div>')
        .replace(/\[right\](.*?)\[\/right\]/gi, '<div style="text-align: right;">$1</div>')
        .replace(/\[color=(.*?)\](.*?)\[\/color\]/gi, '<span style="color: $1;">$2</span>')
        .replace(/\[size=(.*?)\](.*?)\[\/size\]/gi, '<span style="font-size: $1px;">$2</span>')
        .replace(/\[url\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank">$1</a>')
        .replace(/\[url=(.*?)\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank">$2</a>')
        .replace(/\[img\](.*?)\[\/img\]/gi, '<img src="$1" alt="Image" class="rounded" style="max-width: 400px;">')
        .replace(/\[quote\](.*?)\[\/quote\]/gi, '<blockquote>$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/gi, '<pre>$1</pre>')
        .replace(/\[video\](.*?)\[\/video\]/gi,'<video controls style="max-width:300px; width:100%; height:auto;"><source src="$1" type="video/mp4"></video>')
		.replace(/\[youtube\](.*?)\[\/youtube\]/gi,'<iframe width="100%" height="315" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen referrerpolicy="no-referrer"></iframe>')
		.replace(/\[list\](.*?)\[\/list\]/gi, '<ul>$1</ul>')
        .replace(/\[list=1\](.*?)\[\/list\]/gi, '<ol>$1</ol>')
        .replace(/\[\*\](.*?)(?=\[\*\]|\[\/list\])/gi, '<li>$1</li>')
        .replace(/\[spoiler\](.*?)\[\/spoiler\]/gis, function (_, inner) {
            const sid = 'preview-spoiler-' + (++editPostSpoilerCounter);
            return '<div class="mycode_spoiler my-2">'
                 + '<a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#' + sid + '" role="button" aria-expanded="false" aria-controls="' + sid + '">'
                 + '<i class="fa-solid fa-eye"></i> Spoiler (click to show)'
                 + '</a>'
                 + '<div class="collapse mt-2 p-2 border rounded bg-light" id="' + sid + '">'
                 + inner
                 + '</div>'
                 + '</div>';
        })
        .replace(/\[torrent=(\d+)\]/gi, '<div class="mycode_torrent_card card d-inline-block my-2" data-torrent-preview-id="$1" style="max-width:420px;"><div class="card-body py-2 px-3 text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading torrent #$1...</div></div>')
        .replace(/\n/g, "<br>");

    preview.innerHTML = content;
    loadTorrentEmbedPreviews(preview);
}

// [torrent=ID] требует данных с сервера — рендерим плейсхолдер сразу вместе
// с остальным BBCode (синхронно), а карточки подгружаем отдельно через
// ajax_torrent_preview.php и точечно заменяем содержимое плейсхолдера.
function loadTorrentEmbedPreviews(container) {
    container.querySelectorAll('[data-torrent-preview-id]').forEach(function (el) {
        const id = el.getAttribute('data-torrent-preview-id');
        fetch('ajax_torrent_preview.php?id=' + encodeURIComponent(id))
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

// Save function
function savePost(pid) {
    const message = document.getElementById("editPostTextarea" + pid).value;
    const editReason = document.getElementById("editReasonInput" + pid).value;

    if (!message.trim()) {
        showToast("Message cannot be empty", "warning");
        return;
    }

    const saveBtn = document.getElementById("savePostBtn" + pid);
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = "Saving...";
    saveBtn.disabled = true;

    const xhr = new XMLHttpRequest();
    const url = "xmlhttp.php?action=edit_post&do=update_post&pid=" + pid;

    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function() {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;

        if (xhr.status === 200) {
            try {
                const json = JSON.parse(xhr.responseText);

                if (json.errors) {
                    showToast("Error: " + json.errors, "error");
                    return;
                }

                // Update post content
                const postElement = document.getElementById("pid_" + pid);
                if (postElement && json.message) {
                    postElement.innerHTML = json.message;
                }

                // Update edit message
                if (json.editedmsg) {
                    const editedElement = document.getElementById("edited_by_" + pid);
                    if (editedElement) {
                        editedElement.innerHTML = json.editedmsg;
                    }
                }

                // Close modal
                const modalElement = document.getElementById("editPostModal" + pid);
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                }

                showToast("Post updated successfully!", "success");

            } catch (e) {
                showToast("Error processing server response", "error");
            }
        } else {
            showToast("Server error: " + xhr.status, "error");
        }
    };

    xhr.onerror = function() {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        showToast("Network error while saving", "error");
    };

    // Include edit reason and CSRF token in the data
    const data = "value=" + encodeURIComponent(message) + "&editreason=" + encodeURIComponent(editReason) + "&my_post_key=" + encodeURIComponent(my_post_key);
    xhr.send(data);
}


// Извлекает ID из голого числа, полного URL (torrent-17.html), query-параметра
// или произвольного вставленного текста со ссылкой внутри.
function extractTorrentId(raw) {
    raw = raw || '';
    const m = raw.match(/torrent-(\d+)\.html/i)
           || raw.match(/[?&](?:id|tid)=(\d+)/i)
           || raw.match(/(\d+)/);
    return m ? m[1] : '';
}

// Вставляет [torrent=ID] в позицию курсора — аналог wrapBBCode(), но для
// одиночного самозакрытого тега без выделения текста.
function insertTorrentTag(pid, id) {
    const ta = document.getElementById("editPostTextarea" + pid);
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const tag = "[torrent=" + id + "]";
    ta.value = ta.value.substring(0, start) + tag + ta.value.substring(end);
    ta.focus();
    ta.setSelectionRange(start + tag.length, start + tag.length);
    renderPreview(pid);
}

function initTorrentPanel(pid) {
    const input   = document.getElementById("torrentIdInput" + pid);
    const btn     = document.getElementById("insertTorrentBtn" + pid);
    const preview = document.getElementById("torrentPreview" + pid);
    if (!input || !btn) return;

    let debounceTimer = null;

    if (preview) {
        input.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            const id = extractTorrentId(input.value);
            if (!id) {
                preview.innerHTML = "";
                return;
            }
            debounceTimer = setTimeout(function () {
                preview.innerHTML = '<div class="text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading preview...</div>';
                fetch('ajax_torrent_preview.php?id=' + encodeURIComponent(id))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.error) {
                            preview.innerHTML = '<div class="text-danger small">' + escapeHtml(data.error) + '</div>';
                            return;
                        }
                        const img = data.image
                            ? '<img src="' + escapeHtml(data.image) + '" class="card-img-top" style="height:100px;object-fit:cover;">'
                            : '';
                        preview.innerHTML = '<div class="card">' + img
                            + '<div class="card-body py-2 px-3">'
                            + '<div class="fw-bold text-truncate small"><i class="fa-solid fa-magnet me-1"></i>' + escapeHtml(data.name) + '</div>'
                            + '<div class="text-muted small">' + escapeHtml(data.catname) + ' &middot; ' + escapeHtml(data.size)
                            + ' &middot; <span class="text-success">' + data.seeders + ' seeders</span>'
                            + ' &middot; <span class="text-danger">' + data.leechers + ' leechers</span>'
                            + '</div></div>';
                    })
                    .catch(function () {
                        preview.innerHTML = '<div class="text-danger small">Failed to load preview</div>';
                    });
            }, 400);
        });
    }

    const doInsert = function () {
        const id = extractTorrentId(input.value);
        if (!id) {
            input.focus();
            return;
        }
        insertTorrentTag(pid, id);
        input.value = "";
        if (preview) preview.innerHTML = "";
    };

    btn.addEventListener("click", doInsert);
    input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            doInsert();
        }
    });
}

// Initialize edit post functionality
function initEditPost(pid) {
    // Set up save button
    const saveBtn = document.getElementById("savePostBtn" + pid);
    if (saveBtn) {
        saveBtn.addEventListener("click", function() {
            savePost(pid);
        });
    }

    // Initialize preview
    const textarea = document.getElementById("editPostTextarea" + pid);
    const preview = document.getElementById("editPostPreview" + pid);

    if (textarea && preview) {
        textarea.addEventListener("input", function() {
            renderPreview(pid);
        });

        // Initial preview
        renderPreview(pid);
    }

    initTorrentPanel(pid);
}

// Global initialization for all edit modals
document.addEventListener("DOMContentLoaded", function() {
    // Auto-initialize all edit post modals on the page
    const editModals = document.querySelectorAll('[id^="editPostModal"]');
    editModals.forEach(modal => {
        const pid = modal.id.replace('editPostModal', '');
        initEditPost(pid);
    });
});


// ===== Delete post =====

// Функция для удаления поста
function deletePost(postId) {
    // Показываем индикатор загрузки

    const deleteBtn = document.getElementById('confirmDeleteBtn' + postId);
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Deleting...';
    deleteBtn.disabled = true;

    // AJAX запрос для удаления поста
    fetch('editpost.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'deletepost',
            'pid': postId,
            'delete': '1',
            'my_post_key': my_post_key,
            'ajax': '1'
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.data == '1') {
            // Успешное удаление
            if(data.first == '1') {
                // Удален первый пост (вся тема)
                showToast('Thread has been deleted successfully', 'success');
                setTimeout(() => {
                    window.location.href = data.url || forumBaseUrl;
                }, 1500);
            } else {
                // Удален обычный пост
                showToast('Post has been deleted successfully', 'success');
                // Скрываем удаленный пост
                const postElement = document.getElementById('post_' + postId);
                if(postElement) {
                    postElement.style.display = 'none';
                }
                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('deletePostModal' + postId));
                if(modal) {
                    modal.hide();
                }
            }
        } else if(data.data == '2') {
            // Пост удален, но нет прав на просмотр удаленных
            showToast('Post has been deleted successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else if(data.data == '3') {
            // Тема удалена, но нет прав на просмотр удаленных
            showToast('Thread has been deleted successfully', 'success');
            setTimeout(() => {
                window.location.href = data.url;
            }, 1500);
        }
    })
    .catch(error => {
        console.error('Error deleting post:', error);
        showToast('Error deleting post', 'error');
        // Восстанавливаем кнопку
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    });
}

// Инициализация обработчиков для кнопок удаления
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('button[id^="confirmDeleteBtn"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const postId = this.id.replace('confirmDeleteBtn', '');
            deletePost(postId);
        });
    });
});
