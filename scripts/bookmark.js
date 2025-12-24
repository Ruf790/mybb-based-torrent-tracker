// Обработка кликов по закладкам
document.addEventListener('click', function(e) {
    const bookmarkElement = e.target.closest('.bookmark-toggle');
    if (bookmarkElement) {
        e.preventDefault();
        const torrentId = bookmarkElement.dataset.torrentId;
        
        // Валидация ID
        if (!torrentId || isNaN(parseInt(torrentId))) {
            console.error('Invalid torrent ID:', torrentId);
            return;
        }
        
        // AJAX запрос для добавления/удаления закладки
        toggleBookmark(torrentId, bookmarkElement);
    }
});

async function toggleBookmark(torrentId, element) {
    // Показываем индикатор загрузки
    const originalHTML = element.innerHTML;
    element.innerHTML = '<i class="fa-solid fa-spinner fa-spin fa-lg" style="color: #ffc107;"></i>';
    element.style.pointerEvents = 'none';
    
    try {
        const response = await fetch('bookmark.php?action=toggle&id=' + torrentId);
        
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Обновляем иконку
            if (data.bookmarked) {
                element.innerHTML = '<i class="fa-solid fa-star fa-lg" style="color: #ffc107;"></i>';
                element.classList.add('bookmarked');
                updatePopoverContent(element, 'bookmarked');
                showToast('Bookmark added successfully!', 'success');
            } else {
                element.innerHTML = '<i class="fa-regular fa-star fa-lg" style="color: #6c757d;"></i>';
                element.classList.remove('bookmarked');
                updatePopoverContent(element, 'unbookmarked');
                showToast('Bookmark removed!', 'info');
            }
            
        } else {
            throw new Error(data.message || 'Operation failed');
        }
        
    } catch (error) {
        console.error('Bookmark toggle error:', error);
        // Восстанавливаем оригинальное состояние
        element.innerHTML = originalHTML;
        showToast('Error: ' + error.message, 'error');
    } finally {
        element.style.pointerEvents = 'auto';
    }
}

function updatePopoverContent(element, state) {
    const popover = bootstrap.Popover.getInstance(element);
    if (popover) {
        // Обновляем контент popover
        if (state === 'bookmarked') {
            element.setAttribute('data-bs-title', '✅ Bookmarked');
            element.setAttribute('data-bs-content', 
                '<div class="bookmark-popover-content">' +
                    '<div class="mb-2">' +
                        '<strong>In Your Bookmarks</strong>' +
                        '<div class="small text-muted">Easily accessible anytime</div>' +
                    '</div>' +
                    '<div class="bookmarked-info small text-success">' +
                        '<i class="bi bi-check-circle me-1"></i>' +
                        'Added to your collection' +
                    '</div>' +
                '</div>'
            );
        } else {
            element.setAttribute('data-bs-title', '⭐ Add to Bookmarks');
            element.setAttribute('data-bs-content', 
                '<div class="bookmark-popover-content">' +
                    '<div class="mb-2">' +
                        '<strong>Save for later</strong>' +
                        '<div class="small text-muted">Quick access to this torrent</div>' +
                    '</div>' +
                    '<div class="torrent-preview small">' +
                        '<i class="bi bi-link-45deg me-1"></i>' +
                        'Torrent preview' +
                    '</div>' +
                '</div>'
            );
        }
        
        // Обновляем popover
        popover.dispose();
        new bootstrap.Popover(element);
    }
}

// Инициализация popovers после загрузки DOM
document.addEventListener('DOMContentLoaded', function() {
    // Инициализируем popovers для закладок
    const bookmarkElements = document.querySelectorAll('.bookmark-toggle[data-bs-toggle="popover"]');
    bookmarkElements.forEach(element => {
        new bootstrap.Popover(element);
    });
});