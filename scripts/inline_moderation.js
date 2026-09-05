var inlineModeration = {
    init: function() {
        if(!inlineType || !inlineId) {
            return false;
        }

        inlineModeration.cookieName = 'inlinemod_' + inlineType + inlineId;

        // Обе формы регистрируются здесь же, в основном теле init(),
        // которое точно выполняется — раньше часть кода жила внутри
        // вложенного document.addEventListener('DOMContentLoaded', ...),
        // а сам init() уже вызывается через такой же слушатель на то же
        // событие. DOMContentLoaded срабатывает один раз за всю загрузку
        // страницы — он уже случился к моменту вызова init(), значит
        // вложенный слушатель на то же событие никогда не сработает.

        // Форма на странице треда (showthread.php) — управление постами
        // (Delete/Merge/Approve). Выбор инструмента в select меняет форму
        // и триггерит её submit программно.
        var optionsSelector = document.getElementById("inlinemoderation_options_selector");
        if(optionsSelector) {
            optionsSelector.addEventListener('change', function() {
                var form = document.getElementById("inlinemoderation_options");
                if(form) {
                    // requestSubmit() — современная замена
                    // dispatchEvent(new Event('submit')): корректно
                    // запускает настоящую отправку формы (с проверкой
                    // валидности и т.д.), в отличие от синтетического
                    // события, которое браузеры уже помечают как
                    // deprecated и планируют вовсе перестать поддерживать.
                    form.requestSubmit();
                }
            });

            var optionsForm = document.getElementById("inlinemoderation_options");
            if(optionsForm) {
                optionsForm.addEventListener('submit', function() {
                    if(optionsSelector.value == "") {
                        toast(lang.select_tool, 'error');
                        return false;
                    } else if(document.querySelectorAll('input[name^="inlinemod_"]:checked').length === 0) {
                        toast(lang.selected_nil, 'error');
                        return false;
                    }

                    inlineModeration.bridgeAllToServerCookies();
                });
            }
        }

        // Форма на странице списка тредов (forumdisplay.php) называется
        // inlinemoderation_threads, отправляется обычной submit-кнопкой
        // ("Go"), без JS-диспатча.
        var threadsForm = document.getElementById("inlinemoderation_threads");
        if(threadsForm) {
            threadsForm.addEventListener('submit', function() {
                inlineModeration.bridgeAllToServerCookies();
            });
        }

        var inputs = document.querySelectorAll('input');

        if(!inputs.length) {
            return false;
        }

        var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
        var removedIds = inlineModeration.getCookie(inlineModeration.cookieName + '_removed');
        var allChecked = true;

        inputs.forEach(function(element) {
            if((element.name != 'allbox') && (element.type == 'checkbox') && (element.id) && (element.id.split('_')[0] == 'inlinemod')) {
                element.addEventListener('click', inlineModeration.checkItem);
            }

            if(element.id) {
                var inlineCheck = element.id.split('_');
                var id = inlineCheck[1];

                if(inlineCheck[0] == 'inlinemod') {
                    if(inlineIds.indexOf(id) != -1 || (inlineIds.indexOf('ALL') != -1 && removedIds.indexOf(id) == -1)) {
                        element.checked = true;
                        var post = element.closest('.post');
                        var thread = element.closest('.inline_row');
                        var fieldset = element.closest('fieldset');
                        
                        if(post) {
                            post.classList.add('trow_selected');
                        } else if(thread) {
                            thread.classList.add('trow_selected');
                        }
                        
                        if(fieldset) {
                            fieldset.classList.add('inline_selected');
                        }
                    } else {
                        element.checked = false;
                        var post = element.closest('.post');
                        var thread = element.closest('.inline_row');
                        
                        if(post) {
                            post.classList.remove('trow_selected');
                        } else if(thread) {
                            thread.classList.remove('trow_selected');
                        }
                    }
                    allChecked = false;
                }
            }
        });

        inlineModeration.updateCookies(inlineIds, removedIds);

        if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0) {
            var allSelectedRow = document.getElementById('allSelectedrow');
            if(allSelectedRow) {
                allSelectedRow.style.display = 'block';
            }
        } else if(inlineIds.indexOf('ALL') == -1 && allChecked == true) {
            var selectRow = document.getElementById('selectAllrow');
            if(selectRow) {
                selectRow.style.display = 'block';
            }
        }
        return true;
    },

    checkItem: function() {
        var element = this;

        if(!element || !element.id) {
            return false;
        }

        var inlineCheck = element.id.split('_');
        var id = inlineCheck[1];

        if(!id) {
            return false;
        }

        var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
        var removedIds = inlineModeration.getCookie(inlineModeration.cookieName + '_removed');

        if(element.checked == true) {
            if(inlineIds.indexOf('ALL') == -1) {
                inlineIds = inlineModeration.addId(inlineIds, id);
            } else {
                removedIds = inlineModeration.removeId(removedIds, id);
                if(removedIds.length == 0) {
                    var allSelectedRow = document.getElementById('allSelectedrow');
                    if(allSelectedRow) {
                        allSelectedRow.style.display = 'block';
                    }
                }
            }
            var post = element.closest('.post');
            var thread = element.closest('.inline_row');
            
            if(post) {
                post.classList.add('trow_selected');
            } else if(thread) {
                thread.classList.add('trow_selected');
            }
        } else {
            if(inlineIds.indexOf('ALL') == -1) {
                inlineIds = inlineModeration.removeId(inlineIds, id);
                var selectRow = document.getElementById('selectAllrow');
                if(selectRow) {
                    selectRow.style.display = 'none';
                }
            } else {
                removedIds = inlineModeration.addId(removedIds, id);
                var allSelectedRow = document.getElementById('allSelectedrow');
                if(allSelectedRow) {
                    allSelectedRow.style.display = 'none';
                }
            }
            var post = element.closest('.post');
            var thread = element.closest('.inline_row');
            
            if(post) {
                post.classList.remove('trow_selected');
            } else if(thread) {
                thread.classList.remove('trow_selected');
            }
        }

        inlineModeration.updateCookies(inlineIds, removedIds);
        return true;
    },

    clearChecked: function() {
        var selectRow = document.getElementById('selectAllrow');
        if(selectRow) selectRow.style.display = 'none';
        
        var allSelectedRow = document.getElementById('allSelectedrow');
        if(allSelectedRow) allSelectedRow.style.display = 'none';

        var inputs = document.querySelectorAll('input');

        if(!inputs.length) {
            return false;
        }

        inputs.forEach(function(element) {
            if(!element.value) return;
            if(element.type == 'checkbox' && ((element.id && element.id.split('_')[0] == 'inlinemod') || element.name == 'allbox')) {
                element.checked = false;
            }
        });

        document.querySelectorAll('.trow_selected').forEach(function(element) {
            element.classList.remove('trow_selected');
        });

        document.querySelectorAll('fieldset.inline_selected').forEach(function(element) {
            element.classList.remove('inline_selected');
        });

        var inlineGo = document.getElementById('inline_go');
        if(inlineGo) inlineGo.value = go_text + ' (0)';
        
        localStorage.removeItem(inlineModeration.cookieName);
        localStorage.removeItem(inlineModeration.cookieName + '_removed');

        return true;
    },

    checkAll: function(master) {
        var inputs = document.querySelectorAll('input');

        if(!inputs.length) {
            return false;
        }

        var inlineIds = inlineModeration.getCookie(inlineModeration.cookieName);
        var removedIds = inlineModeration.getCookie(inlineModeration.cookieName + '_removed');

        inputs.forEach(function(element) {
            if(!element.value || !element.id) return;
            
            var inlineCheck = element.id.split('_');
            if((element.name != 'allbox') && (element.type == 'checkbox') && (inlineCheck[0] == 'inlinemod')) {
                var id = inlineCheck[1];
                var changed = (element.checked != master.checked);

                var post = element.closest('.post');
                var fieldset = element.closest('fieldset');
                var thread = element.closest('.inline_row');
                
                if(post) {
                    if(master.checked == true) {
                        post.classList.add('trow_selected');
                    } else {
                        post.classList.remove('trow_selected');
                    }
                } else if(thread) {
                    if(master.checked == true) {
                        thread.classList.add('trow_selected');
                    } else {
                        thread.classList.remove('trow_selected');
                    }
                }
                
                if(fieldset) {
                    if(master.checked == true) {
                        fieldset.classList.add('inline_selected');
                    } else {
                        fieldset.classList.remove('inline_selected');
                    }
                }

                if(changed) {
                    element.click();
                    
                    if(master.checked == true) {
                        if(inlineIds.indexOf('ALL') == -1) {
                            inlineIds = inlineModeration.addId(inlineIds, id);
                        } else {
                            removedIds = inlineModeration.removeId(removedIds, id);
                        }
                    } else {
                        if(inlineIds.indexOf('ALL') == -1) {
                            inlineIds = inlineModeration.removeId(inlineIds, id);
                        } else {
                            removedIds = inlineModeration.addId(removedIds, id);
                        }
                    }
                }
            }
        });

        var count = inlineModeration.updateCookies(inlineIds, removedIds);

        if(count < all_text) {
            var selectRow = document.getElementById('selectAllrow');
            if(selectRow) {
                if(master.checked == true) {
                    selectRow.style.display = 'block';
                } else {
                    selectRow.style.display = 'none';
                }
            }
        }

        if(inlineIds.indexOf('ALL') == -1 || removedIds.length != 0) {
            var allSelectedRow = document.getElementById('allSelectedrow');
            if(allSelectedRow) allSelectedRow.style.display = 'none';
        } else if(inlineIds.indexOf('ALL') != -1 && removedIds.length == 0) {
            var allSelectedRow = document.getElementById('allSelectedrow');
            if(allSelectedRow) allSelectedRow.style.display = 'block';
        }
    },

    selectAll: function() {
        inlineModeration.updateCookies(['ALL'], []);

        var selectRow = document.getElementById('selectAllrow');
        if(selectRow) selectRow.style.display = 'none';
        
        var allSelectedRow = document.getElementById('allSelectedrow');
        if(allSelectedRow) allSelectedRow.style.display = 'block';
    },

    getCookie: function(name) {
        // localStorage не умеет сам истекать по времени, как умела кука
        // (была на час) — храним метку времени вместе с данными и сами
        // проверяем её при чтении, чтобы забытый выбор не висел вечно.
        var raw = localStorage.getItem(name);
        var ids = [];
        if (raw) {
            var parts = raw.split('|');
            var savedAt = parseInt(parts[0], 10);
            var isExpired = !savedAt || (Date.now() - savedAt) > 60 * 60 * 1000;

            if (isExpired) {
                localStorage.removeItem(name);
            } else {
                for (var i = 1; i < parts.length; i++) {
                    if (parts[i] != '' && parts[i] != null) {
                        ids.push(parts[i]);
                    }
                }
            }
        }
        return ids;
    },

    setCookie: function(name, array) {
        if (array.length != 0) {
            var data = Date.now() + '|' + array.join('|');
            localStorage.setItem(name, data);
        } else {
            localStorage.removeItem(name);
        }
    },

    // Пишет текущее содержимое localStorage в настоящую куку — нужно
    // только в момент отправки формы, чтобы серверный getids() (который
    // читает исключительно $mybb->cookies[...]) увидел актуальный выбор.
    // Кука живёт минуту — этого достаточно, чтобы пережить сам запрос.
    bridgeToServerCookie: function(name) {
        var ids = inlineModeration.getCookie(name);
        var value = ids.length ? ('|' + ids.join('|') + '|') : '';
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=60';
    },

    bridgeAllToServerCookies: function() {
        if (!inlineModeration.cookieName) return;

        // Защита от двойного срабатывания submit на одну и ту же отправку
        // (по неизвестной причине событие иногда стреляет дважды подряд) —
        // без этой защиты второй вызов читал бы уже очищенный localStorage
        // и затирал куку пустым значением прямо перед уходом запроса.
        if (inlineModeration._bridging) return;
        inlineModeration._bridging = true;
        setTimeout(function () { inlineModeration._bridging = false; }, 2000);

        inlineModeration.bridgeToServerCookie(inlineModeration.cookieName);
        inlineModeration.bridgeToServerCookie(inlineModeration.cookieName + '_removed');

        // Раньше, на куках, сервер сам чистил выбор после успешного
        // действия (clearinline()) — но localStorage сервер не видит и не
        // может очистить. Раз данные уже переданы мостом выше, дальше в
        // localStorage они не нужны — иначе после редиректа обратно на
        // страницу выбор "оживёт" снова, хотя действие уже выполнено.
        localStorage.removeItem(inlineModeration.cookieName);
        localStorage.removeItem(inlineModeration.cookieName + '_removed');
    },

    updateCookies: function(inlineIds, removedIds) {
        var count;
        if(inlineIds.indexOf('ALL') != -1) {
            count = all_text - removedIds.length;
        } else {
            count = inlineIds.length;
        }
        
        if(count < 0) {
            count = 0;
        }
        
        var inlineGo = document.getElementById('inline_go');
        if(inlineGo) inlineGo.value = go_text + ' (' + count + ')';
        
        if(count == 0) {
            inlineModeration.clearChecked();
        } else {
            inlineModeration.setCookie(inlineModeration.cookieName, inlineIds);
            inlineModeration.setCookie(inlineModeration.cookieName + '_removed', removedIds);
        }
        return count;
    },

    addId: function(array, id) {
        if(array.indexOf(id) == -1) {
            array.push(id);
        }
        return array;
    },

    removeId: function(array, id) {
        var position = array.indexOf(id);
        if(position != -1) {
            array.splice(position, 1);
        }
        return array;
    }
};

// Инициализация
document.addEventListener('DOMContentLoaded', inlineModeration.init);