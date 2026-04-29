// Вспомогательные функции для извлечения данных из сообщения
function extractTargetFromMessage(message) {
    if (!message) return 'Unknown';
    
    // Ищем пользователя
    const userMatch = message.match(/user:\s*([a-zA-Z0-9_\-\.]+)/i);
    if (userMatch) return userMatch[1];
    
    // Ищем группу
    const groupMatch = message.match(/group.*ID:\s*(\d+)/i);
    if (groupMatch) return `Group ${groupMatch[1]}`;
    
    // Ищем "all users"
    if (message.toLowerCase().includes('all users')) return 'All Users';
    
    return 'Unknown';
}

function extractAmountFromMessage(message) {
    if (!message) return 'N/A';
    
    // Ищем количество GB
    const gbMatch = message.match(/(\d+)\s*GB/i);
    if (gbMatch) return `${gbMatch[1]} GB`;
    
    // Ищем любые числа (предполагаем что это GB)
    const numberMatch = message.match(/\b(\d+)\b/);
    if (numberMatch) return `${numberMatch[1]} (units)`;
    
    return 'N/A';
}

// Загружаем активность при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadRecentActivity();
});

// Функция для обновления (обертка для onclick)
function refreshActivity() {
    loadRecentActivity();
}


///////////////////////////////////////////////////////
async function searchByGroup(groupId) {
    const startTime = Date.now();
    const resultsDiv = document.getElementById('userResults');
    const statsDiv = document.getElementById('searchStats');
    
    // Show loading
    resultsDiv.innerHTML = `
        <div class="text-center p-5">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading group members...</span>
            </div>
            <p class="text-muted">Loading group members...</p>
        </div>
    `;
    
    statsDiv.style.display = 'none';
    
    try {
        // ИСПРАВЛЕННЫЙ URL: Используйте тот же паттерн, что и в performUserSearch
        const url = `index.php?act=downloadadd&action=search_by_group&group_id=${groupId}`;
        console.log('Fetching URL:', url); // Для отладки
        
        const response = await fetch(url);
        
        // Сначала получим текст для отладки
        const responseText = await response.text();
        console.log('Response preview:', responseText.substring(0, 200));
        
        // Пробуем разобрать JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            console.error('Full response:', responseText);
            
            // Если не JSON, значит endpoint не работает
            resultsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Endpoint Error</h6>
                    <p>The server returned HTML instead of JSON.</p>
                    <p class="mb-0"><strong>Action needed:</strong> Add PHP handler for <code>search_by_group</code></p>
                </div>
            `;
            return;
        }
        
        const searchTime = Date.now() - startTime;
        
        if (data.error) {
            resultsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.error}
                </div>
            `;
            return;
        }
        
        if (!data.users || data.users.length === 0) {
            resultsDiv.innerHTML = `
                <div class="text-center p-5">
                    <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted mb-2">No users found</h6>
                    <p class="small text-muted">No active users in this group</p>
                </div>
            `;
            return;
        }
        
        // Update stats
        statsDiv.style.display = 'block';
        document.getElementById('resultCount').textContent = `${data.count || data.users.length} users`;
        document.getElementById('groupName').textContent = data.group_name ? `in ${data.group_name}` : '';
        document.getElementById('searchTime').textContent = `Time: ${searchTime}ms`;
        
        // Display results
        displayGroupResults(data.users, data.group_name || `Group ${groupId}`);
        
    } catch (error) {
        console.error('Error searching by group:', error);
        resultsDiv.innerHTML = `
            <div class="alert alert-danger">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                        <h6 class="alert-heading mb-1">Search Failed</h6>
                        <p class="mb-0">Error: ${error.message}</p>
                        <small class="text-muted">Check browser console for details</small>
                    </div>
                </div>
            </div>
        `;
    }
}


