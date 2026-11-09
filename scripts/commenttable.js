/* ── i18n ─────────────────────────────────────────────────────────────────
   AGS_LANG is printed by the page (details.php) before the scripts.
   Missing dictionary/key -> English fallback. {1} and %1$s are both substituted
   ($lang->load() turns {1} into %1$s). Insert results as text, not HTML. */
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
    const str  = (dict && typeof dict[key] === 'string') ? dict[key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a || b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// Replaces the element content with <tag class="iconClass"></tag> + text node.
function agsIconText(el, iconClass, text, tag) {
    el.textContent = '';
    const icon = document.createElement(tag || 'i');
    icon.className = iconClass;
    el.appendChild(icon);
    el.appendChild(document.createTextNode(text));
}

// HTML-escape for the few places where a translation has to go into an HTML template.
function agsEsc(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

function toggleCommentSelect(checkbox) {
    const wrapper = document.getElementById('comment-' + checkbox.value);
    if (wrapper) {
        wrapper.classList.toggle('comment-selected', checkbox.checked);
    }
    toggleMassDeleteButton();
    toggleMergeButton();
}

function toggleSelectAll(masterSwitch) {
    document.querySelectorAll('.comment-checkbox').forEach(cb => {
        cb.checked = masterSwitch.checked;
        const wrapper = document.getElementById('comment-' + cb.value);
        if (wrapper) {
            wrapper.classList.toggle('comment-selected', masterSwitch.checked);
        }
    });
    toggleMassDeleteButton();
    toggleMergeButton();
}

function toggleMassDeleteButton() {
    const count = document.querySelectorAll('.comment-checkbox:checked').length;
    const btn = document.getElementById('massDeleteButton');
    if (btn) {
        btn.classList.toggle('d-none', count === 0);
        agsIconText(btn, 'fa-solid fa-trash', ' ' + t('cm_delete_selected', 'Delete Selected ({1})', count));
    }
}

function toggleMergeButton() {
    const count = document.querySelectorAll('.comment-checkbox:checked').length;
    const btn = document.getElementById('mergeCommentsButton');
    if (btn) {
        // Merge имеет смысл только от 2 выбранных комментариев
        btn.classList.toggle('d-none', count < 2);
        agsIconText(btn, 'fa-solid fa-code-merge', ' ' + t('ct_merge_selected', 'Merge Selected ({1})', count));
    }
}

function mergeComments() {
    const checked = [...document.querySelectorAll('.comment-checkbox:checked')].map(cb => cb.value);
    if (checked.length < 2) {
        return;
    }
    if (!confirm(t('ct_merge_confirm', 'Merge {1} selected comments into one? This cannot be undone.', checked.length))) {
        return;
    }

    const btn = document.getElementById('mergeCommentsButton');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        agsIconText(btn, 'spinner-border spinner-border-sm me-1', t('ct_merging', 'Merging...'), 'span');
    }

    fetch('comment.php?action=merge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            comment_ids: checked.join(','),
            my_post_key: window.CS_POST_CODE || ''
        })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(t('cm_error', 'Error: {1}', data.error || t('ct_unknown_error', 'Unknown error')));
                if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                return;
            }

            // Убираем поглощённые комментарии из DOM
            (data.removed_ids || []).forEach(id => {
                const el = document.getElementById('comment-' + id);
                if (el) el.remove();
            });

            // Заменяем мастер-комментарий на обновлённый HTML
            const masterEl = document.getElementById('comment-' + data.master_id);
            if (masterEl && data.html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = data.html;
                masterEl.replaceWith(...tmp.childNodes);
            }

            toggleMassDeleteButton();
            toggleMergeButton();
        })
        .catch(() => {
            alert(t('ct_merge_failed', 'Merge failed. Please try again.'));
            if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
        });
}

function quote(textarea, form, quote) {
    var area = document.forms[form].elements[textarea];
    area.value = area.value + " " + quote + " ";
    area.focus();
}
