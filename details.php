<?php
/*
************************************************
*==========[TS Special Edition v.5.6]==========*
************************************************
*              Special Thanks To               *
*        DrNet - wWw.SpecialCoders.CoM         *
*          Vinson - wWw.Decode4u.CoM           *
*    MrDecoder - wWw.Fearless-Releases.CoM     *
*           Fynnon - wWw.BvList.CoM            *
*==============================================*
*   Note: Don't Modify Or Delete This Credit   *
*     Next Target: TS Special Edition v5.7     *
*     TS SE WILL BE ALWAYS FREE SOFTWARE !     *
************************************************
*/

define("SCRIPTNAME", "details.php");


$templatelist = "imdb_table,peers_table,similartorrents_table,comment_posturl,postbit_online,postbit_offline,postbit_signature,postbit_avatar,commentstable,comment_editreason,comment_edit,comment_quickdelete,multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";


require_once('global.php');

require_once 'cache/smilies.php';


require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;



if(!isset($CURUSER))
{
	
	stderr('You are not Logged');
}







require_once INC_PATH . '/functions_icons.php';

require_once(INC_PATH.'/commenttable.php');

require_once INC_PATH . '/functions_multipage.php';


require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;
  
  
$parser_options = array(
	"allow_html" => 1,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
);

gzip();
maxsysop();

define('D_VERSION', '3.5.5');
define("IN_ARCHIVE", true);







 // If there is no tid but a pid, trick the system into thinking there was a tid anyway.
if(!empty($mybb->input['pid']) && !$mybb->input['id'])
{
	// see if we already have the post information
	if(isset($style) && $style['pid'] == $mybb->input['pid'] && $style['id'])
	{
		$mybb->input['id'] = $style['id'];
		unset($style['id']); // stop the thread caching code from being tricked
	}
	else
	{
		$options = array(
			"limit" => 1
		);
		$query = $db->simple_select("comments", "torrent", "id=".$mybb->input['pid'], $options);
		$post = $db->fetch_array($query);
		
		if(empty($post))
		{
			// post does not exist --> show error message
			stderr($lang->global['invalid_comm']);
		}
		
		$mybb->input['id'] = $post['torrent'];
	}
}






$is_mod = is_mod($usergroups);


function get_comment($pid)
{
	global $db;
	static $post_cache;

	$pid = (int)$pid;

	if(isset($post_cache[$pid]))
	{
		return $post_cache[$pid];
	}
	else
	{
		$query = $db->simple_select("comments", "*", "id = '{$pid}'");
		$post = $db->fetch_array($query);

		if($post)
		{
			$post_cache[$pid] = $post;
			return $post;
		}
		else
		{
			$post_cache[$pid] = false;
			return false;
		}
	}
}





function get_torrent($tid, $recache = false)
{
	global $db;
	static $thread_cache;

	$tid = (int)$tid;

	if(isset($thread_cache[$tid]) && !$recache)
	{
		return $thread_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("torrents", "*", "id = '{$tid}'");
		$thread = $db->fetch_array($query);

		if($thread)
		{
			$thread_cache[$tid] = $thread;
			return $thread;
		}
		else
		{
			$thread_cache[$tid] = false;
			return false;
		}
	}
}



// Get the torrent details from the database.
$torrent = get_torrent($mybb->input['id']);

if(!$torrent)
{
	stderr($lang->global['notorrentid']);
}

$id = $torrent['id'];










$query = $db->sql_query_prepared("
    SELECT t.name, t.banned, n.nfo, c.name AS categoryname,
           c.pid, c.type, c.id AS categoryid, c.icon,
           p.canupload, p.candownload, p.cancomment,
           u.id, u.username, u.usergroup, u.enabled, u.donor, u.warned, u.leechwarn
    FROM torrents t
    LEFT JOIN ts_nfo n ON (t.id = n.id)
    LEFT JOIN categories c ON (t.category = c.id)
    LEFT JOIN users u ON (t.owner = u.id)
    LEFT JOIN ts_u_perm p ON (u.id = p.userid)
    WHERE t.id = ?
", [$id]);

// Проверка результата
if (!$query || $db->num_rows($query) == 0 || !($torrent2 = $db->fetch_array($query))) 
{
    stderr($lang->global['notorrentid']);
} 
elseif ($torrent2["banned"] == "yes" && !$is_mod) 
{
    stderr($lang->global['torrentbanned']);
}








	
	
$lang->load('details');
$lang->load('browse');
$lang->load('upload');







require_once(INC_PATH.'/functions_mkprettytime.php');




$SimilarTorrents = '';
$torrent_name = $torrent['name'];


$query = "
    SELECT MATCH(t.name) AGAINST(? IN BOOLEAN MODE) AS score,
           t.id, t.name, t.anonymous, t.owner, t.category, t.size, t.added, t.seeders,
           t.leechers, c.icon AS catimage, c.name AS catname, u.username, u.usergroup
    FROM torrents t
    LEFT JOIN categories c ON (c.id = t.category)
    LEFT JOIN users u ON (t.owner = u.id)
    WHERE MATCH(t.name) AGAINST(? IN BOOLEAN MODE)
      AND t.id != ?
      AND t.visible = 'yes'
      AND t.banned = 'no'
    ORDER BY score DESC
    LIMIT 10
";

// Параметры для prepared statement
$params = [$torrent_name, $torrent_name, $id];

// Выполнение подготовленного запроса
$query_result = $db->sql_query_prepared($query, $params);

// Проверка результата
if ($query_result && $db->num_rows($query_result) > 0)
{
    $FoundSMTQ = '';
    // Использование $query_result->result вместо $query_result
    while ($SMTQ = $db->fetch_array($query_result)) {
        if ($SMTQ['score'] > 1) {
            $SEOLink = get_torrent_link($SMTQ['id']);
            $SEOLinkC = ts_seo($SMTQ['category'], $SMTQ['catname'], 'c');

            $FoundSMTQ .= '
            <tr>
                <td align="center" style="width: 40px; height: 36px;">
                    <a href="'.$SEOLinkC.'" data-toggle="tooltip" data-placement="top" title="'.$SMTQ['catname'].'">
                        <i class="'.$SMTQ['catimage'].' fa-2x category-icon"></i>
                    </a>
                </td>
                <td>
                    <a href="'.$SEOLink.'">'.htmlspecialchars_uni($SMTQ['name']).'</a>
                </td>
                <td>
                    <span class="small text-muted">
                        <i class="bi bi-calendar me-1"></i> '.my_datee($dateformat, $SMTQ['added']).'
                    </span><br>
                    <span class="small text-muted">
                        <i class="bi bi-clock me-1"></i> '.my_datee($timeformat, $SMTQ['added']).'
                    </span>
                </td>
                <td>
                    '.(!$is_mod && $SMTQ['owner'] != $CURUSER['id'] && $SMTQ['anonymous'] == 'yes'
                        ? '<div class="gray">'.$lang->global['anonymous'].'</div>'
                        : '<a href="'.get_profile_link($SMTQ['owner']).'">'.format_name($SMTQ['username'], $SMTQ['usergroup']).'</a>'
                          .($SMTQ['anonymous'] == 'yes' ? '<div class="gray">'.$lang->global['anonymous'].'</div>' : ''))
                    .'
                </td>
            </tr>';
        }
    }

    if ($FoundSMTQ) {
        eval("\$SimilarTorrents = \"".$templates->get("similartorrents_table")."\";");
    }
}



	

