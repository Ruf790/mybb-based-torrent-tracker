/* ==========================================================
   Batch Torrent Upload — UI helpers
   (BBCode toolbar, previews, genre tags, announce copy).
   Main upload logic stays in batch_upload.js.
   Requires BATCH_CONFIG (scriptUrl, postKey) defined inline.
   ========================================================== */
'use strict';

let _batchActiveTextarea = null;

// t() объявлен в batch_upload.js (подключается раньше); запасной вариант -
// английский fallback, если основной скрипт не загрузился
function buT(key, fallback, ...args) {
    return typeof t === 'function' ? t(key, fallback, ...args) : fallback;
}

function buEscape(str) {
    if (typeof window.escapeHtml === 'function') return window.escapeHtml(str);
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ── Copy announce URL ───────────────────────────────────────
document.getElementById('buCopyAnnounce')?.addEventListener('click', function () {
    const btn  = this;
    const text = document.getElementById('batchAnnounceUrl')?.textContent.trim() ?? '';
    if (!text || !navigator.clipboard) return;

    navigator.clipboard.writeText(text).then(() => {
        const icon  = btn.querySelector('i');
        const label = btn.querySelector('span');
        btn.classList.add('is-copied');
        if (icon)  icon.className = 'fa-solid fa-check me-1';
        if (label) label.textContent = buT('copied', 'Copied!');
        setTimeout(() => {
            btn.classList.remove('is-copied');
            if (icon)  icon.className = 'fa-solid fa-copy me-1';
            if (label) label.textContent = buT('copy', 'Copy');
        }, 1800);
    }).catch(() => {});
});

// ── Drop zone: keyboard access ──────────────────────────────
document.querySelector('.bu-page .drop-zone')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        document.getElementById('dragDropFiles')?.click();
    }
});

// ── Floating BBCode toolbar ─────────────────────────────────
document.addEventListener('focusin', function (e) {
    if (!e.target.matches('textarea[name="descriptions[]"]')) return;
    _batchActiveTextarea = e.target;
    document.getElementById('bbToolbar')?.classList.remove('d-none');
});

document.addEventListener('focusout', function (e) {
    if (!e.target.matches('textarea[name="descriptions[]"]')) return;
    setTimeout(() => {
        if (!document.activeElement?.closest('#bbToolbar') &&
            !document.activeElement?.matches('textarea[name="descriptions[]"]')) {
            document.getElementById('bbToolbar')?.classList.add('d-none');
        }
    }, 200);
});

function hideBBToolbar() {
    document.getElementById('bbToolbar')?.classList.add('d-none');
    _batchActiveTextarea = null;
}

function batchBB(open, close) {
    const ta = _batchActiveTextarea;
    if (!ta) return;
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    const sel   = ta.value.substring(start, end);
    ta.value    = ta.value.substring(0, start) + open + sel + close + ta.value.substring(end);
    ta.selectionStart = start + open.length;
    ta.selectionEnd   = start + open.length + sel.length;
    ta.focus();
}

function batchPreview() {
    const ta = _batchActiveTextarea;
    if (!ta) return;
    const body  = document.getElementById('batchPreviewBody');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('batchPreviewModal'));
    body.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i></div>';
    modal.show();

    fetch(BATCH_CONFIG.scriptUrl + '&action=bbcode_preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'text=' + encodeURIComponent(ta.value) + '&my_post_key=' + encodeURIComponent(BATCH_CONFIG.postKey)
    })
    .then(r => r.text())
    .then(text => {
        const match = text.match(/\{[\s\S]*\}/);
        if (match) {
            const data = JSON.parse(match[0]);
            body.innerHTML = data.html || '<pre>' + buEscape(ta.value) + '</pre>';
        } else {
            body.innerHTML = '<pre>' + buEscape(ta.value) + '</pre>';
        }
    })
    .catch(() => {
        body.innerHTML = '<pre>' + buEscape(ta.value) + '</pre>';
    });
}

// ── Screenshots: previews on file select ────────────────────
// (poster preview + validation lives in batch_upload.js)
document.getElementById('batchUploadForm')?.addEventListener('change', function (e) {
    const target = e.target;
    if (!(target instanceof HTMLInputElement) || target.type !== 'file') return;

    // Screenshots (several files)
    if (target.name.startsWith('screenshots_') && target.name.endsWith('[]')) {
        const container  = target.closest('.row');
        const previewDiv = container?.querySelector('.screenshots-preview');
        if (!previewDiv) return;

        previewDiv.innerHTML = '';
        Array.from(target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = document.createElement('img');
                img.src       = ev.target.result;
                img.alt       = file.name;
                img.title     = file.name;
                img.className = 'bu-shot';
                previewDiv.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }
});

// ── Genre tag buttons (per torrent row) ─────────────────────
function toggleBatchGenreTag(btn) {
    const container = btn.closest('.row');
    const input     = container?.querySelector('.batch-tags-input');
    if (!input) return;

    const genre = btn.dataset.genre;
    const color = btn.dataset.color;
    const current = input.value.split(',').map(s => s.trim()).filter(Boolean);

    const idx = current.indexOf(genre);
    if (idx === -1) {
        current.push(genre);
        btn.classList.add('batch-genre-active');
        btn.style.background = color;
        btn.style.color = '#fff';
    } else {
        current.splice(idx, 1);
        btn.classList.remove('batch-genre-active');
        btn.style.background = 'transparent';
        btn.style.color = color;
    }

    input.value = current.join(', ');
}

function clearBatchTags(btn) {
    const container = btn.closest('.row');
    if (!container) return;

    const input = container.querySelector('.batch-tags-input');
    if (input) input.value = '';

    container.querySelectorAll('.batch-genre-tag-btn').forEach(b => {
        b.classList.remove('batch-genre-active');
        b.style.background = 'transparent';
        b.style.color = b.dataset.color;
    });
}