(function () {
    'use strict';

    // ── i18n ───────────────────────────────────────────────────────────────
    // AGS_LANG is printed by member.php before this script (keys without the js_ prefix).
    // $lang->load() turns {1} into %1$s, so both forms are substituted.
    const AGS_T = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};

    function t(key, fallback, ...args) {
        let s = (typeof AGS_T[key] === 'string' && AGS_T[key] !== '') ? AGS_T[key] : fallback;
        args.forEach((arg, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(arg)).split('%' + n + '$s').join(String(arg));
        });
        return s;
    }

    const DESC_MIN = 10;
    const DESC_MAX = 2000;

    function faIcon(cls) {
        const i = document.createElement('i');
        i.className = cls;
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    // showToast() (toast.js) takes HTML — translated text always goes in escaped.
    function toastText(iconCls, text, type) {
        showToast((iconCls ? '<i class="' + iconCls + '"></i>' : '') + escapeHtml(text), type);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const reportForm = document.getElementById('reportUserForm');
        if (!reportForm) {
            return;
        }
        const submitBtn = reportForm.querySelector('button[type="submit"]');

        const userReportCaptchaImg = document.getElementById('userReportCaptchaDisplay');
        if (userReportCaptchaImg) {
            userReportCaptchaImg.addEventListener('click', refreshUserReportCaptcha);
        }

        const userReportCaptchaRefreshBtn = document.getElementById('userReportRefreshCaptcha');
        if (userReportCaptchaRefreshBtn) {
            userReportCaptchaRefreshBtn.addEventListener('click', refreshUserReportCaptcha);
        }

        reportForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Валидация перед отправкой
            if (!validateReportForm()) {
                return;
            }

            // Показать индикатор загрузки
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.replaceChildren(
                faIcon('fa-solid fa-spinner fa-spin me-1'),
                document.createTextNode(' ' + t('report_submitting', 'Submitting...'))
            );
            submitBtn.disabled = true;

            // AJAX отправка
            const formData = new FormData(reportForm);

            fetch(reportForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    // internal marker, never shown to the user (checked in .catch below)
                    throw new Error(`Invalid JSON response from server (status ${response.status})`);
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    // Успешно - закрыть модальное окно и показать сообщение
                    const modal = bootstrap.Modal.getInstance(document.getElementById('reportUserModal'));
                    modal.hide();

                    toastText(
                        'fa-solid fa-check-circle me-2',
                        data.message || t('report_success', 'Report submitted successfully'),
                        'success'
                    );

                    // Очистить форму
                    reportForm.reset();
                    resetCharCounter();

                    // Обновить счетчик символов
                    updateCharCounter();

                    // Перенаправить если указано в ответе
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                } else {
                    // Ошибка от сервера
                    let errorMessage = escapeHtml(t('report_failed', 'Failed to submit report'));

                    if (data.error) {
                        errorMessage = escapeHtml(data.error);
                    } else if (data.errors && data.errors.length > 0) {
                        errorMessage = data.errors.map(escapeHtml).join('<br>');
                    }

                    showToast(
                        '<i class="fa-solid fa-exclamation-circle me-2"></i>' + errorMessage,
                        'error'
                    );

                    // Код капчи одноразовый и сервер уже аннулировал его при этой
                    // попытке (вне зависимости от результата) - показываем новую картинку.
                    refreshUserReportCaptcha();
                    const captchaFieldOnError = document.getElementById('userReportCaptchaInput');
                    if (captchaFieldOnError) captchaFieldOnError.value = '';

                    // Выделить поля с ошибками (сервер отдаёт имена полей в data.fields)
                    highlightErrors(data.fields);
                }
            })
            .catch(error => {
                console.error('Report submission error:', error);

                let errorMessage = t('report_network_error', 'Network error, please try again');

                // Проверяем если это JSON ошибка
                if (error.message.includes('JSON')) {
                    errorMessage = t('report_bad_response', 'Invalid server response. Please try again.');
                }

                toastText('fa-solid fa-times-circle me-2', errorMessage, 'error');

                refreshUserReportCaptcha();
                const captchaFieldOnCatch = document.getElementById('userReportCaptchaInput');
                if (captchaFieldOnCatch) captchaFieldOnCatch.value = '';
            })
            .finally(() => {
                // Восстановить кнопку
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            });
        });

        // Валидация формы
        function validateReportForm() {
            const reasonSelected = reportForm.querySelector('input[name="reason"]:checked');
            const description = reportForm.querySelector('#reportDescription').value.trim();

            // Проверка причины
            if (!reasonSelected) {
                toastText('', t('report_need_reason', 'Please select a report reason'), 'warning');
                highlightField('reason');
                return false;
            }

            // Проверка описания
            if (description.length < DESC_MIN) {
                toastText('', t('report_desc_short', 'Please provide a detailed description (minimum {1} characters)', DESC_MIN), 'warning');
                highlightField('description');
                return false;
            }

            if (description.length > DESC_MAX) {
                toastText('', t('report_desc_long', 'Description is too long (maximum {1} characters)', DESC_MAX), 'warning');
                highlightField('description');
                return false;
            }

            // Проверка капчи (сам код сверяется на сервере)
            const captchaField = document.getElementById('userReportCaptchaInput');
            if (captchaField && !captchaField.value.trim()) {
                toastText('', t('report_need_captcha', 'Please enter the security code.'), 'warning');
                captchaField.focus();
                return false;
            }

            return true;
        }

        // Выделение поля с ошибкой
        function highlightField(fieldName) {
            const field = document.querySelector(`[name="${fieldName}"]`) ||
                          document.querySelector(`#${fieldName}`);

            if (field) {
                field.classList.add('is-invalid');
                field.focus();

                // Убрать класс через 3 секунды
                setTimeout(() => {
                    field.classList.remove('is-invalid');
                }, 3000);
            }
        }

        // Подсветка полей с ошибками. Раньше искали слова в тексте ошибки —
        // после перевода это не работает, поэтому сервер присылает имена полей.
        const SERVER_FIELD_TO_INPUT = {
            reason:      'reason',
            description: 'description',
            captcha:     'captcha_response'
        };

        function highlightErrors(fields) {
            if (!Array.isArray(fields)) return;

            fields.forEach(field => {
                const name = SERVER_FIELD_TO_INPUT[field];
                if (name) highlightField(name);
            });
        }

        // Счетчик символов для описания
        const descriptionField = document.getElementById('reportDescription');
        const charCount = document.createElement('div');
        charCount.className = 'form-text text-end';
        charCount.id = 'charCount';
        charCount.textContent = t('report_char_count', '{1}/{2} characters', 0, DESC_MAX);
        descriptionField.parentNode.appendChild(charCount);

        function updateCharCounter() {
            const length = descriptionField.value.length;
            charCount.textContent = t('report_char_count', '{1}/{2} characters', length, DESC_MAX);

            if (length < DESC_MIN) {
                charCount.className = 'form-text text-end text-danger';
            } else if (length < 100) {
                charCount.className = 'form-text text-end text-warning';
            } else if (length > DESC_MAX - 100) {
                charCount.className = 'form-text text-end text-danger';
            } else {
                charCount.className = 'form-text text-end text-success';
            }
        }

        function resetCharCounter() {
            charCount.textContent = t('report_char_count', '{1}/{2} characters', 0, DESC_MAX);
            charCount.className = 'form-text text-end text-danger';
        }

        descriptionField.addEventListener('input', updateCharCounter);
        updateCharCounter(); // Инициализация

        // Автозаполнение описания при выборе причины
        const reasonRadios = document.querySelectorAll('input[name="reason"]');
        reasonRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                const descriptionField = document.getElementById('reportDescription');
                if (descriptionField.value.trim() === '') {
                    // the radio's <label> is its next sibling (.btn-check pattern)
                    const label      = this.parentNode.querySelector('label[for="' + this.id + '"]') || this.parentNode;
                    const reasonText = (label.querySelector('.fw-bold') || {}).textContent || '';
                    const reasonDesc = (label.querySelector('.text-muted') || {}).textContent || '';
                    const titleEl    = document.getElementById('reportUserModalLabel');
                    const reported   = (titleEl && titleEl.dataset.username) || '';

                    descriptionField.value =
                        t('report_tpl_intro', 'I am reporting this user for: {1}', reasonText) + '\n\n' +
                        t('report_tpl_reason', 'Reason: {1}', reasonDesc) + '\n\n' +
                        t('report_tpl_details', 'Additional details:') + '\n' +
                        t('report_tpl_datetime', '- Date/Time: {1}', new Date().toLocaleString(document.documentElement.lang || undefined)) + '\n' +
                        t('report_tpl_user', '- Reported User: {1}', reported) + '\n' +
                        t('report_tpl_evidence', '- Evidence:') + '\n';

                    descriptionField.dispatchEvent(new Event('input'));
                    descriptionField.focus();
                }
            });
        });

        // Очистка формы при закрытии модального окна
        const reportModal = document.getElementById('reportUserModal');
        if (reportModal) {
            reportModal.addEventListener('hidden.bs.modal', function () {
                reportForm.reset();
                resetCharCounter();

                // Сбросить выбор причины
                const selectedReason = reportForm.querySelector('input[name="reason"]:checked');
                if (selectedReason) {
                    selectedReason.checked = false;
                }

                // Убрать классы ошибок
                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
            });
        }
    });

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function (ch) {
            switch (ch) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
            }
        });
    }

    // Обновляет картинку с кодом капчи (код проверяется на сервере, report_captcha.php)
    function refreshUserReportCaptcha() {
        const img = document.getElementById('userReportCaptchaDisplay');
        if (img) {
            img.src = 'report_captcha.php?t=' + Date.now();
        }
    }

    // Функция для открытия модального окна (вызывается из onclick в member.php)
    function openReportUserModal(userId, username) {
        const modal = new bootstrap.Modal(document.getElementById('reportUserModal'));

        // Обновить данные в форме
        document.querySelector('input[name="reported_id"]').value = userId;
        document.querySelector('input[name="reported_user_id"]').value = userId;

        // Обновить заголовок
        const modalTitle = document.getElementById('reportUserModalLabel');
        if (modalTitle) {
            modalTitle.dataset.username = String(username ?? '');
            modalTitle.replaceChildren(
                faIcon('fa-solid fa-flag me-2'),
                document.createTextNode(t('report_title', 'Report User: {1}', modalTitle.dataset.username))
            );
        }

        // Показать модальное окно
        modal.show();
        refreshUserReportCaptcha();

        // Сфокусироваться на первом поле после открытия
        setTimeout(() => {
            const firstReason = document.querySelector('input[name="reason"]');
            if (firstReason) {
                firstReason.focus();
            }
        }, 500);
    }

    // These were globals before the IIFE wrap — keep them reachable for inline handlers / other scripts.
    window.openReportUserModal     = openReportUserModal;
    window.refreshUserReportCaptcha = refreshUserReportCaptcha;
    if (typeof window.escapeHtml !== 'function') {
        window.escapeHtml = escapeHtml;
    }
})();
