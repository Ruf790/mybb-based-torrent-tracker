'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Конфиг из PHP ─────────────────────────────────────
    const { ratioData: RATIO_DATA, baseUrl: BASEURL, torrentId: TORRENT_ID, scriptName: SCRIPT_NAME, postCode: POST_CODE } = window.VS_CONFIG;

    // ── Texts: VS_CONFIG.lang (js_vs_* keys of viewsnatches.lang.php), English fallback ──
    const LANG = window.VS_CONFIG.lang || {};
    const t = (key, fallback, ...args) =>
        String(typeof LANG['vs_' + key] === 'string' ? LANG['vs_' + key] : fallback)
            .replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
                const i = parseInt(a || b, 10) - 1;
                return i >= 0 && i < args.length ? String(args[i]) : m;
            });
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

    // ── Ratio Chart ───────────────────────────────────────
    const ratioContainer = document.getElementById('ratioChart');
    if (ratioContainer && window.Highcharts) {
        const RATIO_LABELS = [
            t('ratio_1', 'Ratio < 0.5'), t('ratio_2', 'Ratio 0.5–1.0'),
            t('ratio_3', 'Ratio 1.0–2.0'), t('ratio_4', 'Ratio > 2.0'),
        ];
        const RATIO_COLORS = ['#dc3545', '#ffc107', '#198754', '#0d6efd'];

        Highcharts.chart('ratioChart', {
            chart: {
                type: 'pie',
                backgroundColor: 'transparent',
                animation: { duration: 600 }
            },
            title: { text: null },
            credits: { enabled: false },
            tooltip: {
                pointFormat: '<b>{point.y}</b> ' + esc(t('users_word', 'users')) + ' ({point.percentage:.1f}%)'
            },
            legend: {
                enabled: true,
                verticalAlign: 'bottom',
                itemStyle: { fontWeight: '500' }
            },
            plotOptions: {
                pie: {
                    innerSize: '55%',
                    borderRadius: 4,
                    borderWidth: 2,
                    borderColor: '#fff',
                    dataLabels: {
                        enabled: true,
                        format: '{point.percentage:.0f}%',
                        distance: -28,
                        style: { fontWeight: '600', textOutline: 'none' },
                        filter: { property: 'percentage', operator: '>', value: 5 }
                    },
                    showInLegend: true
                }
            },
            series: [{
                name: t('users', 'Users'),
                colorByPoint: true,
                data: RATIO_LABELS.map((name, i) => ({
                    name,
                    y: RATIO_DATA[i],
                    color: RATIO_COLORS[i]
                }))
            }]
        });
    }

    // ── Выбор пользователей ───────────────────────────────
    function getSelectedUsers() {
        return [...document.querySelectorAll('.user-checkbox:checked')].map(c => c.value);
    }

    function updateSelectionCounter() {
        const n  = document.querySelectorAll('.user-checkbox:checked').length;
        const el = document.getElementById('selectedCount');
        if (el) el.textContent = n;
    }

    function selectAllUsers(checked) {
        document.querySelectorAll('.user-checkbox').forEach(c => c.checked = checked);
        updateSelectionCounter();
    }

    // ── Поиск ─────────────────────────────────────────────
    function quickSearch(term) {
        const q    = term.toLowerCase();
        const rows = document.querySelectorAll('#snatchTable tbody tr');
        let visible = 0;
        rows.forEach(row => {
            const match = !q || row.textContent.toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const el = document.getElementById('visibleCount');
        if (el) el.textContent = visible;
    }

    // ── Фильтры ───────────────────────────────────────────
    function toggleAdvancedFilters() {
        const el = document.getElementById('advancedFilters');
        if (el) el.classList.toggle('d-none');
    }

    // ── Детали пользователя ───────────────────────────────
    function showUserDetails(userId) {
        fetch(`${BASEURL}/ajax/user_snatch_details.php?userid=${userId}&torrentid=${TORRENT_ID}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('userDetailsContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
            })
            .catch(err => console.error('Error:', err));
    }

    // ── Массовое сообщение ────────────────────────────────
    // Default texts as shown to the moderator: if they are sent unchanged, PHP replaces them
    // with the same texts in each recipient's language
    const DEFAULT_SUBJECT = t('default_subject', 'Regarding torrent download');
    const DEFAULT_BODY    = t('default_body', 'Hello {username},\n\nRegarding the torrent {torrenturl}...\n\nBest regards, Staff').replace(/\\n/g, '\n');

    function sendMassMessage() {
        const selected = getSelectedUsers();
        if (!selected.length) { alert(t('select_user', 'Please select at least one user')); return; }

        const existing = document.getElementById('massMessageModal');
        if (existing) existing.remove();

        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="massMessageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>${esc(t('send_to', 'Send to {1} users', selected.length))}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">${esc(t('subject', 'Subject'))}</label>
                                <input type="text" class="form-control" id="msgSubject" value="${esc(DEFAULT_SUBJECT)}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">${esc(t('message', 'Message'))}</label>
                                <textarea class="form-control" id="msgText" rows="8">${esc(DEFAULT_BODY)}</textarea>
                                <div class="form-text">${esc(t('placeholders', 'Placeholders:'))} <code>{username}</code> <code>{torrentname}</code> <code>{torrenturl}</code></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${esc(t('cancel', 'Cancel'))}</button>
                            <button type="button" class="btn btn-primary" onclick="submitMassMessage()">
                                <i class="fas fa-paper-plane me-1"></i>${esc(t('send', 'Send'))}
                            </button>
                        </div>
                    </div>
                </div>
            </div>`);
        new bootstrap.Modal(document.getElementById('massMessageModal')).show();
    }

    function submitMassMessage() {
        const subject  = document.getElementById('msgSubject').value.trim();
        const message  = document.getElementById('msgText').value.trim();
        const selected = getSelectedUsers();
        if (!subject || !message) { alert(t('fill_fields', 'Please fill in the subject and the message')); return; }
        // Normalise line breaks: the textarea may give \r\n
        const untouched = subject === DEFAULT_SUBJECT.trim()
            && message.replace(/\r\n/g, '\n') === DEFAULT_BODY.trim();
        submitAction('send_message', selected, { message, subject, use_default: untouched ? '1' : '' });
    }

    // ── Быстрые действия ──────────────────────────────────
    function quickAction(action) {
        const selected = getSelectedUsers();
        if (!selected.length) { alert(t('select_user', 'Please select at least one user')); return; }

        const cfg = {
            reseed_request: { title: t('act_reseed', 'Send reseed requests'),   cls: 'btn-warning', icon: 'fas fa-seedling' },
            delete_snatches:{ title: t('act_delete', 'Delete snatch records'),  cls: 'btn-danger',  icon: 'fas fa-trash' },
            mark_as_seeding:{ title: t('act_mark',   'Mark as seeding'),        cls: 'btn-success', icon: 'fas fa-check' },
        }[action];
        if (!cfg) return;

        const existing = document.getElementById('confirmActionModal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'confirmActionModal';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="${cfg.icon} me-2"></i>${esc(cfg.title)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${esc(t('confirm_for', 'Confirm the action for {1} selected users?', '\u0000')).replace('\u0000', '<strong>' + selected.length + '</strong>')}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${esc(t('cancel', 'Cancel'))}</button>
                        <button type="button" class="btn ${cfg.cls}" id="confirmActionBtn">
                            <i class="${cfg.icon} me-1"></i>${esc(t('confirm', 'Confirm'))}
                        </button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
        modal.querySelector('#confirmActionBtn').addEventListener('click', () => submitAction(action, selected));
        new bootstrap.Modal(modal).show();
    }

    function submitAction(action, users, extra = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${SCRIPT_NAME}?id=${TORRENT_ID}`;

        const fields = { mass_action: action, selected_users: JSON.stringify(users), my_post_key: POST_CODE, ...extra };
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = name;
            input.value = value;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
    }

    // ── Авто-обновление ───────────────────────────────────
    // Not while the moderator is working: an open dialog, ticked users or a search
    // would be lost on reload (before, the page reloaded in the middle of writing a PM)
    setInterval(() => {
        if (document.hidden) return;
        if (document.querySelector('.modal.show')) return;
        if (document.querySelector('.user-checkbox:checked')) return;
        const search = document.getElementById('searchInput');
        if (search && search.value.trim() !== '') return;
        window.location.reload();
    }, 30000);

    // ── Экспорт в window ──────────────────────────────────
    Object.assign(window, {
        selectAllUsers, updateSelectionCounter, showUserDetails,
        sendMassMessage, submitMassMessage, quickAction, submitAction,
        quickSearch, toggleAdvancedFilters,
    });
});