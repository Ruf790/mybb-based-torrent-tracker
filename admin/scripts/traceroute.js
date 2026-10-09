/**
 * Traceroute Utility v3.1 (AJAX Live)
 */
'use strict';

/**
 * Translated string from AGS_LANG with an English fallback.
 * Fills {1}, {2}… (and %1$s… after $lang->load()).
 */
function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string')
        ? AGS_LANG[key]
        : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}

let traceId = null;
let offset = 0;
let timer = null;

const output = document.getElementById('output');
const emptyBox = document.getElementById('traceEmpty');
const statusBox = document.getElementById('traceStatus');

const STATUS = {
    ready:   { cls: 'trc-soft-muted',   ico: 'fa-circle-pause',  key: 'status_ready',   fb: 'Ready' },
    running: { cls: 'trc-soft-warning', ico: 'fa-spinner fa-spin', key: 'status_running', fb: 'Running…' },
    done:    { cls: 'trc-soft-success', ico: 'fa-circle-check',  key: 'status_done',    fb: 'Completed' }
};

function setStatus(name) {
    const st = STATUS[name];
    if (!statusBox || !st) return;
    statusBox.className = 'trc-badge ' + st.cls;
    const ico = document.createElement('i');
    ico.className = 'fa-solid ' + st.ico;
    const txt = document.createElement('span');
    txt.className = 'trc-status-text';
    txt.textContent = t(st.key, st.fb);
    statusBox.replaceChildren(ico, txt);
}

function formatTrace(text) {
    return text
        .replace(/\*/g, '<span class="timeout">*</span>')
        .replace(/^(\s*\d+)/gm, '<span class="hop">$1</span>')
        .replace(/(\d+\.?\d*\s?ms)/gi, '<span class="time">$1</span>');
}

document.getElementById('traceForm').addEventListener('submit', e => {
    e.preventDefault();

    output.innerHTML = '';
    offset = 0;
    if (emptyBox) emptyBox.hidden = true;
    output.hidden = false;
    setStatus('running');

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'start',
            host: document.getElementById('host').value
        })
    })
    .then(r => r.json())
    .then(data => {
        traceId = data.id;
        timer = setInterval(loadProgress, 1000);
    });
});

function loadProgress() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'progress',
            id: traceId,
            offset: offset
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.chunk) {
            output.innerHTML += formatTrace(data.chunk);
            offset = data.size;
            output.scrollTop = output.scrollHeight;
        }
        if (data.done) {
            clearInterval(timer);
            setStatus('done');
        }
    });
}
