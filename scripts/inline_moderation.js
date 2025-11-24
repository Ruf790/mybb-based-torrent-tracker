var inlineModeration = {
    init: function() {
        document.addEventListener('DOMContentLoaded', function() {
            var optionsSelector = document.getElementById("inlinemoderation_options_selector");
            if(optionsSelector) {
                optionsSelector.addEventListener('change', function() {
                    var form = document.getElementById("inlinemoderation_options");
                    if(form) {
                        form.dispatchEvent(new Event('submit'));
                    }
                });

                var form = document.getElementById("inlinemoderation_options");
                if(form) {
                    form.addEventListener('submit', function() {
                        if(optionsSelector.value == "") {
                            toast(lang.select_tool, 'error');
                            return false;
                        } else if(document.querySelectorAll('input[name^="inlinemod_"]:checked').length === 0) {
                            toast(lang.selected_nil, 'error');
                            return false;
                        }
                    });
                }
            }
        });
        
        if(!inlineType || !inlineId) {
            return false;
        }

        inlineModeration.cookieName = 'inlinemod_' + inlineType + inlineId;
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
        
        Cookie.unset(inlineModeration.cookieName);
        Cookie.unset(inlineModeration.cookieName + '_removed');

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
        var inlineCookie = Cookie.get(name);

        var ids = [];
        if(inlineCookie) {
            var inlineIds = inlineCookie.split('|');
            inlineIds.forEach(function(item) {
                if(item != '' && item != null) {
                    ids.push(item);
                }
            });
        }
        return ids;
    },

    setCookie: function(name, array) {
        if(array.length != 0) {
            var data = '|' + array.join('|') + '|';
            Cookie.set(name, data, 60 * 60 * 1000);
        } else {
            Cookie.unset(name);
        }
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