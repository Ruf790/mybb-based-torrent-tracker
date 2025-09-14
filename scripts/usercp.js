const UserCP = {
    buddyField: null,

    init() {
        // Закрытие popup по Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.querySelector('#buddyselect_container')?.offsetParent !== null) {
                document.querySelector('#buddyselect_container').style.display = 'none';
            }
        });
    },

    regenBuddySelected() {
        const selectedBuddies = Array.from(document.querySelectorAll('input[id^="checkbox_"]'))
            .filter(cb => cb.checked)
            .map(cb => cb.parentElement.textContent.trim());

        document.querySelector("#buddyselect_buddies").textContent = selectedBuddies.join(', ');
    },

    async openBuddySelect(field) {
        const fieldEl = document.getElementById(field);
        if (!fieldEl) return false;

        this.buddyField = `#${field}`;

        if (document.getElementById("buddyselect_container")) {
            this.buddySelectLoaded();
            return false;
        }

        if (use_xmlhttprequest === 1) {
            try {
                const response = await fetch('xmlhttp.php?action=get_buddyselect');
                const text = await response.text();
                this.buddySelectLoaded({ responseText: text });
            } catch (err) {
                console.error('Ошибка загрузки списка друзей:', err);
            }
        }
    },

    buddySelectLoaded(request) {
        let container = document.getElementById("buddyselect_container");

        if (request) {
            try {
                const json = JSON.parse(request.responseText);
                if (json.errors) {
                    json.errors.forEach(msg => $.jGrowl(lang.buddylist_error + msg, { theme: 'jgrowl_error' }));
                    return false;
                }
            } catch {
                if (request.responseText) {
                    container?.remove();
                    container = document.createElement('div');
                    container.id = "buddyselect_container";
                    container.innerHTML = request.responseText;
                    container.style.display = 'none';
                    document.body.appendChild(container);
                }
            }
        }

        Object.assign(container.style, {
            top: "50%",
            left: "50%",
            position: "fixed",
            display: "block",
            zIndex: "1000",
            textAlign: "left",
            transform: "translate(-50%, -50%)"
        });

        // Сброс чекбоксов
        document.querySelectorAll('input[id^="checkbox_"]').forEach(cb => cb.checked = false);

        // Выставляем выбранные опции
        const listedBuddies = $(this.buddyField).select2("data");
        listedBuddies.forEach(user => {
            document.querySelectorAll('input[id^="checkbox_"]').forEach(cb => {
                if (cb.parentElement.textContent.trim() === user.text) cb.checked = true;
            });
        });

        this.regenBuddySelected();
    },

    closeBuddySelect(canceled = false) {
        if (!canceled) {
            const buddies = document.querySelector("#buddyselect_buddies").textContent.split(",")
                .map(b => b.trim())
                .filter(b => b);

            const newBuddies = buddies.map(b => ({ id: b, text: b }));
            $(this.buddyField).select2("data", newBuddies).select2("focus");
        }
        document.getElementById("buddyselect_container").style.display = 'none';
    },

    async addBuddy(type) {
        const typeSubmit = document.getElementById(`${type}_submit`);
        const typeAdd = document.getElementById(`${type}_add_username`);

        if (!typeAdd.value.length) return false;
        if (use_xmlhttprequest !== 1) return true;

        const oldValue = typeSubmit.value;
        typeAdd.disabled = typeSubmit.disabled = true;
        typeSubmit.value = type === "ignored" ? lang.adding_ignored : lang.adding_buddy;
        const list = type === "ignored" ? "ignore" : "buddy";

        try {
            const formData = new FormData();
            formData.append('ajax', 1);
            formData.append('add_username', typeAdd.value);

            const res = await fetch(`usercp.php?action=do_editlists&my_post_key=${my_post_key}&manage=${type}`, {
                method: 'POST',
                body: formData
            });

            const text = await res.text();
            if (text.includes("buddy_count") || text.includes("ignored_count")) {
                document.getElementById(`${list}_list`).innerHTML = text;
            } else {
                document.getElementById("sentrequests").innerHTML = text;
            }
        } catch (err) {
            console.error("Ошибка добавления пользователя:", err);
        } finally {
            typeSubmit.disabled = typeAdd.disabled = false;
            typeSubmit.value = oldValue;
            typeAdd.value = '';
            typeAdd.focus();
            $(typeAdd).select2('data', null);
        }

        return false;
    },

    removeBuddy(type, uid) {
    const message = type === "ignored" ? lang.remove_ignored : lang.remove_buddy;

    if (confirm(message)) {
        fetch(`usercp.php?action=do_editlists&my_post_key=${my_post_key}&manage=${type}&delete=${uid}`, {
            method: 'POST',
            body: new URLSearchParams({ ajax: 1 })
        }).catch(err => {
            console.error("Ошибка удаления пользователя:", err);
        });
    }

    return false;
}


};

document.addEventListener('DOMContentLoaded', () => UserCP.init());
