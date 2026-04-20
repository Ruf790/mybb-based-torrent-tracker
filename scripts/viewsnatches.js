'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Конфиг из PHP ─────────────────────────────────────
    const { ratioData: RATIO_DATA, baseUrl: BASEURL, torrentId: TORRENT_ID, scriptName: SCRIPT_NAME } = window.VS_CONFIG;

    // ── Ratio Chart ───────────────────────────────────────
    const ratioCtx = document.getElementById('ratioChart');
    if (ratioCtx) {
        new Chart(ratioCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ratio < 0.5', 'Ratio 0.5–1.0', 'Ratio 1.0–2.0', 'Ratio > 2.0'],
                datasets: [{ data: RATIO_DATA, backgroundColor: ['#dc3545','#ffc107','#198754','#0d6efd'] }]
            },
            options: { responsive: false, cutout: '40%', plugins: { legend: { position: 'bottom' } } }
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
    function sendMassMessage() {
        const selected = getSelectedUsers();
        if (!selected.length) { alert('Please select at least one user'); return; }

        const existing = document.getElementById('massMessageModal');
        if (existing) existing.remove();

        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="massMessageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Send to ${selected.length} Users</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" id="msgSubject" value="Regarding torrent download">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" id="msgText" rows="8">Hello {username},\n\nRegarding the torrent {torrenturl}...\n\nBest regards, Staff</textarea>
                                <div class="form-text">Placeholders: <code>{username}</code> <code>{torrentname}</code> <code>{torrenturl}</code></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="submitMassMessage()">
                                <i class="fas fa-paper-plane me-1"></i>Send
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
        if (!subject || !message) { alert('Please fill subject and message'); return; }
        submitAction('send_message', selected, { message, subject });
    }

    // ── Быстрые действия ──────────────────────────────────
    function quickAction(action) {
        const selected = getSelectedUsers();
        if (!selected.length) { alert('Please select users first'); return; }

        const cfg = {
            reseed_request: { title: 'Send Reseed Requests', cls: 'btn-warning', icon: 'fas fa-seedling' },
            delete_snatches:{ title: 'Delete Snatch Records', cls: 'btn-danger',  icon: 'fas fa-trash' },
            mark_as_seeding:{ title: 'Mark as Seeding',       cls: 'btn-success', icon: 'fas fa-check' },
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
                        <h5 class="modal-title"><i class="${cfg.icon} me-2"></i>${cfg.title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Confirm action for <strong>${selected.length}</strong> selected users?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn ${cfg.cls}" id="confirmActionBtn">
                            <i class="${cfg.icon} me-1"></i>Confirm
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

        const fields = { mass_action: action, selected_users: JSON.stringify(users), ...extra };
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
    setInterval(() => { if (!document.hidden) window.location.reload(); }, 30000);

    // ── Экспорт в window ──────────────────────────────────
    Object.assign(window, {
        selectAllUsers, updateSelectionCounter, showUserDetails,
        sendMassMessage, submitMassMessage, quickAction, submitAction,
        quickSearch, toggleAdvancedFilters,
    });
});