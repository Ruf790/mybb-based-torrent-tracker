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
 */

function deletePost(postId) {
    const deleteBtn = document.getElementById('confirmDeleteBtn' + postId);
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Deleting...';
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
                    showToast('Thread has been deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                    }, 1500);
                } else {
                    // Обычный пост — на editpost.php нет списка постов для
                    // скрытия на месте, поэтому всегда возвращаемся в тред.
                    showToast('Post has been deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                    }, 1500);
                }
            } else if (data.data === '2') {
                // Пост удалён, но нет прав на просмотр удалённых
                showToast('Post has been deleted successfully', 'success');
                setTimeout(() => {
                    window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                }, 1500);
            } else if (data.data === '3') {
                // Тред удалён, нет прав на просмотр удалённых
                showToast('Thread has been deleted successfully', 'success');
                setTimeout(() => {
                    window.location.href = data.url || (typeof forumBaseUrl !== 'undefined' ? forumBaseUrl : '/');
                }, 1500);
            } else {
                showToast('Unexpected response while deleting post', 'error');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        })
        .catch((error) => {
            console.error('Error deleting post:', error);
            showToast('Error deleting post', 'error');
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
