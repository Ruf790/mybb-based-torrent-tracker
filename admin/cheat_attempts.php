<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

define("IN_MYBB", 1);
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';

function show_image($text, $size = 300) {
    return 'onmouseover="ddrivetip(\'' . $text . '\', ' . $size . ')" onmouseout="hideddrivetip()"';
}

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('CA_VERSION', '0.6 by xam');
$eol = PHP_EOL;

if ($_POST['do'] == 'apply') {
    if (is_array($_POST['ban'])) {
        $modcomment = gmdate('Y-m-d') . ' - Banned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;
        $db->sql_query('UPDATE users SET enabled = \'no\', passkey=\'\', modcomment=CONCAT(' . $db->sqlesc($modcomment . '') . ', modcomment) WHERE id IN (' . implode(', ', $_POST['ban']) . ')');
        $ca_message = 'Users have been banned';
    }

    if (is_array($_POST['warn'])) {
        $warnlength11 = '1';
        $warneduntil = TIMENOW + $warnlength11 * 604800;
        $lastwarned = TIMENOW;
        
        $query = 'warned = \'yes\', timeswarned = timeswarned + 1, lastwarned = ' . $lastwarned . ', warnedby = ' . $CURUSER['id'] . ', warneduntil = ' . $db->sqlesc($warneduntil);
        $modcomment = gmdate('Y-m-d') . ' - Warned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;
        $db->sql_query('UPDATE users SET ' . $query . ', modcomment=CONCAT(' . $db->sqlesc($modcomment . '') . ', modcomment) WHERE id IN (' . implode(', ', $_POST['warn']) . ')');
        
        $res = $db->sql_query('SELECT id FROM users WHERE id IN (' . implode(', ', $_POST['warn']) . ')');
        require_once INC_PATH . '/functions_pm.php';
        
        while ($arr = mysqli_fetch_assoc($res)) {
            $pm = array(
                'subject' => 'You have been warned!',
                'message' => 'You have been warned for 1 week because of Possible Cheat Attempt!',
                'touid' => $arr['id']
            );
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
        }
        $ca_message = 'Users have been warned';
    }

    if (is_array($_POST['delete'])) {
        $db->sql_query('DELETE FROM cheat_attempts WHERE id IN (' . implode(', ', $_POST['delete']) . ')');
        $ca_message = 'Cheat Attempts have been deleted!';
    }
}

$res = $db->sql_query('SELECT COUNT(*) FROM cheat_attempts');
$row = mysqli_fetch_row($res);
$count = $row[0];

if(!$torrentsperpage || (int)$torrentsperpage < 1) {
    $torrentsperpage = 20;
}

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

$end = $start + $perpage;
$lower = $start + 1;
$upper = $end;

if($upper > $count) {
    $upper = $count;
}

$page_url = str_replace("{fid}", $fid, $_this_script_ . '');
$multipage = multipage($count, $perpage, $page, $page_url);

stdhead('Cheat Attempts');
?>

