/**
 * Select2 Field Module
 * Handles user selection with tags functionality
 */

(function(window, document, undefined) {
    'use strict';
    
    // Глобальная переменная для доступа к полю из тестовых функций
    window.globalSelect2Field = null;
    
    // Класс для управления полем с тегами
    function Select2Field(inputId, type, maxRecipients, texts) {
        this.originalInput = document.getElementById(inputId);
        if (!this.originalInput) {
            console.error('Element not found:', inputId);
            return;
        }
        
        // Создаем контейнер для тегов
        this.container = document.createElement('div');
        this.container.className = 'select2-tags-container';
        this.container.id = inputId + '-container';
        
        // Создаем поле ввода внутри контейнера
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'select2-tags-input';
        this.input.placeholder = texts.searchUser;
        this.container.appendChild(this.input);
        
        // Заменяем оригинальный input на наш контейнер
        this.originalInput.parentNode.insertBefore(this.container, this.originalInput.nextSibling);
        this.originalInput.style.display = 'none';
        
        this.type = type;
        this.maxRecipients = maxRecipients;
        this.texts = texts;
        this.recipients = [];
        this.debounceTimer = null;
        this.onChange = null;
        this.clickHandler = null;
        
        // Получаем элементы счетчика и ошибки
        this.counterElement = document.getElementById('recipientCounter');
        this.errorElement = document.getElementById('recipientError');
        
        // Создаем dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'select2-dropdown';
        document.body.appendChild(this.dropdown);
        
        this.init();
        this.initExistingValues();
        this.updateCounter();
    }
    
    Select2Field.prototype.init = function() {
        var self = this;
        
        // Обработчик фокуса
        this.input.addEventListener('focus', function() {
            self.container.classList.add('focused');
            self.updateDropdownPosition();
            if (self.input.value.trim().length >= 2) {
                self.searchUsers(self.input.value.trim());
            }
        });
        
        // Обработчик потери фокуса
        this.input.addEventListener('blur', function() {
            setTimeout(function() {
                self.container.classList.remove('focused');
                self.dropdown.style.display = 'none';
                
                // Если в поле ввода есть текст, добавляем его как получателя
                if (self.input.value.trim().length >= 2) {
                    self.addRecipient(self.input.value.trim());
                }
            }, 200);
        });
        
        // Обработчик ввода
        this.input.addEventListener('input', function(e) {
            var query = e.target.value.trim();
            
            clearTimeout(self.debounceTimer);
            self.debounceTimer = setTimeout(function() {
                if (query.length > 0 && query.length < 2) {
                    self.showMessage(self.texts.inputTooShort);
                    return;
                }
                
                if (query.length >= 2) {
                    self.searchUsers(query);
                } else {
                    self.dropdown.style.display = 'none';
                }
            }, 300);
        });
        
        // Обработчик нажатия клавиш
        this.input.addEventListener('keydown', function(e) {
            var items = self.dropdown.querySelectorAll('.select2-item');
            var activeItem = self.dropdown.querySelector('.active');
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    self.navigateDropdown(items, activeItem, 'down');
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    self.navigateDropdown(items, activeItem, 'up');
                    break;
                case 'Enter':
                    if (activeItem) {
                        e.preventDefault();
                        var username = activeItem.getAttribute('data-username');
                        self.addRecipient(username);
                    } else if (self.input.value.trim().length >= 2) {
                        e.preventDefault();
                        self.addRecipient(self.input.value.trim());
                    }
                    break;
                case 'Escape':
                    self.dropdown.style.display = 'none';
                    if (activeItem) {
                        activeItem.classList.remove('active');
                    }
                    break;
                case 'Backspace':
                    if (self.input.value === '' && self.recipients.length > 0) {
                        e.preventDefault();
                        self.removeRecipient(self.recipients[self.recipients.length - 1]);
                    }
                    break;
                case ',':
                case ';':
                    if (self.input.value.trim().length > 0) {
                        e.preventDefault();
                        var username = self.input.value.slice(0, -1).trim();
                        if (username) {
                            self.addRecipient(username);
                        }
                    }
                    break;
            }
        });
        
        window.addEventListener('resize', function() {
            self.updateDropdownPosition();
        });
    };
    
    Select2Field.prototype.initExistingValues = function() {
        var self = this;
        if (this.originalInput.value) {
            var values = this.originalInput.value.split(',')
                .map(function(v) { return v.trim(); })
                .filter(function(v) { return v !== ''; });
            
            values.forEach(function(value) {
                self.addRecipient(value, true);
            });
        }
    };
    
    Select2Field.prototype.updateCounter = function() {
        var count = this.getRecipientsCount();
        var limit = this.getMaxSelectionSize();
        
        if (this.counterElement) {
            this.counterElement.innerHTML = '<i class="fas fa-user-plus me-1"></i> Recipients: ' + count + '/' + limit;
            
            this.counterElement.classList.remove('near-limit', 'limit-reached');
            if (count >= limit) {
                this.counterElement.classList.add('limit-reached');
            } else if (count >= limit - 2) {
                this.counterElement.classList.add('near-limit');
            }
        }
        
        this.checkLimit();
    };
    
    Select2Field.prototype.checkLimit = function() {
        var count = this.getRecipientsCount();
        var limit = this.getMaxSelectionSize();
        
        if (count >= limit) {
            this.container.classList.add('limit-reached');
            if (this.errorElement) {
                this.errorElement.classList.add('show');
            }
            this.disable();
        } else {
            this.container.classList.remove('limit-reached');
            if (this.errorElement) {
                this.errorElement.classList.remove('show');
            }
            this.enable();
        }
    };
    
    Select2Field.prototype.updateDropdownPosition = function() {
        var rect = this.container.getBoundingClientRect();
        this.dropdown.style.position = 'fixed';
        this.dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        this.dropdown.style.left = (rect.left + window.scrollX) + 'px';
        this.dropdown.style.width = rect.width + 'px';
    };
    
    Select2Field.prototype.navigateDropdown = function(items, activeItem, direction) {
        if (items.length === 0) return;
        
        if (!activeItem) {
            items[0].classList.add('active');
        } else {
            var currentIndex = Array.from(items).indexOf(activeItem);
            var nextIndex;
            
            if (direction === 'down') {
                nextIndex = (currentIndex + 1) % items.length;
            } else {
                nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
            }
            
            activeItem.classList.remove('active');
            items[nextIndex].classList.add('active');
        }
    };
    
    Select2Field.prototype.searchUsers = function(query) {
        var self = this;
        if (query.length < 2) {
            this.dropdown.style.display = 'none';
            return;
        }
        
        fetch("xmlhttp.php?action=get_users&query=" + encodeURIComponent(query))
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                self.displayResults(data);
            })
            .catch(function(error) {
                console.error('Error:', error);
                self.dropdown.style.display = 'none';
            });
    };
    
    Select2Field.prototype.displayResults = function(users) {
        this.dropdown.innerHTML = '';
        
        if (!users || users.length === 0) {
            this.showMessage(this.texts.noMatches);
            return;
        }
        
        if (this.clickHandler) {
            this.dropdown.removeEventListener('click', this.clickHandler);
        }
        
        var self = this;
        users.forEach(function(user) {
            var username = user.username || user.text || user.name || user.label || "";
            var item = document.createElement('div');
            item.className = 'select2-item';
            item.textContent = username;
            item.setAttribute('data-username', username);
            self.dropdown.appendChild(item);
        });
        
        this.clickHandler = function(e) {
            var item = e.target.closest('.select2-item');
            if (item) {
                var username = item.getAttribute('data-username');
                self.addRecipient(username);
                self.dropdown.style.display = 'none';
            }
        };
        
        this.dropdown.addEventListener('click', this.clickHandler);
        this.dropdown.style.display = 'block';
        this.updateDropdownPosition();
    };
    
    Select2Field.prototype.showMessage = function(message) {
        this.dropdown.innerHTML = '<div class="select2-message"><i class="fas fa-info-circle me-1"></i> ' + message + '</div>';
        this.dropdown.style.display = 'block';
        this.updateDropdownPosition();
    };
    
    Select2Field.prototype.addRecipient = function(username, skipDuplicateCheck) {
        if (this.getRecipientsCount() >= this.getMaxSelectionSize()) {
            this.showMessage(this.texts.selectionTooBig);
            return;
        }
        
        if (!skipDuplicateCheck && this.recipients.includes(username)) {
            return;
        }
        
        if (!this.recipients.includes(username)) {
            this.recipients.push(username);
            
            var tag = document.createElement('div');
            tag.className = 'select2-tag';
            tag.innerHTML = 
                '<span class="select2-tag-text"><i class="fas fa-user me-1"></i>' + this.escapeHtml(username) + '</span>' +
                '<button type="button" class="select2-tag-remove" data-username="' + this.escapeHtml(username) + '">×</button>';
            
            this.container.insertBefore(tag, this.input);
            
            var removeButton = tag.querySelector('.select2-tag-remove');
            var self = this;
            removeButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeRecipient(username);
            });
            
            this.input.value = '';
            this.updateOriginalInput();
            this.updateCounter();
            
            if (this.onChange) this.onChange();
        }
        
        this.dropdown.style.display = 'none';
    };
    
    Select2Field.prototype.escapeHtml = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    Select2Field.prototype.removeRecipient = function(username) {
        var index = this.recipients.indexOf(username);
        if (index !== -1) {
            this.recipients.splice(index, 1);
            
            var tags = this.container.querySelectorAll('.select2-tag');
            var self = this;
            tags.forEach(function(tag) {
                var tagUsername = tag.querySelector('.select2-tag-remove').getAttribute('data-username');
                if (tagUsername === username) {
                    self.container.removeChild(tag);
                }
            });
            
            this.updateOriginalInput();
            this.updateCounter();
            
            if (this.onChange) this.onChange();
        }
    };
    
    Select2Field.prototype.updateOriginalInput = function() {
        if (this.recipients && this.recipients.length > 0) {
            this.originalInput.value = this.recipients.join(', ');
        } else {
            this.originalInput.value = '';
        }
        
        var event = new Event('change', { bubbles: true });
        this.originalInput.dispatchEvent(event);
    };
    
    Select2Field.prototype.getRecipientsCount = function() {
        return this.recipients.length;
    };
    
    Select2Field.prototype.getMaxSelectionSize = function() {
        return this.maxRecipients;
    };
    
    Select2Field.prototype.enable = function() {
        this.input.disabled = false;
        this.input.placeholder = this.texts.searchUser;
    };
    
    Select2Field.prototype.disable = function() {
        this.input.disabled = true;
        this.input.placeholder = this.texts.selectionTooBig;
    };
    
    Select2Field.prototype.forceUpdate = function() {
        this.updateOriginalInput();
    };
    
    // Инициализация при загрузке DOM
    document.addEventListener("DOMContentLoaded", function() {
        var maxRecipientsElement = document.getElementById('maxRecipientsData');
        var maxRecipients = maxRecipientsElement ? parseInt(maxRecipientsElement.value) : 5;
        
        var texts = {
            searchUser: "Search for users...",
            noMatches: "No matches found",
            inputTooShort: "Please enter at least 2 characters",
            selectionTooBig: "You are only allowed to send messages to " + maxRecipients + " users at a time"
        };
        
        try {
            window.globalSelect2Field = new Select2Field('to', 'to', maxRecipients, texts);
            
            var form = document.querySelector('form[name="input"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (window.globalSelect2Field) window.globalSelect2Field.forceUpdate();
                });
            }
            
        } catch (error) {
            console.error('Error initializing Select2 field:', error);
        }
    });
    
    // Экспортируем класс в глобальную область
    window.Select2Field = Select2Field;
    
})(window, document);