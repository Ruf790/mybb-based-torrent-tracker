// Advanced CSS Editor (admin/csseditor.php)
// AGS_LANG и CSSE_CURRENT_FILE выводит PHP перед подключением скрипта.
// ── Ланг ──────────────────────────────────────────────────
// $lang->load() отдаёт {1} как %1$s — подставляем оба формата.
function t(key, fallback, ...args) {
    let s = (AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}

const AJAX_URL   = (() => {
    const p = new URLSearchParams(window.location.search);
    return window.location.pathname + '?act=' + (p.get('act') || 'csseditor');
})();
const CURRENT_FILE = window.CSSE_CURRENT_FILE ?? '';

// Перевод диалогов поиска/замены CodeMirror (опция phrases)
const CM_PHRASES = {
    'Search:':                              t('cm_search', 'Search:'),
    '(Use /re/ syntax for regexp search)':  t('cm_regex_hint', '(Use /re/ syntax for regexp search)'),
    'Replace:':                             t('cm_replace', 'Replace:'),
    'Replace all:':                         t('cm_replace_all', 'Replace all:'),
    'With:':                                t('cm_with', 'With:'),
    'Replace?':                             t('cm_replace_confirm', 'Replace?'),
    'Yes':                                  t('cm_yes', 'Yes'),
    'No':                                   t('cm_no', 'No'),
    'All':                                  t('cm_all', 'All'),
    'Stop':                                 t('cm_stop', 'Stop'),
};

// ── Редактор ──────────────────────────────────────────────
const editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
    lineNumbers: true, mode: 'text/css', theme: 'eclipse',
    indentUnit: 4, tabSize: 4, lineWrapping: true,
    autoCloseBrackets: true, matchBrackets: true,
    foldGutter: true, gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    phrases: CM_PHRASES,
    extraKeys: {
        'Ctrl-S': saveFile, 'Ctrl-F': 'findPersistent', 'Ctrl-H': 'replace',
        'Ctrl-G': 'findNext', 'Shift-Ctrl-G': 'findPrev',
        'Ctrl-Space': 'autocomplete', 'Alt-F': formatCode, 'Ctrl-/': 'toggleComment',
    },
    hintOptions: { completeSingle: false, hint: CodeMirror.hint.css },
});

// ── Просмотрщик бэкапов — ленивая инициализация ──────────
let backupViewerCM = null;
function getBackupViewer() {
    if (backupViewerCM) return backupViewerCM;
    backupViewerCM = CodeMirror(document.getElementById('backupViewerContainer'), {
        lineNumbers: true, mode: 'text/css', theme: 'eclipse', readOnly: true, value: '',
        phrases: CM_PHRASES,
    });
    backupViewerCM.setSize('100%', '60vh');
    return backupViewerCM;
}

const backupModal = new bootstrap.Modal(document.getElementById('backupModal'));

// ── Toast ─────────────────────────────────────────────────
// titleText — сообщение вставляется как текст, не как HTML
function showAlert(type, message, timer = 3000) {
    Swal.fire({ icon: type, titleText: String(message ?? ''), timer, showConfirmButton: false, toast: true, position: 'top-end' });
}

// ── AJAX helper ───────────────────────────────────────────
function ajaxPost(data) {
    const myPostKey = document.getElementById('myPostKey')?.value || '';
    return fetch(AJAX_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax: '1', file: CURRENT_FILE, my_post_key: myPostKey, ...data }),
    }).then(r => r.json());
}

// ── Сохранение ────────────────────────────────────────────
function saveFile() {
    let content  = editor.getValue();
    const backup = document.getElementById('backupCheck').checked;
    const minify = document.getElementById('minifyCheck').checked;
    if (minify) {
        content = content
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/\s+/g, ' ')
            .replace(/\s?([\{\}:;,])\s?/g, '$1')
            .replace(/;\}/g, '}').trim();
    }
    ajaxPost({ action: 'save', content, backup: backup ? '1' : '0' })
        .then(d => { d.status === 'success' ? showAlert('success', d.message) : showAlert('error', d.message); updateStats(); })
        .catch(e => showAlert('error', e.message));
}

