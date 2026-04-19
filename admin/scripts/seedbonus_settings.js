const presets = window.seedbonusPresets || {};

document.addEventListener('DOMContentLoaded', function () {
    initSliders();
    initEventListeners();
    updateSliderValues();
});

function initSliders() {
    document.querySelectorAll('input[type="range"]').forEach(slider => {
        const valueSpan = document.getElementById(slider.id + 'Value');
        if (valueSpan) {
            slider.addEventListener('input', function () {
                valueSpan.textContent = this.value;
            });
        }
    });
}

function updateSliderValues() {
    document.querySelectorAll('input[type="range"]').forEach(slider => {
        const valueSpan = document.getElementById(slider.id + 'Value');
        if (valueSpan) valueSpan.textContent = slider.value;
    });
}

function initEventListeners() {
    // Presets
    document.querySelectorAll('.config-badge').forEach(badge => {
        badge.addEventListener('click', function (e) {
            e.preventDefault();
            loadPreset(this.dataset.preset);
        });
    });

    // Save
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) saveBtn.addEventListener('click', e => { e.preventDefault(); saveSettings(); });

    // Reset
    const resetBtn = document.getElementById('resetBtn');
    if (resetBtn) resetBtn.addEventListener('click', e => { e.preventDefault(); resetSettings(); });

    // Copy code
    const copyBtn = document.getElementById('copyCodeBtn');
    if (copyBtn) copyBtn.addEventListener('click', copyCode);

    // Download code
    const dlBtn = document.getElementById('downloadCodeBtn');
    if (dlBtn) dlBtn.addEventListener('click', downloadCode);

    // Inflation users
    const inflationInput = document.getElementById('inflationUsers');
    if (inflationInput) inflationInput.addEventListener('input', updateInflation);
}

function loadPreset(presetName) {
    if (!presets[presetName]) {
        showToast(`Preset "${presetName}" not found`, 'error');
        return;
    }
    if (!confirm(`Load "${presetName}" preset? Current settings will be overwritten.`)) return;

    const formData = new FormData();
    formData.append('action', 'load_preset');
    formData.append('preset', presetName);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1000);
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
}

function saveSettings() {
    const formData = new FormData();
    ['basicForm', 'multipliersForm', 'timeForm'].forEach(id => {
        const form = document.getElementById(id);
        if (form) new FormData(form).forEach((v, k) => formData.append(k, v));
    });
    formData.append('action', 'save');

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => showToast(data.message, data.success ? 'success' : 'error'))
        .catch(err => showToast('Error: ' + err.message, 'error'));
}

function resetSettings() {
    if (confirm('Reset all settings to default values?')) loadPreset('balanced');
}

function copyCode() {
    const el = document.getElementById('generatedCode');
    if (!el) return;
    navigator.clipboard.writeText(el.textContent)
        .then(() => showToast('Code copied to clipboard', 'success'))
        .catch(() => showToast('Failed to copy code', 'error'));
}

function downloadCode() {
    const el = document.getElementById('generatedCode');
    if (!el) return;
    const a = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(new Blob([el.textContent], { type: 'text/php' })),
        download: 'seedbonus_config.php'
    });
    document.body.appendChild(a);
    a.click();
    a.remove();
    showToast('File downloaded', 'success');
}

function updateInflation() {
    const users    = parseInt(document.getElementById('inflationUsers')?.value) || 100;
    const daily    = parseInt(document.getElementById('previewDaily')?.textContent.replace(/,/g, '')) || 0;
    const total    = users * daily;
    const monthly  = total * 30;
    const avgBal   = monthly / 500;

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = Math.round(val).toLocaleString(); };
    set('inflationDaily',      total);
    set('inflationMonthly',    monthly);
    set('inflationAvgBalance', avgBal);
}