<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Cheat Attempts
                        <?php if(isset($ca_message)): ?>
                        <span class="badge bg-danger ms-2"><?php echo $ca_message; ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="pagination-container">
                            <?php echo $multipage; ?>
                        </div>
                    </div>

                    <script>
                    $(document).ready(function(){
                        $('[data-toggle="tooltip"]').tooltip();   
                    });
                    </script>

                    <form method="post" action="">
                        <input type="hidden" name="do" value="apply">
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-user me-1"></i> User</th>
                                        <th><i class="fas fa-calendar me-1"></i> Added</th>
                                        <th><i class="fas fa-file-alt me-1"></i> Torrent</th>
                                        <th><i class="fas fa-desktop me-1"></i> Agent</th>
                                        <th><i class="fas fa-tachometer-alt me-1"></i> Upload Speed</th>
                                        <th><i class="fas fa-upload me-1"></i> Uploaded</th>
                                        <th><i class="fas fa-clock me-1"></i> Within</th>
                                        <th><i class="fas fa-globe me-1"></i> IP</th>
                                        <th class="text-center"><i class="fas fa-ban me-1"></i> Ban</th>
                                        <th class="text-center"><i class="fas fa-exclamation-triangle me-1"></i> Warn</th>
                                        <th class="text-center"><i class="fas fa-trash me-1"></i> Del</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res = $db->sql_query('SELECT c.*, u.id as userid, u.username, u.usergroup, u.uploaded, u.enabled, u.donor, u.leechwarn, u.warned, p.canupload, p.candownload, p.cancomment, t.name 
                                        FROM cheat_attempts c 
                                        LEFT JOIN users u ON (c.uid=u.id) 
                                        LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
                                        LEFT JOIN torrents t ON (c.torrentid=t.id) 
                                        ORDER BY c.added DESC LIMIT '.$start.',' . $perpage);
                                    
                                    require_once INC_PATH . '/functions_mkprettytime.php';
                                    
                                    while ($arr = mysqli_fetch_assoc($res)) {
                                        $uppd = mksize($arr['upthis']);
                                        echo '
                                        <tr>
                                            <td>
                                                <a href="' . $BASEURL . '/' . get_profile_link($arr['userid']) . '" class="text-decoration-none">
                                                    ' . format_name(htmlspecialchars_uni($arr['username']), $arr['usergroup']) . '
                                                </a>
                                                <span class="user-icons">' . get_user_icons($arr) . '</span>
                                            </td>
                                            <td>
                                                <div class="small">' . my_datee($dateformat, $arr['added']) . '</div>
                                                <div class="text-muted smaller">' . my_datee($timeformat, $arr['added']) . '</div>
                                            </td>
                                            <td>
                                                <a href="' . $BASEURL . '/' . get_torrent_link($arr['torrentid']) . '" 
                                                   data-toggle="tooltip" 
                                                   data-placement="top" 
                                                   title="' . htmlspecialchars_uni($arr['name']) . '"
                                                   class="badge bg-info text-decoration-none">
                                                   #' . intval($arr['torrentid']) . '
                                                </a>
                                            </td>
                                            <td><span class="font-monospace small">' . htmlspecialchars_uni($arr['agent']) . '</span></td>
                                            <td><span class="badge bg-warning text-dark">' . mksize($arr['transfer_rate']) . '/s</span></td>
                                            <td>' . $uppd . '</td>
                                            <td><span class="badge bg-secondary">' . mkprettytime($arr['timediff']) . '</span></td>
                                            <td><span class="font-monospace">' . htmlspecialchars_uni($arr['ip']) . '</span></td>
                                            <td class="text-center">
                                                
                                                <div class="form-check form-switch d-inline-block">
                                                <input type="checkbox" class="form-check-input ban-checkbox" name="ban[]" value="' . intval($arr['userid']) . '">
                                                </div>
                                            
                                            
                                                </td>
                                            <td class="text-center">
                                                
                                             <div class="form-check form-switch d-inline-block">
                                            <input type="checkbox" class="form-check-input warn-checkbox" name="warn[]" value="' . intval($arr['userid']) . '">
                                            </div>

                                            </td>
                                            <td class="text-center">
                                                
                                                 <div class="form-check form-switch d-inline-block">
                                               <input type="checkbox" class="form-check-input delete-checkbox" name="delete[]" value="' . intval($arr['id']) . '">
                                               </div>
                                            </td>
                                        </tr>';
                                    }
                                    ?>
                                </tbody>
                                <tfoot>
                                    
                              <tr>
                <td colspan="11" class="text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="checkAll('ban[]')">
                            <i class="fas fa-check-square me-1"></i>Check All Ban
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="checkAll('warn[]')">
                            <i class="fas fa-check-square me-1"></i>Check All Warn
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="checkAll('delete[]')">
                            <i class="fas fa-check-square me-1"></i>Check All Delete
                        </button>
                        <button type="submit" name="submit" value="Apply Changes" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i>Apply Changes
                        </button>
                    </div>
                </td>
            </tr>




                                </tfoot>
                            </table>
                        </div>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="pagination-container">
                            <?php echo $multipage; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkAll(type) {
    // Используем querySelectorAll, чтобы работать с именами с квадратными скобками
    var checkboxes = document.querySelectorAll('input[name="' + type + '"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = !checkbox.checked; // переключаем состояние
    });
}
</script>

<?php
stdfoot();
?>