if (empty($torrent["tags"])) 
{
    $keywords = 'No Keywords Specified.';
} 
else 
{
    $tags = explode(",", $torrent['tags']);
    $keywords = "";
    foreach ($tags as $tag) 
	{
        $keywords .= '<a href="'.$BASEURL.'/browse.php?do=search&keywords='.$tag.'&search_type=t_tags" title="'.$tag.'" class="badge bg-primary">'.$tag.'</a> ';
    }
    $keywords = substr($keywords, 0, (strlen($keywords) - 1));
}


if ($torrent2['type'] == 's')
{
	require(TSDIR.'/cache/categories.php');
	foreach ($_categoriesC as $catarray)
	{
		if ($catarray['id'] == $torrent2['pid'])
		{
			$parentcategory = $catarray['name'];
			$parentcatid = $catarray['id'];
			break;
		}
	}
	if ($parentcategory && $parentcatid)
	{
		$seolink = ts_seo($parentcatid,$parentcategory,'c');
		$seolink2 = ts_seo($torrent2['categoryid'],$torrent2['categoryname'],'c');
		$torrent2["categoryname"] = '<a href="'.$seolink.'" target="_self" alt="'.$parentcategory.'" title="'.$parentcategory.'" />'.$parentcategory.'</a> / <a href="'.$seolink2.'" target="_self" alt="'.$torrent2['categoryname'].'" title="'.$torrent2['categoryname'].'" />'.$torrent2['categoryname'].'</a>';
	}
}
else
{
	$seolink2 = ts_seo($torrent2['categoryid'],$torrent2['categoryname'],'c');
	
	$torrent2["categoryname"] = '
	<a href="'.$seolink2.'" target="_self" alt="'.$torrent2['categoryname'].'" title="'.$torrent2['categoryname'].'" />
	<i class="'.$torrent2['icon'].' fa-2x category-icon" title="'.$torrent2['categoryname'].'"></i>
	</a>';
}






