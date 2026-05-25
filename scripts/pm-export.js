/**
 * PM Export Module
 * Handles private message export functionality
 */

(function() {
    'use strict';
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        
        // Format selection animation
        const formatCards = document.querySelectorAll('.format-card');
        
        const updateSelection = (card, radio) => {
            document.querySelectorAll('.format-card').forEach(c => {
                c.classList.remove('selected');
            });
            if(radio.checked) {
                card.classList.add('selected');
            }
        };
        
        formatCards.forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            if (!radio) return;
            
            updateSelection(card, radio);
            
            radio.addEventListener('change', () => {
                updateSelection(card, radio);
            });
            
            card.addEventListener('click', (e) => {
                if (e.target !== radio) {
                    radio.checked = true;
                    updateSelection(card, radio);
                }
            });
        });
        
        // Confirmation before export with deletion
        const form = document.getElementById('exportForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const deleteCheckbox = document.querySelector('input[name="deletepms"]');
                if (deleteCheckbox && deleteCheckbox.checked) {
                    const confirmMsg = '⚠️ WARNING! You have chosen to delete messages after export.\n\n' +
                                      'This action cannot be undone.\n\n' +
                                      'We recommend saving a copy of the export before deletion.\n\n' +
                                      'Continue?';
                    if (!confirm(confirmMsg)) {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Folder selection validation
                const folderSelect = document.querySelector('select[name="exportfolders[]"]');
                if (folderSelect) {
                    const selectedOptions = Array.from(folderSelect.selectedOptions);
                    if (selectedOptions.length === 0) {
                        alert('Please select at least one folder to export.');
                        e.preventDefault();
                        return;
                    }
                }
                
                // Show loading state on button
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
                    submitBtn.disabled = true;
                    
                    // Re-enable after form submission (fallback)
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 30000);
                }
            });
        }
        
        // Add validation for days input field
        const daycutInput = document.querySelector('input[name="daycut"]');
        if (daycutInput) {
            daycutInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                if (isNaN(value)) {
                    this.value = 30;
                } else if (value < 1) {
                    this.value = 1;
                } else if (value > 3650) {
                    this.value = 3650;
                }
            });
            
            // Add blur validation
            daycutInput.addEventListener('blur', function() {
                if (this.value === '' || this.value === null) {
                    this.value = 30;
                }
            });
        }
        
        // Multi-select helper: Show selected count
        const folderSelect = document.querySelector('select[name="exportfolders[]"]');
        if (folderSelect) {
            const updateSelectedCount = () => {
                const selectedCount = Array.from(folderSelect.selectedOptions).length;
                const badge = folderSelect.parentElement.querySelector('.selected-count');
                if (badge) {
                    if (selectedCount > 0) {
                        badge.textContent = `Selected: ${selectedCount} folder(s)`;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            };
            
            folderSelect.addEventListener('change', updateSelectedCount);
            updateSelectedCount();
            
            // Add helper text if not exists
            if (!folderSelect.parentElement.querySelector('.selected-count')) {
                const countBadge = document.createElement('small');
                countBadge.className = 'badge selected-count';
                countBadge.style.marginTop = '0.5rem';
                countBadge.style.display = 'none';
                folderSelect.parentElement.appendChild(countBadge);
                updateSelectedCount();
            }
        }
        
        // Tooltip functionality for better UX
        const addTooltip = (element, text) => {
            if (!element) return;
            
            element.setAttribute('title', text);
            element.style.cursor = 'help';
        };
        
        // Add tooltips to important elements
        addTooltip(document.querySelector('input[name="daycut"]'), 'Enter number of days (1-3650)');
        addTooltip(document.querySelector('select[name="exportfolders[]"]'), 'Select one or more folders to export');
        addTooltip(document.querySelector('input[name="exportunread"]'), 'Export only unread messages');
        addTooltip(document.querySelector('input[name="deletepms"]'), '⚠️ Delete messages after export - This cannot be undone!');
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const form = document.getElementById('exportForm');
                if (form) {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.click();
                    }
                }
            }
            
            // Escape to reset form
            if (e.key === 'Escape') {
                const deleteCheckbox = document.querySelector('input[name="deletepms"]');
                if (deleteCheckbox && deleteCheckbox.checked) {
                    deleteCheckbox.checked = false;
                }
            }
        });
        
        // Log export initialization (for debugging)
        console.log('PM Export module initialized');
        
    });
})();