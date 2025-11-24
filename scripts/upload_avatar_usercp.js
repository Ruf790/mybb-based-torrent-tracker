
// avatar_upload_usercp.js - упрощенная версия
class AvatarUpload {
    constructor() {
        this.avatarContainer = document.getElementById("avatarImage");
        this.avatarInput = document.getElementById("avatarInput");
        this.init();
    }

    init() {
        if (!this.avatarContainer || !this.avatarInput) {
            console.log('Avatar upload elements not found');
            return;
        }

        this.avatarContainer.addEventListener("click", () => this.handleAvatarClick());
        this.avatarInput.addEventListener("change", () => this.handleAvatarChange());
        
        this.saveOriginalAvatar();
        
        // Добавляем стиль для курсора если его нет
        if (this.avatarContainer.style.cursor !== 'pointer') {
            this.avatarContainer.style.cursor = 'pointer';
        }
    }

    saveOriginalAvatar() {
        if (this.avatarContainer.tagName === 'IMG') {
            this.avatarContainer.dataset.originalSrc = this.avatarContainer.src;
        } else {
            this.avatarContainer.dataset.originalContent = this.avatarContainer.innerHTML;
        }
    }

    handleAvatarClick() {
        this.avatarInput.click();
    }

    handleAvatarChange() {
        const file = this.avatarInput.files[0];
        if (!file) return;

        if (!this.validateFileType(file)) {
            showToast("Please select a valid image file (JPG, PNG, GIF, SVG)", "error");
            return;
        }

        if (!this.validateFileSize(file)) {
            showToast("File size too large. Maximum 2MB allowed.", "error");
            return;
        }

        this.previewAvatar(file);
        this.uploadAvatar(file);
    }

    validateFileType(file) {
        const allowedTypes = [
            'image/jpeg', 
            'image/jpg', 
            'image/png', 
            'image/gif',
            'image/svg+xml'
        ];
        return allowedTypes.includes(file.type);
    }

    validateFileSize(file) {
        const maxSize = 2 * 1024 * 1024; // 2MB
        return file.size <= maxSize;
    }

    previewAvatar(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            this.updateAvatarPreview(e.target.result, file.type);
        };
        reader.readAsDataURL(file);
    }

    updateAvatarPreview(dataUrl, fileType) {
        if (fileType === 'image/svg+xml') {
            // Для SVG заменяем содержимое
            try {
                const svgContent = atob(dataUrl.split(',')[1]);
                this.avatarContainer.innerHTML = svgContent;
            } catch (e) {
                console.error('Error processing SVG:', e);
                showToast("Error processing SVG file", "error");
            }
        } else {
            // Для обычных изображений
            if (this.avatarContainer.tagName === 'IMG') {
                this.avatarContainer.src = dataUrl;
            } else {
                // Заменяем SVG контейнер на img
                const newImg = document.createElement('img');
                newImg.src = dataUrl;
                newImg.className = this.avatarContainer.className || 'rounded img-fluid';
                newImg.style.cssText = this.avatarContainer.style.cssText + '; object-fit: cover;';
                newImg.alt = 'Avatar';
                newImg.dataset.originalSrc = dataUrl;
                
                // Заменяем элемент в DOM
                this.avatarContainer.parentNode.replaceChild(newImg, this.avatarContainer);
                this.avatarContainer = newImg;
                
                // Добавляем обработчик клика к новому изображению
                this.avatarContainer.addEventListener("click", () => this.handleAvatarClick());
            }
        }
    }

    uploadAvatar(file) {
        const formData = new FormData();
        formData.append("avatarupload", file);
        formData.append("action", "do_avatar");
        formData.append("my_post_key", window.my_post_key);

        // Показываем тост загрузки
        showToast("Uploading avatar...", "info");

        fetch("usercp.php?action=do_avatar", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast("Avatar successfully updated!", "success");
                if (data.avatarUrl) {
                    this.updateGlobalAvatar(data.avatarUrl);
                }
                // Обновляем оригинальный аватар после успешной загрузки
                this.saveOriginalAvatar();
            } else {
                const errorMsg = data.error || "Unknown error occurred";
                showToast("Error: " + errorMsg, "error");
                this.restoreOriginalAvatar();
            }
        })
        .catch(error => {
            console.error("Upload error:", error);
            showToast("Upload error: " + error.message, "error");
            this.restoreOriginalAvatar();
        })
        .finally(() => {
            // Сбрасываем input для возможности повторной загрузки того же файла
            this.avatarInput.value = '';
        });
    }

    restoreOriginalAvatar() {
        if (this.avatarContainer.tagName === 'IMG' && this.avatarContainer.dataset.originalSrc) {
            this.avatarContainer.src = this.avatarContainer.dataset.originalSrc;
        } else if (this.avatarContainer.dataset.originalContent) {
            this.avatarContainer.innerHTML = this.avatarContainer.dataset.originalContent;
        }
    }

    updateGlobalAvatar(avatarUrl) {
        // Обновляем аватарки в навигации и других местах
        const navAvatars = document.querySelectorAll('.nav-avatar, .user-avatar, .header-avatar');
        navAvatars.forEach(avatar => {
            if (avatar.src) {
                // Добавляем timestamp для обхода кэша
                avatar.src = avatarUrl + (avatarUrl.includes('?') ? '&' : '?') + 't=' + new Date().getTime();
            }
        });
    }
}

// Инициализация когда DOM загружен
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        new AvatarUpload();
    });
} else {
    // DOM уже загружен
    new AvatarUpload();
}