$isfree = ($torrent['free'] == 'yes' ? '<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['freedownload'].'"><span class="badge bg-success">F</span></a>' : '');
$issilver = ($torrent['silver'] == 'yes' ? '

<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['silverdownload'].'">
<span class="badge bg-secondary">S</span></a>



' : '');



$isdoubleupload = ($torrent['doubleupload'] == 'yes' ? '


<a href="#" data-toggle="tooltip" data-placement="top" title="'.$lang->browse['dupload'].'">
<span class="badge bg-dark">x2</span></a>




' : '');



require_once 'details_edit.php';


$HEAD = sprintf($lang->details['detailsfor'], $torrent['name']);
stdhead($HEAD, true, 'supernote','INDETAILS', '');





echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/details.css">';
echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/animate.min.css">';



echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/details_modal.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/edit_torrent.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/tooltip.js"></script>';






$gigs = $CURUSER['downloaded'] / (1024*1024*1024);





if ($hitrun == 'yes')
{
	$ratio = ($CURUSER['downloaded'] > 0 ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0);
	$percentage = $ratio * 100;
	
	if ($torrent['free'] != 'yes' AND $usergroups['isvipgroup'] != 'yes' AND $ratio <= ($hitrun_ratio + 0.4) AND $torrent['owner'] != $CURUSER['id'] AND !$is_mod AND $CURUSER['downloaded'] <> 0)
	{
		
		$warning_message = '<div class="container mt-3">
           <div class="red_alert mb-3" role="alert">
                '.sprintf($lang->details['downloadwarning'], number_format($ratio, 2), mksize($percentage), $hitrun_ratio).'
                </div>
            </div>';
		
	}
}


if (isset($warning_message))
{
	echo $warning_message;
}



$sratio = $torrent['leechers'] > 0 ? $torrent['seeders'] / $torrent['leechers'] : 1;
$lratio = $torrent['seeders'] > 0 ? $torrent['leechers'] / $torrent['seeders'] : 1;




$showcommenttable = '';

$threadcount = 0;


$query = $db->simple_select("comments c", "COUNT(id) AS commentss", "torrent = '$id'");
$threadcount = $db->fetch_field($query, "commentss");

if (!$threadcount)
{
	$showcommenttable .= '
	
	<div class="container mt-3">
       <div class="card border-0 mb-4">
	      <div class="card-header rounded-bottom text-19 fw-bold">
		  
		  
		  
		  <div style="display: block;" id="ajax_comment_preview">'.$lang->details['nocommentsyet'].'</div>
		  <div style="display: block;" id="ajax_comment_preview2"></div>
		  
		  
		  
		  
		  
		  
		  
	      </div>
	   </div>
	</div>';
	 
	
}
else
{
	

		$multipage = '';

        // Figure out if we need to display multiple pages.
		$page = 1;
		$perpage = $ts_perpage;
		if(isset($mybb->input['page']) && $mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

  
if (!empty($mybb->input['pid'])) 
{
    $post = get_comment($mybb->input['pid']);
    if ($post) 
	{
        
        $query = "
            SELECT COUNT(c.dateline) AS count 
            FROM comments c
            WHERE c.torrent = ? 
              AND c.dateline <= ?
        ";
        $params = [$id, $post['dateline']];

        
        $res = $db->sql_query_prepared($query, $params);

        if ($res) 
		{
           
            $result = $db->fetch_field($res, "count");

            if (($result % $perpage) == 0) {
                $page = $result / $perpage;
            } 
			else 
			{
                $page = intval($result / $perpage) + 1;
            }
        }
    }
}


        
  
        $query = $db->simple_select("comments c", "COUNT(*) AS replies", "c.torrent='$id'");
        $thread['replies'] = $db->fetch_field($query, 'replies')-1;
			
				
        $postcount = intval($thread['replies'])+1;
		$pages = $postcount / $perpage;
		$pages = ceil($pages);

		if(isset($mybb->input['page']) && $mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$upper = $start+$perpage;
		
		
		$postcounter = "";
		
		if(!$postcounter)
	    { // Used to show the # of the post
		if($page > 1)
		{
			if(!$ts_perpage || (int)$ts_perpage < 1)
			{
				$ts_perpage = 20;
			}

			$postcounter = $ts_perpage*($page-1);
		}
		else
		{
			$postcounter = 0;
		}
		
	    }
		
		
		$multipage = multipage($postcount, $perpage, $page, str_replace("{id}", $id, TORRENT_URL_PAGED));


	
	






$allrows = [];

$query = "
    SELECT 
        c.id, c.torrent AS torrentid, c.text, c.user, c.editreason, c.dateline, c.editedby, c.editedat, c.totalvotes, 
        uu.username AS editedbyuname, gg.namestyle AS editbynamestyle, 
        u.added AS registered, u.enabled, u.lastactive, u.lastvisit, u.invisible, u.warned, u.leechwarn, u.username, u.usertitle, 
		u.usergroup, u.displaygroup, u.postnum, u.threadnum, u.added, u.comms,u.donor, u.uploaded, u.downloaded, 
		u.avatar AS useravatar, u.avatardimensions, u.signature, 
        g.title AS grouptitle, g.namestyle 
    FROM comments c
    LEFT JOIN users uu ON (c.editedby = uu.id)
    LEFT JOIN usergroups gg ON (uu.usergroup = gg.gid)
    LEFT JOIN users u ON (c.user = u.id)
    LEFT JOIN usergroups g ON (u.usergroup = g.gid)
    WHERE c.torrent = ?
    ORDER BY c.id
    LIMIT ?, ?
";

// Параметры запроса
$params = [
    (int)$id, 
    (int)$start, 
    (int)$perpage
];

$subres = $db->sql_query_prepared($query, $params);

if ($subres && isset($subres)) 
{
    while ($subrow = $db->fetch_array($subres)) 
	{
        $allrows[] = $subrow;
    }
    $db->free_result($subres);
}










$showcommenttable .= '<div class="container mt-3">'.$multipage.'</div>'.commenttable($allrows,'','',false,true,true).'<div class="container mt-3">'.$multipage.'</div>';

	

	
	
	
	
}

$rowspan = 9;
$reseed = '';
if ($torrent['seeders'] == 0 && $torrent['ts_external'] == 'no')
{
	$reseed = '
	<tr>
		<td style="padding-left: 5px;" class="trow2" valign="top" width="147">'.$lang->details['askreseed'].'</td>
		<td valign="top" style="padding-left: 5px;">'.sprintf($lang->details['askreseed2'], $id).'</td>
	</tr>';
	$rowspan++;
}



if (isset($_GET['cerror']))
{
	switch ($_GET['cerror'])
	{
		case 1:
			$cerror = $lang->global['notorrentid'];
		break;
		case 2:
			$cerror = $lang->global['dontleavefieldsblank'];
		break;
		case 3:
			$cerror = sprintf($lang->global['flooderror'], $usergroups['floodlimit'], $lang->comment['floodcomment'], "-");
		break;
		default:
			$cerror = $lang->global['error'];
		break;
	}
}

if ($torrent['ts_external'] == 'yes')
{
	$peerstable = sprintf($lang->details['peers3'], ts_nf($torrent['seeders']), ts_nf($torrent['leechers']), (ts_nf($torrent['seeders'] + $torrent['leechers']))).($torrent['seeders'] == 0 && $torrent['ts_external'] == 'no' ? '<br />'.sprintf($lang->details['askreseed2'],$id) : '');
}
else
{
	function getagent($httpagent='', $peer_id="")
	{
		global $lang;
		return ($httpagent ? $httpagent : ($peer_id ? $peer_id : $lang->global['unknown']));
	}









function dltable($name, $arr, $torrent, $is_seeders = false)
{
    global $CURUSER, $pic_base_url, $lang, $usergroups, $is_mod, $BASEURL, $templates;

    $totalcount = is_array($arr) ? count($arr) : 0;
    
    // Заголовок секции
    $p = '<div class="d-flex align-items-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                <i class="bi bi-people-fill text-primary fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">' . htmlspecialchars($name) . '</h4>
                <p class="text-muted mb-0"><i class="bi bi-person-check me-1"></i>' . htmlspecialchars($totalcount) . ' active peers</p>
            </div>
          </div>';

    if ($totalcount <= 0) {
        $p .= '<div class="text-center py-5 bg-light rounded-3">
                 <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                 <p class="text-muted fs-5">No peers currently connected</p>
               </div>';
        return $p;
    }

    $now = TIMENOW;
    include_once(INC_PATH . '/functions_ratio.php');

    // Заголовки таблицы ТОЛЬКО для первой таблицы (сидов)
    $table_headers = '';
    if ($is_seeders) {
        $userLabel       = '<i class="bi bi-person-circle me-2"></i>' . (isset($lang->global['user']) ? $lang->global['user'] : 'User');
        $connectableLabel= '<i class="bi bi-wifi me-2"></i>' . (isset($lang->details['connectable']) ? $lang->details['connectable'] : 'Connectable');
        $uploadedLabel   = '<i class="bi bi-cloud-arrow-up me-2"></i>' . (isset($lang->details['uploaded']) ? $lang->details['uploaded'] : 'Uploaded');
        $upRateLabel     = '<i class="bi bi-speedometer me-2"></i>' . (isset($lang->details['rate']) ? $lang->details['rate'] : 'Up Rate');
        $downloadedLabel = '<i class="bi bi-cloud-arrow-down me-2"></i>' . (isset($lang->details['downloaded']) ? $lang->details['downloaded'] : 'Downloaded');
        $downRateLabel   = '<i class="bi bi-speedometer2 me-2"></i>' . (isset($lang->details['rate']) ? $lang->details['rate'] : 'Down Rate');
        $ratioLabel      = '<i class="bi bi-percent me-2"></i>' . (isset($lang->details['ratio']) ? $lang->details['ratio'] : 'Ratio');
        $completedLabel  = '<i class="bi bi-check-circle me-2"></i>' . (isset($lang->details['completed']) ? $lang->details['completed'] : 'Completed');
        $connectedLabel  = '<i class="bi bi-clock-history me-2"></i>' . (isset($lang->details['connected']) ? $lang->details['connected'] : 'Connected');
        $idleLabel       = '<i class="bi bi-hourglass-split me-2"></i>' . (isset($lang->details['idle']) ? $lang->details['idle'] : 'Idle');
        $clientLabel     = '<i class="bi bi-laptop me-2"></i>' . (isset($lang->details['client']) ? $lang->details['client'] : 'Client');

        $table_headers = <<<HTML
        <thead class="bg-gradient-primary text-white">
            <tr>
                <th scope="col" class="ps-4 py-3 border-0">{$userLabel}</th>
                <th scope="col" class="text-center py-3 border-0">{$connectableLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$uploadedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$upRateLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$downloadedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$downRateLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$ratioLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$completedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$connectedLabel}</th>
                <th scope="col" class="text-end py-3 border-0">{$idleLabel}</th>
                <th scope="col" class="text-start pe-4 py-3 border-0">{$clientLabel}</th>
            </tr>
        </thead>
HTML;
    }

    // Начинаем таблицу
    $s = '<div class="table-responsive rounded-3 shadow-sm border-0">';
    $s .= '<table class="table table-hover table-borderless mb-0" role="grid" aria-label="Peers Table">';
    $s .= $table_headers; // Добавляем заголовки только для первой таблицы
    $s .= '<tbody class="bg-white">';

    // Остальной код функции остается таким же...
    $num = 0;
    foreach ($arr as $e) {
        $num++;

        if (!$is_mod && $CURUSER['id'] != $e['id']) {
            $s .= <<<HTML
<tr class="animate__animated animate__fadeIn">
    <td colspan="11" class="text-center py-4 text-muted">
        <i class="bi bi-eye-slash display-6 opacity-50 mb-2 d-block"></i>
        <span class="fs-5">Anonymous User</span>
    </td>
</tr>
HTML;
            continue;
        }

        $highlight = $CURUSER["id"] == $e["id"] ? ' class="bg-success bg-opacity-10 border-start border-success border-4"' : ' class="border-start border-light border-4"';

        $ip_display = $is_mod
            ? '<div class="mt-1"><small class="badge bg-dark bg-opacity-25 text-dark"><i class="bi bi-globe me-1"></i>' . htmlspecialchars_uni($e["ip"]) . ':<span class="fw-bold">' . (int)$e['port'] . '</span></small></div>'
            : '<div class="mt-1"><small class="badge bg-light text-muted"><i class="bi bi-eye me-1"></i>' . preg_replace('/\.\d+\.\d+$/', '***', htmlspecialchars_uni($e["ip"])) . ':<span class="fw-bold">' . (int)$e['port'] . '</span></small></div>';

        $user_display = empty($e["username"])
            ? '<span class="text-muted"><i class="bi bi-question-circle me-2"></i>Unknown</span>'
            : ($is_mod || $torrent['anonymous'] != 'yes' || $e['id'] != $torrent['owner']
                ? '<div class="d-flex align-items-center">
                      <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                          <i class="bi bi-person text-primary"></i>
                      </div>
                      <div>
                          <a href="' . get_profile_link($e['userid']) . '" class="text-decoration-none fw-bold text-dark" data-bs-toggle="tooltip" title="View profile">' . get_user_color($e["username"], $e["namestyle"]) . '</a>
                          <div class="d-flex align-items-center mt-1">' .
                            ($e["donor"] == "yes" ? '<span class="badge bg-warning me-1" data-bs-toggle="tooltip" title="Donor"><i class="bi bi-star-fill"></i></span>' : '') .
                            ($e["enabled"] == "no" ? '<span class="badge bg-danger me-1" data-bs-toggle="tooltip" title="Disabled"><i class="bi bi-slash-circle"></i></span>' : '') .
                            ($e["warned"] == "yes" ? '<a href="rules.php#warning" class="badge bg-dark text-decoration-none" data-bs-toggle="tooltip" title="Warned"><i class="bi bi-exclamation-triangle"></i></a>' : '') .
                          '</div>
                      </div>
                   </div>' . $ip_display
                : '<span class="text-muted"><i class="bi bi-incognito me-2"></i>Anonymous</span>');

        $secs = max(1, ($now - $e["st"]) - ($now - $e["la"]));
        $upload_rate = mksize(($e["uploaded"] - $e["uploadoffset"]) / $secs) . '/s';
        $download_rate = $e["seeder"] == "no"
            ? mksize(($e["downloaded"] - $e["downloadoffset"]) / $secs) . '/s'
            : mksize(($e["downloaded"] - $e["downloadoffset"]) / max(1, $e["finishedat"] - $e['st'])) . '/s';

        if ($e["downloaded"]) {
            $ratio = floor(($e["uploaded"] / $e["downloaded"]) * 1000) / 1000;
            $ratio_color = get_ratio_color($ratio);
            $ratio_icon = $ratio >= 1.0 ? 'bi-arrow-up-right-circle' : ($ratio >= 0.5 ? 'bi-arrow-right-circle' : 'bi-arrow-down-right-circle');
            $ratio_display = '<span class="d-flex align-items-center justify-content-end"><i class="bi ' . $ratio_icon . ' me-2" style="color: ' . $ratio_color . '"></i><span class="fw-bold" style="color: ' . $ratio_color . '">' . number_format($ratio, 2) . '</span></span>';
        } elseif ($e["uploaded"]) {
            $ratio_display = '<span class="badge bg-success"><i class="bi bi-infinity me-1"></i>∞</span>';
        } else {
            $ratio_display = '<span class="text-muted">---</span>';
        }

        $connect_icon = $e['connectable'] == 'yes' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
        $connect_label = $e['connectable'] == 'yes' ? 'Yes' : 'No';
        $connect_display = '<div class="d-flex align-items-center justify-content-center"><i class="bi ' . $connect_icon . ' me-2"></i><span class="fw-semibold">' . $connect_label . '</span></div>';

        $uploadedSize = '<span class="fw-bold text-success">' . mksize($e["uploaded"]) . '</span>';
        $downloadedSize = '<span class="fw-bold text-info">' . mksize($e["downloaded"]) . '</span>';
        $completedPerc = sprintf("%.2f%%", 100 * (1 - ($e["to_go"] / $torrent["size"])));
        
        $progress_width = min(100, (1 - ($e["to_go"] / $torrent["size"])) * 100);
        $completed_display = '
            <div class="d-flex align-items-center justify-content-end">
                <div class="me-3 text-end">
                    <span class="fw-bold">' . $completedPerc . '</span>
                </div>
                <div class="progress" style="width: 60px; height: 6px;">
                    <div class="progress-bar bg-success" style="width: ' . $progress_width . '%"></div>
                </div>
            </div>';

        
        
        $agentInfo = htmlspecialchars_uni(getagent($e["agent"], $e["peer_id"]));
        $client_icon = strpos(strtolower($agentInfo), 'qbittorrent') !== false ? 'bi-download' : 
                      (strpos(strtolower($agentInfo), 'utorrent') !== false ? 'bi-cloud-download' : 'bi-laptop');
        $client_display = '<div class="d-flex align-items-center"><i class="bi ' . $client_icon . ' me-2 text-muted"></i><small>' . $agentInfo . '</small></div>';


		$startTime = '<span class="badge bg-primary bg-opacity-75 text-white"><i class="bi bi-clock-history me-1"></i>' . mkprettytime($now - $e["st"]) . '</span>';
        $lastActive = '<span class="badge bg-warning bg-opacity-75 text-white"><i class="bi bi-hourglass-split me-1"></i>' . mkprettytime($now - $e["la"]) . '</span>';
        $upload_rate_display = '<span class="badge bg-success bg-opacity-75 text-white"><i class="bi bi-speedometer me-1"></i>' . $upload_rate . '</span>';
        $download_rate_display = '<span class="badge bg-info bg-opacity-75 text-white"><i class="bi bi-speedometer2 me-1"></i>' . $download_rate . '</span>';
		
		
		
		
		
		
		
		

        $s .= <<<HTML
<tr{$highlight}>
    <td class="ps-4 py-3">{$user_display}</td>
    <td class="text-center py-3">{$connect_display}</td>
    <td class="text-end py-3">{$uploadedSize}</td>
    <td class="text-end py-3">{$upload_rate_display}</td>
    <td class="text-end py-3">{$downloadedSize}</td>
    <td class="text-end py-3">{$download_rate_display}</td>
    <td class="text-end py-3">{$ratio_display}</td>
    <td class="text-end py-3">{$completed_display}</td>
    <td class="text-end py-3">{$startTime}</td>
    <td class="text-end py-3">{$lastActive}</td>
    <td class="pe-4 py-3">{$client_display}</td>
</tr>
HTML;
    }

    $s .= '</tbody></table></div>';
    
    // Статистику показываем только для первой таблицы
    if ($is_seeders) {
        $seeders_count = array_filter($arr, function($e) { return $e['seeder'] == 'yes'; });
        $leechers_count = array_filter($arr, function($e) { return $e['seeder'] == 'no'; });
        
        $s .= '';
    }
    
    return $p . $s;
}





	



$seeders = [];
$downloaders = [];

// Запрос с ?
$query = "
    SELECT p.seeder, p.finishedat, p.downloadoffset, p.uploadoffset, p.ip, p.port, p.uploaded, p.downloaded, p.to_go, 
           p.started AS st, p.connectable, p.agent, p.peer_id, p.last_action AS la, p.userid,  
           u.id, u.enabled, u.username, u.displaygroup, u.warned, u.donor, g.namestyle 
    FROM peers p
    LEFT JOIN users u ON (p.userid=u.id)
    LEFT JOIN usergroups g ON (u.usergroup=g.gid)
    WHERE p.torrent = ?
";

// Параметры
$params = [$id];

$subres = $db->sql_query_prepared($query, $params);

// Проверка результата
if ($subres && $db->num_rows($subres) > 0) 
{
    while ($subrow = $db->fetch_array($subres)) 
	{
        if ($subrow['seeder'] === 'yes') 
		{
            $seeders[] = $subrow;
        } 
		else 
		{
            $downloaders[] = $subrow;
        }
    }
}











	function leech_sort($a,$b)
	{
		if ( isset( $_GET["usort"] ) ) return seed_sort($a,$b);
		$x = $a["to_go"];
		$y = $b["to_go"];
		if ($x == $y)
			return 0;
		if ($x < $y)
			return -1;
		return 1;
	}
	function seed_sort($a,$b)
	{
		$x = $a["uploaded"];
		$y = $b["uploaded"];
		if ($x == $y)
			return 0;
		if ($x < $y)
			return 1;
		return -1;
	}

	usort($seeders, "seed_sort");
	usort($downloaders, "leech_sort");

	// И исправьте вызовы функций:
$peerstable = dltable($lang->details['seeders2'], $seeders, $torrent, true); // true - это сиды
$peerstable .= dltable($lang->details['leechers2'], $downloaders, $torrent, false); // false - это личи
}





		



// Подключите функцию insert_bbcode_editor
require_once INC_PATH . '/editor.php';


// Вызов функции
$editor = insert_bbcode_editor($smilies, $BASEURL, 'message');



			
// Формирование HTML
$showcommenttable .= '
<br />
<div class="container mt-4">
    <h2 class="mb-3">Quick Comment</h2>
    '.(!empty($cerror) ? '<div class="error">'.$cerror.'</div>' : '').'
	' . ($useajax == 'yes' ? '<script src="' . $BASEURL . '/scripts/quick_comment.js"></script>' : '') . '
    ' . $editor['toolbar'] . '
    <form name="comment" id="comment" method="post" action="comment.php?action=add&tid=' . $id . '" novalidate>
        <input type="hidden" name="ctype" value="quickcomment">
        <input type="hidden" name="page" value="' . intval(isset($_GET['page']) ? $_GET['page'] : 0) . '">
        <div id="fileIdsContainer"></div>
        <div class="mb-3">
            <label for="message" class="form-label">Your Comment <small class="text-muted">(макс. 500 символов)</small></label>
            <textarea class="form-control" id="message" name="message" rows="6" placeholder="Write a comment, use BBCode..." maxlength="500" aria-describedby="charCount" required></textarea>
            <div id="charCount" class="form-text text-end">0 / 500</div>
        </div>
        <div id="message_preview" class="form-control mt-3 d-none"></div>
        ' . ($useajax == 'yes' ? '
        <div class="d-flex align-items-center justify-content-center mb-3">
            <i id="loading-layer" class="fa-solid fa-circle-notch fa-spin" aria-label="Loading..." style="display:none; color: #0b59e0; width:24px; height:24px; margin-right: 10px;"></i>
            <button type="button" class="btn btn-primary me-2" id="quickcomment" onclick="TSajaxquickcomment(\'' . $id . '\');">' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid='.$id.'" class="btn btn-secondary">' . $lang->global['advancedbutton'] . '</a>	
        </div>' : '
        <div class="d-flex gap-2 justify-content-center mb-3">
            <button type="submit" name="submit" class="btn btn-primary">' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid='.$id.'" class="btn btn-secondary">' . $lang->global['advancedbutton'] . '</a>
        </div>') . '
    </form>
    ' . $editor['modal'] . '
</div>';








if($torrent['anonymous'] == 'yes' AND $torrent['owner'] != $CURUSER['id'] AND !$is_mod)
{
		$username = $lang->global['anonymous'];
}
else
{
	$username = '<a href="'.get_profile_link($torrent['owner']).'">'.format_name($torrent2['username'], $torrent2['usergroup']).'</a>' . get_user_icons ($torrent2) .'';
}








$ShowTLINK = '';

if (!empty($torrent['t_link'])) 
{
    require_once INC_PATH . '/functions_imdb_rating.php';

    $html = $torrent['t_link']; // исходный HTML блока
    $hasHtml = (strpos($html, '<') !== false && strpos($html, '>') !== false);

    // 1) Пытаемся вытащить IMDb URL из блока
    $imdbUrl = null;
    if (preg_match('#https?://www\.imdb\.com/title/(tt\d+)/#i', $html, $m)) 
	{
        $imdbUrl = $m[0]; // полная ссылка с ttXXXXXXX
    }

    // 2) Получаем картинку рейтинга (передаём URL, а не HTML!)
    $IMDBRating = $imdbUrl ? TSSEGetIMDBRatingImage($imdbUrl) : false;

    if ($IMDBRating && !empty($IMDBRating['image'])) 
	{
        // 3) Вклеиваем картинку после "User Rating:" — гибкий паттерн
        $pattern = '#(<b>\s*User\s*Rating:\s*</b>\s*)#i';
        if (preg_match($pattern, $html)) 
		{
            $html = preg_replace($pattern, '$1' . $IMDBRating['image'] . ' ', $html, 1);
        } 
		else 
		{
            // если маркера нет — добавим блок в начало рейтингового раздела или просто в начало
            $html = $IMDBRating['image'] . ' ' . $html;
        }
    } 
	else 
	{
        // 4) НЕ трогаем размеченный HTML!
        if (!$hasHtml) 
		{
            // если вдруг тут голый текст — можно автолинковать
            $html = format_urls($html, '_blank');
        }
    }

    // 5) Возвращаем собранное
    $torrent['t_link'] = $html;

    // $refresh только для модераторов (если нужно в шаблоне)
    $refresh = !empty($is_mod) ? ($lang->global['refresh'] ?? '') : '';

    eval("\$ShowTLINK = \"".$templates->get("imdb_table")."\";");
}

		



			

$show_manage = '';
if ($CURUSER['id'] === $torrent['owner'] OR $is_mod)
{
    
	
	
	
	

$show_manage .= '
<div class="dropdown d-inline-block">
    <!-- Компактная кнопка с иконкой -->
    <a href="#" class="btn btn-light btn-icon rounded-circle p-2 shadow-sm" 
       role="button" id="manageCompactDropdown" data-bs-toggle="dropdown" aria-expanded="false"
       data-bs-toggle="tooltip" title="Manage Torrent">
        <i class="bi bi-three-dots-vertical text-primary"></i>
    </a>
    
    <!-- Компактное меню -->
    <ul class="dropdown-menu shadow border-0 rounded-2 p-1" aria-labelledby="manageCompactDropdown">
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1" 
               href="#" data-bs-toggle="modal" data-bs-target="#add_data_Modal">
                <i class="bi bi-pencil-square text-success me-2"></i>
                <span>Quick Edit</span>
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1" 
               href="upload.php?id='.$id.'">
                <i class="bi bi-file-earmark-text text-info me-2"></i>
                <span>Full Edit</span>
            </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1 text-danger" 
               href="delete.php?id='.$id.'" onclick="return confirm(\'Are you sure?\')">
                <i class="bi bi-trash3 me-2"></i>
                <span>Delete</span>
            </a>
        </li>
    </ul>
</div>

<style>
.btn-icon {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.btn-icon:hover {
    transform: scale(1.1);
    border-color: #007bff;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.dropdown-item {
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background: #007bff;
    color: white;
    margin-left: 5px;
    margin-right: -5px;
    border-radius: 6px;
}
</style>
';

	
	
	
	
	
	
  	
}


if ($is_mod)
{
	
	
	
	
	
	
$show_manage .= '
	
	
 <a href="'.$BASEURL.'/admin/index.php?act=hit_and_run&torrentid=='.$id.'">
 <i class="fa-solid fa-person-running fa-xl" style="color: #161718;" alt="Hit & Run" title="Hit & Run"></i></a>
	 
  
  <a href="'.$BASEURL.'/comment.php?tid='.$id.'&action='.($torrent['allowcomments'] != 'yes' ? 'open' : 'close').'"  onmouseout="window.status=\'\'; return true;" onMouseOver="window.status=\''.($torrent['allowcomments'] == 'no' ? $lang->details['open'] : $lang->details['close']).'\'; return true;">'.($torrent['allowcomments'] != 'yes' ? 
  
  '<i class="fa-solid fa-comment-slash fa-xl" style="color: #e91b0c;" alt="'.$lang->details['open'].'" title="'.$lang->details['open'].'"></i>' : 
  
  '<i class="fa-solid fa-comment-slash fa-xl" style="color: #08e74b;" alt="'.$lang->details['close'].'" title="'.$lang->details['close'].'"></i>').'</a>
	
  
	
<a href="'.$BASEURL.'/admin/index.php?act=torrent_info&amp;id='.$id.'"><i class="fa-sharp fa-solid fa-info fa-xl" style="color: #94b4eb;" alt="Torrent Info" title="Torrent Info"></i></a>
  
	
<a href="'.$BASEURL.'/admin/index.php?act=fastdelete&amp;id='.$id.'"><i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;" alt="Delete Torrent" title="Delete Torrent"></i></a>';
	
	
	
	


}

  


















if (is_file(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent") && ($Data = file_get_contents(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent"))) 
{
    
	
	$torrentPath = TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent";
    
    // Загружаем торрент
    $torrentObj = TorrentFile::load($torrentPath);

    $files = $torrentObj->v1()->getFiles();
	

    // Step 1: Build folder structure
    $tree = [];



foreach ($files as $file) 
{
    $path = str_replace('\\', '/', implode('/', $file->path)); // ✅ тут исправлено
    $size = $file->length;
    $parts = explode('/', $path);
    $current = &$tree;

    foreach ($parts as $i => $part) 
	{
        if ($i === count($parts) - 1) 
		{
            $current[$part] = $size; // file
        } 
		else 
		{
            if (!isset($current[$part])) 
			{
                $current[$part] = [];
            }
            $current = &$current[$part]; // folder
        }
    }
}






    // Step 2: Icon helper
	
	function getFileIcon($filename) 
    {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'video' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mpeg', 'mpg', '3gp', 'm4v', 'vob', 'ts', 'm2ts', 'ogv', 'rm', 'rmvb'],
        'audio' => ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        'doc' => ['nfo', 'txt', 'md', 'log', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'code' => ['php', 'html', 'css', 'js', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'sh', 'bat'],
        'exec' => ['exe', 'iso', 'apk', 'bin', 'dll', 'app', 'deb', 'rpm'],
    ];

    $colors = [
        'video' => 'bg-danger',    // red
        'audio' => 'bg-primary',   // blue
        'image' => 'bg-success',   // green
        'archive' => 'bg-warning', // orange
        'doc' => 'bg-secondary',   // gray
        'code' => 'bg-info',       // cyan/light blue
        'exec' => 'bg-dark',       // black/dark
        'default' => 'bg-secondary'
    ];

    $iconClasses = [
        'video' => 'fa-solid fa-file-video',
        'audio' => 'fa-solid fa-file-audio',
        'image' => 'fa-solid fa-file-image',
        'archive' => 'fa-solid fa-file-zipper',
        'doc' => 'fa-solid fa-file-lines',
        'code' => 'fa-solid fa-file-code',
        'exec' => 'fa-solid fa-microchip',
        'default' => 'fa-solid fa-file',
    ];

    $type = 'default';
    foreach ($icons as $key => $exts) 
	{
        if (in_array($ext, $exts)) 
		{
            $type = $key;
            break;
        }
    }

    $colorClass = $colors[$type] ?? $colors['default'];
    $iconClass = $iconClasses[$type] ?? $iconClasses['default'];

    return '<span class="badge rounded-pill d-inline-flex align-items-center px-2 py-1 ' . $colorClass . ' text-white" aria-label="'.htmlspecialchars(strtoupper($ext)).'" title="'.htmlspecialchars(strtoupper($ext)).'">
                <i class="' . $iconClass . ' me-1" style="font-size: 1em;"></i>
                <small style="line-height:1;">' . htmlspecialchars(strtoupper($ext)) . '</small>
            </span>';
    }

	
	

    // Step 3: Recursive render function
    function renderAccordion($tree, $parentId = 'root', $level = 0) 
	{
        static $counter = 0;
        $html = '<div class="accordion" id="accordion-' . $parentId . '">';
        foreach ($tree as $name => $content) 
		{
            if (is_array($content)) 
			{
                $accordionId = 'item-' . (++$counter);
				$showClass = '';           // ВСЕ папки свернуты по умолчанию
                $buttonCollapsed = 'collapsed';
                $ariaExpanded = 'false';
				
                $html .= '
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-' . $accordionId . '">
                        <button class="accordion-button ' . $buttonCollapsed . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $accordionId . '" aria-expanded="' . $ariaExpanded . '" aria-controls="collapse-' . $accordionId . '">
                        📁 ' . htmlspecialchars($name) . '
                        </button>
                    </h2>
                    <div id="collapse-' . $accordionId . '" class="accordion-collapse collapse ' . $showClass . '" aria-labelledby="heading-' . $accordionId . '" data-bs-parent="#accordion-' . $parentId . '">
                        <div class="accordion-body">
                        ' . renderAccordion($content, $accordionId, $level + 1) . '
                        </div>
                    </div>
                </div>';
            } 
			else 
			{
                // File with icon and size
                $icon = getFileIcon($name);
                $html .= '<div class="ms-3 py-1"> ' . $icon . ' ' . htmlspecialchars($name) . ' <span class="badge bg-secondary">' . mksize($content) . '</span></div>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    //echo renderAccordion($tree);
}






// Descr
$parsedDescr = $parser->parse_message($torrent['descr'], $parser_options);






// Скрины
$screenshots = [];

// Подготовленный запрос
$query = "SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY id ASC";
$params = [$id];

$res = $db->sql_query_prepared($query, $params);

// Проверка результата
if ($res && isset($res)) 
{
    while ($row = $db->fetch_array($res)) 
	{
        $screenshots[] = $row;
    }
}



// Формирование HTML
$screensHtml = '<div class="row g-3">';
foreach ($screenshots as $shot) {
    $filename = htmlspecialchars($shot['filename']);
    $screenshotUrl = '/torrents/screens/' . $filename;

    $screensHtml .= '
    <div class="col-6 col-md-4 col-lg-3">
        <a href="#"
           class="screenshot-wrapper d-block position-relative overflow-hidden rounded-4"
           data-bs-toggle="modal"
           data-bs-target="#universalImageModal"
           data-img-src="' . $screenshotUrl . '"
           data-title="Screenshot">
            <img src="' . $screenshotUrl . '"
                 class="img-fluid rounded-4 transition-scale"
                 alt="Screenshot">
        </a>
    </div>';
}
$screensHtml .= '</div>';





$descr = $parsedDescr;

if (!empty($screenshots)) 
{
    $descr .= '
        <br>
		<br>
        <h5 class="mb-3"><i class="fa-solid fa-image me-2"></i>Screenshots</h5>
        ' . $screensHtml;
}












// Пример вывода t_image и t_image2 с одним модалом
$modal_images = ''; // инициализация
$images = [];

if (!empty($torrent['t_image'])) 
{
    $images[] = $torrent['t_image'];
}

if (!empty($torrent['t_image2'])) 
{
    $images[] = $torrent['t_image2'];
}

foreach ($images as $img) 
{
    $modal_images .= '
    <a href="#"
       data-bs-toggle="modal"
       data-bs-target="#universalImageModal"
       data-img-src="' . htmlspecialchars_uni($img) . '"
       data-title="' . htmlspecialchars_uni($torrent['name']) . '">
        <img src="' . htmlspecialchars_uni($img) . '"
             class="rounded"
             width="400"
             alt="' . htmlspecialchars_uni($torrent['name']) . '">
    </a>
    ';
}

















// Добавьте эти вспомогательные функции перед выводом HTML
function getHealthColor($seeders, $leechers) {
    if ($seeders == 0) return 'danger'; // Нет сидов - красный
    if ($seeders >= 10) return 'success'; // Много сидов - зеленый
    if ($seeders >= 3) return 'warning'; // Среднее количество - желтый
    return 'danger'; // Мало сидов - красный
}

function getHealthPercentage($seeders, $leechers) {
    $total = $seeders + $leechers;
    if ($total == 0) return 0;
    return round(($seeders / $total) * 100);
}

function getSeederPercentage($seeders, $leechers) {
    $total = $seeders + $leechers;
    if ($total == 0) return 0;
    return round(($seeders / $total) * 100);
}

function getLeecherPercentage($seeders, $leechers) {
    $total = $seeders + $leechers;
    if ($total == 0) return 0;
    return round(($leechers / $total) * 100);
}











// Теперь основная часть HTML


$details = '


<!-- Основной контейнер -->
<div id="torrent_details" class="container mt-5">
    <!-- Хлебные крошки с плавной анимацией -->
    <nav aria-label="breadcrumb" class="mb-4 animate__animated animate__fadeIn">
        <ol class="breadcrumb bg-light p-3 rounded-3 shadow-sm">
            <li class="breadcrumb-item">
                <a href="/" class="text-decoration-none text-primary">
                    <i class="bi bi-house-door me-1"></i> Home
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="browse.php" class="text-decoration-none text-primary">
                    <i class="bi bi-folder me-1"></i> Browse
                </a>
            </li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page">
                '.htmlspecialchars_uni(mb_substr($torrent['name'], 0, 50)).'...
            </li>
        </ol>
    </nav>

    <!-- Заголовок с улучшенным дизайном -->
    <div class="torrent-header mb-5">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div class="flex-grow-1">
                <h1 class="h3 mb-3 fw-bold text-dark animate__animated animate__fadeInDown">
                    <span class="status-badges me-2">'.$isfree.$issilver.$isdoubleupload.'</span>
                    '.htmlspecialchars_uni($torrent['name']).'
                </h1>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-light text-dark border shadow-sm">
                        <i class="bi bi-hash me-1"></i>ID: '.$id.'
                    </span>
                    <span class="badge bg-'.getHealthColor($torrent['seeders'], $torrent['leechers']).' shadow-sm">
                        <i class="bi bi-activity me-1"></i>Health: '.getHealthPercentage($torrent['seeders'], $torrent['leechers']).'%
                    </span>
                </div>
            </div>
            <div class="flex-shrink-0 ms-3">
                
            </div>
        </div>
    </div>

    <!-- Карточка загрузки с градиентом и анимацией -->
    <div class="card shadow-lg border-0 mb-5 animate__animated animate__zoomIn">
        <div class="card-header bg-gradient bg-primary text-white py-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-0 fw-semibold">
                        <i class="bi bi-cloud-download me-2"></i>Download Torrent
                    </h4>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group" role="group">
                        <a href="'.get_download_link($id).'"
                           class="btn btn-light btn-lg d-flex align-items-center shadow-sm"
                           alt="'.$lang->details['dltorrent'].'"
                           title="'.$lang->details['dltorrent'].'">
                            <i class="bi bi-download me-2"></i>Download
                        </a>
                        <button type="button" class="btn btn-outline-light dropdown-toggle dropdown-toggle-split"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu shadow">
                            <li><a class="dropdown-item" href=""><i class="bi bi-magnet me-2"></i>Magnet Link</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-share me-2"></i>Share</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#reportModal" data-bs-toggle="modal"><i class="bi bi-flag me-2"></i>Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Тело карточки -->
        <div class="card-body p-4">
           
                        '.$modal_images.'
                  

            <!-- Прогресс-бары с улучшенным стилем -->
            <div class="stats-progress mb-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            
                            <span class="small fw-bold text-success">'.ts_nf($torrent['seeders']).'</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 8px;">
                            <div class="progress-bar bg-success rounded-pill" style="width: '.getSeederPercentage($torrent['seeders'], $torrent['leechers']).'%"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            
                            <span class="small fw-bold text-warning">'.ts_nf($torrent['leechers']).'</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 8px;">
                            <div class="progress-bar bg-warning rounded-pill" style="width: '.getLeecherPercentage($torrent['seeders'], $torrent['leechers']).'%"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-muted">Downloads</span>
                            <span class="small fw-bold text-info">'.ts_nf($torrent['times_completed']).'</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 8px;">
                            <div class="progress-bar bg-info rounded-pill" style="width: '.min(100, $torrent['times_completed']).'%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Табы с информацией -->
    <div class="card shadow-sm border-0 mb-5 animate__animated animate__fadeInUp">
        <div class="card-header bg-light p-0">
            <ul class="nav nav-tabs nav-fill" id="torrentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button" role="tab">
                        <i class="bi bi-folder me-2"></i>Files ('.ts_nf($torrent['numfiles']).')
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="peers-tab" data-bs-toggle="tab" data-bs-target="#peers" type="button" role="tab">
                        <i class="bi bi-people me-2"></i>Peers ('.ts_nf($torrent['seeders'] + $torrent['leechers']).')
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="torrentTabsContent">
                <!-- Вкладка информации -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="info-grid">
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-calendar me-1"></i>Uploaded</span>                                   
									<span class="fw-bold">
                                        '.my_datee($dateformat, $torrent['added']).'
                                        <small class="text-muted ms-2">'.my_datee($timeformat, $torrent['added']).'</small>
                                    </span>									
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-tag me-1"></i>Category</span>
                                    <span class="fw-bold">'.$torrent2['categoryname'].'</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-hdd me-1"></i>Size</span>
                                    <span class="fw-bold">'.mksize($torrent['size']).'</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-hash me-1"></i>Hash</span>
                                    <span class="font-monospace small">'.($torrent['info_hash'] ?? 'N/A').'</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-grid">
                                
								 <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-download me-1"></i>Downloads</span>
                                    <span class="badge bg-light text-dark">
									<a href="viewsnatches.php?id='.$id.'">'.ts_nf($torrent['times_completed']).'</span></a>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-eye me-1"></i>Views</span>
                                    <span class="badge bg-light text-dark">'.ts_nf($torrent['hits']).'</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-chat me-1"></i>Comments</span>
                                    <span class="badge bg-light text-dark">'.ts_nf($torrent['comments']).'</span>
                                </div>
								
								
								
								
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-person me-1"></i>Uploader</span>
                                    <span class="fw-bold">'.$username.'</span>
                                </div>
                                '.($show_manage != '' ? '
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-gear me-1"></i>Manage Torrent</span>
                                    <span class="fw-bold">'.$show_manage.'</span>
                                </div>' : '').'
                            </div>
                        </div>
                    </div>

                    <!-- Теги с анимацией при наведении -->
                    '.($keywords ? '
                    <div class="mt-4">
                        <h6 class="fw-semibold"><i class="bi bi-tags me-2"></i>Tags</h6>
                        <div class="d-flex flex-wrap gap-2">'.$keywords.'</div>
                    </div>' : '').'
                </div>

                <!-- Вкладка файлов -->
                <div class="tab-pane fade" id="files" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold">File Structure</h6>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary shadow-sm" onclick="expandAllFiles()">Expand All</button>
                            <button type="button" class="btn btn-outline-secondary shadow-sm" onclick="collapseAllFiles()">Collapse All</button>
                        </div>
                    </div>
                    <div class="file-tree">'.renderAccordion($tree).'</div>
                </div>

                <!-- Вкладка пиров -->
                <div class="tab-pane fade" id="peers" role="tabpanel">
                    '.$peerstable.'
                </div>
            </div>
        </div>
    </div>

    <!-- Описание с улучшенным форматированием -->
    <div class="card shadow-sm border-0 mt-5 animate__animated animate__fadeInUp">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-card-text me-2"></i>Description</h5>
        </div>
        <div class="card-body p-4">'.$descr.'</div>
    </div>

    <!-- Дополнительные секции -->
    '.$ShowTLINK.'
    '.$SimilarTorrents.'
    '.$showcommenttable.'
</div>

<!-- Встроенные стили для улучшения дизайна -->
<style>
    /* Плавные переходы для карточек */
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15) !important;
    }

    /* Стили для прогресс-баров */
    .progress {
        background-color: #f1f3f5;
    }
    .progress-bar {
        transition: width 0.6s ease;
    }

    /* Стили для тегов */
    .badge {
        transition: transform 0.2s ease;
    }
    .badge:hover {
        transform: scale(1.05);
    }


    /* Сглаженные анимации для вкладок */
    .nav-tabs .nav-link {
        transition: all 0.3s ease;
        border-radius: 0.25rem;
    }
    .nav-tabs .nav-link:hover {
        background-color: #e9ecef;
    }
    .nav-tabs .nav-link.active {
        background-color: #007bff;
        color: white !important;
        border-color: transparent;
    }

    /* Улучшение хлебных крошек */
    .breadcrumb {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
    }
    .breadcrumb-item a {
        transition: color 0.3s ease;
    }
    .breadcrumb-item a:hover {
        color: #0056b3;
    }
</style>

<!-- Скрипты для улучшенного UX -->
<script>
    // Расширение/сворачивание файлов
    function expandAllFiles() {
        const collapses = document.querySelectorAll(\'.file-tree .collapse\');
        collapses.forEach(el => {
            if (el.classList.contains(\'show\')) return;
            new bootstrap.Collapse(el, { show: true });
        });
    }

    function collapseAllFiles() {
        const collapses = document.querySelectorAll(\'.file-tree .collapse\');
        collapses.forEach(el => {
            if (!el.classList.contains(\'show\')) return;
            new bootstrap.Collapse(el, { hide: true });
        });
    }

    // Анимация загрузки элементов
    document.addEventListener(\'DOMContentLoaded\', function() {
        const elements = document.querySelectorAll(\'.card, .torrent-header\');
        elements.forEach((el, index) => {
            el.style.opacity = \'0\';
            el.style.transform = \'translateY(20px)\';
            setTimeout(() => {
                el.style.transition = \'all 0.5s ease\';
                el.style.opacity = \'1\';
                el.style.transform = \'translateY(0)\';
            }, index * 150);
        });
    });
</script>
';
















echo '




<!-- Universal Image Preview Modal -->
<div class="modal fade" id="universalImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2" id="universalImageModalTitle">
                    <i class="bi bi-image text-primary"></i> Image Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light p-0">
                <div style="position: relative; height: 70vh; overflow: hidden;">
                    <img src="" id="universalImagePreview" class="img-fluid"
                         style="max-height: 100%; max-width: 100%; object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div class="text-start">
                        <span class="text-muted fw-medium" id="universalImageDimensions"></span>
                        <span class="text-muted mx-2">•</span>
                        <span class="text-muted fw-medium" id="universalImageSize"></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="universalFullscreenBtn">
                            <i class="bi bi-arrows-angle-expand me-1"></i> Fullscreen
                        </button>
                        <a href="#" class="btn btn-primary" id="universalDownloadBtn" download>
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>





'.($is_mod ? '
<script type="text/javascript">
	l_updated = "'.$lang->global['imgupdated'].'";
	l_refresh = "'.$lang->global['refresh'].'";
</script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/quick_imdb.js"></script>' : '');







echo $details;




stdfoot();
?>
