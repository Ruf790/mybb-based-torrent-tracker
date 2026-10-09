/* Database Backups (admin/backupdb.php) */
(function () {
    "use strict";

    /* ---------- Локализация: AGS_LANG выводит PHP (ключи js_* из ланга, без префикса) ---------- */
    const t = (key, fallback, ...args) => {
        const L = (typeof AGS_LANG !== "undefined" && AGS_LANG) ? AGS_LANG : {};
        const src = typeof L[key] === "string" ? L[key] : fallback;
        return src.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] !== undefined ? String(args[n - 1]) : m));
    };

    /* ---------- New backup: выбор таблиц ---------- */
    const form = document.getElementById("table_selection");
    if (form) {
        const boxes = () => [...document.querySelectorAll(".bk .bk-trow input")];
        const fmt = b => {
            const u = ["B", "KB", "MB", "GB", "TB"];
            let i = 0;
            while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
            return (i ? b.toFixed(2) : b) + " " + u[i];
        };
        const sel     = document.getElementById("bkSel");
        const selSize = document.getElementById("bkSelSize");
        const go      = document.getElementById("bkGo");
        const filter  = document.getElementById("bkFilter");

        // Содержимое кнопки: иконка + текст. Перевод вставляем как текст, не через innerHTML
        function setGo(tag, cls, text) {
            const ic = document.createElement(tag);
            ic.className = cls;
            go.replaceChildren(ic, document.createTextNode(text));
        }

        function count() {
            const on = boxes().filter(b => b.checked);
            sel.textContent = on.length;
            selSize.textContent = fmt(on.reduce((s, b) => s + (+b.dataset.size || 0), 0));
            go.disabled = on.length === 0;
        }

        document.querySelectorAll(".bk [data-sel]").forEach(b => b.addEventListener("click", () => {
            const on = b.dataset.sel === "all";
            boxes().forEach(x => { if (!x.closest(".bk-trow").hidden) x.checked = on; });
            count();
        }));

        filter.addEventListener("input", function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll(".bk .bk-trow").forEach(r => { r.hidden = q && !r.dataset.name.includes(q); });
        });

        form.addEventListener("change", count);
        form.addEventListener("submit", function () {
            // Для «Download» страница не перезагружается — через несколько секунд вернём кнопку
            go.disabled = true;
            setGo("span", "spinner-border spinner-border-sm me-1", t("working", "Working…"));
            setTimeout(() => {
                go.disabled = false;
                setGo("i", "fa-solid fa-play me-1", t("create_backup", "Create backup"));
            }, 8000);
        });

        count();
    }

    /* ---------- Список: модалка удаления ---------- */
    const modal = document.getElementById("bkDelModal");
    if (modal) {
        document.addEventListener("click", function (e) {
            const b = e.target.closest(".bk-del");
            if (!b) return;
            document.getElementById("bkDelFile").textContent = b.dataset.file;
            document.getElementById("bkDelForm").action = b.dataset.url;
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    }
})();
