// Form validation
(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Helper functions
function setPoints(points) {
    const inputs = document.querySelectorAll('input[name="seedbonus"]');
    inputs.forEach(input => input.value = points);
    return false;
}

function confirmBulkDistribution() {
    const points = document.querySelector('input[name="seedbonus"]').value;
    const groupSelect = document.querySelector('select[name="usergroup"]');
    const groupName = groupSelect.options[groupSelect.selectedIndex].text;

    if (!points || points < 1) {
        alert('Please enter a valid points amount first.');
        return;
    }

    const confirmation = confirm(
        `⚠️ BULK DISTRIBUTION CONFIRMATION\n\n` +
        `You are about to distribute ${points} bonus points\n` +
        `to ALL users in: ${groupName}\n\n` +
        `This action cannot be undone.\n\n` +
        `Click OK to proceed, or Cancel to review.`
    );

    if (confirmation) {
        document.querySelector('form').submit();
    }
}

// Auto-select current tab based on previous selection
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('bonusTab');
    if (savedTab) {
        const tab = new bootstrap.Tab(document.querySelector(savedTab));
        tab.show();
    }

    // Save tab selection
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', e => {
            localStorage.setItem('bonusTab', e.target.getAttribute('data-bs-target'));
        });
    });
});
