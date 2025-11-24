
// Функция для удаления поста
function deletePost(postId) {
    // Показываем индикатор загрузки
	
    const deleteBtn = document.getElementById('confirmDeleteBtn' + postId);
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class=\"fa-solid fa-spinner fa-spin me-1\"></i> Deleting...';
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
    document.querySelectorAll('button[id^=\"confirmDeleteBtn\"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const postId = this.id.replace('confirmDeleteBtn', '');
            deletePost(postId);
        });
    });
});