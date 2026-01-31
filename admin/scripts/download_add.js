// Configuration
const CONFIG = {
    maxIndividual: 1000,
    maxBulk: 50,
    minGB: 1,
    apiEndpoint: 'index.php?act=downloadadd', // Убедитесь что это правильный URL
    currentUser: 'Admin'
};

// Глобальные функции для быстрых кнопок
window.setIndividualAmount = function(gb) {
    try {
        const input = document.getElementById('downloaded');
        const range = document.getElementById('gbRange');
        
        if (!input || !range) {
            console.error('Elements not found');
            return;
        }
        
        // Преобразуем в число
        gb = parseInt(gb);
        if (isNaN(gb)) {
            console.error('Invalid GB value:', gb);
            return;
        }
        
        // Применяем ограничения
        const min = CONFIG.minGB;
        const max = CONFIG.maxIndividual;
        const value = Math.max(min, Math.min(gb, max));
        
        // Устанавливаем значения
        input.value = value;
        range.value = value;
        
        // Обновляем отображение
        if (typeof updateBytesDisplay === 'function') {
            updateBytesDisplay();
        }
        
    } catch (error) {
        console.error('Error in setIndividualAmount:', error);
    }
};

window.setBulkAmount = function(gb) {
    try {
        const select = document.getElementById('classamount');
        if (!select) {
            console.error('Classamount select not found');
            return;
        }
        
        // Преобразуем в число
        gb = parseInt(gb);
        if (isNaN(gb)) {
            console.error('Invalid GB value:', gb);
            return;
        }
        
        // Применяем ограничения
        const min = CONFIG.minGB;
        const max = CONFIG.maxBulk;
        const value = Math.max(min, Math.min(gb, max));
        
        // Устанавливаем значение
        select.value = value;
        
        // Обновляем UI
        if (typeof updateAmountProgress === 'function') {
            updateAmountProgress();
        }
        if (typeof calculateTotalImpact === 'function') {
            calculateTotalImpact();
        }
        
    } catch (error) {
        console.error('Error in setBulkAmount:', error);
    }
};












// User search functionality
document.addEventListener('DOMContentLoaded', function() {
    const userSearchInput = document.getElementById('userSearch');
    const searchUserBtn = document.getElementById('searchUser');
    const userResults = document.getElementById('userResults');
    
    if (userSearchInput && searchUserBtn) {
        // Search on button click
        searchUserBtn.addEventListener('click', performUserSearch);
        
        // Search on Enter key
        userSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performUserSearch();
            }
        });
        
        // Live search on typing (optional)
        let searchTimeout;
        userSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            if (this.value.length >= 2) {
                searchTimeout = setTimeout(performUserSearch, 300);
            } else {
                userResults.innerHTML = '<div class="text-muted text-center p-3">Type at least 2 characters...</div>';
            }
        });
    }
});

async function performUserSearch() {
    const searchInput = document.getElementById('userSearch');
    const resultsDiv = document.getElementById('userResults');
    
    if (!searchInput || !resultsDiv) return;
    
    const query = searchInput.value.trim();
    
    if (query.length < 2) {
        resultsDiv.innerHTML = '<div class="alert alert-warning">Please enter at least 2 characters</div>';
        return;
    }
    
    // Show loading
    resultsDiv.innerHTML = `
        <div class="text-center p-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Searching...</span>
            </div>
            <p class="mt-2 text-muted">Searching users...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`index.php?act=downloadadd&action=search_users&query=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.error) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
        }
        
        if (!data.users || data.users.length === 0) {
            resultsDiv.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-user-times fa-2x text-muted mb-3"></i>
                    <p class="text-muted">No users found for "${query}"</p>
                </div>
            `;
            return;
        }
        
        // Display results
        const usersHtml = data.users.map(user => `
            <div class="card mb-2 user-result-item" data-username="${user.username}" style="cursor: pointer;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${user.username}</h6>
                            <small class="text-muted">ID: ${user.id} • Group: ${getGroupName(user.group_id)}</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-primary select-user-btn">
                                <i class="fas fa-check me-1"></i> Select
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        resultsDiv.innerHTML = usersHtml;
        
        // Add click handlers to result items
        document.querySelectorAll('.user-result-item, .select-user-btn').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                const username = this.closest('.user-result-item').dataset.username;
                selectUserFromSearch(username);
            });
        });
        
    } catch (error) {
        console.error('Search error:', error);
        resultsDiv.innerHTML = `<div class="alert alert-danger">Error searching users: ${error.message}</div>`;
    }
}

