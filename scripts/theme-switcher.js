/**
 * Theme Switcher for MyBB
 * Handles dark/light mode switching and year input formatting
 */

(function() {
    "use strict";
    
    /**
     * Initialize theme switching
     */
    function initThemeSwitcher() {
        const htmlElement = document.documentElement;
        const switchElement = document.getElementById('darkModeSwitch');
        
        // Get preferred theme from localStorage or system preference
        const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
        const savedTheme = localStorage.getItem('bsTheme');
        let currentTheme = savedTheme || (prefersDarkScheme ? 'dark' : 'light');
        
        // Apply theme
        htmlElement.setAttribute('data-bs-theme', currentTheme);
        
        // Update switch state if exists
        if (switchElement) {
            switchElement.checked = currentTheme === 'dark';
            
            // Listen for switch changes
            switchElement.addEventListener('change', function() {
                const newTheme = this.checked ? 'dark' : 'light';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('bsTheme', newTheme);
                
                // Dispatch custom event for other scripts
                window.dispatchEvent(new CustomEvent('themeChanged', { 
                    detail: { theme: newTheme } 
                }));
            });
        }
        
        // Listen for system theme changes
        window.matchMedia("(prefers-color-scheme: dark)").addEventListener('change', function(e) {
            if (!localStorage.getItem('bsTheme')) {
                const newTheme = e.matches ? 'dark' : 'light';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                if (switchElement) {
                    switchElement.checked = newTheme === 'dark';
                }
            }
        });
    }
    
    /**
     * Initialize year input formatting
     */
    function initYearInput() {
        const yearInput = document.querySelector('input[name="bday3"]');
        if (yearInput) {
            yearInput.addEventListener('input', function(e) {
                // Remove non-digit characters and limit to 4 digits
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
            });
            
            // Also validate on blur
            yearInput.addEventListener('blur', function(e) {
                const val = parseInt(this.value, 10);
                if (this.value.length === 4 && (val < 1900 || val > new Date().getFullYear())) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
    }
    
    /**
     * Initialize all components when DOM is ready
     */
    function init() {
        initThemeSwitcher();
        initYearInput();
    }
    
    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();