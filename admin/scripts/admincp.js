const AdminCP = {
    init: function() {
        // Инициализация при необходимости
    },

    deleteConfirmation: function(element, message) {
        if(!element) return false;
        
        const confirmReturn = confirm(message);
        if(confirmReturn === true) {
            const form = document.createElement("form");
            form.method = "post";
            form.action = element.href;
            form.style.display = "none";
            
            document.body.appendChild(form);
            form.submit();
        }
        return false;
    }
};

// Замена $(function()) на DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    AdminCP.init();
});