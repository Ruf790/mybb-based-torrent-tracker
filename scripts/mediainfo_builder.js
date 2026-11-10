/* MediaInfo template builder (upload.php)
   Fields → text in the short summary format that include/functions_mediainfo.php
   always parses:
     Quality: WEB-DL 2160p
     Container: MKV
     Video: HEVC, 3840x2160 (16:9), 23.976 fps, 24.3 Mbps, 10 bit, DV / HDR10
     Audio Rus: AC3, 5.1, 384 kbps | Dub
     Subtitles: Russian (Forced, SRT), English (Full, SDH, PGS)
*/
(function () {
    'use strict';

    const box    = document.getElementById('miBuilder');
    const toggle = document.getElementById('miBuilderToggle');
    const area   = document.getElementById('mediainfo');
    if (!box || !toggle || !area) return;

    const rows    = document.getElementById('miAudioRows');
    const tpl     = document.getElementById('miAudioTpl');
    const subRows = document.getElementById('miSubRows');
    const subTpl  = document.getElementById('miSubTpl');
    const L    = (k, d) => (typeof AGS_LANG !== 'undefined' && AGS_LANG[k]) ? AGS_LANG[k] : d;

    let lastGenerated = null;   // text we wrote ourselves — safe to overwrite

    const val   = (sel) => (box.querySelector(sel)?.value || '').trim();
    const clean = (s) => s.replace(/[|\r\n]+/g, ' ').trim();

    // 3840x1608 → «2.39:1»; 1920x1080 → «16:9»
    function autoAspect(res) {
        const m = res.match(/^(\d{3,4})x(\d{3,4})$/);
        // DVD 720x576 / 720x480 — анаморф, по пикселям соотношение не угадать
        if (!m || +m[1] <= 720) return '';
        const r = +m[1] / +m[2];
        const known = [[4 / 3, '4:3'], [16 / 9, '16:9'], [1.85, '1.85:1'], [2.0, '2.00:1'], [2.2, '2.20:1'], [2.39, '2.39:1']];
        for (const [k, label] of known) {
            if (Math.abs(r - k) < 0.025) return label;
        }
        return r.toFixed(2) + ':1';
    }

    function syncAutoLabel(res) {
        const sel = box.querySelector('[data-mi="vaspect"]');
        const opt = sel?.querySelector('option[value=""]');
        if (!opt) return;
        const a = autoAspect(res);
        opt.textContent = (sel.dataset.autoLabel || 'Auto') + (a ? ' (' + a + ')' : '');
    }

    function addAudio() {
        const node = tpl.content.firstElementChild.cloneNode(true);
        rows.appendChild(node);
        return node;
    }

    function addSub() {
        const node = subTpl.content.firstElementChild.cloneNode(true);
        subRows.appendChild(node);
        return node;
    }

    function build() {
        const out = [];
        const q = clean(val('[data-mi="quality"]'));
        const c = val('[data-mi="container"]');
        const d = clean(val('[data-mi="duration"]'));
        const sNum = val('[data-mi="size"]').replace(',', '.').replace(/[^\d.]/g, '');
        const s    = sNum ? sNum + ' ' + (val('[data-mi="sizeunit"]') || 'GB') : '';
        if (q) out.push('Quality: ' + q);
        if (c) out.push('Container: ' + c);
        if (d) out.push('Duration: ' + d);
        if (s) out.push('Size: ' + s);

        const v = [];
        const codec = val('[data-mi="vcodec"]');
        const res   = clean(val('[data-mi="vres"]')).replace(/\s*[x×х]\s*/i, 'x');
        const asp   = val('[data-mi="vaspect"]') || autoAspect(res);   // вручную или авто
        syncAutoLabel(res);
        const fps   = val('[data-mi="vfps"]');
        const br    = val('[data-mi="vbitrate"]').replace(',', '.').replace(/[^\d.]/g, '');
        const depth = val('[data-mi="vdepth"]');
        const hdr   = [...box.querySelectorAll('[data-mi-hdr]:checked')].map(i => i.value);
        if (codec) v.push(codec);
        if (res)   v.push(res + (asp ? ' (' + asp + ')' : ''));
        else if (asp) v.push(asp);
        if (fps)   v.push(fps + ' fps');
        if (br)    v.push(br + ' Mbps');
        if (depth) v.push(depth);
        if (hdr.length) v.push(hdr.join(' / '));
        if (v.length) out.push('Video: ' + v.join(', '));

        rows.querySelectorAll('[data-mi-audio]').forEach(r => {
            const g = (k) => (r.querySelector('[data-mi-a="' + k + '"]')?.value || '').trim();
            const parts = [];
            if (g('codec')) parts.push(g('codec'));
            if (g('ch'))    parts.push(g('ch'));
            const abr = g('bitrate').replace(/[^\d]/g, '');
            if (abr)        parts.push(abr + ' kbps');
            if (!parts.length) return;
            const title = clean(g('title'));
            out.push('Audio' + (g('lang') ? ' ' + g('lang') : '') + ': ' + parts.join(', ') + (title ? ' | ' + title : ''));
        });

        const subs = [];
        subRows.querySelectorAll('[data-mi-sub]').forEach(r => {
            const lang = r.querySelector('[data-mi-s="lang"]')?.value || '';
            if (!lang) return;
            const extra = [...r.querySelectorAll('[data-mi-flag]:checked')].map(c => c.value);
            const fmt   = r.querySelector('[data-mi-s="format"]')?.value || '';
            if (fmt) extra.push(fmt);
            subs.push(lang + (extra.length ? ' (' + extra.join(', ') + ')' : ''));
        });
        if (subs.length) out.push('Subtitles: ' + subs.join(', '));

        return out.join('\n');
    }

    function sync() {
        const text = build();
        area.value = text;
        lastGenerated = text;
        area.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }

    // Pre-fill builder from text it generated earlier (edit page)
    function load(text) {
        const set = (sel, v) => { const el = box.querySelector(sel); if (el) el.value = v; };
        let any = false;
        text.split('\n').forEach(line => {
            const m = line.match(/^(Quality|Container|Duration|Size|Video|Audio(?: (\w{2,4}))?|Subtitles):\s*(.*)$/);
            if (!m) return;
            any = true;
            const key = m[1].split(' ')[0], v = m[3];
            if (key === 'Quality')   set('[data-mi="quality"]', v);
            if (key === 'Container') set('[data-mi="container"]', v);
            if (key === 'Duration')  set('[data-mi="duration"]', v);
            if (key === 'Size') {
                const r = v.match(/^([\d.]+)\s*(GB|MB|TB)?$/i);
                set('[data-mi="size"]', r ? r[1] : v);
                if (r && r[2]) set('[data-mi="sizeunit"]', r[2].toUpperCase());
            }
            if (key === 'Subtitles') {
                v.split(/,\s*(?![^()]*\))/).forEach(p => {
                    const r = p.trim().match(/^([^(]+?)\s*(?:\((.*)\))?$/);
                    if (!r) return;
                    const row = addSub();
                    const sel = row.querySelector('[data-mi-s="lang"]');
                    if (sel) sel.value = r[1];
                    (r[2] || '').split(',').map(x => x.trim()).forEach(f => {
                        const fs = row.querySelector('[data-mi-s="format"]');
                        if (fs && [...fs.options].some(o => o.value === f)) { fs.value = f; return; }
                        row.querySelectorAll('[data-mi-flag]').forEach(cb => { if (cb.value === f) cb.checked = true; });
                    });
                });
            }
            if (key === 'Video') {
                v.split(/,\s+(?![^()]*\))/).forEach(p => {
                    let r;
                    if ((r = p.match(/^(\d{3,4}x\d{3,4})(?:\s*\(([^)]+)\))?$/))) {
                        set('[data-mi="vres"]', r[1]);
                        // Совпадает с авто — оставляем «Авто», иначе это ручной выбор
                        if (r[2] && r[2] !== autoAspect(r[1])) set('[data-mi="vaspect"]', r[2]);
                    }
                    else if ((r = p.match(/^([\d.]+) fps$/)))  set('[data-mi="vfps"]', r[1]);
                    else if ((r = p.match(/^([\d.]+) Mbps$/))) set('[data-mi="vbitrate"]', r[1]);
                    else if (/^\d+ bit$/.test(p))              set('[data-mi="vdepth"]', p);
                    else if (/^(DV|HDR10\+?|HLG)( \/ |$)/.test(p)) {
                        p.split(' / ').forEach(h => box.querySelectorAll('[data-mi-hdr]').forEach(cb => { if (cb.value === h) cb.checked = true; }));
                    } else if (/^\d+:\d+$|^\d\.\d+:1$/.test(p)) set('[data-mi="vaspect"]', p);
                    else set('[data-mi="vcodec"]', p);
                });
            }
            if (key === 'Audio') {
                const row = addAudio();
                const sa = (k, x) => { const el = row.querySelector('[data-mi-a="' + k + '"]'); if (el) el.value = x; };
                if (m[2]) sa('lang', m[2]);
                const [tech, title] = v.split(' | ');
                if (title) sa('title', title);
                (tech || '').split(', ').forEach(p => {
                    let r;
                    if (/^\d\.\d$/.test(p)) sa('ch', p);
                    else if ((r = p.match(/^(\d+) kbps$/))) sa('bitrate', r[1]);
                    else sa('codec', p);
                });
            }
        });
        return any;
    }

    toggle.addEventListener('click', () => {
        const opening = box.classList.contains('d-none');
        if (opening) {
            const cur = area.value.trim();
            if (!rows.children.length) {
                if (cur !== '' && load(cur) && build() === cur) {
                    lastGenerated = cur;      // our own format — keep editing it
                } else {
                    rows.innerHTML = '';
                    addAudio();
                }
            }
            // Someone else's text (pasted report) — overwrite only with consent
            if (cur !== '' && cur !== lastGenerated
                && !confirm(L('mib_replace', 'The template will replace the current MediaInfo text. Continue?'))) {
                return;
            }
        }
        box.classList.toggle('d-none', !opening);
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });

    document.getElementById('miAudioAdd')?.addEventListener('click', () => { addAudio(); });

    document.getElementById('miSubAdd')?.addEventListener('click', () => { addSub(); });

    box.addEventListener('click', (e) => {
        const del = e.target.closest('[data-mi-del]');
        if (!del) return;
        del.closest('[data-mi-audio], [data-mi-sub]')?.remove();
        sync();
    });

    box.addEventListener('input', (e) => {
        if (e.target.closest('input, select')) sync();
    });
    box.addEventListener('change', (e) => {
        if (e.target.closest('select, [type="checkbox"]')) sync();
    });

    // Hand edits in the textarea detach it from the builder
    area.addEventListener('input', (e) => {
        if (e.isTrusted && area.value !== lastGenerated) lastGenerated = null;
    });
})();
