// I18N: AGS_LANG (js_* lang keys, prefix stripped) is printed by moderation.php; English fallbacks are used if a key is missing.
function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach(function (a, i) { s = s.split('{' + (i + 1) + '}').join(String(a)); });
    return s;
}

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
            showError(t('split_title_required', 'Title Required'), t('split_title_required_text', 'Please enter a title for the new thread.'));
            subjectInput?.focus();
            return;
        }

        // Проверка форума
        if (!forumSelect?.value) {
            showError(t('split_forum_required', 'Forum Required'), t('split_forum_required_text', 'Please select a destination forum.'));
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
            confirmButtonText: t('ok', 'OK'),
            confirmButtonColor: '#0d6efd'
        });
    } else {
        alert(text);
    }
}

// Тело окна подтверждения (DOM, текст вставляется как текст — не через innerHTML)
function buildSplitConfirmBody(postCount, newTitle, forumName) {
    const wrap = document.createElement('div');
    wrap.className = 'text-start';

    const intro = document.createElement('p');
    const parts = t('split_confirm_body', 'You are about to move {1} post(s)').split('{1}');
    intro.appendChild(document.createTextNode(parts[0]));
    const count = document.createElement('strong');
    count.textContent = postCount;
    intro.appendChild(count);
    if (parts.length > 1) intro.appendChild(document.createTextNode(parts.slice(1).join('')));
    wrap.appendChild(intro);

    [[t('split_lbl_title', 'Title:'), newTitle], [t('lbl_forum', 'Forum:'), forumName]].forEach(function (row) {
        const p = document.createElement('p');
        const label = document.createElement('strong');
        label.textContent = row[0];
        p.appendChild(label);
        p.appendChild(document.createTextNode(' ' + row[1]));
        wrap.appendChild(p);
    });

    return wrap;
}

// Подтверждение
async function confirmSplit(postCount, newTitle, forumName) {
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: t('split_confirm_title', 'Split Thread Confirmation'),
            html: buildSplitConfirmBody(postCount, newTitle, forumName),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: t('split_confirm_btn', 'Split Thread'),
            cancelButtonText: t('cancel', 'Cancel')
        });
        return result.isConfirmed;
    } else {
        return confirm(t('split_confirm_plain', 'Create "{1}" in "{2}" with {3} posts?', newTitle, forumName, postCount));
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
    while (btn.firstChild) btn.removeChild(btn.firstChild);
    const icon = document.createElement('i');
    icon.className = 'fas fa-spinner fa-spin me-2';
    btn.appendChild(icon);
    btn.appendChild(document.createTextNode(' ' + t('splitting', 'Splitting...')));
}