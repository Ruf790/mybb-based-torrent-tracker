const QuickPermEditor = {
    init: function(id) {
        const enabledList = document.getElementById('fields_enabled_' + id);
        const disabledList = document.getElementById('fields_disabled_' + id);
        const fieldsInput = document.getElementById('fields_' + id);

        if (!enabledList || !disabledList || !fieldsInput) {
            return;
        }

        // Инициализируем сортировку для enabled списка
        this.initSortable(enabledList, disabledList, id);
        
        // Инициализируем сортировку для disabled списка
        this.initSortable(disabledList, enabledList, id);
    },

    initSortable: function(element, connectedElement, id) {
        element.setAttribute('draggable', 'true');

        element.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', e.target.id);
            e.target.classList.add('dragging');
        });

        element.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = this.getDragAfterElement(element, e.clientY);
            const draggable = document.querySelector('.dragging');
            
            if (afterElement == null) {
                element.appendChild(draggable);
            } else {
                element.insertBefore(draggable, afterElement);
            }
        });

        element.addEventListener('dragend', function(e) {
            e.target.classList.remove('dragging');
            QuickPermEditor.buildFieldsList(id);
        });

        // Разрешаем дроп из connected элемента
        connectedElement.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        connectedElement.addEventListener('drop', function(e) {
            e.preventDefault();
            const id = e.dataTransfer.getData('text/plain');
            const draggable = document.getElementById(id);
            const afterElement = this.getDragAfterElement(this, e.clientY);
            
            if (afterElement == null) {
                this.appendChild(draggable);
            } else {
                this.insertBefore(draggable, afterElement);
            }
            
            QuickPermEditor.buildFieldsList(
                id.replace('fields_enabled_', '').replace('fields_disabled_', '')
            );
        });
    },

    getDragAfterElement: function(container, y) {
        const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },

    buildFieldsList: function(id) {
        let new_input = '';
        const enabledList = document.getElementById('fields_enabled_' + id);

        if (!enabledList) return;

        // Собираем ID всех элементов в enabled списке
        enabledList.querySelectorAll('li').forEach(function(item) {
            const itemId = item.id.split("-");
            if (itemId[1]) {
                if (new_input) {
                    new_input += ",";
                }
                new_input += itemId[1];
            }
        });

        const fieldsInput = document.getElementById('fields_' + id);
        if (!fieldsInput) return;

        // Если значение изменилось, снимаем галочку с default permissions
        if (fieldsInput.value !== new_input) {
            const defaultPermissions = document.getElementById('default_permissions_' + id);
            if (defaultPermissions) {
                defaultPermissions.checked = false;
            }
        }

        // Обновляем скрытое поле
        fieldsInput.value = new_input;

        // Устанавливаем fields_inherit в 0 если существует
        const fieldsInherit = document.getElementById('fields_inherit_' + id);
        if (fieldsInherit) {
            fieldsInherit.value = '0';
        }
    }
};