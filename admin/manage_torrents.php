<?php
declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_category.php';
require_once INC_PATH . '/functions_bookmark.php';


class TorrentManager 
{
    private array $errors = [];
    
    public function showErrors(): void {
        global $lang;
        
        if (!empty($this->errors)) {
            $errors = implode('<br>', $this->errors);
            echo '
            <div class="alert-modern alert-modern-danger fade-in-up">
                <div class="alert-modern-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-modern-content">
                    <h5 class="alert-modern-title">' . $lang->global['error'] . '</h5>
                    <p class="alert-modern-message">' . $errors . '</p>
                </div>
                <button type="button" class="alert-modern-close" data-dismiss="alert">
                    <i class="fas fa-times"></i>
                </button>
            </div>';
        }
    }

    public function addError(string $error): void {
        $this->errors[] = $error;
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
			'thirtypercent' => fn() => $this->toggleField($torrentIdsStr, 'thirtypercent'),
            'visible' => fn() => $this->toggleField($torrentIdsStr, 'visible'),
            'anonymous' => fn() => $this->toggleField($torrentIdsStr, 'anonymous'),
            'banned' => fn() => $this->toggleField($torrentIdsStr, 'banned'),
            'nuke' => fn() => $this->toggleField($torrentIdsStr, 'isnuked'),
            'doubleupload' => fn() => $this->toggleField($torrentIdsStr, 'doubleupload'),
            'openclose' => fn() => $this->toggleField($torrentIdsStr, 'allowcomments'),
        ];

