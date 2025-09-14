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

define('AA_VERSION', '0.7 by xam');
$eol = PHP_EOL;

if ($_POST['do'] == 'apply') {
    if (is_array($_POST['ban'])) {
        $modcomment = gmdate('Y-m-d') . ' - Banned by ' . $CURUSER['username'] . ' (Cheat Attempt)' . $eol;
        $db->sql_query('UPDATE users SET enabled = \'no\', passkey=\'\', modcomment=CONCAT(' . $db->sqlesc($modcomment . '') . ', modcomment) WHERE id IN (' . implode(', ', $_POST['ban']) . ')');
        $aa_message = 'Users have been banned';
    }

    if (is_array($_POST['warn'])) {
        $warnlength2 = '1';
        $warneduntil = TIMENOW + $warnlength2 * 604800;
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
        $aa_message = 'Users have been warned';
    }

    if (is_array($_POST['delete'])) {
        $db->sql_query('DELETE FROM announce_actions WHERE id IN (' . implode(', ', $_POST['delete']) . ')');
        $aa_message = 'Announce Actions have been deleted!';
    }
}

$res = $db->sql_query('SELECT COUNT(*) FROM announce_actions');
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

$page_url = str_replace("{fid}", $fid, $_this_script_ . '');
$multipage = multipage($count, $perpage, $page, $page_url);

stdhead('Announce Actions');
?>

<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-broadcast-tower me-2"></i>Announce Actions
                        <?php if(isset($aa_message)): ?>
                        <span class="badge bg-danger ms-2"><?php echo $aa_message; ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="pagination-container">
                            <?php echo $multipage; ?>
                        </div>
                    </div>

                    <form method="post" action="">
                        <input type="hidden" name="do" value="apply">
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-user me-1"></i> User</th>
                                        <th><i class="fas fa-file-alt me-1"></i> Torrent</th>
                                        <th><i class="fas fa-globe me-1"></i> IP</th>
                                        <th><i class="fas fa-key me-1"></i> Passkey</th>
                                        <th><i class="fas fa-comment me-1"></i> Announce Message</th>
                                        <th class="text-center"><i class="fas fa-ban me-1"></i> Ban</th>
                                        <th class="text-center"><i class="fas fa-exclamation-triangle me-1"></i> Warn</th>
                                        <th class="text-center"><i class="fas fa-trash me-1"></i> Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res = $db->sql_query('SELECT c.*, u.id as userid, u.username, u.usergroup, u.uploaded, u.enabled, 
                                        u.donor, u.leechwarn, u.warned, p.canupload, p.candownload, p.cancomment, t.name 
                                        FROM announce_actions c 
                                        LEFT JOIN users u ON (c.userid=u.id) 
                                        LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
                                        LEFT JOIN torrents t ON (c.torrentid=t.id) 
                                        ORDER BY c.actiontime DESC LIMIT '.$start.', ' . $perpage);
                                    
                                    while ($arr = mysqli_fetch_assoc($res)) {
                                        $mb = '';
                                        if (preg_match('#There was no Leecher on this torrent however this user uploaded (.*) bytes, which might be a cheat attempt with a cheat software such as Ratio Maker, Ratio Faker etc..#U', $arr['actionmessage'], $results)) {
                                            $mb = ' (' . mksize($results[1]) . ') ';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo $BASEURL . '/' . get_profile_link($arr['userid']); ?>" class="text-decoration-none">
                                                    <?php echo format_name(htmlspecialchars_uni($arr['username']), $arr['usergroup']); ?>
                                                </a>
                                                <span class="user-icons"><?php echo get_user_icons($arr); ?></span>
                                            </td>
                                            <td>
                                                <a href="<?php echo $BASEURL . '/' . get_torrent_link($arr['torrentid']); ?>" 
                                                   data-toggle="tooltip" 
                                                   data-placement="top" 
                                                   title="<?php echo htmlspecialchars_uni($arr['name']); ?>"
                                                   class="badge bg-info text-decoration-none">
                                                   #<?php echo intval($arr['torrentid']); ?>
                                                </a>
                                            </td>
                                            <td><span><?php echo htmlspecialchars_uni($arr['ip']); ?></span></td>
                                            <td><span><?php echo htmlspecialchars_uni($arr['passkey']); ?></span></td>
                                            <td>
                                                <div class="small">
                                                    <strong>Cheat Detected on:</strong> 
                                                    <span><?php echo my_datee($dateformat, $arr['actiontime']) . ' ' . my_datee($timeformat, $arr['actiontime']); ?></span>
                                                    <br>
                                                    <strong>Description:</strong> 
                                                    <?php echo htmlspecialchars_uni($arr['actionmessage']) . $mb; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input type="checkbox" class="form-check-input" name="ban[]" value="<?php echo intval($arr['userid']); ?>">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input type="checkbox" class="form-check-input" name="warn[]" value="<?php echo intval($arr['userid']); ?>">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input type="checkbox" class="form-check-input" name="delete[]" value="<?php echo intval($arr['id']); ?>">
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" class="text-end">
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
    var checkboxes = document.getElementsByName(type);
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = !checkboxes[i].checked;
    }
}
</script>

<?php
stdfoot();
?>