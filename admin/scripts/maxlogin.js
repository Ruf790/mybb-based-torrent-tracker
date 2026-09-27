// maxlogin.js — вынесено из maxlogin.php

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
                return { success: false, error: 'Request failed' };
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
                    Swal.fire('Error!', response.error || 'Unknown error', 'error');
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
                    Swal.fire('Error!', response.error || 'Search failed', 'error');
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
                totalCountEl.textContent = count + ' records';
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
                        totalCountEl.textContent = response.count + ' records';
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
                const actionText = action === 'ban' ? 'ban' : 'unban';
                
                Swal.fire({
                    title: 'Are you sure?',
                    html: 'Do you want to <strong>' + actionText + '</strong> IP <code>' + ip + '</code>?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, ' + actionText + ' it!',
                    cancelButtonText: 'Cancel',
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
                                    title: 'Success!',
                                    text: response.data.ip + ' has been ' + 
                                          (response.data.is_banned ? 'banned' : 'unbanned'),
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                
                                updateFilterInfo();
                            } else {
                                Swal.fire('Error!', response.error || 'Operation failed', 'error');
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
                    title: 'Delete Attempt',
                    html: 'Are you sure you want to delete attempt from IP <code>' + ip + '</code>?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
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
                                    title: 'Deleted!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire('Error!', response.error || 'Delete failed', 'error');
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
                info.push('Search: "' + searchTerm + '"');
            }
            
            if (bannedFilter !== 'all') {
                info.push('Status: ' + (bannedFilter === 'yes' ? 'Banned' : 'Active'));
            }
            
            if (typeFilter !== 'all') {
                info.push('Type: ' + (typeFilter === 'login' ? 'Login' : 'Recovery'));
            }
            
            filterInfoEl.innerHTML = info.length > 0 ? 
                '<i class="fas fa-filter me-1"></i>' + info.join(' • ') : 
                'Showing all records';
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
                .catch(() => ({success: false, error: 'Request failed'}))
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
                        Swal.fire('Error', res.error || 'Failed to load', 'error');
                    }
                    logUpdateCount();
                });
            }

            function logUpdateCount(n) {
                if (!logCount) return;
                if (n !== undefined) { logCount.textContent = n + ' records'; return; }
                logReq('log_ajax_get_count').then(res => {
                    if (res.success) logCount.textContent = res.count + ' records';
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
                const labels = {all: 'ALL records', fail: 'all FAILED records', success: 'all SUCCESS records', suspicious: 'all SUSPICIOUS records'};
                Swal.fire({
                    title: 'Delete ' + labels[scope] + '?',
                    html: 'This action <strong>cannot be undone</strong>.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete',
                    reverseButtons: true
                }).then(result => {
                    if (!result.isConfirmed) return;
                    logReq('log_ajax_delete_all', {scope}).then(res => {
                        if (res.success) {
                            logPage = 1;
                            logUpdate();
                            Swal.fire({icon: 'success', title: 'Deleted!', text: res.message, timer: 2000, showConfirmButton: false});
                        } else {
                            Swal.fire('Error', res.error || 'Delete failed', 'error');
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
                        title: 'Delete log entry?',
                        html: 'Remove record <strong>#' + id + '</strong> from IP <code>' + ip + '</code>?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Yes, delete',
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
                                Swal.fire({icon:'success', title:'Deleted!', text: res.message, timer:2000, showConfirmButton:false});
                            } else {
                                Swal.fire('Error', res.error || 'Delete failed', 'error');
                            }
                        });
                    });
                }
            });

            // Init
            setTimeout(() => { logUpdate(); logUpdateCount(); }, 150);
        });
