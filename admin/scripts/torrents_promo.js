(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const form = $('tpForm');
    let dirty = false;
    const colors = { 't-green': '#22c55e', 't-blue': '#3b82f6', 't-amber': '#f59e0b', 't-teal': '#06b6d4', 't-purple': '#8b5cf6', 't-indigo': '#6366f1', 't-slate': '#94a3b8' };

    // ── Шансы: число ↔ ползунок, полоса распределения, сумма ──
    function chances() {
        let sum = 0; const parts = [];
        document.querySelectorAll('.tp-chance').forEach(row => {
            const v = Math.max(0, Math.min(100, parseInt(row.querySelector('.tp-chance-num').value, 10) || 0));
            sum += v; parts.push([v, colors[row.dataset.cls] || '#94a3b8']);
        });
        $('tpSplit').innerHTML = parts.map(([v, c]) => '<span style="width:' + Math.min(v, 100) + '%;background:' + c + '"></span>').join('');
        const sumEl = $('tpSum');
        // Больше 100% в сумме — такого не бывает, часть правил не сработает
        sumEl.className = 'tp-sum' + (sum > 100 ? ' bad' : '');
        sumEl.innerHTML = sum > 100
            ? '<i class="fa-solid fa-triangle-exclamation me-1"></i>Total ' + sum + '% — more than 100%, lower some chances'
            : '<i class="fa-solid fa-dice me-1"></i>' + sum + '% of new uploads get a random promotion · ' + (100 - sum) + '% stay normal';
    }
    document.querySelectorAll('.tp-chance').forEach(row => {
        const num = row.querySelector('.tp-chance-num'), rng = row.querySelector('.tp-chance-range');
        num.addEventListener('input', () => { rng.value = num.value || 0; chances(); });
        rng.addEventListener('input', () => { num.value = rng.value; chances(); markDirty(); });
    });

    // ── Истечение: строка бледнеет, если 0 дней ──
    document.querySelectorAll('.tp-days').forEach(i => i.addEventListener('input', () => i.closest('.tp-flow').classList.toggle('tp-off', (parseInt(i.value, 10) || 0) === 0)));

    // ── Главный выключатель правил ──
    function rulesState() { $('tpRules').classList.toggle('tp-off', !$('prorules').checked); }
    $('prorules').addEventListener('change', rulesState);

    // ── Несохранённые изменения ──
    function markDirty() { dirty = true; $('tpDirty').hidden = false; }
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('reset', () => setTimeout(() => {
        document.querySelectorAll('.tp-chance').forEach(r => { r.querySelector('.tp-chance-range').value = r.querySelector('.tp-chance-num').value; });
        document.querySelectorAll('.tp-days').forEach(i => i.dispatchEvent(new Event('input')));
        chances(); rulesState(); dirty = false; $('tpDirty').hidden = true;
    }, 0));
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    form.addEventListener('submit', async e => {
        const sum = [...document.querySelectorAll('.tp-chance-num')].reduce((s, i) => s + (parseInt(i.value, 10) || 0), 0);
        if (sum > 100) {
            e.preventDefault();
            const msg = 'Random chances add up to ' + sum + '% — please keep the total at 100% or less.';
            window.Swal ? Swal.fire({ title: 'Too much randomness', text: msg, icon: 'warning', confirmButtonColor: '#0d6efd' }) : alert(msg);
            return;
        }
        if ($('deldeadtorrent').checked && !$('deldeadtorrent').defaultChecked) {
            e.preventDefault();
            const ok = window.Swal
                ? (await Swal.fire({ title: 'Enable auto-delete?', html: 'Torrents without seeders will be <b>deleted automatically</b>. This can\'t be undone.', icon: 'warning', showCancelButton: true, reverseButtons: true, focusCancel: true, confirmButtonText: 'Enable', confirmButtonColor: '#dc2626', cancelButtonColor: '#6c757d' })).isConfirmed
                : confirm('Torrents without seeders will be deleted automatically. Enable?');
            if (!ok) return;
            dirty = false;
            $('tpSave').disabled = true;
            HTMLFormElement.prototype.submit.call(form);
            return;
        }
        dirty = false;
        const b = $('tpSave'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    });

    chances(); rulesState();
})();
