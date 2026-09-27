(function () {
    "use strict";

    const root = document.querySelector(".ag-awaiting");
    const form = document.getElementById("userActivationForm");
    if (!root || !form) return;

    const boxes    = Array.from(root.querySelectorAll(".user-checkbox"));
    const masters  = [document.getElementById("mainCheckbox"), document.getElementById("selectAll")].filter(Boolean);
    const countEl  = document.getElementById("selectedCount");
    const bar      = document.getElementById("agActionBar");
    const barBtns  = Array.from(root.querySelectorAll("[data-ag-action]"));
    const filter   = document.getElementById("agFilter");
    const noMatch  = document.getElementById("agNoMatch");
    let submitting = false;

    const rowOf     = (box) => box.closest("tr");
    const isVisible = (box) => !rowOf(box).hidden;
    const checked   = () => boxes.filter((b) => b.checked);

    function update() {
        const visible = boxes.filter(isVisible);
        const nVis    = visible.filter((b) => b.checked).length;
        const nAll    = checked().length;

        masters.forEach((m) => {
            m.checked       = visible.length > 0 && nVis === visible.length;
            m.indeterminate = nVis > 0 && nVis < visible.length;
        });
        boxes.forEach((b) => rowOf(b).classList.toggle("is-selected", b.checked));

        if (countEl) countEl.textContent = String(nAll);
        if (bar) bar.classList.toggle("has-selection", nAll > 0);
        barBtns.forEach((btn) => { btn.disabled = nAll === 0 || submitting; });
    }

    masters.forEach((m) => m.addEventListener("change", () => {
        boxes.filter(isVisible).forEach((b) => { b.checked = m.checked; });
        update();
    }));
    boxes.forEach((b) => b.addEventListener("change", update));

    // Quick filter (current page only)
    if (filter) {
        filter.addEventListener("input", () => {
            const q = filter.value.trim().toLowerCase();
            let shown = 0;
            boxes.forEach((b) => {
                const tr  = rowOf(b);
                const hit = q === "" || (tr.dataset.search || "").includes(q);
                tr.hidden = !hit;
                if (hit) shown++;
            });
            if (noMatch) noMatch.hidden = shown > 0;
            update();
        });
    }

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

    function submit(action) {
        if (submitting) return;
        submitting = true;
        form.querySelectorAll('input[name="delete"]').forEach((el) => el.remove());
        if (action === "delete") {
            const del = document.createElement("input");
            del.type = "hidden"; del.name = "delete"; del.value = "1";
            form.appendChild(del);
        }
        update();
        form.submit();
    }

    function confirmAction(action) {
        const sel = checked();
        if (sel.length === 0) {
            if (window.Swal) {
                Swal.fire({ icon: "info", title: "No users selected", text: "Select at least one user first.", customClass: { popup: "ag-swal" } });
            } else {
                alert("Select at least one user first.");
            }
            return false;
        }

        const isActivate = action === "activate";
        const n = sel.length;
        // dataset returns the decoded name, so it must be re-escaped before going into HTML
        const names = sel.slice(0, 8).map((b) => "<li>" + esc(b.dataset.username || "") + "</li>").join("")
                    + (n > 8 ? "<li>+" + (n - 8) + " more</li>" : "");

        if (!window.Swal) {
            const msg = isActivate
                ? "Activate " + n + " user(s)?"
                : "Permanently delete " + n + " user(s)? This cannot be undone.";
            if (confirm(msg)) submit(action);
            return false;
        }

        Swal.fire({
            icon: isActivate ? "question" : "warning",
            title: isActivate ? "Activate " + n + " user(s)?" : "Delete " + n + " user(s)?",
            html: (isActivate
                    ? "They will receive an e-mail and can log in right away."
                    : "Accounts will be removed permanently. <b>This cannot be undone.</b>")
                  + '<ul class="ag-swal-names">' + names + "</ul>",
            input: isActivate ? undefined : "checkbox",
            inputPlaceholder: "I understand this is permanent",
            inputValidator: isActivate ? undefined : (v) => (!v ? "Please tick the box to confirm." : undefined),
            showCancelButton: true,
            focusCancel: !isActivate,
            reverseButtons: true,
            buttonsStyling: false,
            confirmButtonText: isActivate
                ? '<i class="fa-solid fa-circle-check me-2"></i>Activate'
                : '<i class="fa-solid fa-trash-can me-2"></i>Delete',
            cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i>Cancel',
            customClass: {
                popup: "ag-swal",
                confirmButton: "btn rounded-pill px-4 mx-1 " + (isActivate ? "btn-success" : "btn-danger"),
                cancelButton: "btn btn-outline-secondary rounded-pill px-4 mx-1"
            }
        }).then((r) => { if (r.isConfirmed) submit(action); });

        return false;
    }

    barBtns.forEach((btn) => btn.addEventListener("click", () => confirmAction(btn.dataset.agAction)));

    // Per-row quick actions: select only that row, then confirm
    root.querySelectorAll("[data-row-action]").forEach((btn) => btn.addEventListener("click", () => {
        boxes.forEach((b) => { b.checked = b.value === btn.dataset.uid; });
        update();
        confirmAction(btn.dataset.rowAction);
    }));

    // Kept for backwards compatibility with any inline onclick="confirmAction(...)"
    window.confirmAction = confirmAction;

    update();
})();