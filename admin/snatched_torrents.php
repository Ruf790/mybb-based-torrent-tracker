<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-light border m-3"><i class="fas fa-exclamation-triangle me-2 text-warning"></i><b class="text-dark">Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('ST_VERSION', '0.4 by xam');
stdhead('All Snatched Torrents');

// Обработка поиска
$search_user = isset($_GET['search_user']) ? $db->escape_string(trim($_GET['search_user'])) : '';
$search_torrent = isset($_GET['search_torrent']) ? $db->escape_string(trim($_GET['search_torrent'])) : '';
$search_user_id = isset($_GET['search_user_id']) ? intval($_GET['search_user_id']) : 0;
$search_torrent_id = isset($_GET['search_torrent_id']) ? intval($_GET['search_torrent_id']) : 0;



// Формируем условия поиска
$where_conditions = [];
$search_params = [];

if (!empty($search_user)) {
    $where_conditions[] = "u.username LIKE '%" . $search_user . "%'";
    $search_params[] = "search_user=" . urlencode($search_user);
}

if (!empty($search_torrent)) {
    $where_conditions[] = "t.name LIKE '%" . $search_torrent . "%'";
    $search_params[] = "search_torrent=" . urlencode($search_torrent);
}

if ($search_user_id > 0) {
    $where_conditions[] = "s.userid = " . $search_user_id;
    $search_params[] = "search_user_id=" . $search_user_id;
}

if ($search_torrent_id > 0) {
    $where_conditions[] = "s.torrentid = " . $search_torrent_id;
    $search_params[] = "search_torrent_id=" . $search_torrent_id;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Получаем общее количество
$count_query = "SELECT COUNT(*) FROM snatched s 
               LEFT JOIN torrents t ON (s.torrentid=t.id) 
               LEFT JOIN users u ON (s.userid=u.id) 
               " . $where_clause;

$res1 = $db->sql_query($count_query);
$row1 = mysqli_fetch_array($res1);
$count = $row1[0];
$count1 = number_format($count);





// Добавляем параметры поиска в URL пагинации
$search_url = !empty($search_params) ? '&' . implode('&', $search_params) : '';









?>

<script>
$(document).ready(function(){
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Добавляем сортировку таблицы
    $('.sortable').click(function(){
        var table = $(this).parents('table').eq(0);
        var rows = table.find('tr:gt(0)').toArray().sort(comparator($(this).index()));
        this.asc = !this.asc;
        if (!this.asc){ rows = rows.reverse(); }
        for (var i = 0; i < rows.length; i++){ table.append(rows[i]); }
    });
    
    function comparator(index) {
        return function(a, b) {
            var valA = getCellValue(a, index), valB = getCellValue(b, index);
            return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.toString().localeCompare(valB);
        }
    }
    
    function getCellValue(row, index){ return $(row).children('td').eq(index).text(); }
    
    // Очистка поиска
    $('#clearSearch').click(function(){
    window.location.href = '<?php echo $_this_script_; ?>';
});
});
</script>


<div class="container mt-3">
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Header Card -->
            <div class="card shadow-sm border-light mb-4">
                <div class="card-header bg-white text-dark rounded-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 text-dark">
                            <i class="fas fa-download me-2 text-primary"></i>All Snatched Torrents
                        </h4>
                        <span class="badge bg-light text-dark border fs-6">
                            <i class="fas fa-database me-1 text-muted"></i>Total: <?php echo $count1; ?> snatched
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
            $torrentsperpage = ($CURUSER['torrentsperpage'] != 0 ? intval($CURUSER['torrentsperpage']) : 20);
            $perpage = $torrentsperpage;
            
            if($mybb->input['page'] > 0) {
                $page = $mybb->input['page'];
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
            
            
			
			
			$page_url = str_replace("{fid}", $fid, $_this_script_ . $search_url);
			
			
			//$page_url = str_replace("{fid}", $fid, $_this_script_ . '?act=snatched_torrents' . $search_url);
			
			
            $multipage = multipage($count, $perpage, $page, $page_url);
            
            // Display pagination
            if($count > $perpage) {
                echo '<div class="card mb-3 border-light">';
                echo '<div class="card-body py-2 bg-white">';
                echo '<div class="d-flex justify-content-between align-items-center">';
                echo '<div class="small text-muted">Showing ' . ($start + 1) . ' to ' . min($start + $perpage, $count) . ' of ' . $count . ' records</div>';
                echo '<div>' . $multipage . '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            
            // Main query
            $sql = "SELECT s.*, t.name, u.username as uname, u.id as uid, u.usergroup, u.avatar, u.avatardimensions, 
                           u.donor, u.enabled, u.warned, u.leechwarn, p.canupload, p.candownload, p.cancomment
                    FROM snatched s 
                    LEFT JOIN torrents t ON (s.torrentid=t.id) 
                    LEFT JOIN users u ON (s.userid=u.id) 
                    LEFT JOIN ts_u_perm p ON (u.id=p.userid)
                    " . $where_clause . "
                    ORDER BY s.to_go DESC 
                    LIMIT " . $start . ", " . $perpage;
            
            $result = $db->sql_query($sql);
            
            if ($db->num_rows($result) != 0) {
            ?>
            
            <!-- Main Data Card -->
            <div class="card shadow-sm border-light">
                <div class="card-body p-0 bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
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
                                        <a href="<?php echo $BASEURL . '/' . get_torrent_link($row['torrentid']); ?>" 
                                           class="text-decoration-none text-dark" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="<?php echo htmlspecialchars_uni($row['name']); ?>">
                                            <i class="fas fa-magnet text-danger me-1"></i>
                                            <?php echo cutename($row['name']); ?>
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
                                            <span class="badge bg-light text-muted border" data-bs-toggle="tooltip" title="Not completed">
                                                <i class="fas fa-times me-1"></i>Not completed
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
                                        <div class="progress bg-light" style="height: 8px;" data-bs-toggle="tooltip" title="<?php echo $progress; ?>%">
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
                echo '<div class="card shadow-sm border-light">';
                echo '<div class="card-body text-center py-5 bg-white">';
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
                echo '<div class="card mt-3 border-light">';
                echo '<div class="card-body py-2 bg-white">';
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
.card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    background: #ffffff;
}
.card-header {
    border-radius: 12px 12px 0 0 !important;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}
.table {
    background: #ffffff;
    border-color: #e9ecef;
}
.table th {
    border-top: none;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    color: #495057;
    background: #f8f9fa;
}
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.03) !important;
    transform: translateY(-1px);
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.sortable:hover {
    background-color: rgba(248, 249, 250, 0.8) !important;
}
.progress {
    border-radius: 10px;
    overflow: hidden;
    background: #e9ecef;
}
.badge {
    font-weight: 500;
    background: #f8f9fa;
}
.bg-light {
    background: #f8f9fa !important;
}
.text-dark {
    color: #212529 !important;
}
.border-light {
    border-color: #e9ecef !important;
}
.nav-avatar {
    border-radius: 50%;
    border: 2px solid #e9ecef;
    object-fit: cover;
}
.input-group-text {
    background: #f8f9fa;
    border-color: #e9ecef;
}
</style>

<?php
stdfoot();
?>