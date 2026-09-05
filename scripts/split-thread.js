// Подсчет количества постов
function countPosts() {
    const postsInput = document.querySelector('input[name="posts"]');
    if (!postsInput) return 0;
    
    const postsValue = postsInput.value || '';
    let count = 0;
    
    if (postsValue) {
        if (postsValue.includes('|')) {
            count = postsValue.split('|').filter(isValidId).length;
        } else if (postsValue.includes(',')) {
            count = postsValue.split(',').filter(isValidId).length;
        } else if (postsValue.includes('_')) {
            count = postsValue.split('_').filter(isValidId).length;
        } else if (postsValue.trim() !== '') {
            const num = parseInt(postsValue.trim());
            count = !isNaN(num) && num > 0 ? 1 : 0;
        }
    }
    
    return count;
}

// Валидация ID
function isValidId(id) {
    const trimmed = id.trim();
    return trimmed !== '' && !isNaN(parseInt(trimmed));
}

document.addEventListener('DOMContentLoaded', function() {

    // Обновление счетчика
    const postCount = countPosts();
    document.querySelectorAll('#postsCount, #postsCount2').forEach(el => {
        if (el) el.textContent = postCount;
    });

    // Стилизация select
    const forumSelect = document.querySelector('select');
    if (forumSelect) {
        forumSelect.classList.add('form-select-custom', 'form-select');
    }

    // Стилизация input
    const subjectInput = document.querySelector('input[name="newsubject"]');
    if (subjectInput) {
        subjectInput.classList.add('form-control-custom');
    }

    // Форма
    const form = document.getElementById('splitThreadForm');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const subjectInput = this.querySelector('input[name="newsubject"]');
        const forumSelect = this.querySelector('select');
        const postCount = document.getElementById('postsCount')?.textContent || '0';

        // Проверка заголовка
        if (!subjectInput?.value.trim()) {
            showError('Title Required', 'Please enter a title for the new thread.');
            subjectInput?.focus();
            return;
        }

        // Проверка форума
        if (!forumSelect?.value) {
            showError('Forum Required', 'Please select a destination forum.');
            forumSelect?.focus();
            return;
        }

        const forumName = forumSelect.options[forumSelect.selectedIndex].text;
        const newTitle = subjectInput.value;

        const confirmed = await confirmSplit(postCount, newTitle, forumName);
        if (!confirmed) return;

        showProgress();
        disableButton(this);

        setTimeout(() => {
            HTMLFormElement.prototype.submit.call(form);
        }, 100);
    });
});

// Универсальная ошибка
async function showError(title, text) {
    if (typeof Swal !== 'undefined') {
        await Swal.fire({
            icon: 'error',
            title,
            text,
            confirmButtonColor: '#0d6efd'
        });
    } else {
        alert(text);
    }
}

// Подтверждение
async function confirmSplit(postCount, newTitle, forumName) {
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: '<strong>Split Thread Confirmation</strong>',
            html: `
                <div class="text-start">
                    <p>You are about to move <strong>${postCount}</strong> post(s)</p>
                    <p><strong>Title:</strong> ${newTitle}</p>
                    <p><strong>Forum:</strong> ${forumName}</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Split Thread'
        });
        return result.isConfirmed;
    } else {
        return confirm(`Create "${newTitle}" in "${forumName}" with ${postCount} posts?`);
    }
}

// Прогресс
function showProgress() {
    const progressBar = document.getElementById('progressBar');
    if (!progressBar) return;

    progressBar.style.display = 'block';
    const bar = progressBar.querySelector('.progress-bar');

    let width = 0;
    const interval = setInterval(() => {
        if (width >= 100) {
            clearInterval(interval);
        } else {
            width += 10;
            bar.style.width = width + '%';
        }
    }, 50);
}

// Блок кнопки
function disableButton(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Splitting...';
}