const QuickPermEditor = {
    _initialized: false,

    init: function(id) {
        console.log('QuickPermEditor init for ID:', id);

        const enabledContainer = document.getElementById('enabled-' + id);
        const disabledContainer = document.getElementById('disabled-' + id);
        const fieldsInput = document.getElementById('fields_' + id);

        if (!enabledContainer || !disabledContainer || !fieldsInput) {
            console.log('QuickPermEditor: Контейнеры не найдены для ID', id);
            return;
        }

        // Удаляем все старые обработчики с контейнеров
        const newEnabled = enabledContainer.cloneNode(false);
        const newDisabled = disabledContainer.cloneNode(false);
        
        // Копируем содержимое
        while (enabledContainer.firstChild) {
            newEnabled.appendChild(enabledContainer.firstChild);
        }
        while (disabledContainer.firstChild) {
            newDisabled.appendChild(disabledContainer.firstChild);
        }
        
        // Заменяем контейнеры
        enabledContainer.parentNode.replaceChild(newEnabled, enabledContainer);
        disabledContainer.parentNode.replaceChild(newDisabled, disabledContainer);
        
        // Получаем новые контейнеры
        const newEnabledContainer = document.getElementById('enabled-' + id);
        const newDisabledContainer = document.getElementById('disabled-' + id);
        
        // Делаем бейджи перетаскиваемыми
        this.makeBadgesDraggable(id);
        
        // Настраиваем дроп-зоны
        this.setupDropZone(newEnabledContainer, id);
        this.setupDropZone(newDisabledContainer, id);
        
        // Настраиваем клики
        this.setupClickHandler(newEnabledContainer, id);
        this.setupClickHandler(newDisabledContainer, id);
    },

    makeBadgesDraggable: function(id) {
        const badges = document.querySelectorAll('#permission-fields-' + id + ' .permission-badge');
        
        badges.forEach(badge => {
            badge.setAttribute('draggable', 'true');
            
            // Удаляем старые обработчики
            badge.removeEventListener('dragstart', badge._dragStart);
            badge.removeEventListener('dragend', badge._dragEnd);
            
            // Создаем новые
            badge._dragStart = (e) => {
                e.dataTransfer.setData('text/plain', e.target.dataset.perm);
                e.dataTransfer.setData('source-id', e.target.parentElement.id);
                e.dataTransfer.setData('group-id', id);
                e.dataTransfer.effectAllowed = 'move';
                e.target.classList.add('dragging');
            };
            
            badge._dragEnd = (e) => {
                e.target.classList.remove('dragging');
                this.buildFieldsList(id);
            };
            
            badge.addEventListener('dragstart', badge._dragStart);
            badge.addEventListener('dragend', badge._dragEnd);
        });
    },

    setupDropZone: function(container, id) {
        if (!container) return;
        
        // Удаляем старые обработчики
        container.removeEventListener('dragover', container._dragOver);
        container.removeEventListener('dragleave', container._dragLeave);
        container.removeEventListener('drop', container._drop);
        
        // Создаем новые
        container._dragOver = (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const groupIdFromDrag = e.dataTransfer.getData('group-id');
            if (groupIdFromDrag === id) {
                container.style.backgroundColor = 'rgba(13, 110, 253, 0.15)';
                container.style.borderColor = '#0d6efd';
                container.style.borderStyle = 'solid';
            }
        };
        
        container._dragLeave = (e) => {
            container.style.backgroundColor = '';
            container.style.borderColor = '';
            container.style.borderStyle = 'dashed';
        };
        
        container._drop = (e) => {
            e.preventDefault();
            
            container.style.backgroundColor = '';
            container.style.borderColor = '';
            container.style.borderStyle = 'dashed';
            
            const permName = e.dataTransfer.getData('text/plain');
            const sourceId = e.dataTransfer.getData('source-id');
            const groupIdFromDrag = e.dataTransfer.getData('group-id');
            
            if (groupIdFromDrag !== id) return;
            if (!permName) return;
            
            const draggedBadge = document.querySelector(`#permission-fields-${id} .permission-badge[data-perm="${permName}"]`);
            if (!draggedBadge) return;
            
            const sourceContainer = document.getElementById(sourceId);
            if (sourceContainer === container) return;
            
            if (sourceContainer && sourceContainer.contains(draggedBadge)) {
                sourceContainer.removeChild(draggedBadge);
            }
            
            container.appendChild(draggedBadge);
            this.updateBadgeClasses(draggedBadge, container);
            this.buildFieldsList(id);
            this.updatePermissionStatus(id, false);
        };
        
        container.addEventListener('dragover', container._dragOver);
        container.addEventListener('dragleave', container._dragLeave);
        container.addEventListener('drop', container._drop);
    },

    setupClickHandler: function(container, id) {
        if (!container) return;
        
        // Удаляем старый обработчик
        container.removeEventListener('click', container._click);
        
        // Создаем новый
        container._click = (e) => {
            const badge = e.target.closest('.permission-badge');
            if (!badge) return;
            
            const currentContainer = badge.parentElement;
            let targetContainer;
            
            if (currentContainer.id.startsWith('enabled-')) {
                targetContainer = document.getElementById('disabled-' + id);
                badge.classList.remove('bg-success', 'bg-opacity-10', 'text-success');
                badge.classList.add('bg-danger', 'bg-opacity-10', 'text-danger');
            } else if (currentContainer.id.startsWith('disabled-')) {
                targetContainer = document.getElementById('enabled-' + id);
                badge.classList.remove('bg-danger', 'bg-opacity-10', 'text-danger');
                badge.classList.add('bg-success', 'bg-opacity-10', 'text-success');
            } else {
                return;
            }
            
            if (!targetContainer) return;
            
            currentContainer.removeChild(badge);
            targetContainer.appendChild(badge);
            
            this.buildFieldsList(id);
            this.updatePermissionStatus(id, false);
        };
        
        container.addEventListener('click', container._click);
    },

    // ОСТАЛЬНЫЕ МЕТОДЫ БЕЗ ИЗМЕНЕНИЙ
    updateBadgeClasses: function(badge, targetContainer) {
        badge.classList.remove(
            'bg-success', 'bg-opacity-10', 'text-success',
            'bg-danger', 'bg-opacity-10', 'text-danger'
        );

        if (targetContainer.id.startsWith('enabled-')) {
            badge.classList.add('bg-success', 'bg-opacity-10', 'text-success');
        } else {
            badge.classList.add('bg-danger', 'bg-opacity-10', 'text-danger');
        }
    },

    buildFieldsList: function(id) {
        const enabledContainer = document.getElementById('enabled-' + id);
        const fieldsInput = document.getElementById('fields_' + id);
        const fieldsInherit = document.getElementById('fields_inherit_' + id);

        if (!enabledContainer || !fieldsInput) return;

        const enabledPerms = Array.from(enabledContainer.querySelectorAll('.permission-badge'))
            .map(b => b.dataset.perm)
            .filter(Boolean);

        const newValue = enabledPerms.join(',');
        fieldsInput.value = newValue;
        if (fieldsInherit) fieldsInherit.value = '0';
    },

    updatePermissionStatus: function(id, isInherited) {
        const row = document.querySelector(`tr[data-group-id="${id}"]`);
        if (!row) return;

        const statusBadge = row.querySelector('.badge.bg-info, .badge.bg-warning');
        if (!statusBadge) return;

        if (isInherited) {
            statusBadge.className = 'badge bg-info bg-opacity-10 text-info px-3 py-2';
            statusBadge.innerHTML = '<i class="fas fa-link me-1"></i>Inherited';
            this.removeClearButton(id);
        } else {
            statusBadge.className = 'badge bg-warning bg-opacity-10 text-warning px-3 py-2';
            statusBadge.innerHTML = '<i class="fas fa-pen me-1"></i>Custom';
            this.addClearButton(id, row);
        }
    },

    addClearButton: function(id, row) {
        const actionsCell = row.querySelector('td:last-child .btn-group');
        if (!actionsCell) return;
        if (actionsCell.querySelector('.clear-permission-btn')) return;

        const btn = document.createElement('a');
        btn.href = 'javascript:void(0);';
        btn.className = 'btn btn-outline-danger btn-sm ms-1 clear-permission-btn';
        btn.dataset.gid = id;
        btn.title = 'Clear Custom Permissions';
        btn.innerHTML = '<i class="fas fa-trash"></i>';
        btn.onclick = (e) => {
            e.preventDefault();
            this.clearPermissions(id);
        };

        actionsCell.appendChild(btn);
    },

    removeClearButton: function(id) {
        const row = document.querySelector(`tr[data-group-id="${id}"]`);
        if (!row) return;
        const btn = row.querySelector('.clear-permission-btn');
        if (btn) btn.remove();
    },

    clearPermissions: function(id) {
        if (!confirm('Are you sure you want to clear custom permissions and revert to inherited values?')) return;

        const defaultPerms = document.getElementById('fields_default_' + id);
        if (!defaultPerms) return;

        const defaults = defaultPerms.value.split(',').filter(Boolean);
        this.resetPermissionsToDefault(id, defaults);

        const fieldsInput = document.getElementById('fields_' + id);
        const fieldsInherit = document.getElementById('fields_inherit_' + id);

        if (fieldsInput) fieldsInput.value = defaults.join(',');
        if (fieldsInherit) fieldsInherit.value = '1';

        this.updatePermissionStatus(id, true);
        this.removeClearButton(id);
        this.showNotification('Permissions reset to inherited values', 'info');
    },

    resetPermissionsToDefault: function(id, defaults) {
        const enabled = document.getElementById('enabled-' + id);
        const disabled = document.getElementById('disabled-' + id);
        if (!enabled || !disabled) return;

        enabled.innerHTML = '';
        disabled.innerHTML = '';

        defaults.forEach(perm => {
            const label = this.getPermissionLabel(perm);
            const badge = this.createPermissionBadge(perm, label, true);
            enabled.appendChild(badge);
        });

        this.makeBadgesDraggable(id);
    },

    createPermissionBadge: function(perm, label, enabled = true) {
        const badge = document.createElement('span');
        badge.className = `badge ${enabled ? 'bg-success' : 'bg-danger'} bg-opacity-10 text-${enabled ? 'success' : 'danger'} me-2 mb-1 permission-badge`;
        badge.dataset.perm = perm;
        badge.setAttribute('draggable', 'true');
        badge.textContent = '• ' + label;
        return badge;
    },

    getPermissionLabel: function(perm) {
        const labels = {
            'canview': 'View',
            'canpostthreads': 'Post Threads',
            'canpostreplys': 'Post Replies',
            'canpostpolls': 'Post Polls'
        };
        return labels[perm] || perm;
    },

    showNotification: function(message, type = 'success') {
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:350px;';
            document.body.appendChild(container);
        }

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.role = 'alert';
        alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        container.appendChild(alert);

        setTimeout(() => alert.remove(), 3000);
    },

    initAll: function() {
        console.log('QuickPermEditor initAll called');
        const groupRows = document.querySelectorAll('tr[data-group-id]');
        
        groupRows.forEach(row => {
            const groupId = row.dataset.groupId;
            if (groupId) {
                this.init(groupId);
            }
        });
    },

    debounceInitAll: (() => {
        let timeout;
        return function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => QuickPermEditor.initAll(), 100);
        };
    })(),

    resetAllToInherited: function() {
        console.log('Resetting all permissions to inherited');
        
        if (!confirm('Are you sure you want to reset all permission changes to inherited values?')) {
            return false;
        }
        
        document.querySelectorAll('tr[data-group-id]').forEach(row => {
            const id = row.dataset.groupId;
            const defaultPerms = document.getElementById('fields_default_' + id);
            const fieldsInput = document.getElementById('fields_' + id);
            const fieldsInherit = document.getElementById('fields_inherit_' + id);
            
            if (defaultPerms && fieldsInput) {
                const defaults = defaultPerms.value.split(',').filter(Boolean);
                this.resetPermissionsToDefault(id, defaults);
                fieldsInput.value = defaults.join(',');
                if (fieldsInherit) fieldsInherit.value = '1';
                this.updatePermissionStatus(id, true);
                this.removeClearButton(id);
            }
        });
        
        this.showNotification('All permissions reset to inherited values', 'success');
        return true;
    },

    validateFormFields: function() {
        let isValid = true;
        document.querySelectorAll('tr[data-group-id]').forEach(row => {
            const fieldsInput = document.getElementById('fields_' + row.dataset.groupId);
            if (!fieldsInput) {
                console.error(`Missing fields_${row.dataset.groupId}`);
                isValid = false;
            }
        });
        return isValid;
    },

    addStyles: function() {
        if (!document.querySelector('#quick-perm-editor-styles')) {
            const style = document.createElement('style');
            style.id = 'quick-perm-editor-styles';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                .permission-badge.dragging {
                    opacity: 0.5;
                    transform: scale(1.05);
                    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
                }
                #notification-container .alert {
                    animation: slideIn 0.3s ease;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    border: none;
                    border-radius: 8px;
                }
            `;
            document.head.appendChild(style);
        }
    },

    initEditor: function() {
        console.log('QuickPermEditor: Full initialization');
        
        this.addStyles();
        window.resetPermissions = () => this.resetAllToInherited();
        this.debounceInitAll();
    }
};

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM Content Loaded');
    QuickPermEditor.initEditor();
});

console.log('QuickPermEditor: Full script loaded');