(function() {
    // I18N: AGS_LANG (js_* lang keys, prefix stripped) is printed by moderation.php; English fallbacks are used if a key is missing.
    function t(key, fallback, ...args) {
        let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
        args.forEach(function (a, i) { s = s.split('{' + (i + 1) + '}').join(String(a)); });
        return s;
    }

    const form = document.getElementById('delayedModForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const typeChecked = form.querySelectorAll('input[name="type"]:checked');
        if (typeChecked.length === 0) {
            alert(t('dm_select_action', 'Please select a moderation action to schedule.'));
            e.preventDefault();
            return false;
        }
        
        const dateYear = form.querySelector('input[name="date_year"]');
        const dateTime = form.querySelector('input[name="date_time"]');
        let errorMsg = '';
        
        if (dateYear && dateYear.value) {
            const year = parseInt(dateYear.value);
            const currentYear = new Date().getFullYear();
            if (isNaN(year) || year < currentYear || year > currentYear + 10) {
                errorMsg = t('dm_bad_year', 'Please enter a valid year ({1} - {2}).', currentYear, currentYear + 10);
                dateYear.focus();
            }
        }
        
        if (!errorMsg && dateTime && dateTime.value) {
            const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (!timeRegex.test(dateTime.value.trim())) {
                errorMsg = t('dm_bad_time', 'Please enter time in HH:MM format (24-hour). Example: 14:30');
                dateTime.focus();
            }
        }
        
        const moveRadio = document.getElementById('type_movecopythread');
        if (!errorMsg && moveRadio && moveRadio.checked) {
            const forumSelect = form.querySelector('select[name="fid"]');
            if (forumSelect && (!forumSelect.value || forumSelect.value === '0')) {
                errorMsg = t('dm_select_forum', 'Please select a destination forum for move/copy operation.');
                forumSelect.focus();
            }
        }
        
        if (errorMsg) {
            alert(errorMsg);
            e.preventDefault();
            return false;
        }
        
        if (!confirm(t('dm_confirm', 'Schedule this moderation action on the selected date and time? You can manage it later from the queue.'))) {
            e.preventDefault();
            return false;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            while (submitBtn.firstChild) submitBtn.removeChild(submitBtn.firstChild);
            const spin = document.createElement('i');
            spin.className = 'fas fa-spinner fa-pulse me-2';
            submitBtn.appendChild(spin);
            submitBtn.appendChild(document.createTextNode(' ' + t('dm_scheduling', 'Scheduling...')));
        }
        return true;
    });
    
    const yearInput = document.querySelector('input[name="date_year"]');
    const timeInput = document.querySelector('input[name="date_time"]');
    
    if (yearInput) {
        yearInput.addEventListener('input', function(e) {
            let val = this.value.replace(/[^0-9]/g, '');
            if (val.length > 4) val = val.slice(0,4);
            this.value = val;
            const yr = parseInt(val);
            const curYear = new Date().getFullYear();
            if (val.length === 4 && (yr >= curYear && yr <= curYear+10)) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else if (val.length > 0) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-valid','is-invalid');
            }
        });
    }
    
    if (timeInput) {
        timeInput.addEventListener('input', function() {
            let val = this.value.replace(/[^0-9:]/g, '');
            if (val.length === 2 && !val.includes(':')) val = val + ':';
            if (val.length > 5) val = val.slice(0,5);
            this.value = val;
            const regex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (regex.test(val)) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else if (val.length >= 3) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-valid','is-invalid');
            }
        });
    }
})();