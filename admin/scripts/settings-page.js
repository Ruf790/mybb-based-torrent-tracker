document.addEventListener('DOMContentLoaded', function() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#startPicker", {
            enableTime: true,
            dateFormat: "Y-m-d H:i:S",
            time_24hr: true,
            defaultHour: 0,
            defaultMinute: 0,
            defaultSecond: 0
        });
        flatpickr("#endPicker", {
            enableTime: true,
            dateFormat: "Y-m-d H:i:S",
            time_24hr: true,
            defaultHour: 23,
            defaultMinute: 59,
            defaultSecond: 59
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const triggerTabList = document.querySelectorAll('.sidebar-nav .nav-link[data-bs-toggle="tab"]');
    triggerTabList.forEach(triggerEl => {
        triggerEl.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('href');
            if (!target) return;
            
            document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            this.classList.add('active');
            
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            const targetPane = document.querySelector(target);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
            
            try {
                localStorage.setItem('settings_active_tab', target);
            } catch(e) {}
        });
    });
    
    try {
        const lastTab = localStorage.getItem('settings_active_tab');
        if (lastTab) {
            const link = document.querySelector(`.sidebar-nav .nav-link[href="${lastTab}"]`);
            if (link) {
                link.click();
            }
        }
    } catch(e) {}
});