// Улучшенная функция отображения результатов
function displayGroupResults(users, groupName) {
    const resultsDiv = document.getElementById('userResults');
    
    // Сортируем пользователей
    users.sort((a, b) => a.username.localeCompare(b.username));
    
    // Рассчитываем общую статистику
    const totalUsers = users.length;
    const totalDownloaded = users.reduce((sum, user) => {
        // Извлекаем число из строки типа "478.35 GB"
        const match = user.downloaded.match(/([\d.]+)/);
        return sum + (match ? parseFloat(match[1]) : 0);
    }, 0);
    
    const totalUploaded = users.reduce((sum, user) => {
        const match = user.uploaded.match(/([\d.]+)/);
        return sum + (match ? parseFloat(match[1]) : 0);
    }, 0);
    
    const avgRatio = users.reduce((sum, user) => sum + (user.ratio || 0), 0) / totalUsers;

    let resultsHtml = `
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-users me-2"></i>
                        <strong>${totalUsers}</strong> users in <strong>${groupName}</strong>
                    </div>
                    <div class="text-end">
                        <small>Total: ${(totalDownloaded).toFixed(2)} GB</small>
                        <br>
                        <small>Avg Ratio: ${avgRatio.toFixed(2)}</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th class="text-center">ID</th>
                                <th class="text-end">Downloaded</th>
                                <th class="text-end">Uploaded</th>
                                <th class="text-center">Ratio</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
    `;
    
    users.forEach(user => {
        // Определяем цвет для ratio
        let ratioClass = 'success';
        let ratioIcon = 'fa-arrow-up';
        
        if (user.ratio < 1) {
            ratioClass = 'warning';
            ratioIcon = 'fa-exclamation-triangle';
        }
        if (user.ratio < 0.5) {
            ratioClass = 'danger';
            ratioIcon = 'fa-arrow-down';
        }
        
        resultsHtml += `
            <tr class="user-result-item" data-username="${user.username}">
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                            <span class="text-primary fw-bold">${user.username.charAt(0).toUpperCase()}</span>
                        </div>
                        <div>
                            <div class="fw-medium">${escapeHtml(user.username)}</div>
                            <small class="text-muted">${getGroupName(user.group_id)}</small>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary">${user.id}</span>
                </td>
                <td class="text-end">
                    <span class="text-muted">${user.downloaded}</span>
                </td>
                <td class="text-end">
                    <span class="text-muted">${user.uploaded}</span>
                </td>
                <td class="text-center">
                    <span class="badge bg-${ratioClass}">
                        <i class="fas ${ratioIcon} me-1"></i>
                        ${user.ratio.toFixed(2)}
                    </span>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-primary select-user-btn" 
                            onclick="selectUserFromSearch('${escapeHtml(user.username)}')"
                            title="Select ${user.username}">
                        <i class="fas fa-check"></i> Select
                    </button>
                </td>
            </tr>
        `;
    });
    
    resultsHtml += `
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-mouse-pointer me-1"></i>
                            Click on any row or use Select button
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Showing ${totalUsers} users
                        </small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    resultsDiv.innerHTML = resultsHtml;
    
    // Добавляем обработчики кликов на строки таблицы
    document.querySelectorAll('.user-result-item').forEach(row => {
        row.addEventListener('click', function(e) {
            // Игнорируем клики на кнопки
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                return;
            }
            const username = this.dataset.username;
            selectUserFromSearch(username);
        });
        
        // Эффект при наведении
        row.style.cursor = 'pointer';
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
}

// Функция для получения названия группы по ID
function getGroupName(groupId) {
    const groupMap = {
        6: '👑 Administrator',
        5: '🛡️ Moderator',
        4: '📤 Uploader',
        3: '⭐ VIP',
        2: '⚡ Power User',
        1: '👤 User',
        7: '💻 Sysop',
        9: '🚫 Banned'
    };
    return groupMap[groupId] || `Group ${groupId}`;
}

















// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}





// Global variables for search
let searchMode = 'startsWith'; // 'startsWith' or 'contains'
let sortOrder = 'asc'; // 'asc' or 'desc'

// Quick search function
function quickSearch(term) {
    document.getElementById('userSearch').value = term;
    if (term === '') {
        searchAllUsers();
    } else {
        performUserSearch();
    }
}

// Search all users
async function searchAllUsers() {
    const searchInput = document.getElementById('userSearch');
    const resultsDiv = document.getElementById('userResults');
    
    searchInput.value = '';
    
    // Show loading
    resultsDiv.innerHTML = `
        <div class="text-center p-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted">Loading all active users...</p>
        </div>
    `;
    
    try {
        // Используем пустой запрос для получения всех пользователей
        const response = await fetch(`index.php?act=downloadadd&action=search_users&query=`);
        const data = await response.json();
        
        if (data.users && data.users.length > 0) {
            displaySearchResults(data.users, 'All Active Users');
        } else {
            resultsDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-users me-2"></i>
                    No active users found
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading all users:', error);
    }
}

// Toggle search mode
function toggleSearchMode() {
    const modeElement = document.getElementById('searchMode');
    if (searchMode === 'startsWith') {
        searchMode = 'contains';
        modeElement.textContent = 'Contains';
    } else {
        searchMode = 'startsWith';
        modeElement.textContent = 'Starts With';
    }
}

// Toggle sort order
function toggleSortOrder() {
    const orderElement = document.getElementById('sortOrder');
    if (sortOrder === 'asc') {
        sortOrder = 'desc';
        orderElement.textContent = 'Z-A';
    } else {
        sortOrder = 'asc';
        orderElement.textContent = 'A-Z';
    }
}

// Export search results
function exportSearchResults() {
    const results = document.querySelectorAll('.user-result-item');
    if (results.length === 0) {
        alert('No results to export');
        return;
    }
    
    let csvData = ['Username,ID,Group,Selected Time'];
    results.forEach(item => {
        const username = item.dataset.username;
        const id = item.querySelector('.user-id')?.textContent?.replace('ID: ', '') || '';
        const group = item.querySelector('.user-group')?.textContent || '';
        csvData.push(`"${username}",${id},"${group}","${new Date().toISOString()}"`);
    });
    
    const csvContent = csvData.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `user_search_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Clear search
function clearSearch() {
    document.getElementById('userSearch').value = '';
    document.getElementById('userSearch').focus();
    document.getElementById('userResults').innerHTML = `
        <div class="text-center text-muted p-5">
            <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
            <p>Enter a username or use quick filters to search</p>
        </div>
    `;
    document.getElementById('searchStats').style.display = 'none';
}

// Initialize when modal opens
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('userLookupModal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', function() {
            document.getElementById('userSearch').focus();
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            clearSearch();
        });
    }
});