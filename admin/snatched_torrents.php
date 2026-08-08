<?php

declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border m-3"><i class="fas fa-exclamation-triangle me-2 text-warning"></i><b class="text-dark">Error!</b> Direct initialization of this file is not allowed.</div>');
}



define('ST_VERSION', '0.7');
stdhead('All Snatched Torrents');

// Обработка поиска
$search_user = isset($_GET['search_user']) ? trim($_GET['search_user']) : '';
$search_torrent = isset($_GET['search_torrent']) ? trim($_GET['search_torrent']) : '';
$search_user_id = isset($_GET['search_user_id']) ? intval($_GET['search_user_id']) : 0;
$search_torrent_id = isset($_GET['search_torrent_id']) ? intval($_GET['search_torrent_id']) : 0;



// Формируем условия поиска
$where_conditions = [];
$where_params = [];
$search_params = [];

if (!empty($search_user)) {
    $where_conditions[] = "u.username LIKE ?";
    $where_params[] = '%' . $db->escape_string_like($search_user) . '%';
    $search_params[] = "search_user=" . urlencode($search_user);
}

if (!empty($search_torrent)) {
    $where_conditions[] = "t.name LIKE ?";
    $where_params[] = '%' . $db->escape_string_like($search_torrent) . '%';
    $search_params[] = "search_torrent=" . urlencode($search_torrent);
}

if ($search_user_id > 0) {
    $where_conditions[] = "s.userid = ?";
    $where_params[] = $search_user_id;
    $search_params[] = "search_user_id=" . $search_user_id;
}

if ($search_torrent_id > 0) {
    $where_conditions[] = "s.torrentid = ?";
    $where_params[] = $search_torrent_id;
    $search_params[] = "search_torrent_id=" . $search_torrent_id;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Получаем общее количество
$count_query = "SELECT COUNT(*) AS cnt FROM snatched s 
               LEFT JOIN torrents t ON (s.torrentid=t.id) 
               LEFT JOIN users u ON (s.userid=u.id) 
               " . $where_clause;

$res1 = $db->sql_query_prepared($count_query, $where_params);
$row1 = $db->fetch_array($res1);
$count = $row1['cnt'];
$count1 = number_format((int)$count);





// Добавляем параметры поиска в URL пагинации
$search_url = !empty($search_params) ? '&' . implode('&', $search_params) : '';


echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/popover.js"></script>';






?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    
    // Сортировка таблицы
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.rows);
            const columnIndex = Array.from(this.parentElement.cells).indexOf(this);
            const isAsc = !this.classList.contains('asc');
            
            // Сортируем строки
            rows.sort((a, b) => {
                const valA = a.cells[columnIndex].textContent.trim();
                const valB = b.cells[columnIndex].textContent.trim();
                
                // Проверяем, являются ли значения числовыми
                const numA = parseFloat(valA);
                const numB = parseFloat(valB);
                
                if (!isNaN(numA) && !isNaN(numB)) {
                    return isAsc ? numA - numB : numB - numA;
                } else {
                    return isAsc ? 
                        valA.localeCompare(valB) : 
                        valB.localeCompare(valA);
                }
            });
            
            // Очищаем tbody и добавляем отсортированные строки
            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }
            
            rows.forEach(row => {
                tbody.appendChild(row);
            });
            
            // Обновляем индикаторы сортировки
            sortableHeaders.forEach(h => {
                h.classList.remove('asc', 'desc');
                h.querySelector('.sort-indicator')?.remove();
            });
            
            this.classList.add(isAsc ? 'asc' : 'desc');
            
            // Добавляем индикатор сортировки
            const indicator = document.createElement('span');
            indicator.className = 'sort-indicator ms-1';
            indicator.textContent = isAsc ? '↑' : '↓';
            this.appendChild(indicator);
        });
    });
    
    // Очистка поиска
    const clearSearchBtn = document.getElementById('clearSearch');
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            window.location.href = '<?php echo $_this_script_; ?>';
        });
    }
});

// Вспомогательная функция для проверки числового значения
function isNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --rules-accent: var(--bs-primary);
    --rules-accent-strong: var(--bs-primary-text-emphasis, var(--bs-primary));
    --rules-accent-soft: var(--bs-primary-bg-subtle, rgba(13, 110, 253, .12));
}

.st-masthead {
    padding: 1.75rem 1.5rem;
    margin-bottom: 1.25rem;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
}

.st-masthead__eyebrow {
    display: inline-block;
    font-family: 'Oswald', sans-serif;
    font-weight: 600;
    font-size: .72rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--rules-accent-strong);
    background: var(--rules-accent-soft);
    border: 1px solid var(--rules-accent);
    border-radius: 999px;
    padding: .3rem .85rem;
    margin-bottom: .75rem;
}

