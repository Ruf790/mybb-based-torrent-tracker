<?php

declare(strict_types=1);

/*******************************************************************************
 * Max Login Attempts Manager v3.0
 * PHP 8.5+ Modernized Version
 * Enhanced with AJAX, Live Search, Filters & SweetAlert2
 ******************************************************************************/

// Security check
if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert"><b>Access Denied:</b> Direct access not permitted.</div>');
}

class LoginAttemptsManager
{
    private const VERSION = '3.0';
    private const PER_PAGE = 20;
    
    private string $action;
    private ?int $id;
    private ?string $update;
    private int $page;
    private string $orderBy;
    private string $orderType;
    private ?string $filterBanned;
    private ?string $filterType;
    private ?string $searchIp;
    
    public function __construct()
    {
        $this->initialize();
    }
    
    private function initialize(): void
    {
        $this->action = $this->getRequest('action', 'showlist');
        $this->id = $this->getRequestInt('id');
        $this->update = $this->getRequest('update');
        $this->page = $this->getRequestInt('page', 1);
        $this->filterBanned = $this->getRequest('filter_banned');
        $this->filterType = $this->getRequest('filter_type');
        $this->searchIp = $this->getRequest('search_ip');
        
        $order = $this->getRequest('order', 'added');
        $this->orderBy = match($order) {
            'id', 'ip', 'added', 'attempts', 'type' => $order,
            'status' => 'banned',
            default => 'added'
        };
        
        $this->orderType = $this->getRequest('otype') === 'DESC' ? 'ASC' : 'DESC';
    }
    
    public function execute(): void
    {
        // AJAX обработчики
        if ($this->action === 'ajax_ban' || $this->action === 'ajax_unban') {
            $this->handleAjaxToggleBan();
            return;
        }
        
        if ($this->action === 'ajax_delete') {
            $this->handleAjaxDelete();
            return;
        }
        
        if ($this->action === 'ajax_search') {
            $this->handleAjaxSearch();
            return;
        }
        
        if ($this->action === 'ajax_get_page') {
            $this->handleAjaxGetPage();
            return;
        }
        
        if ($this->action === 'ajax_get_count') {
            $this->handleAjaxGetCount();
            return;
        }
        
        // Обычные обработчики
        match($this->action) {
            'showlist' => $this->showList(),
            'ban' => $this->ban(),
            'unban' => $this->unban(),
            'delete' => $this->delete(),
            'edit' => $this->edit(),
            'save' => $this->save(),
            'searchip' => $this->searchIp(),
            default => $this->showError('Invalid Action')
        };
    }
    
    private function showList(): void
    {
        global $db, $dateformat, $timeformat, $BASEURL;
        
        stdhead('Login Attempts Manager - View List');
        
        // Подключаем SweetAlert2 и необходимые скрипты
        echo $this->includeJavaScriptLibraries();
        
        echo $this->renderHeader();
        
        if ($this->update) {
            echo $this->renderSuccessMessage($this->update);
        }
        
        // Фильтры и поиск
        echo $this->renderFiltersAndSearch();
        
        // Контейнер для таблицы с AJAX
        echo '<div id="attempts-table-container">';
        echo $this->renderTableContent();
        echo '</div>';
        
        stdfoot();
    }
    
    private function renderTableContent(): string
    {
        global $db, $dateformat, $timeformat, $BASEURL;
        
        $whereClause = $this->buildWhereClause();
        $totalRows = $this->getTotalRows($whereClause);
        
        if ($totalRows === 0) {
            return $this->renderEmptyState();
        }
        
        $pagination = $this->getPagination($totalRows);
        $query = $this->buildQuery($whereClause, $pagination['offset']);
        
        $result = $db->sql_query($query);
        
        $output = $this->renderTable($result, $dateformat, $timeformat, $BASEURL);
        $output .= $this->renderPagination($pagination, $totalRows);
        
        return $output;
    }
    
