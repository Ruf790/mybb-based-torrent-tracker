const form = document.getElementById('massInviteForm');
const previewBox = document.getElementById('previewBox');
const previewCount = document.getElementById('previewCount');
const estimatedChange = document.getElementById('estimatedChange');
const previewBtn = document.getElementById('previewBtn');


// Функция обновления предварительного просмотра
function updatePreview() {
    const amount = document.getElementById('amount').value;
    const typeElement = document.querySelector('input[name="type"]:checked');

    if (!amount || !typeElement) return;

    const data = new FormData(form);
    data.append('preview', 'yes');

    // window.massInviteScript - PHP-значение ($this->currentScript),
    // задаётся отдельным маленьким инлайн-скриптом в самом massinvite.php.
    fetch(window.massInviteScript, {
        method: 'POST',
        body: data
    })
    .then(res => res.json())
    .then(json => {
        const count = json.count;
        const type = typeElement.value;

        previewCount.textContent = count;
        previewBox.classList.remove('d-none');

        // Обновляем расчетное изменение
        const change = type === '+' ? `+${amount}` : `-${amount}`;
        estimatedChange.textContent = `${change} invites`;
        estimatedChange.className = `mb-0 fw-bold ${type === '+' ? 'text-success' : 'text-danger'}`;

        // Обновляем стиль в зависимости от количества пользователей
        if (count > 0) {
            previewBox.className = 'alert alert-info border-0 shadow-sm animated-preview';
        } else {
            previewBox.className = 'alert alert-warning border-0 shadow-sm';
            estimatedChange.textContent = 'No changes';
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Обработчики событий
form.querySelectorAll('input, select').forEach(el => {
    el.addEventListener('change', updatePreview);
    el.addEventListener('input', updatePreview);
});

// Кнопка обновления предпросмотра
previewBtn.addEventListener('click', updatePreview);

// Обработчик отправки формы
form.addEventListener('submit', function(e) {
    e.preventDefault();
    const amount = document.getElementById('amount').value;
    const typeElement = document.querySelector('input[name="type"]:checked');
    const affected = previewCount.textContent;

    if (!typeElement || amount < 1) {
        Swal.fire({
            title: 'Validation Error',
            text: 'Please fill in all fields correctly.',
            icon: 'error',
            confirmButtonColor: '#764ba2'
        });
        return;
    }

    const type = typeElement.value;
    const actionText = type === '+' ? 'ADD' : 'REMOVE';
    const iconType = type === '+' ? 'success' : 'warning';

    Swal.fire({
        title: '🚀 Confirm Bulk Operation',
        html: `
            <div class="text-center">
                <div class="mb-4">
                    <div class="display-6 mb-2 ${type === '+' ? 'text-success' : 'text-danger'}">
                        ${type === '+' ? '+' : '-'}${amount}
                    </div>
                    <p class="text-muted">invites per user</p>
                </div>

                <div class="alert alert-${type === '+' ? 'success' : 'danger'} border-0 mb-3" style="background: ${type === '+' ? 'rgba(25, 135, 84, 0.1)' : 'rgba(220, 53, 69, 0.1)'}">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${type === '+' ? 'plus' : 'dash'}-circle-fill fs-3 me-3 ${type === '+' ? 'text-success' : 'text-danger'}"></i>
                        <div>
                            <h6 class="mb-0">${actionText} Operation</h6>
                            <small class="text-muted">${affected} users will be affected</small>
                        </div>
                    </div>
                </div>

                <div class="text-start mt-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <span>Negative values are prevented</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-clock-history text-info me-2"></i>
                        <span>Operation will be logged</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                        <span>This action is irreversible</span>
                    </div>
                </div>
            </div>
        `,
        icon: iconType,
        showCancelButton: true,
        confirmButtonText: type === '+' ? `Add ${amount} Invites` : `Remove ${amount} Invites`,
        cancelButtonText: 'Cancel',
        confirmButtonColor: type === '+' ? '#198754' : '#dc3545',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        width: 500,
        customClass: {
            confirmButton: `btn btn-${type === '+' ? 'success' : 'danger'} px-4 py-2`,
            cancelButton: 'btn btn-secondary px-4 py-2',
            popup: 'rounded-3'
        }
    }).then(result => {
        if (result.isConfirmed) {
            // Показываем красивый индикатор загрузки
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;

            Swal.fire({
                title: 'Processing...',
                html: `
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">Updating invites for ${affected} users</p>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Отправляем форму с небольшой задержкой для красоты
            setTimeout(() => {
                form.submit();
            }, 1500);
        }
    });
});

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();


    // Добавляем дополнительные стили
    const style = document.createElement('style');
    style.textContent = `
        .btn-check:checked + .btn-outline-success {
            background: linear-gradient(135deg, #20c997 0%, #198754 100%);
            color: white !important;
            border-color: #198754;
        }

        .btn-check:checked + .btn-outline-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #dc3545 100%);
            color: white !important;
            border-color: #dc3545;
        }

        .btn-group .btn {
            border-radius: 8px !important;
            margin: 0 2px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        #previewBox {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-control:focus {
            animation: pulseShadow 0.5s ease-out;
        }

        @keyframes pulseShadow {
            0% { box-shadow: 0 0 0 0 rgba(118, 75, 162, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(118, 75, 162, 0); }
            100% { box-shadow: 0 0 0 0 rgba(118, 75, 162, 0); }
        }
    `;
    document.head.appendChild(style);
});

// Добавляем звуковой эффект при наведении на кнопки (опционально)
document.querySelectorAll('#type-buttons .btn').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
        if (typeof Audio !== 'undefined') {
            const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ');
            audio.volume = 0.1;
            audio.play().catch(() => {});
        }
    });
});