function selectUserFromSearch(username) {
    // Fill the username in individual form
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.value = username;
        verifyUser(); // Auto-verify the selected user
        
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('userLookupModal'));
        if (modal) modal.hide();
        
        // Focus on the form
        usernameInput.focus();
    }
}



		
// Bulk form confirmation
	// Bulk form confirmation - ИСПРАВЛЕННАЯ ВЕРСИЯ
document.addEventListener('DOMContentLoaded', function() {
    const applyBulkBtn = document.getElementById('applyBulkBtn');
    const bulkForm = document.getElementById('bulkForm');
    const confirmBulkBtn = document.getElementById('confirmBulkAction');
    
    if (applyBulkBtn && bulkForm && confirmBulkBtn) {
        applyBulkBtn.addEventListener('click', function() {
            const groupSelect = document.getElementById('usergroup');
            const amountSelect = document.getElementById('classamount');
            
            // ВАЖНО: Проверяем только количество, группа может быть пустой
            if (!amountSelect.value || parseInt(amountSelect.value) < 1) {
                alert('⚠️ Please select an amount first (1-50 GB)!');
                amountSelect.focus();
                return;
            }
            
            const groupId = groupSelect.value;
            const amountGB = amountSelect.value;
            
            // Название группы
            let groupName = 'all users';
            if (groupId) {
                const selectedOption = groupSelect.options[groupSelect.selectedIndex];
                groupName = selectedOption.text.split('(')[0].trim() || `Group ${groupId}`;
            }
            
            // Информация о пользователях
            let userInfo = 'all active users';
            let impactInfo = '';
            
            if (groupId && currentGroupStats) {
                userInfo = `${currentGroupStats.user_count} users`;
                const totalBytes = amountGB * 1024 * 1024 * 1024 * currentGroupStats.user_count;
                impactInfo = `<div class="mt-3">
                    <small class="text-muted">Total impact: ${formatBytes(totalBytes)}</small>
                </div>`;
            }
			
			
			
			// === УЛУЧШЕННЫЙ ВАРИАНТ warningMessage ===
let warningIcon = '';
let warningTitle = '';
let warningType = 'warning';

if (!groupId) {
    warningIcon = 'fa-radiation';
    warningTitle = 'CRITICAL: System-wide Operation';
    warningType = 'danger';
} else {
    warningIcon = 'fa-exclamation-triangle';
    warningTitle = 'Warning: Group Operation';
    warningType = 'warning';
}

// И используйте в HTML:
let warningMessage = `
    <div class="alert alert-${warningType} mt-3">
        <div class="d-flex align-items-center">
            <i class="fas ${warningIcon} fa-2x me-3"></i>
            <div>
                <h6 class="alert-heading mb-1">${warningTitle}</h6>
                <p class="mb-0">
                    ${!groupId ? 
                      'This action will affect <strong>EVERY</strong> active user in the entire system!' : 
                      'This action affects all users in the selected group and cannot be undone.'}
                </p>
            </div>
        </div>
    </div>
`;
// === КОНЕЦ УЛУЧШЕННОГО КОДА ===
			
			
            
            // Заполняем модалку
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center p-3">
                    <div class="mb-4">
                        <div class="text-warning display-1 mb-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h5 class="fw-bold">Confirm Bulk Action</h5>
                    </div>
                    
                    <div class="alert alert-warning">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Target:</strong></span>
                            <span class="fw-bold">${groupName}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Amount:</strong></span>
                            <span class="fw-bold text-primary">${amountGB} GB</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Affects:</strong></span>
                            <span class="fw-bold">${userInfo}</span>
                        </div>
                    </div>
                    
                    <p class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        This will add ${amountGB} GB to ${userInfo} in ${groupName}
                    </p>
                    ${impactInfo}
					
					
        ${warningMessage}
					
					
					
					
                </div>
            `;
            
            // Показываем модалку
            const modalElement = document.getElementById('confirmationModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            
            // Обработчик подтверждения
            confirmBulkBtn.onclick = function() {
                // Показываем индикаторы
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
                this.disabled = true;
                applyBulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Applying Changes...';
                applyBulkBtn.disabled = true;
                
                // Закрываем модалку
                modal.hide();
                
                // Отправляем форму
                setTimeout(() => {
                    bulkForm.submit();
                }, 300);
            };
        });
    }
});
		
		
		
		
		
		

        // Global state
        let currentGroupId = null;
        let currentGroupStats = null;
        let selectedUser = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initApplication();
            loadRecentActivity();
            setupEventListeners();
        });

        function initApplication() {
            // Setup dark mode
          
            
            // Setup group selector
            initGroupSelector();
            
            // Update initial displays
            updateBytesDisplay();
            updateAmountProgress();
        }

        

        function initGroupSelector() {
            const groupSelect = document.getElementById('usergroup');
            if (groupSelect) {
                groupSelect.addEventListener('change', function() {
                    const groupId = this.value;
                    currentGroupId = groupId;
                    
                    if (groupId) {
                        loadGroupStats(groupId);
                        calculateTotalImpact();
                    } else {
                        hideGroupInfo();
                    }
                });
            }
            
            const amountSelect = document.getElementById('classamount');
            if (amountSelect) {
                amountSelect.addEventListener('change', function() {
                    updateAmountProgress();
                    calculateTotalImpact();
                });
            }
        }

        // User verification functions
        async function verifyUser() {
            const usernameInput = document.getElementById('username');
            const username = usernameInput.value.trim();
            
            if (!username || username.length < 3) {
                alert('Please enter a username (at least 3 characters)');
                return;
            }
            
            const verifyBtn = document.getElementById('verifyUserBtn');
            const userInfo = document.getElementById('userInfo');
            
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            userInfo.style.display = 'none';
            
            try {
                const response = await fetch(`${CONFIG.apiEndpoint}?action=check_user&username=${encodeURIComponent(username)}`);
                const data = await response.json();
                
                if (data.exists) {
                    selectedUser = data.user;
                    displayUserInfo(data.user);
                    verifyBtn.innerHTML = '<i class="fas fa-check text-success"></i> Verified';
                    verifyBtn.classList.remove('btn-outline-secondary');
                    verifyBtn.classList.add('btn-success');
                } else {
                    selectedUser = null;
                    userInfo.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-user-times me-2"></i>
                            User not found or inactive.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    userInfo.style.display = 'block';
                    verifyBtn.innerHTML = '<i class="fas fa-times text-danger"></i> Not Found';
                    verifyBtn.classList.remove('btn-outline-secondary');
                    verifyBtn.classList.add('btn-danger');
                }
            } catch (error) {
                console.error('Error checking user:', error);
                userInfo.innerHTML = `
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error checking user. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                userInfo.style.display = 'block';
                verifyBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
            } finally {
                setTimeout(() => {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check"></i> Verify';
                    verifyBtn.classList.remove('btn-success', 'btn-danger');
                    verifyBtn.classList.add('btn-outline-secondary');
                }, 2000);
            }
        }

        function displayUserInfo(user) {
            const userInfo = document.getElementById('userInfo');
            userInfo.innerHTML = `
                <div class="card border-success">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="card-title">
                                    <i class="fas fa-user-circle me-2"></i>
                                    ${user.username}
                                </h6>
                                <p class="mb-1">
                                    <small class="text-muted">ID: ${user.id}</small>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-users me-1"></i>
                                    Group: ${getGroupName(user.usergroup)}
                                </p>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <div class="text-success fw-bold">${user.downloaded_human}</div>
                                            <small class="text-muted">Downloaded</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <div class="text-primary fw-bold">${user.uploaded_human}</div>
                                            <small class="text-muted">Uploaded</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="badge ${user.ratio >= 1 ? 'bg-success' : 'bg-warning'}">
                                        Ratio: ${user.ratio}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            userInfo.style.display = 'block';
        }

       // Group functions
        async function loadGroupStats(groupId) {
            try {
                const response = await fetch(`${CONFIG.apiEndpoint}?action=group_stats&group_id=${groupId}`);
                const data = await response.json();
                
                if (!data.error) {
                    currentGroupStats = data;
                    displayGroupInfo(data);
                }
            } catch (error) {
                console.error('Error loading group stats:', error);
            }
        }

        function displayGroupInfo(stats) {
            const groupInfo = document.getElementById('groupInfo');
            
            groupInfo.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="fw-bold fs-4">${stats.user_count}</div>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="fw-bold fs-4">${stats.total_downloaded_human}</div>
                            <small class="text-muted">Total Downloaded</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="fw-bold fs-4">${stats.avg_per_user}</div>
                            <small class="text-muted">Average Per User</small>
                        </div>
                    </div>
                </div>
            `;
            groupInfo.style.display = 'block';
        }

        function hideGroupInfo() {
            document.getElementById('groupInfo').style.display = 'none';
        }

        // Calculation functions
        function calculateTotalImpact() {
            const amount = parseInt(document.getElementById('classamount').value || 10);
            const stats = currentGroupStats;
            
            if (!stats) {
                document.getElementById('totalImpact').textContent = 'Total Impact: Select group';
                document.getElementById('perUserImpact').textContent = 'Per user: N/A';
                return;
            }
            
            const totalBytes = amount * 1024 * 1024 * 1024 * stats.user_count;
            const perUserBytes = amount * 1024 * 1024 * 1024;
            
            document.getElementById('totalImpact').innerHTML = `
                Total Impact: <span class="text-warning">${formatBytes(totalBytes)}</span>
            `;
            document.getElementById('perUserImpact').textContent = 
                `Per user: ${formatBytes(perUserBytes)}`;
        }

        function updateBytesDisplay() {
            const gbInput = document.getElementById('downloaded');
            const display = document.getElementById('bytesDisplay');
            
            if (gbInput && display) {
                const gb = parseFloat(gbInput.value) || 0;
                const bytes = gb * 1024 * 1024 * 1024;
                display.textContent = formatBytes(bytes);
            }
        }

        function updateAmountProgress() {
            const amount = parseInt(document.getElementById('classamount').value || 10);
            const progress = ((amount - CONFIG.minGB) / (CONFIG.maxBulk - CONFIG.minGB)) * 100;
            document.getElementById('amountProgress').style.width = `${progress}%`;
            document.getElementById('currentAmount').textContent = `${amount} GB`;
        }

        // Formatting utilities
        function formatBytes(bytes) {
            if (bytes >= 1e12) return (bytes / 1e12).toFixed(2) + ' TB';
            if (bytes >= 1e9) return (bytes / 1e9).toFixed(2) + ' GB';
            if (bytes >= 1e6) return (bytes / 1e6).toFixed(2) + ' MB';
            if (bytes >= 1e3) return (bytes / 1e3).toFixed(2) + ' KB';
            return bytes + ' bytes';
        }

        function getGroupName(groupId) {
            // Simple mapping - можно расширить
            const groups = {
                1: 'Administrator',
                2: 'Moderator',
                3: 'VIP',
                4: 'Member',
                5: 'User'
            };
            return groups[groupId] || `Group ${groupId}`;
        }

        // Activity functions
// Функция для загрузки активности
function loadRecentActivity() {
    const activityBody = document.getElementById('activityBody');
    const activitySpinner = document.getElementById('activitySpinner');
    
    // Показываем спиннер
    activitySpinner.style.display = 'block';
    activityBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center text-muted">
                <i class="fas fa-spinner fa-spin me-2"></i>
                Loading activity...
            </td>
        </tr>
    `;
    
    // Создаем URL для AJAX запроса
    const url = new URL(window.location.href);
    url.searchParams.set('action', 'recent_activity');
    url.searchParams.set('_t', new Date().getTime()); // Добавляем timestamp для избежания кэширования
    
    fetch(url.toString())
        .then(response => {
            // Сначала проверяем статус ответа
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            // Затем пытаемся получить JSON
            return response.json().catch(() => {
                // Если не JSON, возвращаем текст
                return response.text().then(text => {
                    throw new Error(`Not JSON response: ${text.substring(0, 100)}...`);
                });
            });
        })
        .then(data => {
            // Скрываем спиннер
            activitySpinner.style.display = 'none';
            
            if (data.error) {
                // Ошибка от сервера
                activityBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.error}
                        </td>
                    </tr>
                `;
                return;
            }
            
            if (!data.activities || data.activities.length === 0) {
                // Нет данных
                activityBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                            No recent activity found
                        </td>
                    </tr>
                `;
                return;
            }
            
            // Рендерим данные
            const rows = data.activities.map(activity => {
                const target = extractTargetFromMessage(activity.message);
                const amount = extractAmountFromMessage(activity.message);
                
                return `
                    <tr>
                        <td>
                            <span class="badge bg-secondary">${activity.added_relative}</span>
                            <small class="text-muted d-block">${activity.added}</small>
                        </td>
                        <td>${activity.message}</td>
                        <td>${activity.username}</td>
                        <td>${target}</td>
                        <td>${amount}</td>
                        <td><span class="badge bg-success">Completed</span></td>
                    </tr>
                `;
            }).join('');
            
            activityBody.innerHTML = rows;
        })
        .catch(error => {
            console.error('Error loading activity:', error);
            activitySpinner.style.display = 'none';
            
            activityBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading activity log: ${error.message}
                    </td>
                </tr>
            `;
        });
}

        function extractTargetFromMessage(message) {
            const userMatch = message.match(/user:\s*([a-zA-Z0-9_\-\.]+)/i);
            if (userMatch) return userMatch[1];
            
            const groupMatch = message.match(/group.*ID:\s*(\d+)/i);
            if (groupMatch) return `Group ${groupMatch[1]}`;
            
            return 'Unknown';
        }

        function extractAmountFromMessage(message) {
            const match = message.match(/(\d+)\s*GB/i);
            return match ? `${match[1]} GB` : 'N/A';
        }

        function refreshActivity() {
            document.getElementById('activitySpinner').style.display = 'block';
            loadRecentActivity();
        }

        // Event listeners setup
        function setupEventListeners() {
            // Show stats dashboard
            const showStatsBtn = document.getElementById('showStats');
            if (showStatsBtn) {
                showStatsBtn.addEventListener('click', function() {
                    const dashboard = document.getElementById('statsDashboard');
                    if (dashboard.style.display === 'none') {
                        dashboard.style.display = 'block';
                    } else {
                        dashboard.style.display = 'none';
                    }
                });
            }
            
            const hideStatsBtn = document.getElementById('hideStats');
            if (hideStatsBtn) {
                hideStatsBtn.addEventListener('click', function() {
                    document.getElementById('statsDashboard').style.display = 'none';
                });
            }
            
            // Range slider sync
            const gbRange = document.getElementById('gbRange');
            const downloadedInput = document.getElementById('downloaded');
            
            if (gbRange && downloadedInput) {
                gbRange.addEventListener('input', function() {
                    downloadedInput.value = this.value;
                    updateBytesDisplay();
                });
                
                downloadedInput.addEventListener('input', function() {
                    gbRange.value = this.value;
                });
            }
        }

        // Export functions
        function exportToCSV() {
            const data = [
                ['Date', 'Admin', 'Action', 'Target', 'Amount', 'Status'],
                [new Date().toISOString(), CONFIG.currentUser, 'Export', 'All', 'N/A', 'Generated']
            ];
            
            const csv = data.map(row => row.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `download_manager_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Quick action functions
        function setIndividualAmount(gb) {
            const input = document.getElementById('downloaded');
            const range = document.getElementById('gbRange');
            
            if (input) {
                input.value = Math.min(Math.max(gb, CONFIG.minGB), CONFIG.maxIndividual);
                updateBytesDisplay();
            }
            
            if (range) {
                range.value = Math.min(Math.max(gb, CONFIG.minGB), CONFIG.maxIndividual);
            }
        }

        function setBulkAmount(gb) {
            const select = document.getElementById('classamount');
            if (select) {
                select.value = Math.min(Math.max(gb, CONFIG.minGB), CONFIG.maxBulk);
                updateAmountProgress();
                calculateTotalImpact();
            }
        }

        // Form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
      