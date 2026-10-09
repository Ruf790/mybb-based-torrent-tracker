/* Staff panel: Warned Users (warned.php) */
(function () {
    "use strict";

    // Language strings from warned.lang.php (js_* keys, prefix stripped)
    const L = (typeof AGS_LANG === "object" && AGS_LANG) ? AGS_LANG : {};

    // t(key, fallback, ...args): {1}/%1$s placeholders, English fallback
    function t(key, fallback, ...args) {
        let s = typeof L[key] === "string" && L[key] !== "" ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split("{" + n + "}").join(String(a)).split("%" + n + "$s").join(String(a));
        });
        return s;
    }

    // Plural form per lang rule: "one" | "few" | "many"
    function plural(n) {
        if (L.plural_rule === "ru") {
            const m10 = n % 10, m100 = n % 100;
            if (m10 === 1 && m100 !== 11) return "one";
            if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return "few";
            return "many";
        }
        return n === 1 ? "one" : "many";
    }

    // Flash message close button
    document.querySelectorAll(".wu-page .wu-flash-close").forEach(b => {
        b.addEventListener("click", () => b.closest(".wu-flash")?.remove());
    });

    const form = document.getElementById("warnedForm");
    if (!form) return;
    const master  = document.getElementById("wuSelectAll");
    const counter = document.getElementById("wuSelCount");
    const btn     = document.getElementById("wuRemoveBtn");
    const clear   = document.getElementById("wuClearBtn");
    const boxes   = () => Array.from(form.querySelectorAll('input[name="userid[]"]'));

    function refresh() {
        const all = boxes();
        const n = all.filter(b => b.checked).length;
        all.forEach(b => b.closest("tr").classList.toggle("wu-selected", b.checked));
        if (counter) counter.textContent = n;
        if (btn) btn.disabled = n === 0;
        if (clear) clear.disabled = n === 0;
        if (master) {
            master.checked = n > 0 && n === all.length;
            master.indeterminate = n > 0 && n < all.length;
        }
    }

    // Kept global for backwards compatibility
    window.select_deselectAll = function (cb) {
        boxes().forEach(b => { b.checked = cb.checked; });
        refresh();
    };

    if (master) master.addEventListener("change", () => window.select_deselectAll(master));

    form.addEventListener("change", e => {
        if (e.target.name === "userid[]") refresh();
    });

    // Click anywhere on a row toggles it (links and the switch keep their own behaviour)
    form.querySelector("tbody").addEventListener("click", e => {
        if (e.target.closest("a, label, input, button")) return;
        const cb = e.target.closest("tr")?.querySelector('input[name="userid[]"]');
        if (!cb) return;
        cb.checked = !cb.checked;
        refresh();
    });

    if (clear) clear.addEventListener("click", () => {
        boxes().forEach(b => { b.checked = false; });
        refresh();
    });

    if (btn) btn.addEventListener("click", () => {
        const n = boxes().filter(b => b.checked).length;
        if (n === 0) return;
        const form_ = plural(n);
        const text = form_ === "one"
            ? t("confirm_text_one", "Warnings will be removed from {1} user.", n)
            : form_ === "few"
                ? t("confirm_text_few", "Warnings will be removed from {1} users.", n)
                : t("confirm_text_many", "Warnings will be removed from {1} users.", n);
        if (window.Swal) {
            Swal.fire({
                title: t("confirm_title", "Remove warnings?"),
                text: text,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: t("confirm_btn", "Remove warnings"),
                cancelButtonText: t("cancel_btn", "Cancel"),
                confirmButtonColor: "#dc3545",
                reverseButtons: true
            }).then(r => { if (r.isConfirmed) form.submit(); });
        } else if (confirm(text + " " + t("continue", "Continue?"))) {
            form.submit();
        }
    });

    refresh();
})();
