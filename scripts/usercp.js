/**
 * usercp.js — всё JS панели пользователя (usercp.php)
 *
 * Объединено из: usercp.js, usercp_attachments.js, usercp-options.js
 *
 *  1. Buddy / ignore lists — поиск и добавление пользователей
 *  2. Attachments          — выбор и доп. информация по вложениям
 *  3. Options              — выбор категорий для подписки
 *
 * Каждый блок сам проверяет наличие своих элементов, поэтому файл можно
 * подключать на любой вкладке UserCP.
 * Зависимости (глобальные): my_post_key, lang и showToast() (опционально).
 */

/* ══════════════════════════════════════════════════════════
   Общие хелперы
   ══════════════════════════════════════════════════════════ */

// Только http(s) (абсолютные или относительные, вроде ./uploads/avatars/x.png?dateline=…)
// — никаких javascript:/data: в src
function ucpSafeUrl(url) {
    url = String(url || '').trim();
    if (!url) return '';
    try {
        const u = new URL(url, window.location.href);
        return (u.protocol === 'http:' || u.protocol === 'https:') ? url : '';
    } catch (e) {
        return '';
    }
}

// <img> + текст через DOM, без innerHTML (имя пользователя приходит с сервера)
function ucpUserNode(tag, className, user) {
    const node = document.createElement(tag);
    node.className = className;
    const avatar = ucpSafeUrl(user.avatar);
    if (avatar) {
        const img = document.createElement('img');
        img.src = avatar;
        img.alt = '';
        node.appendChild(img);
    }
    const name = document.createElement('span');
    name.className = 'user-name';
    name.textContent = user.text;
    node.appendChild(name);
    return node;
}


/* ══════════════════════════════════════════════════════════
   1. Buddy / ignore lists
   ══════════════════════════════════════════════════════════ */

class UserMultiSelect {
    constructor(containerId, hiddenInputId, limit = 5) {
        this.container   = document.getElementById(containerId);
        this.hiddenInput = document.getElementById(hiddenInputId);
        this.limit       = limit;
        this.selected    = [];
        this.searchSeq   = 0;
        this.debounce    = null;
        if (this.container && this.hiddenInput) this.init();
    }

    get storageKey() {
        return 'ucp_' + this.container.id;
    }

    init() {
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.placeholder = 'Поиск пользователя...';
        this.input.autocomplete = 'off';

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'user-dropdown';
        this.dropdown.hidden = true;

        this.container.append(this.input, this.dropdown);

        this.input.addEventListener('input', () => {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.search(), 250);
        });
        this.input.addEventListener('keydown', e => {
            if (e.key === 'Escape') this.dropdown.hidden = true;
        });

        document.addEventListener('click', e => {
            if (!this.container.contains(e.target)) this.dropdown.hidden = true;
        });

        this.restore();
    }

    async search() {
        const q = this.input.value.trim();
        // Раньше при <2 символах оставался старый выпадающий список
        if (q.length < 2) {
            this.dropdown.hidden = true;
            this.dropdown.replaceChildren();
            return;
        }

        // Защита от гонки: ответы старых запросов, пришедшие позже новых, игнорируем
        const seq = ++this.searchSeq;

        let list;
        try {
            const res = await fetch('xmlhttp.php?action=get_users&query=' + encodeURIComponent(q), {
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            list = Array.isArray(data) ? data : (Array.isArray(data.results) ? data.results : []);
        } catch (err) {
            console.error('User search failed:', err);
            return;
        }

        if (seq !== this.searchSeq) return;

        this.dropdown.replaceChildren();

        list.forEach(user => {
            if (!user || !user.id || !user.text) return;
            if (this.selected.some(u => String(u.id) === String(user.id))) return;

            const opt = ucpUserNode('div', 'user-option', user);
            opt.addEventListener('click', () => this.add(user));
            this.dropdown.appendChild(opt);
        });

        this.dropdown.hidden = this.dropdown.children.length === 0;
    }

    add(user) {
        if (this.selected.length >= this.limit) {
            alert(`Максимум ${this.limit}`);
            return;
        }
        this.selected.push({ id: user.id, text: user.text, avatar: user.avatar || '' });
        this.render();
        this.save();
        this.dropdown.hidden = true;
        this.input.value = '';
        this.input.focus();
    }

    remove(id) {
        this.selected = this.selected.filter(u => String(u.id) !== String(id));
        this.render();
        this.save();
    }

    clear() {
        this.selected = [];
        this.render();
        try { localStorage.removeItem(this.storageKey); } catch (e) { /* ignore */ }
    }

    render() {
        this.container.querySelectorAll('.user-tag').forEach(e => e.remove());

        this.selected.forEach(user => {
            const tag = ucpUserNode('div', 'user-tag', user);
            const x = document.createElement('span');
            x.className = 'user-tag-remove';
            x.setAttribute('role', 'button');
            x.setAttribute('aria-label', 'Remove');
            x.innerHTML = '&times;';
            x.addEventListener('click', () => this.remove(user.id));
            tag.appendChild(x);
            this.container.insertBefore(tag, this.input);
        });

        this.hiddenInput.value = this.selected.map(u => u.text).join(',');
    }

    save() {
        try {
            if (this.selected.length) {
                localStorage.setItem(this.storageKey, JSON.stringify(this.selected));
            } else {
                localStorage.removeItem(this.storageKey);
            }
        } catch (e) { /* storage недоступен — не критично */ }
    }

    restore() {
        try {
            // Миграция со старого ключа (без префикса)
            const legacy = localStorage.getItem(this.container.id);
            if (legacy !== null) {
                localStorage.removeItem(this.container.id);
            }
            const saved = localStorage.getItem(this.storageKey);
            if (!saved) return;
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed)) {
                this.selected = parsed.filter(u => u && u.id && u.text).slice(0, this.limit);
                this.render();
            }
        } catch (e) {
            try { localStorage.removeItem(this.storageKey); } catch (e2) { /* ignore */ }
        }
    }
}

