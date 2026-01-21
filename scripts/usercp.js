document.addEventListener('DOMContentLoaded', () => {

class UserMultiSelect {
    constructor(containerId, hiddenInputId, limit = 5) {
        this.container = document.getElementById(containerId);
        this.hiddenInput = document.getElementById(hiddenInputId);
        this.limit = limit;
        this.selected = [];
        if (this.container) this.init();
    }

    init() {
        this.input = document.createElement('input');
        this.input.placeholder = 'Поиск пользователя...';

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'user-dropdown';
        this.dropdown.hidden = true;

        this.container.append(this.input, this.dropdown);

        this.input.addEventListener('input', () => this.search());

        document.addEventListener('click', e => {
            if (!this.container.contains(e.target)) {
                this.dropdown.hidden = true;
            }
        });

        this.restore();
    }

    async search() {
        const q = this.input.value.trim();
        if (q.length < 2) return;

        const res = await fetch(
            'xmlhttp.php?action=get_users&query=' + encodeURIComponent(q)
        );
        const data = await res.json();

        this.dropdown.innerHTML = '';
        this.dropdown.hidden = false;

        (data.results || data).forEach(user => {
            if (this.selected.some(u => u.id == user.id)) return;

            const opt = document.createElement('div');
            opt.className = 'user-option';
            opt.innerHTML = `
                ${user.avatar ? `<img src="${user.avatar}">` : ''}
                <span>${user.text}</span>
            `;
            opt.addEventListener('click', () => this.add(user));
            this.dropdown.appendChild(opt);
        });
    }

    add(user) {
        if (this.selected.length >= this.limit) {
            alert(`Максимум ${this.limit}`);
            return;
        }
        this.selected.push(user);
        this.render();
        this.save();
        this.dropdown.hidden = true;
        this.input.value = '';
    }

    remove(id) {
        this.selected = this.selected.filter(u => u.id !== id);
        this.render();
        this.save();
    }

    render() {
        this.container.querySelectorAll('.user-tag').forEach(e => e.remove());

        this.selected.forEach(user => {
            const tag = document.createElement('div');
            tag.className = 'user-tag';
            tag.innerHTML = `
                ${user.avatar ? `<img src="${user.avatar}">` : ''}
                ${user.text}
                <span>&times;</span>
            `;
            tag.querySelector('span').addEventListener('click', () => this.remove(user.id));
            this.container.insertBefore(tag, this.input);
        });

        this.hiddenInput.value = this.selected.map(u => u.text).join(',');
    }

    save() {
        localStorage.setItem(this.container.id, JSON.stringify(this.selected));
    }

    restore() {
        const saved = localStorage.getItem(this.container.id);
        if (saved) {
            this.selected = JSON.parse(saved);
            this.render();
        }
    }
}

const UserCP = {
    buddySelect: null,
    ignoredSelect: null,

    init() {
        this.buddySelect = new UserMultiSelect(
            'buddy_add_username',
            'buddy_add_username_input'
        );

        this.ignoredSelect = new UserMultiSelect(
            'ignored_add_username',
            'ignored_add_username_input'
        );

        document.getElementById('buddy_search_btn')
            ?.addEventListener('click', () => this.buddySelect.input.focus());

        document.getElementById('ignored_search_btn')
            ?.addEventListener('click', () => this.ignoredSelect.input.focus());
    },

    async addBuddy(type) {
        const select = type === 'ignored'
            ? this.ignoredSelect
            : this.buddySelect;

        if (!select.hiddenInput.value) return false;
        if (use_xmlhttprequest !== 1) return true;

        const formData = new FormData();
        formData.append('ajax', 1);
        formData.append('add_username', select.hiddenInput.value);

        const res = await fetch(
            `usercp.php?action=do_editlists&manage=${type}&my_post_key=${my_post_key}`,
            { method: 'POST', body: formData }
        );

        const html = await res.text();
        document.getElementById(
            type === 'ignored' ? 'ignore_list' : 'buddy_list'
        ).innerHTML = html;

        select.selected = [];
        select.render();
        localStorage.removeItem(select.container.id);

        return false;
    },

    removeBuddy(type, uid) {
        if (!confirm(type === 'ignored' ? lang.remove_ignored : lang.remove_buddy)) {
            return false;
        }

        fetch(
            `usercp.php?action=do_editlists&manage=${type}&delete=${uid}&my_post_key=${my_post_key}`,
            { method: 'POST', body: new URLSearchParams({ ajax: 1 }) }
        );

        return false;
    }
};

window.UserCP = UserCP;
UserCP.init();

});
