/* Email Logs (admin/logmails.php) */
(function () {
    "use strict";

    // Translation helper: AGS_LANG[key] or English fallback, {1}/%1$s substitution
    function t(key, fallback, ...args) {
        let s = (typeof AGS_LANG === "object" && AGS_LANG && typeof AGS_LANG[key] === "string")
            ? AGS_LANG[key]
            : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split("{" + n + "}").join(String(a)).split("%" + n + "$s").join(String(a));
        });
        return s;
    }

    function el(tag, className) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        return node;
    }

    function iconLabel(target, iconClass, text) {
        target.replaceChildren(el("i", iconClass), document.createTextNode(text));
    }

    // card border-light > card-body > h6 (icon + label) + value node
    function detailCard(iconClass, label, valueNode) {
        const card = el("div", "card border-light");
        const body = el("div", "card-body");
        const h6 = el("h6", "card-subtitle mb-2 text-muted");
        iconLabel(h6, iconClass, label);
        body.append(h6, valueNode);
        card.append(body);
        return card;
    }

    function textP(text) {
        const p = el("p", "card-text");
        p.textContent = text;
        return p;
    }

    function row(rowClass, cols) {
        const r = el("div", rowClass);
        cols.forEach(([colClass, node]) => {
            const c = el("div", colClass);
            c.append(node);
            r.append(c);
        });
        return r;
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Select all checkboxes
        const selectAll = document.getElementById("selectAll");
        const selectAllBtn = document.getElementById("selectAllBtn");

        function toggleAllCheckboxes(checked) {
            document.querySelectorAll(".mail-checkbox").forEach(cb => {
                cb.checked = checked;
            });
            updateSelectedCount();
        }

        if (selectAll) {
            selectAll.addEventListener("change", function () {
                toggleAllCheckboxes(this.checked);
            });
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener("click", function () {
                const allChecked = Array.from(document.querySelectorAll(".mail-checkbox"))
                    .every(cb => cb.checked);
                toggleAllCheckboxes(!allChecked);
                if (selectAll) {
                    selectAll.checked = !allChecked;
                }
            });
        }

        // Count selected
        function updateSelectedCount() {
            const selected = document.querySelectorAll(".mail-checkbox:checked").length;
            const selectedCount = document.getElementById("selectedCount");
            const deleteBtn = document.getElementById("deleteSelectedBtn");

            if (selectedCount) {
                selectedCount.textContent = t("selected", "Selected: {1}", selected);
            }

            if (deleteBtn) {
                deleteBtn.disabled = selected === 0;
            }
        }

        document.querySelectorAll(".mail-checkbox").forEach(cb => {
            cb.addEventListener("change", updateSelectedCount);
        });

        // Expand/collapse messages
        document.querySelectorAll(".show-more-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const content = this.closest(".mail-preview").querySelector(".mail-content");
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                    iconLabel(this, "fas fa-chevron-up me-1", t("collapse", "Collapse"));
                } else {
                    content.style.maxHeight = "100px";
                    iconLabel(this, "fas fa-chevron-down me-1", t("show_more", "Show More"));
                }
            });
        });

        // Delete single entry
        document.querySelectorAll(".delete-single").forEach(btn => {
            btn.addEventListener("click", function () {
                if (confirm(t("confirm_delete_one", "Delete this entry?"))) {
                    const form = document.getElementById("mailLogsForm");
                    const checkbox = document.createElement("input");
                    checkbox.type = "hidden";
                    checkbox.name = "logid[]";
                    checkbox.value = this.dataset.id;
                    form.appendChild(checkbox);
                    form.submit();
                }
            });
        });

        // View email details
        document.querySelectorAll(".view-mail-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const modal = new bootstrap.Modal(document.getElementById("mailDetailsModal"));
                const content = document.getElementById("mailDetailsContent");

                // Message body is parser output (HTML) — rendered as before
                const messageBox = el("div", "mail-content-full p-3 bg-light rounded");
                messageBox.innerHTML = this.dataset.message || "";

                // my_datee('relative') may return markup (<span title=…>) — rendered as before
                const dateP = el("p", "card-text");
                dateP.innerHTML = this.dataset.date || "";

                content.replaceChildren(
                    row("row mb-4", [
                        ["col-md-6", detailCard("fas fa-paper-plane me-2", t("sender", "Sender"), textP(this.dataset.from || ""))],
                        ["col-md-6", detailCard("fas fa-inbox me-2", t("recipient", "Recipient"), textP(this.dataset.to || ""))]
                    ]),
                    row("row mb-4", [
                        ["col-12", detailCard("fas fa-calendar-alt me-2", t("send_date", "Send Date"), dateP)]
                    ]),
                    row("row", [
                        ["col-12", detailCard("fas fa-envelope me-2", t("message_content", "Message Content"), messageBox)]
                    ])
                );

                modal.show();
            });
        });

        updateSelectedCount();
    });
})();
