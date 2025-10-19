<?php
declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';

class TorrentManager 
{
    private array $errors = [];
    
    public function showErrors(): void {
        global $lang;
        
        if (!empty($this->errors)) {
            $errors = implode('<br>', $this->errors);
            echo '
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                    <div>
                        <h5 class="alert-heading mb-2">' . $lang->global['error'] . '</h5>
                        ' . $errors . '
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }

    public function addError(string $error): void {
        $this->errors[] = $error;
    }

    public function getTorrentFlags(array $torrent): string {
        global $BASEURL, $pic_base_url, $lang;
        
        $flags = [
            'visible' => $torrent['visible'] === 'yes' 
                ? '<span class="badge bg-success" data-bs-toggle="tooltip" title="Active Torrent"><i class="fas fa-check-circle me-1"></i>Active</span>'
                : '<span class="badge bg-danger" data-bs-toggle="tooltip" title="Dead Torrent"><i class="fas fa-times-circle me-1"></i>Dead</span>',
                
            'free' => $torrent['free'] === 'yes'
                ? '<span class="badge bg-info" data-bs-toggle="tooltip" title="' . $lang->browse['freedownload'] . '"><i class="fas fa-gift me-1"></i>Free</span>'
                : '',
                
            'silver' => $torrent['silver'] === 'yes'
                ? '<span class="badge bg-secondary" data-bs-toggle="tooltip" title="' . $lang->browse['silverdownload'] . '"><i class="fas fa-star me-1"></i>Silver</span>'
                : '',
                
            'sticky' => $torrent['sticky'] === 'yes'
                ? '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="' . $lang->browse['sticky'] . '"><i class="fas fa-thumbtack me-1"></i>Sticky</span>'
                : '',
                
            'anonymous' => $torrent['anonymous'] === 'yes'
                ? '<span class="badge bg-dark" data-bs-toggle="tooltip" title="Anonymous Torrent"><i class="fas fa-user-secret me-1"></i>Anonymous</span>'
                : '',
                
            'banned' => $torrent['banned'] === 'yes'
                ? '<span class="badge bg-danger" data-bs-toggle="tooltip" title="Banned Torrent"><i class="fas fa-ban me-1"></i>Banned</span>'
                : '',
                
            'doubleupload' => $torrent['doubleupload'] === 'yes'
                ? '<span class="badge bg-purple" data-bs-toggle="tooltip" title="' . $lang->browse['dupload'] . '"><i class="fas fa-bolt me-1"></i>2x Upload</span>'
                : '',
        ];

        return implode(' ', array_filter($flags));
    }

    public function handleUpdate(array $postData): void {
        $torrentIds = $postData['torrentid'] ?? [];
        $actionType = $postData['actiontype'] ?? '';
        $category = (int)($postData['category'] ?? 0);

        if (empty($actionType)) {
            $this->addError('Please select action type!');
            return;
        }

        if (!is_array($torrentIds) || count($torrentIds) < 1) {
            $this->addError('Please select at least one torrent!');
            return;
        }

        $torrentIdsStr = implode(',', array_map('intval', $torrentIds));
        
        $actions = [
            'move' => fn() => $this->moveTorrents($torrentIdsStr, $category),
            'delete' => fn() => $this->deleteTorrents($torrentIds),
            'sticky' => fn() => $this->toggleField($torrentIdsStr, 'sticky'),
            'free' => fn() => $this->toggleField($torrentIdsStr, 'free'),
            'silver' => fn() => $this->toggleField($torrentIdsStr, 'silver'),
            'visible' => fn() => $this->toggleField($torrentIdsStr, 'visible'),
            'anonymous' => fn() => $this->toggleField($torrentIdsStr, 'anonymous'),
            'banned' => fn() => $this->toggleField($torrentIdsStr, 'banned'),
            'nuke' => fn() => $this->toggleField($torrentIdsStr, 'isnuked'),
            'doubleupload' => fn() => $this->toggleField($torrentIdsStr, 'doubleupload'),
            'openclose' => fn() => $this->toggleField($torrentIdsStr, 'allowcomments'),
        ];

        if (isset($actions[$actionType])) {
            $actions[$actionType]();
        }
    }

    private function moveTorrents(string $ids, int $category): void {
        global $db;
        if ($category > 0) {
            $db->update_query('torrents', ['category' => $category], "id IN ($ids)");
        } else {
            $this->addError('Invalid category selected!');
        }
    }

    private function deleteTorrents(array $ids): void {
        require_once INC_PATH . '/functions_deletetorrent.php';
        foreach ($ids as $id) {
            deletetorrent((int)$id);
        }
    }

    private function toggleField(string $ids, string $field): void {
        global $db;
        $db->sql_query("UPDATE torrents SET $field = IF($field = 'yes', 'no', 'yes') WHERE id IN ($ids)");
    }
}

// Initialize torrent manager
$torrentManager = new TorrentManager();

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('
    <div class="alert alert-danger text-center mt-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

define('MT_VERSION', 'v0.8 by xam');

// Process form data
$do = $_POST['do'] ?? $_GET['do'] ?? '';
$browsecategory = (int)($_GET['browsecategory'] ?? $_POST['browsecategory'] ?? 0);
$searchword = htmlspecialchars($_GET['searchword'] ?? $_POST['searchword'] ?? '');
$searchtype = $_GET['searchtype'] ?? $_POST['searchtype'] ?? '';

// Build query conditions
$queryBuilder = new class {
    public array $conditions = [];
    public string $extralink = '';

    public function addCategoryCondition(int $category): void {
        global $db;
        
        if ($category > 0) {
            $query = $db->simple_select('categories', 'type', "id = '{$category}'");
            if ($db->num_rows($query)) {
                $result = $db->fetch_array($query);
                
                if ($result['type'] === 's') {
                    $this->conditions[] = "t.category = $category";
                } else {
                    $subCats = [$category];
                    $subQuery = $db->simple_select('categories', 'id', "pid = '{$category}'");
                    while ($subCat = $db->fetch_array($subQuery)) {
                        $subCats[] = $subCat['id'];
                    }
                    $this->conditions[] = "t.category IN (" . implode(',', $subCats) . ")";
                }
                $this->extralink .= "browsecategory=$category&amp;";
            }
        }
    }

    public function addSearchCondition(string $searchword): void {
        if (!empty($searchword)) {
            global $db;
            $this->conditions[] = "t.name LIKE " . $db->sqlesc("%$searchword%");
            $this->extralink .= "searchword=" . urlencode($searchword) . "&amp;";
        }
    }

    public function addSearchTypeCondition(string $searchtype): void {
        $conditions = [
            'deadonly' => "(t.visible = 'no' OR (t.seeders=0 AND t.leechers=0))",
            'internal' => "t.ts_external = 'no'",
            'external' => "t.ts_external = 'yes'",
            'silver' => "t.silver = 'yes'",
            'free' => "t.free = 'yes'",
            'recommend' => "t.sticky = 'yes'",
            'doubleuploads' => "t.doubleupload = 'yes'"
        ];

        if (isset($conditions[$searchtype])) {
            $this->conditions[] = $conditions[$searchtype];
            $this->extralink .= "searchtype=$searchtype&amp;";
        }
    }

    public function getWhereClause(): string {
        return $this->conditions ? 'WHERE ' . implode(' AND ', $this->conditions) : '';
    }

    public function getCountQuery(): string {
        return "SELECT COUNT(*) as total FROM torrents t " . $this->getWhereClause();
    }

    public function getMainQuery(string $orderBy, int $start, int $perPage): string {
        return "
            SELECT t.*, u.username, u.usergroup, u.avatar, c.name as category_name, c.cat_desc 
            FROM torrents t 
            LEFT JOIN users u ON t.owner = u.id 
            LEFT JOIN categories c ON t.category = c.id 
            " . $this->getWhereClause() . "
            ORDER BY $orderBy 
            LIMIT $start, $perPage
        ";
    }
};

$queryBuilder->addCategoryCondition($browsecategory);
$queryBuilder->addSearchCondition($searchword);
$queryBuilder->addSearchTypeCondition($searchtype);

// Handle form submission
if ($do === 'update') {
    $torrentManager->handleUpdate($_POST);
}

stdhead('Manage Torrents', true, 'supernote');

// Enhanced CSS with mobile optimizations
echo '
<style>
.bg-purple { background-color: #6f42c1 !important; }
.torrent-card { transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.08); }
.torrent-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.flag-badges .badge { margin: 2px; font-size: 0.75em; }
.avatar-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; }
.highlight-match { background-color: #fff3cd; padding: 2px 4px; border-radius: 3px; }
.modal-torrent-image { max-height: 200px; object-fit: contain; }
.action-btn-group .btn { margin: 2px; }

/* Mobile optimizations */
@media (max-width: 768px) {
    .table-responsive { 
        font-size: 0.875rem; 
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    .mobile-hidden { display: none !important; }
    .torrent-name { 
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .avatar-sm { width: 24px; height: 24px; }
    .flag-badges .badge { 
        font-size: 0.7em; 
        padding: 0.25em 0.5em;
        margin: 1px;
    }
    .container-fluid { padding-left: 10px; padding-right: 10px; }
}

/* Performance optimizations */
.torrent-btn { transition: all 0.2s ease; }
.torrent-btn:hover { transform: scale(1.05); }

/* Loading states */
.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Selection states */
.torrent-card.selected { 
    background-color: rgba(13, 110, 253, 0.05);
    border-left: 3px solid #0d6efd;
}

/* Print styles */
@media print {
    .btn, .pagination, .card-header, .torrent-btn { display: none !important; }
    .table { border: 1px solid #000 !important; }
}
</style>';

$torrentManager->showErrors();

// Build ordering
$orderBy = 't.added DESC';
$allowedOrders = ['name', 'owner', 'category', 'added'];
if (isset($_GET['orderby']) && in_array($_GET['orderby'], $allowedOrders)) {
    $what = ($_GET['what'] ?? 'desc') === 'desc' ? 'DESC' : 'ASC';
    $orderBy = "t.{$_GET['orderby']} $what";
    $orderLink = "orderby={$_GET['orderby']}&amp;what={$_GET['what']}&amp;";
}

// Get torrent count and setup pagination with performance monitoring
$startTime = microtime(true);
$countQuery = $db->sql_query($queryBuilder->getCountQuery());
$totalTorrents = (int)$db->fetch_field($countQuery, 'total');
$queryTime = round((microtime(true) - $startTime) * 1000, 2);

// Adaptive pagination based on performance
$torrentsPerPage = max(20, (int)($CURUSER['torrentsperpage'] ?? $ts_perpage ?? 50));
if ($queryTime > 1000 && $totalTorrents > 1000) {
    $torrentsPerPage = min($torrentsPerPage, 25); // Reduce for large datasets
}

$page = max(1, (int)($_GET['page'] ?? 1));
$start = ($page - 1) * $torrentsPerPage;

$multipage = multipage($totalTorrents, $torrentsPerPage, $page, $_this_script_ . '' . ($orderLink ?? '') . $queryBuilder->extralink);




// Build dropdowns
require_once INC_PATH . '/functions_category.php';
$categoryDropdown = ts_category_list('browsecategory', $browsecategory, '<option value="0">All Categories</option>');

$searchTypeDropdown = '
<select class="form-select form-select-sm border" name="searchtype">
    <option value="">All Types</option>
    <option value="free"' . ($searchtype === 'free' ? ' selected' : '') . '>Free Torrents</option>
    <option value="silver"' . ($searchtype === 'silver' ? ' selected' : '') . '>Silver Torrents</option>
    <option value="sticky"' . ($searchtype === 'recommend' ? ' selected' : '') . '>Sticky Torrents</option>
    <option value="doubleuploads"' . ($searchtype === 'doubleuploads' ? ' selected' : '') . '>2x Upload</option>
    <option value="internal"' . ($searchtype === 'internal' ? ' selected' : '') . '>Internal</option>
    <option value="external"' . ($searchtype === 'external' ? ' selected' : '') . '>External</option>
    <option value="deadonly"' . ($searchtype === 'deadonly' ? ' selected' : '') . '>Dead Torrents</option>
</select>';

// Main interface with enhanced features
echo '
<div class="container-fluid py-4">
    <!-- Enhanced Header with Performance Stats -->
    <div class="card border-0 bg-gradient-primary mb-4">
        <div class="card-body py-4 text-white position-relative">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-2">
                        <i class="fas fa-cogs me-3"></i>
                        Torrent Management Panel
                    </h1>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-database me-2"></i>
                        Managing ' . number_format($totalTorrents) . ' torrents
                        <small class="opacity-50">(' . $queryTime . 'ms)</small>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="bg-white bg-opacity-10 rounded p-2 text-center">
                                <div class="h5 mb-0">' . number_format($totalTorrents) . '</div>
                                <small>Total</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white bg-opacity-10 rounded p-2 text-center">
                                <div class="h5 mb-0">' . $torrentsPerPage . '</div>
                                <small>Per Page</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Search Form -->
    <div class="container mt-3">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="get" action="' . $_this_script_ . '" class="row g-2 align-items-end" id="searchForm">
                    <input type="hidden" name="act" value="manage_torrents">
                    
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold">🔍 Search Torrents</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="searchword" value="' . $searchword . '" 
                                   placeholder="Enter torrent name..." id="torrent-search"
                                   data-min-length="2">
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-text small">Minimum 2 characters</div>
                    </div>
                    
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">📁 Category</label>
                        ' . $categoryDropdown . '
                    </div>
                    
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">⚡ Filter</label>
                        ' . $searchTypeDropdown . '
                    </div>
                    
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-primary w-100 mb-1" id="searchBtn">
                            <i class="fas fa-filter me-1"></i>Search
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 btn-sm" onclick="resetFilters()">
                            <i class="fas fa-refresh me-1"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    ' . ($totalTorrents > 5000 ? '
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Large dataset:</strong> ' . number_format($totalTorrents) . ' torrents found. 
        Use specific filters for better performance.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>' : '') . '

    <div class="container mt-3" id="paginationTop">
        ' . $multipage . '
    </div>

    <!-- Enhanced Torrents Table -->
    <form method="post" action="' . $_this_script_ . '" name="update" id="torrentForm">
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="page" value="' . $page . '">
        
        <div class="container mt-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0 text-primary">
                                <i class="fas fa-list me-2"></i>
                                Torrent List
                                <small class="text-muted">• Page ' . $page . '</small>
                            </h5>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="form-check form-switch d-inline-block me-3">
                                <input class="form-check-input" type="checkbox" id="selectAll" 
                                       onclick="toggleAllSelection(this)">
                                <label class="form-check-label small" for="selectAll">Select All</label>
                            </div>
                            <span class="badge bg-primary fs-6">' . number_format($totalTorrents) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive" id="torrentTableContainer">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">
                                    <a href="' . $_this_script_ . '?act=manage_torrents&amp;orderby=name&amp;what=' . ($_GET['what'] ?? 'desc') . '&amp;' . $queryBuilder->extralink . '" 
                                       class="text-decoration-none text-dark sort-link">
                                        <i class="fas fa-file-alt me-2"></i>Torrent Name
                                    </a>
                                </th>
                                <th class="py-3 mobile-hidden">Status</th>
                                <th class="py-3">
                                    <a href="' . $_this_script_ . '?act=manage_torrents&amp;orderby=owner&amp;what=' . ($_GET['what'] ?? 'desc') . '&amp;' . $queryBuilder->extralink . '" 
                                       class="text-decoration-none text-dark sort-link">
                                        <i class="fas fa-user me-2"></i>Uploader
                                    </a>
                                </th>
                                <th class="py-3 mobile-hidden">Category</th>
                                <th class="py-3">
                                    <a href="' . $_this_script_ . '?act=manage_torrents&amp;orderby=added&amp;what=' . ($_GET['what'] ?? 'desc') . '&amp;' . $queryBuilder->extralink . '" 
                                       class="text-decoration-none text-dark sort-link">
                                        <i class="fas fa-calendar me-2"></i>Added
                                    </a>
                                </th>
                                <th class="pe-4 py-3 text-center">
                                    <i class="fas fa-check-square text-muted"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="torrentTableBody">';

// Fetch and display torrents with performance monitoring
$queryStart = microtime(true);
$query = $db->sql_query($queryBuilder->getMainQuery($orderBy, $start, $torrentsPerPage));
$queryTime = round((microtime(true) - $queryStart) * 1000, 2);

$torrentCount = 0;
$totalSize = 0;

while ($torrent = $db->fetch_array($query)) {
    $torrentCount++;
    $totalSize += $torrent['size'];
    $flags = $torrentManager->getTorrentFlags($torrent);
    $userAvatar = format_avatar($torrent['avatar'] ?? '', $torrent['avatardimensions'] ?? '');
    
    echo '
    <tr class="torrent-card" data-id="' . $torrent['id'] . '" data-size="' . $torrent['size'] . '">
        <td class="ps-4 py-3">
            <div class="d-flex align-items-start">
                <button type="button" class="btn btn-sm btn-outline-primary me-2 torrent-btn"
                        data-bs-toggle="modal" data-bs-target="#manageTorrentModal"
                        data-id="' . $torrent['id'] . '"
                        data-name="' . htmlspecialchars($torrent['name']) . '"
                        data-image="' . htmlspecialchars($torrent['t_image'] ?? '') . '"
                        data-infohash="' . htmlspecialchars($torrent['info_hash']) . '"
                        data-seeders="' . $torrent['seeders'] . '"
                        data-leechers="' . $torrent['leechers'] . '"
                        data-completed="' . $torrent['times_completed'] . '">
                    <i class="fas fa-cog fa-sm"></i>
                </button>
                <div class="flex-grow-1">
                    <a href="' . $BASEURL . '/'.get_torrent_link($torrent['id']) . '" 
                       class="fw-semibold text-dark text-decoration-none d-block mb-1 torrent-name">
                       ' . htmlspecialchars($torrent['name']) . '
                    </a>
                    <div class="text-muted small">
                        <span class="me-2"><i class="fas fa-hdd me-1"></i>' . mksize($torrent['size']) . '</span>
                        <span><i class="fas fa-download me-1"></i>' . ts_nf($torrent['times_completed']) . '</span>
                    </div>
                    <div class="mobile-visible mt-1">
                        ' . $flags . '
                    </div>
                </div>
            </div>
        </td>
        <td class="py-3 mobile-hidden">
            <div class="flag-badges">' . $flags . '</div>
        </td>
        <td class="py-3">
            <div class="d-flex align-items-center">
                ' . ($userAvatar['image'] ? '<img src="' . $userAvatar['image'] . '" class="avatar-sm me-2" alt="" loading="lazy">' : '') . '
                <a href="' . $BASEURL . '/' . get_profile_link($torrent['owner']) . '" class="text-decoration-none small">
                    ' . format_name($torrent['username'], $torrent['usergroup']) . '
                </a>
            </div>
        </td>
        <td class="py-3 mobile-hidden">
            <span class="badge bg-light text-dark" data-bs-toggle="tooltip" title="' . htmlspecialchars($torrent['cat_desc']) . '">
                ' . htmlspecialchars($torrent['category_name']) . '
            </span>
        </td>
        <td class="py-3">
            <div class="text-muted small">
                <div><i class="far fa-calendar me-1"></i>' . my_datee($dateformat, $torrent['added']) . '</div>
                <div class="mobile-hidden"><i class="far fa-clock me-1"></i>' . my_datee($timeformat, $torrent['added']) . '</div>
            </div>
        </td>
        <td class="pe-4 py-3 text-center">
            <input type="checkbox" class="form-check-input torrent-checkbox" name="torrentid[]" 
                   value="' . $torrent['id'] . '" onchange="updateSelectionCounter()">
        </td>
    </tr>';
}

if ($torrentCount === 0) {
    echo '
    <tr>
        <td colspan="6" class="text-center py-5">
            <div class="text-muted">
                <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                <h5>No torrents found</h5>
                <p class="mb-3">Try adjusting your search criteria</p>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetFilters()">
                    <i class="fas fa-refresh me-1"></i>Reset Filters
                </button>
            </div>
        </td>
    </tr>';
}

echo '
                        </tbody>
                    </table>
                </div>
                
                <!-- Enhanced Bulk Actions -->
                <div class="card-footer bg-white py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="me-2">
                                    <span class="fw-semibold">Bulk Actions:</span>
                                    <small class="text-muted ms-2" id="selectedCounter">0 selected</small>
                                </div>
                                <select class="form-select form-select-sm w-auto" name="actiontype" id="actionType" onchange="toggleMoveCategory(this)">
                                    <option value="">Select Action</option>
                                    <option value="move">Move Torrents</option>
                                    <option value="delete">Delete Torrents</option>
                                    <option value="sticky">Toggle Sticky</option>
                                    <option value="free">Toggle Free</option>
                                    <option value="silver">Toggle Silver</option>
                                    <option value="visible">Toggle Visibility</option>
                                    <option value="anonymous">Toggle Anonymous</option>
                                    <option value="banned">Toggle Ban</option>
                                    <option value="doubleupload">Toggle 2x Upload</option>
                                </select>
                                <div id="moveCategory" style="display: none;" class="ms-2">
                                    ' . ts_category_list('category', 0, '<option value="0">Select Category</option>') . '
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm ms-2" id="executeBtn" disabled>
                                    <i class="fas fa-play me-1"></i>Execute
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                                    <i class="fas fa-times me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-muted small">
                                <div>📊 ' . number_format($torrentCount) . ' items • ' . mksize($totalSize) . '</div>
                                <div>⚡ ' . $queryTime . 'ms load time</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Bottom Pagination -->
    <div class="container mt-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                ' . $multipage . '
            </div>
        </div>
    </div>
</div>';




?>


<!-- MODAL -->
<div class="modal fade" id="manageTorrentModal" tabindex="-1" aria-labelledby="manageTorrentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageTorrentModalLabel">Manage Torrent</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="manageTorrentContent">
        <div class="text-center p-3">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_SESSION['action_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toastHTML = `
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast show bg-success text-white">
                <div class="toast-header">
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    <?= $_SESSION['action_success']; ?>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', toastHTML);
});
</script>
<?php unset($_SESSION['action_success']); endif; ?>







<style>
    #autocomplete-results {
      position: absolute;
      z-index: 1000;
      width: 100%;
      border: 1px solid #ccc;
      background-color: #fff;
      display: none;
    }
    #autocomplete-results.show {
      display: block;
    }
	
	
mark {
  background-color: #ffeb3b;
  color: inherit;
  padding: 0 2px;
  border-radius: 2px;
}
	
	

	
	
  </style>




<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('manageTorrentModal');
    const content = document.getElementById('manageTorrentContent');
    const baseurl = "<?= $BASEURL ?>";  // Make sure this works!

    
	function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Link copied to clipboard!');
        });
    }
    window.copyToClipboard = copyToClipboard;          
	
	
	
	

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const name = button.getAttribute('data-name');
        const id = button.getAttribute('data-id');
        const image = button.getAttribute('data-image');
        const infohash = button.getAttribute('data-infohash') || ''; // Add data-infohash to button if possible

        const imagePreview = image
            ? `<img src="${image}" class="img-fluid rounded shadow-sm" alt="Preview">`
            : '<div class="alert alert-secondary">No image preview</div>';

        // You should fetch these values dynamically if available:
        const seeders = button.getAttribute('data-seeders') || 'N/A';
        const leechers = button.getAttribute('data-leechers') || 'N/A';
        const completed = button.getAttribute('data-completed') || 'N/A';

        const html = `
            <h5><i class="fa-solid fa-magnet"></i> ${name}</h5>
            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    ${imagePreview}
                    <div class="mt-3 text-center">
                        <a href="${baseurl}/download.php?id=${id}" class="btn btn-success btn-sm">
                            <i class="fa fa-download"></i> Download .torrent
                        </a>
                        <a href="magnet:?xt=urn:btih:${infohash}" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-magnet"></i> Magnet Link
                        </a>
                    </div>
                </div>
                <div class="col-md-8">
                    <ul class="list-group list-group-flush shadow-sm">
                        <li class="list-group-item"><a href="${baseurl}/details.php?id=${id}"><i class="fa fa-eye"></i> View Torrent</a></li>
                        <li class="list-group-item"><a href="${baseurl}/details.php?id=${id}#startcomments"><i class="fa fa-comments"></i> View Comments</a></li>
                        <li class="list-group-item"><a href="${baseurl}/upload.php?id=${id}"><i class="fa fa-edit"></i> Edit Torrent</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=torrent_info&id=${id}"><i class="fa fa-info-circle"></i> Torrent Info</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=viewstats&id=${id}"><i class="fa fa-chart-bar"></i> Torrent Stats</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=nuketorrent&id=${id}" class="text-danger"><i class="fa fa-skull-crossbones"></i> Nuke Torrent</a></li>
                        <li class="list-group-item"><a href="${baseurl}/admin/index.php?act=fastdelete&id=${id}" class="text-danger"><i class="fa fa-trash"></i> Delete Torrent</a></li>
                    </ul>
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-success"><i class="fa fa-seedling"></i> Seeders: ${seeders}</span>
                            <span class="badge bg-danger"><i class="fa fa-user-slash"></i> Leechers: ${leechers}</span>
                            <span class="badge bg-secondary"><i class="fa fa-arrow-down"></i> Completed: ${completed}</span>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('${baseurl}/details.php?id=${id}')">
                            <i class="fa fa-copy"></i> Copy Link
                        </button>
                    </div>
                </div>
            </div>
        `;

        content.innerHTML = html;

        // Fetch extra torrent info if you want to override seeders/leechers/completed etc
        fetch(`${baseurl}/admin/torrent_extra.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                content.querySelector('.col-md-8').insertAdjacentHTML('beforeend', `
                    <hr>
                    <p><strong>Size:</strong> ${data.size}</p>
                    <p><strong>Files:</strong> ${data.file_count}</p>
                   
                `);
            });
    });

    
	
	
	
	// Live search functionality
$(document).ready(function () {
  const $input = $("#torrent-search");
  const $results = $("#autocomplete-results");

  let debounceTimer;

  $input.on("input", function () {
    const query = $(this).val().trim();

    clearTimeout(debounceTimer);
    if (query.length < 3) {
      $results.removeClass("show").empty();
      return;
    }

    debounceTimer = setTimeout(() => {
      $.ajax({
        url: "<?= $BASEURL ?>/search_torrents.php",
        dataType: "json",
        data: { input: query },
        success: function (data) {
          $results.empty();

          if (!Array.isArray(data) || data.length === 0) {
            $results.append('<a class="dropdown-item disabled">No results found</a>').addClass("show");
            return;
          }

         
	      data.forEach(item => {
  if (!item.name || !item.id) return;
  const img = item.image_url ? `<img src="${item.image_url}" alt="" style="width:40px;height:auto;margin-right:10px;">` : "";

  // Highlight match in name
  const regex = new RegExp("(" + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ")", "ig");
  const highlightedName = item.name.replace(regex, '<mark>$1</mark>');

  const $option = $(`<a class="dropdown-item d-flex align-items-center" href="${baseurl}/details.php?id=${item.id}">
                      ${img}<span>${highlightedName}</span>
                     </a>`);
  $results.append($option);
});
		  
		  
		  
		 
		  
		  
		  
		  
		  
		  
		  
		  
		  
		  

          $results.addClass("show");
        },
        error: function () {
          $results.html('<a class="dropdown-item disabled">Error retrieving results</a>').addClass("show");
        }
      });
    }, 300); // Debounce delay
  });

  // Hide dropdown when clicking outside
  $(document).on("click", function (e) {
    if (!$(e.target).closest("#torrent-search, #autocomplete-results").length) {
      $results.removeClass("show").empty();
    }
  });
});
	
	
	
	
	
	
	
	

    // Hover preview fix:
    let hoverPreviewDiv = null;
    document.querySelectorAll('.torrent-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function () {
            const img = this.getAttribute('data-image');
            if (!img) return;

            if (hoverPreviewDiv) hoverPreviewDiv.remove();

            hoverPreviewDiv = document.createElement('div');
            hoverPreviewDiv.id = 'hoverPreview';
            hoverPreviewDiv.style.position = 'absolute';
            hoverPreviewDiv.style.zIndex = 9999;
            hoverPreviewDiv.style.top = (this.getBoundingClientRect().top + window.scrollY + 30) + 'px';
            hoverPreviewDiv.style.left = (this.getBoundingClientRect().left + window.scrollX + 30) + 'px';
            hoverPreviewDiv.innerHTML = `<img src="${img}" class="img-thumbnail" style="max-width:150px;">`;
            document.body.appendChild(hoverPreviewDiv);
        });

        btn.addEventListener('mouseleave', () => {
            if (hoverPreviewDiv) {
                hoverPreviewDiv.remove();
                hoverPreviewDiv = null;
            }
        });
    });

    // Show/hide category dropdown based on selected action (looks okay)
    const actionSelect = document.querySelector('select[name="actiontype"]');
    if(actionSelect) {
        actionSelect.addEventListener('change', function () {
            const moveBlock = document.getElementById('movetorrent');
            moveBlock.style.display = this.value === 'move' ? 'block' : 'none';
        });
    }
});

</script>







?>

<!-- Enhanced JavaScript -->
<script>
// Performance monitoring
const pageLoadStart = performance.now();

document.addEventListener('DOMContentLoaded', function() {
    const loadTime = performance.now() - pageLoadStart;
    console.log('🚀 Page loaded in', loadTime.toFixed(2), 'ms');
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Enhanced selection management
let selectedTorrents = new Set();

function toggleAllSelection(source) {
    const checkboxes = document.querySelectorAll('.torrent-checkbox');
    const isChecked = source.checked;
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
        const torrentId = checkbox.value;
        
        if (isChecked) {
            selectedTorrents.add(torrentId);
            checkbox.closest('.torrent-card').classList.add('selected');
        } else {
            selectedTorrents.delete(torrentId);
            checkbox.closest('.torrent-card').classList.remove('selected');
        }
    });
    
    updateSelectionCounter();
    updateExecuteButton();
}

function updateSelectionCounter() {
    const checkboxes = document.querySelectorAll('.torrent-checkbox:checked');
    selectedTorrents = new Set(Array.from(checkboxes).map(cb => cb.value));
    
    const counter = document.getElementById('selectedCounter');
    if (counter) {
        counter.textContent = selectedTorrents.size + ' selected';
        counter.className = selectedTorrents.size > 0 ? 'text-primary fw-bold ms-2' : 'text-muted ms-2';
    }
    
    // Update visual selection state
    document.querySelectorAll('.torrent-card').forEach(card => {
        const checkbox = card.querySelector('.torrent-checkbox');
        if (checkbox && selectedTorrents.has(checkbox.value)) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });
    
    updateExecuteButton();
}

function updateExecuteButton() {
    const executeBtn = document.getElementById('executeBtn');
    const actionSelect = document.getElementById('actionType');
    
    if (executeBtn && actionSelect) {
        const hasSelection = selectedTorrents.size > 0;
        const hasAction = actionSelect.value !== '';
        
        executeBtn.disabled = !(hasSelection && hasAction);
    }
}

function clearSelection() {
    selectedTorrents.clear();
    document.querySelectorAll('.torrent-checkbox').forEach(cb => {
        cb.checked = false;
        cb.closest('.torrent-card').classList.remove('selected');
    });
    updateSelectionCounter();
    updateExecuteButton();
}

function toggleMoveCategory(select) {
    const moveDiv = document.getElementById('moveCategory');
    if (select.value === 'move') {
        moveDiv.style.display = 'block';
    } else {
        moveDiv.style.display = 'none';
    }
    updateExecuteButton();
}

// Utility functions
function clearSearch() {
    document.getElementById('torrent-search').value = '';
}

function resetFilters() {
    window.location.href = '<?= $_this_script_ ?>';
}

// Mobile touch improvements
if ('ontouchstart' in window) {
    document.addEventListener('touchstart', function(e) {
        if (e.target.classList.contains('torrent-btn')) {
            e.target.style.transform = 'scale(0.95)';
        }
    });
    
    document.addEventListener('touchend', function(e) {
        if (e.target.classList.contains('torrent-btn')) {
            e.target.style.transform = '';
        }
    });
}

// Initialize selection counter on load
document.addEventListener('DOMContentLoaded', updateSelectionCounter);

console.log('🎯 Enhanced Torrent Manager loaded successfully');
</script>

<style>
.mobile-visible { display: none; }
@media (max-width: 768px) {
    .mobile-visible { display: block; }
    .mobile-hidden { display: none; }
}

#autocomplete-results {
    position: absolute;
    z-index: 1000;
    width: 100%;
    border: 1px solid #ccc;
    background-color: #fff;
    display: none;
    max-height: 300px;
    overflow-y: auto;
}
#autocomplete-results.show { display: block; }

mark {
    background-color: #ffeb3b;
    color: inherit;
    padding: 0 2px;
    border-radius: 2px;
}

/* Enhanced mobile table styles */
@media (max-width: 768px) {
    .table-responsive table {
        min-width: 600px; /* Prevent table from becoming too narrow */
    }
}
</style>

<?php
stdfoot();
?>