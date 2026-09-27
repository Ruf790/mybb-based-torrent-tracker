/* Staff panel: Warned Users (warned.php) */
(function () {
    "use strict";

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
        const text = "Warnings will be removed from " + n + " user" + (n === 1 ? "" : "s") + ".";
        if (window.Swal) {
            Swal.fire({
                title: "Remove warnings?",
                text: text,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Remove warnings",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#dc3545",
                reverseButtons: true
            }).then(r => { if (r.isConfirmed) form.submit(); });
        } else if (confirm(text + " Continue?")) {
            form.submit();
        }
    });

    refresh();
})();
