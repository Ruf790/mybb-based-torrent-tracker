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
    document.querySelectorAll("tbody tr").forEach(tr => {
        const cells = tr.querySelectorAll("td");
        if (cells.length >= 7) {
            rows.push([
                cells[0].innerText.trim(),
                cells[1].innerText.trim(),
                cells[2].innerText.trim(),
                cells[3].innerText.trim(),
                cells[4].innerText.trim(),
                cells[6].innerText.trim(),
            ]);
        }
    });
    const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')).join("\n");
    const a = document.createElement("a");
    a.href = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
    a.download = "logs_" + new Date().toISOString().slice(0,10) + ".csv";
    a.click();
});



function updateDeleteBtn() {
    const count = document.querySelectorAll('.log-checkbox:checked').length;
    const btn = document.querySelector('#logs-form [type="submit"].btn-danger');
    if (btn) {
        const icon = document.createElement('i');
        icon.className = 'fas fa-trash me-1';
        const label = count > 0
            ? t('delete_selected_n', 'Delete Selected ({1})', count)
            : t('delete_selected', 'Delete Selected');
        btn.replaceChildren(icon, document.createTextNode(' ' + label));
        btn.disabled = count === 0;
    }
}

function showDeleteModal(count, onConfirm) {
    const existing = document.getElementById('deleteConfirmModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'deleteConfirmModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;display:flex;align-items:center;justify-content:center;animation:fadeInBg 0.2s ease';

    modal.innerHTML =
        '<style>' +
        '@keyframes fadeInBg{from{opacity:0}to{opacity:1}}' +
        '@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}' +
        '</style>' +
        '<div style="background:#fff;border-radius:16px;padding:0;width:420px;max-width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideUp 0.25s ease;overflow:hidden">' +
            '<div style="background:linear-gradient(135deg,#e74a3b,#c0392b);padding:24px 28px 20px;text-align:center">' +
                '<div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">' +
                    '<i class="fas fa-trash" style="color:#fff;font-size:22px"></i>' +
                '</div>' +
                '<h5 data-role="title" style="color:#fff;margin:0;font-size:18px;font-weight:700"></h5>' +
            '</div>' +
            '<div style="padding:24px 28px;text-align:center">' +
                '<p data-role="lead" style="color:#5a5c69;margin:0 0 6px;font-size:15px"></p>' +
                '<p data-role="count" style="color:#e74a3b;font-size:28px;font-weight:700;margin:0 0 6px"></p>' +
                '<p data-role="tail" style="color:#5a5c69;margin:0 0 20px;font-size:15px"></p>' +
                '<div style="display:flex;gap:12px;justify-content:center">' +
                    '<button id="modalCancel" style="flex:1;padding:11px;border:2px solid #e3e6f0;background:#fff;border-radius:10px;color:#5a5c69;font-size:14px;font-weight:600;cursor:pointer"></button>' +
                    '<button id="modalConfirm" style="flex:1;padding:11px;border:none;background:linear-gradient(135deg,#e74a3b,#c0392b);border-radius:10px;color:#fff;font-size:14px;font-weight:600;cursor:pointer">' +
                        '<i class="fas fa-trash me-1"></i>' +
                    '</button>' +
                '</div>' +
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
        document.createTextNode(' ' + tp('delete_n', count, 'Delete {1} log', 'Delete {1} logs')));

    document.body.appendChild(modal);

    document.getElementById('modalCancel').onclick = function() { modal.remove(); };
    document.getElementById('modalConfirm').onclick = function() { modal.remove(); onConfirm(); };
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
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
