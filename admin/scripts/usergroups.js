/* User Groups (admin/groups.php): живой предпросмотр ника/иконки, переключатели вступления */
document.addEventListener('DOMContentLoaded', function () {
    // Строки из ланга (const AGS_LANG выводит groups.php перед скриптом); английский — fallback
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    function t(key, fallback, ...args) {
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => { s = s.split('{' + (i + 1) + '}').join(String(a)); });
        return s;
    }
    function escText(str) {
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // Живой предпросмотр стиля ника и картинки группы
    const style = document.querySelector('.ug input[name="namestyle"]');
    const title = document.querySelector('.ug input[name="title"]');
    const img   = document.querySelector('.ug input[name="image"]');
    const out   = document.getElementById('ugNamePreview');
    const iout  = document.getElementById('ugImagePreview');

    function safeHtml(html) {
        // Показываем только разметку оформления — без скриптов и обработчиков
        const doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
        doc.querySelectorAll('script, iframe, object, embed, link, meta, style').forEach(n => n.remove());
        doc.querySelectorAll('*').forEach(el => [...el.attributes].forEach(a => {
            if (/^on/i.test(a.name) || /javascript:/i.test(a.value)) el.removeAttribute(a.name);
        }));
        return doc.body.firstChild.innerHTML;
    }
    function renderName() {
        if (!out || !style) return;
        // Перевод подставляется экранированным текстом внутрь стиля ника
        const name = escText(t('preview_username', 'Username'));
        out.innerHTML = safeHtml((style.value || '{username}').split('{username}').join(name));
    }
    function renderImg() {
        if (!iout || !img) return;
        const v = img.value.trim().replace('{lang}', 'english');
        iout.replaceChildren();
        if (!v) { iout.textContent = '—'; return; }
        if (v.startsWith('<')) { iout.innerHTML = safeHtml(v); return; }
        const el = document.createElement('img');
        el.alt = '';
        el.src = /^(https?:)?\/\//i.test(v) || v.startsWith('/') ? v : '../' + v;
        el.onerror = () => {
            const span = document.createElement('span');
            span.className = 'text-danger small';
            const ic = document.createElement('i');
            ic.className = 'fa-solid fa-image me-1';
            span.append(ic, document.createTextNode(t('img_not_found', 'not found')));
            iout.replaceChildren(span);
        };
        iout.appendChild(el);
    }
    style && style.addEventListener('input', renderName);
    img && img.addEventListener('input', renderImg);
    renderName(); renderImg();

    // «Модерировать заявки» и «Только по приглашению» — взаимоисключающие
    const mod = document.getElementById('sw_moderate'), inv = document.getElementById('sw_invite'), join = document.getElementById('sw_joinable');
    function syncJoin() {
        [mod, inv].forEach(el => { if (el) { el.disabled = !(join && join.checked); el.closest('.ug-switch').style.opacity = el.disabled ? .5 : 1; } });
    }
    mod && mod.addEventListener('change', () => { if (mod.checked && inv) inv.checked = false; });
    inv && inv.addEventListener('change', () => { if (inv.checked && mod) mod.checked = false; });
    join && join.addEventListener('change', syncJoin);
    syncJoin();
});
