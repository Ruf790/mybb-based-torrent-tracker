(function () {
    'use strict';

    const boxes    = () => Array.from(document.querySelectorAll('.user-checkbox'));
    const selected = () => boxes().filter(cb => cb.checked);

    function refresh() {
        const sel = selected();
        document.getElementById('iuSelCount').textContent = sel.length;
        document.querySelectorAll('.iu-act').forEach(b => { b.disabled = sel.length === 0; });
        boxes().forEach(cb => cb.closest('tr').classList.toggle('is-selected', cb.checked));

        const all = boxes();
        const master = document.getElementById('checkAll');
        master.checked = all.length > 0 && sel.length === all.length;
        master.indeterminate = sel.length > 0 && sel.length < all.length;
    }

    function submit(action) {
        document.getElementById('selectedUsersData').value = selected().map(cb => cb.value).join(',');
        document.getElementById('formAction').value = action;
        document.getElementById('mainForm').submit();
    }

    window.submitForm = function (action) {
        const sel = selected();
        if (sel.length === 0) {
            alert('Please select at least one user.');
            return;
        }
        if (action === 'delete_selected_users') {
            document.getElementById('selectedUsersCount').textContent = sel.length;
            const list = document.getElementById('selectedUsersList');
            list.replaceChildren();
            sel.forEach(cb => {
                // textContent, не innerHTML: раньше ник вставлялся как HTML
                const item = document.createElement('span');
                item.className = 'iu-del-item';
                const icon = document.createElement('i');
                icon.className = 'fas fa-user';
                item.append(icon, document.createTextNode(' ' + cb.dataset.username));
                list.appendChild(item);
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteConfirmModal')).show();
        } else if (action === 'send_warn_email') {
            if (confirm('Send warning emails to ' + sel.length + ' user(s)?')) submit(action);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('checkAll').addEventListener('change', function () {
            boxes().forEach(cb => { cb.checked = this.checked; });
            refresh();
        });

        document.addEventListener('change', e => {
            if (e.target.classList && e.target.classList.contains('user-checkbox')) refresh();
        });

        // Клик по строке переключает чекбокс (ссылки и сам чекбокс — как обычно)
        document.querySelector('.iu-table tbody').addEventListener('click', e => {
            if (e.target.closest('a, input, label, button')) return;
            const cb = e.target.closest('tr')?.querySelector('.user-checkbox');
            if (cb) { cb.checked = !cb.checked; refresh(); }
        });

        document.getElementById('iuSelectOverdue').addEventListener('click', () => {
            boxes().forEach(cb => { cb.checked = cb.closest('tr').dataset.state === 'overdue'; });
            refresh();
        });

        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            if (selected().length === 0) return;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
            submit('delete_selected_users');
        });

        refresh();
    });
})();