.st-masthead__title {
    font-family: 'Oswald', sans-serif;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .01em;
    font-size: clamp(1.5rem, 3.4vw, 2rem);
    color: var(--bs-emphasis-color);
    margin: 0;
}

.st-panel {
    border: 1px solid var(--bs-border-color) !important;
    border-radius: .75rem;
    background: var(--bs-body-bg);
    overflow: hidden;
}

.st-panel .card-header {
    background: transparent !important;
    color: var(--bs-emphasis-color) !important;
    border-bottom: 1px solid var(--bs-border-color);
    border-left: 4px solid var(--rules-accent);
    border-radius: 0 !important;
}

.st-panel .card-header h4 {
    font-family: 'Oswald', sans-serif;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-size: 1.05rem;
}

.st-count-badge {
    font-family: 'Oswald', sans-serif;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .04em;
    color: var(--rules-accent-strong) !important;
    background: var(--rules-accent-soft) !important;
    border: 1px solid var(--rules-accent) !important;
}

.st-panel table thead th {
    font-family: 'Oswald', sans-serif;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--bs-secondary-color) !important;
    background: var(--bs-tertiary-bg) !important;
    border-top: none;
    border-bottom: 1px solid var(--bs-border-color) !important;
}

.st-panel .table-hover tbody tr:hover {
    background-color: var(--rules-accent-soft) !important;
    transform: translateY(-1px);
    transition: all .2s ease;
}

.sortable {
    position: relative;
    user-select: none;
}

.sortable:hover {
    background-color: var(--rules-accent-soft) !important;
}

.sort-indicator {
    font-weight: bold;
    color: var(--rules-accent);
}

.asc .sort-indicator {
    color: #198754;
}

.desc .sort-indicator {
    color: #dc3545;
}

.st-panel .progress {
    border-radius: 10px;
    overflow: hidden;
}

.nav-avatar {
    border-radius: 50%;
    border: 2px solid var(--bs-border-color);
    object-fit: cover;
}
</style>


