// maxlogin.js — вынесено из maxlogin.php
// Strings: AGS_LANG (printed by maxlogin.php from js_* keys of maxlogin.lang.php).
// Translations go into the DOM as text only (textContent / text nodes).

(function () {

    /** Translated string with an English fallback; fills {1}… and %1$s… */
    function t(key, fallback, ...args) {
        const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
        const str = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
        return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
            const i = parseInt(a || b, 10) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }

    /** Element with plain text content. */
    function el(tag, text) {
        const node = document.createElement(tag);
        node.textContent = text;
        return node;
    }

    /**
     * Translated string as a DOM fragment (for Swal `html`): text parts become
     * text nodes, {N} placeholders are replaced by the given nodes/strings.
     */
    function tNode(key, fallback, ...parts) {
        const wrap = document.createElement('div');
        t(key, fallback).split(/(\{\d+\}|%\d+\$s)/).forEach(function (seg) {
            const m = seg.match(/^\{(\d+)\}$|^%(\d+)\$s$/);
            if (m) {
                const part = parts[parseInt(m[1] || m[2], 10) - 1];
                if (part !== undefined) {
                    wrap.appendChild(part instanceof Node ? part : document.createTextNode(String(part)));
                    return;
                }
            }
            if (seg !== '') wrap.appendChild(document.createTextNode(seg));
        });
        return wrap;
    }

    document.addEventListener('DOMContentLoaded', function() {
        let searchTimeout;
        let currentPage = 1;
        const cfg = window.maxloginConfig || {};
        let currentOrder = cfg.orderBy || 'added';
        let currentOrderType = cfg.orderType || 'DESC';
        let currentFilterBanned = cfg.filterBanned || 'all';
        let currentFilterType = cfg.filterType || 'all';
        let currentSearchTerm = cfg.searchTerm || '';
        
        // Вспомогательные функции для работы с DOM
        function $(selector) {
            return document.querySelector(selector);
        }
        
        function $$(selector) {
            return document.querySelectorAll(selector);
        }
        
        // Инициализация полей
        const filterBannedEl = $('#filter-banned');
        const filterTypeEl = $('#filter-type');
        const liveSearchEl = $('#live-search');
        
        if (filterBannedEl) filterBannedEl.value = currentFilterBanned;
        if (filterTypeEl) filterTypeEl.value = currentFilterType;
        if (liveSearchEl) liveSearchEl.value = currentSearchTerm;
        
        // Функция для показа загрузки
        function showLoading(show) {
            const spinner = $('#loading-spinner');
            const container = $('#attempts-table-container');
            
            if (spinner) {
                spinner.classList.toggle('d-none', !show);
            }
            
            if (container) {
                container.style.opacity = show ? '0.5' : '1';
            }
        }
        
        // AJAX helper
        function makeRequest(url, data) {
            const myPostKey = document.getElementById('maxloginPostKey')?.value || '';
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ ...data, my_post_key: myPostKey })
            })
            .then(response => response.json())
            .catch(error => {
                console.error('Request failed:', error);
                return { success: false, error: t('request_failed', 'Request failed') };
            });
        }
        
        // Функция для обновления таблицы
        function updateTable() {
            showLoading(true);
            
            const searchTerm = liveSearchEl ? liveSearchEl.value : '';
            const filterBanned = filterBannedEl ? filterBannedEl.value : 'all';
            const filterType = filterTypeEl ? filterTypeEl.value : 'all';
            
            makeRequest('?act=maxlogin&action=ajax_get_page', {
                page: currentPage,
                filter_banned: filterBanned,
                filter_type: filterType,
                search_ip: searchTerm,
                order: currentOrder,
                otype: currentOrderType
            })
            .then(response => {
                if (response.success) {
                    const container = $('#attempts-table-container');
                    if (container) {
                        container.innerHTML = response.html;
                    }
                    updateFilterInfo();
                    updateTotalCount();
                } else {
                    Swal.fire(t('error', 'Error'), response.error || t('unknown_error', 'Unknown error'), 'error');
                }
            })
            .finally(() => {
                showLoading(false);
            });
        }
        
        // Live search с debounce
        if (liveSearchEl) {
            liveSearchEl.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = this.value;
                
                if (searchTerm.length === 0 || searchTerm.length >= 2) {
                    searchTimeout = setTimeout(function() {
                        performLiveSearch(searchTerm);
                    }, 300);
                }
            });
        }
        
        function performLiveSearch(searchTerm) {
            showLoading(true);
            
            const filterBanned = filterBannedEl ? filterBannedEl.value : 'all';
            const filterType = filterTypeEl ? filterTypeEl.value : 'all';
            
            makeRequest('?act=maxlogin&action=ajax_search', {
                search: searchTerm,
                filter_banned: filterBanned,
                filter_type: filterType
            })
            .then(response => {
                if (response.success) {
                    const container = $('#attempts-table-container');
                    if (container) {
                        container.innerHTML = response.html;
                    }
                    updateFilterInfo();
                    updateTotalCount(response.count);
                } else {
                    Swal.fire(t('error', 'Error'), response.error || t('search_failed', 'Search failed'), 'error');
                }
            })
            .finally(() => {
                showLoading(false);
            });
        }
        
        // Обновление общего количества
        function updateTotalCount(count) {
            const totalCountEl = $('#total-count');
            if (!totalCountEl) return;
            
            if (count !== undefined) {
                totalCountEl.textContent = t('records', '{1} records', count);
            } else {
                const searchTerm = liveSearchEl ? liveSearchEl.value : '';
                const filterBanned = filterBannedEl ? filterBannedEl.value : 'all';
                const filterType = filterTypeEl ? filterTypeEl.value : 'all';
                
                makeRequest('?act=maxlogin&action=ajax_get_count', {
                    filter_banned: filterBanned,
                    filter_type: filterType,
                    search_ip: searchTerm
                })
                .then(response => {
                    if (response.success && totalCountEl) {
                        totalCountEl.textContent = t('records', '{1} records', response.count);
                    }
                });
            }
        }
        
        // Очистка поиска
        const clearSearchBtn = $('#clear-search');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                if (liveSearchEl) {
                    liveSearchEl.value = '';
                    liveSearchEl.dispatchEvent(new Event('input'));
                }
            });
        }
        
        // Очистка фильтров
        const clearFiltersBtn = $('#clear-filters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                if (filterBannedEl) filterBannedEl.value = 'all';
                if (filterTypeEl) filterTypeEl.value = 'all';
                if (liveSearchEl) liveSearchEl.value = '';
                currentPage = 1;
                updateTable();
            });
        }
        
        // Обновление по изменению фильтров
        if (filterBannedEl) {
            filterBannedEl.addEventListener('change', function() {
                currentPage = 1;
                updateTable();
            });
        }
        
        if (filterTypeEl) {
            filterTypeEl.addEventListener('change', function() {
                currentPage = 1;
                updateTable();
            });
        }
        
        // Сортировка (делегирование событий)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.sort-header')) {
                e.preventDefault();
                const header = e.target.closest('.sort-header');
                const order = header.dataset.order;
                
                if (currentOrder === order) {
                    currentOrderType = currentOrderType === 'DESC' ? 'ASC' : 'DESC';
                } else {
                    currentOrder = order;
                    currentOrderType = 'DESC';
                }
                
                // Обновляем иконки сортировки
                document.querySelectorAll('.sort-header i').forEach(icon => {
                    icon.classList.remove('fa-sort-up', 'fa-sort-down');
                    icon.classList.add('fa-sort');
                });
                
                const icon = header.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-sort');
                    icon.classList.add(currentOrderType === 'ASC' ? 'fa-sort-up' : 'fa-sort-down');
                }
                
                updateTable();
            }
            
            // Пагинация
            if (e.target.closest('.pagination-page')) {
                e.preventDefault();
                const link = e.target.closest('.pagination-page');
                currentPage = parseInt(link.dataset.page);
                updateTable();
                
                const container = $('#attempts-table-container');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            
            // Обновить таблицу
            if (e.target.closest('#refresh-btn')) {
                e.preventDefault();
                currentPage = 1;
                updateTable();
            }
            
            // AJAX бан/разбан
            if (e.target.closest('.ban-btn')) {
                e.preventDefault();
                const button = e.target.closest('.ban-btn');
                const id = button.dataset.id;
                const action = button.dataset.action;
                const ip = button.dataset.ip;
                const isBan = action === 'ban';
                
                Swal.fire({
                    title: t('confirm_title', 'Are you sure?'),
                    html: isBan
                        ? tNode('confirm_ban', 'Do you want to ban IP {1}?', el('code', ip))
                        : tNode('confirm_unban', 'Do you want to unban IP {1}?', el('code', ip)),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: isBan ? t('yes_ban', 'Yes, ban it!') : t('yes_unban', 'Yes, unban it!'),
                    cancelButtonText: t('cancel', 'Cancel'),
                    confirmButtonColor: action === 'ban' ? '#d33' : '#3085d6',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoading(true);
                        
                        makeRequest('?act=maxlogin&action=ajax_' + action, {
                            id: id,
                            ajax_action: action
                        })
                        .then(response => {
                            if (response.success) {
                                // Обновляем строку в таблице
                                const row = document.getElementById('row-' + id);
                                if (row) {
                                    const statusCell = row.querySelector('.status-cell');
                                    const banBtn = row.querySelector('.ban-btn');
                                    
                                    if (statusCell) {
                                        statusCell.innerHTML = response.data.status_badge;
                                    }
                                    
                                    if (banBtn && response.data.ban_button) {
                                        banBtn.outerHTML = response.data.ban_button;
                                    }
                                }
                                
                                // Показываем уведомление
                                Swal.fire({
                                    icon: 'success',
                                    title: t('success', 'Success!'),
                                    text: response.data.is_banned
                                        ? t('ip_banned', '{1} has been banned', response.data.ip)
                                        : t('ip_unbanned', '{1} has been unbanned', response.data.ip),
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                
                                updateFilterInfo();
                            } else {
                                Swal.fire(t('error', 'Error'), response.error || t('operation_failed', 'Operation failed'), 'error');
                            }
                        })
                        .finally(() => {
                            showLoading(false);
                        });
                    }
                });
            }
            
            // AJAX удаление
            if (e.target.closest('.delete-btn')) {
                e.preventDefault();
                const button = e.target.closest('.delete-btn');
                const id = button.dataset.id;
                const ip = button.dataset.ip;
                
                Swal.fire({
                    title: t('delete_attempt_title', 'Delete attempt'),
                    html: tNode('delete_attempt_confirm', 'Are you sure you want to delete the attempt from IP {1}?', el('code', ip)),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: t('yes_delete', 'Yes, delete'),
                    cancelButtonText: t('cancel', 'Cancel'),
                    confirmButtonColor: '#d33',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoading(true);
                        
                        makeRequest('?act=maxlogin&action=ajax_delete', {
                            id: id
                        })
                        .then(response => {
                            if (response.success) {
                                // Удаляем строку из таблицы
                                const row = document.getElementById('row-' + id);
                                if (row) {
                                    row.style.transition = 'opacity 0.3s';
                                    row.style.opacity = '0';
                                    
                                    setTimeout(() => {
                                        row.remove();
                                        updateTable(); // Обновляем таблицу после удаления
                                    }, 300);
                                }
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: t('deleted', 'Deleted!'),
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire(t('error', 'Error'), response.error || t('delete_failed', 'Delete failed'), 'error');
                            }
                        })
                        .finally(() => {
                            showLoading(false);
                        });
                    }
                });
            }
        });
        
        // Функция обновления информации о фильтрах
        function updateFilterInfo() {
            const filterInfoEl = $('#filter-info');
            if (!filterInfoEl) return;
            
            const bannedFilter = filterBannedEl ? filterBannedEl.value : 'all';
            const typeFilter = filterTypeEl ? filterTypeEl.value : 'all';
            const searchTerm = liveSearchEl ? liveSearchEl.value : '';
            
            let info = [];
            
            if (searchTerm) {
                info.push(t('filter_search', 'Search: "{1}"', searchTerm));
            }
            
            if (bannedFilter !== 'all') {
                info.push(t('filter_status', 'Status: {1}', bannedFilter === 'yes' ? t('banned', 'Banned') : t('active', 'Active')));
            }
            
            if (typeFilter !== 'all') {
                info.push(t('filter_type', 'Type: {1}', typeFilter === 'login' ? t('login', 'Login') : t('recovery', 'Recovery')));
            }
            
            // Text nodes only: the search term is user input
            filterInfoEl.textContent = '';
            if (info.length > 0) {
                const icon = document.createElement('i');
                icon.className = 'fas fa-filter me-1';
                filterInfoEl.appendChild(icon);
                filterInfoEl.appendChild(document.createTextNode(info.join(' • ')));
            } else {
                filterInfoEl.textContent = t('showing_all', 'Showing all records');
            }
        }
        
        // Инициализация
        setTimeout(function() {
            updateTable();
            updateFilterInfo();
            updateTotalCount();
        }, 100);
    });


        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()


        document.addEventListener('DOMContentLoaded', function () {
            let logPage      = 1;
            const logCfg = window.maxloginLogConfig || {};
            let logOrder     = logCfg.orderBy || 'added';
            let logOrderType = logCfg.orderType || 'DESC';
            let logSearch    = '';
            let logStatus    = 'all';
            let logSuspicious = 'all';
            let logTimeout;

            const logContainer = document.getElementById('log-table-container');
            const logSpinner   = document.getElementById('log-spinner');
            const logCount     = document.getElementById('log-total-count');

            function logReq(action, extra) {
                const myPostKey = document.getElementById('maxloginPostKey')?.value || '';
                const params = new URLSearchParams({
                    action,
                    lpage:              logPage,
                    lorder:             logOrder,
                    lotype:             logOrderType,
                    filter_status:      logStatus,
                    filter_suspicious:  logSuspicious,
                    search_log_ip:      logSearch,
                    my_post_key:        myPostKey,
                    ...extra
                });
                if (logSpinner) logSpinner.classList.remove('d-none');
                if (logContainer) logContainer.style.opacity = '0.5';

                return fetch('?act=maxlogin', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params
                })
                .then(r => r.json())
                .catch(() => ({success: false, error: t('request_failed', 'Request failed')}))
                .finally(() => {
                    if (logSpinner) logSpinner.classList.add('d-none');
                    if (logContainer) logContainer.style.opacity = '1';
                });
            }

            function logUpdate() {
                logReq('log_ajax_get_page').then(res => {
                    if (res.success && logContainer) {
                        logContainer.innerHTML = res.html;
                    } else if (!res.success) {
                        Swal.fire(t('error', 'Error'), res.error || t('load_failed', 'Failed to load'), 'error');
                    }
                    logUpdateCount();
                });
            }

            function logUpdateCount(n) {
                if (!logCount) return;
                if (n !== undefined) { logCount.textContent = t('records', '{1} records', n); return; }
                logReq('log_ajax_get_count').then(res => {
                    if (res.success) logCount.textContent = t('records', '{1} records', res.count);
                });
            }

            // Search
            const logSearchEl = document.getElementById('log-search');
            if (logSearchEl) {
                logSearchEl.addEventListener('input', function () {
                    clearTimeout(logTimeout);
                    const v = this.value;
                    logTimeout = setTimeout(() => { logSearch = v; logPage = 1; logUpdate(); }, 350);
                });
            }
            document.getElementById('log-clear-search')?.addEventListener('click', () => {
                if (logSearchEl) logSearchEl.value = '';
                logSearch = ''; logPage = 1; logUpdate();
            });

            // Filters
            document.getElementById('log-filter-status')?.addEventListener('change', function () {
                logStatus = this.value; logPage = 1; logUpdate();
            });
            document.getElementById('log-filter-suspicious')?.addEventListener('change', function () {
                logSuspicious = this.value; logPage = 1; logUpdate();
            });
            document.getElementById('log-refresh')?.addEventListener('click', () => { logPage = 1; logUpdate(); });
            document.getElementById('log-clear-filters')?.addEventListener('click', () => {
                logStatus = 'all'; logSuspicious = 'all'; logSearch = ''; logPage = 1;
                if (logSearchEl) logSearchEl.value = '';
                const ss = document.getElementById('log-filter-status');
                const sp = document.getElementById('log-filter-suspicious');
                if (ss) ss.value = 'all';
                if (sp) sp.value = 'all';
                logUpdate();
            });

            // Delete All
            document.addEventListener('click', function(e) {
                const delAll = e.target.closest('.log-delete-all');
                if (!delAll) return;
                e.preventDefault();
                const scope = delAll.dataset.scope;
                const titles = {
                    all:        t('delete_all_title_all', 'Delete ALL records?'),
                    fail:       t('delete_all_title_fail', 'Delete all FAILED records?'),
                    success:    t('delete_all_title_success', 'Delete all SUCCESSFUL records?'),
                    suspicious: t('delete_all_title_suspicious', 'Delete all SUSPICIOUS records?')
                };
                Swal.fire({
                    title: titles[scope] || titles.all,
                    html: tNode('irreversible', 'This action {1}.', el('strong', t('irreversible_strong', 'cannot be undone'))),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: t('yes_delete', 'Yes, delete'),
                    cancelButtonText: t('cancel', 'Cancel'),
                    reverseButtons: true
                }).then(result => {
                    if (!result.isConfirmed) return;
                    logReq('log_ajax_delete_all', {scope}).then(res => {
                        if (res.success) {
                            logPage = 1;
                            logUpdate();
                            Swal.fire({icon: 'success', title: t('deleted', 'Deleted!'), text: res.message, timer: 2000, showConfirmButton: false});
                        } else {
                            Swal.fire(t('error', 'Error'), res.error || t('delete_failed', 'Delete failed'), 'error');
                        }
                    });
                });
            });

            // Delegation: sort, pagination, delete
            document.addEventListener('click', function (e) {
                // Sort
                const sortHdr = e.target.closest('.log-sort');
                if (sortHdr) {
                    e.preventDefault();
                    const ord = sortHdr.dataset.order;
                    logOrderType = logOrder === ord ? (logOrderType === 'DESC' ? 'ASC' : 'DESC') : 'DESC';
                    logOrder = ord;
                    document.querySelectorAll('.log-sort i').forEach(i => {
                        i.className = 'fas fa-sort';
                    });
                    const icon = sortHdr.querySelector('i');
                    if (icon) icon.className = 'fas fa-sort-' + (logOrderType === 'ASC' ? 'up' : 'down');
                    logPage = 1; logUpdate();
                }

                // Pagination
                const pageLink = e.target.closest('.log-page');
                if (pageLink) {
                    e.preventDefault();
                    logPage = parseInt(pageLink.dataset.page);
                    logUpdate();
                    logContainer?.scrollIntoView({behavior: 'smooth', block: 'start'});
                }

                // Delete
                const delBtn = e.target.closest('.log-delete-btn');
                if (delBtn) {
                    e.preventDefault();
                    const id = delBtn.dataset.id;
                    const ip = delBtn.dataset.ip;
                    Swal.fire({
                        title: t('delete_log_title', 'Delete log entry?'),
                        html: tNode('delete_log_confirm', 'Remove record {1} from IP {2}?', el('strong', '#' + id), el('code', ip)),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        confirmButtonText: t('yes_delete', 'Yes, delete'),
                        cancelButtonText: t('cancel', 'Cancel'),
                        reverseButtons: true
                    }).then(result => {
                        if (!result.isConfirmed) return;
                        logReq('log_ajax_delete', {id}).then(res => {
                            if (res.success) {
                                const row = document.getElementById('log-row-' + id);
                                if (row) {
                                    row.style.transition = 'opacity 0.3s';
                                    row.style.opacity = '0';
                                    setTimeout(() => { row.remove(); logUpdateCount(); }, 300);
                                }
                                Swal.fire({icon:'success', title: t('deleted', 'Deleted!'), text: res.message, timer:2000, showConfirmButton:false});
                            } else {
                                Swal.fire(t('error', 'Error'), res.error || t('delete_failed', 'Delete failed'), 'error');
                            }
                        });
                    });
                }
            });

            // Init
            setTimeout(() => { logUpdate(); logUpdateCount(); }, 150);
        });

})();
