(function() {
    const form = document.getElementById('delayedModForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const typeChecked = form.querySelectorAll('input[name="type"]:checked');
        if (typeChecked.length === 0) {
            alert('Please select a moderation action to schedule.');
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
                errorMsg = 'Please enter a valid year (' + currentYear + ' - ' + (currentYear+10) + ').';
                dateYear.focus();
            }
        }
        
        if (!errorMsg && dateTime && dateTime.value) {
            const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (!timeRegex.test(dateTime.value.trim())) {
                errorMsg = 'Please enter time in HH:MM format (24-hour). Example: 14:30';
                dateTime.focus();
            }
        }
        
        const moveRadio = document.getElementById('type_movecopythread');
        if (!errorMsg && moveRadio && moveRadio.checked) {
            const forumSelect = form.querySelector('select[name="fid"]');
            if (forumSelect && (!forumSelect.value || forumSelect.value === '0')) {
                errorMsg = 'Please select a destination forum for move/copy operation.';
                forumSelect.focus();
            }
        }
        
        if (errorMsg) {
            alert(errorMsg);
            e.preventDefault();
            return false;
        }
        
        if (!confirm('Schedule this moderation action on the selected date & time? You can manage later from queue.')) {
            e.preventDefault();
            return false;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse me-2"></i> Scheduling...';
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