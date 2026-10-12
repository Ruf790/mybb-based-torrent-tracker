/* PHP Editor (admin/phpeditor.php) — needs AGS_LANG defined before this file */
'use strict';

// ── Ланг ──────────────────────────────────────────────────
function t(key, fallback, ...args) {
    let s = (AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}

const AJAX_URL    = (() => {
    const p = new URLSearchParams(window.location.search);
    return window.location.pathname + '?act=' + (p.get('act') || 'phpeditor');
})();
const CURRENT_FILE = document.getElementById('codeEditor').dataset.file;
const CM_MODE      = document.getElementById('codeEditor').dataset.cmMode || 'text/plain';

// ── Редактор ──────────────────────────────────────────────
const editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
    lineNumbers:       true,
    mode:              CM_MODE,
    theme:             'eclipse',
    indentUnit:        4,
    tabSize:           4,
    indentWithTabs:    false,
    lineWrapping:      false,
    autoCloseBrackets: true,
    matchBrackets:     true,
    matchTags:         true,
    foldGutter:        true,
    gutters:           ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    extraKeys: {
        'Ctrl-S':       () => saveFile(),
        'Ctrl-F':       'findPersistent',
        'Ctrl-H':       'replace',
        'Ctrl-G':       'findNext',
        'Shift-Ctrl-G': 'findPrev',
        'Ctrl-Space':   'autocomplete',
        'Alt-F':        () => formatCode(),
        'Ctrl-/':       'toggleComment',
    },
    hintOptions: { completeSingle: false },
});

// ── Просмотрщик бэкапов ───────────────────────────────────
let backupViewerCM = null;
function getBackupViewer() {
    if (backupViewerCM) return backupViewerCM;
    backupViewerCM = CodeMirror(document.getElementById('backupViewerContainer'), {
        lineNumbers: true,
        mode:        CM_MODE,
        theme:       'eclipse',
        readOnly:    true,
        value:       '',
    });
    backupViewerCM.setSize('100%', '60vh');
    return backupViewerCM;
}

const backupModal = new bootstrap.Modal(document.getElementById('backupModal'));

// ── Toast ─────────────────────────────────────────────────
function showAlert(type, message, timer = 3000) {
    Swal.fire({ icon: type, titleText: String(message ?? ''), timer, showConfirmButton: false, toast: true, position: 'top-end' });
}

// ── AJAX ──────────────────────────────────────────────────
function ajaxPost(data) {
    const myPostKey = document.getElementById('myPostKey')?.value || '';
    return fetch(AJAX_URL, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams({ ajax: '1', file: CURRENT_FILE, my_post_key: myPostKey, ...data }),
    }).then(r => r.json());
}

// ── Сохранение ────────────────────────────────────────────
function saveFile() {
    const content = editor.getValue();
    const backup  = document.getElementById('backupCheck').checked;

    ajaxPost({ action: 'save', content, backup: backup ? '1' : '0' })
        .then(d => {
            if (d.status === 'success') {
                showAlert('success', d.message);
                if (d.info) updateFileInfoBar(d.info);
                updateStats();
            } else {
                showAlert('error', d.message);
            }
        })
        .catch(e => showAlert('error', e.message));
}

// ── Форматирование ────────────────────────────────────────
function formatCode() {
    const cur = editor.getCursor();
    const content = editor.getValue();

    // Простое форматирование отступов
    const lines    = content.split('\n');
    let   indent   = 0;
    const formatted = lines.map(line => {
        const trimmed = line.trim();
        if (!trimmed) return '';
        if (trimmed.match(/^[}\])]/) ) indent = Math.max(0, indent - 1);
        const result = '    '.repeat(indent) + trimmed;
        if (trimmed.match(/[{[(]$/)  ) indent++;
        return result;
    });

    editor.setValue(formatted.join('\n'));
    editor.setCursor(cur);
    showAlert('success', t('formatted', 'Code formatted'));
}

