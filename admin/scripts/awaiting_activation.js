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

    /* i18n: t(key, fallback, ...args) — AGS_LANG comes from PHP (js_* keys without
       the prefix); substitutes {1} and %1$s. Result is plain text: insert it with
       textContent / createTextNode, or esc() it before any HTML. */
    const t = (key, fallback, ...args) => {
        let s = (typeof AGS_LANG === "object" && AGS_LANG && typeof AGS_LANG[key] === "string")
            ? AGS_LANG[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split("{" + n + "}").join(String(a)).split("%" + n + "$s").join(String(a));
        });
        return s;
    };

    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    };

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
                Swal.fire({
                    icon: "info",
                    titleText: t("none_title", "No users selected"),
                    text: t("none_text", "Select at least one user first."),
                    customClass: { popup: "ag-swal" }
                });
            } else {
                alert(t("none_text", "Select at least one user first."));
            }
            return false;
        }

        const isActivate = action === "activate";
        const n = sel.length;
        if (!window.Swal) {
            const msg = isActivate
                ? t("activate_title", "Activate {1} user(s)?", n)
                : t("delete_confirm_plain", "Permanently delete {1} user(s)? This cannot be undone.", n);
            if (confirm(msg)) submit(action);
            return false;
        }

        // Dialog body built from DOM nodes: names and lang strings go in as text
        const body = el("div");
        if (isActivate) {
            body.appendChild(el("div", "", t("activate_text", "They will receive an e-mail and a PM and can log in right away.")));
        } else {
            const line = el("div", "", t("delete_text", "Accounts will be removed permanently.") + " ");
            line.appendChild(el("b", "", t("delete_irreversible", "This cannot be undone.")));
            body.appendChild(line);
        }
        const list = el("ul", "ag-swal-names");
        sel.slice(0, 8).forEach((b) => list.appendChild(el("li", "", b.dataset.username || "")));
        if (n > 8) list.appendChild(el("li", "", t("more", "+{1} more", n - 8)));
        body.appendChild(list);

        Swal.fire({
            icon: isActivate ? "question" : "warning",
            titleText: isActivate
                ? t("activate_title", "Activate {1} user(s)?", n)
                : t("delete_title", "Delete {1} user(s)?", n),
            html: body,
            input: isActivate ? undefined : "checkbox",
            inputPlaceholder: t("delete_checkbox", "I understand this is permanent"),
            inputValidator: isActivate ? undefined : (v) => (!v ? esc(t("delete_validator", "Please tick the box to confirm.")) : undefined),
            showCancelButton: true,
            focusCancel: !isActivate,
            reverseButtons: true,
            buttonsStyling: false,
            // Button labels are HTML (icon + text), so the lang text is escaped
            confirmButtonText: isActivate
                ? '<i class="fa-solid fa-circle-check me-2"></i>' + esc(t("btn_activate", "Activate"))
                : '<i class="fa-solid fa-trash-can me-2"></i>' + esc(t("btn_delete", "Delete")),
            cancelButtonText: '<i class="fa-solid fa-xmark me-2"></i>' + esc(t("btn_cancel", "Cancel")),
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