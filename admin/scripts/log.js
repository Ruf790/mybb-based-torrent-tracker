/* admin/log.php — Combined Logs. Strings: AGS_LANG (js_* keys of log.lang.php) */
(function () {
'use strict';


// t(key, fallback, ...args): {1} / %1$s substitution, English fallback
function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((a, i) => {
        s = s.split('{' + (i + 1) + '}').join(String(a)).split('%' + (i + 1) + '$s').join(String(a));
    });
    return s;
}

// Plural forms: key_one / key_few / key_many (Intl.PluralRules by js_plural_locale)
function tp(key, n, fbOne, fbMany) {
    let cat = 'many';
    try { cat = new Intl.PluralRules(t('plural_locale', 'en')).select(n); } catch (e) { cat = n === 1 ? 'one' : 'many'; }
    if (cat !== 'one' && cat !== 'few') cat = 'many';
    return t(key + '_' + cat, cat === 'one' ? fbOne : fbMany, n);
}

// Date picker (value comes from the input itself)
const dateInput = document.getElementById("date-filter");
if (dateInput && typeof window.flatpickr === "function") {
    window.flatpickr(dateInput, {
        dateFormat: "Y-m-d",
        allowInput: true,
        defaultDate: dateInput.value || null
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const filterForm = document.getElementById("filter-form");
    if (!filterForm) {
        console.error("Filter form not found");
        return;
    }
    const selectAllCheckbox = document.getElementById("select-all");
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function() {
            document.querySelectorAll(".log-checkbox").forEach(cb => cb.checked = this.checked);
        });
    }
    const eventFilter = document.getElementById("event-filter");
    const dateFilter = document.getElementById("date-filter");
    // log type: select (old markup) or radio pills (new)
    document.querySelectorAll('input[name="log_type"]').forEach(r => r.addEventListener("change", () => filterForm.submit()));
    const logTypeFilter = document.getElementById("log-type");
    [eventFilter, dateFilter, logTypeFilter].forEach(el => {
        if (el) {
            el.addEventListener("change", () => filterForm.submit());
        }
    });
    const searchInput = document.getElementById("search-input");
    let searchTimer;
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => filterForm.submit(), 800);
        });
    }
    const refreshBtn = document.getElementById("refresh-logs");
    if (refreshBtn) {
        refreshBtn.addEventListener("click", () => window.location.reload());
    }
});

document.getElementById("export-csv")?.addEventListener("click", function() {
    const rows = [[t('csv_type', 'Type'), t('csv_username', 'Username'), t('csv_date', 'Date'),
                   t('csv_time', 'Time'), t('csv_action', 'Action'), t('csv_ip', 'IP')]];
    document.querySelectorAll("tr.lg-row").forEach(tr => {
        const d = tr.dataset;
        rows.push([d.type || '', d.user || '', d.date || '', d.time || '', d.action || '', d.ip || ''].map(v => v.trim()));
    });
    // BOM: Excel opens UTF-8 (Cyrillic) correctly
    const csv = "\ufeff" + rows.map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')).join("\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    a.download = "logs_" + new Date().toISOString().slice(0,10) + ".csv";
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
});



function updateDeleteBtn() {
    const count = document.querySelectorAll('.log-checkbox:checked').length;
    const btn = document.querySelector('#logs-form [type="submit"].btn-danger');
    if (btn && !btn.dataset.label) btn.dataset.label = btn.textContent.trim();
    if (btn) {
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-trash me-1';
        const label = count > 0
            ? t('delete_selected_n', 'Delete Selected ({1})', count)
            : (btn.dataset.label || t('delete_selected', 'Delete Selected'));
        btn.replaceChildren(icon, document.createTextNode(label));
        btn.disabled = count === 0;
    }
}

function showDeleteModal(count, onConfirm) {
    document.getElementById('deleteConfirmModal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'deleteConfirmModal';
    modal.className = 'lg-dm';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML =
        '<div class="lg-dm__box">' +
            '<span class="lg-dm__ico"><i class="fa-solid fa-trash-can"></i></span>' +
            '<h5 class="lg-dm__title" data-role="title"></h5>' +
            '<div class="lg-dm__txt" data-role="lead"></div>' +
            '<div class="lg-dm__n" data-role="count"></div>' +
            '<p class="lg-dm__txt" data-role="tail"></p>' +
            '<div class="lg-dm__btns">' +
                '<button type="button" class="btn btn-outline-secondary" id="modalCancel"></button>' +
                '<button type="button" class="btn btn-danger" id="modalConfirm"><i class="fa-solid fa-trash me-1"></i></button>' +
            '</div>' +
        '</div>';

    // Translations go in as text, never via innerHTML
    modal.querySelector('[data-role="title"]').textContent = t('confirm_title', 'Confirm Deletion');
    modal.querySelector('[data-role="lead"]').textContent  = t('about_to_delete', 'You are about to delete');
    modal.querySelector('[data-role="count"]').textContent = String(count);
    modal.querySelector('[data-role="tail"]').textContent  =
        tp('entries', count, 'log entry. This cannot be undone.', 'log entries. This cannot be undone.');
    modal.querySelector('#modalCancel').textContent = t('cancel', 'Cancel');
    modal.querySelector('#modalConfirm').appendChild(
        document.createTextNode(tp('delete_n', count, 'Delete {1} log', 'Delete {1} logs')));

    document.body.appendChild(modal);
    const close = () => { modal.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = e => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onKey);

    modal.querySelector('#modalCancel').onclick = close;
    modal.querySelector('#modalConfirm').onclick = function() { close(); onConfirm(); };
    modal.addEventListener('click', function(e) { if (e.target === modal) close(); });
    modal.querySelector('#modalConfirm').focus();
}

document.addEventListener('DOMContentLoaded', function() {
    updateDeleteBtn();

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('log-checkbox')) {
            updateDeleteBtn();
        }
    });

    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.log-checkbox').forEach(cb => cb.checked = this.checked);
            updateDeleteBtn();
        });
    }

    const logsForm = document.getElementById('logs-form');
    if (logsForm) {
        logsForm.addEventListener('submit', function(e) {
            const count = document.querySelectorAll('.log-checkbox:checked').length;
            if (count === 0) { e.preventDefault(); return; }
            e.preventDefault();
            showDeleteModal(count, function() {
                logsForm.submit();
            });
        });
    }
});
})();
