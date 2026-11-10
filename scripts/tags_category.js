/* ── i18n ─────────────────────────────────────────────────────────────────
   Strings come from AGS_LANG (js_tc_* keys in the page's lang file);
   missing -> English fallback. {1} and %1$s are both substituted. */
function tcT(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
    const str  = (dict && typeof dict[key] === 'string') ? dict[key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a || b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// ===== Category picker =====
function selectCategory(btn) {
    document.querySelectorAll('.cat-pick-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('categoryPicker').classList.remove('is-invalid');

    btn.classList.add('active');
    document.getElementById('categorySelected').value = btn.dataset.id;
    document.getElementById('categoryError').style.display = 'none';
    // Built from nodes: the category name is inserted as text, not HTML
    const label  = document.getElementById('categoryLabel');
    const icon   = document.createElement('i');
    const strong = document.createElement('strong');
    icon.className     = 'fas fa-check-circle text-success me-1';
    strong.textContent = btn.dataset.name || '';
    label.textContent  = '';
    label.append(icon, tcT('tc_selected', 'Selected:') + ' ', strong);
}

// ===== Genre tags =====
function toggleGenreTag(button) {
    const genre = button.getAttribute('data-genre');   // English value — goes into the tags field
    const label = button.getAttribute('data-label') || genre;   // translated name — only for messages
    const tagsInput = document.getElementById('tags');
    let currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);

    const added = !button.classList.contains('genre-active');
    if (added) {
        if (!currentTags.includes(genre)) {
            currentTags.push(genre);
        }
    } else {
        currentTags = currentTags.filter(t => t !== genre);
    }
    button.classList.toggle('genre-active', added);

    tagsInput.value = currentTags.join(', ');
    tagsInput.dispatchEvent(new Event('change'));

    showTagFeedback(added
        ? tcT('tc_tag_added',   '{1} added to tags',     label)
        : tcT('tc_tag_removed', '{1} removed from tags', label), added);
}

function clearAllTags() {
    document.getElementById('tags').value = '';
    document.querySelectorAll('.genre-tag-btn').forEach(button => button.classList.remove('genre-active'));
    showTagFeedback(tcT('tc_tags_cleared', 'All tags cleared'), false);
}

// showToast() takes HTML, the message contains a genre name: escape it
function showTagFeedback(message, isAdd) {
    if (typeof showToast === 'function') {
        showToast(escapeHtml(message), isAdd ? 'success' : 'info');
    }
}

// Typing in the tags field keeps the genre buttons in sync
document.addEventListener('DOMContentLoaded', function () {
    const tagsInput = document.getElementById('tags');
    if (!tagsInput) return;

    const sync = () => {
        const currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);
        document.querySelectorAll('.genre-tag-btn').forEach(button => {
            button.classList.toggle('genre-active', currentTags.includes(button.getAttribute('data-genre')));
        });
    };

    tagsInput.addEventListener('input', sync);
    sync();
});
