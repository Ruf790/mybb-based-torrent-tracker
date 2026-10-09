(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const self = document.querySelector('.rt').dataset.self;
    const sep  = self.includes('?') ? '&' : '?';
    const input = $('username'), box = $('usernameSuggestions');
    let users = {}, current = null, timer = null, activeIndex = -1;

    // ── Ланг: AGS_LANG выводит ratio.php; {1} и %1$s ($lang->load() переводит {1} в %1$s) ──
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const sub = (s, fn) => s.replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => fn(+(a || b) - 1, m));
    // Перевод как текст
    function t(key, fallback, ...args) {
        const s = typeof L[key] === 'string' ? L[key] : fallback;
        return sub(s, (i, m) => i < args.length ? String(args[i]) : m);
    }
    // Для html у Swal: сам перевод экранируется, в {N} подставляется готовый (уже экранированный) HTML
    function tHtml(key, fallback, ...htmlArgs) {
        return sub(esc(typeof L[key] === 'string' ? L[key] : fallback), (i, m) => i < htmlArgs.length ? String(htmlArgs[i]) : m);
    }
    function setHint(el, cls, iconCls, text) {
        el.className = cls;
        if (iconCls) {
            const i = document.createElement('i'); i.className = iconCls;
            el.replaceChildren(i, document.createTextNode(text));
        } else {
            el.textContent = text;
        }
    }

    // ── Разбор «1.5 GB» и формат размера (как на сервере) ──
    function parse(v) {
        v = v.replace(/[, ]/g, m => m === ',' ? '.' : '').trim();
        const m = v.match(/^(\d+(?:\.\d+)?)(B|KB|KIB|MB|MIB|GB|GIB|TB|TIB|PB|PIB)?$/i);
        if (!m) return null;
        const pow = { B: 0, KB: 1, MB: 2, GB: 3, TB: 4, PB: 5 }[(m[2] || 'B').toUpperCase().replace('I', '')] || 0;
        return Math.round(parseFloat(m[1]) * Math.pow(1024, pow));
    }
    function fmt(b) { const u = ['B','KB','MB','GB','TB','PB']; let i = 0; while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return (i ? b.toFixed(2) : b) + ' ' + u[i]; }
    function ratio(up, down) { return down > 0 ? up / down : Infinity; }
    function rcls(r) { return r >= 2 ? 'r-good' : r >= 1 ? 'r-ok' : r >= .5 ? 'r-warn' : 'r-bad'; }
    function rtxt(r) { return isFinite(r) ? r.toFixed(2) : '∞'; }

    function values() {
        const mode = document.querySelector('[name="mode"]:checked').value;
        const out = {};
        ['uploaded', 'downloaded'].forEach(f => {
            const raw = $(f).value.trim(), hint = $(f + 'Hint');
            const old = current ? current[f] : 0;
            if (raw === '') { out[f] = old; setHint(hint, 'rt-hint', null, t('hint_empty', 'Empty = leave unchanged')); $(f).setCustomValidity(''); return; }
            const b = parse(raw);
            if (b === null) { out[f] = old; setHint(hint, 'rt-hint bad', 'fa-solid fa-circle-xmark me-1', t('hint_bad', 'Use e.g. 512 MB or 1.5 GB')); $(f).setCustomValidity('bad'); return; }
            $(f).setCustomValidity('');
            out[f] = mode === 'add' ? old + b : mode === 'sub' ? Math.max(0, old - b) : b;
            setHint(hint, 'rt-hint', null, t('hint_bytes', '= {1} bytes', b.toLocaleString('en-US')));
        });
        return out;
    }

    function preview() {
        const v = values();
        if (!current) return;
        $('rtUpOld').textContent = fmt(current.uploaded);   $('rtUpNew').textContent = fmt(v.uploaded);
        $('rtDownOld').textContent = fmt(current.downloaded); $('rtDownNew').textContent = fmt(v.downloaded);
        $('rtUpNew').classList.toggle('chg', v.uploaded !== current.uploaded);
        $('rtDownNew').classList.toggle('chg', v.downloaded !== current.downloaded);
        const r0 = ratio(current.uploaded, current.downloaded), r1 = ratio(v.uploaded, v.downloaded);
        $('rtRatioOld').textContent = rtxt(r0); $('rtRatioOld').className = 'n ' + rcls(r0);
        $('rtRatioNew').textContent = rtxt(r1); $('rtRatioNew').className = 'n ' + rcls(r1);
    }

    function choose(u) {
        current = u; input.value = u.username; hideBox();
        $('rtEmpty').hidden = true; $('rtPreview').hidden = false;
        $('rtName').innerHTML = u.name_html;   // экранировано сервером
        $('rtMeta').textContent = t('user_meta', 'ID {1} · ratio {2}', u.id, u.ratio);
        $('rtAv').textContent = (u.username || '?').charAt(0).toUpperCase();
        preview();
    }

    // ── Автокомплит ──
    function hideBox() { box.classList.add('d-none'); box.replaceChildren(); activeIndex = -1; }
    function render(list) {
        users = {};
        box.replaceChildren();
        if (!list.length) { hideBox(); return; }
        list.forEach(u => {
            users[u.username.toLowerCase()] = u;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'list-group-item list-group-item-action py-2';
            const left = document.createElement('span'); left.innerHTML = u.name_html;
            const right = document.createElement('small'); right.className = 'text-body-secondary text-nowrap';
            right.textContent = '↑' + u.up_h + ' ↓' + u.down_h + ' · ' + u.ratio;
            b.append(left, right);
            b.addEventListener('click', () => choose(u));
            box.appendChild(b);
        });
        box.classList.remove('d-none');
        // точное совпадение — сразу выбираем для превью
        const exact = users[input.value.trim().toLowerCase()];
        if (exact && (!current || current.id !== exact.id)) { current = null; choose(exact); box.classList.remove('d-none'); }
    }
    function search(term) {
        fetch(self + sep + 'action=search_user&term=' + encodeURIComponent(term), { credentials: 'same-origin' })
            .then(r => r.json()).then(render).catch(hideBox);
    }
    input.addEventListener('input', () => {
        const t = input.value.trim();
        clearTimeout(timer);
        if (current && current.username.toLowerCase() !== t.toLowerCase()) { current = null; $('rtPreview').hidden = true; $('rtEmpty').hidden = false; }
        if (t.length < 2) { hideBox(); return; }
        timer = setTimeout(() => search(t), 250);
    });
    input.addEventListener('keydown', e => {
        const items = box.querySelectorAll('.list-group-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, items.length - 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); }
        else if (e.key === 'Enter' && activeIndex >= 0) { e.preventDefault(); items[activeIndex].click(); return; }
        else if (e.key === 'Escape') { hideBox(); return; }
        else return;
        items.forEach((it, i) => it.classList.toggle('active', i === activeIndex));
    });
    document.addEventListener('click', e => { if (e.target !== input && !box.contains(e.target)) hideBox(); });

    // ── Поля и быстрые значения ──
    ['uploaded', 'downloaded'].forEach(f => $(f).addEventListener('input', preview));
    document.querySelectorAll('[name="mode"]').forEach(r => r.addEventListener('change', preview));
    document.querySelectorAll('.rt .rt-chips').forEach(box => box.addEventListener('click', e => {
        const c = e.target.closest('.rt-chip'); if (!c) return;
        $(box.dataset.for).value = c.dataset.v; preview();
    }));
    $('updateForm').addEventListener('reset', () => setTimeout(() => { current = null; $('rtPreview').hidden = true; $('rtEmpty').hidden = false; values(); }, 0));

    $('updateForm').addEventListener('submit', async e => {
        const f = $('updateForm');
        e.preventDefault();
        values();
        if (!input.value.trim() || !f.checkValidity()) { f.reportValidity(); return; }
        const v = values(), who = input.value.trim();
        const mode = document.querySelector('[name="mode"]:checked').value;
        const modeTxt = { set: t('mode_set', 'Set to'), add: t('mode_add', 'Add'), sub: t('mode_sub', 'Subtract') }[mode];
        let ok;

        if (window.Swal) {
            const row = (icon, cls, label, a, b) => '<tr><td class="text-body-secondary pe-3 text-nowrap"><i class="fa-solid ' + icon + ' ' + cls + ' me-1"></i>' + esc(label) + '</td>'
                + '<td class="font-monospace">' + esc(a) + '</td><td class="px-2 text-body-secondary"><i class="fa-solid fa-arrow-right-long"></i></td>'
                + '<td class="font-monospace fw-bold' + (a !== b ? ' text-primary' : '') + '">' + esc(b) + '</td></tr>';
            const r0 = current ? ratio(current.uploaded, current.downloaded) : null, r1 = current ? ratio(v.uploaded, v.downloaded) : null;
            const html = current
                ? '<div class="text-start">'
                  + '<div class="d-flex align-items-center gap-2 mb-3"><span class="badge rounded-pill text-bg-secondary">' + esc(modeTxt) + '</span><span class="fw-bold">' + current.name_html + '</span></div>'
                  + '<table class="w-100 mb-2" style="font-size:.95rem">'
                  + row('fa-upload', 'text-success', t('lbl_uploaded', 'Uploaded'), fmt(current.uploaded), fmt(v.uploaded))
                  + row('fa-download', 'text-danger', t('lbl_downloaded', 'Downloaded'), fmt(current.downloaded), fmt(v.downloaded))
                  + row('fa-scale-balanced', '', t('lbl_ratio', 'Ratio'), rtxt(r0), rtxt(r1))
                  + '</table><div class="small text-body-secondary"><i class="fa-solid fa-clipboard-list me-1"></i>' + esc(t('log_note', 'Old and new values are written to the site log')) + '</div></div>'
                : '<div>' + tHtml('confirm_simple', 'Update statistics for {1}?', '<b>' + esc(who) + '</b>') + '</div>';
            const res = await Swal.fire({
                titleText: t('confirm_title', 'Save changes?'), html, icon: 'question', width: 520,
                showCancelButton: true, reverseButtons: true, focusCancel: mode !== 'set',
                confirmButtonText: '<i class="fa-solid fa-floppy-disk me-1"></i>' + esc(t('btn_save', 'Save')), cancelButtonText: esc(t('btn_cancel', 'Cancel')),
                confirmButtonColor: '#0d6efd', cancelButtonColor: '#6c757d',
            });
            ok = res.isConfirmed;
        } else {
            const q = t('confirm_simple', 'Update statistics for {1}?', who);
            ok = confirm(current
                ? q + '\n\n' + t('lbl_uploaded', 'Uploaded') + ': ' + fmt(current.uploaded) + ' → ' + fmt(v.uploaded)
                  + '\n' + t('lbl_downloaded', 'Downloaded') + ': ' + fmt(current.downloaded) + ' → ' + fmt(v.downloaded)
                  + '\n' + t('lbl_ratio', 'Ratio') + ': ' + rtxt(ratio(current.uploaded, current.downloaded)) + ' → ' + rtxt(ratio(v.uploaded, v.downloaded))
                : q);
        }
        if (!ok) return;

        const b = $('rtSave'); b.disabled = true;
        const spin = document.createElement('span'); spin.className = 'spinner-border spinner-border-sm me-1';
        b.replaceChildren(spin, document.createTextNode(t('saving', 'Saving…')));
        if (window.Swal) Swal.fire({ titleText: t('saving', 'Saving…'), allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        HTMLFormElement.prototype.submit.call(f);
    });

    // После ошибки поля уже заполнены — подгружаем пользователя
    if (input.value.trim().length >= 2) search(input.value.trim());
    values();
})();