<div class="container mt-3">
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">

            <div class="st-masthead">
                <span class="st-masthead__eyebrow">Admin / Torrents</span>
                <h1 class="st-masthead__title"><i class="fas fa-download me-2" style="color: var(--rules-accent)"></i>All Snatched Torrents</h1>
            </div>

            <!-- Header Card -->
            <div class="card st-panel shadow-sm mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-download me-2" style="color: var(--rules-accent)"></i>All Snatched Torrents
                        </h4>
                        <span class="badge st-count-badge fs-6">
                            <i class="fas fa-database me-1"></i>Total: <?php echo $count1; ?> snatched
                        </span>
                    </div>
                </div>
                
                <!-- Search Form -->
                <div class="card-body bg-light">
                    <form method="GET" action="<?php echo $_this_script_; ?>" id="searchForm">
					
					    <input type="hidden" name="act" value="snatched_torrents">
						
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Search by Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="search_user" 
                                           value="<?php echo htmlspecialchars($search_user); ?>" 
                                           placeholder="Username...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Search by User ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="number" class="form-control" name="search_user_id" 
                                           value="<?php echo $search_user_id ?: ''; ?>" 
                                           placeholder="User ID...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Search by Torrent</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                                    <input type="text" class="form-control" name="search_torrent" 
                                           value="<?php echo htmlspecialchars($search_torrent); ?>" 
                                           placeholder="Torrent name...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Search by Torrent ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="number" class="form-control" name="search_torrent_id" 
                                           value="<?php echo $search_torrent_id ?: ''; ?>" 
                                           placeholder="Torrent ID...">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                    <button type="button" id="clearSearch" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </button>
                                    <?php if (!empty($where_conditions)): ?>
                                    <span class="badge bg-info align-self-center">
                                        <i class="fas fa-filter me-1"></i>Filter active
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            // Pagination settings
            $torrentsperpage = (int)($CURUSER['torrentsperpage'] <> 0 ? intval($CURUSER['torrentsperpage']) : $ts_perpage);

            if($torrentsperpage < 1)
			{
                 $torrentsperpage = 20;
            }

            $perpage = $torrentsperpage;
            
            if($mybb->get_input('page', MyBB::INPUT_INT) > 0) {
                $page = $mybb->get_input('page', MyBB::INPUT_INT);
                $start = ($page-1) * $perpage;
                $pages = ceil($count / $perpage);
                if($page > $pages || $page <= 0) {
                    $start = 0;
                    $page = 1;
                }
            } else {
                $start = 0;
                $page = 1;
            }
            
            
			
			
			//$page_url = str_replace($_this_script_ . $search_url);
			$page_url = str_replace('', '', $_this_script_ . $search_url);
            $multipage = multipage((int)$count, $perpage, $page, $page_url);
			
			
            
            // Display pagination
            if($count > $perpage) {
                echo '<div class="card st-panel mb-3">';
                echo '<div class="card-body py-2">';
                echo '<div class="d-flex justify-content-between align-items-center">';
                echo '<div class="small text-muted">Showing ' . ($start + 1) . ' to ' . min($start + $perpage, $count) . ' of ' . $count . ' records</div>';
                echo '<div>' . $multipage . '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            
            // Main query
            $sql = "SELECT s.*, t.name, t.size, t.added, u.username as uname, u.id as uid, u.usergroup, u.avatar, u.avatardimensions, 
                           u.donor, u.enabled, u.warned, u.leechwarn
                    FROM snatched s 
                    LEFT JOIN torrents t ON (s.torrentid=t.id) 
                    LEFT JOIN users u ON (s.userid=u.id) 
                    " . $where_clause . "
                    ORDER BY s.to_go DESC 
                    LIMIT ?, ?";
            
            $result = $db->sql_query_prepared($sql, array_merge($where_params, [(int)$start, (int)$perpage]));
            
            if ($db->num_rows($result) != 0) {
            ?>
            
            <!-- Main Data Card -->
            <div class="card st-panel shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="sortable text-dark" style="cursor: pointer;">
                                        <i class="fas fa-user me-1 text-muted"></i>User
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="sortable text-dark" style="cursor: pointer;">
                                        <i class="fas fa-file-alt me-1 text-muted"></i>Torrent
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="sortable text-end text-dark" style="cursor: pointer;">
                                        <i class="fas fa-upload me-1 text-muted"></i>Uploaded
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="sortable text-end text-dark" style="cursor: pointer;">
                                        <i class="fas fa-download me-1 text-muted"></i>Downloaded
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="sortable text-dark" style="cursor: pointer;">
                                        <i class="fas fa-play me-1 text-muted"></i>Started
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="sortable text-dark" style="cursor: pointer;">
                                        <i class="fas fa-check-circle me-1 text-muted"></i>Completed
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="sortable text-center text-dark" style="cursor: pointer;">
                                        <i class="fas fa-seedling me-1 text-muted"></i>Seeding
                                        <i class="fas fa-sort ms-1 text-muted"></i>
                                    </th>
                                    <th class="text-center text-dark">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                while ($row = $db->fetch_array($result)) {
                                    $progress = 0;
                                    if ($row['downloaded'] > 0 && $row['size'] > 0) {
                                        $progress = min(100, round(($row['downloaded'] / $row['size']) * 100));
                                    }
                                    
                                    // Получаем аватар пользователя
                                   $useravatar = format_avatar($row['avatar'], $row['avatardimensions']);
                                   $user_avatar = '<img class="nav-avatar" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
								   
								   
								   
								   $torrent_name = htmlspecialchars_uni($row['name'] ?? '');
                                   $short_name = cutename($torrent_name);
                                   $torrent_link = $BASEURL . '/' . get_torrent_link($row['torrentid']);
                                   $formatted_name = isset($row['uname']) ? htmlspecialchars($row['uname'], ENT_QUOTES) : 'Anonymous';
                                   $torrent_added = isset($row['added']) ? date('Y-m-d H:i', $row['added']) : 'N/A';


                                   $popover_title = '📁 ' . htmlspecialchars(cutename($torrent_name, 20), ENT_QUOTES);


                                   $popover_content = htmlspecialchars('
                                      <div class="torrent-popover">
                                   <div class="mb-2">
                                  <strong>📂 Full Name:</strong><br>
                                  <span class="text-break small">' . $torrent_name . '</span>
                                  </div>
                                  <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2 small text-muted">
                                  <span><i class="fas fa-user me-1"></i>' . $formatted_name . '</span>
                                  <span><i class="fas fa-clock me-1"></i>' . $torrent_added . '</span>
                                  </div>
                                  </div>
                                  ', ENT_QUOTES);
								  
								  
								  
								  
								  $progress_title = "Download Progress";
$progress_content = htmlspecialchars("
    <div class='progress-popover'>
        <strong>Current Progress:</strong><br>
        <span class='badge " . ($progress == 100 ? 'bg-success' : 'bg-warning') . "'>$progress%</span>
        <div class='mt-2 small text-muted'>
            " . ($progress == 100 ? '✅ Download completed' : '🔄 Download in progress') . "
        </div>
    </div>
", ENT_QUOTES);
					


			$completed_status = $progress > 0 ? "{$progress}% complete" : "Not started";


$badge_title = "Completion Status";
$badge_content = htmlspecialchars('
    <div class="completion-popover">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-times text-danger me-2"></i>
            <strong>Status:</strong>
        </div>
        <div class="small">
            <span class="text-danger">❌ ' . ($progress > 0 ? "Not completed ({$progress}%)" : 'Not started') . '</span>
            <div class="mt-1 text-muted">
                <i class="fas fa-info-circle me-1"></i>This torrent download is not finished yet.
            </div>
        </div>
    </div>
', ENT_QUOTES);			
								  
								  
								  
								  
								  
								  
   


                                ?>
                                <tr class="border-bottom">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <?php echo $user_avatar; ?>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <a href="<?php echo $BASEURL . '/' . get_profile_link($row['uid']); ?>" 
                                                   class="text-decoration-none fw-semibold text-dark d-block">
                                                    <?php echo format_name($row['uname'], $row['usergroup']); ?>
                                                </a>
                                                <div class="small text-muted mt-1">
                                                    <?php echo get_user_icons($row); ?>
                                                </div>
                                                <small class="text-muted">ID: <?php echo $row['uid']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        
										
								    <a href="<?php echo $torrent_link; ?>" 
                                       class="text-decoration-none torrent-link" 
                                       data-bs-toggle="popover" 
                                       data-bs-placement="top"
                                       data-bs-title="<?php echo $popover_title; ?>"
                                       data-bs-content="<?php echo $popover_content; ?>"
                                       data-bs-html="true">
                                       <i class="fas fa-magnet text-danger me-1"></i>
                                       <?php echo $short_name; ?>
                                    </a>
										

										
                                        <br>
                                        <small class="text-muted">ID: <?php echo $row['torrentid']; ?></small>
                                    </td>
                                    <td class="text-end fw-semibold text-success">
                                        <?php echo mksize($row['uploaded']); ?>
                                    </td>
                                    <td class="text-end fw-semibold text-danger">
                                        <?php echo mksize($row['downloaded']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                            <?php echo my_datee($dateformat, $row['startdat']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo my_datee($timeformat, $row['startdat']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($row['completedat'] > 0) { ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                <?php echo my_datee($dateformat, $row['completedat']); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?php echo my_datee($timeformat, $row['completedat']); ?></small>
                                        <?php } else { ?>
                                            
											
											<span class="badge bg-light text-muted border completion-badge" 
      data-bs-toggle="popover" 
      data-bs-title="<?php echo $badge_title; ?>"
      data-bs-content="<?php echo $badge_content; ?>"
      data-bs-html="true"
      data-bs-trigger="hover focus">
    <i class="fas fa-times me-1 text-danger"></i><?php echo htmlspecialchars($completed_status, ENT_QUOTES); ?>
</span>
											
											
											
                                        <?php } ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['seeder'] == 'yes') { ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                                                <i class="fas fa-check me-1"></i>YES
                                            </span>
                                        <?php } else { ?>
                                            <span class="badge bg-light text-muted border px-3 py-2">
                                                <i class="fas fa-times me-1"></i>NO
                                            </span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                       
									   
									   
									   <div class="progress bg-light" style="height: 8px;" 
     data-bs-toggle="popover" 
     data-bs-title="<?php echo $progress_title; ?>"
     data-bs-content="<?php echo $progress_content; ?>"
     data-bs-html="true">
    <div class="progress-bar <?php echo $progress == 100 ? 'bg-success' : 'bg-warning'; ?>" 
         role="progressbar" 
         style="width: <?php echo $progress; ?>%" 
         aria-valuenow="<?php echo $progress; ?>" 
         aria-valuemin="0" 
         aria-valuemax="100">
    </div>
</div>
									   
									   
									   
									   
									   
									   
									   
									   
                                        <small class="text-muted d-block text-center"><?php echo $progress; ?>%</small>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php
            } else {
                echo '<div class="card st-panel shadow-sm">';
                echo '<div class="card-body text-center py-5">';
                echo '<i class="fas fa-inbox fa-3x text-muted mb-3"></i>';
                if (!empty($where_conditions)) {
                    echo '<h4 class="text-muted">No results found</h4>';
                    echo '<p class="text-muted">No snatched torrents match your search criteria.</p>';
                } else {
                    echo '<h4 class="text-muted">No snatched torrents found</h4>';
                    echo '<p class="text-muted">There are currently no snatched torrents in the database.</p>';
                }
                echo '</div>';
                echo '</div>';
            }

            // Bottom pagination
            if($count > $perpage) {
                echo '<div class="card st-panel mt-3">';
                echo '<div class="card-body py-2">';
                echo '<div class="d-flex justify-content-between align-items-center">';
                echo '<div class="small text-muted">Showing ' . ($start + 1) . ' to ' . min($start + $perpage, $count) . ' of ' . $count . ' records</div>';
                echo '<div>' . $multipage . '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            ?>
         </div>
        </div>
    </div>
</div>

<style>
.input-group-text {
    background: var(--bs-tertiary-bg);
    border-color: var(--bs-border-color);
}
</style>

<?php
stdfoot();
?>