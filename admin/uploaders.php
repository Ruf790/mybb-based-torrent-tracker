<?

declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger text-center mt-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

define('U_VERSION', '0.4 by xam');
include_once INC_PATH . '/functions_ratio.php';

$combine = false;
if ((isset($_GET['uploader']) AND is_valid_id($_GET['uploader']))) {
    $uploader = intval($_GET['uploader']);
    $combine = true;
}

$user = array();
$query = $db->sql_query('SELECT id, name, added, owner, seeders, leechers FROM torrents');
while ($uploads = mysqli_fetch_assoc($query)) {
    ++$user['totaltorrents'][$uploads['owner']];
    $user['lastupload'][$uploads['owner']] = ($combine ? $user['lastupload'][$uploads['owner']] : '') . '
    <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
        <i class="fas fa-file-alt text-primary me-3 fs-5"></i>
        <div class="flex-grow-1">
            <a href="' . $BASEURL . '/' . get_torrent_link($uploads['id']) . '" class="text-decoration-none fw-semibold text-dark">
                ' . htmlspecialchars($uploads['name']) . '
            </a>
            <div class="text-muted small mt-1">
                <i class="far fa-calendar me-1"></i>' . my_datee($dateformat, $uploads['added']) . ' 
                <i class="far fa-clock me-1"></i>' . my_datee($timeformat, $uploads['added']) . '
                <span class="ms-2">
                    <i class="fas fa-seedling text-success me-1"></i>' . ts_nf($uploads['seeders']) . '
                    <i class="fas fa-download text-danger ms-2 me-1"></i>' . ts_nf($uploads['leechers']) . '
                </span>
            </div>
        </div>
    </div>';
}

$what = ((isset($_GET['type']) AND $_GET['type'] == 2) ? 'g.canupload = \'yes\'' : 'u.usergroup=' . UC_UPLOADER);
if ($combine) {
    $what = 'u.id=' . $db->sqlesc($_GET['uploader']);
}

include_once $rootpath . '/admin/include/global_config.php';
$query = $db->sql_query('' . 'SELECT u.id FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.enabled=\'yes\' AND ' . $what);
$total_count = $db->num_rows($query);

stdhead($SITENAME . ' Uploader List');




// CSS стили
echo "<style>
.user-avatar { 
    width: 55px; 
    height: 55px; 
    border-radius: 50%; 
    object-fit: cover; 
    margin-right: 0.75rem; 
    border: 2px solid #e9ecef; 
    transition: border-color 0.3s ease;
}
.user-avatar:hover {
    border-color: #007bff;
}
.highlight-row { 
    background-color: rgba(13, 110, 253, 0.05) !important; 
    border-left: 3px solid #007bff;
}
.pagination-container .pagination { 
    margin-bottom: 0; 
}
.table-responsive { 
    overflow-x: auto; 
}
.card { 
    transition: box-shadow 0.3s ease; 
    border: 1px solid rgba(0,0,0,0.125);
}
.card:hover { 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important; 
}
.text-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.dropdown-item.active {
    background-color: #007bff;
    color: white;
}
.badge {
    font-size: 0.75em;
}
</style>";



echo '
<div class="container mt-3">
    <!-- Header Card -->
    <div class="card border-0 mb-4 bg-primary">
        <div class="card-body py-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-2 text-white">
                        <i class="fas fa-users me-3"></i>
                        ' . $SITENAME . ' Uploaders
                    </h1>
                    <p class="text-white-50 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Complete overview of all uploaders and their statistics
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group" role="group">
                        <a href="' . $_this_script_ . '&amp;type=1" class="btn btn-light btn-sm">
                            <i class="fas fa-user-group me-2"></i>UC_UPLOADER
                        </a>
                        <a href="' . $_this_script_ . '&amp;type=2" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-upload me-2"></i>Can Upload Only
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Uploaders
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">' . ts_nf($total_count) . '</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="container mt-3">
        <div class="card-header py-3 border-bottom">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-list-alt me-2"></i>
                        Uploader Details
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary fs-6">' . ts_nf($total_count) . ' Records</span>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">
                                <i class="fas fa-user me-2 text-primary"></i>
                                Uploader / Ratio
                            </th>
                            <th class="text-center py-3">
                                <i class="fas fa-clock me-2 text-primary"></i>
                                Last Access
                            </th>
                            <th class="text-center py-3">
                                <i class="fas fa-upload me-2 text-primary"></i>
                                Uploads
                            </th>
                            <th class="pe-4 py-3">
                                <i class="fas fa-history me-2 text-primary"></i>
                                Last Upload' . ($combine ? 's' : '') . '
                            </th>
                        </tr>
                    </thead>
                    <tbody>';