        if (isset($actions[$actionType])) {
            $actions[$actionType]();
            $_SESSION['action_success'] = 'Action completed successfully!';
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

if (!defined('STAFF_PANEL')) {
    exit('
    <div class="alert-modern alert-modern-danger text-center">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

define('MT_VERSION', 'v1.0 by xam');

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
            'silver' => "t.silver = 'yes'",
			'thirtypercent' => "t.thirtypercent = 'yes'",
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
            SELECT t.*, u.username, u.usergroup, u.avatar, c.name as category_name 
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
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        $torrentManager->addError('Security check failed. Please refresh the page and try again.');
    } else {
        $torrentManager->handleUpdate($_POST);
    }
}



// Handle quick_edit из модалки
if ($do === 'quick_edit') {
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        $_SESSION['action_success'] = null;
        header('Location: ' . $_this_script_ . '');
        exit;
    }

    $tid  = (int)($_POST['torrent_id'] ?? 0);
    $name = $db->escape_string(htmlspecialchars_uni($_POST['name'] ?? ''));
    $cat  = (int)($_POST['category'] ?? 0);

    if ($tid > 0 && $name !== '' && $cat > 0) {
        $db->update_query('torrents', [
            'name'     => $name,
            'category' => $cat,
        ], "id = $tid");
        $_SESSION['action_success'] = 'Torrent #' . $tid . ' updated successfully!';
    }

    header('Location: ' . $_this_script_ . '');
    exit;
}






stdhead('Manage Torrents', true, 'supernote');

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/manage_torrents.css">';

$torrentManager->showErrors();

// Build ordering
$orderBy = 't.added DESC';
$allowedOrders = ['name', 'owner', 'category', 'added'];
$currentOrder = $_GET['orderby'] ?? '';
$currentWhat = $_GET['what'] ?? 'desc';
$nextWhat = $currentWhat === 'desc' ? 'asc' : 'desc';

if (isset($_GET['orderby']) && in_array($_GET['orderby'], $allowedOrders)) {
    $what = $currentWhat === 'desc' ? 'DESC' : 'ASC';
    $orderBy = "t.{$_GET['orderby']} $what";
    $orderLink = "orderby={$_GET['orderby']}&amp;what=$nextWhat&amp;";
}

// Get torrent count and setup pagination
$startTime = microtime(true);
$countQuery = $db->sql_query($queryBuilder->getCountQuery());
$totalTorrents = (int)$db->fetch_field($countQuery, 'total');
$queryTime = round((microtime(true) - $startTime) * 1000, 2);

// Adaptive pagination based on performance
$torrentsPerPage = max(20, (int)($CURUSER['torrentsperpage'] ?? $ts_perpage ?? 50));
if ($queryTime > 1000 && $totalTorrents > 1000) {
    $torrentsPerPage = min($torrentsPerPage, 25);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$start = ($page - 1) * $torrentsPerPage;

$multipage = multipage($totalTorrents, $torrentsPerPage, $page, $_this_script_ . '' . ($orderLink ?? '') . $queryBuilder->extralink);

// Build dropdowns
require_once INC_PATH . '/functions_category.php';
$categoryDropdown = ts_category_list('browsecategory', $browsecategory, '<option value="0">📁 All Categories</option>');

$searchTypeDropdown = '
<select class="form-select form-select-sm border" name="searchtype" style="border-radius: 0.5rem;">
    <option value="">⚡ All Types</option>
    <option value="free"' . ($searchtype === 'free' ? ' selected' : '') . '>🎁 Free Torrents</option>
    <option value="silver"' . ($searchtype === 'silver' ? ' selected' : '') . '>⭐ Silver Torrents</option>
    <option value="thirtypercent"' . ($searchtype === 'thirtypercent' ? ' selected' : '') . '>🟣 30% Leech</option>
    <option value="recommend"' . ($searchtype === 'recommend' ? ' selected' : '') . '>📌 Sticky Torrents</option>
    <option value="doubleuploads"' . ($searchtype === 'doubleuploads' ? ' selected' : '') . '>⚡ 2x Upload</option>
    <option value="deadonly"' . ($searchtype === 'deadonly' ? ' selected' : '') . '>💀 Dead Torrents</option>
</select>';

// Main interface
echo '
<div class="container mt-3">
    <!-- Hero Header -->
    <div class="hero-header text-white">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="fas fa-cogs me-3"></i>
                    Torrent Management
                </h1>
                <p class="lead mb-0 opacity-90">
                    <i class="fas fa-database me-2"></i>
                    Managing <strong>' . number_format($totalTorrents) . '</strong> torrents
                    <span class="badge bg-white bg-opacity-25 ms-2">
                        <i class="fas fa-tachometer-alt me-1"></i>' . $queryTime . 'ms
                    </span>
                </p>
            </div>
            <div class="col-md-5">
                <div class="hero-stats">
                    <div class="hero-stat-card">
                        <div class="hero-stat-value">' . number_format($totalTorrents) . '</div>
                        <div class="hero-stat-label">Total Torrents</div>
                    </div>
                    <div class="hero-stat-card">
                        <div class="hero-stat-value">' . $torrentsPerPage . '</div>
                        <div class="hero-stat-label">Per Page</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Card -->
    <div class="search-card">
        <form method="get" action="' . $_this_script_ . '" id="searchForm" class="row g-3 align-items-end">
            <input type="hidden" name="act" value="manage_torrents">
            
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small text-muted text-uppercase mb-2">
                    <i class="fas fa-search me-1"></i>Search Torrents
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="fas fa-search text-primary"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="searchword" 
                           value="' . $searchword . '" placeholder="Enter torrent name..." 
                           id="torrent-search" style="border-radius: 0 0.5rem 0.5rem 0;">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="Clear">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <small class="text-muted">Minimum 2 characters</small>
            </div>
            
            <div class="col-6 col-md-3">
                <label class="form-label fw-semibold small text-muted text-uppercase mb-2">
                    <i class="fas fa-folder me-1"></i>Category
                </label>
                ' . $categoryDropdown . '
            </div>
            
            <div class="col-6 col-md-3">
                <label class="form-label fw-semibold small text-muted text-uppercase mb-2">
                    <i class="fas fa-filter me-1"></i>Filter
                </label>
                ' . $searchTypeDropdown . '
            </div>
            
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 mb-2" id="searchBtn">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
                <button type="button" class="btn btn-outline-secondary w-100 btn-sm" onclick="resetFilters()">
                    <i class="fas fa-redo-alt me-2"></i>Reset
                </button>
            </div>
        </form>
    </div>

    ' . ($totalTorrents > 5000 ? '
    <div class="alert-modern alert-modern-warning mb-4">
        <div class="alert-modern-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="alert-modern-content">
            <h5 class="alert-modern-title">Large Dataset Warning</h5>
            <p class="alert-modern-message">' . number_format($totalTorrents) . ' torrents found. Use specific filters for better performance.</p>
        </div>
        <button type="button" class="alert-modern-close" data-dismiss="alert"><i class="fas fa-times"></i></button>
    </div>' : '') . '

    <!-- Pagination Top -->
    <div class="mb-4" id="paginationTop">' . $multipage . '</div>

    <!-- Torrents Table -->
    <form method="post" action="' . $_this_script_ . '" name="update" id="torrentForm">
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="page" value="' . $page . '">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        
        <div class="torrent-table-container">
            <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                <h5 class="mb-0 text-primary">
                    <i class="fas fa-list me-2"></i>
                    Torrent List
                    <small class="text-muted fw-normal ms-2">Page ' . $page . '</small>
                </h5>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="selectAll" onclick="toggleAllSelection(this)">
                        <label class="form-check-label small" for="selectAll">Select All</label>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <i class="fas fa-database me-1"></i>' . number_format($totalTorrents) . '
                    </span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="torrent-table">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 35%">
                                <a href="' . $_this_script_ . '?act=manage_torrents&amp;orderby=name&amp;what=' . $nextWhat . '&amp;' . $queryBuilder->extralink . '" 
                                   class="text-decoration-none text-dark sort-link">
                                    <i class="fas fa-file-alt me-2"></i>Torrent Name
                                    ' . ($currentOrder === 'name' ? '<i class="fas fa-sort-' . ($currentWhat === 'desc' ? 'down' : 'up') . ' ms-1"></i>' : '') . '
                                </a>
                            </th>
                            <th class="mobile-hidden" style="width: 15%">Status</th>
                            <th style="width: 15%">
                                <a href="' . $_this_script_ . '?act=manage_torrents&amp;orderby=owner&amp;what=' . $nextWhat . '&amp;' . $queryBuilder->extralink . '" 
                                   class="text-decoration-none text-dark sort-link">
                                    <i class="fas fa-user me-2"></i>Uploader
                                    ' . ($currentOrder === 'owner' ? '<i class="fas fa-sort-' . ($currentWhat === 'desc' ? 'down' : 'up') . ' ms-1"></i>' : '') . '
                                </a>
                            </th>
                            <th class="mobile-hidden" style="width: 12%">Category</th>
                            <th style="width: 15%">
                                <a href="' . $_this_script_ . '?act=manage_torrents&amp;orderby=added&amp;what=' . $nextWhat . '&amp;' . $queryBuilder->extralink . '" 
                                   class="text-decoration-none text-dark sort-link">
                                    <i class="fas fa-calendar me-2"></i>Added
                                    ' . ($currentOrder === 'added' ? '<i class="fas fa-sort-' . ($currentWhat === 'desc' ? 'down' : 'up') . ' ms-1"></i>' : '') . '
                                </a>
                            </th>
                            <th class="pe-4 text-center" style="width: 8%">
                                <i class="fas fa-check-square text-muted"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="torrentTableBody">';

// Fetch and display torrents
$queryStart = microtime(true);
$query = $db->sql_query($queryBuilder->getMainQuery($orderBy, $start, $torrentsPerPage));
$queryTime = round((microtime(true) - $queryStart) * 1000, 2);

$torrentCount = 0;
$totalSize = 0;

while ($torrent = $db->fetch_array($query)) {
    $torrentCount++;
    $totalSize += $torrent['size'];
    $flags = GetTorrentTags($torrent);
    $userAvatar = format_avatar($torrent['avatar'] ?? '', $torrent['avatardimensions'] ?? '');
    
    echo '
    <tr class="torrent-row" data-id="' . $torrent['id'] . '" data-size="' . $torrent['size'] . '">
        <td class="ps-4" data-label="Torrent Name">
            <div class="d-flex align-items-start gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-circle torrent-btn"
                        data-bs-toggle="modal" data-bs-target="#manageTorrentModal"
                        data-id="' . $torrent['id'] . '"
                        data-name="' . htmlspecialchars($torrent['name']) . '"
                        data-image="' . htmlspecialchars($torrent['t_image'] ?? '') . '"
                        data-infohash="' . htmlspecialchars($torrent['info_hash']) . '"
                        data-seeders="' . $torrent['seeders'] . '"
                        data-leechers="' . $torrent['leechers'] . '"
                        data-completed="' . $torrent['times_completed'] . '"
                        style="width: 32px; height: 32px;">
                    <i class="fas fa-cog fa-sm"></i>
                </button>
                <div>
                    <a href="' . $BASEURL . '/' . get_torrent_link($torrent['id']) . '" 
                       class="torrent-name-link d-block mb-1">
                       ' . htmlspecialchars($torrent['name']) . '
                    </a>
                    <div class="text-muted small">
                        <span class="me-3"><i class="fas fa-hdd me-1"></i>' . mksize($torrent['size']) . '</span>
                        <span><i class="fas fa-download me-1"></i>' . ts_nf($torrent['times_completed']) . '</span>
                        <span class="ms-2"><i class="fas fa-chart-line me-1"></i>S:' . $torrent['seeders'] . ' L:' . $torrent['leechers'] . '</span>
                    </div>
                    <div class="mt-2 mobile-visible d-md-none">
                        ' . $flags . '
                    </div>
                </div>
            </div>
        </td>
        <td class="mobile-hidden" data-label="Status">
            <div class="d-flex flex-wrap gap-1">' . $flags . '</div>
        </td>
        <td data-label="Uploader">
            <div class="d-flex align-items-center gap-2">
                ' . ($userAvatar['image'] ? '<img src="' . $userAvatar['image'] . '" class="rounded-circle" width="28" height="28" alt="" loading="lazy">' : '<div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:28px;height:28px;"><i class="fas fa-user text-muted fa-sm"></i></div>') . '
                <a href="' . $BASEURL . '/' . get_profile_link($torrent['owner']) . '" class="text-decoration-none small fw-semibold">
                    ' . format_name($torrent['username'], $torrent['usergroup']) . '
                </a>
            </div>
        </td>
        <td class="mobile-hidden" data-label="Category">
            <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                <i class="fas fa-tag me-1"></i>' . htmlspecialchars($torrent['category_name']) . '
            </span>
        </td>
        <td data-label="Added">
            <div class="text-muted small">
                <div><i class="far fa-calendar me-1"></i>' . my_datee($dateformat, $torrent['added']) . '</div>
                <div class="mobile-hidden"><i class="far fa-clock me-1"></i>' . my_datee($timeformat, $torrent['added']) . '</div>
            </div>
        </td>
        <td class="pe-4 text-center" data-label="Select">
            <input type="checkbox" class="torrent-checkbox" name="torrentid[]" 
                   value="' . $torrent['id'] . '" onchange="updateSelectionCounter()">
        </td>
    </tr>';
}

if ($torrentCount === 0) {
    echo '
    <tr>
        <td colspan="6" class="text-center py-5">
            <div class="text-muted">
                <i class="fas fa-inbox fa-4x mb-3 opacity-25"></i>
                <h5 class="mb-2">No torrents found</h5>
                <p class="mb-3">Try adjusting your search criteria</p>
                <button type="button" class="btn btn-outline-primary" onclick="resetFilters()">
                    <i class="fas fa-redo-alt me-2"></i>Reset Filters
                </button>
            </div>
        </td>
    </table>';
}

echo '
                    </tbody>
                </table>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div class="me-2">
                                <span class="fw-semibold">Bulk Actions:</span>
                                <span class="badge bg-primary ms-2" id="selectedCounter">0 selected</span>
                            </div>
                            <select class="form-select form-select-sm w-auto" name="actiontype" id="actionType" 
                                    onchange="toggleMoveCategory(this)" style="border-radius: 0.5rem;">
                                <option value="">— Select Action —</option>
                                <option value="move">📁 Move Torrents</option>
                                <option value="delete">🗑️ Delete Torrents</option>
                                <option value="sticky">📌 Toggle Sticky</option>
                                <option value="free">🎁 Toggle Free</option>
                                <option value="silver">⭐ Toggle Silver</option>
								<option value="thirtypercent">🟣 Toggle 30% Leech</option>
                                <option value="visible">👁️ Toggle Visibility</option>
                                <option value="anonymous">🕵️ Toggle Anonymous</option>
                                <option value="banned">🚫 Toggle Ban</option>
                                <option value="doubleupload">⚡ Toggle 2x Upload</option>
                            </select>
                            <div id="moveCategory" style="display: none;">
                                ' . ts_category_list('category', 0, '<option value="0">📁 Select Category...</option>') . '
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" id="executeBtn" disabled>
                                <i class="fas fa-play me-2"></i>Execute
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                                <i class="fas fa-times me-2"></i>Clear
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <div class="text-muted small">
                            <div><i class="fas fa-chart-bar me-1"></i>' . number_format($torrentCount) . ' items • ' . mksize($totalSize) . '</div>
                            <div><i class="fas fa-tachometer-alt me-1"></i>' . $queryTime . 'ms load time</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Bottom Pagination -->
    <div class="mt-4">' . $multipage . '</div>
</div>';

// Modal
echo '
<div class="modal fade" id="manageTorrentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-xl rounded-4">
            <div class="modal-header bg-gradient-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-cog me-2"></i>Manage Torrent</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="manageTorrentContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading torrent information...</p>
                </div>
            </div>
        </div>
    </div>
</div>';

if (isset($_SESSION['action_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 p-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast show bg-success text-white border-0 shadow-lg" role="alert">
            <div class="toast-header bg-success text-white border-0">
                <i class="fas fa-check-circle me-2"></i>
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"><?= htmlspecialchars($_SESSION['action_success']) ?></div>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
});
</script>
<?php unset($_SESSION['action_success']); endif; ?>

<script>
window.manageBaseUrl = "<?= $BASEURL ?>";
window.manageTorrentScript = "<?= $_this_script_ ?>";
window.torrentCount = <?= $torrentCount ?>;
window.totalTorrents = <?= $totalTorrents ?>;
</script>


<script src="<?= $BASEURL ?>/admin/scripts/manage_torrents.js"></script>


<?php
stdfoot();
?>