// ── Форматирование ────────────────────────────────────────
function formatCode() {
    editor.setValue(css_beautify(editor.getValue(), {
        indent_size: 4, selector_separator_newline: true,
        end_with_newline: true, newline_between_rules: true,
    }));
    showAlert('success', t('formatted', 'Code formatted'));
}

// ── Статистика ────────────────────────────────────────────
function updateStats() {
    const c = editor.getValue();
    document.getElementById('statsSelectors').textContent    = (c.match(/[^{]+\{/g)      || []).length;
    document.getElementById('statsProperties').textContent   = (c.match(/[^:]+:[^;]+;/g)  || []).length;
    document.getElementById('statsMediaQueries').textContent = (c.match(/@media[^{]+\{/g) || []).length;
    const normal  = c.length;
    const mini    = c.replace(/\/\*[\s\S]*?\*\//g,'').replace(/\s+/g,' ').replace(/\s?([\{\}:;,])\s?/g,'$1').replace(/;\}/g,'}').length;
    document.getElementById('normalSize').textContent = formatFileSize(normal);
    document.getElementById('minSize').textContent    = formatFileSize(mini);
    const pct = Math.round((mini / Math.max(normal, 1)) * 100);
    document.getElementById('sizeBar').style.width    = pct + '%';
    document.getElementById('sizeBar').textContent    = pct + '%';
}

function formatFileSize(b) {
    if (!b) return '0 B';
    const u = ['B','KB','MB'], i = Math.floor(Math.log(b) / Math.log(1024));
    return (b / 1024**i).toFixed(2) + ' ' + u[i];
}

// ── Бэкапы — делегирование событий ───────────────────────
document.getElementById('backupsList').addEventListener('click', function (e) {
    const row  = e.target.closest('.backup-item');
    if (!row) return;
    const file = row.dataset.file;
    if (e.target.closest('.view-backup'))    loadBackup(file, false);
    if (e.target.closest('.restore-backup')) loadBackup(file, true);
});

function loadBackup(backupFile, intoEditor = false) {
    ajaxPost({ action: 'get_backup_content', backup_file: backupFile })
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            if (intoEditor) { editor.setValue(d.content); showAlert('success', t('backup_loaded', 'Backup loaded into the editor')); return; }
            getBackupViewer().setValue(d.content);
            backupModal.show();
            backupModal._element.addEventListener('shown.bs.modal', () => getBackupViewer().refresh(), { once: true });
        })
        .catch(e => showAlert('error', e.message));
}

document.getElementById('restoreFromModal').addEventListener('click', () => {
    editor.setValue(getBackupViewer().getValue());
    backupModal.hide();
    showAlert('success', t('content_loaded', 'Content loaded into the editor'));
});

// Строка таблицы бэкапов — собирается через DOM, тексты как textContent
function buildBackupButton(cls, icon, label) {
    const btn = document.createElement('button');
    btn.className = 'btn btn-sm ' + cls;
    btn.title = label;
    btn.setAttribute('aria-label', label);
    const i = document.createElement('i');
    i.className = 'fas ' + icon;
    btn.appendChild(i);
    return btn;
}

function buildBackupRow(b) {
    const tr = document.createElement('tr');
    tr.className = 'backup-item';
    tr.dataset.file = b.file;

    const tdName = document.createElement('td');
    const badge  = document.createElement('span');
    badge.className   = 'badge bg-secondary';
    badge.textContent = b.file.replace(/\.\d{4}-\d{2}-\d{2}.*\.bak$/, '');
    tdName.appendChild(badge);
    tdName.appendChild(document.createTextNode(' ' + b.date));

    const tdSize = document.createElement('td');
    tdSize.textContent = b.size_fmt;

    const tdAct = document.createElement('td');
    tdAct.appendChild(buildBackupButton('btn-outline-primary view-backup', 'fa-eye', t('view', 'View')));
    tdAct.appendChild(document.createTextNode(' '));
    tdAct.appendChild(buildBackupButton('btn-outline-success restore-backup', 'fa-undo', t('restore', 'Restore')));

    tr.append(tdName, tdSize, tdAct);
    return tr;
}

function buildEmptyBackupRow() {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan   = 3;
    td.className = 'text-center text-muted py-3';
    td.textContent = t('no_backups', 'No backups yet');
    tr.appendChild(td);
    return tr;
}

document.getElementById('refreshBackups').addEventListener('click', () => {
    ajaxPost({ action: 'get_backups' })
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            const tbody = document.getElementById('backupsList');
            if (!d.backups.length) {
                tbody.replaceChildren(buildEmptyBackupRow());
                return;
            }
            tbody.replaceChildren(...d.backups.map(buildBackupRow));
            showAlert('success', t('list_updated', 'List updated'));
        });
});

// ── Preview ───────────────────────────────────────────────
document.getElementById('livePreviewToggle').addEventListener('change', function () {
    const wrap = document.getElementById('previewContainer');
    wrap.style.display = this.checked ? 'block' : 'none';
    if (this.checked) applyPreview();
    else document.getElementById('livePreviewStyle')?.remove();
});
document.getElementById('refreshPreview').addEventListener('click', applyPreview);

function applyPreview() {
    document.getElementById('livePreviewStyle')?.remove();
    const css = editor.getValue().trim();
    if (!css) { showAlert('info', t('editor_empty', 'The editor is empty')); return; }
    const s = document.createElement('style');
    s.id = 'livePreviewStyle';
    s.textContent = css;
    document.head.appendChild(s);
    showAlert('success', t('styles_applied', 'Styles applied'), 1500);
}

editor.on('change', () => {
    if (document.getElementById('livePreviewToggle').checked) {
        clearTimeout(window._previewTimer);
        window._previewTimer = setTimeout(applyPreview, 500);
    }
    clearTimeout(window._statsTimer);
    window._statsTimer = setTimeout(updateStats, 800);
});

// ── Сниппеты ─────────────────────────────────────────────
const SNIPPETS = {
    'reset':       `* {\n    margin: 0;\n    padding: 0;\n    box-sizing: border-box;\n}`,
    'flex-center': `.centered {\n    display: flex;\n    justify-content: center;\n    align-items: center;\n}`,
    'grid-layout': `.grid-container {\n    display: grid;\n    grid-template-columns: repeat(3, 1fr);\n    gap: 20px;\n}`,
    'media-query': `@media (min-width: 768px) {\n    \n}`,
    'animation':   `@keyframes fadeIn {\n    from { opacity: 0; }\n    to { opacity: 1; }\n}\n\n.animated {\n    animation: fadeIn 0.5s ease-in-out;\n}`,
    'transition':  `.element {\n    transition: all 0.3s ease;\n    will-change: transform, opacity;\n}`,
    'transform':   `.element {\n    transform: translateX(10px) rotate(5deg);\n    transform-origin: center center;\n}`,
    'shadow':      `.shadow-soft {\n    box-shadow: 0 2px 8px rgba(0,0,0,0.1);\n}\n\n.shadow-medium {\n    box-shadow: 0 4px 12px rgba(0,0,0,0.15);\n}`,
};

document.querySelectorAll('.template-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const snippet = SNIPPETS[btn.dataset.snippet];
        if (!snippet) return;
        const cursor = editor.getCursor();
        const prefix = (cursor.ch > 0 || cursor.line > 0) ? '\n\n' : '';
        editor.replaceRange(prefix + snippet, cursor);
        editor.scrollIntoView(cursor);
        showAlert('success', t('snippet_added', 'Snippet added'), 1500);
    });
});

// ── Кнопки ───────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click',   saveFile);
document.getElementById('formatBtn').addEventListener('click', formatCode);
document.getElementById('findBtn').addEventListener('click',   () => editor.execCommand('findPersistent'));
document.getElementById('reloadBtn').addEventListener('click', () => location.reload());

// ── Инициализация ─────────────────────────────────────────
updateStats();