    private function handleAjaxToggleBan(): void
    {
        global $db;
        
        header('Content-Type: application/json');
        
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $action = $_POST['ajax_action'] ?? '';
            
            if (!$id || !is_valid_id($id)) {
                throw new Exception('Invalid ID');
            }
            
            $newStatus = $action === 'ban' ? 'yes' : 'no';
            $message = $action === 'ban' ? 'Ban' : 'Unban';
            
            $query = sprintf(
                "UPDATE loginattempts SET banned = '%s' WHERE id = %d",
                $db->escape_string($newStatus),
                $id
            );
            
            $db->sql_query($query);
            
            // Получаем обновленные данные
            $query = sprintf(
                "SELECT * FROM loginattempts WHERE id = %d",
                $id
            );
            $result = $db->sql_query($query);
            $row = $db->fetch_array($result);
            
            echo json_encode([
                'success' => true,
                'message' => "IP {$message}ned successfully!",
                'data' => [
                    'id' => $row['id'],
                    'ip' => $row['ip'],
                    'banned' => $row['banned'],
                    'status_badge' => $row['banned'] === 'yes' ? 
                        '<span class="badge bg-danger">Banned</span>' : 
                        '<span class="badge bg-success">Active</span>',
                    'ban_button' => $this->renderBanButton($row),
                    'is_banned' => $row['banned'] === 'yes'
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    private function handleAjaxDelete(): void
    {
        global $db;
        
        header('Content-Type: application/json');
        
        try {
            $id = (int) ($_POST['id'] ?? 0);
            
            if (!$id || !is_valid_id($id)) {
                throw new Exception('Invalid ID');
            }
            
            // Получаем IP перед удалением для сообщения
            $query = sprintf("SELECT ip FROM loginattempts WHERE id = %d", $id);
            $result = $db->sql_query($query);
            $row = $db->fetch_array($result);
            $ip = $row['ip'] ?? '';
            
            $query = sprintf("DELETE FROM loginattempts WHERE id = %d", $id);
            $db->sql_query($query);
            
            echo json_encode([
                'success' => true,
                'message' => "Attempt from IP {$ip} deleted successfully!",
                'id' => $id
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    private function handleAjaxSearch(): void
    {
        global $db, $dateformat, $timeformat, $BASEURL;
        
        header('Content-Type: application/json');
        
        try {
            $searchTerm = $db->escape_string($_POST['search'] ?? '');
            $filterBanned = $_POST['filter_banned'] ?? '';
            $filterType = $_POST['filter_type'] ?? '';
            
            $whereParts = [];
            
            if (!empty($searchTerm)) {
                $whereParts[] = sprintf("ip LIKE '%%%s%%'", $searchTerm);
            }
            
            if (!empty($filterBanned) && $filterBanned !== 'all') {
                $whereParts[] = sprintf("banned = '%s'", $db->escape_string($filterBanned));
            }
            
            if (!empty($filterType) && $filterType !== 'all') {
                $whereParts[] = sprintf("type = '%s'", $db->escape_string($filterType));
            }
            
            $whereClause = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
            $query = sprintf(
                "SELECT * FROM loginattempts %s ORDER BY %s %s LIMIT 50",
                $whereClause,
                $this->orderBy,
                $this->orderType
            );
            
            $result = $db->sql_query($query);
            $count = $db->num_rows($result);
            
            if ($count === 0) {
                $html = $this->renderEmptySearch($searchTerm);
            } else {
                $html = '<table class="table table-hover table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%">ID</th>
                            <th style="width: 20%">IP Address</th>
                            <th style="width: 20%">Action Time</th>
                            <th style="width: 10%">Attempts</th>
                            <th style="width: 15%">Type</th>
                            <th style="width: 20%">Status</th>
                            <th style="width: 10%" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                while ($row = $db->fetch_array($result)) {
                    $html .= $this->renderTableRow($row, $dateformat, $timeformat, $BASEURL);
                }
                
                $html .= '</tbody></table>';
            }
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'count' => $count
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    private function handleAjaxGetPage(): void
    {
        global $db, $dateformat, $timeformat, $BASEURL;
        
        header('Content-Type: application/json');
        
        try {
            $page = (int) ($_POST['page'] ?? 1);
            $filterBanned = $_POST['filter_banned'] ?? '';
            $filterType = $_POST['filter_type'] ?? '';
            $searchIp = $_POST['search_ip'] ?? '';
            $order = $_POST['order'] ?? $this->orderBy;
            $otype = $_POST['otype'] ?? $this->orderType;
            
            $this->page = $page;
            $this->filterBanned = $filterBanned;
            $this->filterType = $filterType;
            $this->searchIp = $searchIp;
            $this->orderBy = $order;
            $this->orderType = $otype;
            
            $html = $this->renderTableContent();
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'page' => $page
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    private function handleAjaxGetCount(): void
    {
        global $db;
        
        header('Content-Type: application/json');
        
        try {
            $filterBanned = $this->getRequest('filter_banned', 'all');
            $filterType = $this->getRequest('filter_type', 'all');
            $searchIp = $this->getRequest('search_ip', '');
            
            $whereParts = [];
            
            if (!empty($searchIp)) {
                $whereParts[] = sprintf("ip LIKE '%%%s%%'", $db->escape_string($searchIp));
            }
            
            if (!empty($filterBanned) && $filterBanned !== 'all') {
                $whereParts[] = sprintf("banned = '%s'", $db->escape_string($filterBanned));
            }
            
            if (!empty($filterType) && $filterType !== 'all') {
                $whereParts[] = sprintf("type = '%s'", $db->escape_string($filterType));
            }
            
            $whereClause = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
            $query = "SELECT COUNT(*) as count FROM loginattempts " . $whereClause;
            
            $result = $db->sql_query($query);
            $row = $db->fetch_array($result);
            
            echo json_encode([
                'success' => true,
                'count' => (int) $row['count']
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    private function buildWhereClause(): string
    {
        global $db;
        
        $whereParts = [];
        
        if (!empty($this->searchIp)) {
            $whereParts[] = sprintf("ip LIKE '%%%s%%'", $db->escape_string($this->searchIp));
        }
        
        if (!empty($this->filterBanned) && $this->filterBanned !== 'all') {
            $whereParts[] = sprintf("banned = '%s'", $db->escape_string($this->filterBanned));
        }
        
        if (!empty($this->filterType) && $this->filterType !== 'all') {
            $whereParts[] = sprintf("type = '%s'", $db->escape_string($this->filterType));
        }
        
        return empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);
    }
    
    private function buildQuery(string $whereClause, int $offset): string
    {
        return sprintf(
            "SELECT * FROM loginattempts %s ORDER BY %s %s LIMIT %d, %d",
            $whereClause,
            $this->orderBy,
            $this->orderType,
            $offset,
            self::PER_PAGE
        );
    }
    
    private function getTotalRows(string $whereClause): int
    {
        global $db;
        
        $query = "SELECT COUNT(*) as count FROM loginattempts " . $whereClause;
        $result = $db->sql_query($query);
        $row = $db->fetch_array($result);
        
        return (int) $row['count'];
    }
    
    private function getPagination(int $totalRows): array
    {
        $totalPages = (int) ceil($totalRows / self::PER_PAGE);
        $currentPage = max(1, min($this->page, $totalPages));
        $offset = ($currentPage - 1) * self::PER_PAGE;
        
        return [
            'current' => $currentPage,
            'total' => $totalPages,
            'offset' => $offset,
            'per_page' => self::PER_PAGE,
            'total_rows' => $totalRows
        ];
    }
    
    private function renderHeader(): string
    {
        return <<<HTML
        <div class="container mt-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white rounded-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fas fa-shield-alt me-2"></i>
                                Failed Login Attempts Manager
                            </h5>
                            <small class="text-white-50">Track and manage suspicious login activities</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm text-white me-2 d-none" 
                                 id="loading-spinner" 
                                 role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="badge bg-light text-dark" id="total-count">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
        HTML;
    }
    
    private function renderFiltersAndSearch(): string
    {
        $bannedSelected = htmlspecialchars($this->filterBanned ?? 'all');
        $typeSelected = htmlspecialchars($this->filterType ?? 'all');
        $searchValue = htmlspecialchars($this->searchIp ?? '');
        
        return <<<HTML
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="live-search" 
                           placeholder="Search by IP address (live search)" 
                           value="{$searchValue}"
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="clear-search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="form-text">Start typing to search instantly</div>
            </div>
            
            <div class="col-md-2">
                <select class="form-select" id="filter-banned">
                    <option value="all" {$this->selected($bannedSelected === 'all')}>All Status</option>
                    <option value="yes" {$this->selected($bannedSelected === 'yes')}>Banned Only</option>
                    <option value="no" {$this->selected($bannedSelected === 'no')}>Active Only</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <select class="form-select" id="filter-type">
                    <option value="all" {$this->selected($typeSelected === 'all')}>All Types</option>
                    <option value="login" {$this->selected($typeSelected === 'login')}>Login Only</option>
                    <option value="recover" {$this->selected($typeSelected === 'recover')}>Recovery Only</option>
                </select>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary btn-sm" id="refresh-btn">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="clear-filters">
                    <i class="fas fa-filter-circle-xmark me-1"></i> Clear Filters
                </button>
            </div>
            
            <div class="text-muted small">
                <span id="filter-info"></span>
            </div>
        </div>
        HTML;
    }
    
    private function renderTable(mysqli_result $result, string $dateformat, string $timeformat, string $baseUrl): string
    {
        global $db;
        
        $output = <<<HTML
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 5%">
                            <a href="#" class="text-white text-decoration-none sort-header" data-order="id">
                                ID <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 20%">
                            <a href="#" class="text-white text-decoration-none sort-header" data-order="ip">
                                IP Address <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 20%">
                            <a href="#" class="text-white text-decoration-none sort-header" data-order="added">
                                Action Time <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 10%">
                            <a href="#" class="text-white text-decoration-none sort-header" data-order="attempts">
                                Attempts <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 15%">
                            <a href="#" class="text-white text-decoration-none sort-header" data-order="type">
                                Type <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 20%">
                            <a href="#" class="text-white text-decoration-none sort-header" data-order="status">
                                Status <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 10%" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
        HTML;
        
        while ($row = $db->fetch_array($result)) {
            $output .= $this->renderTableRow($row, $dateformat, $timeformat, $baseUrl);
        }
        
        $output .= <<<HTML
                </tbody>
            </table>
        </div>
        HTML;
        
        return $output;
    }
    
    private function renderTableRow(array $row, string $dateformat, string $timeformat, string $baseUrl): string
    {
        $ip = htmlspecialchars($row['ip'], ENT_QUOTES);
        $date = my_datee($dateformat, $row['added']);
        $time = my_datee($timeformat, $row['added']);
        $type = $row['type'] === 'recover' ? 'Recover Password' : 'Login';
        $typeClass = $row['type'] === 'recover' ? 'warning' : 'primary';
        $isBanned = $row['banned'] === 'yes';
        $statusBadge = $isBanned ? 
            '<span class="badge bg-danger">Banned</span>' : 
            '<span class="badge bg-success">Active</span>';
        
        return <<<HTML
        <tr id="row-{$row['id']}">
            <td class="fw-bold">#{$row['id']}</td>
            <td>
                <div class="d-flex justify-content-between align-items-center">
                    <code class="text-dark ip-address">{$ip}</code>
                    <a href="{$baseUrl}/admin/index.php?act=ipsearch&do=1&ip={$ip}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-info" 
                       title="Search in database">
                        <i class="fas fa-search"></i>
                    </a>
                </div>
            </td>
            <td>
                <div class="text-nowrap">
                    <i class="fas fa-calendar-alt me-1 text-muted"></i> {$date}<br>
                    <i class="fas fa-clock me-1 text-muted"></i> {$time}
                </div>
            </td>
            <td>
                <span class="badge bg-secondary rounded-pill px-3 attempts-count">{$row['attempts']}</span>
            </td>
            <td>
                <span class="badge bg-{$typeClass} attempt-type">{$type}</span>
            </td>
            <td class="status-cell">
                {$statusBadge}
            </td>
            <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                    {$this->renderBanButton($row)}
                    <a href="?act=maxlogin&action=edit&id={$row['id']}" 
                       class="btn btn-outline-primary" 
                       title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="btn btn-outline-danger delete-btn" 
                            data-id="{$row['id']}" 
                            data-ip="{$ip}"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        HTML;
    }
    
    private function renderBanButton(array $row): string
    {
        $isBanned = $row['banned'] === 'yes';
        $banText = $isBanned ? 'Unban' : 'Ban';
        $banClass = $isBanned ? 'success' : 'danger';
        $banIcon = $isBanned ? 'unlock' : 'lock';
        $action = $isBanned ? 'unban' : 'ban';
        
        return <<<HTML
        <button class="btn btn-outline-{$banClass} ban-btn" 
                data-id="{$row['id']}" 
                data-action="{$action}"
                data-ip="{$row['ip']}"
                title="{$banText} IP">
            <i class="fas fa-{$banIcon}"></i>
        </button>
        HTML;
    }
    
    private function renderPagination(array $pagination, int $totalRows): string
    {
        $current = $pagination['current'];
        $total = $pagination['total'];
        $totalRowsFormatted = number_format($totalRows);
        
        if ($total <= 1) {
            return '<div class="text-center text-muted mt-3">Total records: ' . $totalRowsFormatted . '</div>';
        }
        
        $pages = [];
        $start = max(1, $current - 2);
        $end = min($total, $current + 2);
        
        // Previous button
        $prevDisabled = $current === 1 ? 'disabled' : '';
        $prevPage = max(1, $current - 1);
        
        // Page numbers
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $current;
            $pages[] = sprintf(
                '<li class="page-item %s">
                    <a class="page-link pagination-page" href="#" data-page="%d">%d</a>
                </li>',
                $active ? 'active' : '',
                $i,
                $i
            );
        }
        
        // Next button
        $nextDisabled = $current === $total ? 'disabled' : '';
        $nextPage = min($total, $current + 1);
        
        return sprintf(
            '<div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted">
                    Showing page %d of %d (Total: %s records)
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination mb-0">
                        <li class="page-item %s">
                            <a class="page-link pagination-page" href="#" data-page="%d" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        %s
                        <li class="page-item %s">
                            <a class="page-link pagination-page" href="#" data-page="%d" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>',
            $current,
            $total,
            $totalRowsFormatted,
            $prevDisabled,
            $prevPage,
            implode('', $pages),
            $nextDisabled,
            $nextPage
        );
    }
    




private function includeJavaScriptLibraries(): string
{
    // Получаем текущие значения фильтров
    $currentBanned = $this->filterBanned ?? 'all';
    $currentType = $this->filterType ?? 'all';
    $currentSearch = $this->searchIp ?? '';
    
    // Экранируем значения для JavaScript
    $currentBanned = addslashes($currentBanned);
    $currentType = addslashes($currentType);
    $currentSearch = addslashes($currentSearch);
    $orderBy = addslashes($this->orderBy);
    $orderType = addslashes($this->orderType);
    
    return <<<HTML
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- JavaScript для AJAX функционала -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let searchTimeout;
        let currentPage = 1;
        let currentOrder = '{$orderBy}';
        let currentOrderType = '{$orderType}';
        let currentFilterBanned = '{$currentBanned}';
        let currentFilterType = '{$currentType}';
        let currentSearchTerm = '{$currentSearch}';
        
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
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
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
    </script>
    
    <style>
    .sort-header:hover {
        background-color: rgba(255,255,255,0.1);
        border-radius: 4px;
        padding: 2px 6px;
    }
    #attempts-table-container {
        transition: opacity 0.3s ease;
        min-height: 300px;
    }
    .ban-btn, .delete-btn {
        transition: all 0.2s ease;
    }
    .ban-btn:hover, .delete-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .ip-address {
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }
    .status-badge {
        font-size: 0.85em;
    }
    #loading-spinner {
        width: 1.5rem;
        height: 1.5rem;
    }
    </style>
    HTML;
}











    
    private function renderEmptyState(): string
    {
        $hasFilters = !empty($this->filterBanned) || !empty($this->filterType) || !empty($this->searchIp);
        
        if ($hasFilters) {
            return <<<HTML
            <div class="text-center py-5">
                <i class="fas fa-search fa-4x text-warning mb-3"></i>
                <h4 class="text-warning">No Matching Records</h4>
                <p class="text-muted">No login attempts match your current filters.</p>
                <button class="btn btn-outline-primary mt-2" id="clear-filters-btn">
                    <i class="fas fa-filter-circle-xmark me-2"></i> Clear Filters
                </button>
            </div>
            <script>
            $('#clear-filters-btn').click(function() {
                $('#clear-filters').click();
            });
            </script>
            HTML;
        }
        
        return <<<HTML
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">No login attempts found</h4>
            <p class="text-muted">There are no failed login attempts in the system.</p>
        </div>
        HTML;
    }
    
    private function renderEmptySearch(string $searchTerm): string
    {
        $searchTermHtml = htmlspecialchars($searchTerm);
        
        return <<<HTML
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-warning mb-3"></i>
            <h4 class="text-warning">No Results Found</h4>
            <p class="text-muted">No login attempts found for: <code>{$searchTermHtml}</code></p>
            <button class="btn btn-outline-primary mt-2" onclick="$('#clear-search').click()">
                <i class="fas fa-times me-2"></i> Clear Search
            </button>
        </div>
        HTML;
    }
    
    // Стандартные методы (без AJAX) - они должны быть объявлены ТОЛЬКО ОДИН РАЗ
    
    private function ban(): void
    {
        $this->validateId();
        $this->updateRecord('banned', 'yes', 'Ban');
    }
    
    private function unban(): void
    {
        $this->validateId();
        $this->updateRecord('banned', 'no', 'Unban');
    }
    
    private function delete(): void
    {
        $this->validateId();
        
        if (isset($_GET['return'])) {
            $this->deleteRecord(true);
        } else {
            $this->deleteRecord();
        }
    }
    
    private function edit(): void
    {
        global $db;
        
        $this->validateId();
        stdhead('Login Attempts - Edit');
        
        $query = sprintf(
            "SELECT * FROM loginattempts WHERE id = %d",
            $this->id
        );
        
        $result = $db->sql_query($query);
        $attempt = $db->fetch_array($result);
        
        echo $this->renderEditForm($attempt);
        stdfoot();
    }
    
    private function save(): void
    {
        global $db;
        
        $id = (int) $_POST['id'];
        $attempts = (int) $_POST['attempts'];
        $type = $db->escape_string($_POST['type']);
        $banned = $db->escape_string($_POST['banned']);
        
        $this->validateId($id);
        $this->validateAttempts($attempts);
        
        $query = sprintf(
            "UPDATE loginattempts 
             SET attempts = %d, type = '%s', banned = '%s' 
             WHERE id = %d LIMIT 1",
            $attempts,
            $type,
            $banned,
            $id
        );
        
        $db->sql_query($query);
        
        if (!empty($_POST['returnto'])) {
            redirect($_POST['returnto']);
        }
        
        redirect($_SERVER['PHP_SELF'] . '?act=maxlogin&update=Edit');
    }
    
    private function searchIp(): void
    {
        global $db, $dateformat, $timeformat, $BASEURL;
        
        $ip = $db->escape_string($_POST['ip']);
        stdhead('Login Attempts - Search Results');
        
        $query = sprintf(
            "SELECT * FROM loginattempts WHERE ip LIKE '%%%s%%'",
            $ip
        );
        
        $result = $db->sql_query($query);
        
        if ($db->num_rows($result) === 0) {
            echo $this->renderEmptySearch($ip);
        } else {
            echo $this->renderTable($result, $dateformat, $timeformat, $BASEURL);
        }
        
        echo $this->renderSearchForm();
        stdfoot();
    }
    
    // Вспомогательные методы
    
    private function validateId(?int $id = null): void
    {
        $id = $id ?? $this->id;
        
        if (!$id || !is_valid_id($id)) {
            stderr('Error', 'Invalid ID');
        }
    }
    
    private function validateAttempts(int $attempts): void
    {
        if ($attempts < 0 || $attempts > 1000) {
            stderr('Error', 'Invalid attempts value');
        }
    }
    
    private function updateRecord(string $field, string $value, string $message): void
    {
        global $db;
        
        $query = sprintf(
            "UPDATE loginattempts SET %s = '%s' WHERE id = %d",
            $field,
            $db->escape_string($value),
            $this->id
        );
        
        $db->sql_query($query);
        redirect($_SERVER['PHP_SELF'] . "?act=maxlogin&update=$message");
    }
    
    private function deleteRecord(bool $returnToRequests = false): void
    {
        global $db;
        
        $query = sprintf(
            "DELETE FROM loginattempts WHERE id = %d",
            $this->id
        );
        
        $db->sql_query($query);
        
        if ($returnToRequests) {
            redirect('admin.php?act=viewunbaniprequest');
        }
        
        redirect($_SERVER['PHP_SELF'] . '?act=maxlogin&update=Delete');
    }
    
    private function renderEditForm(array $attempt): string
    {
        $added = my_datee('relative', $attempt['added']);
        $returnHidden = isset($_GET['return']) && $_GET['return'] === 'yes' ? 
            '<input type="hidden" name="returnto" value="admin.php?act=viewunbaniprequest">' : '';
        
        return <<<HTML
        <div class="container-md">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Edit Login Attempt #{$attempt['id']}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">IP Address</h6>
                                    <p class="card-text">
                                        <code class="fs-5">{$attempt['ip']}</code>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Action Time</h6>
                                    <p class="card-text">
                                        <i class="fas fa-clock text-primary me-2"></i>
                                        {$added}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" action="?act=maxlogin&action=save" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="{$attempt['id']}">
                        <input type="hidden" name="ip" value="{$attempt['ip']}">
                        {$returnHidden}
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="attempts" class="form-label">
                                    <i class="fas fa-retweet me-1"></i> Attempts Count
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="attempts" 
                                       name="attempts" 
                                       value="{$attempt['attempts']}" 
                                       min="0" 
                                       max="1000" 
                                       required>
                                <div class="invalid-feedback">
                                    Please enter a valid number between 0 and 1000.
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="type" class="form-label">
                                    <i class="fas fa-key me-1"></i> Attempt Type
                                </label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="login" {$this->selected($attempt['type'] === 'login')}>
                                        Login Attempt
                                    </option>
                                    <option value="recover" {$this->selected($attempt['type'] === 'recover')}>
                                        Password Recovery Attempt
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="banned" class="form-label">
                                    <i class="fas fa-ban me-1"></i> Status
                                </label>
                                <select class="form-select" id="banned" name="banned" required>
                                    <option value="yes" {$this->selected($attempt['banned'] === 'yes')}>
                                        <span class="text-danger">Banned</span>
                                    </option>
                                    <option value="no" {$this->selected($attempt['banned'] === 'no')}>
                                        <span class="text-success">Not Banned</span>
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                            <a href="?" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
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
        </script>
        HTML;
    }
    
    private function renderSearchForm(): string
    {
        return <<<HTML
        <div class="container-md mt-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-search me-2"></i>
                        Search IP Address
                    </h6>
                </div>
                <div class="card-body">
                    <form method="post" action="?action=searchip" class="row g-3 align-items-center">
                        <input type="hidden" name="action" value="searchip">
                        
                        <div class="col-md-8">
                            <label for="searchIp" class="visually-hidden">IP Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-address-card"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="searchIp" 
                                       name="ip" 
                                       placeholder="Enter IP address (e.g., 192.168.1.1)" 
                                       required 
                                       pattern="^([0-9]{1,3}\.){3}[0-9]{1,3}$">
                                <div class="invalid-feedback">
                                    Please enter a valid IP address.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="fas fa-search me-2"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        HTML;
    }
    
    private function renderSuccessMessage(string $action): string
    {
        return <<<HTML
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Success!</strong> Operation "{$action}" completed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        HTML;
    }
    
    private function showError(string $message): void
    {
        stderr('Error', $message);
    }
    
    private function getRequest(string $key, string $default = ''): string
    {
        return htmlspecialchars($_REQUEST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
    }
    
    private function getRequestInt(string $key, int $default = 0): int
    {
        $value = $_REQUEST[$key] ?? $default;
        return (int) filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default]
        ]);
    }
    
    private function selected(bool $condition): string
    {
        return $condition ? 'selected' : '';
    }
}

// Initialize and execute the manager
try {
    $manager = new LoginAttemptsManager();
    $manager->execute();
} catch (Exception $e) {
    stderr('System Error', 'An unexpected error occurred: ' . htmlspecialchars($e->getMessage()));
}