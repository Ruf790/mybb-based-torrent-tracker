<?php
/***************************************************/
/*=========[ TS Special Edition v.5.6 ]===========*/
/*=============[ Special Thanks To ]==============*/
/*          DrNet - SpecialCoders.CoM             */
/*          Vinson - Decode4u.CoM                 */
/*      MrDecoder - Fearless-Releases.CoM         */
/*           Fynnon - BvList.CoM                  */
/***************************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-light border" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define("IN_MYBB", 1);
define('TSHRD_TOOL', 'v1.2 by xam');

require_once INC_PATH . '/datahandler.php';
include_once $rootpath . '/admin/include/global_config.php';
include_once $rootpath . '/admin/include/staff_languages.php';

$torrentid = ((isset($_GET['torrentid']) && is_valid_id($_GET['torrentid'])) ? intval($_GET['torrentid']) : ((isset($_POST['torrentid']) && is_valid_id($_POST['torrentid'])) ? intval($_POST['torrentid']) : 0));
$type = ((isset($_GET['type']) && $_GET['type'] == 'seedtime') ? 'seedtime' : 'ratio');
$eol = PHP_EOL;

// Получаем текущую страницу из GET параметра
$page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
$per_page = $config['ts_hit_and_run']['query_limit'];

if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
    if (!empty($_POST['ban']) && count($_POST['user_torrent_ids']) > 0) {
        $userids = [];
        foreach ($_POST['user_torrent_ids'] as $work) {
            $worknow = explode('|', $work);
            $userids[] = $worknow[0];
        }

        if (count($userids) > 0) {
            $userids = implode(',', $userids);
            $modcomment = gmdate('Y-m-d') . ' - Banned by ' . $CURUSER['username'] . '. (TS Hit & Run Staff Tool)' . $eol;
            $db->sql_query('UPDATE users SET enabled=\'no\', usergroup=\'' . UC_BANNED . '\', modcomment=CONCAT(' . $db->sqlesc($modcomment) . ', modcomment) WHERE id IN(0,' . $userids . ')') or sqlerr(__FILE__, 44);
        }
    } elseif (!empty($_POST['warn'])) {
        if ($_POST['do'] == 'warn') {
            $user_torrent_ids = explode(',', $_POST['user_torrent_ids']);
            require_once INC_PATH . '/functions_pm.php';
            foreach ($user_torrent_ids as $work) {
                $arrays = explode('|', $work);
                $db->sql_query('REPLACE INTO ts_hit_and_run (userid,torrentid,added) VALUES (' . intval($arrays[0]) . ', ' . intval($arrays[1]) . ', ' . TIMENOW . ')');
                $msg = str_replace(
                    ['{torrentinfo}', '{torrentdownloadinfo}', '{showratio}'],
                    ['[URL]' . $BASEURL . '/details.php?id=' . intval($arrays[1]) . '[/URL]', '[URL]' . $BASEURL . '/download.php?id=' . intval($arrays[1]) . '[/URL]', $arrays[2]],
                    $_POST['warnmessage']
                );

                $pm = [
                    'subject' => 'Warning!',
                    'message' => $msg,
                    'touid' => $arrays[0]
                ];
                $pm['sender']['uid'] = -1;
                send_pm($pm, -1, true);

                $modcomment = gmdate('Y-m-d') . ' - Warned by ' . $CURUSER['username'] . '. Torrent ID: ' . intval($arrays[1]) . ' (TS Hit & Run Staff Tool)' . $eol;
                $db->sql_query('UPDATE users SET timeswarned = timeswarned + 1, modcomment=CONCAT(' . $db->sqlesc($modcomment) . ', modcomment) WHERE id = ' . intval($arrays[0]));
            }
        } elseif (count($_POST['user_torrent_ids']) > 0) {
            stdhead('TS Hit & Run Detection Tool');
            echo '
            <div class="container">
                <div class="card border-light shadow-sm">
                    <div class="card-header bg-light text-dark border-bottom">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>TS Hit & Run Detection Tool</h5>
                    </div>
                    <form method="post" action="' . $_this_script_ . '" name="update">
                        <input type="hidden" name="do" value="warn">
                        <input type="hidden" name="page" value="' . intval($_POST['page']) . '">
                        ' . ($torrentid ? '<input type="hidden" name="torrentid" value="' . $torrentid . '">' : '') . '
                        <input type="hidden" name="user_torrent_ids" value="' . implode(',', $_POST['user_torrent_ids']) . '">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label text-dark"><strong>Please Enter Warning Message:</strong></label>
                                <small class="form-text text-muted d-block mb-2">Do not change <b>{torrentinfo}</b>, <b>{showratio}</b> and <b>{torrentdownloadinfo}</b> values which will be automatically changed by system.</small>
                                <textarea name="warnmessage" class="form-control border-light shadow-sm" rows="10">' . $adminlang['ts_hit_and_run'] . '</textarea>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top">
                            <button type="reset" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>Reset Message</button>
                            <button type="submit" class="btn btn-outline-warning" value="warn users" name="warn"><i class="fas fa-exclamation-circle me-1"></i>Warn Users</button>
                        </div>
                    </form>
                </div>
            </div>';
            stdfoot();
            exit();
        }
    }
}

$alreadywarnedarrays = [];
$query = $db->sql_query('SELECT userid,torrentid,added FROM ts_hit_and_run WHERE added > ' . (TIMENOW - 60 * 60 * (7 * 24)));
if ($db->num_rows($query) > 0) {
    while ($alreadywarned = mysqli_fetch_assoc($query)) {
        $alreadywarnedarrays[$alreadywarned['userid']][$alreadywarned['torrentid']] = $alreadywarned['added'];
    }
}

$extraquery = $extraquery2 = $hiddenvalues = '';
$link = $orjlink = '';
if (is_valid_id($torrentid)) {
    $extraquery = ' AND s.torrentid=' . $torrentid;
    $hiddenvalues = '<input type="hidden" name="torrentid" value="' . $torrentid . '">';
    $link = $orjlink = 'torrentid=' . $torrentid . '&amp;';
}

if (isset($_GET['page'])) {
    $hiddenvalues .= '<input type="hidden" name="page" value="' . intval($_GET['page']) . '">';
}

$skip_usergroups = implode(',', $config['ts_hit_and_run']['skip_usergroups']);
if (isset($_GET['show_by_userid'])) {
    $userid = intval($_GET['show_by_userid']);
    if (is_valid_id($userid)) {
        $extraquery2 = ' AND u.id=' . $db->sqlesc($userid);
    }
}

require_once INC_PATH . '/functions_icons.php';
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' && isset($_POST['do_search'])) {
    if (!empty($_POST['keywords'])) {
        $keywords = trim($_POST['keywords']);
        $searchtype = intval($_POST['searchtype']);
        switch ($searchtype) {
            case 1:
                $extraquery2 = ' AND u.username=' . $db->sqlesc($keywords);
                break;
            case 2:
                $extraquery2 = ' AND u.id=' . $db->sqlesc($keywords);
                break;
            case 3:
                $extraquery2 = ' AND s.torrentid=' . $db->sqlesc($keywords);
                break;
        }
    }
}

switch ($type) {
    case 'ratio':
        $typequery = '(t.seeders > 0 OR t.leechers > 0) AND s.uploaded/s.downloaded < ' . $config['ts_hit_and_run']['min_share_ratio'];
        $link = ($link ? $link . '&' : '') . 'type=ratio';
        break;
    case 'seedtime':
        $typequery = '(s.seedtime = 0 OR s.seedtime < s.leechtime)';
        $link = ($link ? $link . '&;' : '') . 'type=seedtime&amp;';
        break;
}


$query = $db->sql_query('SELECT COUNT(*) as total 
FROM snatched s 
INNER JOIN users u ON (s.userid=u.id) 
LEFT JOIN torrents t ON (s.torrentid=t.id) 
WHERE s.finished=\'yes\' AND s.seeder=\'no\' AND (u.enabled=\'yes\' AND u.usergroup NOT IN (' . $skip_usergroups . ') AND u.ustatus=\'confirmed\') AND t.visible=\'yes\' AND ' . $typequery . $extraquery . $extraquery2);

$total_count = 0;
if ($result = mysqli_fetch_assoc($query)) {
    $total_count = $result['total'];
}


$offset = ($page - 1) * $per_page;
$limit = "LIMIT $offset, $per_page";
$total_pages = ceil($total_count / $per_page);


function generate_pagination($base_url, $current_page, $total_pages, $per_page, $total_count) {
    if ($total_pages <= 1) return '';
    
    
    $base_url = str_replace('&&', '&', $base_url);
    $base_url = rtrim($base_url, '&');
    
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page - 1) . '">&laquo;</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $start_page + 4);
    
    if ($end_page - $start_page < 4) {
        $start_page = max(1, $end_page - 4);
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $pagination .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page + 1) . '">&raquo;</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }
    
    $pagination .= '</ul></nav>';
    
   
    $start_item = ($current_page - 1) * $per_page + 1;
    $end_item = min($current_page * $per_page, $total_count);
    $page_info = '<div class="text-center text-muted small mb-2">Showing ' . $start_item . ' to ' . $end_item . ' of ' . $total_count . ' entries</div>';
    
    return $page_info . $pagination;
}



$base_url = $_this_script_ . '&' . $link;
$base_url = str_replace('&&', '&', $base_url);
$base_url = rtrim($base_url, '&');
$pagertop = generate_pagination($base_url, $page, $total_pages, $per_page, $total_count);
$pagerbottom = $pagertop;


$query = $db->sql_query('SELECT s.torrentid, s.seedtime, s.leechtime, s.userid, s.downloaded, s.uploaded, 
t.name, t.seeders, t.leechers, u.timeswarned, u.username, u.enabled, u.donor, u.leechwarn, u.warned, p.canupload, p.candownload, p.cancomment, 
g.namestyle FROM snatched s 
INNER JOIN users u ON (s.userid=u.id) 
LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
LEFT JOIN torrents t ON (s.torrentid=t.id) 
LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
WHERE s.finished=\'yes\' AND s.seeder=\'no\' AND (u.enabled=\'yes\' AND u.usergroup NOT IN (' . $skip_usergroups . ') AND u.ustatus=\'confirmed\') AND t.visible=\'yes\' AND (t.seeders > 0 OR t.leechers > 0) AND s.uploaded/s.downloaded < ' . $config['ts_hit_and_run']['min_share_ratio'] . $extraquery . $extraquery2 . ' ORDER by u.timeswarned DESC ' . $limit);

if (($total = $db->num_rows($query)) > 0) {
    include_once INC_PATH . '/readconfig_cleanup.php';
    $criticallimit = $ban_user_limit - 1;
    stdhead('TS Hit & Run Detection Tool');
    
    echo '
    <div class="container mb-4">
        <div class="card border-light shadow-sm">
            <div class="card-header bg-light text-dark border-bottom">
                <h5 class="mb-0"><i class="fas fa-search me-2 text-primary"></i>Search Hit and Run</h5>
            </div>
            <div class="card-body">
                <form method="post" action="' . $_this_script_ . '&do_search">
                    <input type="hidden" name="do_search" value="1">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="col-form-label text-dark">Keyword(s):</label>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control border-light shadow-sm" name="keywords" value="' . htmlspecialchars_uni($keywords) . '">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select border-light shadow-sm" name="searchtype">
                                <option value="3"' . ($searchtype == 3 ? ' selected' : '') . '>Search by Torrent ID</option>
                                <option value="2"' . ($searchtype == 2 ? ' selected' : '') . '>Search by User ID</option>
                                <option value="1"' . ($searchtype == 1 ? ' selected' : '') . '>Search by Username</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100" name="do_search"><i class="fas fa-search me-1"></i>Search</button>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group">
                                <a href="' . $_this_script_ . '&' . $orjlink . 'page=' . $page . '&type=seedtime" class="btn btn-outline-secondary btn-sm">Show by Seed/Leech Time</a>
                                <a href="' . $_this_script_ . '&' . $orjlink . 'page=' . $page . '&type=ratio" class="btn btn-outline-secondary btn-sm">Show by Ratio</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    echo $pagertop;
    
    echo '
    <div class="container">
        <div class="card border-light shadow-sm">
            <div class="card-header bg-light text-dark border-bottom">
                <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>TS Hit & Run Detection Tool</h5>
                <small class="text-muted">Found: ' . $total_count . ' users. Query Limit: ' . $config['ts_hit_and_run']['query_limit'] . '</small>
            </div>
            <form method="post" action="' . $_this_script_ . '" name="update">
                ' . $hiddenvalues . '
                <input type="hidden" name="page" value="' . $page . '">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-dark border-light">Username</th>
                                    <th class="text-dark border-light">Torrent Name</th>
                                    <th class="text-dark border-light">Uploaded / SeedTime</th>
                                    <th class="text-dark border-light">Downloaded / LeechTime</th>
                                    <th class="text-dark border-light">Ratio</th>
                                    <th class="text-dark border-light">Times Warned<br><small class="text-muted">(' . $ban_user_limit . ' warns = ban)</small></th>
                                    <th class="text-center text-dark border-light">
									
									
	<script type="text/javascript">
		
function select_deselectAll(formname,elm,group)
{
	var frm=document.forms[formname];
	for(i=0;i<frm.length;i++)
	{
		if(elm.attributes["checkall"] != null && elm.attributes["checkall"].value == group)
		{
			if(frm.elements[i].attributes["checkme"] != null && frm.elements[i].attributes["checkme"].value == group)
			{
				frm.elements[i].checked=elm.checked;
			}
		}
		else if(frm.elements[i].attributes["checkme"] != null && frm.elements[i].attributes["checkme"].value == group)
		{
			if(frm.elements[i].checked == false)
			{
				frm.elements[1].checked = false;
			}
		}
	}
}
	
	
</script>								
                                        
                                        <div class="form-check form-switch d-inline-block">
    <input class="form-check-input" type="checkbox"
           id="checkall_group"
           checkall="group"
           onclick="return select_deselectAll(\'update\', this, \'group\');">
    <label class="form-check-label" for="checkall_group"></label>
  </div>


                                    </th>
                                </tr>
                            </thead>
                            <tbody>';

    require_once INC_PATH . '/functions_mkprettytime.php';
    while ($user = mysqli_fetch_assoc($query)) {
        if ($alreadywarnedarrays[$user['userid']][$user['torrentid']]) {
            $disabled = ' disabled';
            $alreadw = ' <span class="badge bg-light text-dark border" title="Already warned"><i class="fas fa-check-circle me-1 text-success"></i>Warned</span>';
        } else {
            $disabled = ' checkme="group"';
            $alreadw = '';
        }

        if ($user['timeswarned'] == 0) {
            $warnClass = 'text-success';
        } elseif ($user['timeswarned'] == $criticallimit) {
            $warnClass = 'text-warning';
        } elseif ($user['timeswarned'] >= $ban_user_limit) {
            $warnClass = 'text-danger';
        } else {
            $warnClass = 'text-dark';
        }

        $user_icons = get_user_icons($user);
        $ratio = number_format($user['uploaded'] / $user['downloaded'], 2);
        $ratioClass = ($ratio < 1) ? 'text-danger' : 'text-success';

        echo '
                                <tr class="bg-white">
                                    <td><a href="' . $_this_script_ . '&show_by_userid=' . $user['userid'] . '" class="text-decoration-none text-dark">' . get_user_color($user['username'], $user['namestyle']) . '</a> ' . $user_icons . '</td>
                                    <td><a href="' . $_this_script_ . '&torrentid=' . $user['torrentid'] . '" class="text-decoration-none text-dark">' . cutename($user['name'], 80) . '</a></td>
                                    <td class="text-dark">' . mksize($user['uploaded']) . ' <small class="text-muted">(' . mkprettytime($user['seedtime']) . ')</small></td>
                                    <td class="text-dark">' . mksize($user['downloaded']) . ' <small class="text-muted">(' . mkprettytime($user['leechtime']) . ')</small></td>
                                    <td class="' . $ratioClass . ' fw-bold">' . $ratio . '</td>
                                    <td class="' . $warnClass . ' fw-bold">' . $user['timeswarned'] . '</td>
                                    <td class="text-center">
                                        
                                    
                                    <div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" 
         name="user_torrent_ids[]" 
         value="' . $user['userid'] . '|' . $user['torrentid'] . '|' . $ratio . '"' . $disabled . '>
  <label class="form-check-label">' . $alreadw . '</label>
</div>





                                    </td>
                                </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top">
                    <div class="row align-items-center">
                        <div class="col">
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Already warned users cannot be selected</small>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline-warning me-2" value="warn selected users" name="warn"><i class="fas fa-exclamation-triangle me-1"></i>Warn Selected</button>
                            <button type="submit" class="btn btn-outline-danger" name="ban"><i class="fas fa-ban me-1"></i>Ban Selected</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>';

    echo $pagerbottom;
    stdfoot();
    return 1;
}

stderr($lang->global['error'], '<div class="alert alert-light border" role="alert"><i class="fas fa-exclamation-circle me-2 text-warning"></i>Nothing found!</div>');
?>