$uploaders = array();
$query = $db->sql_query('' . 'SELECT u.username, u.avatar, u.avatardimensions, u.usergroup, u.id, u.lastactive, u.lastvisit, u.uploaded, u.downloaded
FROM users u 
WHERE u.enabled=\'yes\' AND ' . $what . ' ' . $limit);

while ($res = mysqli_fetch_assoc($query)) 
{
    $info = ($user['lastupload'][$res['id']] ? $user['lastupload'][$res['id']] : '
    <div class="text-center text-muted py-3">
        <i class="fas fa-inbox fa-2x mb-2"></i>
        <div>No uploaded torrents found</div>
    </div>');
    
    $last_seen = max(array($res['lastactive'], $res['lastvisit']));
    if (empty($last_seen)) {
        $user['lastvisit'] = '<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Never</span>';
    } else {
        $user['lastvisit'] = '<span class="badge bg-light text-dark"><i class="far fa-clock me-1"></i>' . my_datee('relative', $last_seen) . '</span>';
    }
    
    
	$useravatar = format_avatar($res['avatar'], $res['avatardimensions']);
    $ava_img = "<img class='user-avatar' src='{$useravatar['image']}' alt='' {$useravatar['width_height']} />";
	
	$ratio_html = get_user_ratio($res['uploaded'], $res['downloaded']);
    $ratio_class = 'success';
    if (strpos($ratio_html, '∞') === false) {
        $ratio_value = floatval($ratio_html);
        if ($ratio_value < 0.5) $ratio_class = 'danger';
        elseif ($ratio_value < 1.0) $ratio_class = 'warning';
    }
    
    echo '
    <tr class="border-bottom">
        <td class="ps-4 py-3">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    
                        '.$ava_img.'
                    
                </div>
                <div class="flex-grow-1 ms-3">
                    <a href="' . $BASEURL . '/' . get_profile_link($res['id']) . '" class="text-decoration-none fw-semibold text-dark">
                        ' . format_name($res['username'], $res['usergroup']) . '
                    </a>
                    <div class="mt-1">
                        <span class="badge bg-' . $ratio_class . '">
                            <i class="fas fa-chart-line me-1"></i>' . $ratio_html . '
                        </span>
                        ' . ($combine ? '' : '
                        <a href="' . $_this_script_ . '&amp;uploader=' . $res['id'] . '" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fas fa-external-link-alt me-1"></i>View All
                        </a>') . '
                    </div>
                </div>
            </div>
        </td>
        <td class="text-center py-3">
            ' . $user['lastvisit'] . '
        </td>
        <td class="text-center py-3">
            <span class="fw-bold text-primary fs-5">' . ts_nf($user['totaltorrents'][$res['id']]) . '</span>
        </td>
        <td class="pe-4 py-3">
            <div class="last-uploads" style="max-height: 200px; overflow-y: auto;">
                ' . $info . '
            </div>
        </td>
    </tr>';
}

echo '
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="card-footer bg-white border-0 py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        <i class="fas fa-database me-2"></i>
                        Showing ' . ts_nf($total_count) . ' uploaders
                    </p>
                </div>
                <div class="col-md-6">
                    ' . $pagerbottom . '
                </div>
            </div>
        </div>
    </div>
</div>';

stdfoot();
?>