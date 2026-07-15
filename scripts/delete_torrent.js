document.addEventListener("DOMContentLoaded", function() {
    const deleteModal = document.getElementById("deleteTorrentModal");
    const confirmCheckbox = document.getElementById("confirmDelete");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    const torrentNamePreview = document.getElementById("torrentNamePreview");
    
    let currentTorrentId = null;
    
    // Обработчик для всех кнопок удаления
    document.addEventListener("click", function(e) {
        if (e.target.closest('[data-bs-target="#deleteTorrentModal"]')) {
            const button = e.target.closest('[data-bs-target="#deleteTorrentModal"]');
            currentTorrentId = button.getAttribute("data-torrent-id");
            const torrentName = button.getAttribute("data-torrent-name");
            
            // Обновляем содержимое модалки
            if (torrentNamePreview) {
                torrentNamePreview.innerHTML = `<strong>"${torrentName}"</strong>`;
            }
            
            // Сбрасываем подтверждение
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }
            if (confirmBtn) {
                confirmBtn.disabled = true;
            }
        }
    });
    
    // Переключение кнопки удаления в зависимости от подтверждения
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener("change", function() {
            if (confirmBtn) {
                confirmBtn.disabled = !this.checked;
            }
        });
    }
    
    // Обработка подтверждения удаления
    if (confirmBtn) {
        confirmBtn.addEventListener("click", function() {
            if (currentTorrentId && confirmCheckbox && confirmCheckbox.checked) {
                // Показываем состояние загрузки
                confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Deleting...';
                confirmBtn.disabled = true;
                
                // Создаем и отправляем форму POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete.php';
                
                // Добавляем необходимые поля
                const idInput = document.createElement('input');
                idInput.name = 'id';
                idInput.value = currentTorrentId;
                
                const reasonTypeInput = document.createElement('input');
                reasonTypeInput.name = 'reasontype';
                reasonTypeInput.value = '5'; // Причина: "Другое"
                
                const reasonInput = document.createElement('input');
                reasonInput.name = 'reason[3]';
                reasonInput.value = 'Deleted via quick delete modal';
                
                form.appendChild(idInput);
                form.appendChild(reasonTypeInput);
                form.appendChild(reasonInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    // Сброс состояния модалки при закрытии
    if (deleteModal) {
        deleteModal.addEventListener("hidden.bs.modal", function() {
            currentTorrentId = null;
            if (confirmCheckbox) {
                confirmCheckbox.checked = false;
            }
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i>Delete Torrent';
            }
        });
    }
});
