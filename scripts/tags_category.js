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
    document.querySelectorAll('.cat-pick-btn').forEach(b => {
        b.classList.remove('active');
        b.style.borderColor = '';
    });

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
    const color = button.getAttribute('data-color');
    const tagsInput = document.getElementById('tags');
    let currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);

    // Ripple effect
    button.style.transform = 'scale(0.96)';
    setTimeout(() => {
        button.style.transform = '';
    }, 150);

    if (button.classList.contains('genre-active')) {
        // Remove tag
        currentTags = currentTags.filter(t => t !== genre);
        button.classList.remove('genre-active');
        button.style.color = color;
        button.style.background = 'white';
    } else {
        // Add tag
        if (!currentTags.includes(genre)) {
            currentTags.push(genre);
        }
        button.classList.add('genre-active');
        button.style.color = 'white';
        button.style.background = `linear-gradient(135deg, ${color} 0%, ${color}cc 100%)`;
        button.style.borderColor = 'transparent';
    }

    tagsInput.value = currentTags.join(', ');

    // Trigger change event
    tagsInput.dispatchEvent(new Event('change'));

    // Show feedback
    const added = button.classList.contains('genre-active');
    showToastFeedback(added
        ? tcT('tc_tag_added',   '{1} added to tags',     label)
        : tcT('tc_tag_removed', '{1} removed from tags', label), added);
}

function clearAllTags() {
    const tagsInput = document.getElementById('tags');
    const buttons = document.querySelectorAll('.genre-tag-btn');

    // Clear input
    tagsInput.value = '';

    // Remove active class from all buttons
    buttons.forEach(button => {
        button.classList.remove('genre-active');
        const color = button.getAttribute('data-color');
        button.style.color = color;
        button.style.background = 'white';
    });

    // Show clear feedback
    showToastFeedback(tcT('tc_tags_cleared', 'All tags cleared'), false);
}

function showToastFeedback(message, isAdd) {
    // Temporary toast; the message is inserted as text (it contains a tag/category name)
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 p-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast align-items-center text-white bg-${isAdd ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${isAdd ? 'check-circle' : 'trash-alt'} me-2"></i><span></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    toast.querySelector('.toast-body span').textContent = message;

    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast.querySelector('.toast'), { delay: 1500 });
    bsToast.show();

    setTimeout(() => {
        toast.remove();
    }, 2000);
}

// Initialize active tags on page load
document.addEventListener('DOMContentLoaded', function() {
    const tagsInput = document.getElementById('tags');
    if (tagsInput && tagsInput.value) {
        const currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);
        const buttons = document.querySelectorAll('.genre-tag-btn');

        buttons.forEach(button => {
            const genre = button.getAttribute('data-genre');
            const color = button.getAttribute('data-color');
            if (currentTags.includes(genre)) {
                button.classList.add('genre-active');
                button.style.color = 'white';
                button.style.background = `linear-gradient(135deg, ${color} 0%, ${color}cc 100%)`;
                button.style.borderColor = 'transparent';
            }
        });
    }
});