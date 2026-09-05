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
        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected (' + count + ')';
    }
}

function toggleMergeButton() {
    const count = document.querySelectorAll('.comment-checkbox:checked').length;
    const btn = document.getElementById('mergeCommentsButton');
    if (btn) {
        // Merge имеет смысл только от 2 выбранных комментариев
        btn.classList.toggle('d-none', count < 2);
        btn.innerHTML = '<i class="fa-solid fa-code-merge"></i> Merge Selected (' + count + ')';
    }
}

function mergeComments() {
    const checked = [...document.querySelectorAll('.comment-checkbox:checked')].map(cb => cb.value);
    if (checked.length < 2) {
        return;
    }
    if (!confirm('Merge ' + checked.length + ' selected comments into one? This cannot be undone.')) {
        return;
    }

    const btn = document.getElementById('mergeCommentsButton');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Merging...';
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
                alert('Error: ' + (data.error || 'Unknown error'));
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
            alert('Merge failed. Please try again.');
            if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
        });
}

function quote(textarea, form, quote) {
    var area = document.forms[form].elements[textarea];
    area.value = area.value + " " + quote + " ";
    area.focus();
}
