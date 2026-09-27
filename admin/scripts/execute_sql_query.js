// Format (simple keyword uppercase)
document.getElementById('qeFormat').addEventListener('click', function(){
    const ta = document.getElementById('query');
    const kw = ['SELECT','FROM','WHERE','JOIN','LEFT JOIN','RIGHT JOIN','INNER JOIN',
                 'ON','GROUP BY','ORDER BY','HAVING','LIMIT','OFFSET','INSERT INTO',
                 'VALUES','UPDATE','SET','DELETE','CREATE','DROP','ALTER','TRUNCATE',
                 'AND','OR','NOT','IN','IS NULL','IS NOT NULL','AS','DISTINCT'];
    let sql = ta.value;
    kw.forEach(k => {
        sql = sql.replace(new RegExp('\\b' + k + '\\b', 'gi'), k);
    });
    ta.value = sql;
});

document.getElementById('qeClear').addEventListener('click', () => {
    document.getElementById('query').value = '';
    document.getElementById('query').focus();
});

// Examples
document.querySelectorAll('.qe-ex-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        document.getElementById('query').value = this.dataset.q;
        document.getElementById('query').focus();
    });
});

// History items
document.querySelectorAll('.qe-history-item').forEach(item => {
    item.addEventListener('click', function(){
        document.getElementById('query').value = this.dataset.q;
        bootstrap.Modal.getInstance(document.getElementById('histModal'))?.hide();
    });
});

// Scroll to results (if a result card is present on the page)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.qe-result-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// Copy result as CSV
document.getElementById('qeCopyCsv')?.addEventListener('click', function(){
    const table = document.getElementById('qeResultTable');
    if (!table) return;

    const rows = [...table.querySelectorAll('tr')].map(tr =>
        [...tr.children]
            .slice(1) // skip the "#" row-number column
            .map(cell => {
                const text = cell.textContent.trim();
                return '"' + text.replace(/"/g, '""') + '"';
            })
            .join(',')
    );

    navigator.clipboard.writeText(rows.join('\n')).then(() => {
        const btn = this;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    });
});

// Ctrl+Enter to submit
document.getElementById('query').addEventListener('keydown', function(e){
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('qeForm').submit();
    }
});

// Подтверждение перед выполнением потенциально деструктивных запросов —
// один клик по "Execute" иначе может снести данные без права на "отменить".
// Серверная сторона тоже проверяет это поле — JS можно отключить или обойти.
document.getElementById('qeForm').addEventListener('submit', function(e){
    const sql = document.getElementById('query').value.trim();
    const destructive = /^\s*(DROP|DELETE|TRUNCATE|UPDATE|ALTER)\b/i;
    const confirmField = document.getElementById('confirmDestructive');
    if (destructive.test(sql)) {
        const ok = confirm(
            'This looks like a destructive query (DROP/DELETE/TRUNCATE/UPDATE/ALTER).\n\n' +
            'It will run immediately with no undo. Continue?'
        );
        if (!ok) {
            e.preventDefault();
            return;
        }
        confirmField.value = '1';
    } else {
        confirmField.value = '0';
    }
});
