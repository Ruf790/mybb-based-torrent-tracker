document.addEventListener('DOMContentLoaded', function() {
    const registrationForm = document.getElementById('registration_form');
    if (!registrationForm) {
        console.error('Форма регистрации не найдена!');
        return;
    }

    const errorMessages = {
        required: lang.js_validator_not_empty,
        username: {
            required: lang.js_validator_no_username,
            length: lang.js_validator_username_length,
            taken: 'Username is already taken'
        },
        email: {
            invalid: lang.js_validator_invalid_email,
            match: lang.js_validator_email_match,
            taken: 'Email is already registered'
        },
        password: {
            length: lang.js_validator_password_length,
            matches: lang.js_validator_password_matches,
            security: lang.js_validator_bad_password_security
        },
        captcha: lang.js_validator_no_image_text,
        question: lang.js_validator_no_security_question
    };

    function showError(input, message) {
        hideError(input);
        const error = document.createElement('div');
        error.className = 'error-message';
        error.style.color = 'red';
        error.style.fontSize = '12px';
        error.style.marginTop = '5px';
        error.textContent = message;
        input.parentNode.appendChild(error);
        input.classList.add('error');
    }

    function hideError(input) {
        const error = input.parentNode.querySelector('.error-message');
        if (error) error.remove();
        input.classList.remove('error');
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    async function checkRemote(url, data) {
        try {
            // MyBB использует FormData вместо URLSearchParams
            const formData = new FormData();
            formData.append('my_post_key', my_post_key);
            
            for (const key in data) {
                formData.append(key, data[key]);
            }

            console.log('Отправка запроса на:', url, Object.fromEntries(formData));
            
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            // MyBB может возвращать как JSON, так и plain text
            const responseText = await response.text();
            console.log('Ответ сервера:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                result = responseText;
            }

            // Обработка различных форматов ответов MyBB
            if (result === "true") {
                return { valid: true, message: null };
            }
            
            if (typeof result === 'string' && result !== "true") {
                return { valid: false, message: result };
            }
            
            if (result && typeof result === 'object') {
                if (result.errors && Array.isArray(result.errors)) {
                    return { valid: false, message: result.errors[0] };
                }
                if (result.error) {
                    return { valid: false, message: result.error };
                }
                if (result.valid !== undefined) {
                    return { valid: result.valid, message: result.message };
                }
            }

            return { valid: true, message: null };
            
        } catch (err) {
            console.error('Ошибка валидации:', err);
            return { valid: false, message: 'Ошибка сервера' };
        }
    }

    const validators = {
        username: async value => {
            if (!value) return errorMessages.username.required;
            if (value.length < regsettings.minnamelength || value.length > regsettings.maxnamelength) {
                return errorMessages.username.length;
            }
            const result = await checkRemote('xmlhttp.php?action=username_availability', { username: value });
            return result.valid ? null : (result.message || errorMessages.username.taken);
        },
        
        email: async value => {
            if (!value) return errorMessages.required;
            if (!validateEmail(value)) return errorMessages.email.invalid;
            const result = await checkRemote('xmlhttp.php?action=email_availability', { email: value });
            return result.valid ? null : (result.message || errorMessages.email.taken);
        },
        
        email2: value => {
            const email = document.getElementById('email')?.value;
            if (!value) return errorMessages.required;
            if (!email || value !== email) return errorMessages.email.match;
            return null;
        },
        
        password: async value => {
            if (!value) return errorMessages.password.length;
            if (value.length < regsettings.minpasswordlength) return errorMessages.password.length;

            const username = document.getElementById('username')?.value;
            const email = document.getElementById('email')?.value;
            
            if ((email && value === email) || 
                (username && value === username) ||
                (email && value.includes(email)) || 
                (username && value.includes(username)) ||
                (email && email.includes(value)) || 
                (username && username.includes(value))) {
                return errorMessages.password.security;
            }

            if (regsettings.requirecomplexpasswords == "1") {
                const result = await checkRemote('xmlhttp.php?action=complex_password', { password: value });
                if (!result.valid) return result.message || 'Password does not meet complexity requirements';
            }
            return null;
        },
        
        password2: value => {
            const password = document.getElementById('password')?.value;
            if (!value) return errorMessages.password.length;
            if (!password || value !== password) return errorMessages.password.matches;
            return null;
        },
        
        captcha: async value => {
            if (!value) return errorMessages.captcha;
            const imagehash = document.getElementById('imagehash')?.value;
            if (!imagehash) return 'Missing captcha hash';
            
            const result = await checkRemote('xmlhttp.php?action=validate_captcha', { 
                imagestring: value, 
                imagehash: imagehash 
            });
            return result.valid ? null : (result.message || 'Invalid captcha');
        },
        
        question: async value => {
            if (!value) return errorMessages.question;
            const questionId = document.getElementById('question_id')?.value;
            if (!questionId) return 'Missing question ID';
            
            const result = await checkRemote('xmlhttp.php?action=validate_question', { 
                answer: value, 
                question: questionId 
            });
            return result.valid ? null : (result.message || 'Invalid answer');
        }
    };

    async function validateField(event) {
        const field = event.target;
        const name = field.name;
        
        hideError(field);

        let errorMessage = null;

        // Custom fields
        if (name.includes('profile_fields')) {
            if (!field.value && field.type !== 'checkbox') {
                errorMessage = errorMessages.required;
            }
            if (field.type === 'checkbox') {
                const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
                const checked = Array.from(checkboxes).some(cb => cb.checked);
                if (!checked) errorMessage = errorMessages.required;
            }
        } 
        // Основные поля
        else {
            const validatorName = name.replace('[]', '');
            if (validators[validatorName]) {
                errorMessage = await validators[validatorName](field.value);
            }
        }

        if (errorMessage) {
            showError(field, errorMessage);
            return false;
        }
        return true;
    }

    function setupCustomFieldsValidation() {
        try {
            const requiredFields = JSON.parse(regsettings.requiredfields);
            requiredFields.forEach(field => {
                let selector;
                if (field.type === "textarea") {
                    selector = `textarea[name="profile_fields[${field.fid}]"]`;
                } else if (field.type === "multiselect") {
                    selector = `select[name="profile_fields[${field.fid}][]"]`;
                } else if (field.type === "checkbox") {
                    selector = `input[type="checkbox"][name="profile_fields[${field.fid}][]"]`;
                } else {
                    selector = `input[name="profile_fields[${field.fid}]"]`;
                }

                document.querySelectorAll(selector).forEach(el => {
                    el.addEventListener('blur', validateField);
                    el.addEventListener('input', () => hideError(el));
                });
            });
        } catch (err) { 
            console.error('Ошибка парсинга custom fields:', err); 
        }
    }

    async function validateForm(e) {
        e.preventDefault();
        let isValid = true;

        // Валидация основных полей
        const mainFields = ['username', 'email', 'email2'];
        if (regsettings.regtype !== "randompass") {
            mainFields.push('password', 'password2');
        }

        for (const fieldName of mainFields) {
            const field = document.getElementById(fieldName);
            if (field) {
                const valid = await validateField({ target: field });
                if (!valid) isValid = false;
            }
        }

        // Дополнительные проверки
        if (regsettings.captchaimage == "1" && regsettings.captchahtml == "1") {
            const captchaField = document.getElementById('imagestring');
            if (captchaField) {
                const valid = await validateField({ target: captchaField });
                if (!valid) isValid = false;
            }
        }

        if (regsettings.securityquestion == "1" && regsettings.questionexists == "1") {
            const questionField = document.getElementById('answer');
            if (questionField) {
                const valid = await validateField({ target: questionField });
                if (!valid) isValid = false;
            }
        }

        // Валидация custom fields
        try {
            const requiredFields = JSON.parse(regsettings.requiredfields);
            for (const field of requiredFields) {
                let elements;
                if (field.type === "textarea") {
                    elements = document.querySelectorAll(`textarea[name="profile_fields[${field.fid}]"]`);
                } else if (field.type === "multiselect") {
                    elements = document.querySelectorAll(`select[name="profile_fields[${field.fid}][]"]`);
                } else if (field.type === "checkbox") {
                    elements = document.querySelectorAll(`input[type="checkbox"][name="profile_fields[${field.fid}][]"]`);
                } else {
                    elements = document.querySelectorAll(`input[name="profile_fields[${field.fid}]"]`);
                }

                for (const element of elements) {
                    let valid = true;
                    if (field.type === "checkbox") {
                        const checkboxes = document.querySelectorAll(`input[name="${element.name}"]`);
                        valid = Array.from(checkboxes).some(cb => cb.checked);
                    } else {
                        valid = !!element.value;
                    }
                    
                    if (!valid) {
                        showError(element, errorMessages.required);
                        isValid = false;
                    }
                }
            }
        } catch (err) {
            console.error('Ошибка валидации custom fields:', err);
        }

        if (isValid) {
            registrationForm.submit();
        } else {
            console.log('Форма содержит ошибки');
        }
    }

    function init() {
        console.log('Инициализация валидации формы...');

        // Основные поля
        const mainFields = ['username', 'email', 'email2', 'password', 'password2', 'imagestring', 'answer'];
        mainFields.forEach(name => {
            const field = document.getElementById(name);
            if (field) {
                field.addEventListener('blur', validateField);
                field.addEventListener('input', () => hideError(field));
            }
        });

        // Custom fields
        setupCustomFieldsValidation();

        // Обработчик формы
        registrationForm.addEventListener('submit', validateForm);

        console.log('Валидация инициализирована');
    }

    init();
});