const UserCP = {
    buddySelect: null,
    ignoredSelect: null,

    init() {
        this.buddySelect   = new UserMultiSelect('buddy_add_username', 'buddy_add_username_input');
        this.ignoredSelect = new UserMultiSelect('ignored_add_username', 'ignored_add_username_input');

        document.getElementById('buddy_search_btn')
            ?.addEventListener('click', () => this.buddySelect.input?.focus());
        document.getElementById('ignored_search_btn')
            ?.addEventListener('click', () => this.ignoredSelect.input?.focus());
    },

    // Добавление — обычная отправка формы (сервер делает redirect обратно на список).
    // Раньше onsubmit="return UserCP.addBuddy(…)" вызывал async-функцию: она возвращала
    // Promise (не false), так что форма всё равно уходила обычным способом, а AJAX-ветка
    // искала несуществующие #buddy_list / #ignore_list. Оставляем только рабочий путь.
    addBuddy(type) {
        const select = type === 'ignored' ? this.ignoredSelect : this.buddySelect;
        if (!select || !select.hiddenInput || !select.hiddenInput.value) {
            select?.input?.focus();
            return false;
        }
        // Выбор больше не нужен — иначе он вернётся из localStorage после redirect
        try { localStorage.removeItem(select.storageKey); } catch (e) { /* ignore */ }
        return true;
    },

    // Удаление — AJAX: сервер отвечает JSON, строку убираем со страницы.
    removeBuddy(type, uid) {
        const msg = (typeof lang !== 'undefined' && lang && (type === 'ignored' ? lang.remove_ignored : lang.remove_buddy))
            || 'Remove this user from the list?';
        if (!confirm(msg)) return false;

        const body = new URLSearchParams({ ajax: '1', my_post_key: my_post_key });

        fetch(
            'usercp.php?action=do_editlists&manage=' + encodeURIComponent(type) + '&delete=' + encodeURIComponent(uid),
            { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'Remove failed');
                }

                const row = document.getElementById(type + '_' + uid);
                if (row) {
                    row.style.transition = 'opacity .25s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 250);
                }

                if (typeof showToast === 'function' && data.message) {
                    // message приходит уже экранированным (htmlspecialchars_uni) — декодируем
                    // сущности через <textarea>: в нём HTML не разбирается в элементы
                    const tmp = document.createElement('textarea');
                    tmp.innerHTML = data.message;
                    showToast(tmp.value, 'success');
                }

                // Список опустел или строка не нашлась — перезагружаем, чтобы показать
                // актуальное состояние (сообщение «список пуст», счётчики)
                if (!row || data.count === 0) {
                    setTimeout(() => window.location.reload(), 600);
                }
            })
            .catch(err => {
                console.error('Remove from list failed:', err);
                alert('Could not update the list. Please try again.');
            });

        return false;
    }
};

window.UserCP = UserCP;


/* ══════════════════════════════════════════════════════════
   2. Attachments
   ══════════════════════════════════════════════════════════ */

function initUserCPAttachments() {
    // Подсветка выбранного вложения. Класс вместо style.backgroundColor = '#f8f9ff' —
    // светлый hex-фон ломал тёмную тему.
    document.addEventListener('change', function (e) {
        const checkbox = e.target.closest('.attachment-checkbox');
        if (!checkbox) return;
        const item = checkbox.closest('.attachment-item');
        if (!item) return;
        item.classList.toggle('border-primary', checkbox.checked);
        item.classList.toggle('bg-primary-subtle', checkbox.checked);
    });

    // Клик по строке: показать/скрыть доп. инфо (курсор pointer — в CSS)
    document.addEventListener('click', function (e) {
        const item = e.target.closest('.attachment-item');
        if (!item) return;
        if (e.target.closest('.btn, a, .form-check-input, label')) return;
        const info = item.querySelector('.additional-info');
        if (info) info.classList.toggle('d-none');
    });

    // Уже отмеченные при загрузке (браузер восстанавливает состояние формы)
    document.querySelectorAll('.attachment-checkbox:checked').forEach(cb => {
        const item = cb.closest('.attachment-item');
        if (item) item.classList.add('border-primary', 'bg-primary-subtle');
    });
}


/* ══════════════════════════════════════════════════════════
   3. Options — подписка на категории
   ══════════════════════════════════════════════════════════ */

// Глобальная: вызывается из разметки onclick="toggleCatSub(this)"
function toggleCatSub(btn) {
    btn.classList.toggle('active');
    btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');

    const target = document.getElementById('catSubsSelected');
    if (!target) return;

    target.value = [...document.querySelectorAll('#catSubsPicker .cat-pick-btn.active')]
        .map(b => '[cat' + parseInt(b.dataset.id, 10) + ']')
        .join('');
}
window.toggleCatSub = toggleCatSub;


/* ══════════════════════════════════════════════════════════
   Boot
   ══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {
    UserCP.init();
    if (document.querySelector('.attachment-item')) initUserCPAttachments();
});