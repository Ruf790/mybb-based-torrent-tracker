// ========= CHARACTER COUNTER =========
document.addEventListener('DOMContentLoaded', function () {
    const signatureTextarea = document.getElementById('signature');
    const charCountSpan = document.getElementById('charCount');
    const charCounterDiv = document.getElementById('charCounter');

    function updateCharCount() {
        if (signatureTextarea) {
            const length = signatureTextarea.value.length;
            charCountSpan.textContent = length;

            if (length > 500) {
                charCounterDiv.classList.add('danger');
                charCounterDiv.classList.remove('warning');
            } else if (length > 300) {
                charCounterDiv.classList.add('warning');
                charCounterDiv.classList.remove('danger');
            } else {
                charCounterDiv.classList.remove('warning', 'danger');
            }
        }
    }

    if (signatureTextarea) {
        updateCharCount();
        signatureTextarea.addEventListener('input', updateCharCount);
    }
});