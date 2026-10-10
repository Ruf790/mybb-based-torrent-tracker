/**
 * delete_post_editpost.js
 *
 * Отдельная версия логики удаления поста — специально для editpost.php.
 * В отличие от showthread.php (где после удаления поста нужно скрыть его
 * в списке постов на той же странице), на editpost.php своей копии
 * поста для скрытия нет — здесь просто редиректим после любого исхода:
 *   - удалён первый пост → тред удалён целиком → редирект на forum/url с сервера
 *   - удалён обычный пост → редирект обратно в тред
 *
 * Ожидает те же глобальные зависимости, что и обычный delete_post.js:
 * my_post_key, showToast(), forumBaseUrl (опционально).
 * Тексты берутся из AGS_LANG (выводится PHP из editpost.lang.php, ключи js_*).
 */

/**
 * Перевод по ключу с английским fallback и подстановкой {1}, {2}, ...
 */
function t(key, fallback, ...args) {
    let str = (typeof AGS_LANG !== 'undefined' && AGS_LANG && typeof AGS_LANG[key] === 'string')
        ? AGS_LANG[key]
        : fallback;
    args.forEach(function (arg, i) {
        str = str.split('{' + (i + 1) + '}').join(String(arg));
    });
    return str;
}

function deletePost(postId) {
    const deleteBtn = document.getElementById('confirmDeleteBtn' + postId);
    const originalText = deleteBtn.innerHTML;
    // Спиннер + переведённый текст (текстом, не через innerHTML)
    deleteBtn.textContent = '';
    const spinner = document.createElement('i');
    spinner.className = 'fa-solid fa-spinner fa-spin me-1';
    deleteBtn.appendChild(spinner);
    deleteBtn.appendChild(document.createTextNode(' ' + t('deleting', 'Deleting...')));
    deleteBtn.disabled = true;

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
            'ajax': '1',
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.data === '1') {
                if (data.first === '1') {
                    // Первый пост — тред удалён целиком
                    showToast(t('thread_deleted', 'Thread has been deleted successfully'), 'success');
                    setTimeout(() => {
                        window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                    }, 1500);
                } else {
                    // Обычный пост — на editpost.php нет списка постов для
                    // скрытия на месте, поэтому всегда возвращаемся в тред.
                    showToast(t('post_deleted', 'Post has been deleted successfully'), 'success');
                    setTimeout(() => {
                        window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                    }, 1500);
                }
            } else if (data.data === '2') {
                // Пост удалён, но нет прав на просмотр удалённых
                showToast(t('post_deleted', 'Post has been deleted successfully'), 'success');
                setTimeout(() => {
                    window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                }, 1500);
            } else if (data.data === '3') {
                // Тред удалён, нет прав на просмотр удалённых
                showToast(t('thread_deleted', 'Thread has been deleted successfully'), 'success');
                setTimeout(() => {
                    window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                }, 1500);
            } else {
                showToast(t('delete_unexpected', 'Unexpected response while deleting post'), 'error');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        })
        .catch((error) => {
            console.error('Error deleting post:', error);
            showToast(t('delete_error', 'Error deleting post'), 'error');
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('button[id^="confirmDeleteBtn"]').forEach(function (button) {
        button.addEventListener('click', function () {
            const postId = this.id.replace('confirmDeleteBtn', '');
            deletePost(postId);
        });
    });
});
