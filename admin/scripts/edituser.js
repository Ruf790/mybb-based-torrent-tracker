document.addEventListener('DOMContentLoaded', function() {

    
	// ── Live preview для textarea ─────────────────────────────────────────────
    document.addEventListener('input', function(e) {
        if (e.target.tagName !== 'TEXTAREA') return;
        const container = e.target.closest('.modal-body, .card-body, .bb-editor');
        const preview   = container?.querySelector('[data-bb-preview]');
        if (preview && typeof parseBBCode === 'function') {
            preview.innerHTML = parseBBCode(e.target.value);
        }
    });
	
	
	
	// ── Анимация при скролле ─────────────────────────────────────────────────
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.form-floating, .stat-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        observer.observe(el);
    });

    // ── Валидация формы ──────────────────────────────────────────────────────
    const form = document.getElementById('userEditForm');
    if (form) {
        form.addEventListener('input', function(e) {
            const input = e.target;
            if (input.checkValidity()) {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
            } else {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
            }
        });
    }

    // ── Tab persistence ──────────────────────────────────────────────────────
    const activeTab = sessionStorage.getItem('activeUserTab');
    if (activeTab) {
        const tab = document.querySelector(`[data-bs-target="${activeTab}"]`);
        if (tab) new bootstrap.Tab(tab).show();
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            sessionStorage.setItem('activeUserTab', e.target.getAttribute('data-bs-target'));
        });
    });

});

// ── Test avatar ──────────────────────────────────────────────────────────────
function testAvatar() {
    const avatarInput = document.querySelector('input[name="avatar"]');
    if (!avatarInput) { showToast('Avatar field not found!', 'error'); return; }

    let avatarUrl = avatarInput.value.trim();
    if (!avatarUrl) { showToast('Please enter an avatar URL first.', 'warning'); return; }

    // Нормализация: если это не полный URL (http/https) и нет ведущего слэша —
    // добавляем его, иначе браузер резолвит путь относительно текущей папки
    // (/admin/), а не относительно корня сайта.
    if (!/^https?:\/\//i.test(avatarUrl) && !avatarUrl.startsWith('/')) {
        avatarUrl = '/' + avatarUrl;
    }

    const button = document.querySelector('button[onclick="testAvatar()"]');
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing...';

    const img = new Image();
    const timeout = setTimeout(() => {
        img.onload = img.onerror = null;
        button.disabled = false;
        button.innerHTML = originalHtml;
        showToast('Avatar test timed out.', 'error');
    }, 10000);

    img.onload = function() {
        clearTimeout(timeout);
        button.disabled = false;
        button.innerHTML = originalHtml;
        showToast('✓ Avatar valid! Dimensions: ' + img.naturalWidth + '×' + img.naturalHeight + 'px', 'success');
    };

    img.onerror = function() {
        clearTimeout(timeout);
        button.disabled = false;
        button.innerHTML = originalHtml;
        showToast('✗ Avatar URL invalid or image cannot be loaded. Check: URL is correct, image is accessible, supported format (JPG, PNG, GIF, WebP).', 'error');
    };

    img.src = avatarUrl + (avatarUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
}

// ── Security action ──────────────────────────────────────────────────────────
function securityAction(action, uid, postKey, script) {
    if (!confirm('Are you sure?')) return;
    const data = new FormData();
    data.append('action', 'updateuser');
    data.append('userid', uid);
    data.append('my_post_key', postKey);
    data.append(action, '1');
    fetch(script, { method: 'POST', body: data })
        .then(() => location.reload())
        .catch(e => alert('Error: ' + e));
}

// ── Send PM ──────────────────────────────────────────────────────────────────
function sendPMajax(uid, postKey, script) {
    const subject = document.getElementById('pmSubject')?.value.trim();
    const message = document.getElementById('pmMessage')?.value.trim();
    if (!subject || !message) { alert('Subject and message are required.'); return; }

    const data = new FormData();
    data.append('action', 'updateuser');
    data.append('userid', uid);
    data.append('my_post_key', postKey);
    data.append('send_pm', '1');
    data.append('pm_subject', subject);
    data.append('pm_message', message);

    fetch(script, { method: 'POST', body: data }).then(() => {
        bootstrap.Modal.getInstance(document.getElementById('sendPMModal'))?.hide();
        alert('PM sent successfully!');
    });
}