// ── Статистика ────────────────────────────────────────────
function updateStats() {
    const c = editor.getValue();
    const lines     = c.split('\n').length;
    const functions = (c.match(/function\s+\w+\s*\(/g)    || []).length;
    const classes   = (c.match(/\bclass\s+\w+/g)           || []).length;
    const comments  = (c.match(/\/\/.*|\/\*[\s\S]*?\*\//g) || []).length;
    const bytes     = new TextEncoder().encode(c).length;

    document.getElementById('statsLines').textContent     = lines;
    document.getElementById('statsFunctions').textContent = functions;
    document.getElementById('statsClasses').textContent   = classes;
    document.getElementById('statsComments').textContent  = comments;
    document.getElementById('currentSize').textContent    = formatBytes(bytes);

    const origBytes = parseInt(document.getElementById('originalSize').textContent) || bytes;
    const pct       = Math.min(Math.round((bytes / Math.max(origBytes, 1)) * 100), 100);
    document.getElementById('sizeBar').style.width   = pct + '%';
    document.getElementById('sizeBar').textContent   = pct + '%';

    // Подсветка синтаксиса в строке статуса
    if (document.getElementById('syntaxCheckToggle').checked && CM_MODE.includes('php')) {
        checkSyntax(c);
    }
}

function formatBytes(b) {
    if (!b) return '0 B';
    const u = ['B','KB','MB'], i = Math.floor(Math.log(b) / Math.log(1024));
    return (b / 1024**i).toFixed(2) + ' ' + u[i];
}

// Простая клиентская проверка синтаксиса (только базовые ошибки)
function checkSyntax(code) {
    const el     = document.getElementById('syntaxStatus');
    const errors = [];

    // Проверяем баланс скобок
    let braces = 0, parens = 0, brackets = 0;
    let inStr = false, strChar = '', inComment = false;

    for (let i = 0; i < code.length; i++) {
        const ch = code[i];
        if (inComment) {
            if (ch === '*' && code[i+1] === '/') { inComment = false; i++; }
            continue;
        }
        if (!inStr && ch === '/' && code[i+1] === '*') { inComment = true; i++; continue; }
        if (!inStr && ch === '/' && code[i+1] === '/') { while (i < code.length && code[i] !== '\n') i++; continue; }
        if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; continue; }
        if (inStr && ch === strChar && code[i-1] !== '\\') { inStr = false; continue; }
        if (inStr) continue;

        if (ch === '{') braces++;   else if (ch === '}') braces--;
        if (ch === '(') parens++;   else if (ch === ')') parens--;
        if (ch === '[') brackets++; else if (ch === ']') brackets--;
    }

    if (braces   !== 0) errors.push(t('unbalanced_braces',   'Unbalanced {}'));
    if (parens   !== 0) errors.push(t('unbalanced_parens',   'Unbalanced ()'));
    if (brackets !== 0) errors.push(t('unbalanced_brackets', 'Unbalanced []'));

    const span = document.createElement('span');
    const icon = document.createElement('i');
    if (errors.length) {
        span.className = 'syntax-err';
        icon.className = 'fas fa-times-circle me-1';
        span.append(icon, document.createTextNode(errors.join(', ')));
    } else {
        span.className = 'syntax-ok';
        icon.className = 'fas fa-check-circle me-1';
        span.append(icon, document.createTextNode(t('syntax_ok', 'OK')));
    }
    el.replaceChildren(span);
}

function updateFileInfoBar(info) {
    if (info.lines)    document.querySelector('.file-info-bar span:nth-child(3)').textContent = '📄 ' + t('info_lines', '{1} lines', info.lines);
    if (info.modified) document.querySelector('.file-info-bar span:nth-child(4)').textContent = '🕐 ' + info.modified;
}

// ── Бэкапы ────────────────────────────────────────────────
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
            if (intoEditor) {
                editor.setValue(d.content);
                showAlert('success', t('backup_loaded', 'Backup loaded into the editor'));
                return;
            }
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

document.getElementById('refreshBackups').addEventListener('click', () => {
    ajaxPost({ action: 'get_backups' })
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            const tbody = document.getElementById('backupsList');
            if (!d.backups.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan     = 3;
                td.className   = 'text-center text-muted py-3';
                td.textContent = t('no_backups', 'No backups yet');
                tr.appendChild(td);
                tbody.replaceChildren(tr);
                return;
            }
            const esc = s => s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            tbody.innerHTML = d.backups.map(b => `
                <tr class="backup-item" data-file="${esc(b.file)}">
                    <td><span class="badge bg-secondary">${esc(b.file.replace(/\.\d{4}-\d{2}-\d{2}.*\.bak$/, '').replace(/__/g, '/'))}</span> ${esc(b.date)}</td>
                    <td>${esc(b.size_fmt)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-backup"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-outline-success restore-backup"><i class="fas fa-undo"></i></button>
                    </td>
                </tr>`).join('');
            showAlert('success', t('list_refreshed', 'List refreshed'));
        });
});

// ── Кнопки ────────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click',   saveFile);
document.getElementById('formatBtn').addEventListener('click', formatCode);
document.getElementById('findBtn').addEventListener('click',   () => editor.execCommand('findPersistent'));
document.getElementById('reloadBtn').addEventListener('click', () => location.reload());

// ── Изменения в редакторе ─────────────────────────────────
editor.on('change', () => {
    clearTimeout(window._statsTimer);
    window._statsTimer = setTimeout(updateStats, 800);
});

// ── Инициализация ─────────────────────────────────────────
updateStats();
