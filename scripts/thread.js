var Thread = {
    init: function() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                Thread.initialize();
            });
        } else {
            Thread.initialize();
        }
    },

    initialize: function() {
        Thread.initQuickReply();
        Thread.initMultiQuote();
        Thread.showQuoteButtons();

        if(thread_deleted == "1") {
            Thread.hideElements("#quick_reply_form, .new_reply_button, .thread_tools, .inline_rating");
            var option = document.querySelector("#moderator_options_selector option.option_mirage");
            if(option) option.disabled = true;
        }

        visible_replies = parseInt(visible_replies, 10);
        Thread.splitToolHandler();
        
        var moderatorSelector = document.getElementById("moderator_options_selector");
        if(moderatorSelector) {
            moderatorSelector.addEventListener('change', function() {
                var moderatorForm = document.getElementById("moderator_options");
                if(moderatorForm) {
                    moderatorForm.dispatchEvent(new Event('submit'));
                }
            });

            var moderatorForm = document.getElementById("moderator_options");
            if(moderatorForm) {
                moderatorForm.addEventListener('submit', function(e){
                    if(moderatorSelector.value == "") {
                        if(typeof showToast !== 'undefined') {
                            showToast('Select tool', 'warning');
                        } else {
                        }
                        e.preventDefault();
                        return false;
                    }
                });
            }
        }
        
        var spinnerImg = document.querySelector('#quickreply_spinner img');
        if(spinnerImg) spinnerImg.src = spinner_image;
    },

    // ПОЛНОСТЬЮ ПЕРЕПИСАННАЯ ИНИЦИАЛИЗАЦИЯ БЫСТРОГО ОТВЕТА
    initQuickReply: function() {
        var quickReplyForm = document.getElementById('quick_reply_form');
        if(quickReplyForm && use_xmlhttprequest == 1) {
            
            // Удаляем стандартный обработчик отправки формы
            quickReplyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                return Thread.quickReply(e);
            });

            // Обработчик для кнопки отправки
            var quickReplySubmit = document.getElementById('quick_reply_submit');
            if(quickReplySubmit) {
                quickReplySubmit.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return Thread.quickReply(e);
                });
            }
        }
    },

    // ПОЛНОСТЬЮ ПЕРЕПИСАННЫЙ МЕТОД БЫСТРОГО ОТВЕТА
    quickReply: function(e) {
        
        if(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Защита от множественных отправок
        if(this.quick_replying) {
            return false;
        }

        this.quick_replying = true;

        var quickReplyForm = document.getElementById('quick_reply_form');
        if(!quickReplyForm) {
            console.error('Quick reply form not found');
            this.quick_replying = false;
            return false;
        }
        
        // Проверяем сообщение
        var messageElement = document.getElementById('message');
        if(!messageElement || messageElement.value.trim() === '') {
            if(typeof showToast !== 'undefined') {
                showToast('Please enter a message', 'warning');
            }
            this.quick_replying = false;
            return false;
        }
        
        // Отключаем кнопку отправки
        var submitButton = document.getElementById('quick_reply_submit');
        if(submitButton) {
            submitButton.disabled = true;
            var originalText = submitButton.value;
            submitButton.value = 'Posting...';
        }

        // Подготавливаем данные формы
        var formData = new FormData(quickReplyForm);
        
        // Добавляем AJAX параметры
        formData.append('ajax', '1');
        formData.append('my_post_key', my_post_key);
        formData.append('random_seed', Math.random().toString(36).substring(2, 15));


        // Показываем спиннер
        var qreply_spinner = document.getElementById('quickreply_spinner');
        if(qreply_spinner) {
            qreply_spinner.style.display = 'block';
        }

        // Отправляем запрос
        fetch('newreply.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            Thread.quickReplyDone(data);
        })
        .catch(error => {
            console.error('Quick reply error:', error);
            if(typeof showToast !== 'undefined') {
                showToast('Error sending reply: ' + error.message, 'error');
            }
        })
        .finally(() => {
            // Всегда скрываем спиннер и восстанавливаем кнопку
            if(qreply_spinner) {
                qreply_spinner.style.display = 'none';
            }
            if(submitButton) {
                submitButton.disabled = false;
                submitButton.value = originalText || 'Post Reply';
            }
            this.quick_replying = false;
        });

        return false;
    },

    // ИСПРАВЛЕННАЯ ОБРАБОТКА ОТВЕТА
    quickReplyDone: function(json) {

        if(typeof json == 'object' && json.hasOwnProperty("errors")) {
            json.errors.forEach(function(message) {
                if(typeof showToast !== 'undefined') {
                    showToast('Quick reply error: ' + message, 'error');
                }
            });
            return false;
        }

        // Проверяем на ошибку дублирования поста
        if(json.data && (json.data.includes('error_post_already_submitted') || json.message && json.message.includes('error_post_already_submitted'))) {
            if(typeof showToast !== 'undefined') {
                showToast('Post was already submitted. Please wait...', 'warning');
            }
            console.warn('Duplicate post detected in response');
            return false;
        }

        if(json.data && json.data.match(/id="post_([0-9]+)"/)) {
            var pid = json.data.match(/id="post_([0-9]+)"/)[1];
            
            // Добавляем новый пост на страницу
            var postsContainer = document.getElementById('posts');
            if(postsContainer) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = json.data;
                
                while(tempDiv.firstChild) {
                    postsContainer.appendChild(tempDiv.firstChild);
                }
            }

            // Обновляем счетчик
            ++visible_replies;
            Thread.splitToolHandler();
            
            // Инициализируем модерацию для нового поста
            var inlineModCheck = document.getElementById("inlinemod_" + pid);
            if (inlineModCheck && typeof inlineModeration !== 'undefined') {
                inlineModCheck.addEventListener('change', inlineModeration.checkItem);
            }

            // Очищаем форму
            var quickReplyForm = document.getElementById('quick_reply_form');
            if(quickReplyForm) {
                quickReplyForm.reset();
                
                var quotedIds = document.getElementById('quoted_ids');
                if(quotedIds) quotedIds.value = '';
                
            }
            
            // Обновляем lastpid
            var lastpid = document.getElementById('lastpid');
            if(lastpid) {
                lastpid.value = pid;
            }
            
            // Успешное уведомление
            if(typeof showToast !== 'undefined') {
                showToast('Reply posted successfully!', 'success');
            }
            
            // Переинициализируем интерфейс
            setTimeout(function() {
                Thread.showQuoteButtons();
                Thread.clearMultiQuoted();
            }, 100);
        } else {
            console.warn('No valid post data found in response');
            if(typeof showToast !== 'undefined') {
                showToast('No post data received', 'error');
            }
        }

        // Выполняем JavaScript из ответа
        if(json.data) {
            var scripts = json.data.match(/<script\b[^>]*>([\s\S]*?)<\/script>/gi);
            if(scripts) {
                scripts.forEach(function(script) {
                    var code = script.replace(/<script\b[^>]*>|<\/script>/gi, '');
                    try { 
                        eval(code); 
                    } catch(e) { 
                        console.error('Error executing script:', e); 
                    }
                });
            }
        }

        return true;
    },

    // УДАЛЕНИЕ ДУБЛИРУЮЩИХСЯ ЦИТАТ
    removeDuplicateQuotes: function(text) {
        // Разделяем текст на цитаты
        var quoteRegex = /(\[quote="[^"]+" pid='\d+' dateline='\d+'\]\s*[\s\S]*?\[\/quote\])/g;
        var quotes = text.match(quoteRegex);
        
        if (!quotes || quotes.length <= 1) {
            return text; // Если только одна цитата или нет цитат, возвращаем как есть
        }
        
        // Удаляем дубликаты
        var uniqueQuotes = [];
        var seenQuotes = new Set();
        
        quotes.forEach(function(quote) {
            // Нормализуем пробелы для сравнения
            var normalizedQuote = quote.replace(/\s+/g, ' ').trim();
            if (!seenQuotes.has(normalizedQuote)) {
                seenQuotes.add(normalizedQuote);
                uniqueQuotes.push(quote);
            }
        });
        
        // Если осталась только одна уникальная цитата, возвращаем ее
        if (uniqueQuotes.length === 1) {
            return uniqueQuotes[0].trim();
        }
        
        // Иначе объединяем уникальные цитаты
        return uniqueQuotes.join('\n\n').trim();
    },

    // MULTIQUOTE
    multiQuote: function(pid) {
        
        pid = parseInt(pid);
        if(isNaN(pid)) {
            console.error('Invalid pid:', pid);
            return false;
        }

        let new_post_ids = [];
        const quoted = this.getCookie("multiquote");
        let is_new = true;
        let deleted = false;
        
        const postElement = document.getElementById(`pid${pid}`);
        if(postElement) {
            const nextPost = postElement.nextElementSibling;
            if(nextPost && nextPost.classList.contains('deleted_post')) {
                if(typeof showToast !== 'undefined') {
                    showToast('This post has been deleted', 'error');
                }
                deleted = true;
            }
        }

        if(quoted && !deleted) {
            const post_ids = quoted.split("|");

            post_ids.forEach(post_id => {
                const numPostId = parseInt(post_id);
                if(!isNaN(numPostId) && numPostId !== pid && post_id !== '') {
                    new_post_ids.push(numPostId.toString());
                } else if(numPostId === pid) {
                    is_new = false;
                }
            });
        }

        const mquoteLink = document.querySelector(`#multiquote_${pid}`)?.closest('a');
        if(is_new && !deleted) {
            new_post_ids.push(pid.toString());
            if(mquoteLink) {
                mquoteLink.classList.remove('postbit_multiquote');
                mquoteLink.classList.add('postbit_multiquote_on');
            }
            if(typeof showToast !== 'undefined') {
                showToast('Post added to multi-quote', 'success');
            }
        } else if(mquoteLink) {
            mquoteLink.classList.remove('postbit_multiquote_on');
            mquoteLink.classList.add('postbit_multiquote');
            if(typeof showToast !== 'undefined') {
                showToast('Post removed from multi-quote', 'info');
            }
        }

        const mquoteQuick = document.getElementById('quickreply_multiquote');
        if(mquoteQuick) {
            if(new_post_ids.length) {
                mquoteQuick.style.display = 'block';
                // Заменяем обработчик чтобы избежать дублирования
                mquoteQuick.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    Thread.loadMultiQuoted();
                    return false;
                };
            } else {
                mquoteQuick.style.display = 'none';
            }
        }
        
        this.setCookie("multiquote", new_post_ids.join("|"));
        return false;
    },

    // ЗАГРУЗКА MULTIQUOTE
    loadMultiQuoted: function() {
        
        // Защита от множественных вызовов
        if (this.loadingMultiQuote) {
            return false;
        }
        
        this.loadingMultiQuote = true;
        
        var mquote_spinner = document.getElementById('quickreply_spinner');
        if(mquote_spinner) mquote_spinner.style.display = 'block';

        fetch('xmlhttp.php?action=get_multiquoted&load_all=1')
            .then(response => response.json())
            .then(data => {
                Thread.insertMultiQuotedToQuickReply(data);
                if(mquote_spinner) mquote_spinner.style.display = 'none';
                this.loadingMultiQuote = false;
            })
            .catch(error => {
                console.error('Error:', error);
                if(mquote_spinner) mquote_spinner.style.display = 'none';
                if(typeof showToast !== 'undefined') {
                    showToast('Error loading multi-quote', 'error');
                }
                this.loadingMultiQuote = false;
            });

        return false;
    },

    // ВСТАВКА MULTIQUOTE
    insertMultiQuotedToQuickReply: function(json) {
        if(typeof json == 'object' && json.hasOwnProperty("errors")) {
            json.errors.forEach(function(message) {
                if(typeof showToast !== 'undefined') {
                    showToast('Multi-quote error: ' + message, 'error');
                }
            });
            return false;
        }

        if(json && json.message) {
            var messageElement = document.getElementById('message');
            if(messageElement) {
                var quoteText = json.message.trim();
                
                // Удаляем дублирующиеся цитаты
                quoteText = Thread.removeDuplicateQuotes(quoteText);
                
                // Проверяем, не содержится ли уже такой текст
                if (messageElement.value.includes(quoteText)) {
                    if(typeof showToast !== 'undefined') {
                        showToast('Multi-quote already exists in message', 'info');
                    }
                    return false;
                }
                
                if(messageElement.value.trim() !== '') {
                    quoteText = '\n\n' + quoteText;
                }

                var startPos = messageElement.selectionStart;
                var endPos = messageElement.selectionEnd;
                var currentValue = messageElement.value;
                
                messageElement.value = currentValue.substring(0, startPos) + 
                                      quoteText + 
                                      currentValue.substring(endPos);
                
                var newPos = startPos + quoteText.length;
                messageElement.setSelectionRange(newPos, newPos);

                messageElement.focus();
                
                var quotedIds = document.getElementById('quoted_ids');
                if(quotedIds) quotedIds.value = 'all';

                if(typeof showToast !== 'undefined') {
                    showToast('Multi-quote added to quick reply', 'success');
                }
            }
        }

        Thread.clearMultiQuoted();
        var quickquote = document.getElementById('quickreply_multiquote');
        if(quickquote) quickquote.style.display = 'none';
    },

    // ПОКАЗ КНОПОК ЦИТИРОВАНИЯ
    showQuoteButtons: function() {
        
        setTimeout(function() {
            var quoteSelectors = [
                'a[href*="newreply.php"][href*="quotedpid"]',
                'a.postbit_quote', 
                '.postbit_quote a',
                'a[onclick*="quote"]',
                '.post_controls a[href*="quote"]',
                '.postbit_buttons a[href*="quote"]'
            ];
            
            var quoteButtons = [];
            quoteSelectors.forEach(function(selector) {
                var found = document.querySelectorAll(selector);
                found.forEach(function(button) {
                    quoteButtons.push(button);
                });
            });
            
            var multiQuoteSelectors = [
                '.postbit_multiquote',
                '.postbit_multiquote_on', 
                'a[onclick*="multiQuote"]',
                '.post_controls a[onclick*="multiQuote"]',
                '.postbit_buttons a[onclick*="multiQuote"]'
            ];
            
            var multiQuoteButtons = [];
            multiQuoteSelectors.forEach(function(selector) {
                var found = document.querySelectorAll(selector);
                found.forEach(function(button) {
                    multiQuoteButtons.push(button);
                });
            });
            
            // Показываем кнопки
            quoteButtons.forEach(function(button) {
                button.style.display = 'inline-block';
                button.style.visibility = 'visible';
                button.style.opacity = '1';
                button.classList.remove('hidden');
                button.removeAttribute('hidden');
            });
            
            multiQuoteButtons.forEach(function(button) {
                button.style.display = 'inline-block';
                button.style.visibility = 'visible';
                button.style.opacity = '1';
                button.classList.remove('hidden');
                button.removeAttribute('hidden');
            });
            
            
        }, 100);
    },

    // ИНИЦИАЛИЗАЦИЯ MULTIQUOTE
    initMultiQuote: function() {
        var quoted = Thread.getCookie('multiquote');
        if(quoted) {
            var post_ids = quoted.split("|");

            post_ids.forEach(function(value) {
                var mquote_a = document.querySelector("#multiquote_" + value);
                if(mquote_a) {
                    mquote_a.classList.remove('postbit_multiquote');
                    mquote_a.classList.add('postbit_multiquote_on');
                }
            });

            var mquote_quick = document.getElementById('quickreply_multiquote');
            if(mquote_quick) {
                mquote_quick.style.display = 'block';
                mquote_quick.onclick = function(e) {
                    e.preventDefault();
                    Thread.loadMultiQuoted();
                    return false;
                };
            }
        }
        return true;
    },

    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    hideElements: function(selector) {
        try {
            document.querySelectorAll(selector).forEach(function(el) {
                el.style.display = 'none';
            });
        } catch(e) {
        }
    },

    clearMultiQuoted: function() {
        var quickquote = document.getElementById('quickreply_multiquote');
        if(quickquote) quickquote.style.display = 'none';
        
        var quoted = Thread.getCookie("multiquote");
        if(quoted) {
            var post_ids = quoted.split("|");
            post_ids.forEach(function(post_id) {
                var mquote_a = document.querySelector("#multiquote_" + post_id);
                if(mquote_a) {
                    mquote_a.classList.remove('postbit_multiquote_on');
                    mquote_a.classList.add('postbit_multiquote');
                }
            });
        }
        Thread.deleteCookie('multiquote');
    },

    splitToolHandler: function() {
        if(thread_deleted !== "1") {
            var moderatorSelector = document.getElementById("moderator_options_selector");
            if(moderatorSelector) {
                var splitTool = moderatorSelector.querySelector("option[value=split]");
                if(splitTool) {
                    if(visible_replies > 0) {
                        splitTool.disabled = false;
                    } else {
                        splitTool.disabled = true;
                    }
                }
            }
        }
    },

    // РАБОТА С COOKIES
    getCookie: function(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    },

    setCookie: function(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
    },

    deleteCookie: function(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
    },

};

// Инициализация
setTimeout(function() {
    try {
        Thread.init();
    } catch(e) {
        console.error('Error initializing Thread:', e);
    }
}, 100);