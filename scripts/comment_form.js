/**
 * Comment Add Form — comment_form.js
 * Char counter, modal, AJAX submit
 */
document.addEventListener('DOMContentLoaded', function () {

    /* ── Char counter ─────────────────────────────────── */
    const textarea  = document.getElementById('commentText');
    const charCount = document.getElementById('charCount');
    const maxLength = 500;

    if (textarea && charCount) {
        textarea.addEventListener('input', function () {
            const len = this.value.length;
            charCount.textContent = len + ' / ' + maxLength;
            charCount.classList.remove('warning', 'danger');
            if (len > maxLength * 0.85) charCount.classList.add('warning');
            if (len > maxLength)        charCount.classList.add('danger');
        });
    }

    /* ── Modal ────────────────────────────────────────── */
    const modalOverlay  = document.getElementById('modalOverlay');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalMessage  = document.getElementById('modalMessage');
    const modalIcon     = document.getElementById('modalIcon');

    window.showModal = function (message, type = 'info') {
        modalMessage.textContent = message;
        modalIcon.className = 'modal-icon ' + type;
        modalIcon.textContent = type === 'success' ? '✅'
                              : type === 'error'   ? '❌'
                              :                      'ℹ️';
        modalOverlay.classList.add('active');
    };

    window.hideModal = function () {
        modalOverlay.classList.remove('active');
    };

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', hideModal);
    if (modalOverlay)  modalOverlay.addEventListener('click', e => {
        if (e.target === e.currentTarget) hideModal();
    });

    /* ── AJAX submit ──────────────────────────────────── */
    const form = document.getElementById('commentForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch(this.action, {
                method: 'POST',
                body: new FormData(this),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    showModal(data.message || 'Error submitting comment.', 'error');
                } else if (data.redirect) {
                    showModal('Comment posted successfully!', 'success');
                    setTimeout(() => { window.location.href = data.redirect; }, 800);
                } else {
                    showModal('Unexpected response from server.', 'error');
                }
            })
            .catch(() => showModal('Failed to submit comment.', 'error'));
        });
    }
});
