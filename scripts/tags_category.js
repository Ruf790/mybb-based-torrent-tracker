// ===== Category picker =====
function selectCategory(btn) {
    document.querySelectorAll('.cat-pick-btn').forEach(b => {
        b.classList.remove('active');
        b.style.borderColor = '';
    });

    btn.classList.add('active');
    document.getElementById('categorySelected').value = btn.dataset.id;
    document.getElementById('categoryError').style.display = 'none';
    document.getElementById('categoryLabel').innerHTML =
        '<i class="fas fa-check-circle text-success me-1"></i>' +
        'Selected: <strong>' + btn.dataset.name + '</strong>';
}

// ===== Genre tags =====
function toggleGenreTag(button) {
    const genre = button.getAttribute('data-genre');
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
    showToastFeedback(genre, button.classList.contains('genre-active'));
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
    showToastFeedback('All tags cleared', false);
}

function showToastFeedback(message, isAdd) {
    // Create temporary toast notification
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 p-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast align-items-center text-white bg-${isAdd ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${isAdd ? 'check-circle' : 'trash-alt'} me-2"></i>
                    ${message} ${isAdd ? 'added to' : 'removed from'} tags
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

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
